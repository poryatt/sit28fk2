#!/usr/bin/env python3
"""Browser checks (v3) for the visual page builder (real Chromium, real mouse, real project JS).

Usage:  python tests/browser/run_builder_checks.py [--public PATH_TO_public_DIR]
Needs:  pip install playwright && python -m playwright install chromium
It serves a stub page (no PHP, no MySQL) that loads the project's builder scripts in the SAME order as
modules/pages/index.php, stubs only fetch() and Swal, and drives the editor with real mouse/keyboard input.
Exit code 0 = every check passed, 1 = at least one failed, 2 = Playwright is not available.
"""
import argparse, functools, glob, http.server, json, os, shutil, socketserver, sys, tempfile, threading

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('Playwright is not installed: browser checks NOT RUN (not verified).')
    sys.exit(2)

ap = argparse.ArgumentParser()
ap.add_argument('--public', default=os.path.join(os.path.dirname(__file__), '..', '..', 'public'))
args = ap.parse_args()
PUBLIC = os.path.abspath(args.public)
JS = os.path.join(PUBLIC, 'assets', 'js', 'builder')
CSS = sorted(glob.glob(os.path.join(PUBLIC, 'assets', 'css', 'builder', '*.css')))

def scripts():
    out = ['block-registry.js', 'builder-core.js']
    if os.path.exists(os.path.join(JS, 'builder-tree.js')):
        out.append('builder-tree.js')
    out += ['types/' + os.path.basename(f) for f in sorted(glob.glob(os.path.join(JS, 'types', '*.js')))]
    out += ['builder-shell.js']
    out += ['ui/' + os.path.basename(f) for f in sorted(glob.glob(os.path.join(JS, 'ui', '*.js')))]
    return out

U = lambda n: n * 32  # 32-char uid helper: U('a')
HTML = """<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">
%(css)s
</head><body>
<script>
window.__swal = [];
window.__swalAnswer = 'confirm';
window.Swal = { fire: function (o) { window.__swal.push(o && o.title ? o.title : String(o)); return Promise.resolve(window.__swalAnswer === 'confirm' ? { isConfirmed: true, value: 'x' } : { isConfirmed: false, isDenied: false, isDismissed: true }); } };
window.__posts = [];
var TREE = %(tree)s;
var MEDIA = %(media)s;
function J(o, s) { return new Response(JSON.stringify(o), { status: s || 200, headers: { 'Content-Type': 'application/json' } }); }
window.fetch = function (u, o) {
  if (o && o.method === 'POST') { window.__posts.push({ url: String(u), body: o.body }); }
  if (String(u).indexOf('/save') === 0) {
    var b = JSON.parse(o.body);
    return Promise.resolve(J({ success: true, message: 'ok', data: { canvas: { id: 7, buildable_type: 'page', template_id: null, title: b.title, slug: 't', status: b.status, version: 2, updated_at_jalali: '-' }, block_count: 5 } }));
  }
  if (String(u).indexOf('/load') === 0) {
    return Promise.resolve(J({ success: true, data: { canvas: { id: 7, buildable_type: 'page', template_id: null, title: 't', slug: 't', status: 'draft', version: 1, updated_at_jalali: '-' }, tree: TREE } }));
  }
  if (String(u).indexOf('/api/builder/media.php') === 0) {
    var mm = String(u).match(/[?&]id=(\\d+)/);
    if (mm) { var it = MEDIA.filter(function (x) { return x.id == mm[1]; })[0]; return Promise.resolve(it ? J({ success: true, data: it }) : J({ success: false, message: 'x' }, 404)); }
    return Promise.resolve(J({ success: true, data: { items: MEDIA, page: 1, pages: 1, total: MEDIA.length } }));
  }
  return Promise.resolve(J({ success: false, message: 'stub: ' + u }, 404));
};
</script>
<div id="builder-root" data-load-url="/load" data-save-url="/save" data-csrf-token="x" data-can-edit="1"
     data-can-edit-templates="1" data-entry-type="page" data-can-upload-media="1" data-can-create-page="1" data-can-view-list="1">
  <div class="card builder-title-card"><input id="builder-title" class="form-control"></div>
  <div class="card builder-canvas-card p-3" style="position:relative"><div id="builder-canvas" class="builder-canvas"></div><div id="builder-canvas-state"></div></div>
  <div id="builder-actions"><button id="builder-btn-save">s</button><button id="builder-btn-preview">p</button><button id="builder-btn-publish">pub</button>
    <a id="builder-btn-new-page" href="#new-page">new</a><a id="builder-btn-back-list" href="#list">list</a></div>
  <span id="builder-status-badge"></span><span id="builder-dirty-badge" hidden></span>
</div>
%(js)s
</body></html>"""
TREE = [
  {'uid': U('a'), 'type': 'header', 'data': {}, 'children': []},
  {'uid': U('b'), 'type': 'body', 'data': {}, 'children': [
      {'uid': U('c'), 'type': 'text', 'data': {'html': '<p>سلام</p>', 'align': 'right'}, 'children': []},
      {'uid': U('d'), 'type': 'button', 'data': {'label': 'دکمه', 'url': '#', 'open_new_tab': False, 'media_id': None}, 'children': []}]},
  {'uid': U('e'), 'type': 'footer', 'data': {}, 'children': []}]

