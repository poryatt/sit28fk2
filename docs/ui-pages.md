# Page-by-page UI analysis (Part 10B)

Written **before** any page markup is changed (step B1), then updated at the end of each page step with the "Result" line.
Eight dimensions per page: **(1)** purpose and user · **(2)** permissions and states · **(3)** problems found today (evidence) · **(4)** layout decision · **(5)** components used (Part 10A catalog only) · **(6)** RTL notes · **(7)** accessibility notes · **(8)** frozen hooks and behavior.
Every real page and every non-file page of the panel has an entry; `python tests/ui/check_pages_covered.py` fails when a page path is missing from this file.
Entries below are **pre-filled from reading the code**. In B1 you must re-read each page, correct anything that is not true of your copy, and add what you find. Mark every correction `[corrected]` and every addition `[added]`.

---

## `login.php`
1. **Purpose / user:** sign in with e-mail or username + password. Used by every person, signed out; the first impression of the product.
2. **Permissions / states:** public. States: default, error (`$error`), notice (`$notice`, for example a session that expired), submitting. No `data-theme-url` (nobody is signed in, so the theme is only remembered in `localStorage`).
3. **Problems:** two inline `style=""` attributes; purple hard-coded in the page; alerts are color-only; no theme control; the password cannot be revealed; no brand mark; not themed in dark mode.
4. **Layout:** one centered `.auth-card` on a tinted page: brand mark, `<h1>`, the form, the alerts above the form with icon + text, a theme toggle at the page corner (`.auth__theme`).
5. **Components:** `.auth`, `.auth-card`, `.field`, `.input-icon--action` + `data-password-toggle`, `.alert` (with icon), `.btn-primary`, `.icon-btn`.
6. **RTL:** identifier and password inputs are `dir="ltr"` content in an RTL layout (`.input-ltr`); the placeholder alignment stays at the inline-start; the eye button sits at the inline-end of the field.
7. **A11y:** visible labels (not placeholders), `autocomplete="username"` / `current-password`, alert `role`, the toggle has an `aria-label` that changes with its state, focus ring from A.
8. **Frozen:** field names `identifier`, `password`, `csrf_token`; ids `login-identifier`, `login-password`; form method/action; the PHP above the `<!DOCTYPE html>` line (login logic) is not touched.

**Result (B2):** `php -l` clean. Rendered signed-out in both themes at 360/768/1280: no horizontal overflow, card centered (`[20,340]` at 360, `[432,848]` at 1280). Theme toggle flips `data-bs-theme` both ways and the eye button switches the input to `type=text` and the label to «پنهان کردن رمز عبور». Wrong password against the clone DB shows `role="alert"` with the `ti-alert-circle` icon and text «نام کاربری یا رمز عبور اشتباه است.»; exactly one `<h1>`. `check_pages_semantics.py --pages /login.php` → 10 passed, 0 failed. `[corrected]` 10A needed the `data-theme-toggle` / `data-password-toggle` hooks that this page uses; after the 10A rebase they exist in `app.js`.

