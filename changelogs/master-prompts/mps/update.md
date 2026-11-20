```markdown
# Refactor Instructions — Management Panel

## 1. Purpose

*This file instructs an automated agent to perform three specific refactor operations on the Management Panel codebase and database. It is a surgical change list, not a re-specification. The agent must apply exactly the changes described here and nothing else.*

---

## 2. Scope of Changes

*Three changes are required. Each change is independent and must be applied in full.*

| # | Change | Type | Affected Area |
| --- | --- | --- | --- |
| 1 | Remove the `store` module entirely, including its database tables | Deletion | UI + API + DB + permissions + dashboard |
| 2 | Rename the `pages` module sidebar label to `"ساخت سایت"` and wipe all its contents, leaving only one blank page | Rename + Data wipe | UI label + DB data |
| 3 | Rename the `articles` module sidebar label to `"صفحات ساخته شده"` and wipe all its contents | Rename + Data wipe | UI label + DB data |

Persian strings that must be preserved exactly as written (do not translate, do not reword):

- `"ساخت سایت"` — the new sidebar label for the former pages module
- `"صفحات ساخته شده"` — the new sidebar label for the former articles module

---

## 3. Change 1 — Remove the "راه‌اندازی فروشگاه" (Store Setup) Module

*The store module must be removed completely from the codebase, from the UI, from the permission system, and from the database schema.*

### 3.1 Files to delete (frontend + module pages)

Delete these files and any empty parent folder left behind:

```text
modules/store/index.php
modules/store/products.php
modules/store/product_create.php
modules/store/product_edit.php
modules/store/categories.php
```

### 3.2 Files to delete (API endpoints)

Delete every endpoint under `api/store/`:

```text
api/store/products_list.php
api/store/product_create.php
api/store/product_update.php
api/store/product_delete.php
api/store/categories_list.php
api/store/category_create.php
api/store/category_update.php
api/store/category_delete.php
```

Then delete the now-empty `api/store/` directory itself.

### 3.3 Sidebar update

In `includes/sidebar.php`, remove the entire menu item that renders the module with key `store` and the label `"راه‌اندازی فروشگاه"`. Do not leave a placeholder, comment, or disabled item behind.

### 3.4 Permission system cleanup

The following rows must be removed from the database (see §3.6). In addition, remove any hardcoded references to `store.view`, `store.edit`, and `store.delete` from:

- `includes/permissions.php` (any lookup table or switch that references the store module)
- Any seed file that lists the store permissions
- Any test or fixture that references the store permissions

### 3.5 Dashboard cleanup

In `modules/dashboard/index.php` and `api/dashboard/stats.php`:

- Remove the stat card that shows the products count.
- Remove the JSON field `products_count` from the dashboard stats response.
- Remove any "Set Up Your Store" call-to-action card if it exists.
- In `api/dashboard/trend.php`, remove the `products` series from the JSON response.
- In the `dashboard_snapshots` table (see §3.6), the `products_count` column may be dropped as part of the same migration. If the migration tool does not support dropping a column safely on the target MySQL version, keep the column but stop reading from it and stop writing to it.

### 3.6 Database changes

Run the following SQL as a single migration, in this exact order to respect foreign keys:

```sql
-- Step 1: drop dependent rows
DELETE FROM panel_user_permissions
WHERE permission_id IN (
  SELECT id FROM permissions WHERE module = 'store'
);
DELETE FROM permissions WHERE module = 'store';

-- Step 2: drop the store tables (order matters because of foreign keys)
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_categories;

-- Step 3 (optional, see §3.5): drop the products_count column from snapshots
ALTER TABLE dashboard_snapshots DROP COLUMN products_count;
```

### 3.7 Update `database/schema.sql`

Edit the master schema file to remove:

- The entire `CREATE TABLE product_categories (...)` block
- The entire `CREATE TABLE products (...)` block
- The entire `CREATE TABLE product_images (...)` block
- The `products_count` column definition inside `dashboard_snapshots` (if §3.6 step 3 was applied)
- Any `INSERT INTO permissions` rows whose `module` equals `'store'`

---

## 4. Change 2 — Rename "صفحات اصلی سایت" to "ساخت سایت" and Wipe Contents

*The pages module keeps its full structure and behavior. Only the sidebar label changes, and all existing data is cleared so that exactly one blank page remains.*

### 4.1 Sidebar label update

In `includes/sidebar.php`, change the Persian label of the module whose key is `pages` from `"صفحات اصلی سایت"` to exactly:

```
"ساخت سایت"
```

Do not change the module key, the folder path, or the icon. Only the visible string changes.

### 4.2 Page header and breadcrumb updates

Search the codebase for any occurrence of the literal string `"صفحات اصلی سایت"` and replace it with `"ساخت سایت"`. This includes, but is not limited to:

- `modules/pages/index.php` (page title)
- `modules/pages/create.php` (page title)
- `modules/pages/edit.php` (page title)
- Any breadcrumb renderer in `includes/header.php`
- Any place where the module label is looked up from the database for display

### 4.3 Database contents wipe

Run the following SQL as a migration, in this exact order:

```sql
-- Step 1: clear all blocks attached to any page
DELETE FROM page_blocks;