MEDIA = [{'id': i, 'file_name': 'img%d.jpg' % i, 'file_path': '/uploads/media/%032d.jpg' % i, 'file_type': 'image',
          'mime_type': 'image/jpeg', 'width': 10, 'height': 10} for i in range(1, 6)]
tmp = tempfile.mkdtemp(prefix='builder_harness_')
shutil.copytree(os.path.join(PUBLIC, 'assets'), os.path.join(tmp, 'assets'))
page = HTML % {
    'css': '\n'.join('<link rel="stylesheet" href="assets/css/builder/%s">' % os.path.basename(c) for c in CSS),
    'js': '\n'.join('<script src="assets/js/builder/%s"></script>' % s for s in scripts()),
    'tree': json.dumps(TREE),
    'media': json.dumps(MEDIA)}
open(os.path.join(tmp, 'index.html'), 'w', encoding='utf-8').write(page)

class Quiet(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *a): pass
socketserver.TCPServer.allow_reuse_address = True
httpd = socketserver.TCPServer(('127.0.0.1', 0), functools.partial(Quiet, directory=tmp))
URL = 'http://127.0.0.1:%d/index.html' % httpd.server_address[1]
threading.Thread(target=httpd.serve_forever, daemon=True).start()

results = []
def check(name, ok, detail=''):
    results.append((name, bool(ok)))
    print(('PASS ' if ok else 'FAIL ') + name + ((' -- ' + str(detail)) if detail and not ok else ''))

def fresh(browser):
    pg = browser.new_page(viewport={'width': 1300, 'height': 900})
    errs = []
    pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.on('console', lambda m: errs.append(m.text) if m.type == 'error' and 'ERR_NAME_NOT_RESOLVED' not in m.text and 'Failed to load resource' not in m.text else None)
    pg.goto(URL); pg.wait_for_function("window.BuilderCanvas && document.querySelectorAll('.builder-block').length > 0")
    return pg, errs

def center(pg, sel):
    b = pg.query_selector(sel).bounding_box()
    return b['x'] + b['width'] / 2, b['y'] + b['height'] / 2

def real_click(pg, sel):
    x, y = center(pg, sel); pg.mouse.move(x, y); pg.mouse.down(); pg.mouse.up()

def body_types(pg):
    return pg.evaluate("window.BuilderCanvas.getTree()[1].children.map(function(c){return c.type})")

