# Tests

```
php tests/run_all.php
node tests/js/builder_tree_test.js
python tests/browser/run_builder_checks.py
```

- `php tests/run_all.php` — all `tests/*_test.php` (non-zero exit on failure; `SKIP` = optional extension such as `gd`/`sodium` absent).
- `node tests/js/builder_tree_test.js` — `builder-tree.js` unit + parity test vs `includes/builder/tree_validator.php`.
- `python tests/browser/run_builder_checks.py` — 26 checks; reads `tests/browser/engine_contract.json`; needs a running server + Chrome.