-- Step 2: clear all pages
DELETE FROM pages;

-- Step 3: reset auto-increment counters (optional but recommended)
ALTER TABLE pages AUTO_INCREMENT = 1;
ALTER TABLE page_blocks AUTO_INCREMENT = 1;
```

### 4.4 Insert a single blank page

After the wipe, insert exactly one empty page so that the module is not completely empty on first load. Use this SQL:

```sql
INSERT INTO pages (title, slug, status, is_system_page, is_archived, created_by)
VALUES ('بدون عنوان', 'untitled', 'draft', 0, 0, NULL);
```

Notes on the inserted row:

- `title` is set to `"بدون عنوان"` (Untitled) so it is obviously a placeholder.
- `slug` is `untitled` and must be unique.
- `status` is `draft` so it is not publicly published.
- `is_system_page` is `0` so the user can delete or rename it freely.
- No rows are inserted into `page_blocks`, so the page starts with an empty block editor canvas.
- `created_by` is `NULL` because the seed runs before any user logs in.

### 4.5 Behavior that must NOT change

The following parts of the pages module must remain exactly as they are:

- The block editor (text / image / table blocks, drag & drop, transactional save).
- The system-page deletion protection logic (`is_system_page = 1` cannot be deleted, only archived).
- The API endpoints under `api/pages/` (list, create, update, delete, archive, blocks_save).
- The permission checks for `pages.view`, `pages.edit`, `pages.delete`.
- The empty-state UI shown when no pages exist.

---

## 5. Change 3 — Rename "مقالات و وبلاگ سایت" to "صفحات ساخته شده" and Wipe Contents

*The articles module keeps its full structure and behavior. Only the sidebar label changes, and all existing data is cleared.*

### 5.1 Sidebar label update

In `includes/sidebar.php`, change the Persian label of the module whose key is `articles` from `"مقالات و وبلاگ سایت"` to exactly:

```
"صفحات ساخته شده"
```

Do not change the module key, the folder path, or the icon. Only the visible string changes.

### 5.2 Page header and breadcrumb updates

Search the codebase for any occurrence of the literal string `"مقالات و وبلاگ سایت"` and replace it with `"صفحات ساخته شده"`. This includes, but is not limited to:

- `modules/articles/index.php` (page title)
- `modules/articles/create.php` (page title)
- `modules/articles/edit.php` (page title)
- Any breadcrumb renderer in `includes/header.php`
- Any place where the module label is looked up from the database for display

### 5.3 Database contents wipe

Run the following SQL as a migration, in this exact order:

```sql
-- Step 1: clear all blocks attached to any article
DELETE FROM article_blocks;

-- Step 2: clear all articles
DELETE FROM articles;

-- Step 3: clear all article categories
DELETE FROM article_categories;

