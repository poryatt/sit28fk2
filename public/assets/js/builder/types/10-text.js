/* Visual page builder — text block (Part 4).
 * Inline rich text via native contenteditable + execCommand. No rich-text library.
 * Registers format commands once; each editable applies builder:format for its own uid.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "درشت";
  var STR_2 = "کج";
  var STR_3 = "زیرخط";
  var STR_4 = "تیتر ۱";
  var STR_5 = "تیتر ۲";
  var STR_6 = "تیتر ۳";
  var STR_7 = "تیتر ۴";
  var STR_8 = "متن عادی";
  var STR_9 = "فهرست";
  var STR_10 = "فهرست شماره‌دار";
  var STR_11 = "نقل‌قول";
  var STR_12 = "پیوند";
  var STR_13 = "راست‌چین";
  var STR_14 = "وسط‌چین";
  var STR_15 = "چپ‌چین";
  var STR_16 = "ثبت";
  var STR_17 = "انصراف";
  var STR_18 = "متن را بنویسید…";
  var STR_19 = "هم‌عرض";
  var STR_20 = "تراز متن";
  var STR_21 = "رنگ متن";
  var STR_22 = "رنگ پس‌زمینه";
  var STR_23 = "حذف رنگ‌ها";
  var STR_24 = "قالب‌بندی (درشت، کج، تیتر، فهرست، پیوند) از نوار ابزار بالای بلاک انجام می‌شود.";
  var STR_25 = "متن";


  var COMMANDS = [
    { id: 'bold', icon: 'ti-bold', title: STR_1 },
    { id: 'italic', icon: 'ti-italic', title: STR_2 },
    { id: 'underline', icon: 'ti-underline', title: STR_3 },
    { id: 'h1', icon: 'ti-h-1', title: STR_4 },
    { id: 'h2', icon: 'ti-h-2', title: STR_5 },
    { id: 'h3', icon: 'ti-h-3', title: STR_6 },
    { id: 'h4', icon: 'ti-h-4', title: STR_7 },
    { id: 'p', icon: 'ti-pilcrow', title: STR_8 },
    { id: 'ul', icon: 'ti-list-ul', title: STR_9 },
    { id: 'ol', icon: 'ti-list-ol', title: STR_10 },
    { id: 'blockquote', icon: 'ti-quote', title: STR_11 },
    { id: 'link', icon: 'ti-link', title: STR_12 },
    { id: 'align-right', icon: 'ti-align-right', title: STR_13 },
    { id: 'align-center', icon: 'ti-align-center', title: STR_14 },
    { id: 'align-left', icon: 'ti-align-left', title: STR_15 }
  ];

  var registered = false;
  function ensureCommands() {
    if (registered || !window.BuilderCanvas) { return; }
    for (var i = 0; i < COMMANDS.length; i++) {
      try { window.BuilderCanvas.registerFormatCommand(COMMANDS[i]); } catch (e) { /* duplicate — ignore */ }
    }
    registered = true;
  }

  function canEdit() {
    return window.BuilderShell && window.BuilderShell.canEdit();
  }

  function currentData(uid) {
    var node = window.BuilderCanvas ? window.BuilderCanvas.getNode(uid) : null;
    return node ? node.data || {} : {};
  }

  function applyCommand(uid, command) {
    var blockEl = window.BuilderCanvas ? window.BuilderCanvas.getBlockElement(uid) : null;
    if (!blockEl) { return; }
    var editable = blockEl.querySelector('.builder-text-editable');
    if (!editable) { return; }
    editable.focus();
    try {
      if (command === 'bold') { document.execCommand('bold', false, null); }
      else if (command === 'italic') { document.execCommand('italic', false, null); }
      else if (command === 'underline') { document.execCommand('underline', false, null); }
      else if (command === 'h1') { document.execCommand('formatBlock', false, 'h1'); }
      else if (command === 'h2') { document.execCommand('formatBlock', false, 'h2'); }
      else if (command === 'h3') { document.execCommand('formatBlock', false, 'h3'); }
      else if (command === 'h4') { document.execCommand('formatBlock', false, 'h4'); }
      else if (command === 'p') { document.execCommand('formatBlock', false, 'p'); }
      else if (command === 'blockquote') { document.execCommand('formatBlock', false, 'blockquote'); }
      else if (command === 'ul') { document.execCommand('insertUnorderedList', false, null); }
      else if (command === 'ol') { document.execCommand('insertOrderedList', false, null); }
      else if (command === 'align-right') { document.execCommand('justifyRight', false, null); }
      else if (command === 'align-center') { document.execCommand('justifyCenter', false, null); }
      else if (command === 'align-left') { document.execCommand('justifyLeft', false, null); }
      else if (command === 'link') {
        var url = '';
        if (typeof Swal !== 'undefined') {
          Swal.fire({ title: STR_12, input: 'text', inputPlaceholder: 'https://…', showCancelButton: true, confirmButtonText: STR_16, cancelButtonText: STR_17 }).then(function (r) {
            if (r && r.isConfirmed && r.value) { document.execCommand('createLink', false, r.value); queueSave(uid, editable); }
          });
          return;
        } else {
          url = window.prompt(STR_12, 'https://');
          if (url) { document.execCommand('createLink', false, url); }
        }
      } else { return; }
    } catch (e) { return; }
    queueSave(uid, editable);
    syncFormatState();
  }

  var saveTimers = {};
  var pending = {};
  function commit(uid) {
    var editable = pending[uid];
    delete pending[uid];
    if (saveTimers[uid]) { window.clearTimeout(saveTimers[uid]); delete saveTimers[uid]; }
    if (!editable || !window.BuilderCanvas) { return; }
    var data = currentData(uid);
    var next = {};
    for (var k in data) { if (Object.prototype.hasOwnProperty.call(data, k)) { next[k] = data[k]; } }
    next.html = editable.innerHTML;
    try { window.BuilderCanvas.updateBlockData(uid, next, { skipRefresh: true }); } catch (e) { /* read-only — ignore */ }
  }
  function flushAll() {
    var uids = Object.keys(pending);
    for (var i = 0; i < uids.length; i++) { commit(uids[i]); }
  }
  function queueSave(uid, editable) {
    pending[uid] = editable;
    if (saveTimers[uid]) { window.clearTimeout(saveTimers[uid]); }
    saveTimers[uid] = window.setTimeout(function () { commit(uid); }, 500);
    if (window.BuilderShell) { window.BuilderShell.markDirty(); }
  }
  document.addEventListener('builder:before-save', flushAll);

  function syncFormatState(ev) {
    if (!window.BuilderCanvas) { return; }
    if (ev && ev.target && ev.target.closest && ev.target.closest('.builder-toolbar, .builder-settings-panel, .builder-insert-menu, .builder-media-picker')) { return; }
    var sel = window.getSelection ? window.getSelection() : null;
    var anchor = sel && sel.anchorNode ? (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentNode) : null;
    if (!anchor || !anchor.closest || !anchor.closest('.builder-text-editable')) { return; }
    var state = {};
    try {
      state.bold = document.queryCommandState('bold');
      state.italic = document.queryCommandState('italic');
      state.underline = document.queryCommandState('underline');
    } catch (e) { return; }
    try { window.BuilderCanvas.setFormatState(state); } catch (e) { /* ignore */ }
  }

  document.addEventListener('builder:format', function (e) {
    var d = (e && e.detail) || {};
    if (!d.command || !d.uid) { return; }
    applyCommand(d.uid, d.command);
  });
  document.addEventListener('keyup', syncFormatState);
  document.addEventListener('mouseup', syncFormatState);

  function render(node, el) {
    ensureCommands();
    var data = node.data || {};
    var wrap = document.createElement('div');
    wrap.className = 'builder-text-wrap';
    var editable = document.createElement('div');
    editable.className = 'builder-text-editable';
    editable.setAttribute('dir', 'rtl');
    var align = data.align || 'right';
    editable.setAttribute('data-align', align === 'center' ? 'center' : (align === 'left' ? 'left' : (align === 'justify' ? 'justify' : 'right')));
    if (data.color) { try { editable.style.setProperty('--builder-text-color', data.color); } catch (e) {} } /* dynamic geometry: user-chosen colour */
    if (data.bg) { try { editable.style.setProperty('--builder-text-bg', data.bg); } catch (e) {} } /* dynamic geometry: user-chosen colour */
    editable.innerHTML = data.html || '';
    if (!data.html) {
      editable.setAttribute('data-placeholder', STR_18);
    }
    if (canEdit()) {
      editable.contentEditable = 'true';
      editable.addEventListener('input', function () { queueSave(node.uid, editable); });
      var selectSelf = function () { try { window.BuilderCanvas.select(node.uid); } catch (e) { /* ignore */ } };
      editable.addEventListener('pointerdown', selectSelf);
      editable.addEventListener('focusin', selectSelf);
      editable.addEventListener('blur', function () { commit(node.uid); });
    } else {
      editable.contentEditable = 'false';
    }
    wrap.appendChild(editable);
    el.appendChild(wrap);
  }

  function field(labelText, inputEl) {
    var lab = document.createElement('label');
    lab.className = 'builder-text-field';
    var sp = document.createElement('span');
    sp.textContent = labelText;
    lab.appendChild(sp);
    lab.appendChild(inputEl);
    return lab;
  }

  function renderSettingsPanel(node, panelEl) {
    var data = node.data || {};
    function persist(patch) {
      var base = currentData(node.uid);
      var next = {};
      for (var k in base) { if (Object.prototype.hasOwnProperty.call(base, k)) { next[k] = base[k]; } }
      for (var p in patch) { if (Object.prototype.hasOwnProperty.call(patch, p)) { next[p] = patch[p]; } }
      try { window.BuilderCanvas.updateBlockData(node.uid, next); } catch (e) {}
    }
    var alignSel = document.createElement('select');
    alignSel.className = 'form-control';
    var opts = [['right', STR_13], ['center', STR_14], ['left', STR_15], ['justify', STR_19]];
    for (var i = 0; i < opts.length; i++) {
      var o = document.createElement('option');
      o.value = opts[i][0];
      o.textContent = opts[i][1];
      if ((data.align || 'right') === opts[i][0]) { o.selected = true; }
      alignSel.appendChild(o);
    }
    alignSel.addEventListener('change', function () { persist({ align: alignSel.value }); });
    panelEl.appendChild(field(STR_20, alignSel));

    var colorIn = document.createElement('input');
    colorIn.type = 'color';
    colorIn.value = data.color || '#000000';
    colorIn.className = 'form-control';
    colorIn.addEventListener('change', function () { persist({ color: colorIn.value }); });
    panelEl.appendChild(field(STR_21, colorIn));

    var bgIn = document.createElement('input');
    bgIn.type = 'color';
    bgIn.value = data.bg || '#ffffff';
    bgIn.className = 'form-control';
    bgIn.addEventListener('change', function () { persist({ bg: bgIn.value }); });
    panelEl.appendChild(field(STR_22, bgIn));

    var clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'btn btn-outline-secondary btn-sm';
    clearBtn.textContent = STR_23;
    clearBtn.addEventListener('click', function () { persist({ color: '', bg: '' }); });
    panelEl.appendChild(clearBtn);

    var hint = document.createElement('div');
    hint.className = 'builder-text-hint';
    hint.textContent = STR_24;
    panelEl.appendChild(hint);
  }

  ensureCommands();

  window.BuilderRegistry.registerBlockType({
    type: 'text',
    label: STR_25,
    icon: 'ti-typography',
    category: 'content',
    allowsChildren: false,
    minChildren: 0,
    maxChildren: 0,
    supportsTextFormatting: true,
    settingsOnSelect: false,
    allowedChildTypes: [],
    allowedParentTypes: [],
    defaultData: function () { return { html: '', align: 'right' }; },
    render: render,
    renderSettingsPanel: renderSettingsPanel
  });
})();
