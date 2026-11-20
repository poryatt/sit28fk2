#!/usr/bin/env python3
"""Part 10B: per-page semantic / accessibility checks on the RENDERED pages (Playwright, Chromium).

    python tests/browser/check_pages_semantics.py --base-url http://localhost:8080 --user OWNER --password SECRET
    python tests/browser/check_pages_semantics.py --base-url http://127.0.0.1:8099 --no-login --pages /login.php,/modules/articles/index.php

For every page it prints PASS/FAIL with the offending elements:
  S1  exactly one <h1> (the topbar title or the page's own on login / 403)
  S2  headings never skip a level (h1 -> h3 is a fail)
  S3  every visible input / select / textarea has an accessible name (label[for], wrapping label, aria-label, aria-labelledby)
  S4  every icon-only button / link has an accessible name (aria-label, aria-labelledby, title or visible text)
  S5  every <th> in a <thead> has scope
  S6  no element with aria-hidden="true" is keyboard-focusable (inputs hidden with d-none / display:none are fine)
  S7  no duplicated label inside one .nav-pills list (e.g. two "همه" tabs)
  S8  no element carries an inline on*= handler attribute
  S9  every <img> has an alt attribute (alt="" is fine for decorative images)
  S10 the document has lang="fa" and dir="rtl"
Exit code 1 when anything fails.

Portability note (Part 10B deviation, logged in the changelog): the appendix uses sync_playwright, which cannot run on
this copy's Python (3.14.0a3: greenlet ABI break) and Playwright's bundled Chromium cannot be installed offline, so this
copy is an async port that launches the system Chrome. The JS probe and the S1-S10 checks are unchanged.
"""
import argparse
import os
import sys

from playwright.async_api import async_playwright

DEFAULT_PAGES = [
    "/login.php",
    "/modules/dashboard/index.php",
    "/modules/pages/index.php",
    "/modules/articles/index.php",
    "/modules/templates/index.php",
    "/modules/media/index.php",
    "/modules/licenses/index.php",
    "/modules/settings/profile.php",
    "/modules/settings/general.php",
    "/modules/settings/staff.php",
]

JS = r"""
() => {
  const out = {};
  const vis = (el) => { if (el.closest('.d-none, .modal:not(.show), [hidden]')) return false; const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return cs.display !== 'none' && cs.visibility !== 'hidden' && (r.width > 0 || r.height > 0); };
  const text = (el) => (el.textContent || '').replace(/\s+/g, ' ').trim();
  const nameOf = (el) => {
    if (el.getAttribute('aria-label')) return el.getAttribute('aria-label').trim();
    const lb = el.getAttribute('aria-labelledby');
    if (lb) { const t = lb.split(/\s+/).map(i => document.getElementById(i)).filter(Boolean).map(text).join(' ').trim(); if (t) return t; }
    if (el.id) { const l = document.querySelector('label[for="' + CSS.escape(el.id) + '"]'); if (l && text(l)) return text(l); }
    const wrap = el.closest('label'); if (wrap && text(wrap)) return text(wrap);
    if (el.getAttribute('title')) return el.getAttribute('title').trim();
    return '';
  };
  const desc = (el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.getAttribute('name') ? '[name=' + el.getAttribute('name') + ']' : '') + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
  const h1 = document.querySelectorAll('h1'); out.h1 = h1.length;
  const hs = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter(vis).map(h => Number(h.tagName[1]));
  out.skips = []; for (let i = 1; i < hs.length; i++) { if (hs[i] - hs[i - 1] > 1) out.skips.push('h' + hs[i - 1] + '->h' + hs[i]); }
  out.noName = [...document.querySelectorAll('input, select, textarea')].filter(e => !['hidden', 'submit', 'button', 'image', 'reset'].includes(e.type) && vis(e) && !nameOf(e)).map(desc);
  out.iconOnly = [...document.querySelectorAll('button, a[href]')].filter(vis).filter(e => !text(e) && !nameOf(e) && !e.querySelector('img[alt]:not([alt=""])')).map(desc);
  out.thScope = [...document.querySelectorAll('thead th')].filter(th => !th.getAttribute('scope')).map(desc);
  const focusable = 'a[href],button,input,select,textarea,[tabindex]';
  out.hiddenFocusable = [...document.querySelectorAll('[aria-hidden="true"]')].flatMap(h => [h, ...h.querySelectorAll(focusable)]).filter(e => e.matches(focusable) && e.tabIndex >= 0 && vis(e) && !e.disabled).map(desc);
  out.dupTabs = [...document.querySelectorAll('.nav-pills')].flatMap(ul => { const seen = {}; const d = []; ul.querySelectorAll('.nav-link').forEach(a => { const t = text(a).replace(/\s*\(.*\)\s*$/, ''); if (seen[t]) d.push(t); seen[t] = 1; }); return d; });
  out.handlers = [...document.querySelectorAll('*')].filter(e => [...e.attributes].some(a => /^on[a-z]+$/i.test(a.name))).map(desc);
  out.imgAlt = [...document.querySelectorAll('img')].filter(i => !i.hasAttribute('alt')).map(desc);
  out.lang = document.documentElement.getAttribute('lang') + '/' + document.documentElement.getAttribute('dir');
  return out;
}
"""


