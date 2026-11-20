#!/usr/bin/env python3
"""Part 10B, step B3 — one-shot, idempotent migration of the page builder's CSS (and five Swal calls + the status badge)
onto the design tokens of Part 10A.

    python tools/ui/migrate_builder_assets.py --root .            # apply
    python tools/ui/migrate_builder_assets.py --root . --check    # report only, change nothing

Every edit is an exact-text replacement with an EXPECTED number of matches. If a file differs from what this script was
written against, the script stops that edit, prints which one and why, and exits 1 — it never guesses. Re-running after a
successful run changes nothing (each edit reports 'already applied').

What it does (and nothing else):
  CSS   public/assets/css/builder/*.css : every color literal becomes a token; every `body.dark-mode` block is deleted
        (the tokens switch with <html data-bs-theme>); `.bi` selectors become `.ti`; the text-editable focus becomes a real focus ring.
  JS    5 x `confirmButtonColor: '#dc3545'` -> the themed danger class; builder-shell.js status/dirty badges -> badge-soft-*.
"""
import argparse
import re
import sys
from pathlib import Path

CSS = "public/assets/css/builder/"
JS = "public/assets/js/"

SHELL_VARS = """#builder-root {
  --builder-surface: var(--surface);
  --builder-border: var(--border);
  --builder-muted: var(--text-muted);
  --builder-canvas-bg: var(--bg-subtle);
  --builder-sticky-top: calc(var(--topbar-h) + var(--space-4));
}
"""
CANVAS_VARS = """#builder-root {
  --builder-canvas-selected: var(--primary);
  --builder-canvas-hover: rgba(var(--rgb-primary), 0.55);
  --builder-canvas-toolbar-bg: var(--text);
  --builder-canvas-toolbar-fg: var(--bg);
}
"""
TOOLBAR_VARS = """#builder-root {
  --builder-settings-width: 340px;
  --builder-settings-max-width: 92vw;
  --builder-insert-menu-width: 220px;
  --builder-toolbar-hover-bg: var(--surface-3);
}
"""
FOCUS_RULE = """#builder-root .builder-text-editable:focus-visible {
  outline: var(--focus-width) solid var(--focus-ring);
  outline-offset: var(--focus-offset);
}
"""
BADGE_HELPER = """  /* Status / dirty badges: A's soft badge = icon + word (never color alone). */
  function setBadge(el, tone, icon, text) {
    el.className = 'badge-soft badge-soft-' + tone;
    el.textContent = '';
    var i = document.createElement('i');
    i.className = 'ti ' + icon;
    i.setAttribute('aria-hidden', 'true');
    el.appendChild(i);
    el.appendChild(document.createTextNode(text));
  }

"""

