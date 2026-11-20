#!/usr/bin/env python3
"""Part 10B gate: docs/ui-pages.md must have a row for EVERY real page of the panel (none forgotten).

    python tests/ui/check_pages_covered.py            (run from the project root)

A "real page" is a .php file under public/ that renders HTML for a person: everything under public/ except
public/api/, public/file.php (file streamer), public/logout.php and public/index.php (redirect only).
Plus the two non-file pages every panel has and the builder chrome (listed in EXTRA).
A page counts as covered when its path (for example `modules/articles/index.php`) appears in docs/ui-pages.md.
Exit 0 = all covered, 1 = something is missing (the names are printed).
"""
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / "docs" / "ui-pages.md"
SKIP_DIRS = {"api", "uploads", "assets", "ui-kit"}
SKIP_FILES = {"file.php", "logout.php", "index.php"}
# index.php at the public root is a redirect; module index.php files are real pages, so only the root one is skipped.
EXTRA = ["includes/permissions.php", "builder chrome"]  # 403 page lives in permissions.php; the builder chrome is JS-rendered


def real_pages():
    pub = ROOT / "public"
    out = []
    for p in sorted(pub.rglob("*.php")):
        rel = p.relative_to(pub)
        if rel.parts[0] in SKIP_DIRS:
            continue
        if len(rel.parts) == 1 and rel.name in SKIP_FILES:
            continue
        out.append(rel.as_posix())
    return out


def main():
    if not DOC.exists():
        print(f"FAIL {DOC.relative_to(ROOT)} does not exist")
        return 1
    text = DOC.read_text(encoding="utf-8")
    missing = [p for p in real_pages() + EXTRA if p not in text]
    pages = real_pages()
    for p in pages + EXTRA:
        print(("PASS " if p not in missing else "FAIL ") + p)
    print(f"{len(pages) + len(EXTRA) - len(missing)} covered, {len(missing)} missing")
    return 1 if missing else 0


if __name__ == "__main__":
    sys.exit(main())
