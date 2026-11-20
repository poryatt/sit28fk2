/* Visual page builder — media library picker overlay (Part 8).
 * A vanilla overlay, not a Bootstrap modal: the builder scripts load before
 * Bootstrap's JS. z-index 1070 sits above the builder chrome (1040–1050) and
 * Bootstrap modals (1055), so it is never hidden. Errors inside the picker are
 * shown inline (Swal's z-index 1060 would sit under the picker).
 * Exposes exactly one global: window.BuilderMediaPicker.
 *   open({ kind, title?, onSelect })   close()
 *   getInfo(id, cb)                    upload(file, onProgress, cb)
 *   field({ kind, mediaId, onChange }) // reusable in-panel control
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "۰";
  var STR_2 = "۱";
  var STR_3 = "۲";
  var STR_4 = "۳";
  var STR_5 = "۴";
  var STR_6 = "۵";
  var STR_7 = "۶";
  var STR_8 = "۷";
  var STR_9 = "۸";
  var STR_10 = "۹";
  var STR_11 = "خطا در آپلود";
  var STR_12 = "انتخاب از کتابخانه رسانه";
  var STR_13 = "در حال بارگذاری...";
  var STR_14 = "خطا در دریافت فهرست";
  var STR_15 = "تلاش دوباره";
  var STR_16 = "فایلی یافت نشد";
  var STR_17 = "قبلی";
  var STR_18 = "صفحه ";
  var STR_19 = " از ";
  var STR_20 = "بعدی";
  var STR_21 = "بستن";
  var STR_22 = "جستجو بر اساس نام فایل...";
  var STR_23 = "همه";
  var STR_24 = "تصاویر";
  var STR_25 = "ویدیوها";
  var STR_26 = "آپلود فایل جدید";
  var STR_27 = "در حال آپلود... ";
  var STR_28 = "٪";
  var STR_29 = "انتخاب از کتابخانه";
  var STR_30 = "حذف رسانه";
  var STR_31 = "بدون رسانه";


  var API = '/api/builder/media.php';
  var UPLOAD_URL = '/api/media/upload.php';

  var FA = [STR_1, STR_2, STR_3, STR_4, STR_5, STR_6, STR_7, STR_8, STR_9, STR_10];
  function fa(v) { return String(v).replace(/[0-9]/g, function (d) { return FA[Number(d)]; }); }

  var infoCache = {};

  function getInfo(id, cb) {
    if (!id) { cb(null); return; }
    if (infoCache[id]) { cb(infoCache[id]); return; }
    fetch(API + '?id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        try {
          var b = JSON.parse(t);
          if (b && b.success && b.data) { infoCache[id] = b.data; cb(b.data); return; }
        } catch (e) { /* fall through */ }
        cb(null);
      })
      .catch(function () { cb(null); });
  }

  function csrfToken() {
    var root = document.getElementById('builder-root');
    return (root && root.dataset && root.dataset.csrfToken) || window.CSRF_TOKEN || '';
  }

  function upload(file, onProgress, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', UPLOAD_URL, true);
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
    xhr.upload.addEventListener('progress', function (ev) {
      if (ev.lengthComputable && onProgress) { onProgress(Math.round(ev.loaded / ev.total * 100)); }
    });
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) { return; }
      try {
        var b = JSON.parse(xhr.responseText);
        if (xhr.status >= 200 && xhr.status < 300 && b && b.success && b.data && b.data.id) {
          cb(null, b.data);
        } else {
          cb(new Error((b && b.message) || STR_11));
        }
      } catch (e) { cb(new Error(STR_11)); }
    };
    var fd = new FormData();
    fd.append('file', file);
    xhr.send(fd);
  }

  /* ---------- overlay ---------- */
  var overlayEl = null;
  var panelEl = null;
  var gridEl = null;
  var footerEl = null;
  var searchEl = null;
  var typeEl = null;
  var statusEl = null;
  var openerEl = null;
  var keyHandler = null;
  var overScroll = '';
  var bodyStyle = null;
  var options = { kind: 'any', title: STR_12, onSelect: null };
  var view = { page: 1, pages: 1, type: 'all', q: '', loading: false };
  var debounceTimer = null;

  function close() {
    if (!overlayEl) { return; }
    if (keyHandler) { document.removeEventListener('keydown', keyHandler, true); keyHandler = null; }
    if (bodyStyle) { bodyStyle.overflow = overScroll; }
    if (overlayEl.parentNode) { overlayEl.parentNode.removeChild(overlayEl); }
    overlayEl = null; panelEl = null; gridEl = null; footerEl = null;
    searchEl = null; typeEl = null; statusEl = null;
    if (openerEl && document.contains(openerEl) && typeof openerEl.focus === 'function') {
      try { openerEl.focus(); } catch (e) { /* ignore */ }
    }
    openerEl = null;
  }

  function focusables() {
    if (!panelEl) { return []; }
    var sel = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var list = panelEl.querySelectorAll(sel);
    var out = [];
    for (var i = 0; i < list.length; i++) {
      if (list[i].offsetParent !== null || list[i] === document.activeElement) { out.push(list[i]); }
    }
    return out;
  }

  function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key !== 'Tab') { return; }
    var f = focusables();
    if (!f.length) { return; }
    var first = f[0];
    var last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function setStatus(html) {
    if (!statusEl) { return; }
    statusEl.innerHTML = '';
    if (html === null) { statusEl.hidden = true; return; }
    statusEl.hidden = false;
    if (typeof html === 'string') { statusEl.textContent = html; }
    else { statusEl.appendChild(html); }
  }

  function load() {
    if (!gridEl || view.loading) { return; }
    view.loading = true;
    gridEl.innerHTML = '';
    setStatus(STR_13);
    var url = API + '?type=' + encodeURIComponent(view.type) + '&q=' + encodeURIComponent(view.q) + '&page=' + view.page;
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        var b = null;
        try { b = JSON.parse(t); } catch (e) { b = null; }
        view.loading = false;
        if (!b || b.success !== true || !b.data) { showError(); return; }
        view.page = b.data.page || 1;
        view.pages = b.data.pages || 1;
        renderGrid(b.data.items || []);
      })
      .catch(function () { view.loading = false; showError(); });
  }

  function showError() {
    gridEl.innerHTML = '';
    var box = document.createElement('div');
    box.className = 'builder-media-status';
    box.textContent = STR_14;
    var retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'btn btn-outline-secondary btn-sm';
    retry.textContent = STR_15;
    retry.addEventListener('click', load);
    box.appendChild(document.createElement('br'));
    box.appendChild(retry);
    setStatus(box);
  }

  function renderGrid(items) {
    gridEl.innerHTML = '';
    if (!items.length) {
      setStatus(STR_16);
      renderFooter();
      return;
    }
    setStatus(null);
    for (var i = 0; i < items.length; i++) {
      gridEl.appendChild(makeTile(items[i]));
    }
    renderFooter();
  }

  function makeTile(media) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'builder-media-tile';
    var box = document.createElement('span');
    box.className = 'builder-media-tile-box';
    if (media.file_type === 'image') {
      var img = document.createElement('img');
      img.setAttribute('loading', 'lazy');
      img.setAttribute('alt', '');
      img.src = media.file_path;
      box.appendChild(img);
    } else {
      var ic = document.createElement('i');
      ic.className = 'ti ti-player-play';
      box.appendChild(ic);
    }
    btn.appendChild(box);
    var name = document.createElement('span');
    name.className = 'builder-media-tile-name';
    name.textContent = media.file_name || '';
    btn.appendChild(name);
    btn.addEventListener('click', function () {
      if (typeof options.onSelect === 'function') { options.onSelect(media); }
      close();
    });
    return btn;
  }

  function renderFooter() {
    footerEl.innerHTML = '';
    if (view.pages <= 1) { return; }
    var prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'btn btn-outline-secondary btn-sm';
    prev.textContent = STR_17;
    prev.disabled = view.page <= 1;
    prev.addEventListener('click', function () { if (view.page > 1) { view.page--; load(); } });
    var label = document.createElement('span');
    label.className = 'builder-media-page';
    label.textContent = STR_18 + fa(view.page) + STR_19 + fa(view.pages);
    var next = document.createElement('button');
    next.type = 'button';
    next.className = 'btn btn-outline-secondary btn-sm';
    next.textContent = STR_20;
    next.disabled = view.page >= view.pages;
    next.addEventListener('click', function () { if (view.page < view.pages) { view.page++; load(); } });
    footerEl.appendChild(prev);
    footerEl.appendChild(label);
    footerEl.appendChild(next);
  }

  function canUpload() {
    var root = document.getElementById('builder-root');
    return !!root && root.dataset && root.dataset.canUploadMedia === '1';
  }

  function build() {
    overlayEl = document.createElement('div');
    overlayEl.className = 'builder-media-picker';

    panelEl = document.createElement('div');
    panelEl.className = 'builder-media-picker-panel';
    panelEl.setAttribute('role', 'dialog');
    panelEl.setAttribute('aria-modal', 'true');
    panelEl.setAttribute('dir', 'rtl');

    var header = document.createElement('div');
    header.className = 'builder-media-picker-header';
    var title = document.createElement('div');
    title.className = 'builder-media-picker-title';
    title.textContent = options.title;
    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'builder-media-picker-close';
    closeBtn.setAttribute('aria-label', STR_21);
    closeBtn.innerHTML = '<i class="ti ti-x"></i>';
    closeBtn.addEventListener('click', close);
    header.appendChild(title);
    header.appendChild(closeBtn);

    var tools = document.createElement('div');
    tools.className = 'builder-media-picker-tools';
    searchEl = document.createElement('input');
    searchEl.type = 'search';
    searchEl.className = 'form-control';
    searchEl.placeholder = STR_22;
    searchEl.addEventListener('input', function () {
      if (debounceTimer) { window.clearTimeout(debounceTimer); }
      debounceTimer = window.setTimeout(function () {
        view.q = searchEl.value;
        view.page = 1;
        load();
      }, 300);
    });
    tools.appendChild(searchEl);

    typeEl = document.createElement('select');
    typeEl.className = 'form-select';
    var typeOptions = [['all', STR_23], ['image', STR_24], ['video', STR_25]];
    for (var i = 0; i < typeOptions.length; i++) {
      var o = document.createElement('option');
      o.value = typeOptions[i][0];
      o.textContent = typeOptions[i][1];
      typeEl.appendChild(o);
    }
    typeEl.value = view.type;
    typeEl.addEventListener('change', function () { view.type = typeEl.value; view.page = 1; load(); });
    tools.appendChild(typeEl);

    if (canUpload()) {
      var upBtn = document.createElement('button');
      upBtn.type = 'button';
      upBtn.className = 'btn btn-outline-primary';
      upBtn.textContent = STR_26;
      var upInput = document.createElement('input');
      upInput.type = 'file';
      upInput.classList.add('builder-is-hidden');
      upInput.accept = options.kind === 'image' ? 'image/*' : (options.kind === 'video' ? 'video/*' : 'image/*,video/*');
      upBtn.addEventListener('click', function () { upInput.click(); });
      var upProg = document.createElement('span');
      upProg.className = 'builder-media-upload-progress';
      upInput.addEventListener('change', function () {
        if (!upInput.files || !upInput.files[0]) { return; }
        var file = upInput.files[0];
        upProg.textContent = STR_27 + fa(0) + STR_28;
        upload(file, function (p) { upProg.textContent = STR_27 + fa(p) + STR_28; }, function (err, res) {
          if (err || !res) { upProg.textContent = (err && err.message) || STR_11; return; }
          upProg.textContent = '';
          getInfo(res.id, function (info) {
            if (typeof options.onSelect === 'function') { options.onSelect(info || res); }
            close();
          });
        });
      });
      tools.appendChild(upBtn);
      tools.appendChild(upInput);
      tools.appendChild(upProg);
    }

    statusEl = document.createElement('div');
    statusEl.className = 'builder-media-picker-status';

    gridEl = document.createElement('div');
    gridEl.className = 'builder-media-grid';

    footerEl = document.createElement('div');
    footerEl.className = 'builder-media-picker-footer';

    var body = document.createElement('div');
    body.className = 'builder-media-picker-body';
    body.appendChild(statusEl);
    body.appendChild(gridEl);

    panelEl.appendChild(header);
    panelEl.appendChild(tools);
    panelEl.appendChild(body);
    panelEl.appendChild(footerEl);
    overlayEl.appendChild(panelEl);

    overlayEl.addEventListener('mousedown', function (e) {
      if (e.target === overlayEl) { close(); }
    });
  }

  function open(opts) {
    opts = opts || {};
    if (overlayEl) { close(); }
    options = {
      kind: (opts.kind === 'image' || opts.kind === 'video') ? opts.kind : 'any',
      title: opts.title || STR_12,
      onSelect: typeof opts.onSelect === 'function' ? opts.onSelect : null
    };
    if (options.kind === 'image' || options.kind === 'video') {
      view.type = options.kind;
    } else {
      view.type = 'all';
    }
    view.page = 1;
    view.q = '';
    openerEl = document.activeElement;
    bodyStyle = bodyStyle || document.body.style; /* dynamic geometry: scroll lock */
    overScroll = bodyStyle.overflow;
    build();
    document.body.appendChild(overlayEl);
    bodyStyle.overflow = 'hidden';
    if (options.kind === 'image' || options.kind === 'video') {
      typeEl.hidden = true;
    }
    keyHandler = onKey;
    document.addEventListener('keydown', keyHandler, true);
    load();
    if (searchEl) { searchEl.focus(); }
  }

  /* ---------- reusable in-panel media control ---------- */
  function field(opts) {
    opts = opts || {};
    var kind = (opts.kind === 'image' || opts.kind === 'video') ? opts.kind : 'any';
    var onChange = typeof opts.onChange === 'function' ? opts.onChange : function () {};
    var wrap = document.createElement('div');
    wrap.className = 'builder-media-field';

    var thumb = document.createElement('div');
    thumb.className = 'builder-media-field-thumb';
    var actions = document.createElement('div');
    actions.className = 'builder-media-field-actions';
    var pickBtn = document.createElement('button');
    pickBtn.type = 'button';
    pickBtn.className = 'btn btn-outline-primary btn-sm';
    pickBtn.textContent = STR_29;
    var clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'btn btn-outline-danger btn-sm';
    clearBtn.textContent = STR_30;
    actions.appendChild(pickBtn);
    actions.appendChild(clearBtn);
    wrap.appendChild(thumb);
    wrap.appendChild(actions);

    function renderThumb(info) {
      thumb.innerHTML = '';
      if (!info) {
        thumb.classList.add('builder-media-field-empty');
        thumb.textContent = STR_31;
        clearBtn.hidden = true;
        return;
      }
      thumb.classList.remove('builder-media-field-empty');
      if (info.file_type === 'video') {
        var ic = document.createElement('i');
        ic.className = 'ti ti-player-play';
        thumb.appendChild(ic);
      } else {
        var img = document.createElement('img');
        img.setAttribute('alt', '');
        img.src = info.file_path;
        thumb.appendChild(img);
      }
      clearBtn.hidden = false;
    }

    if (opts.mediaId) { getInfo(opts.mediaId, renderThumb); } else { renderThumb(null); }

    pickBtn.addEventListener('click', function () {
      open({ kind: kind, onSelect: function (media) {
        renderThumb(media);
        onChange(media ? media.id : null, media);
      } });
    });
    clearBtn.addEventListener('click', function () {
      renderThumb(null);
      onChange(null, null);
    });
    return wrap;
  }

  window.BuilderMediaPicker = {
    open: open,
    close: close,
    getInfo: getInfo,
    upload: upload,
    field: field
  };
})();