## `modules/dashboard/index.php`
1. **Purpose / user:** the first screen after sign-in. Answers "what is the state of my site and what do I do next?" in one glance. Owner and staff.
2. **Permissions / states:** needs `dashboard.view` (the page guard stays). The activity card is **owner only** (`isOwner()`). Empty states: no pages yet, no activity yet.
3. **Problems:** a decorative welcome card with an emoji; the card `نوع پنل`; the dead button `مشاهده راهنما`; stat numbers without a hint; no list of recent work; no activity feed; plan/upgrade wording.
4. **Layout:** exactly Part 10A §10: page head (greeting line + `ساخت صفحه جدید`, `آپلود رسانه`), four `.stat` cards, then `آخرین صفحه‌ها` (table card) beside `فعالیت‌های اخیر` (owner only).
5. **Components:** `.page-head`, `.stat`, `.table-card`, `.badge-soft-*`, `.activity-list`, `.empty-state`, `data-countup`.
6. **RTL:** stat order reads right-to-left; the trend text carries its own icon; Jalali dates through `toPersianDigits(toJalaliDateTime())`.
7. **A11y:** `<h2 class="card-head__title">` per card (the page `<h1>` is the topbar title), `<th scope="col">`, icon + word for every status, count-up respects reduced motion (A).
8. **Frozen:** permission keys; the data comes from `BuilderPagesList::counts`, `BuilderPagesList::latest` and `dashboardRecentActivity` (Part 10C), never from new SQL in the page; no IP address is ever printed.
9. **Result (B4):** whole-file appendix markup, `php -l` clean. Live against the clone DB: owner sees 4 `.stat` cards with real counts, latest-pages links and the activity feed; `uit_view` (dashboard.view only) sees the stats but no activity feed, no page links, no head actions and no "first page" CTA. One `<h1>` (topbar), no heading skips, no JS errors, no overflow at 1280 or 360, `data-theme="dark"` applies. Expected environment 404s only: the avatar row points at a file absent from `uploads/avatars/` and the Vazirmatn woff2 is not vendored offline (`fetch_vendor.php`); neither comes from this page.

## `modules/pages/index.php` (builder host)
1. **Purpose / user:** the page builder screen: title, canvas, publish box, meta box. Editors.
2. **Permissions / states:** view vs edit (`$canEdit`); invalid id and "nothing selected" states; the builder fills the canvas through JS.
3. **Problems:** `card p-3` markup with no hierarchy; the invalid/none states are bare cards; the host sets no flat-content mode; builder chrome colors are literals with a `body.dark-mode` block (dead after Part 10A).
4. **Layout:** `$mainClass='app-content--flat'`; 9/12 column = title card + canvas card; 3/12 side column = publish card + meta card; invalid/none states become `.empty-state` with a way back.
5. **Components:** `.card`, `.card-head`, `.card-pad`, `.field`, `.badge-soft-*`, `.empty-state`, `.alert`.
6. **RTL:** the back link uses `ti-arrow-right` (RTL "back"); toolbar order follows reading order.
7. **A11y:** labelled title input, `role="status"` alerts with icon, status/dirty badges carry icon + word (built by the `setBadge` helper), publish buttons are real buttons.
8. **Frozen:** every `id` (`builder-root`, `builder-title`, `builder-canvas`, `builder-canvas-state`, `builder-status-badge`, `builder-dirty-badge`, `builder-updated`, `builder-btn-save|preview|publish|new-page|back-list`, `builder-actions`, `builder-type|slug|count|id`, `builder-title-hint`) and every `data-*` on `#builder-root`; the PHP above the markup except the two edits named in the prompt.
9. **Result (B7):** `php -l` clean. `$mainClass='app-content--flat'` is emitted (`<main class="app-content app-content--flat">`). Live owner runs: canvas entry, `buildable_type=page&new=1` and the invalid entry all return 200 with one `<h1>`, no overflow at 1280/360, the builder root + canvas + two side cards render, the status badge uses `badge-soft`, and no JS errors beyond the two known environment 404s (avatar file, unvendored Vazirmatn). The "no template" state was checked by temporarily clearing `active_template_id` in the clone DB: themed `.empty-state` with `هنوز قالبی فعال نیست` and the templates CTA, no builder root; then restored to `1`.