with sync_playwright() as p:
    browser = p.chromium.launch()

    # A2 + A1: clicking INSIDE the text selects it; the real-mouse trash click then deletes it.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.wait_for_timeout(250)
    sel = pg.evaluate("(window.BuilderCanvas.getSelected()||{node:{}}).node.type")
    check('A2 click inside text block selects the block', sel == 'text', sel)
    if sel == 'text':
        real_click(pg, '.builder-toolbar [data-act="delete"]'); pg.wait_for_timeout(400)
        check('A1 real-mouse click on trash deletes the TEXT block', body_types(pg) == ['button'], body_types(pg))
    else:
        check('A1 real-mouse click on trash deletes the TEXT block', False, 'block was never selected')
    pg.close()

    pg, errs = fresh(browser)
    bx, by = center(pg, '.builder-block[data-type="button"]'); pg.mouse.click(bx, by); pg.wait_for_timeout(250)
    real_click(pg, '.builder-toolbar [data-act="delete"]'); pg.wait_for_timeout(400)
    check('A1 real-mouse click on trash deletes the BUTTON block', body_types(pg) == ['text'], body_types(pg))
    pg.close()

    # A1: split buttons (1-4) are alive with a real click too.
    pg, errs = fresh(browser)
    bx, by = center(pg, '.builder-block[data-type="button"]'); pg.mouse.click(bx, by); pg.wait_for_timeout(250)
    real_click(pg, '.builder-toolbar [data-act="split"][data-n="2"]'); pg.wait_for_timeout(400)
    check('A1 real-mouse click on split(2) wraps the block in a 2-column container',
          'column_container' in body_types(pg), body_types(pg))
    pg.close()

    # A3: typing keeps focus across the 500 ms debounced commit.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('End'); pg.keyboard.type('abc'); pg.wait_for_timeout(900)
    pg.keyboard.type('XYZ'); pg.wait_for_timeout(900)
    html = pg.evaluate("window.BuilderCanvas.getNode('%s').data.html" % U('c'))
    check('A3 typing before AND after the debounce is kept (focus not lost)', 'abcXYZ' in html, html)
    pg.close()

    # A4: Ctrl+S right after typing must POST the new text.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('End'); pg.keyboard.type('LASTWORDS'); pg.keyboard.press('Control+s'); pg.wait_for_timeout(900)
    posted = pg.evaluate("window.__posts.length ? window.__posts[0].body : ''")
    check('A4 save right after typing includes the last keystrokes', 'LASTWORDS' in posted)
    pg.close()

    # A5: settings can be reopened from the toolbar gear after being dismissed.
    pg, errs = fresh(browser)
    uid = pg.evaluate("(function(){var n=window.BuilderCore.createNode('image_video'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % U('b'))
    pg.evaluate("window.BuilderCanvas.select('%s')" % uid); pg.wait_for_timeout(300)
    check('A5 image block opens its settings panel when selected', pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    pg.keyboard.press('Escape'); pg.wait_for_timeout(200)
    closed = not pg.evaluate("window.BuilderSettingsPanel.isOpen()")
    gear = pg.query_selector('.builder-toolbar [data-act="settings"]')
    if gear:
        real_click(pg, '.builder-toolbar [data-act="settings"]'); pg.wait_for_timeout(300)
    check('A5 toolbar gear reopens the settings panel after it was dismissed', closed and gear is not None and pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    pg.close()

    # A5: a text block does not pop the drawer over the text while you type; the gear opens it on demand.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.wait_for_timeout(300)
    check('A5 selecting a text block does NOT auto-open the drawer', not pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    gear = pg.query_selector('.builder-toolbar [data-act="settings"]')
    if gear:
        real_click(pg, '.builder-toolbar [data-act="settings"]'); pg.wait_for_timeout(300)
    check('A5 gear opens text settings on demand', gear is not None and pg.evaluate("window.BuilderSettingsPanel.isOpen()"))
    pg.close()

    # A1: a format button works with a real click while text is selected.
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('Control+a'); pg.wait_for_timeout(100)
    bold = pg.query_selector('.builder-toolbar [data-act="format"][data-id="bold"]')
    if bold:
        real_click(pg, '.builder-toolbar [data-act="format"][data-id="bold"]'); pg.wait_for_timeout(900)
    html = pg.evaluate("window.BuilderCanvas.getNode('%s').data.html" % U('c'))
    check('A1 real-mouse click on Bold formats the selected text', bold is not None and ('<b>' in html or '<strong>' in html), html)
    pg.close()

    # P8: publishing a page shows the "new page" dialog (not the generic toast); a second save does not.
    pg, errs = fresh(browser)
    pg.evaluate("window.__swalAnswer = 'dismiss'")
    real_click(pg, '#builder-btn-publish'); pg.wait_for_timeout(700)
    swal = pg.evaluate("window.__swal")
    check('P8 publishing a page shows the publish dialog', 'صفحه منتشر شد' in swal, swal)
    check('P8 the generic success toast is replaced by the dialog on publish', 'موفق' not in swal, swal)
    pg.evaluate("window.__swal.length = 0")
    real_click(pg, '#builder-btn-save'); pg.wait_for_timeout(700)
    swal = pg.evaluate("window.__swal")
    check('P8 a later save of an already published page shows only the normal toast', 'صفحه منتشر شد' not in swal and 'موفق' in swal, swal)
    pg.close()

    # P8: the permanent "new page" button asks before throwing unsaved work away; clean state navigates directly.
    pg, errs = fresh(browser)
    real_click(pg, '#builder-btn-new-page'); pg.wait_for_timeout(300)
    check('P8 new-page button navigates directly when nothing is unsaved',
          pg.evaluate("location.hash") == '#new-page' and 'تغییرات ذخیره‌نشده' not in pg.evaluate("window.__swal"))
    pg.close()
    pg, errs = fresh(browser)
    pg.click('.builder-text-editable'); pg.keyboard.press('End'); pg.keyboard.type('x'); pg.wait_for_timeout(700)
    real_click(pg, '#builder-btn-new-page'); pg.wait_for_timeout(400)
    check('P8 new-page button asks for confirmation when there are unsaved changes',
          'تغییرات ذخیره‌نشده' in pg.evaluate("window.__swal"), pg.evaluate("window.__swal"))
    pg.close()

    # P8: media picker, and every media block panel uses it instead of a typed id.
    pg, errs = fresh(browser)
    for t in ['image_video', 'button']:
        u = pg.evaluate("(function(){var n=window.BuilderCore.createNode('%s'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % (t, U('b')))
        pg.evaluate("window.BuilderCanvas.select('%s')" % u); pg.wait_for_timeout(250)
        btn = pg.query_selector('.builder-settings-panel-body button:text-is("انتخاب از کتابخانه")')
        check('P8 %s panel has the library picker button' % t, btn is not None)
        if t == 'image_video' and btn:
            btn.click(); pg.wait_for_selector('.builder-media-picker', timeout=3000); pg.wait_for_timeout(300)
            tiles = pg.query_selector_all('.builder-media-picker .builder-media-tile, .builder-media-picker [data-media-id]')
            check('P8 picker lists the library files', len(tiles) == 5, len(tiles))
            if tiles:
                tiles[1].click(); pg.wait_for_timeout(400)
                mid = pg.evaluate("window.BuilderCanvas.getNode('%s').data.media_id" % u)
                check('P8 choosing a tile stores its id and closes the picker', mid == 2 and pg.query_selector('.builder-media-picker') is None, mid)
            btn = pg.query_selector('.builder-settings-panel-body button:text-is("انتخاب از کتابخانه")')
            if btn:
                btn.click(); pg.wait_for_selector('.builder-media-picker'); pg.keyboard.press('Escape'); pg.wait_for_timeout(300)
                check('P8 Esc closes the picker', pg.query_selector('.builder-media-picker') is None)
    for t, add in [('slider', 'افزودن اسلاید'), ('story', 'افزودن حلقه')]:
        u = pg.evaluate("(function(){var n=window.BuilderCore.createNode('%s'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % (t, U('b')))
        pg.evaluate("window.BuilderCanvas.select('%s')" % u); pg.wait_for_timeout(250)
        a = pg.query_selector('.builder-settings-panel-body button:text-is("%s")' % add)
        if a: a.click(); pg.wait_for_timeout(300)
        check('P8 %s panel offers the library picker per item' % t,
              pg.query_selector('.builder-settings-panel-body button:text-is("انتخاب از کتابخانه")') is not None)
    pg.close()

    # Part 9: the engine contract. Every documented Builder* global exists with its documented members and no
    # undocumented Builder* global appeared. The manifest lives next to this script (engine_contract.json).
    manifest_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'engine_contract.json')
    if os.path.exists(manifest_path):
        manifest = json.load(open(manifest_path, encoding='utf-8'))['globals']
        pg, errs = fresh(browser)
        actual = pg.evaluate("(function(){var o={};Object.keys(window).filter(function(k){return /^Builder[A-Z]/.test(k)}).forEach(function(k){var v=window[k];o[k]=(v&&(typeof v==='object'||typeof v==='function'))?Object.keys(v).sort():[]});return o})()")
        missing = {g: [k for k in keys if k not in actual.get(g, [])] for g, keys in manifest.items() if g not in actual or any(k not in actual[g] for k in keys)}
        extra = [g for g in actual if g not in manifest]
        check('CONTRACT every documented Builder* global exists with all its documented members', not missing, missing)
        check('CONTRACT no undocumented Builder* global was added', not extra, extra)
        pg.close()
    else:
        check('CONTRACT engine_contract.json exists next to this script', False, manifest_path)

    # Smoke: no uncaught errors during all of the above.
    pg, errs = fresh(browser)
    for t in ['image_video', 'slider', 'story', 'button']:
        u = pg.evaluate("(function(){var n=window.BuilderCore.createNode('%s'); return window.BuilderCanvas.insertBlock(n,'%s');})()" % (t, U('b')))
        pg.evaluate("window.BuilderCanvas.select('%s')" % u); pg.wait_for_timeout(150)
    check('SMOKE inserting + selecting every content type raises no JS error', not errs, errs[:3])
    pg.close()
    browser.close()

httpd.shutdown(); shutil.rmtree(tmp, ignore_errors=True)
failed = [n for n, ok in results if not ok]
print('\n%d passed, %d failed' % (len(results) - len(failed), len(failed)))
sys.exit(1 if failed else 0)