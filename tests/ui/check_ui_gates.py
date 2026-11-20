#!/usr/bin/env python3
"""Part 10B static gates. Run from the project root:  python tests/ui/check_ui_gates.py [--root .] [--verbose]

Every gate prints its hits as  <gate> <file>:<line>  <text>  and a final table. Exit 0 only when all gates are at 0.
Scanned: .php .js .css under public/ and includes/ (vendor, uploads, node_modules, ui-kit, tests, docs, changelogs, .git,
storage are skipped; the vendored builder library 00-sortable.js is skipped).

G1 inline-style     style="..." in PHP or in a JS string, or .style. / cssText / setAttribute('style') in JS.
                    Allowed: includes/builder/renderer.php and includes/builder/types/*.php (they print the PUBLIC site, not the panel),
                    and a JS line that carries the marker comment `dynamic geometry` (a value that only exists at run time).
G2 color-literal    #hex / rgb() / rgba() / hsl() in CSS other than app/00-tokens.css (`rgba(var(--rgb-text), .3)` is allowed: it reads a token). app/30-bootstrap-bridge.css may keep them
                    only on lines that contain `data:image/svg` or `--bs-backdrop-bg`.
G3 legacy-utility   bg-white bg-light bg-dark text-dark text-white text-white-50 border-secondary text-bg-* and `badge bg-*` in PHP/JS.
G4 old-dark-mode    `dark-mode` or `dark-theme.css` anywhere.
G5 swal-colour      confirmButtonColor / cancelButtonColor.
G6 inline-handler   on<event>="..." attributes in PHP.
G7 physical-css     left / right / margin-left|right / padding-left|right / border-left|right / text-align:left|right / float in app + builder CSS
                    (use logical properties). Allowed on a line that contains `data-align` (user content alignment) or the marker `/* physical */`.
G8 emoji            pictographs in PHP/JS/CSS strings (the panel uses icons, not emoji).
G10 legacy-helper   thumb-140 thumb-160 thumb-cover thumb-icon cursor-pointer hidden-init pages-slug media-progress-bar stat-card in PHP/JS
                    (the helper classes 10A kept only until the pages were rebuilt; at 0 the helpers are deleted from 35-components.css).
G9 arabic-letters   U+064A (Arabic yeh) or U+0643 (Arabic kaf) anywhere in the scanned files.
"""
import argparse
import re
import sys
from pathlib import Path

SKIP_DIRS = {"vendor", "uploads", "node_modules", "ui-kit", "tests", "docs", "changelogs", ".git", "storage"}
SKIP_FILES = {"00-sortable.js"}
RENDERER = ("includes/builder/renderer.php",)
RENDERER_DIR = "includes/builder/types/"

RE_STYLE_ATTR = re.compile(r'\bstyle\s*=\s*["\\\']')
RE_JS_STYLE = re.compile(r"\.style\.|\.style\[|style\.cssText|setAttribute\(\s*['\"]style['\"]")
RE_COLOR = re.compile(r"#[0-9a-fA-F]{3,8}\b|\brgba?\s*\((?!\s*var\()|\bhsla?\s*\(")
RE_LEGACY = re.compile(r"(?<![\w-])(bg-white|bg-light|bg-dark|text-dark|text-white(?:-50)?|border-secondary|text-bg-[a-z]+)(?![\w-])|badge\s+bg-[a-z]+")
RE_DARK = re.compile(r"dark-mode|dark-theme\.css")
RE_SWAL = re.compile(r"confirmButtonColor|cancelButtonColor")
RE_HANDLER = re.compile(r"\son(?:click|change|submit|input|load|keyup|keydown|focus|blur|error|mouse[a-z]+)\s*=", re.I)
RE_PHYS = re.compile(r"(?<![\w-])(?:left|right)\s*:|(?:margin|padding|border)-(?:left|right)\b|text-align\s*:\s*(?:left|right)|float\s*:\s*(?:left|right)|border-(?:top|bottom)-(?:left|right)-radius")
RE_EMOJI = re.compile("[\U0001F300-\U0001FAFF☀-➿⭐⭕️]")
RE_HELPER = re.compile(r"(?<![\w-])(thumb-140|thumb-160|thumb-cover|thumb-icon|cursor-pointer|hidden-init|pages-slug|media-progress-bar|stat-card)(?![\w-])")
RE_ARABIC = re.compile("[\u064a\u0643]")


