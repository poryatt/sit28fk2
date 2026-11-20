/* Visual page builder — image/video block (Part 4).
 * Media is referenced by media_id; the file URL always comes from the server
 * through window.BuilderMediaPicker (GET /api/builder/media.php?id=, gated by
 * pages.view). Upload reuses the panel's media plumbing via the picker too.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "رسانه یافت نشد.";
  var STR_2 = "تصویر یا ویدیو انتخاب نشده است — از تنظیمات بلاک انتخاب کنید.";
  var STR_3 = "در حال بارگذاری…";
  var STR_4 = "تصویر";
  var STR_5 = "ویدیو";
  var STR_6 = "نوع رسانه";
  var STR_7 = "رسانه";
  var STR_8 = "در حال آپلود… ۰٪";
  var STR_9 = "در حال آپلود… ";
  var STR_10 = "٪";
  var STR_11 = "خطا";
  var STR_12 = "آپلود شد.";
  var STR_13 = "آپلود فایل جدید";
  var STR_14 = "متن جایگزین (alt)";
  var STR_15 = "زیرنویس";
  var STR_16 = "پخش خودکار (ویدیو)";
  var STR_17 = "تکرار (ویدیو)";
  var STR_18 = "بی‌صدا (ویدیو)";
  var STR_19 = "تصویر / ویدیو";


  // Media lookups go through the picker (gated by pages.view), never the
  // media-edit endpoint, so staff without media.edit can still see images.
  function fetchInfo(mediaId, cb) {
    if (!mediaId) { cb(null); return; }
    if (window.BuilderMediaPicker && typeof window.BuilderMediaPicker.getInfo === 'function') {
      window.BuilderMediaPicker.getInfo(mediaId, cb);
    } else {
      cb(null);
    }
  }

  function uploadFile(file, onProgress, cb) {
    if (window.BuilderMediaPicker && typeof window.BuilderMediaPicker.upload === 'function') {
      window.BuilderMediaPicker.upload(file, onProgress, cb);
    } else {
      cb(new Error(STR_1));
    }
  }

  function mediaField(opts) {
    if (window.BuilderMediaPicker && typeof window.BuilderMediaPicker.field === 'function') {
      return window.BuilderMediaPicker.field(opts);
    }
    var d = document.createElement('div');
    d.className = 'builder-media-field';
    d.textContent = STR_1;
    return d;
  }

  function currentData(uid) {
    var n = window.BuilderCanvas ? window.BuilderCanvas.getNode(uid) : null;
    return n ? (n.data || {}) : {};
  }

  function persist(uid, patch) {
    var base = currentData(uid);
    var next = {};
    for (var k in base) { if (Object.prototype.hasOwnProperty.call(base, k)) { next[k] = base[k]; } }
    for (var p in patch) { if (Object.prototype.hasOwnProperty.call(patch, p)) { next[p] = patch[p]; } }
    try { window.BuilderCanvas.updateBlockData(uid, next); } catch (e) {}
  }

  function render(node, el) {
    var data = node.data || {};
    var wrap = document.createElement('div');
    wrap.className = 'builder-image-wrap';
    if (!data.media_id) {
      var empty = document.createElement('div');
      empty.className = 'builder-image-empty';
      empty.textContent = STR_2;
      wrap.appendChild(empty);
      el.appendChild(wrap);
      return;
    }
    var loading = document.createElement('div');
    loading.className = 'builder-image-empty';
    loading.textContent = STR_3;
    wrap.appendChild(loading);
    el.appendChild(wrap);
    fetchInfo(data.media_id, function (info) {
      loading.parentNode.removeChild(loading);
      if (!info) {
        var err = document.createElement('div');
        err.className = 'builder-image-empty';
        err.textContent = STR_1;
        wrap.appendChild(err);
        return;
      }
      var isVideo = data.kind === 'video' || info.file_type === 'video' || /\.mp4($|\?)/i.test(info.file_path || '');
      if (isVideo) {
        var v = document.createElement('video');
        v.className = 'builder-image-media';
        v.src = info.file_path;
        v.controls = true;
        if (data.autoplay) { v.autoplay = true; }
        if (data.loop) { v.loop = true; }
        if (data.muted !== false) { v.muted = true; }
        v.setAttribute('playsinline', '');
        wrap.appendChild(v);
      } else {
        var img = document.createElement('img');
        img.className = 'builder-image-media';
        img.src = info.file_path;
        img.alt = data.alt || '';
        wrap.appendChild(img);
      }
      if (data.caption) {
        var cap = document.createElement('div');
        cap.className = 'builder-image-caption';
        cap.textContent = data.caption;
        wrap.appendChild(cap);
      }
    });
  }

  function labeled(labelText, inputEl) {
    var lab = document.createElement('label');
    lab.className = 'builder-image-field';
    var sp = document.createElement('span');
    sp.textContent = labelText;
    lab.appendChild(sp);
    lab.appendChild(inputEl);
    return lab;
  }

  function checkRow(labelText, checked, onChange) {
    var lab = document.createElement('label');
    lab.className = 'builder-image-check';
    var cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.checked = !!checked;
    cb.addEventListener('change', function () { onChange(cb.checked); });
    lab.appendChild(cb);
    var sp = document.createElement('span');
    sp.textContent = labelText;
    lab.appendChild(sp);
    return lab;
  }

  function renderSettingsPanel(node, panelEl) {
    var data = node.data || {};
    var uid = node.uid;

    var kindSel = document.createElement('select');
    kindSel.className = 'form-control';
    var kinds = [['image', STR_4], ['video', STR_5]];
    for (var i = 0; i < kinds.length; i++) {
      var o = document.createElement('option');
      o.value = kinds[i][0]; o.textContent = kinds[i][1];
      if ((data.kind || 'image') === kinds[i][0]) { o.selected = true; }
      kindSel.appendChild(o);
    }
    kindSel.addEventListener('change', function () { persist(uid, { kind: kindSel.value }); });
    panelEl.appendChild(labeled(STR_6, kindSel));

    var idRow = document.createElement('div');
    idRow.className = 'builder-image-field';
    var idLab = document.createElement('span');
    idLab.textContent = STR_7;
    idRow.appendChild(idLab);
    idRow.appendChild(mediaField({
      kind: 'any',
      mediaId: data.media_id,
      onChange: function (id, media) {
        var patch = { media_id: id };
        if (media && media.file_type) { patch.kind = media.file_type; }
        persist(uid, patch);
        try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {}
      }
    }));
    panelEl.appendChild(idRow);

    var fileIn = document.createElement('input');
    fileIn.type = 'file';
    fileIn.accept = 'image/jpeg,image/png,image/webp,video/mp4,video/webm';
    fileIn.className = 'form-control';
    var prog = document.createElement('div');
    prog.className = 'builder-image-progress';
    prog.classList.add('builder-is-hidden');
    fileIn.addEventListener('change', function () {
      if (!fileIn.files || !fileIn.files[0]) { return; }
      prog.classList.remove('builder-is-hidden');
      prog.textContent = STR_8;
      uploadFile(fileIn.files[0], function (p) { prog.textContent = STR_9 + p + STR_10; }, function (err, res) {
        if (err) {
          prog.textContent = err.message;
          if (typeof Swal !== 'undefined') { Swal.fire({ title: STR_11, text: err.message, icon: 'error' }); }
          return;
        }
        prog.textContent = STR_12;
        var isVid = /\.(mp4|webm)$/i.test(res.file_path || '');
        persist(uid, { media_id: res.id, kind: isVid ? 'video' : 'image' });
        kindSel.value = isVid ? 'video' : 'image';
        try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {}
      });
    });
    panelEl.appendChild(labeled(STR_13, fileIn));
    panelEl.appendChild(prog);

    var altIn = document.createElement('input');
    altIn.type = 'text';
    altIn.className = 'form-control';
    altIn.value = data.alt || '';
    altIn.addEventListener('change', function () { persist(uid, { alt: altIn.value }); });
    panelEl.appendChild(labeled(STR_14, altIn));

    var capIn = document.createElement('input');
    capIn.type = 'text';
    capIn.className = 'form-control';
    capIn.value = data.caption || '';
    capIn.addEventListener('change', function () { persist(uid, { caption: capIn.value }); });
    panelEl.appendChild(labeled(STR_15, capIn));

    panelEl.appendChild(checkRow(STR_16, data.autoplay, function (v) { persist(uid, { autoplay: v }); }));
    panelEl.appendChild(checkRow(STR_17, data.loop, function (v) { persist(uid, { loop: v }); }));
    panelEl.appendChild(checkRow(STR_18, data.muted !== false, function (v) { persist(uid, { muted: v }); }));
  }

  window.BuilderRegistry.registerBlockType({
    type: 'image_video',
    label: STR_19,
    icon: 'ti-photo',
    category: 'content',
    allowsChildren: false,
    minChildren: 0,
    maxChildren: 0,
    supportsTextFormatting: false,
    allowedChildTypes: [],
    allowedParentTypes: [],
    defaultData: function () { return { kind: 'image', media_id: null, alt: '', caption: '', autoplay: false, loop: false, muted: true }; },
    render: render,
    renderSettingsPanel: renderSettingsPanel
  });
})();
