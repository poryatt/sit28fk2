/* Visual page builder — button block (Part 4).
 * Text label and/or image + destination link + new-tab toggle.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "رسانه یافت نشد.";
  var STR_2 = "دکمه";
  var STR_3 = "متن دکمه";
  var STR_4 = "https://… یا /نشانی-داخلی";
  var STR_5 = "پیوند مقصد";
  var STR_6 = "باز شدن در برگه جدید";
  var STR_7 = "تصویر دکمه";


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

  function render(node, el) {
    var data = node.data || {};
    var wrap = document.createElement('div');
    wrap.className = 'builder-button-wrap';
    var a = document.createElement('a');
    a.className = 'builder-button-link btn btn-primary';
    a.textContent = data.label || STR_2;
    a.href = data.url || '#';
    if (data.open_new_tab) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
    a.addEventListener('click', function (ev) {
      if (!data.url || data.url === '#') { ev.preventDefault(); }
      ev.stopPropagation();
    });
    if (data.media_id) {
      fetchInfo(data.media_id, function (info) {
        if (!info) { return; }
        var img = document.createElement('img');
        img.className = 'builder-button-img';
        img.src = info.file_path;
        img.alt = data.label || '';
        a.insertBefore(img, a.firstChild);
      });
    }
    wrap.appendChild(a);
    el.appendChild(wrap);
  }

  function labeled(labelText, inputEl) {
    var lab = document.createElement('label');
    lab.className = 'builder-button-field';
    var sp = document.createElement('span');
    sp.textContent = labelText;
    lab.appendChild(sp);
    lab.appendChild(inputEl);
    return lab;
  }

  function renderSettingsPanel(node, panelEl) {
    var data = node.data || {};
    var uid = node.uid;
    function persist(patch) {
      var base = currentData(uid);
      var next = {};
      for (var k in base) { if (Object.prototype.hasOwnProperty.call(base, k)) { next[k] = base[k]; } }
      for (var p in patch) { if (Object.prototype.hasOwnProperty.call(patch, p)) { next[p] = patch[p]; } }
      try { window.BuilderCanvas.updateBlockData(uid, next); } catch (e) {}
    }
    var labelIn = document.createElement('input');
    labelIn.type = 'text';
    labelIn.className = 'form-control';
    labelIn.value = data.label || '';
    labelIn.addEventListener('change', function () { persist({ label: labelIn.value }); try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {} });
    panelEl.appendChild(labeled(STR_3, labelIn));

    var urlIn = document.createElement('input');
    urlIn.type = 'text';
    urlIn.className = 'form-control';
    urlIn.dir = 'ltr';
    urlIn.value = data.url || '';
    urlIn.placeholder = STR_4;
    urlIn.addEventListener('change', function () { persist({ url: urlIn.value }); try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {} });
    panelEl.appendChild(labeled(STR_5, urlIn));

    var tabLab = document.createElement('label');
    tabLab.className = 'builder-button-check';
    var tabCb = document.createElement('input');
    tabCb.type = 'checkbox';
    tabCb.checked = !!data.open_new_tab;
    tabCb.addEventListener('change', function () { persist({ open_new_tab: tabCb.checked }); try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {} });
    tabLab.appendChild(tabCb);
    var tabSp = document.createElement('span');
    tabSp.textContent = STR_6;
    tabLab.appendChild(tabSp);
    panelEl.appendChild(tabLab);

    var imgIn = document.createElement('div');
    imgIn.className = 'builder-button-field';
    var imgLab = document.createElement('span');
    imgLab.textContent = STR_7;
    imgIn.appendChild(imgLab);
    imgIn.appendChild(mediaField({
      kind: 'image',
      mediaId: data.media_id,
      onChange: function (id) {
        persist({ media_id: id });
        try { window.BuilderCanvas.refreshBlock(uid); } catch (e) {}
      }
    }));
    panelEl.appendChild(imgIn);
  }

  window.BuilderRegistry.registerBlockType({
    type: 'button',
    label: STR_2,
    icon: 'ti-link',
    category: 'content',
    allowsChildren: false,
    minChildren: 0,
    maxChildren: 0,
    supportsTextFormatting: false,
    allowedChildTypes: [],
    allowedParentTypes: [],
    defaultData: function () { return { label: STR_2, url: '', open_new_tab: false, media_id: null }; },
    render: render,
    renderSettingsPanel: renderSettingsPanel
  });
})();