def files(root):
    for base in ("public", "includes"):
        d = root / base
        if not d.is_dir():
            continue
        for p in sorted(d.rglob("*")):
            if not p.is_file() or p.suffix.lower() not in (".php", ".js", ".css"):
                continue
            rel = p.relative_to(root).as_posix()
            parts = set(p.relative_to(root).parts[:-1])
            if parts & SKIP_DIRS or p.name in SKIP_FILES:
                continue
            yield p, rel


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--verbose", action="store_true")
    args = ap.parse_args()
    root = Path(args.root).resolve()
    hits = {k: [] for k in ["G1", "G2", "G3", "G4", "G5", "G6", "G7", "G8", "G9", "G10"]}
    scanned = 0
    for p, rel in files(root):
        scanned += 1
        ext = p.suffix.lower()
        text = p.read_text(encoding="utf-8", errors="replace")
        lines = text.splitlines()
        is_public_renderer = rel in RENDERER or rel.startswith(RENDERER_DIR)
        in_css_scope = ext == ".css" and (rel.startswith("public/assets/css/"))
        for i, line in enumerate(lines, 1):
            prev = lines[i - 2] if i > 1 else ""
            marked = "dynamic geometry" in line or "dynamic geometry" in prev
            if ext == ".php" and not is_public_renderer and RE_STYLE_ATTR.search(line):
                hits["G1"].append((rel, i, line))
            if ext == ".js" and (RE_JS_STYLE.search(line) or RE_STYLE_ATTR.search(line)) and not marked and not is_public_renderer:
                hits["G1"].append((rel, i, line))
            if in_css_scope and not rel.endswith("app/00-tokens.css") and RE_COLOR.search(line):
                if rel.endswith("app/30-bootstrap-bridge.css") and ("data:image/svg" in line or "--bs-backdrop-bg" in line):
                    pass
                else:
                    hits["G2"].append((rel, i, line))
            if ext in (".php", ".js") and not is_public_renderer and RE_LEGACY.search(line):
                hits["G3"].append((rel, i, line))
            if RE_DARK.search(line):
                hits["G4"].append((rel, i, line))
            if ext in (".php", ".js") and RE_SWAL.search(line):
                hits["G5"].append((rel, i, line))
            if ext == ".php" and not is_public_renderer and RE_HANDLER.search(line):
                hits["G6"].append((rel, i, line))
            if in_css_scope and (rel.startswith("public/assets/css/app/") or rel.startswith("public/assets/css/builder/")) \
                    and RE_PHYS.search(line) and "data-align" not in line and "/* physical */" not in line:
                if not re.match(r"\s*(/\*|\*)", line):
                    hits["G7"].append((rel, i, line))
            if ext in (".php", ".js") and RE_HELPER.search(line):
                hits["G10"].append((rel, i, line))
            if RE_EMOJI.search(line):
                hits["G8"].append((rel, i, line))
            if RE_ARABIC.search(line):
                hits["G9"].append((rel, i, line))
    names = {"G1": "inline-style", "G2": "color-literal", "G3": "legacy-utility", "G4": "old-dark-mode", "G5": "swal-colour",
             "G6": "inline-handler", "G7": "physical-css", "G8": "emoji", "G9": "arabic-letters", "G10": "legacy-helper"}
    bad = 0
    for g, items in hits.items():
        for rel, i, line in items[: (10_000 if args.verbose else 6)]:
            print(f"{g} {rel}:{i}  {line.strip()[:110]}")
        if len(items) > (10_000 if args.verbose else 6):
            print(f"{g} ... {len(items) - 6} more (use --verbose)")
        bad += len(items)
    print()
    for g in hits:
        print(f"{g} {names[g]:<15} {len(hits[g])}")
    print(f"scanned {scanned} files; total findings {bad}")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
