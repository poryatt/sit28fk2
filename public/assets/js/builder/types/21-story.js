/* Visual page builder — story block (Part 6).
 * Instagram-style circles with per-circle title + cover, multi-media items,
 * fullscreen viewer (per-item timer, prev/next, tap-to-pause, close X,
 * dimmed background). Uploads use XHR so progress is visible.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "رسانه یافت نشد.";
  var STR_2 = "حذف";
  var STR_3 = "انصراف";
  var STR_4 = "موفق";
  var STR_5 = "خطا";
  var STR_6 = "بستن";
  var STR_7 = "استوری ثبت نشده است — از تنظیمات بلاک اضافه کنید.";
  var STR_8 = "استوری";
  var STR_9 = "ا";
  var STR_10 = "خطا در آپلود";
  var STR_11 = "آپلود شد.";
  var STR_12 = "هنوز حلقه‌ای نیست.";
  var STR_13 = "حلقه ";
  var STR_14 = "عنوان حلقه";
  var STR_15 = "کاور حلقه";
  var STR_16 = "مدت (ثانیه)";
  var STR_17 = "حذف مورد";
  var STR_18 = "این مورد حذف می‌شود.";
  var STR_19 = "افزودن آیتم";
  var STR_20 = "در حال آپلود… ۰٪";
  var STR_21 = "در حال آپلود… ";
  var STR_22 = "٪";
  var STR_23 = "افزوده شد.";
  var STR_24 = "حذف حلقه";
  var STR_25 = "<i class=\"ti ti-trash\"></i> حذف حلقه";
  var STR_26 = "این حلقه و همه‌ی موارد آن حذف می‌شود.";
  var STR_27 = "افزودن حلقه";


  // Media lookups go through the picker (gated by pages.view).
  function fetchInfo(mediaId, cb) {
    if (!mediaId) { cb(null); return; }
    if (window.BuilderMediaPicker && typeof window.BuilderMediaPicker.getInfo === 'function') {
      window.BuilderMediaPicker.getInfo(mediaId, cb);
    } else {
      cb(null);
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

  function confirmDelete(title, text, onYes) {
    if (typeof Swal === 'undefined') { onYes(); return; }
    Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonText: STR_2, cancelButtonText: STR_3, customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined), focusCancel: true }).then(function (r) {
      if (r && r.isConfirmed) { onYes(); }
    });
  }

  function notifyOk(msg) {
    if (typeof Swal !== 'undefined') { Swal.fire({ title: STR_4, text: msg, icon: 'success', timer: 2000, showConfirmButton: false }); }
  }
  function notifyErr(msg) {
    if (typeof Swal !== 'undefined') { Swal.fire({ title: STR_5, text: msg, icon: 'error' }); }
  }

  /* ---------- fullscreen viewer (shared by editor canvas and live page) ---------- */
  function openViewer(circles, startCircle) {
    var overlay = document.createElement('div');
    overlay.className = 'builder-story-viewer';
    var box = document.createElement('div');
    box.className = 'builder-story-viewer-box';
    overlay.appendChild(box);
    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'builder-story-viewer-close';
    closeBtn.innerHTML = '<i class="ti ti-x"></i>';
    closeBtn.setAttribute('aria-label', STR_6);
    box.appendChild(closeBtn);
    var bars = document.createElement('div');
    bars.className = 'builder-story-bars';
    box.appendChild(bars);
    var stage = document.createElement('div');
    stage.className = 'builder-story-stage';
    box.appendChild(stage);
    var prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.className = 'builder-story-prev';
    prevBtn.innerHTML = '<i class="ti ti-chevron-right"></i>';
    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'builder-story-next';
    nextBtn.innerHTML = '<i class="ti ti-chevron-left"></i>';
    box.appendChild(prevBtn);
    box.appendChild(nextBtn);
    document.body.appendChild(overlay);

    var st = { ci: startCircle || 0, ii: 0, timer: null, paused: false, barEl: null, barFill: null, remain: 0, startedAt: 0 };
    function cleanup() {
      if (st.timer) { window.clearTimeout(st.timer); st.timer = null; }
      var videos = stage.querySelectorAll('video');
      for (var i = 0; i < videos.length; i++) { try { videos[i].pause(); } catch (e) {} }
    }
    function close() { cleanup(); if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); } document.removeEventListener('keydown', onKey); }
    function onKey(ev) {
      if (ev.key === 'Escape') { close(); }
      else if (ev.key === 'ArrowLeft') { step(1); }
      else if (ev.key === 'ArrowRight') { step(-1); }
      else if (ev.key === ' ') { ev.preventDefault(); togglePause(); }
    }
    function togglePause() {
      if (st.paused) {
        st.paused = false;
        st.startedAt = Date.now();
        st.timer = window.setTimeout(function () { step(1); }, st.remain);
        if (st.barFill) { st.barFill.classList.remove('builder-story-bar-fill--paused'); }
      } else {
        st.paused = true;
        st.remain = Math.max(300, st.remain - (Date.now() - st.startedAt));
        if (st.timer) { window.clearTimeout(st.timer); st.timer = null; }
        if (st.barFill) { st.barFill.classList.add('builder-story-bar-fill--paused'); }
      }
      var vids = stage.querySelectorAll('video');
      for (var i = 0; i < vids.length; i++) { try { if (st.paused) { vids[i].pause(); } else { vids[i].play(); } } catch (e) {} }
    }
    function step(d) {
      var circle = circles[st.ci];
      if (!circle) { close(); return; }
      var items = circle.items || [];
      var ni = st.ii + d;
      if (ni < 0) {
        if (st.ci > 0) { st.ci--; st.ii = (circles[st.ci].items || []).length - 1; show(); }
        else { show(); }
        return;
      }
      if (ni >= items.length) {
        if (st.ci < circles.length - 1) { st.ci++; st.ii = 0; show(); }
        else { close(); }
        return;
      }
      st.ii = ni;
      show();
    }
    function show() {
      cleanup();
      st.paused = false;
      var circle = circles[st.ci];
      if (!circle) { close(); return; }
      var items = circle.items || [];
      if (!items.length) { close(); return; }
      var item = items[st.ii];
      bars.innerHTML = '';
      var fills = [];
      for (var b = 0; b < items.length; b++) {
        var bar = document.createElement('div');
        bar.className = 'builder-story-bar';
        var fill = document.createElement('div');
        fill.className = 'builder-story-bar-fill';
        if (b < st.ii) { fill.classList.add('builder-story-bar-fill--full'); }
        bar.appendChild(fill);
        bars.appendChild(bar);
        fills.push(fill);
      }
      st.barFill = fills[st.ii];
      stage.innerHTML = '';
      var durMs = Math.max(1, Math.min(15, parseInt(item.duration, 10) || 5)) * 1000;
      st.remain = durMs;
      fetchInfo(item.media_id, function (info) {
        if (!document.body.contains(overlay)) { return; }
        stage.innerHTML = '';
        if (!info) {
          var err = document.createElement('div');
          err.className = 'builder-story-err';
          err.textContent = STR_1;
          stage.appendChild(err);
        } else {
          var isVid = info.file_type === 'video' || /\.mp4($|\?)/i.test(info.file_path || '');
          if (isVid) {
            var v = document.createElement('video');
            v.src = info.file_path;
            v.controls = false;
            v.autoplay = true;
            v.setAttribute('playsinline', '');
            try { v.muted = false; } catch (e) {}
            v.addEventListener('ended', function () { step(1); });
            stage.appendChild(v);
            try { var pr = v.play(); if (pr && pr.catch) { pr.catch(function () {}); } } catch (e) {}
          } else {
            var img = document.createElement('img');
            img.src = info.file_path;
            img.alt = '';
            stage.appendChild(img);
          }
        }
        if (st.barFill) {
          st.barFill.style.setProperty('--builder-story-dur', durMs + 'ms'); /* dynamic geometry: per-item duration */
          st.barFill.classList.remove('builder-story-bar-fill--running');
          void st.barFill.offsetWidth;
          st.barFill.classList.add('builder-story-bar-fill--running');
        }
        st.startedAt = Date.now();
        st.timer = window.setTimeout(function () { step(1); }, durMs);
      });
    }
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (ev) { if (ev.target === overlay) { close(); } });
    stage.addEventListener('click', togglePause);
    prevBtn.addEventListener('click', function (ev) { ev.stopPropagation(); step(-1); });
    nextBtn.addEventListener('click', function (ev) { ev.stopPropagation(); step(1); });
    document.addEventListener('keydown', onKey);
    show();
  }

  function render(node, el) {
    var data = node.data || {};
    var circles = Array.isArray(data.circles) ? data.circles : [];
    var wrap = document.createElement('div');
    wrap.className = 'builder-story';
    if (!circles.length) {
      var empty = document.createElement('div');
      empty.className = 'builder-story-empty';
      empty.textContent = STR_7;
      wrap.appendChild(empty);
      el.appendChild(wrap);
      return;
    }
    var row = document.createElement('div');
    row.className = 'builder-story-row';
    wrap.appendChild(row);
    el.appendChild(wrap);
    for (var i = 0; i < circles.length; i++) {
      (function (idx) {
        var c = circles[idx];
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'builder-story-circle';
        var cover = document.createElement('span');
        cover.className = 'builder-story-cover';
        cover.textContent = '…';
        btn.appendChild(cover);
        var title = document.createElement('span');
        title.className = 'builder-story-title';
        title.textContent = c.title || STR_8;
        btn.appendChild(title);
        btn.addEventListener('click', function (ev) { ev.stopPropagation(); openViewer(circles, idx); });
        row.appendChild(btn);
        var coverId = c.cover_media_id || ((c.items && c.items[0]) ? c.items[0].media_id : null);
        fetchInfo(coverId, function (info) {
          cover.textContent = '';
          if (info && !/\.mp4($|\?)/i.test(info.file_path || '')) {
            var img = document.createElement('img');
            img.src = info.file_path;
            img.alt = c.title || '';
            cover.appendChild(img);
          } else {
            cover.textContent = (c.title || STR_9).charAt(0);
          }
        });
      })(i);
    }
  }

  /* ---------- settings panel ---------- */
  function uploadOne(file, onProgress, cb) {
    if (!window.BuilderMediaPicker || typeof window.BuilderMediaPicker.upload !== 'function') {
      var m0 = STR_1;
      notifyErr(m0); cb(new Error(m0)); return;
    }
    window.BuilderMediaPicker.upload(file, onProgress, function (err, res) {
      if (err || !res) {
        var m = (err && err.message) || STR_10;
        notifyErr(m); cb(new Error(m)); return;
      }
      notifyOk(STR_11);
      cb(null, res);
    });
  }

  function renderSettingsPanel(node, panelEl) {
    var data = node.data || {};
    var uid = node.uid;
    var circles = Array.isArray(data.circles) ? JSON.parse(JSON.stringify(data.circles)) : [];
    function save() {
      persist(uid, { circles: circles });
      try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {}
    }
    var list = document.createElement('div');
    list.className = 'builder-story-list';
    panelEl.appendChild(list);

    function redraw() {
      list.innerHTML = '';
      if (!circles.length) {
        var em = document.createElement('div');
        em.className = 'builder-story-hint';
        em.textContent = STR_12;
        list.appendChild(em);
      }
      for (var i = 0; i < circles.length; i++) {
        (function (idx) {
          var c = circles[idx];
          if (!Array.isArray(c.items)) { c.items = []; }
          var card = document.createElement('div');
          card.className = 'builder-story-card';
          var ht = document.createElement('div');
          ht.className = 'builder-story-card-title';
          ht.textContent = STR_13 + (idx + 1);
          card.appendChild(ht);
          var titleIn = document.createElement('input');
          titleIn.type = 'text';
          titleIn.className = 'form-control';
          titleIn.value = c.title || '';
          titleIn.placeholder = STR_14;
          titleIn.addEventListener('change', function () { c.title = titleIn.value; save(); });
          card.appendChild(titleIn);
          var coverIn = document.createElement('div');
          coverIn.className = 'builder-story-field';
          var coverLab = document.createElement('span');
          coverLab.textContent = STR_15;
          coverIn.appendChild(coverLab);
          coverIn.appendChild(mediaField({
            kind: 'image',
            mediaId: c.cover_media_id,
            onChange: function (id) { c.cover_media_id = id; save(); }
          }));
          card.appendChild(coverIn);

          var itemsBox = document.createElement('div');
          itemsBox.className = 'builder-story-items';
          card.appendChild(itemsBox);
          function redrawItems() {
            itemsBox.innerHTML = '';
            for (var j = 0; j < c.items.length; j++) {
              (function (jdx) {
                var it = c.items[jdx];
                var row = document.createElement('div');
                row.className = 'builder-story-item';
                var idField = document.createElement('div');
                idField.className = 'builder-story-field';
                idField.appendChild(mediaField({
                  kind: 'any',
                  mediaId: it.media_id,
                  onChange: function (id) { it.media_id = id; save(); }
                }));
                row.appendChild(idField);
                var durIn = document.createElement('input');
                durIn.type = 'number'; durIn.min = '1'; durIn.max = '15';
                durIn.className = 'form-control';
                durIn.value = it.duration || 5;
                durIn.title = STR_16;
                durIn.addEventListener('change', function () { it.duration = parseInt(durIn.value, 10) || 5; save(); });
                row.appendChild(durIn);
                var delIt = document.createElement('button');
                delIt.type = 'button';
                delIt.className = 'btn btn-outline-danger btn-sm';
                delIt.setAttribute('aria-label', STR_17);
                delIt.innerHTML = '<i class="ti ti-trash"></i>';
                delIt.addEventListener('click', function () {
                  confirmDelete(STR_17, STR_18, function () { c.items.splice(jdx, 1); save(); redrawItems(); });
                });
                row.appendChild(delIt);
                itemsBox.appendChild(row);
              })(j);
            }
          }
          redrawItems();

          var addItem = document.createElement('button');
          addItem.type = 'button';
          addItem.className = 'btn btn-outline-primary btn-sm';
          addItem.textContent = STR_19;
          addItem.addEventListener('click', function () {
            if (c.items.length >= 10) { return; }
            c.items.push({ media_id: null, duration: 5, link: '' });
            save(); redrawItems();
          });
          card.appendChild(addItem);

          var upIn = document.createElement('input');
          upIn.type = 'file';
          upIn.accept = 'image/jpeg,image/png,image/webp,video/mp4';
          upIn.className = 'form-control';
          var upProg = document.createElement('div');
          upProg.className = 'builder-story-hint';
          upProg.classList.add('builder-is-hidden');
          upIn.addEventListener('change', function () {
            if (!upIn.files || !upIn.files[0] || c.items.length >= 10) { return; }
            upProg.classList.remove('builder-is-hidden');
            upProg.textContent = STR_20;
            uploadOne(upIn.files[0], function (p) { upProg.textContent = STR_21 + p + STR_22; }, function (err, res) {
              if (err || !res) { upProg.textContent = STR_10; return; }
              upProg.textContent = STR_23;
              c.items.push({ media_id: res.id, duration: 5, link: '' });
              save(); redrawItems();
            });
          });
          card.appendChild(upIn);
          card.appendChild(upProg);

          var delC = document.createElement('button');
          delC.type = 'button';
          delC.className = 'btn btn-outline-danger btn-sm';
          delC.setAttribute('aria-label', STR_24);
          delC.innerHTML = STR_25;
          delC.addEventListener('click', function () {
            confirmDelete(STR_24, STR_26, function () { circles.splice(idx, 1); save(); redraw(); });
          });
          card.appendChild(delC);
          list.appendChild(card);
        })(i);
      }
    }
    redraw();

    var addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn btn-primary btn-sm';
    addBtn.textContent = STR_27;
    addBtn.addEventListener('click', function () {
      if (circles.length >= 10) { return; }
      circles.push({ title: STR_8, cover_media_id: null, items: [] });
      save(); redraw();
    });
    panelEl.appendChild(addBtn);
  }

  window.BuilderRegistry.registerBlockType({
    type: 'story',
    label: STR_8,
    icon: 'ti-circle-dot',
    category: 'content',
    allowsChildren: false,
    minChildren: 0,
    maxChildren: 0,
    supportsTextFormatting: false,
    allowedChildTypes: [],
    allowedParentTypes: [],
    defaultData: function () { return { circles: [] }; },
    render: render,
    renderSettingsPanel: renderSettingsPanel
  });

  window.BuilderStoryViewer = openViewer;
})();