# (file, kind, old, new, expected_count[, marker])   kind: 's' exact string, 'r' regex (DOTALL); marker = text whose presence means "already applied"
EDITS = [
    # ---- 10-shell.css
    (CSS + "10-shell.css", "s", "shell layout, side column, states, dark theme overrides.", "shell layout, side column, states.", 1),
    (CSS + "10-shell.css", "s", "Never edit style.css / dark-theme.css for builder needs.", "Colors come only from the design tokens (css/app/00-tokens.css); never hard-code one here.", 1),
    (CSS + "10-shell.css", "s", '#builder-root {\n  --builder-surface: #ffffff;\n  --builder-border: #dee2e6;\n  --builder-muted: #6c757d;\n  --builder-canvas-bg: #f8f9fa;\n  --builder-sticky-top: 69px;\n}\n\n[data-theme="dark"] #builder-root {\n  --builder-surface: #1e1e2e;\n  --builder-border: #3a3a4e;\n  --builder-muted: #a0a0b8;\n  --builder-canvas-bg: #16161f;\n}\n', SHELL_VARS, 1),
    # ---- 20-canvas.css
    (CSS + "20-canvas.css", "s", "additions follow the same #builder-root pattern with a dark theme block.", "additions follow the same #builder-root pattern; colors come from the design tokens.", 1),
    (CSS + "20-canvas.css", "s", '#builder-root {\n  --builder-canvas-selected: #6f42c1;\n  --builder-canvas-hover: rgba(111, 66, 193, 0.35);\n  --builder-canvas-toolbar-bg: #212529;\n  --builder-canvas-toolbar-fg: #ffffff;\n}\n\n[data-theme="dark"] #builder-root {\n  --builder-canvas-selected: #a885ff;\n  --builder-canvas-hover: rgba(168, 133, 255, 0.4);\n  --builder-canvas-toolbar-bg: #f0edf7;\n  --builder-canvas-toolbar-fg: #1e1e2e;\n}\n', CANVAS_VARS, 1),
    (CSS + "20-canvas.css", "s", "box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.25);", "box-shadow: var(--shadow-2);", 1),
    (CSS + "20-canvas.css", "s", "background-color: rgba(128, 128, 128, 0.35);", "background-color: rgba(var(--rgb-bg), 0.22);", 1),
    (CSS + "20-canvas.css", "s", "background-color: rgba(128, 128, 128, 0.5);", "background-color: rgba(var(--rgb-bg), 0.35);", 1),
    (CSS + "20-canvas.css", "s", ".builder-toolbar-btn:disabled {\n  opacity: 0.35;", ".builder-toolbar-btn:disabled {\n  opacity: 0.55;", 1),
    # ---- 30-toolbar-interactions.css
    (CSS + "30-toolbar-interactions.css", "s", '#builder-root {\n  --builder-settings-width: 340px;\n  --builder-settings-max-width: 92vw;\n  --builder-insert-menu-width: 220px;\n  --builder-toolbar-hover-bg: rgba(111, 66, 193, 0.35);\n}\n\n[data-theme="dark"] #builder-root {\n  --builder-toolbar-hover-bg: rgba(168, 133, 255, 0.4);\n}\n', TOOLBAR_VARS, 1),
    (CSS + "30-toolbar-interactions.css", "s", "box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);", "box-shadow: var(--shadow-3);", 1),
    (CSS + "30-toolbar-interactions.css", "s", "box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);", "box-shadow: var(--shadow-3);", 1),
    # ---- 40-content-blocks.css
    (CSS + "40-content-blocks.css", "r", r"#builder-root \.builder-text-editable:focus \{\n  box-shadow: 0 0 0 0\.15rem rgba\(13, 110, 253, 0\.15\);\n\}\n", FOCUS_RULE, 1),
    (CSS + "40-content-blocks.css", "r", r"\n[data-theme=\"dark\"] #builder-root \.builder-text-editable:focus \{\n  box-shadow: .*?\n\}\n", "\n", 1),
    (CSS + "40-content-blocks.css", "s", "background: #000;", "background: var(--viewer-stage);", 2),
    (CSS + "40-content-blocks.css", "s", "background: rgba(0, 0, 0, 0.82);", "background: var(--viewer-scrim);", 1),
    (CSS + "40-content-blocks.css", "s", "background: #111;", "background: var(--viewer-surface);", 1),
    (CSS + "40-content-blocks.css", "s", "background: rgba(255, 255, 255, 0.9);", "background: var(--viewer-control-bg);", 1),
    (CSS + "40-content-blocks.css", "s", "background: rgba(255, 255, 255, 0.85);", "background: var(--viewer-control-bg);", 1),
    (CSS + "40-content-blocks.css", "s", "color: #111;", "color: var(--viewer-control-fg);", 2),
    (CSS + "40-content-blocks.css", "s", "background: rgba(255, 255, 255, 0.35);", "background: var(--viewer-track);", 1),
    (CSS + "40-content-blocks.css", "s", "background: #fff;", "background: var(--viewer-fg);", 1),
    (CSS + "40-content-blocks.css", "s", "color: #fff;", "color: var(--viewer-fg);", 1),
    (CSS + "40-content-blocks.css", "s", "cannot rely on\n * #builder-root custom properties. Uses plain colors intentionally. */", "cannot rely on\n * #builder-root custom properties; it reads the --viewer-* design tokens (defined on :root, same in both themes). */", 1),
    # ---- 50-media-picker.css
    (CSS + "50-media-picker.css", "s", "background: rgba(0, 0, 0, 0.55);", "background: var(--overlay);", 1),
    (CSS + "50-media-picker.css", "s", "  background: #fff;\n  border-radius: 12px;", "  background: var(--surface-raised);\n  border-radius: 12px;", 1),
    (CSS + "50-media-picker.css", "s", "  background: #fff;\n  cursor: pointer;", "  background: var(--surface);\n  cursor: pointer;", 1),
    (CSS + "50-media-picker.css", "s", "box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);", "box-shadow: var(--shadow-3);", 1),
    (CSS + "50-media-picker.css", "s", "1px solid #e9ecef;", "1px solid var(--border);", 3),
    (CSS + "50-media-picker.css", "s", "color: #6c757d;", "color: var(--text-muted);", 3),
    (CSS + "50-media-picker.css", "s", "border: 1px solid #dee2e6;", "border: 1px solid var(--border-strong);", 1),
    (CSS + "50-media-picker.css", "s", "border-color: #6f42c1;", "border-color: var(--primary);", 1),
    (CSS + "50-media-picker.css", "s", "background: #f1f3f5;", "background: var(--surface-3);", 2),
    (CSS + "50-media-picker.css", "s", "color: #495057;", "color: var(--text-muted);", 4),
    (CSS + "50-media-picker.css", "s", "color: #868e96;", "color: var(--text-faint);", 1),
    # ---- Swal: the five remaining hard-coded confirm colors
    (JS + "pages-manager.js", "s", "confirmButtonColor: '#dc3545'", "customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined),\n      focusCancel: true", 1),
    (JS + "builder/types/20-slider.js", "s", "confirmButtonColor: '#dc3545' }", "customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined), focusCancel: true }", 1),
    (JS + "builder/types/21-story.js", "s", "confirmButtonColor: '#dc3545' }", "customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined), focusCancel: true }", 1),
    (JS + "builder/ui/10-canvas.js", "s", "confirmButtonColor: '#dc3545'\n", "customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined),\n        focusCancel: true\n", 2),
    # ---- builder-shell.js badges
    (JS + "builder/builder-shell.js", "s",
     "        statusBadge.className = 'badge bg-success';\n        statusBadge.textContent = STR_15;",
     "        setBadge(statusBadge, 'success', 'ti-circle-check', STR_15);", 1),
    (JS + "builder/builder-shell.js", "s",
     "        statusBadge.className = 'badge bg-secondary';\n        statusBadge.textContent = STR_16;",
     "        setBadge(statusBadge, 'muted', 'ti-pencil', STR_16);", 1),
    (JS + "builder/builder-shell.js", "s",
     "      banner.className = 'alert alert-info';\n      banner.textContent = STR_14;\n",
     "      banner.className = 'alert alert-info d-flex align-items-start gap-2';\n      banner.setAttribute('role', 'status');\n      var bannerIcon = document.createElement('i');\n      bannerIcon.className = 'ti ti-lock';\n      bannerIcon.setAttribute('aria-hidden', 'true');\n      var bannerText = document.createElement('span');\n      bannerText.textContent = STR_14;\n      banner.appendChild(bannerIcon);\n      banner.appendChild(bannerText);\n", 1),
    (JS + "builder/builder-shell.js", "s",
     'var STR_22 = "<div>خطا در بارگذاری بوم</div>";',
     'var STR_22 = "<div class=\\"d-flex align-items-center justify-content-center gap-2\\" role=\\"alert\\"><i class=\\"ti ti-alert-circle\\" aria-hidden=\\"true\\"></i><span>خطا در بارگذاری بوم</span></div>";', 1),
    (JS + "builder/builder-shell.js", "s", "  function paintSide() {\n", BADGE_HELPER + "  function paintSide() {\n", 1, "function setBadge("),
]
# Optional edit: the two `.bi` selectors are changed by Part 10C; if C was skipped they are still there.
OPTIONAL = [
    (CSS + "50-media-picker.css", "s", ".builder-media-tile-box .bi {", ".builder-media-tile-box .ti {", 1),
    (CSS + "50-media-picker.css", "s", ".builder-media-field-thumb .bi {", ".builder-media-field-thumb .ti {", 1),
]


