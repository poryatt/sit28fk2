#!/usr/bin/env python3
"""Static WCAG 2.2 AA audit of public/assets/css/app/00-tokens.css (no browser, no dependencies).

Usage:  python tests/ui/check_tokens.py [path/to/00-tokens.css]
Exit code 0 = every pair passes, 1 = at least one pair fails (or a token is missing).

It parses every `--name: #rrggbb;` of the light block (:root / [data-bs-theme="light"]) and of the
dark block ([data-bs-theme="dark"]), then checks the PAIRS table below in BOTH themes.
Kinds: text = 4.5:1, large = 3:1, ui = 3:1 (borders of controls, focus ring, icons).
Edit PAIRS only together with the tokens; a new semantic token needs a new row.
"""
import re
import sys
from pathlib import Path

DEFAULT = Path(__file__).resolve().parents[2] / "public" / "assets" / "css" / "app" / "00-tokens.css"
NEEDS = {"text": 4.5, "large": 3.0, "ui": 3.0}

# (foreground token, background token, kind, description)
PAIRS = [
    ("text", "bg", "text", "body text on page"),
    ("text", "surface", "text", "body text on card"),
    ("text", "surface-2", "text", "body text on nested panel / table header"),
    ("text", "surface-3", "text", "body text on hover fill"),
    ("text-heading", "surface", "text", "headings on card"),
    ("text-label", "surface", "text", "form label on card"),
    ("text-label", "surface-input", "text", "label-like text on input background"),
    ("text-muted", "bg", "text", "secondary text on page"),
    ("text-muted", "surface", "text", "secondary text on card"),
    ("text-muted", "surface-2", "text", "secondary text on nested panel"),
    ("text-muted", "surface-3", "text", "secondary text on hover fill"),
    ("text-faint", "bg", "text", "tertiary text on page"),
    ("text-faint", "surface", "text", "tertiary text on card"),
    ("text-faint", "surface-2", "text", "tertiary text on nested panel"),
    ("text-faint", "surface-3", "text", "tertiary text on hover fill"),
    ("text-hint", "surface", "text", "form hint on card"),
    ("text-placeholder", "surface-input", "text", "placeholder inside input"),
    ("text-placeholder", "surface", "text", "placeholder on card (input without own fill)"),
    ("text", "surface-input", "text", "typed text inside input"),
    ("text-link", "surface", "text", "link on card"),
    ("text-link", "bg", "text", "link on page"),
    ("text", "surface-raised", "text", "text in dropdown / modal / swal"),
    ("text-muted", "surface-raised", "text", "secondary text in dropdown / modal"),
    ("text-placeholder", "surface-raised", "text", "placeholder inside modal"),
    ("on-primary", "primary", "text", "primary button label"),
    ("on-primary", "primary-hover", "text", "primary button label on hover"),
    ("on-primary", "primary-active", "text", "primary button label pressed"),
    ("primary-soft-text", "primary-soft", "text", "soft primary chip / pill"),
    ("secondary-text", "secondary-bg", "text", "secondary button label"),
    ("secondary-text", "secondary-bg-hover", "text", "secondary button label on hover"),
    ("on-success", "success-solid", "text", "solid success button"),
    ("on-warning", "warning-solid", "text", "solid warning button"),
    ("on-danger", "danger-solid", "text", "solid danger button"),
    ("on-info", "info-solid", "text", "solid info button"),
    ("success-text", "success-soft", "text", "success badge / alert"),
    ("warning-text", "warning-soft", "text", "warning badge / alert"),
    ("danger-text", "danger-soft", "text", "danger badge / alert"),
    ("info-text", "info-soft", "text", "info badge / alert"),
    ("muted-soft-text", "muted-soft", "text", "neutral badge"),
    ("danger-text", "surface", "text", "error message under a field"),
    ("success-text", "surface", "text", "success message on card"),
    ("warning-text", "surface", "text", "warning message on card"),
    ("info-text", "surface", "text", "info message on card"),
    ("nav-text", "surface-sidebar", "text", "sidebar item"),
    ("nav-hover-text", "nav-hover-bg", "text", "sidebar item hover"),
    ("nav-active-text", "nav-active-bg", "text", "sidebar item active"),
    ("text-faint", "surface-sidebar", "text", "sidebar group title"),
    ("text", "surface-header", "text", "topbar title"),
    ("text-muted", "surface-header", "text", "topbar breadcrumb"),
    ("tip-text", "tip-bg", "text", "tooltip"),
    ("viewer-fg", "viewer-surface", "text", "text inside the fullscreen media viewer box"),
    ("viewer-fg", "viewer-stage", "text", "error text on the viewer stage"),
    ("viewer-control-fg", "viewer-control-bg", "text", "viewer close / previous / next glyph"),
    ("viewer-control-bg", "viewer-stage", "ui", "viewer control against the stage"),
    ("viewer-control-bg", "viewer-surface", "ui", "viewer control against the viewer box"),
    ("text-disabled", "surface-2", "large", "disabled text on nested panel (exempt by WCAG, kept readable)"),
    ("text-disabled", "surface-3", "large", "disabled text on its disabled fill"),
    ("text-disabled", "surface", "large", "disabled text on card"),
    # non-text contrast (WCAG 1.4.11)
    ("border-control", "surface-input", "ui", "input border on its own fill"),
    ("border-control", "surface", "ui", "input / checkbox border on card"),
    ("border-control", "bg", "ui", "input / checkbox border on page"),
    ("border-control", "surface-raised", "ui", "input border inside modal"),
    ("secondary-border", "surface", "ui", "secondary button border on card"),
    ("secondary-border", "bg", "ui", "secondary button border on page"),
    ("focus-ring", "surface", "ui", "focus ring on card"),
    ("focus-ring", "bg", "ui", "focus ring on page"),
    ("focus-ring", "surface-sidebar", "ui", "focus ring in sidebar"),
    ("focus-ring", "surface-raised", "ui", "focus ring in modal"),
    ("nav-active-bar", "nav-active-bg", "ui", "active indicator bar"),
    ("primary", "surface", "ui", "primary button edge against card"),
    ("primary", "bg", "ui", "primary button edge against page"),
]