def find_browser():
    for cand in [
        os.environ.get("CHROME_PATH"),
        r"C:\Program Files\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
        "/usr/bin/google-chrome",
        "/usr/bin/chromium",
    ]:
        if cand and os.path.isfile(cand):
            return cand
    return None


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base-url", required=True)
    ap.add_argument("--user")
    ap.add_argument("--password")
    ap.add_argument("--pages")
    ap.add_argument("--no-login", action="store_true")
    args = ap.parse_args()
    base = args.base_url.rstrip("/")
    pages = [p.strip() for p in args.pages.split(",") if p.strip()] if args.pages else DEFAULT_PAGES
    bad = total = 0

    def check(label, ok, detail=""):
        nonlocal bad, total
        total += 1
        if not ok:
            bad += 1
        print(("PASS " if ok else "FAIL ") + label + ("" if ok or not detail else " — " + detail))

    async with async_playwright() as pw:
        exe = find_browser()
        browser = await pw.chromium.launch(executable_path=exe) if exe else await pw.chromium.launch()
        ctx = await browser.new_context(viewport={"width": 1280, "height": 900}, locale="fa-IR")
        page = await ctx.new_page()
        if not args.no_login:
            await page.goto(base + "/login.php", wait_until="load")
            await page.fill('input[name="identifier"]', args.user or "")
            await page.fill('input[name="password"]', args.password or "")
            await page.click('form button[type="submit"], form button:not([type])')
            await page.wait_for_load_state("load")
        for path in pages:
            await page.goto(base + path, wait_until="load")
            await page.wait_for_timeout(400)
            r = await page.evaluate(JS)
            check(f"S1 {path} exactly one h1", r["h1"] == 1, f"found {r['h1']}")
            check(f"S2 {path} heading levels do not skip", not r["skips"], ", ".join(r["skips"]))
            check(f"S3 {path} every field has a name", not r["noName"], ", ".join(r["noName"][:5]))
            check(f"S4 {path} icon-only controls are named", not r["iconOnly"], ", ".join(r["iconOnly"][:5]))
            check(f"S5 {path} th have scope", not r["thScope"], f"{len(r['thScope'])} without scope")
            check(f"S6 {path} no focusable aria-hidden", not r["hiddenFocusable"], ", ".join(r["hiddenFocusable"][:5]))
            check(f"S7 {path} no duplicated tab", not r["dupTabs"], ", ".join(r["dupTabs"]))
            check(f"S8 {path} no inline on* handler", not r["handlers"], ", ".join(r["handlers"][:5]))
            check(f"S9 {path} images have alt", not r["imgAlt"], ", ".join(r["imgAlt"][:5]))
            check(f"S10 {path} lang/dir", r["lang"] == "fa/rtl", r["lang"])
        await browser.close()
    print(f"\n{total - bad} passed, {bad} failed, {total} checks")
    return 1 if bad else 0


if __name__ == "__main__":
    import asyncio
    sys.exit(asyncio.run(main()))