-- Step 4: reset auto-increment counters (optional but recommended)
ALTER TABLE articles AUTO_INCREMENT = 1;
ALTER TABLE article_blocks AUTO_INCREMENT = 1;
ALTER TABLE article_categories AUTO_INCREMENT = 1;
```

### 5.4 Behavior that must NOT change

The following parts of the articles module must remain exactly as they are:

- The block editor (text / image / table blocks, drag & drop, transactional save).
- The cover-image upload and category selector on the create and edit forms.
- The API endpoints under `api/articles/` (list, create, update, delete, blocks_save).
- The permission checks for `articles.view`, `articles.edit`, `articles.delete`.
- The empty-state UI shown when no articles exist.

Note: because `article_categories` is wiped in §5.3, the category selector will initially show no options. That is the intended state after the wipe.

---

## 6. What Must Not Change

*The following must remain untouched by this refactor.*

- The `dashboard`, `templates`, `sales`, `media`, `licenses`, and `settings` modules and their labels.
- All shared components: sidebar structure, top header, notification bell, dark mode toggle, block editor component.
- All security rules: PDO prepared statements, CSRF tokens, session hardening, login lockout, upload validation.
- All localization rules: Persian digits, Jalali dates, Toman formatting, RTL layout.
- The permission model itself (owner vs staff, per-module view/edit/delete).
- The following database tables and their data: `panel_users`, `permissions` (except store rows), `panel_user_permissions` (except store rows), `login_attempts`, `template_categories`, `templates`, `site_settings`, `marketplaces`, `marketplace_connections`, `media_library`, `media_usage`, `licenses`, `notifications`, `activity_logs`.

---

## 7. Files Affected — Quick Reference

*This table is a checklist the agent can tick off while applying the changes.*

| File / Path | Action |
| --- | --- |
| `modules/store/` | Delete folder and all files inside |
| `api/store/` | Delete folder and all files inside |
| `includes/sidebar.php` | Remove store item; rename pages label; rename articles label |
| `includes/header.php` | Update any breadcrumb or title strings that reference the three renamed/removed modules |
| `includes/permissions.php` | Remove hardcoded `store.*` references |
| `modules/dashboard/index.php` | Remove products stat card and any store call-to-action |
| `api/dashboard/stats.php` | Remove `products_count` from response |
| `api/dashboard/trend.php` | Remove `products` series from response |
| `modules/pages/index.php` | Update page title string to `"ساخت سایت"` |
| `modules/pages/create.php` | Update page title string to `"ساخت سایت"` |
| `modules/pages/edit.php` | Update page title string to `"ساخت سایت"` |
| `modules/articles/index.php` | Update page title string to `"صفحات ساخته شده"` |
| `modules/articles/create.php` | Update page title string to `"صفحات ساخته شده"` |
| `modules/articles/edit.php` | Update page title string to `"صفحات ساخته شده"` |
| `database/schema.sql` | Remove store tables and store permission seeds; remove `products_count` column from snapshots if applicable |
| Database itself | Run the three migrations in §3.6, §4.3, §5.3, and the seed insert in §4.4 |

---

## 8. Migration Execution Order

*Apply the migrations in this exact order to avoid foreign-key conflicts.*

1. §3.6 — Remove store permissions and drop store tables.
2. §4.3 — Wipe `page_blocks` and `pages`.
3. §4.4 — Insert the single blank page.
4. §5.3 — Wipe `article_blocks`, `articles`, and `article_categories`.
5. §3.5 — Drop the `products_count` column from `dashboard_snapshots` if the target MySQL version supports it.
6. Update `database/schema.sql` (§3.7) so a fresh install matches the new state.

---

## 9. Verification Checklist

*The agent must confirm every item below after applying the changes.*

- [ ] `modules/store/` no longer exists anywhere in the codebase.
- [ ] `api/store/` no longer exists anywhere in the codebase.
- [ ] No code anywhere references the string `"راه‌اندازی فروشگاه"`.
- [ ] No code anywhere references the permission keys `store.view`, `store.edit`, or `store.delete`.
- [ ] `permissions` table has zero rows where `module = 'store'`.
- [ ] `panel_user_permissions` table has zero rows pointing to a store permission.
- [ ] Tables `product_categories`, `products`, and `product_images` no longer exist.
- [ ] Sidebar shows the module with key `pages` under the label `"ساخت سایت"`.
- [ ] Sidebar shows the module with key `articles` under the label `"صفحات ساخته شده"`.
- [ ] Sidebar no longer shows any store entry.
- [ ] `pages` table contains exactly one row: title `"بدون عنوان"`, slug `untitled`, status `draft`, `is_system_page = 0`.
- [ ] `page_blocks` table is empty.
- [ ] `articles` table is empty.
- [ ] `article_blocks` table is empty.
- [ ] `article_categories` table is empty.
- [ ] Dashboard shows no products stat card and no store call-to-action.
- [ ] `api/dashboard/stats.php` response JSON contains no `products_count` field.
- [ ] `api/dashboard/trend.php` response JSON contains no `products` series.
- [ ] All remaining modules (dashboard, templates, sales, media, licenses, settings) still load without error.
- [ ] The block editor on the pages module still works end to end after the wipe.
- [ ] `database/schema.sql` reflects the new state and runs cleanly on a fresh MySQL 8 install.
- [ ] All Persian labels introduced or preserved by this refactor are exactly `"ساخت سایت"` and `"صفحات ساخته شده"` with no trailing spaces or alternate characters.
```

---

*2026-10-17 — Part 8 (builder editor fix pack A1–A9, `صفحات ساخته شده` pages manager, media picker) is documented in `changelogs/changelog-part-8-fixpack-pages-manager.md`. No Part 0–7 behaviour was removed; `activate.php` now also syncs `site_name` (D1).*