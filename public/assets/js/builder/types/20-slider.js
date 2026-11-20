/* Visual page builder — slider block (Part 5).
 * Per-slide image + optional link, configurable autoplay, prev/next, dots,
 * pause-on-hover. Vanilla JS (no Swiper.js): one small slider is not worth
 * a new dependency, and this keeps offline XAMPP development working.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "رسانه یافت نشد.";
  var STR_2 = "حذف";
  var STR_3 = "انصراف";
  var STR_4 = "اسلایدی ثبت نشده است — از تنظیمات بلاک اسلاید اضافه کنید.";
  var STR_5 = "قبلی";
  var STR_6 = "بعدی";
  var STR_7 = "اسلاید ";
  var STR_8 = "هنوز اسلایدی نیست.";
  var STR_9 = "پیوند (اختیاری)";
  var STR_10 = "زیرنویس (اختیاری)";
  var STR_11 = "حذف اسلاید";
  var STR_12 = "<i class=\"ti ti-trash\"></i> حذف اسلاید";
  var STR_13 = "این اسلاید حذف می‌شود.";
  var STR_14 = "افزودن اسلاید";
  var STR_15 = "در حال آپلود...";
  var STR_16 = "خطا در آپلود";
  var STR_17 = "افزوده شد.";
  var STR_18 = "آپلود و افزودن تصویر";
  var STR_19 = "پخش خودکار";
  var STR_20 = "فاصله پخش خودکار (میلی‌ثانیه)";
  var STR_21 = "توقف با نگه داشتن موس";
  var STR_22 = "اسلایدر";


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

  function render(node, el) {
    var data = node.data || {};
    var slides = Array.isArray(data.slides) ? data.slides : [];
    var wrap = document.createElement('div');
    wrap.className = 'builder-slider';
    if (!slides.length) {
      var empty = document.createElement('div');
      empty.className = 'builder-slider-empty';
      empty.textContent = STR_4;
      wrap.appendChild(empty);
      el.appendChild(wrap);
      return;
    }
    var viewport = document.createElement('div');
    viewport.className = 'builder-slider-viewport';
    var track = document.createElement('div');
    track.className = 'builder-slider-track';
    viewport.appendChild(track);
    wrap.appendChild(viewport);

    var prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'builder-slider-nav builder-slider-prev';
    prev.innerHTML = '<i class="ti ti-chevron-right"></i>';
    prev.setAttribute('aria-label', STR_5);
    var nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.className = 'builder-slider-nav builder-slider-next';
    nextBtn.innerHTML = '<i class="ti ti-chevron-left"></i>';
    nextBtn.setAttribute('aria-label', STR_6);
    wrap.appendChild(prev);
    wrap.appendChild(nextBtn);

    var dots = document.createElement('div');
    dots.className = 'builder-slider-dots';
    wrap.appendChild(dots);
    el.appendChild(wrap);

    var state = { index: 0, timer: null, count: slides.length };
    var slideEls = [];
    var dotEls = [];

    function show(i) {
      state.index = (i + state.count) % state.count;
      for (var k = 0; k < slideEls.length; k++) {
        slideEls[k].classList.toggle('builder-is-hidden', k !== state.index);
        if (dotEls[k]) { dotEls[k].classList.toggle('builder-slider-dot-active', k === state.index); }
      }
    }
    function stop() { if (state.timer) { window.clearInterval(state.timer); state.timer = null; } }
    function start() {
      stop();
      if (!data.autoplay || state.count < 2) { return; }
      var interval = parseInt(data.interval, 10) || 4000;
      state.timer = window.setInterval(function () { show(state.index + 1); }, interval);
    }

    for (var s = 0; s < slides.length; s++) {
      (function (idx) {
        var sd = slides[idx];
        var cell = document.createElement('div');
        cell.className = 'builder-slider-slide';
        if (idx !== 0) { cell.classList.add('builder-is-hidden'); }
        var ph = document.createElement('div');
        ph.className = 'builder-slider-empty';
        ph.textContent = '…';
        cell.appendChild(ph);
        track.appendChild(cell);
        slideEls.push(cell);
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'builder-slider-dot' + (idx === 0 ? ' builder-slider-dot-active' : '');
        dot.setAttribute('aria-label', STR_7 + (idx + 1));
        dot.addEventListener('click', function (ev) { ev.stopPropagation(); show(idx); start(); });
        dots.appendChild(dot);
        dotEls.push(dot);
        fetchInfo(sd.media_id, function (info) {
          cell.removeChild(ph);
          if (!info) {
            var err = document.createElement('div');
            err.className = 'builder-slider-empty';
            err.textContent = STR_1;
            cell.appendChild(err);
            return;
          }
          var inner = null;
          var img = document.createElement('img');
          img.className = 'builder-slider-img';
          img.src = info.file_path;
          img.alt = sd.caption || '';
          if (sd.link) {
            inner = document.createElement('a');
            inner.href = sd.link;
            inner.appendChild(img);
            inner.addEventListener('click', function (ev) { ev.stopPropagation(); });
          } else { inner = img; }
          cell.appendChild(inner);
          if (sd.caption) {
            var cap = document.createElement('div');
            cap.className = 'builder-slider-caption';
            cap.textContent = sd.caption;
            cell.appendChild(cap);
          }
        });
      })(s);
    }

    prev.addEventListener('click', function (ev) { ev.stopPropagation(); show(state.index - 1); start(); });
    nextBtn.addEventListener('click', function (ev) { ev.stopPropagation(); show(state.index + 1); start(); });
    if (data.pause_on_hover !== false) {
      wrap.addEventListener('mouseenter', stop);
      wrap.addEventListener('mouseleave', start);
    }
    start();
  }

  function labeled(labelText, inputEl) {
    var lab = document.createElement('label');
    lab.className = 'builder-slider-field';
    var sp = document.createElement('span');
    sp.textContent = labelText;
    lab.appendChild(sp);
    lab.appendChild(inputEl);
    return lab;
  }

  function renderSettingsPanel(node, panelEl) {
    var data = node.data || {};
    var uid = node.uid;
    var slides = Array.isArray(data.slides) ? JSON.parse(JSON.stringify(data.slides)) : [];

    function save() {
      persist(uid, { slides: slides });
      try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {}
    }

    var list = document.createElement('div');
    list.className = 'builder-slider-list';
    panelEl.appendChild(list);

    function redraw() {
      list.innerHTML = '';
      if (!slides.length) {
        var em = document.createElement('div');
        em.className = 'builder-slider-hint';
        em.textContent = STR_8;
        list.appendChild(em);
      }
      for (var i = 0; i < slides.length; i++) {
        (function (idx) {
          var sd = slides[idx];
          var card = document.createElement('div');
          card.className = 'builder-slider-card';
          var title = document.createElement('div');
          title.className = 'builder-slider-card-title';
          title.textContent = STR_7 + (idx + 1);
          card.appendChild(title);
          var idField = document.createElement('div');
          idField.className = 'builder-slider-field';
          idField.appendChild(mediaField({
            kind: 'image',
            mediaId: sd.media_id,
            onChange: function (id) { sd.media_id = id; save(); }
          }));
          card.appendChild(idField);
          var linkIn = document.createElement('input');
          linkIn.type = 'text';
          linkIn.className = 'form-control';
          linkIn.dir = 'ltr';
          linkIn.value = sd.link || '';
          linkIn.placeholder = STR_9;
          linkIn.addEventListener('change', function () { sd.link = linkIn.value; save(); });
          card.appendChild(linkIn);
          var capIn = document.createElement('input');
          capIn.type = 'text';
          capIn.className = 'form-control';
          capIn.value = sd.caption || '';
          capIn.placeholder = STR_10;
          capIn.addEventListener('change', function () { sd.caption = capIn.value; save(); });
          card.appendChild(capIn);
          var del = document.createElement('button');
          del.type = 'button';
          del.className = 'btn btn-outline-danger btn-sm';
          del.setAttribute('aria-label', STR_11);
          del.innerHTML = STR_12;
          del.addEventListener('click', function () {
            confirmDelete(STR_11, STR_13, function () { slides.splice(idx, 1); save(); redraw(); });
          });
          card.appendChild(del);
          list.appendChild(card);
        })(i);
      }
    }
    redraw();

    var addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn btn-outline-primary btn-sm';
    addBtn.textContent = STR_14;
    addBtn.addEventListener('click', function () {
      if (slides.length >= 10) { return; }
      slides.push({ media_id: null, link: '', caption: '' });
      redraw();
    });
    panelEl.appendChild(addBtn);

    var upIn = document.createElement('input');
    upIn.type = 'file';
    upIn.accept = 'image/jpeg,image/png,image/webp';
    upIn.className = 'form-control';
    var upProg = document.createElement('div');
    upProg.className = 'builder-slider-hint';
    upProg.classList.add('builder-is-hidden');
upIn.addEventListener('change', function () {
      if (!upIn.files || !upIn.files[0] || slides.length >= 10) { return; }
      upProg.classList.remove('builder-is-hidden');
      upProg.textContent = STR_15;
      if (!window.BuilderMediaPicker || typeof window.BuilderMediaPicker.upload !== 'function') {
        upProg.textContent = STR_16;
        return;
      }
      window.BuilderMediaPicker.upload(upIn.files[0], null, function (err, res) {
        if (err || !res) { upProg.textContent = (err && err.message) || STR_16; return; }
        slides.push({ media_id: res.id, link: '', caption: '' });
        upProg.textContent = STR_17;
        save(); redraw();
      });
    });
    panelEl.appendChild(labeled(STR_18, upIn));
    panelEl.appendChild(upProg);

    var autoLab = document.createElement('label');
    autoLab.className = 'builder-slider-check';
    var autoCb = document.createElement('input');
    autoCb.type = 'checkbox';
    autoCb.checked = data.autoplay !== false;
    autoCb.addEventListener('change', function () { persist(uid, { autoplay: autoCb.checked }); try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {} });
    autoLab.appendChild(autoCb);
    var autoSp = document.createElement('span');
    autoSp.textContent = STR_19;
    autoLab.appendChild(autoSp);
    panelEl.appendChild(autoLab);

    var intIn = document.createElement('input');
    intIn.type = 'number'; intIn.min = '1000'; intIn.max = '15000'; intIn.step = '500';
    intIn.className = 'form-control';
    intIn.dir = 'ltr';
    intIn.value = data.interval || 4000;
    intIn.addEventListener('change', function () { persist(uid, { interval: parseInt(intIn.value, 10) || 4000 }); try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {} });
    panelEl.appendChild(labeled(STR_20, intIn));

    var hovLab = document.createElement('label');
    hovLab.className = 'builder-slider-check';
    var hovCb = document.createElement('input');
    hovCb.type = 'checkbox';
    hovCb.checked = data.pause_on_hover !== false;
    hovCb.addEventListener('change', function () { persist(uid, { pause_on_hover: hovCb.checked }); try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {} });
    hovLab.appendChild(hovCb);
    var hovSp = document.createElement('span');
    hovSp.textContent = STR_21;
    hovLab.appendChild(hovSp);
    panelEl.appendChild(hovLab);
  }

  window.BuilderRegistry.registerBlockType({
    type: 'slider',
    label: STR_22,
    icon: 'ti-library-photo',
    category: 'content',
    allowsChildren: false,
    minChildren: 0,
    maxChildren: 0,
    supportsTextFormatting: false,
    allowedChildTypes: [],
    allowedParentTypes: [],
    defaultData: function () { return { slides: [], autoplay: true, interval: 4000, pause_on_hover: true }; },
    render: render,
    renderSettingsPanel: renderSettingsPanel
  });
})();