RGB_MAP = {  # --rgb-<key> must equal this hex token
    "text": "text", "bg": "bg", "primary": "primary", "success": "success-solid", "warning": "warning-solid",
    "danger": "danger-solid", "info": "info-solid", "focus": "focus-ring",
}


def parse_rgb(css):
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    out = {"light": {}, "dark": {}}
    for m in re.finditer(r"([^{}]+)\{([^{}]*)\}", css):
        selector, body = m.group(1), m.group(2)
        vals = dict(re.findall(r"--rgb-([a-z0-9-]+)\s*:\s*(\d+\s*,\s*\d+\s*,\s*\d+)\s*;", body))
        if not vals:
            continue
        out["dark" if 'data-bs-theme="dark"' in selector else "light"].update({k: v.replace(" ", "") for k, v in vals.items()})
    return out


def parse_blocks(css):
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    light, dark = {}, {}
    for m in re.finditer(r"([^{}]+)\{([^{}]*)\}", css):
        selector, body = m.group(1).strip(), m.group(2)
        decls = dict(re.findall(r"--([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})\b", body))
        if not decls:
            continue
        if 'data-bs-theme="dark"' in selector:
            dark.update(decls)
        elif ":root" in selector or 'data-bs-theme="light"' in selector:
            light.update(decls)
    return light, dark


def channel(c):
    c /= 255.0
    return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4


def luminance(hexcolor):
    h = hexcolor.lstrip("#")
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)


def ratio(a, b):
    la, lb = luminance(a), luminance(b)
    if la < lb:
        la, lb = lb, la
    return (la + 0.05) / (lb + 0.05)


def main():
    path = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT
    if not path.is_file():
        print("tokens file not found:", path)
        return 1
    light, dark = parse_blocks(path.read_text(encoding="utf-8"))
    failures, total = 0, 0
    for theme, tokens in (("light", light), ("dark", dark)):
        for fg, bg, kind, desc in PAIRS:
            total += 1
            if fg not in tokens or bg not in tokens:
                missing = [n for n in (fg, bg) if n not in tokens]
                print(f"FAIL [{theme}] {desc}: missing token(s) {', '.join(missing)}")
                failures += 1
                continue
            r = ratio(tokens[fg], tokens[bg])
            ok = r >= NEEDS[kind]
            if not ok:
                failures += 1
            print(f"{'PASS' if ok else 'FAIL'} [{theme}] {desc}: --{fg} {tokens[fg]} on --{bg} {tokens[bg]} = {r:.2f}:1 (needs {NEEDS[kind]})")
    rgbs = parse_rgb(path.read_text(encoding="utf-8"))
    for theme, tokens in (("light", light), ("dark", dark)):
        for key, hex_token in RGB_MAP.items():
            total += 1
            want = ",".join(str(int(tokens[hex_token].lstrip("#")[i:i + 2], 16)) for i in (0, 2, 4)) if hex_token in tokens else None
            got = rgbs[theme].get(key)
            if want is None or got != want:
                failures += 1
                print(f"FAIL [{theme}] --rgb-{key} is {got}, expected {want} (from --{hex_token})")
    # sanity: no purple/violet hue in either theme (hue 250-330 with real saturation, excluding blues used for light primary)
    import colorsys
    for theme, tokens in (("light", light), ("dark", dark)):
        for name, value in tokens.items():
            h = value.lstrip("#")
            r, g, b = (int(h[i:i + 2], 16) / 255 for i in (0, 2, 4))
            hue, lig, sat = colorsys.rgb_to_hls(r, g, b)[0] * 360, colorsys.rgb_to_hls(r, g, b)[1], colorsys.rgb_to_hls(r, g, b)[2]
            if 262 <= hue <= 330 and sat > 0.25 and 0.08 < lig < 0.97:
                total += 1
                failures += 1
                print(f"FAIL [{theme}] --{name} {value} looks purple (hue {hue:.0f})")
    print(f"\n{total - failures} passed, {failures} failed, {total} checks")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
