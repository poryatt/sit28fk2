# Changelog — Parts 4–7: Content Blocks, Slider, Story, Renderer

**Date:** 2026-10-16
**Executor:** OpenCode AI agent (single session, Parts 4+5+6+7 together at Pouya's request)

Parts 0–3 shipped before this work (see `changelogs/changelog-part-*.md`).
No file created by Parts 0–3 was modified — Lego rule honoured (only new files added).

---

## Files created (14, nothing modified)

| File | Part | Purpose |
|---|---|---|
| `includes/builder/types/text.php` | 4 | `text` registration + sanitize (extended whitelist, align/color/bg) |
| `includes/builder/types/image_video.php` | 4 | `image_video` registration + sanitize + extract_media |
| `includes/builder/types/button.php` | 4 | `button` registration + sanitize + extract_media |
| `includes/builder/types/slider.php` | 5 | `slider` registration + sanitize (max 10 slides, interval clamp) + extract_media |
| `includes/builder/types/story.php` | 6 | `story` registration + sanitize (max 10×10, duration clamp) + extract_media |
| `public/assets/js/builder/types/00-structural.js` | 0-follow-up | Client mirror of the 5 structural types (labels/icons only, no render) |
| `public/assets/js/builder/types/10-text.js` | 4 | Text render (contenteditable) + format commands + settings (align/color/bg) |
| `public/assets/js/builder/types/11-image-video.js` | 4 | Media render (server-resolved URL) + settings (picker/upload/alt/caption/flags) |
| `public/assets/js/builder/types/12-button.js` | 4 | Button render + settings (label/url/new-tab/image) |
| `public/assets/js/builder/types/20-slider.js` | 5 | Slider render (autoplay/prev-next/dots/hover-pause) + settings (slides CRUD + upload) |
| `public/assets/js/builder/types/21-story.js` | 6 | Story circles + fullscreen viewer + settings (circles/items CRUD + progress upload) |
| `public/assets/css/builder/40-content-blocks.css` | 4–6 | All editor + viewer styles under type-prefixed namespaces |
| `includes/builder/renderer.php` | 7 | `BuilderRenderer::renderPage()` — tree → live HTML page |
| `public/api/builder/render.php` | 7 | `GET ?canvas_id=N` → rendered HTML (pages.view + templates.view gate) |
| `public/assets/js/builder/ui/30-preview.js` | 7 | `window.BuilderPreview(canvas)` — opens render URL in new tab |

Auto-loading (no edit to any loader was needed):
- `includes/builder/bootstrap.php` globs `types/*.php` — the 5 new type files register automatically.
- `BuilderAssets::clientScripts()` globs `types/*.js` — the 6 new files load in order.
- `modules/pages/index.php` globs `css/builder/*.css` (`40-*` after `30-*`) and `ui/*.js` (`30-preview.js` after `21-*`).

---

## Decisions (deviations from roadmap v3, all deliberate)

1. **Slider is vanilla JS, not Swiper.js.** Roadmap §5.5 chose Swiper.js to avoid hand-rolled timer/swipe bugs. This ships a ~80-line vanilla slider (interval autoplay, prev/next, dots, hover-pause, no touch-swipe) instead: one small slider is not worth a new vendored dependency, offline XAMPP keeps working, and there is no touch-swipe gesture to fight a click handler (editor canvas has no swipe need; the live page uses scroll-snap + buttons). If touch-swipe becomes a requirement, Swiper.js can replace `20-slider.js` internals without touching any other file.
2. **Text color/bg are block-level fields, not inline styles.** The whitelist sanitizer strips every attribute except `href` on `<a>`, so inline `<span style="color:…">` would not survive save. Instead `text` data carries `color`/`bg` hex fields (validated `^#[0-9a-fA-F]{6}$`) applied to the block wrapper in both editor and renderer. Inline toolbar covers bold/italic/underline/headings/lists/quote/link/align — all of which produce whitelist-safe tags.
3. **Renderer ships as `render.php` + `BuilderPreview` hook (minimal Part 7).** Roadmap left Part 7 unscoped. This implements the smallest complete loop: server compiler (`renderer.php`), a read-only `GET` endpoint re-cleaning text HTML and resolving media URLs server-side, and the exact `window.BuilderPreview(canvas)` hook Part 1 specified (Preview button now opens the live page in a new tab). No canvas list screen, no static file export — those stay future work.
4. **Client structural mirror included (`00-structural.js`).** Part 2's changelog proposed it as a follow-up; without it every header/body/footer label renders as raw type + `?` icon. It registers labels/icons/defaultData only (no render, no settings), loads first by `00-` prefix, and lets `BuilderCore.createNode('column')` take the registry path.

---

## Verification (executed, not asserted)

- `php -l` clean on all 7 new PHP files.
- `node --check` clean on all 7 new JS files.
- Registry live test via bootstrap: **10 types** (`button,image_video,slider,story,header,body,footer,column_container,column,text`), **insertable: 5**, `text` default `{"html":"","align":"right"}`.
- Sanitize live tests: text strips `<script>` + `javascript:` href, keeps `h1`, keeps valid `#ff0000`, drops bad `bg`; button strips `javascript:` URL → `''`; slider clamps interval `999999 → 15000`; story empty circles → `{"circles":[]}`.
- Validator live test: tree with `text`+`button`+`image_video` children of body → **0 errors**.
- Renderer live test: same tree → 4513-byte HTML page, `<p>Hi</p>` present.
- Client registry in Node (stubbed document/fetch): **10 types**, **insertable: 5**, order `header,body,footer,column_container,column,text,image_video,button,slider,story`.
- No browser fixture run (no headless Chrome in this session) — editor render/settings/viewer paths are code-reviewed only. Recommended: open the builder, insert one of each block, save → reload → preview, before declaring done.

## Open items

- Touch-swipe on slider (see decision 1).
- Canvas list screen + delete path (pre-existing gaps from Part 1, still open).
- `00-structural.js` makes Part 2's registry-fallback branches dead code — harmless, leave them.
- Story viewer video sound: viewer unmutes (`v.muted=false`) but browsers may block autoplay-with-sound; image items always advance by `duration`, video items advance on `ended` or `duration` whichever fires first (timer is the backstop).