## `modules/articles/index.php`
1. **Purpose / user:** list, search, filter (status), edit, preview and delete pages. Editors and viewers.
2. **Permissions / states:** `pages.edit` shows ویرایش, `pages.view` shows مشاهده/پیش‌نمایش, `articles.delete` shows حذف; states: no pages at all, no match for the filter/search, one page of results, several pages.
3. **Problems:** purple/inline utilities; actions are text buttons of different sizes; the empty state is one line; pagination shows every page number; the filter tabs are not tabs; status is color only.
4. **Layout:** page head (one lead line + the one primary action) → one table card with the toolbar (search + status tabs with counts) → table → pagination in `.card-foot`.
5. **Components:** `.page-head`, `.table-card`, `.toolbar`, `.input-icon`, `.nav-pills`, `.badge-soft-*`, `.cell-title|slug|actions`, `.empty-state`, pagination.
6. **RTL:** the slug is `ltr`-isolated (`.cell-slug`); prev/next wording instead of arrows (no mirrored arrow can be wrong); numbers in Persian digits.
7. **A11y:** `scope="col"`; icon-only row actions have `aria-label` + `data-tip`; the active tab has `aria-current="page"`; the search form has `role="search"` and a labelled input.
8. **Frozen:** `data-act="delete-page"`, `data-id`, `data-title` (read by `pages-manager.js`); query parameters `status`, `q`, `page`; the PHP above the cut point.
9. **Result (B5-articles):** tail replaced from `<?php if ($totalPages === 0): ?>` to EOF; `php -l` clean. Live owner (two page canvases inserted in the clone): themed page head, status tabs with exactly one active, table with scoped `th`; `?q=zzzznomatch` shows the "no match" `.empty-state` with no table; no JS errors; no overflow at 360. `uit_view` (no `articles.view`) still gets 403. No removed `data-act`/`data-id`/`data-title` attributes.

## `modules/templates/index.php`
1. **Purpose / user:** see the active template, browse installed/available templates by category, activate/install one, (owner) upload a ZIP.
2. **Permissions / states:** `templates.edit` shows activate/install; **upload is owner only** (the API is owner only, so the form is hidden for others); states: empty category, one page, several pages.
3. **Problems:** `row mb-4` stats as plain cards; thumbnails with inline sizing/utilities; the upload form is shown to people the API rejects; the "active" state is only a button label.
4. **Layout:** active-template card (8/12, or full width for non-owners) + upload card (4/12, owner only) → category pills with counts → responsive grid of template cards (active one marked with a badge and an accent border).
5. **Components:** `.card`, `.card-head`, `.kv`, `.badge-soft-*`, `.nav-pills`, `.empty-state`, template card styles from `45-pages.css`.
6. **RTL:** the version string is ltr-isolated in the title; card actions align to the inline-end.
7. **A11y:** decorative thumbnails `alt=""`; the upload input has a visible label; buttons carry text; pills use `aria-current`.
8. **Frozen:** `#uploadForm`, `.btn-activate`, `.btn-install`, `data-id`; endpoints `/api/templates/{activate,install,upload}.php`; the query parameters `category`, `page`.
9. **Result (B5-templates):** tail replaced at `<div class="row mb-4">`; `php -l` clean. Owner: active-template card, upload form, 9 category pills, template grid with `alt=""` thumbnails; no JS errors; no overflow at 360. `uit_tpl` (staff, `templates.view` only): page 200, active card still visible, **no** upload form. `[not run]` install/activate round trip: the API copies template files under `storage/`, which this clone-check must not touch; the endpoints and `data-id` hooks are unchanged. Empty-category state not exercised (no category with zero templates in the clone).

## `modules/media/index.php`
1. **Purpose / user:** upload, search, filter (image/video), preview and delete media. Editors; viewers can look.
2. **Permissions / states:** upload needs `media.edit`; delete needs `media.delete` (the preview modal shows the delete button only then); states: GD missing (warning or blocking), empty result, uploading, duplicate file.
3. **Problems:** `onclick` attribute on tiles; inline-width progress bars; `bg-white`/`bg-light` utilities; the delete button is visible to people the API would reject; the modal lacks labels; the filter tabs are plain links without a current-page mark.
4. **Layout:** page head lead → (editors) alert about GD if relevant → drop zone card → toolbar (filter pills + search) → responsive `.media-grid` of tiles → pagination → preview modal.
5. **Components:** `.drop-zone`, `.toolbar`, `.nav-pills`, `.input-icon`, `.kv`, `.empty-state`, `.alert` with icon, media-grid styles from `45-pages.css`, Bootstrap modal.
6. **RTL:** file names are bidi-isolated; sizes `کیلوبایت` after the number; the progress rows read right-to-left with the percentage at the inline-end.
7. **A11y:** tiles are `<button>` (keyboard and screen reader operable) with `aria-haspopup="dialog"`; focus returns to the tile when the modal closes; the drop zone is a keyboard-operable `role="button"`; progress uses `<progress>` with a label; `aria-live` for the upload list; deletion asks for confirmation.
8. **Frozen:** endpoints `/api/media/upload.php` and `/api/media/delete.php`; ids `mediaDrop`, `progressArea`, `previewModal`, `previewBody`, `btnDeleteMedia`; query parameters `type`, `q`, `page`.
9. **Result (B5-media):** tail replaced at `<div class="card p-4 mb-3">` (old `upload.js`/inline script gone with it); `php -l` clean. Live: empty state `فایلی یافت نشد` + upload control for the owner; with one seeded library PNG, the tile opens the preview modal, the image loads, the owner sees `#btnDeleteMedia`, `uit_media` (media.view only) does **not**, and Esc closes the modal returning focus to the tile. No JS errors; no overflow at 360. `[not run]` real uploads (writes to shared `uploads/`), GD-missing branch (GD is present), duplicate-file message, and the delete confirmation's API call; UI affordances exist and the API is unchanged. The seeded file/row were removed after the check.

