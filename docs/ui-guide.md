# راهنمای سیستم طراحی پنل (Part 10A)

این سند مرجع کوتاه است: قانون‌ها را نگه دار، بعد صفحه‌ها را بساز.

## لود ترتیب فایل‌ها

```
bootstrap 5.3.3 RTL (CDN با SRI موجود - دست نخورده)
bootstrap-icons 1.11.3 (CDN با SRI - تا Part 10B برچیده می‌شود)
public/assets/css/app/00-tokens.css      <- تنها منبع حقیقت: رنگ/اندازه/رادیوس/زمان
public/assets/css/app/05-fonts.css       <- Vazirmatn FD (local-first + pinned CDN)
public/assets/css/app/10-base.css
public/assets/css/app/20-shell.css       <- فقط پوسته (sidebar/topbar/content)
public/assets/css/app/30-bootstrap-bridge.css
public/assets/css/app/35-components.css  <- اجزای صفحه (auth/tiles/pills/...)
public/assets/css/app/40-motion.css      <- حرکت مشترک
public/assets/css/app/50-auth.css        <- صفحه‌های auth
assets/js/app.js   (defer)               <- PanelUI (theme, sidebar, dialog plumbing)
assets/js/admin.js (defer)               <- csrfHeaders/ajaxDelete/confirmDelete
```

## قانون‌ها

1. **توکن فقط در 00-tokens.css.** هیچ استایل دیگری مقدار رنگ/سایه/سرعت را redefine نمی‌کند.
2. **یاهو بنفش ممنوع.** `#6F42C1/#6610F2/...` مسدود است؛ برند `#1B5FC2` (حافظه tokens).
3. **ویژگی‌های منطقی (logical properties)** در همه CSS جدید: `inline`/`block`، RTL مستقل از LTR.
4. **هیچ استایل inline در PHP جدید** (بجز موقت‌های expressly-waived در جزئیات 10B).
5. **مسیر فایل‌ها مطلق** `/assets/...`.
6. **آیکون‌ها فقط Tabler (`ti ti-...`)** در پوسته/صفحه‌های نو. bootstrap-icons فقط برای کد `/* legacy */` باقی است.
7. **CSS جدید بدون کلاس‌های marca-wise مثل `stat-card`.** اجزای نو: `v-*` (v-tile, v-pill, method-chip, ...).

## تم تیره

- سرور حقیقت: `data-theme="dark|light"` on `<html>` از `panel_users.theme_preference`.
- `PanelUI.applyTheme()` فقط روی همان attribute می‌نویسد. localStorage backup (`panel-theme`)
  و `SessionStorageunsafe` ممنوع. همه رنگ‌ها از tokens با `[data-theme="dark"]`.
- Picks سمت کلاینت هنگام login: **رندر سربرگ برنده است**؛ تغییر UI در changeآنی.

## اجزای کیت

کیت: `public/ui-kit/index.html` — نمایش کامپوننت‌ها برای مرور سریع بدون PHP/DB.
اجرا: `python tests/browser/run_ui_checks.py --kit .` (67 چک).

## اسکریپت‌های ابزار

| ابزار | اجرا | خروجی انتظار |
|---|---|---|
| `python tests/ui/check_tokens.py` | کنترل توکن‌ها | 158 passed, 0 failed |
| `python tests/browser/run_ui_checks.py --kit .` | چک پوسته/رفتار روی کیت | 67 passed, 0 failed |
| `php database/tools/fetch_vendor.php` | دانلود vendor (اینترنت) | همه units ok |
| `php database/tools/check_icons.php` | آیکون‌های ti در tabler vendored | ok / SKIP بدون css |
| `php database/tools/check_removed.php` | بررسی حذف اوپک‌های لگاسی | ok |

## زبان/جهت

RTL فقط. کد/شماره/ایمیل با `.v-ltr`/`.v-code` (برکیت bidi isolate).

## کلاس‌های صفحه‌ای (Part 10B — `45-pages.css`)

- **قالب‌ها:** `.template-card` + `--active`/`__thumb`/`__body`/`__title`/`__ver`/`__actions` و `.template-active__name`.
- **رسانه:** `.media-grid`، `.media-tile` (+`__thumb`/`__name`/`__meta`)، `.media-preview__el`، و ردیف‌های آپلود `.upload-list`/`.upload-row` (+`__name`/`__pct`، `<progress>`).
- **مجوزها:** `.license-card` (+`__form`/`__fields`/`__link`/`__actions`).
- **همکاران:** `.perm-chips`، `.perm-chip` (+`__rights`/`--none`)، `.perm-table`.
- **پروفایل/تنظیمات:** `.profile-avatar`، `.avatar-lg--empty`، `.asset-preview` (+`__img`/`__img--icon`/`__cap`)، `.settings-section` (+`__title`)، `.settings-fieldset`.

بلوک «LEGACY SAFETY NET» از `30-bootstrap-bridge.css` و هشت قاعده‌ی لگاسی `35-components.css` در B8 حذف شدند؛ `upload.js` و `datatable.js` نیز پس از پاک‌شدن ارجاع‌ها حذف شدند.

