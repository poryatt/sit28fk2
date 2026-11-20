# Cleanup candidates — Fix Pack 1 / FIX-5 (2026-10-10)

Rule (DF5): a row/file is deleted automatically only if **all** hold: (a) id listed in the changelogs, (b) `created_at` inside the job window (2026-10-10, first changelog day), (c) not modified afterwards by anyone else. Anything ambiguous is listed here and NOT touched. (DF6: real settings restored from the pre-job dump only when the current value is exactly the known test value. DF7: `panel_app`/`panel_migrator`, `panel-backups/` and the three original avatars are always kept.)

## Verdict: DELETE (all three conditions hold; job-created test artefacts, unmodified outside the job)

| id | table | why |
|---|---|---|
| 17 | `panel_users` teststaff1 | P1/P7 test staff; created 08:15:16 job day; later updates are the job's own perm resets |
| 19 | `panel_users` c1staff | P2 re-invite; created 08:50:19 job day |
| 18 | `panel_users` removed-18 | P2 soft-delete test; anonymised row (job) |
| 30, 31, 32, 33, 34 | `builder_canvases` (M13 Tab1, C1 Page, P2 Regression, L11 Same Title x2) | gate canvases, all created 10-10; creators 1/18 are owner-or-test |
| 5, 6, 10 | `media_library` (c1.png, regress.png, f.ico) | 1px/ICO gate uploads (+ files on disk) |
| 5 | `templates` clean | P4 test upload (+ package file) |
| enamad cert | `licenses.certificate_file` | test PDF; pre-job dump has it NULL -> set NULL + delete file |
| `site_settings` row | test values | current is exactly (`P4 Site`, `example.com`, logo NULL, test favicon) -> restore dump originals; release test favicon via `mediaReleasePath` |
| `active_template_id` | AMBIGUOUS — REPORT ONLY | dump = 2, current = 4 (`panel`, the pre-0006 `is_active=1` template). Per rule: **do not change**; Pouya decides |

## Verdict: NEVER touch
- media 7 + the three files in `public/uploads/avatars/` (real avatar data; 5376 registered by backfill).
- `panel_app`, `panel_migrator`, `panel-backups/`, `business_panel_migtest` (kept until the unblocked re-verification; see FIX-5 changelog).
- Everything else in the database (not job-created).