## `modules/licenses/index.php`
1. **Purpose / user:** record licenses and the payment-gateway details and see their review status. Owner/staff with `licenses` access.
2. **Permissions / states:** `licenses.view` to open; `licenses.edit` to submit (the whole form is a disabled `<fieldset>` and the buttons are hidden otherwise); states per license: `not_connected`, `pending`, `verified`, `rejected`.
3. **Problems:** `bg-light`/`text-dark` utilities; the status badge is color only; the "test transaction" button has no handler anywhere (dead control); the third license type lets the person pick the review status themselves (a self-declared `verified`) — a trust problem to report, not to fix here; secrets share a form with plain fields without a hint.
4. **Layout:** page head lead → a responsive grid of license cards (one per license): card head = name + status badge, form fields, actions, foot with the last update.
5. **Components:** `.card`, `.card-head`, `.field`, `.input-ltr`, `.badge-soft-*`, `.card-foot`, license card styles from `45-pages.css`.
6. **RTL:** codes, merchant ids and API keys are `ltr` inputs; the certificate link uses an external-link icon.
7. **A11y:** every input labelled (`for`/`id` unique per license), the API-key field has `autocomplete="new-password"` and a hint, disabled state is visible and explained by the page lead.
8. **Frozen:** form class `license-form` + `data-type`, field names `enamad_code`, `certificate`, `merchant_id`, `api_key`, `license_number`, `status`; endpoint `/api/licenses/update.php`; the `badge()` mapping of statuses to Persian words; the SQL.
9. **Result (B5-licenses):** whole-file replacement after the diff (SQL, `badge()` word map, submit handler and `requirePermission('licenses','view')` identical; only `$canEditLicenses`, the disabled fieldset, the `badge-soft` markup and the disabled `btn-test` were added). `php -l` clean. Live: owner gets 3 licence cards, 3 `badge-soft` status badges, every input labelled, fields enabled and submit buttons present; `uit_lic` (`licenses.view` only) gets the same cards with `fieldset[disabled]` and no submit buttons; no JS errors; no overflow at 360. Only `not_connected` and `pending` statuses exist in the clone (no `verified`/`rejected` rows, so the test-transaction button was not rendered).