def apply_edit(text, kind, old, new, expected, marker=None):
    """Returns (new_text, status) with status in applied | already | mismatch:<n>."""
    if marker and marker in text:
        return text, "already"
    found = len(re.findall(old, text, flags=re.S)) if kind == "r" else text.count(old)
    if found == expected:
        out = re.sub(old, lambda m: new, text, flags=re.S) if kind == "r" else text.replace(old, new)
        return out, "applied"
    if found == 0 and new in text:
        return text, "already"
    return text, f"mismatch: expected {expected} match(es), found {found}"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--check", action="store_true")
    args = ap.parse_args()
    root = Path(args.root).resolve()
    cache, orig, bad = {}, {}, 0
    for group, strict in ((EDITS, True), (OPTIONAL, False)):
        for rel, kind, old, new, expected, *rest in group:
            path = root / rel
            if not path.is_file():
                print(f"FAIL  {rel}: file not found")
                bad += 1
                continue
            if rel not in orig:
                orig[rel] = path.read_text(encoding="utf-8")
            text = cache.get(rel, orig[rel])
            out, status = apply_edit(text, kind, old, new, expected, rest[0] if rest else None)
            if status.startswith("mismatch"):
                if strict:
                    bad += 1
                    print(f"FAIL  {rel}: {status}  [{old[:60]!r}]")
                else:
                    print(f"skip  {rel}: optional edit not needed [{old[:40]!r}]")
                continue
            cache[rel] = out
            print(f"{status:<8}{rel}  [{old[:60]!r}]")
    if bad:
        print(f"\n{bad} edit(s) could not be applied; NOTHING was written. Read the FAIL lines, adjust by hand, and list it in the changelog.")
        return 1
    if args.check:
        print("\n--check: nothing written")
        return 0
    changed = [rel for rel, text in cache.items() if text != orig[rel]]
    for rel in changed:
        (root / rel).write_text(cache[rel], encoding="utf-8", newline="\n")
    print(f"\nwrote {len(changed)} file(s)" + ("" if changed else " (nothing to change: already migrated)"))
    return 0


if __name__ == "__main__":
    sys.exit(main())
