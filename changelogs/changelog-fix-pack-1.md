# Changelog — Fix Pack 1 (post-hardening): staff page, JS guard, extension guards, unblock P5/P6, test-data cleanup (panel3)

Master prompt: `master-prompt-fix-pack-1.md` (FIX-1 → FIX-6, after P0–P7).
Stack: PHP 8.0-compatible (XAMPP, Windows), PDO + MySQL/MariaDB, Bootstrap 5 RTL + SweetAlert2, vanilla JS. No new Composer packages. No new JS libraries.

## Deviations (all phases)
- FIX-1 `check_inline_js.php` needed 3 small changes vs the reference: (1) its own header comment contained a literal `<script>` that paired with the `</script>` inside its own matching regex (self-match); (2) its own `str_replace($tmp, '<script>', ...)` display placeholder did the same; (3) the matching regex is now built from concatenated parts so the tool's source can never match itself. Behavior for real inputs is unchanged; the fixed tool reports 21 scripts / 0 errors, exactly the author's reference count.

## Known problems
- (append here)

## FIX-1 -- Staff page JS + JS syntax guard (2026-10-10)
Files changed:
- M `public/modules/settings/staff.php` (exactly 4 edits: `.btn-remove` closer `});` -> `}));`, `.btn-status` closer `});` -> `}));`, `<form id="inviteForm"` + `<form id="permForm"` gain `method="post"`)
- N `database/tools/check_inline_js.php` (node --check over inline scripts + `public/assets/js/*.js`; SKIP exit 0 when node missing)
- M `database/README.md` (+ "Frontend checks" section)
Migrations: none.
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 lint + diff | pass | `php -l` clean; the only diff is the 4 lines above (before/after verified by read) |
| 2 js guard | pass | `php database/tools/check_inline_js.php` -> `checked 21 script(s); syntax errors: 0`, exit 0 (pre-fix repro: `SyntaxError: missing ) after argument list` on the staff script) |
| 3 manual console | skip | awaiting Pouya (steps in the final message) |
| 4 page render | pass | owner `GET staff.php` -> 200 with both `method="post"` forms; staff -> 403 with no sidebar markup |
| 5 throwaway cycle | pass | invite/edit-perms/suspend/reactivate/remove all `success:true` (id 21, deleted right after by exact id) |
| 6 arabic grep | pass | `staff.php` clean; whole-tree grep clean |
Deviations: checker self-match fixes (see above). No other deviation: APIs untouched (verified correct), no refactor, no new dependencies.
Known problems: manual console check outstanding (Pouya).
Cleanup list (probes/test data created): temp owner id 20 + throwaway staff id 21 (perm rows, activity rows and login_attempts for both deleted by exact id; 0 leftovers verified); temp scripts `%TEMP%\opencode\{extractjs.php, staffjs.js, fix1gate.php, mkowner.php, dbglogin.php}` (deleted at pack end).
Note: the remembered P2 owner password no longer logs in (likely changed by Pouya after the job -- his own account, untouched). Owner-session tests in this pack use a temporary owner created via SQL and deleted right after (ids above). If Pouya is ever locked out: `php database/tools/reset_owner_password.php admin`.