## `modules/settings/profile.php`
1. **Purpose / user:** the signed-in person edits their own name, e-mail, phone, avatar, theme preference and password.
2. **Permissions / states:** any signed-in user; states: forced password change (`must_change_password`), saved (`?ok`), wrong current password (`?err`), avatar present/absent.
3. **Problems:** one long form without grouping; the password block is not explained; alerts are color only; the avatar has no preview shape; no sticky save.
4. **Layout:** two cards: account (avatar + 2-column field grid; 7/12) and security (password; 5/12) → one sticky `.form-actions` bar.
5. **Components:** `.card`, `.card-head`, `.form-grid`, `.field`, `.input-icon--action` with `data-password-toggle`, `.avatar-lg`, `.form-actions--sticky`, `.alert` with icon.
6. **RTL:** e-mail/phone are `ltr` inputs aligned at the inline-start of their box; the password eye at the inline-end.
7. **A11y:** labels, `autocomplete` tokens, alert roles, the password toggles have `aria-label`s.
8. **Frozen:** field names `full_name`, `email`, `phone`, `theme_preference`, `avatar`, `current_password`, `new_password`; `csrf_field()`; the POST handler above the cut.
9. **Result (B6-profile):** tail replaced at the `must_change_password` line; `php -l` clean. Owner and `uit_set`: two cards, sticky save bar, avatar input, two `data-password-toggle` buttons; clicking a toggle switches its input to `type=text` and `aria-pressed="true"`; no JS errors; no overflow at 360. A password save and avatar upload were not executed (they write to the real account's row in the clone and to shared `uploads/`); the POST handler above the cut is unchanged.

## `modules/settings/general.php`
1. **Purpose / user:** site name, domain, logo, favicon. Owner/staff with `settings` access.
2. **Permissions / states:** view vs edit (`$canEdit`: the fieldset is disabled and the save bar hidden, with an info alert); saved (`?saved`), validation error (`$error`).
3. **Problems:** a flat form; previews are bare images; accepted formats are not stated; read-only mode only differs by missing button.
4. **Layout:** one 8/12 card with two titled sections (`هویت سایت`, `تصاویر برند`), previews under each file input, the save bar in the card foot.
5. **Components:** `.card`, `.settings-section`, `.form-grid`, `.field` + `__hint`, `.asset-preview`, `.form-actions`, `.alert` with icon.
6. **RTL:** the domain input is `ltr`; the previews align at the inline-start.
7. **A11y:** hints are tied with `aria-describedby`; a `<fieldset disabled>` for read-only; alert roles.
8. **Frozen:** field names `site_name`, `site_domain`, `logo`, `favicon`; `csrf_field()`; the POST handler above the cut.
9. **Result (B6-general):** tail replaced at the `?saved` line; `php -l` clean. Owner: editable form (no disabled fieldset) with the save bar. `uit_set` (`settings.view` only): `alert-info` + disabled fieldset + **no** save bar. One `<h1>`; no JS errors; no overflow at 360. Saving (which writes `site_settings` and `uploads/`) not executed.

## `modules/settings/staff.php`
1. **Purpose / user:** the owner adds colleagues and decides, per module, who may view/edit/delete.
2. **Permissions / states:** **owner only** (everyone else gets a themed 403 card); states: no staff yet, active/suspended rows, the two modals.
3. **Problems:** the permission summary is not visible in the list (you have to open the modal for every person); `die`-style 403; native-looking modal tables; actions are unlabeled.
4. **Layout:** page head (lead + `افزودن همکار جدید`) → table card (name, e-mail, status badge, **permission chips** `module · rights`, actions) → invite modal → permission modal with a labelled checkbox table.
5. **Components:** `.page-head`, `.table-card`, `.badge-soft-*`, `.perm-chip`, `.cell-actions`, `.field`, `.input-icon--action`, Bootstrap modals.
6. **RTL:** e-mails `ltr`-isolated; the checkbox columns follow reading order.
7. **A11y:** every checkbox has an `aria-label` ("مشاهده <module>"); `<th scope="row">` in the permission table; modal titles are headings; icon-only remove button has a label.
8. **Frozen:** ids `inviteForm`, `permForm`, `permBody`, `permUserId`, `permUserName`, `inviteModal`, `permModal`; classes `btn-edit-perm`, `btn-status`, `btn-remove`; the checkbox names `perm[<id>][view|edit|delete]`; endpoints under `/api/staff/`; the cascade rules in the checkbox change handler.
9. **Result (B6-staff):** whole-file replacement after the diff (only the `p.module_label` select and the themed non-owner branch changed). `php -l` clean. Live owner: page head, 7 staff rows (clone has extra test staff), permission chips, scoped `th`; invite modal opens and closes with Esc; permission modal shows the person's name, exactly 7 module rows and 21 checkboxes **all with `aria-label`**; the cascade works both ways (checking delete checks view+edit; unchecking view clears edit+delete); Esc closes; no JS errors; no overflow at 360. Non-owner: the central `owner_only` guard (`includes/access_map.php`) answers first with the themed full-page 403 (`۴۰۳ - دسترسی غیر مجاز`), so the in-page branch is unreachable for non-owners but stays themed if the guard is ever relaxed.

## `includes/permissions.php` (the 403 page)
1. **Purpose / user:** what a person sees when a guard refuses them (`denyAccess()`).
2. **Permissions / states:** can be reached signed-out or half-loaded; two forms: a full document (the guard ran before any output) or a card (output already started). API requests keep the JSON 403.
3. **Problems:** a `die()` with inline-styled `<h1>` in Tahoma: no theme, no shell, no way back.
4. **Layout:** the `.auth-card` pattern with a lock icon, the same title/text as before, and a button back to the dashboard.
5. **Components:** `.auth`, `.auth-card`, `.empty-state`, `.btn-primary`.
6. **RTL:** document `dir="rtl"`.
7. **A11y:** one `<h1>`, a real link/button, no color-only meaning.
8. **Frozen:** the HTTP status 403, the JSON response for API requests, the exact Persian title and sentence.

**Result (B2):** `php -l` clean. Verified live against a clone DB (`business_panel_uitest`, real DB untouched): a staff user with no permissions gets HTTP 403 with the themed `.auth-card`, the lock icon, title «۴۰۳ - دسترسی غیر مجاز» and the «بازگشت به پیشخوان» button; a staff user with only `dashboard.view` sees the dashboard (200) and gets 403 on `/modules/licenses/index.php`. A permission-guarded API (`POST /api/media/delete.php` as that staff user) still answers `403 {"success":false,"message":"دسترسی غیر مجاز"}`; the owner-only `invite.php` keeps its own JSON 403 message. `[corrected]` the inline branch prints only a card when the shell already printed.

## builder chrome (JS-rendered; no page file)
1. **Purpose / user:** the canvas toolbar, insert menu, settings panel, media picker, viewers (slider/story) inside the builder.
2. **Permissions / states:** edit vs read-only; selected/hover block; modal media picker.
3. **Problems:** color literals in all builder CSS and a `body.dark-mode` block per file; viewers hard-code black/white; `confirmButtonColor: '#dc3545'` in five Swal calls; the status/dirty badges are color-only; the text-editable focus is a faint shadow.
4. **Layout:** unchanged structure; colors and elevation come from tokens; viewer parts use the always-dark `--viewer-*` tokens; the inverted-pill toolbar uses `--text`/`--bg`.
5. **Components:** tokens only (no new classes), `setBadge` helper → `.badge-soft-*`.
6. **RTL:** logical properties for chrome CSS; the user-content alignment attributes (`data-align`) are left alone.
7. **A11y:** `:focus-visible` ring on the text-editable; disabled toolbar buttons keep ≥ 3:1 contrast; destructive confirmations focus *cancel*.
8. **Frozen:** every `id`, `data-*`, `.builder-*` class; the builder JS logic; `00-sortable.js` is never opened.
9. **Result (B3):** `tools/ui/migrate_builder_assets.py` rewrote 10 files from literal colors/`body.dark-mode` to tokens/`[data-theme="dark"]` and is idempotent (`wrote 10`, then `wrote 0`). `node --check` clean on every builder JS plus `app.js`/`admin.js`; `check_ui_gates.py` reports 0 builder findings in G1/G2/G4/G5; `check_tokens.py` 158/0. `run_builder_checks_async.py` 26 passed, 0 failed. The runner is load-flaky on this machine: an occasional first-page `wait_for_function` timeout (>30 s to boot Chrome under load); an immediate rerun passes and no check was changed. `[corrected]` the migration tool's three `body.dark-mode` regex replacements were changed to exact-string replacements for `[data-theme="dark"]`; the leftover dark focus block at `40-content-blocks.css:359` was deleted by hand.

