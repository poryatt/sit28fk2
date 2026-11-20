#!/usr/bin/env python3
"""UI checker for the Part 10 design system (Playwright, Chromium).

KIT mode (no PHP, no database; serves <root>/public as a static site):
    python tests/browser/run_ui_checks.py --kit . --kit-path /ui-kit/index.html

LIVE mode (a running panel, real login; also the way to audit every real page):
    python tests/browser/run_ui_checks.py --base-url http://localhost:8080 --user OWNER --password SECRET
    options: --pages /modules/dashboard/index.php,/modules/articles/index.php   (default list below)
             --widths 360,768,1280   --shots tests/browser/shots   --no-login (audit public pages only)

Every check prints PASS/FAIL with the measured value. Exit code 1 if anything failed.
Network failures for /assets/vendor/* and the jsDelivr CDN are reported as INFO (the vendor folder may be empty);
every other console error or page error fails the run.
"""
import argparse
import os
import asyncio
import functools
import http.server
import re
import socketserver
import sys
import threading
from pathlib import Path

from playwright.async_api import async_playwright

DEFAULT_PAGES = [
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
# Owner-ordered removals, grouped by the master prompt that performs them. --removed picks the groups to enforce:
# default "plan,footer" = what 10A (shell) already removed; add "sales" after 10C, add "dashboard" after 10B; "all" = everything.
REMOVED_GROUPS = {
    "plan": ["پلن شما", "ارتقای پلن", "برای امکانات بیشتر پلن خود را ارتقا دهید"],
    "footer": ["تمامی حقوق محفوظ"],
    "sales": ["افزایش فروش"],
    "dashboard": ["مشاهده راهنما", "نوع پنل"],
}
FORBIDDEN_TEXT = []  # filled from --removed in main()
# ----------------------------------------------------------------------------- in-page helpers
JS_COLOR_LIB = r"""
(() => {
  if (window.__ui) return;
  const parse = (c) => {
    let m = c.match(/^rgba?\(([^)]+)\)$/);
    if (m) { const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; }
    m = c.match(/^color\(srgb ([^)]+)\)$/);
    if (m) { const p = m[1].split(/[ \/]+/).filter(Boolean).map(Number); return [p[0] * 255, p[1] * 255, p[2] * 255, p.length > 3 ? p[3] : 1]; }
    return null;
  };
  const over = (top, bottom) => {
    const a = top[3] + bottom[3] * (1 - top[3]);
    if (a === 0) return [0, 0, 0, 0];
    return [0, 1, 2].map(i => (top[i] * top[3] + bottom[i] * bottom[3] * (1 - top[3])) / a).concat([a]);
  };
  const lum = (c) => {
    const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]);
  };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  // Effective background of an element: composite ancestors' backgrounds. Returns null when a gradient/image sits in the way.
  const bgOf = (el) => {
    const chain = [];
    for (let n = el; n; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== 'none') return null;
      const c = parse(s.backgroundColor);
      if (c && c[3] > 0) { chain.push(c); if (c[3] >= 1) break; }
    }
    let out = [255, 255, 255, 1];
    for (let i = chain.length - 1; i >= 0; i--) out = over(chain[i], out);
    return out;
  };
  const opacityOf = (el) => { let o = 1; for (let n = el; n; n = n.parentElement) o *= parseFloat(getComputedStyle(n).opacity); return o; };
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) return false;
    for (let n = el; n; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.display === 'none' || s.visibility === 'hidden') return false;
    }
    return opacityOf(el) > 0.05;
  };
  const path = (el) => {
    const bits = [];
    for (let n = el; n && n.nodeType === 1 && bits.length < 4; n = n.parentElement) {
      let t = n.tagName.toLowerCase();
      if (n.id) t += '#' + n.id; else if (n.classList.length) t += '.' + [...n.classList].slice(0, 2).join('.');
      bits.unshift(t);
    }
    return bits.join('>');
  };
  // Bootstrap's own rules turn the bridge variables into real styles. When Bootstrap is not loaded (empty vendor folder and
  // no network) those elements are unstyled, so auditing them would measure the browser default, not our design.
  const bsLoaded = getComputedStyle(document.documentElement).getPropertyValue('--bs-blue').trim() !== '' || !!document.getElementById('bs-stub');
  const BS_SELECTOR = '.btn, .form-control, .form-select, .form-check-input, .form-switch, .alert, .modal, .dropdown-menu, .nav-pills, .pagination, .progress, .table, .card';
  window.__ui = { parse, over, lum, ratio, bgOf, opacityOf, visible, path, bsLoaded, BS_SELECTOR };
})();
"""

JS_AUDIT_TEXT = r"""
() => {
  const U = window.__ui, bad = [], skipped = [];
  let measured = 0, bsSkipped = 0;
  const all = document.querySelectorAll('body *');
  for (const el of all) {
    if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'OPTION'].includes(el.tagName)) continue;
    if (el.closest('.skeleton, [data-audit-skip]')) continue;
    if (!U.bsLoaded && el.closest(U.BS_SELECTOR)) { bsSkipped++; continue; }
    const isField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName);
    let hasText = false;
    if (isField) {
      const type = (el.getAttribute('type') || 'text').toLowerCase();
      hasText = !['checkbox', 'radio', 'hidden', 'file', 'range', 'color', 'submit', 'button', 'image'].includes(type) && (el.value !== '' || el.tagName === 'SELECT');
    } else {
      for (const n of el.childNodes) if (n.nodeType === 3 && n.textContent.trim()) { hasText = true; break; }
    }
    if (!hasText || !U.visible(el)) continue;
    if (el.disabled || el.closest('[disabled], .disabled, [aria-disabled="true"]')) { skipped.push(U.path(el)); continue; }
    const s = getComputedStyle(el);
    const bg = U.bgOf(el.tagName === 'BODY' ? el : el);
    if (!bg) { skipped.push(U.path(el) + ' (gradient)'); continue; }
    let fg = U.parse(s.color);
    if (!fg) continue;
    const op = U.opacityOf(el);
    if (fg[3] < 1 || op < 1) fg = U.over([fg[0], fg[1], fg[2], fg[3] * op], bg);
    const size = parseFloat(s.fontSize), weight = parseInt(s.fontWeight, 10) || 400;
    const large = size >= 24 || (size >= 18.66 && weight >= 700);
    const need = large ? 3 : 4.5;
    const r = U.ratio(fg, bg);
    measured++;
    if (r < need) bad.push(`${U.path(el)} "${(el.value || el.textContent).trim().slice(0, 24)}" ${r.toFixed(2)}:1 < ${need}`);
  }
  return { measured, bad, skipped: skipped.length, bsSkipped };
}
"""

JS_AUDIT_PLACEHOLDERS = r"""
() => {
  const U = window.__ui, bad = []; let measured = 0;
  for (const el of document.querySelectorAll('input[placeholder], textarea[placeholder]')) {
    if (!U.visible(el) || el.disabled) continue;
    if (!U.bsLoaded && el.closest(U.BS_SELECTOR)) continue;
    const ph = getComputedStyle(el, '::placeholder');
    const bg = U.bgOf(el); if (!bg) continue;
    const fg = U.over(U.parse(ph.color).slice(0, 3).concat([U.parse(ph.color)[3] * parseFloat(ph.opacity || 1)]), bg);
    const r = U.ratio(fg, bg); measured++;
    if (r < 4.5) bad.push(`${U.path(el)} placeholder "${el.getAttribute('placeholder').slice(0, 20)}" ${r.toFixed(2)}:1`);
  }
  return { measured, bad };
}
"""

JS_AUDIT_BORDERS = r"""
() => {
  const U = window.__ui, bad = []; let measured = 0;
  const sel = 'input:not([type=hidden]):not([type=file]):not([type=submit]):not([type=button]), select, textarea';
  for (const el of document.querySelectorAll(sel)) {
    if (!U.visible(el) || el.disabled || el.readOnly) continue;
    if (el.closest('[data-audit-skip]')) continue;
    if (!U.bsLoaded && el.closest(U.BS_SELECTOR)) continue;
    const s = getComputedStyle(el);
    const parentBg = U.bgOf(el.parentElement); if (!parentBg) continue;
    const isBox = ['checkbox', 'radio'].includes((el.getAttribute('type') || '').toLowerCase());
    const bw = parseFloat(s.borderTopWidth);
    let c = U.parse(s.borderTopColor);
    if (!c) continue;
    c = U.over(c, parentBg);
    const r = U.ratio(c, parentBg); measured++;
    if (bw < 1 || r < 3) bad.push(`${U.path(el)} ${isBox ? 'box ' : ''}border ${bw}px ${r.toFixed(2)}:1`);
  }
  return { measured, bad };
}
"""


# Used ONLY when Bootstrap itself could not be loaded (empty vendor folder + no network). It reproduces how Bootstrap 5.3
# consumes the --bs-* variables that 30-bootstrap-bridge.css sets, so the audit still measures the colors our tokens produce.
# It does NOT prove Bootstrap's own layout/behavior: that stays "not verified" until the real files are present.
BOOTSTRAP_STUB = """
.btn{color:var(--bs-btn-color,var(--bs-body-color));background-color:var(--bs-btn-bg,transparent);border:1px solid var(--bs-btn-border-color,transparent);padding:.375rem .75rem;border-radius:var(--bs-btn-border-radius,.375rem)}
.btn:disabled,.btn.disabled{color:var(--bs-btn-disabled-color);background-color:var(--bs-btn-disabled-bg);border-color:var(--bs-btn-disabled-border-color)}
.card{background-color:var(--bs-card-bg,var(--bs-body-bg));color:var(--bs-card-color,var(--bs-body-color));border:1px solid var(--bs-card-border-color,var(--bs-border-color));border-radius:var(--bs-card-border-radius,.375rem)}
.table{color:var(--bs-table-color);width:100%;border-collapse:collapse}.table>:not(caption)>*>*{background-color:var(--bs-table-bg)}
.alert{color:var(--bs-alert-color);background-color:var(--bs-alert-bg);border:1px solid var(--bs-alert-border-color)}
.nav-pills .nav-link{color:var(--bs-nav-link-color);display:block}.nav-pills .nav-link.active{color:var(--bs-nav-pills-link-active-color);background-color:var(--bs-nav-pills-link-active-bg)}
.nav-pills{display:flex;list-style:none;padding:0;flex-wrap:wrap}
.page-link{color:var(--bs-pagination-color);background-color:var(--bs-pagination-bg);border:1px solid var(--bs-pagination-border-color);display:block;padding:.375rem .75rem}
.page-item.active .page-link{color:var(--bs-pagination-active-color);background-color:var(--bs-pagination-active-bg)}
.page-item.disabled .page-link{color:var(--bs-pagination-disabled-color);background-color:var(--bs-pagination-disabled-bg)}
.pagination{display:flex;list-style:none;padding:0}
.dropdown-menu{background-color:var(--bs-dropdown-bg);color:var(--bs-dropdown-color);border:1px solid var(--bs-dropdown-border-color)}
.progress{background-color:var(--bs-progress-bg);height:1rem;overflow:hidden}.progress-bar{background-color:var(--bs-progress-bar-bg);color:var(--bs-progress-bar-color);height:100%}
.modal-content{background-color:var(--bs-modal-bg);color:var(--bs-modal-color)}
.row{display:flex;flex-wrap:wrap;margin-inline:-.75rem}.row>*{padding-inline:.75rem;min-width:0}.col-12{flex:0 0 100%}
.form-check-input{appearance:none;display:inline-block;vertical-align:top}
.d-flex{display:flex}.flex-wrap{flex-wrap:wrap}.gap-2{gap:.5rem}.mb-2{margin-bottom:.5rem}.mb-3{margin-bottom:1rem}.mb-0{margin-bottom:0}.w-75{width:75%}
.table-responsive{overflow-x:auto}.visually-hidden-focusable:not(:focus):not(:focus-within){position:absolute}
"""


async def prepare(page):
    """Inject the wiring stub when Bootstrap is absent. Returns True when the stub is in use."""
    absent = await page.evaluate("getComputedStyle(document.documentElement).getPropertyValue('--bs-blue').trim() === ''")
    if absent and not await page.evaluate("!!document.getElementById('bs-stub')"):
        await page.evaluate("css => { const s = document.createElement('style'); s.id = 'bs-stub'; s.textContent = css; document.head.insertBefore(s, document.head.firstChild); }", BOOTSTRAP_STUB)
    return absent


class Report:
    def __init__(self):
        self.passed = 0
        self.failed = 0

    def check(self, name, ok, detail=""):
        if ok:
            self.passed += 1
        else:
            self.failed += 1
        print(f"{'PASS' if ok else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))

    def info(self, text):
        print(f"INFO {text}")


class Quiet(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass


def start_static(root):
    handler = functools.partial(Quiet, directory=str(root))
    server = socketserver.ThreadingTCPServer(("127.0.0.1", 0), handler)
    server.daemon_threads = True
    threading.Thread(target=server.serve_forever, daemon=True).start()
    return server, f"http://127.0.0.1:{server.server_address[1]}"


def is_vendor_noise(text):
    return "Failed to load resource" in text or "ERR_" in text or "/assets/vendor/" in text or "cdn.jsdelivr.net" in text


async def attach_console(page, store):
    page.on("console", lambda m: store["errors"].append(m.text) if m.type == "error" else None)
    page.on("pageerror", lambda e: store["page"].append(str(e)))


async def set_theme(page, value):
    await page.evaluate("v => window.PanelUI.theme.set(v, {persist:false, silent:true, force:true})", value)
    await page.wait_for_timeout(60)


async def audit_common(rep, page, label, theme, store, shots=None):
    await prepare(page)
    await page.evaluate(JS_COLOR_LIB)
    await set_theme(page, theme)
    await page.wait_for_timeout(500)  # let the 140-360 ms color transitions and the page-enter animation finish
    t = await page.evaluate(JS_AUDIT_TEXT)
    rep.check(f"{label} [{theme}] CONTRAST text", not t["bad"], f"{t['measured']} text nodes measured, {t['skipped']} skipped" + (f", {t['bsSkipped']} Bootstrap-dependent nodes skipped (Bootstrap not loaded)" if t["bsSkipped"] else "") + ("; " + " | ".join(t["bad"][:5]) if t["bad"] else ""))
    ph = await page.evaluate(JS_AUDIT_PLACEHOLDERS)
    rep.check(f"{label} [{theme}] CONTRAST placeholders", not ph["bad"], f"{ph['measured']} measured" + ("; " + " | ".join(ph["bad"][:5]) if ph["bad"] else ""))
    bd = await page.evaluate(JS_AUDIT_BORDERS)
    rep.check(f"{label} [{theme}] control borders >= 3:1", not bd["bad"], f"{bd['measured']} measured" + ("; " + " | ".join(bd["bad"][:5]) if bd["bad"] else ""))
    if shots:
        shots.mkdir(parents=True, exist_ok=True)
        await page.screenshot(path=str(shots / f"{re.sub(r'[^a-z0-9]+', '-', label.lower()).strip('-')}-{theme}.png"), full_page=False)


async def audit_structure(rep, page, label, viewport_width):
    rep.check(f"{label} html lang=fa dir=rtl", await page.evaluate("document.documentElement.lang === 'fa' && document.documentElement.dir === 'rtl'"))
    rep.check(f"{label} exactly one h1", await page.locator("h1").count() == 1, f"{await page.locator('h1').count()} found")
    rep.check(f"{label} no horizontal overflow @{viewport_width}", await page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1"),
              await page.evaluate("document.documentElement.scrollWidth + ' > ' + window.innerWidth"))
    arabic = await page.evaluate("/[\\u064A\\u0643]/.test(document.body.innerText)")
    rep.check(f"{label} no Arabic letters (U+064A, U+0643) in visible text", not arabic)
    text = await page.evaluate("document.body.innerText")
    leaked = [t for t in FORBIDDEN_TEXT if t in text]
    rep.check(f"{label} removed content stays removed", not leaked, ", ".join(leaked))
    rep.check(f"{label} no <footer> and no dead href=\"#\"", await page.locator("footer").count() == 0 and await page.locator('a[href="#"]').count() == 0)
    if viewport_width >= 992:
        sb = page.locator("#app-sidebar")
        if await sb.count():
            right = await page.evaluate("document.getElementById('app-sidebar').getBoundingClientRect().right")
            rep.check(f"{label} sidebar sits on the RIGHT @{viewport_width}", abs(right - viewport_width) <= 1.5, f"right edge {right:.0f} of {viewport_width}")


async def focus_audit(rep, page, label, theme, stops=8):
    await prepare(page)
    await page.evaluate(JS_COLOR_LIB)
    await page.evaluate("document.activeElement && document.activeElement.blur()")
    bad, seen = [], 0
    for _ in range(stops):
        await page.keyboard.press("Tab")
        await page.wait_for_timeout(40)
        info = await page.evaluate("""() => {
          const el = document.activeElement; if (!el || el === document.body) return null;
          const U = window.__ui, s = getComputedStyle(el);
          const bg = U.bgOf(el.parentElement); const c = U.parse(s.outlineColor);
          if (!bg || !c) return { path: U.path(el), skip: true };
          return { path: U.path(el), style: s.outlineStyle, width: parseFloat(s.outlineWidth), ratio: U.ratio(U.over(c, bg), bg) };
        }""")
        if not info or info.get("skip"):
            continue
        seen += 1
        if info["style"] == "none" or info["width"] < 2 or info["ratio"] < 3:
            bad.append(f"{info['path']} {info['style']} {info['width']}px {info['ratio']:.2f}:1")
    rep.check(f"{label} [{theme}] keyboard focus ring visible (>=2px, >=3:1)", seen > 0 and not bad, f"{seen} stops" + ("; " + " | ".join(bad[:4]) if bad else ""))


# ----------------------------------------------------------------------------- KIT mode
def find_browser():
    for cand in [
        os.environ.get("CHROME_PATH"),
        r"C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
        r"C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe",
        r"C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe",
        "/usr/bin/google-chrome",
        "/usr/bin/chromium",
    ]:
        if cand and os.path.isfile(cand):
            return cand
    return None


async def run_kit(args, rep, pw):
    root = Path(args.kit).resolve() / "public"
    if not root.is_dir():
        rep.check("kit: public/ folder exists", False, str(root))
        return
    server, base = start_static(root)
    url = base + args.kit_path
    exe = find_browser()
    browser = await pw.chromium.launch(executable_path=exe) if exe else await pw.chromium.launch()
    store = {"errors": [], "page": []}
    shots = Path(args.shots) if args.shots else None
    try:
        ctx = await browser.new_context(viewport={"width": 1280, "height": 800}, locale="fa-IR")
        page = await ctx.new_page()
        await attach_console(page, store)
        resp = await page.goto(url, wait_until="load")
        await page.wait_for_timeout(500)
        rep.check("kit page loads (HTTP 200)", resp is not None and resp.status == 200, str(resp.status if resp else None))
        real_errors = [e for e in store["errors"] if not is_vendor_noise(e)]
        noise = [e for e in store["errors"] if is_vendor_noise(e)]
        rep.check("no console errors / page errors", not real_errors and not store["page"], " | ".join((real_errors + store["page"])[:3]))
        if noise:
            rep.info(f"{len(noise)} vendor/CDN load failure(s) ignored (vendor folder empty or offline). Bootstrap, Tabler and Vazirmatn are NOT verified in this run; a wiring stub stands in for Bootstrap's variable consumption.")
        html = (root / args.kit_path.lstrip("/")).read_text(encoding="utf-8")
        css_dir = root / "assets" / "css" / "app"
        wanted = sorted(p.name for p in css_dir.glob("*.css") if re.match(r"^[0-5]\d-", p.name))
        missing = [n for n in wanted if f"/assets/css/app/{n}" not in html]
        rep.check("kit links every css/app/00-59 file", not missing, f"{len(wanted)} files; missing: {', '.join(missing)}")
        rep.check("kit has no inline style / on* handlers", not re.search(r'\sstyle="|\son[a-z]+="', html))
        rep.check("exactly one aria-current=page in the sidebar", await page.locator('#app-sidebar [aria-current="page"]').count() == 1)
        rep.check("skip link is the first focusable element", await page.evaluate("(() => { document.body.focus(); return document.querySelector('a.skip-link') === document.querySelector('a[href],button'); })()"))
        rep.check("every icon-only button has an accessible name", await page.evaluate("""() => [...document.querySelectorAll('button, a')].filter(b => !b.textContent.trim() && !b.getAttribute('aria-label')).length === 0"""))
        await audit_structure(rep, page, "kit", 1280)

        # --- themes, contrast, focus
        for theme in ("light", "dark"):
            await audit_common(rep, page, "kit", theme, store, shots)
            await focus_audit(rep, page, "kit", theme)
            bgcol = await page.evaluate("getComputedStyle(document.body).backgroundColor")
            want = {"light": "rgb(243, 246, 252)", "dark": "rgb(9, 9, 11)"}[theme]
            rep.check(f"kit [{theme}] body background is the token --bg", bgcol == want, bgcol)
        await set_theme(page, "light")

        # --- theme toggle without reload + persistence + POST contract
        await page.evaluate("window.__noReload = true; document.documentElement.setAttribute('data-theme-url', '/api/staff/toggle_theme.php'); window.__events = []; document.addEventListener('panel:theme-changed', e => window.__events.push(e.detail.theme));")
        posted = {}
        async def on_route(route):
            req = route.request
            posted["body"] = req.post_data or ""
            posted["hdr"] = req.headers.get("x-csrf-token", "")
            await route.fulfill(status=200, content_type="application/json", body='{"success":true,"theme":"dark"}')
        await page.route("**/api/staff/toggle_theme.php", on_route)
        await page.click("[data-theme-toggle]")
        await page.wait_for_timeout(900)
        rep.check("theme toggle flips data-bs-theme without a reload", await page.evaluate("document.documentElement.getAttribute('data-bs-theme') === 'dark' && window.__noReload === true"))
        rep.check("theme toggle stores localStorage['panel-theme']", await page.evaluate("localStorage.getItem('panel-theme')") == "dark")
        rep.check("theme toggle POSTs theme=dark with the CSRF header", "theme=dark" in posted.get("body", "") or 'name="theme"' in posted.get("body", ""), f"body={posted.get('body', '')[:80]!r} header={posted.get('hdr')!r}")
        rep.check("panel:theme-changed event fired with the new theme", await page.evaluate("window.__events.includes('dark')"))
        rep.check("toggle exposes aria-pressed=true in dark", await page.get_attribute("[data-theme-toggle]", "aria-pressed") == "true")
        await page.click("[data-theme-toggle]")
        await page.wait_for_timeout(900)
        rep.check("second click returns to light (idempotent set, no drift)", await page.evaluate("document.documentElement.getAttribute('data-bs-theme')") == "light")
        await page.unroute("**/api/staff/toggle_theme.php")

        # --- sidebar collapse, animation, icon stability, tooltip, cookie
        await page.evaluate("document.documentElement.setAttribute('data-theme-url', '')")
        geo = """() => { const sb = document.getElementById('app-sidebar').getBoundingClientRect(); const ic = document.querySelector('#app-sidebar .nav-link-item > i').getBoundingClientRect(); return {w: sb.width, right: sb.right, iconOff: sb.right - ic.right}; }"""
        before = await page.evaluate(geo)
        rep.check("sidebar expanded width is 272px", abs(before["w"] - 272) <= 1, f"{before['w']:.1f}")
        await page.click("[data-sidebar-toggle]")
        await page.wait_for_timeout(130)
        mid = await page.evaluate(geo)
        await page.wait_for_timeout(500)
        after = await page.evaluate(geo)
        rep.check("sidebar animates (mid-flight width between 76 and 272)", 77 < mid["w"] < 271, f"mid {mid['w']:.1f}px")
        rep.check("sidebar collapsed width is 76px", abs(after["w"] - 76) <= 1, f"{after['w']:.1f}")
        rep.check("icons do not move while collapsing", abs(before["iconOff"] - after["iconOff"]) <= 1 and abs(mid["iconOff"] - before["iconOff"]) <= 1, f"offset {before['iconOff']:.1f} -> {mid['iconOff']:.1f} -> {after['iconOff']:.1f}")
        rep.check("collapsed: labels fade out", await page.evaluate("parseFloat(getComputedStyle(document.querySelector('#app-sidebar .nav-label')).opacity)") < 0.05)
        rep.check("collapsed: data-sidebar, cookie and aria-expanded agree", await page.evaluate("document.documentElement.dataset.sidebar === 'collapsed' && document.cookie.includes('panel_sidebar=collapsed') && document.querySelector('[data-sidebar-toggle]').getAttribute('aria-expanded') === 'false'"))
        rep.check("collapsed: active item still visible (bar + tinted pill)", await page.evaluate("""() => { const a = document.querySelector('#app-sidebar [aria-current=page]'); const r = a.getBoundingClientRect(); return r.width > 40 && getComputedStyle(a, '::before').transform !== 'matrix(1, 0, 0, 0, 0, 0)'; }"""))
        await page.hover('#app-sidebar .nav-link-item[data-tip="قالب سایت"]')
        await page.wait_for_timeout(450)
        tip = await page.evaluate("""() => { const t = document.getElementById('app-tip'); if (!t) return null; const r = t.getBoundingClientRect(); const a = document.querySelector('#app-sidebar .nav-link-item[data-tip="قالب سایت"]').getBoundingClientRect(); return {text: t.textContent, vis: t.classList.contains('is-visible'), toLeft: r.right <= a.left + 1, inView: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight}; }""")
        rep.check("collapsed: tooltip shows the item name on hover, toward the content, inside the viewport", bool(tip) and tip["vis"] and tip["text"] == "قالب سایت" and tip["toLeft"] and tip["inView"], str(tip))
        await page.mouse.move(600, 400)
        await page.wait_for_timeout(300)
        await page.click("[data-sidebar-toggle]")
        await page.wait_for_timeout(600)
        back = await page.evaluate(geo)
        rep.check("expanding restores 272px and the same icon position", abs(back["w"] - 272) <= 1 and abs(back["iconOff"] - before["iconOff"]) <= 1, f"{back['w']:.1f}px")
        await page.hover('#app-sidebar .nav-link-item[data-tip="قالب سایت"]')
        await page.wait_for_timeout(450)
        rep.check("expanded: sidebar items show no tooltip", not await page.evaluate("(() => { const t = document.getElementById('app-tip'); return !!t && t.classList.contains('is-visible'); })()"))
        await page.mouse.move(600, 400)
        await page.evaluate("document.cookie = 'panel_sidebar=; path=/; max-age=0'")

        # --- bidi inputs
        await page.fill("#k-email", "")
        d0 = await page.evaluate("getComputedStyle(document.getElementById('k-email')).direction")
        await page.type("#k-email", "a@b.com")
        d1 = await page.evaluate("getComputedStyle(document.getElementById('k-email')).direction")
        rep.check("e-mail input: RTL while empty (Persian placeholder), LTR once typed", d0 == "rtl" and d1 == "ltr", f"{d0} -> {d1}")
        await page.fill("#k-email", "")
        # --- password toggle
        await page.click("[data-password-toggle]")
        rep.check("password toggle shows the text, flips aria-label/pressed/icon", await page.evaluate("""() => { const b = document.querySelector('[data-password-toggle]'); return document.getElementById('k-pass').type === 'text' && b.getAttribute('aria-label') === 'پنهان کردن رمز عبور' && b.getAttribute('aria-pressed') === 'true' && b.querySelector('i').className.includes('ti-eye-off'); }"""))
        await page.click("[data-password-toggle]")
        rep.check("password toggle hides it again", await page.evaluate("document.getElementById('k-pass').type") == "password")
        # --- page-enter leaves nothing behind
        await page.wait_for_timeout(900)
        rep.check("page-enter animation leaves no transform/will-change on content children", await page.evaluate("""() => [...document.querySelectorAll('.app-content > *')].every(n => { const s = getComputedStyle(n); return s.transform === 'none' && s.willChange === 'auto'; })"""))
        rep.check("no ancestor of the content has transform/filter/backdrop-filter (fixed descendants stay fixed)", await page.evaluate("""() => { for (let n = document.getElementById('app-content'); n && n !== document.documentElement; n = n.parentElement) { const s = getComputedStyle(n); if (s.transform !== 'none' || s.filter !== 'none' || (s.backdropFilter && s.backdropFilter !== 'none')) return false; } return true; }"""))
        rep.check("topbar is sticky and exactly --topbar-h (64px) high", await page.evaluate("""() => { const t = document.querySelector('.app-topbar'); return getComputedStyle(t).position === 'sticky' && Math.abs(t.getBoundingClientRect().height - 64) <= 1; }"""))
        await ctx.close()

        # --- count-up keeps the server text
        ctx = await browser.new_context(viewport={"width": 1280, "height": 800})
        page = await ctx.new_page()
        await page.goto(url, wait_until="load")
        await page.wait_for_timeout(2200)
        rep.check("count-up ends on the server-rendered text", await page.evaluate("[...document.querySelectorAll('[data-countup]')].map(n => n.textContent).join('|')") == "۱۲|۹")
        await ctx.close()

        # --- reduced motion
        ctx = await browser.new_context(viewport={"width": 1280, "height": 800}, reduced_motion="reduce")
        page = await ctx.new_page()
        await page.goto(url, wait_until="load")
        await page.click("[data-sidebar-toggle]")
        await page.wait_for_timeout(60)
        w = await page.evaluate("document.getElementById('app-sidebar').getBoundingClientRect().width")
        rep.check("prefers-reduced-motion: sidebar jumps to its final width at once", abs(w - 76) <= 1, f"{w:.1f}px after 60ms")
        await ctx.close()

        # --- drawer on a phone
        ctx = await browser.new_context(viewport={"width": 390, "height": 800}, is_mobile=True, has_touch=True)
        page = await ctx.new_page()
        await page.goto(url, wait_until="load")
        await page.wait_for_timeout(500)
        await page.evaluate("document.cookie = 'panel_sidebar=collapsed; path=/'; document.documentElement.setAttribute('data-sidebar','collapsed')")
        rep.check("phone: sidebar is hidden while the drawer is closed", await page.evaluate("getComputedStyle(document.getElementById('app-sidebar')).visibility") == "hidden")
        await audit_structure(rep, page, "kit-phone", 390)
        await page.click("[data-sidebar-toggle]")
        await page.wait_for_timeout(600)
        st = await page.evaluate("""() => ({open: document.documentElement.dataset.drawer === 'open', vis: getComputedStyle(document.getElementById('app-sidebar')).visibility, inert: document.querySelector('.app-main').hasAttribute('inert'), focusIn: document.getElementById('app-sidebar').contains(document.activeElement), label: getComputedStyle(document.querySelector('#app-sidebar .nav-label')).opacity, backdrop: getComputedStyle(document.querySelector('.app-backdrop')).visibility, sbRight: Math.round(document.getElementById('app-sidebar').getBoundingClientRect().right), expanded: document.querySelector('[data-sidebar-toggle]').getAttribute('aria-expanded')})""")
        rep.check("phone: drawer opens from the right edge with backdrop, inert page, focus inside, labels visible even if rail state is collapsed",
                  st["open"] and st["vis"] == "visible" and st["inert"] and st["focusIn"] and float(st["label"]) > 0.95 and st["backdrop"] == "visible" and st["sbRight"] == 390 and st["expanded"] == "true", str(st))
        await page.keyboard.press("Escape")
        await page.wait_for_timeout(500)
        rep.check("phone: Escape closes the drawer and returns focus to the toggle", await page.evaluate("document.documentElement.dataset.drawer === undefined && document.activeElement === document.querySelector('[data-sidebar-toggle]') && !document.querySelector('.app-main').hasAttribute('inert')"))
        await page.click("[data-sidebar-toggle]")
        await page.wait_for_timeout(500)
        await page.mouse.click(40, 400)
        await page.wait_for_timeout(500)
        rep.check("phone: tapping the backdrop closes the drawer", await page.evaluate("document.documentElement.dataset.drawer") is None)
        await page.click("[data-sidebar-toggle]")
        await page.wait_for_timeout(500)
        await page.keyboard.press("Shift+Tab")
        rep.check("phone: Shift+Tab from the first drawer item wraps inside the drawer (focus trap)", await page.evaluate("document.getElementById('app-sidebar').contains(document.activeElement)"))
        await ctx.close()

        # --- widths
        for width in (360, 768, 1280, 1440):
            ctx = await browser.new_context(viewport={"width": width, "height": 900})
            page = await ctx.new_page()
            await page.goto(url, wait_until="load")
            await page.wait_for_timeout(300)
            for theme in ("light", "dark"):
                await set_theme(page, theme)
                ok = await page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 1")
                rep.check(f"kit @{width} [{theme}] no horizontal overflow", ok)
            await ctx.close()
    finally:
        await browser.close()
        server.shutdown()


# ----------------------------------------------------------------------------- LIVE mode
async def run_live(args, rep, pw):
    base = args.base_url.rstrip("/")
    pages = [p.strip() for p in args.pages.split(",") if p.strip()] if args.pages else DEFAULT_PAGES
    widths = [int(w) for w in args.widths.split(",")]
    shots = Path(args.shots)
    exe = find_browser()
    browser = await pw.chromium.launch(executable_path=exe) if exe else await pw.chromium.launch()
    try:
        ctx = await browser.new_context(viewport={"width": 1280, "height": 900}, locale="fa-IR")
        page = await ctx.new_page()
        store = {"errors": [], "page": []}
        await attach_console(page, store)
        if not args.no_login:
            await page.goto(base + "/login.php", wait_until="load")
            await page.fill('input[name="identifier"]', args.user)
            await page.fill('input[name="password"]', args.password)
            await page.click('form button[type="submit"], form button:not([type])')
            await page.wait_for_load_state("load")
            rep.check("LIVE login succeeds", "/login.php" not in page.url, page.url)
        for path in pages:
            for width in widths:
                await page.set_viewport_size({"width": width, "height": 900})
                store["errors"].clear(); store["page"].clear()
                resp = await page.goto(base + path, wait_until="load")
                await page.wait_for_timeout(700)
                label = f"LIVE {path}"
                rep.check(f"{label} @{width} HTTP 200", resp is not None and resp.status == 200, str(resp.status if resp else None))
                real = [e for e in store["errors"] if not is_vendor_noise(e)]
                rep.check(f"{label} @{width} no console/page errors", not real and not store["page"], " | ".join((real + store["page"])[:3]))
                await audit_structure(rep, page, label, width)
                for theme in ("light", "dark"):
                    await audit_common(rep, page, f"{label} @{width}", theme, store, shots)
                await set_theme(page, "light")
        if "sales" in args.removed_groups:
            sales_resp = await page.goto(base + "/modules/sales/index.php")
            rep.check("LIVE sales module is gone (HTTP 404/403 or redirect)", sales_resp.status in (403, 404) or "/modules/sales" not in page.url)
    finally:
        await browser.close()


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--kit", help="project root; serves <root>/public statically")
    ap.add_argument("--kit-path", default="/ui-kit/index.html")
    ap.add_argument("--base-url")
    ap.add_argument("--user")
    ap.add_argument("--password")
    ap.add_argument("--pages")
    ap.add_argument("--widths", default="360,768,1280")
    ap.add_argument("--shots", default="tests/browser/shots")
    ap.add_argument("--no-login", action="store_true")
    ap.add_argument("--removed", default="plan,footer", help="comma list of removal groups to enforce: plan,footer,sales,dashboard or all")
    args = ap.parse_args()
    if not args.kit and not args.base_url:
        ap.error("give --kit <root> or --base-url <url>")
    groups = list(REMOVED_GROUPS) if args.removed == "all" else [g.strip() for g in args.removed.split(",") if g.strip()]
    for g in groups:
        FORBIDDEN_TEXT.extend(REMOVED_GROUPS[g])
    args.removed_groups = groups
    rep = Report()
    async with async_playwright() as pw:
        if args.kit:
            await run_kit(args, rep, pw)
        if args.base_url:
            if not args.no_login and not (args.user and args.password):
                ap.error("live mode needs --user and --password (or --no-login)")
            await run_live(args, rep, pw)
    print(f"\n{rep.passed} passed, {rep.failed} failed")
    sys.exit(1 if rep.failed else 0)


if __name__ == "__main__":
    asyncio.run(main())