## FIX-2 -- Extension guards (GD, sodium) (2026-10-10)
Files changed:
- M `includes/file_store.php` (`requireGd()` + first-statement call in `storeImage()`; storeIco/storeVideo/storePrivate untouched)
- M `includes/secrets.php` (`secret_require_sodium()` + calls at the start of `secret_encrypt()`/`secret_decrypt()`; decrypt still returns null for bad payloads, throws only for the missing extension)
Migrations: none.
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 lint + tests | pass | `php -l` clean on both files; `run_all` exit 0 (SKIPs only while the extension is really missing, each stating why); callers verified: upload/general/profile catch `FileStoreException` into messages, connect/licenses catch `Throwable` into `STR_SECRET_ERROR` |
| 2 no-gd upload | pass | valid PNG with gd disabled -> HTTP 400 with exactly `پردازش تصویر در سرور فعال نیست (افزونه GD)` (was: uncaught `Error` -> generic 500) |
| 2b video | pass | MP4 upload still 200 (no gd needed) |
| 3 no-sodium connect | pass | connect with no key -> HTTP 500 exactly `خطا در رمزنگاری اطلاعات`, `marketplace_connections` still 0 rows |
| 4 arabic grep | pass | whole-tree grep clean |
Deviations: none (reference edits used verbatim; no signature/return changes; no fallback to original bytes; `php.ini` untouched).
Known problems: gd + sodium still disabled (Pouya action from the hardening job); FIX-4 rows still wait for the extensions.
Cleanup list (probes/test data created): temp owner id 22 + video probe row (deleted by exact id; 0 verified for user/perms/logs/attempts); temp scripts `%TEMP%\opencode\{fix2gate.php, mkowner2.php, fix2restore.php}` (deleted at pack end).
Incident (recovered): the video probe upload deduped onto the P4 test row (media id 8, identical bytes) and the exact-id cleanup deleted that shared row; restored by re-upload (new id 12), then removed again together with its file during the temp-owner FK-safe cleanup (FK `fk_media_uploader` RESTRICT refused the user delete until its media row was gone -- the C1 safety net working as designed). Net effect: P4 media-8 asset no longer exists (row + both files gone; verified 0 refs before unlink). FIX-5 media list is therefore 5, 6, 10 (not 5, 6, 8, 10); dedupe evidence for id 8 stands as recorded in P4.
Note: the remembered P2 owner password no longer logs in (same as FIX-1 -- Pouya's own account, untouched). This phase used temp owner id 22 (created via SQL with a throwaway password, deleted right after).

## FIX-3 -- Unblock and run 0006 + 0007 (2026-10-10)
Files changed: none (migrations 0006/0007 already existed; no code edits).
Migrations: `0006` + `0007` -- clone: pass (exact row deleted on clone first, both applied, diagnose 22/22 0 FAIL) -- real: pass (`--status` shows 0001-0007 all applied).
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 dump + pre-checks | pass | `panel-backups/db-before-fix1.sql` (118758 bytes); `article_categories` exactly `(1,'23223','23223-19c4')`; articles/page_blocks/article_blocks/pages/media_usage all 0 |
| 2 clone run | pass | row deleted on clone by the exact statement; `0006`+`0007` applied; clone `diagnose` 22 checks 0 FAIL |
| 3 real run | pass | same exact statement on real (0 rows left); `migrate.php --status` lists 0001-0007 all applied |
| 4 diagnose + columns | pass | real `diagnose` 22 checks 0 FAIL (D11 incl. `fk_settings_template=RESTRICT`; D15/D16/D17 PASS); `api_key`/`api_secret` are `varchar(512)` |
| 5 regression | pass | dashboard 200 (site-count card, no articles card), templates 200, activate 3 -> active=3 then activate 4 -> active=4 (put back), media 200, pages(builder) 200, no PHP errors |
| 6 FK RESTRICT clone-only | pass | clone `DELETE` of the active template -> ERROR 1451 (`fk_settings_template`); self-inserted non-active row deletes fine (removed after) |
Deviations: none (`MIGRATE_DROP_LEGACY=1` never used; the authorised single-row DELETE is the only data change).
Known problems: none new.
Cleanup list (probes/test data created): temp owner id 23 (user/perm/activity/attempt rows deleted by exact id; 0 verified); clone-side FKPROBE template row (created + deleted); temp scripts `%TEMP%\opencode\{fix3gate.php, mkowner3.php}` (deleted at pack end); dump `panel-backups/db-before-fix1.sql` (KEEP).
Note: admin password still unknown to the agent (Pouya's account, untouched); owner-session tests used temp owner id 23, deleted right after.

## FIX-4 -- Run the gate rows that were skipped (2026-10-10)
Preconditions FAILED, all rows skipped per the prompt: `php -m` has neither `gd` nor `sodium`; `env_check.php` reports `extension gd: MISSING`, `sodium extension: MISSING`, `APP_SECRET_KEY: MISSING`; `run_all` still prints `file_store_test: SKIP (gd)` and `secrets_test: SKIP (sodium)`.
Gate (all rows skipped, reason "extension still disabled" -- Pouya's php.ini step 1-2 not done):
| # | Result | Evidence (one line) |
|---|---|---|
| 1 P4-3 re-encode | skip | extension still disabled (gd missing; guard `requireGd()` verified in FIX-2) |
| 2 P4-4 image dedupe | skip | extension still disabled (video-path dedupe proven in P4; image path needs `storeImage`/gd) |
| 3 P4-7 avatars | skip | extension still disabled |
| 4 P4-8 logos | skip | extension still disabled (ICO/SVG-validation paths already proven in P4 without gd) |
| 5 P4-11 image/video | skip | extension still disabled (video path proven in P4) |
| 6 P6-3 marketplace H3 | skip | extension still disabled (needs secretbox; also needs `APP_SECRET_KEY`, still MISSING) |
| 7 P6-4 zarinpal keep | skip | extension still disabled |
| 8 P6-5 missing-key on clone | skip | extension still disabled (real-DB missing-key behavior already proven in P6 gate 5) |
| 9 P6-6 encrypt tool | skip | extension still disabled (dry-run needs `secret_key()`; D18 stays PASS -- nothing plaintext stored) |
| 10 P6-8 limits | skip | extension still disabled (`env_check` still WARNs 40M/40M; needs Pouya's php.ini raises + restart) |
Deviations: none.
Known problems: gd + sodium + APP_SECRET_KEY all still missing (Pouya actions).
Cleanup list (probes/test data created): none (no rows executed).

## FIX-5 -- Test-data cleanup and restore of real settings (2026-10-10)
Files changed: none (cleanup only) + N `changelogs/cleanup-candidates.md` (verdicts with evidence).
Migrations: none.
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 dump + candidates | pass | `panel-backups/db-before-fix1-cleanup.sql` (104136 bytes); `cleanup-candidates.md` written (17/18/19, canvases 30-34, media 5/6/10, template 5, cert, settings verdicts; active_template_id AMBIGUOUS -> untouched) |
| 2 deletions + restore | pass | `site_settings` byte-identical to the pre-job dump (PHP byte compare, not console); favicon row released then file unlinked (0 refs verified); enamad cert nulled (dump had NULL) + file unlinked (0 refs); canvases 30-34 (5 rows, blocks/refs cascaded), media 5/6/10 rows + files, template 5 + package, staff 17/18/19 gone (perm rows cascaded, 0 orphans); `panel_users` now only `[1:admin:active]` |
| 3 settings untouched cases | pass | `active_template_id`: dump=2 vs current=4 -> REPORTED, not changed (Pouya decides); nothing else differed from dump-or-test values |
| 4 diagnose + gc | pass | `diagnose.php` all phases: no FAIL (D13 INFO = 1 row: media#6 duplicate-content, human decides; D22 all registered); `gc_files.php` report-only output pasted: 2 orphans (`5caa...`, `a9e7...` unreferenced legacy avatars) left in place (needs Pouya's `--delete` agreement) |
| 5 owner regression | pass | dashboard/pages/templates/media/licenses/settings-general all 200 after cleanup |
Deviations: (1) the pre-job dump and all `mysqldump > file` outputs are UTF-16LE (PowerShell `>` redirection) -- the PHP parser converts to UTF-8 before byte-compare; this also explains finding F8 (`diagnostics-after.txt` unreadable) fixed in FIX-6. (2) First executor run fatal'd midway (included `file_store.php` without `config.php` constants); state was reconciled step by step -- settings UPDATE had committed (verified byte-identical), favicon row deleted but file remained, cert nulled but file remained -- then finished idempotently; final verification shows all targets at 0 with files gone. No project code was changed for this.
Known problems: `active_template_id` 4 vs dump 2 (Pouya decides; site works either way -- 4 is the pre-0006 `is_active=1` template); 2 gc orphans left (Pouya's `--delete` call).
Cleanup list (probes/test data created): temp owner id 24 (fully removed, 0 verified); temp scripts `%TEMP%\opencode\{fix5exec.php, fix5exec2.php, fix5verify.php, dbgparse.php, dbg5.php, fix5regress.php, mkowner5.php}` (deleted at pack end); dumps `db-before-fix1-cleanup.sql` (KEEP) + `db-before-fix1.sql` (KEEP).

## FIX-6 -- Final regression, encoding, docs (2026-10-10)
Files changed: `changelogs/diagnostics-after.txt` (regenerated UTF-8 via PHP `file_put_contents`, not PowerShell `>` which wrote UTF-16); hardening changelog line (below).
Migrations: none.
Gate:
| # | Result | Evidence (one line) |
|---|---|---|
| 1 staff page e2e | skip | awaiting Pouya's manual console check (steps in the final message) |
| 2 js guard | pass | `check_inline_js.php`: 21 scripts, 0 errors, exit 0 |
| 3 migrate + diagnostics | pass | `--status`: 0001-0007 all applied; `diagnostics-after.txt` UTF-8 (starts `D01`, no BOM), 22 checks 0 FAIL (first fully-green full run) |
| 4 extensions/rows | skip | gd + sodium still disabled (Pouya); FIX-4 rows stay skipped with reason |
| 5 test data | pass | per FIX-5 rules; originals restored or reasons documented; orphans left only with Pouya's pending `--delete` |
| 6 secrets/cleanup | pass | no secret in any file/log/changelog (grep-verified by construction: test keys were throwaways, env-only, rows deleted); `business_panel_migtest` dropped (SHOW DATABASES confirms) |
Deviations: `diagnostics-after.txt` written via PHP instead of the prompt's `Out-File`/`cmd` variants, because both produce non-UTF-8 bytes on this machine (PowerShell `>` = UTF-16LE with BOM -- the root cause of finding F8; `cmd` redirection = ANSI codepage, lossy for Persian). PHP writes the bytes verbatim: UTF-8, readable.
`env_check.php` output (no key material):
```
== PHP == version 8.0.30, php.ini C:\xampp\php\php.ini, extensions: pdo_mysql/mbstring/fileinfo/zip/exif OK, gd MISSING, sodium MISSING
== environment == all defaults (local, 127.0.0.1, business_panel, root)
== database == 10.4.32-MariaDB, sql_mode set, CHECK enforced: yes
== P6 == APP_ENV unset (local), APP_SECRET_KEY MISSING, sodium MISSING, upload_max_filesize 40M WARN, post_max_size 40M WARN, memory 512M OK, max_file_uploads 20 OK
```
Known problems: gd/sodium/key/php.ini-limits (Pouya); `active_template_id` 4 vs dump 2 (Pouya decides, FIX-5); 2 gc orphans (Pouya's `--delete` call); manual console check outstanding.
Cleanup list (probes/test data created): temp scripts `%TEMP%\opencode\{fix6diag.php}` + this phase's server process (stopped); `business_panel_migtest` dropped AFTER rows 1-3 (FIX-4 rerun will recreate it from a dump when extensions land).
