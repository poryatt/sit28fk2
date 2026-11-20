/* Visual page builder — canvas engine (Part 2).
 * Renders the block tree, selection, toolbar chrome, column split/unsplit,
 * delete and sibling drag-and-drop. It never knows what any content block
 * contains: content types draw themselves through their own render().
 * Pure tree rules live in builder-tree.js (window.BuilderTree).
 * Exposes exactly one global: window.BuilderCanvas.
 *
 * Contents:
 *   1. Strings and limits
 *   2. Structural type constants + column factories
 *   3. Module state and small helpers (deepCopy, escapeHtml, emit)
 *   4. Tree queries — delegated to window.BuilderTree
 *   5. Element creation
 *   6. Sortable wiring
 *   7. Selection + toolbar
 *   8. Column split / unsplit
 *   9. Public mutations
 *  10. Read-only mode
 *  11. Controller + public API + install
 */
(function () {
  'use strict';

  /* Persian strings, verbatim (§12). Labels/icons of block types always come
   * from the registry, never from this list. */
  var STR_ADD = 'افزودن بلاک';
  var STR_DELETE = 'حذف بلاک';
  var STR_DRAG = 'جابه‌جایی';
  var STR_EMPTY = 'هنوز بلاکی اضافه نشده است';
  var STR_NO_RENDER = 'نمایش این بلاک هنوز اضافه نشده است';
  var STR_GHOST = 'این نوع بلاک شناخته نمی‌شود';
  var STR_DEL_TITLE = 'حذف بلاک';
  var STR_DEL_TEXT = 'این بلاک و همه‌ی محتوای داخل آن حذف می‌شود.';
  var STR_DEL_OK = 'حذف';
  var STR_CANCEL = 'انصراف';
  var STR_COL_TITLE = 'حذف ستون‌های پر';
  var STR_COL_TEXT = 'ستون‌هایی که حذف می‌شوند محتوا دارند. ادامه می‌دهید؟';
  var STR_SETTINGS = 'تنظیمات بلاک';
  var STR_READONLY = 'شما اجازه‌ی ویرایش این بوم را ندارید.';
  var STR_ERROR = 'خطا';

  /* Mirrored from includes/builder/tree_validator.php, copied character for
   * character from that file (not from the Part 2 prompt table). */
  var V_UNKNOWN_TYPE = 'نوع بلاک نامعتبر است';
  var V_OFF_ROOT = 'بلاک ساختاری فقط میتواند در ریشه قرار بگیرد';
  var V_COUNT = 'تعداد فرزندهای بلاک مجاز نیست';
  var V_TOO_MANY = 'تعداد بلاکها بیش از حد مجاز است';
  var V_TOO_DEEP = 'عمق تودرتویی بیش از حد مجاز است';
  var V_BAD_PLACE = 'این نوع بلاک در این محل مجاز نیست';
  var V_BAD_ROOT = 'ساختار ریشه باید دقیقاً شامل هدر، بدنه و فوتر باشد';

  var MAX_BLOCKS = 500;
  var MAX_DEPTH = 8;

  /* Column plumbing this part owns. Structural type rules (header/body/footer,
   * column, column_container) live in builder-tree.js. */
  var ROOT_DEPTH = 1;
  var TYPE_COLUMN = 'column';
  var TYPE_CONTAINER = 'column_container';

  /* Type rules and effective-constraint helpers live in builder-tree.js
   * (window.BuilderTree); this file calls them with the tree as an argument. */

  /* Structural node factory. BuilderCore.createColumnContainer/createNode
   * need client-side registrations that Part 0 did not ship, so this tries
   * them first and falls back to the specified node shape. */
  function makeColumn() {
    try {
      return window.BuilderCore.createNode(TYPE_COLUMN);
    } catch (e) {
      return { uid: window.BuilderCore.createUid(), type: TYPE_COLUMN, data: {}, children: [] };
    }
  }

  function makeContainer(n) {
    try {
      return window.BuilderCore.createColumnContainer(n);
    } catch (e) {
      var container = { uid: window.BuilderCore.createUid(), type: TYPE_CONTAINER, data: {}, children: [] };
      for (var i = 0; i < n; i++) {
        container.children.push(makeColumn());
      }
      return container;
    }
  }

  var FORMAT_ID_RE = /^[a-z][a-z0-9_-]{0,31}$/;

  var tree = null;
  var mountEl = null;
  var toolbarEl = null;
  var toolbarHtmlCache = '';
  var selectedUid = null;
  var interactive = true;
  var els = {};
  var lists = {};
  var sortables = {};
  var formatCommands = {};
  var formatOrder = [];
  var formatState = {};
  var renderBound = false;
  var listenersBound = false;

  function deepCopy(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function canEdit() {
    return window.BuilderShell && window.BuilderShell.canEdit();
  }

  function emit(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { detail: detail }));
  }

  function fail(message) {
    Swal.fire({ title: STR_ERROR, text: message, icon: 'error' });
  }

  function childByClass(el, cls) {
    for (var i = 0; i < el.childNodes.length; i++) {
      var child = el.childNodes[i];
      if (child.nodeType === 1 && child.classList.contains(cls)) {
        return child;
      }
    }
    return null;
  }

  function directBlocks(listEl) {
    var out = [];
    for (var i = 0; i < listEl.childNodes.length; i++) {
      var child = listEl.childNodes[i];
      if (child.nodeType === 1 && child.classList.contains('builder-block')) {
        out.push(child);
      }
    }
    return out;
  }

  /* ===== Section: tree queries (delegated to window.BuilderTree) ===== */

  function needEdit() {
    if (!canEdit()) {
      fail(STR_READONLY);
      return false;
    }
    return true;
  }

  function mutated(reason) {
    window.BuilderShell.markDirty();
    updateToolbar();
    emit('builder:tree-changed', { reason: reason });
  }

  /* ===== Section: element creation ===== */

  function runTypeRender(def, node, contentEl) {
    if (def && typeof def.render === 'function') {
      def.render(node, contentEl);
      return;
    }
    if (def && def.category === 'content') {
      var placeholder = document.createElement('div');
      placeholder.className = 'builder-canvas-norender';
      placeholder.textContent = STR_NO_RENDER;
      contentEl.appendChild(placeholder);
      return;
    }
    if (!def) {
      var ghost = document.createElement('div');
      ghost.className = 'builder-canvas-ghost';
      ghost.textContent = STR_GHOST + ': ' + node.type;
      contentEl.appendChild(ghost);
    }
  }

  function refreshContent(uid) {
    var el = els[uid];
    if (!el) {
      return;
    }
    var loc = window.BuilderTree.locate(tree, uid);
    if (!loc) {
      return;
    }
    var contentEl = childByClass(el, 'builder-block-content');
    if (!contentEl) {
      return;
    }
    contentEl.innerHTML = '';
    runTypeRender(window.BuilderRegistry.getBlockType(loc.node.type), loc.node, contentEl);
  }

  function makeAffordance(parentUid) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'builder-block-add';
    btn.title = STR_ADD;
    btn.innerHTML = '<i class="ti ti-plus"></i><span>' + escapeHtml(STR_ADD) + '</span>';
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var loc = window.BuilderTree.locate(tree, parentUid);
      if (!loc) {
        return;
      }
      emit('builder:insert-requested', { parentUid: parentUid, index: loc.node.children.length, anchorEl: btn });
    });
    return btn;
  }

  function refreshListChrome(parentUid) {
    var listEl = lists[parentUid];
    if (!listEl) {
      return;
    }
    var loc = window.BuilderTree.locate(tree, parentUid);
    if (!loc) {
      return;
    }
    var oldEmpty = childByClass(listEl, 'builder-canvas-empty');
    if (oldEmpty) {
      listEl.removeChild(oldEmpty);
    }
    var oldAdd = childByClass(listEl, 'builder-block-add');
    if (oldAdd) {
      listEl.removeChild(oldAdd);
    }
    if (!interactive) {
      return;
    }
    var def = window.BuilderRegistry.getBlockType(loc.node.type);
    var unrestricted;
    if (def) {
      unrestricted = def.allowsChildren && def.allowedChildTypes.length === 0;
    } else {
      unrestricted = window.BuilderTree.isStructuralName(loc.node.type) && loc.node.type !== TYPE_CONTAINER;
    }
    if (!unrestricted) {
      return;
    }
    if (loc.node.children.length === 0) {
      var empty = document.createElement('div');
      empty.className = 'builder-canvas-empty';
      var text = document.createElement('span');
      text.textContent = STR_EMPTY;
      empty.appendChild(text);
      empty.appendChild(makeAffordance(parentUid));
      listEl.appendChild(empty);
    } else {
      listEl.appendChild(makeAffordance(parentUid));
    }
  }

  function createBlockEl(node, depth) {
    var def = window.BuilderRegistry.getBlockType(node.type);
    var isRoot = depth === ROOT_DEPTH;

    var el = document.createElement('div');
    el.className = 'builder-block' + (isRoot ? ' builder-block-root' : '');
    el.dataset.uid = node.uid;
    el.dataset.type = node.type;
    el.dataset.depth = String(depth);

    if (interactive && !isRoot) {
      var handle = document.createElement('span');
      handle.className = 'builder-block-handle';
      handle.title = STR_DRAG;
      handle.innerHTML = '<i class="ti ti-grip-vertical"></i>';
      el.appendChild(handle);
    }

    var label = document.createElement('span');
    label.className = 'builder-block-label';
    var icon = document.createElement('i');
    icon.className = 'ti ' + (def ? def.icon : 'ti-help-circle');
    label.appendChild(icon);
    var labelText = document.createElement('span');
    labelText.textContent = def ? def.label : node.type;
    label.appendChild(labelText);
    el.appendChild(label);
    if (node.type === TYPE_COLUMN) {
      el.classList.add('builder-col');
    }

    var contentEl = document.createElement('div');
    contentEl.className = 'builder-block-content';
    el.appendChild(contentEl);
    runTypeRender(def, node, contentEl);

    els[node.uid] = el;

    var allowsKids = def ? def.allowsChildren : (window.BuilderTree.isStructuralName(node.type) || node.children.length > 0);
    if (allowsKids) {
      var listEl = document.createElement('div');
      listEl.className = 'builder-block-children';
      listEl.dataset.parentUid = node.uid;
      if (node.type === TYPE_CONTAINER) {
        listEl.classList.add('builder-children-horizontal');
      }
      el.appendChild(listEl);
      lists[node.uid] = listEl;
      for (var i = 0; i < node.children.length; i++) {
        listEl.appendChild(createBlockEl(node.children[i], depth + 1));
      }
      refreshListChrome(node.uid);
      ensureSortable(node.uid);
    }

    return el;
  }

  function setDepthAttrs(node, depth) {
    var el = els[node.uid];
    if (el) {
      el.dataset.depth = String(depth);
    }
    for (var i = 0; i < node.children.length; i++) {
      setDepthAttrs(node.children[i], depth + 1);
    }
  }

  /* Move/create elements so a children list matches the tree, without
   * recreating elements whose uid persists (their type render() must not
   * run again). Elements whose uid left the tree are destroyed. */
  function syncListChildren(parentUid) {
    var loc = window.BuilderTree.locate(tree, parentUid);
    var listEl = lists[parentUid];
    if (!loc || !listEl) {
      return;
    }
    var i;
    var desired = [];
    for (i = 0; i < loc.node.children.length; i++) {
      desired.push(loc.node.children[i].uid);
    }
    for (i = 0; i < loc.node.children.length; i++) {
      var child = loc.node.children[i];
      var el = els[child.uid] || createBlockEl(child, loc.depth + 1);
      listEl.appendChild(el);
    }
    var extras = [];
    var blocks = directBlocks(listEl);
    for (i = 0; i < blocks.length; i++) {
      if (desired.indexOf(blocks[i].dataset.uid) === -1) {
        extras.push(blocks[i]);
      }
    }
    for (i = 0; i < extras.length; i++) {
      var uid = extras[i].dataset.uid;
      if (window.BuilderTree.locate(tree, uid)) {
        extras[i].parentNode.removeChild(extras[i]);
      } else {
        destroyDetached(extras[i]);
      }
    }
    setDepthAttrs(loc.node, loc.depth);
    refreshListChrome(parentUid);
    ensureSortable(parentUid);
    if (selectedUid) {
      positionToolbar();
    }
  }

  function destroyDetached(el) {
    var blocks = [el];
    var descendants = el.querySelectorAll ? el.querySelectorAll('.builder-block') : [];
    for (var i = 0; i < descendants.length; i++) {
      blocks.push(descendants[i]);
    }
    for (var j = 0; j < blocks.length; j++) {
      var uid = blocks[j].dataset.uid;
      if (sortables[uid]) {
        try {
          sortables[uid].destroy();
        } catch (e) {}
        delete sortables[uid];
      }
      if (uid && !window.BuilderTree.locate(tree, uid)) {
        delete els[uid];
      }
    }
    if (selectedUid && !document.contains(els[selectedUid])) {
      clearSelection();
    }
    if (el.parentNode) {
      el.parentNode.removeChild(el);
    }
    for (var k in lists) {
      if (lists.hasOwnProperty(k) && !window.BuilderTree.locate(tree, k)) {
        delete lists[k];
      }
    }
  }

  /* ===== Section: Sortable ===== */

  function ensureSortable(parentUid) {
    var listEl = lists[parentUid];
    if (!listEl) {
      return;
    }
    if (sortables[parentUid]) {
      return;
    }
    if (!interactive || typeof Sortable === 'undefined') {
      return;
    }
    var loc = window.BuilderTree.locate(tree, parentUid);
    var horizontal = !!loc && loc.node.type === TYPE_CONTAINER;
    sortables[parentUid] = new Sortable(listEl, {
      group: { name: 'builder-' + parentUid, pull: false, put: false },
      handle: '.builder-block-handle',
      draggable: '.builder-block',
      direction: horizontal ? 'horizontal' : 'vertical',
      animation: 150,
      ghostClass: 'builder-canvas-drag-ghost',
      chosenClass: 'builder-canvas-drag-chosen',
      forceFallback: false,
      onEnd: function (evt) {
        var item = evt.item;
        var uid = item && item.dataset ? item.dataset.uid : null;
        if (!uid) {
          return;
        }
        var order = directBlocks(listEl);
        var newIndex = order.indexOf(item);
        if (newIndex === -1) {
          newIndex = (typeof evt.newIndex === 'number') ? evt.newIndex : 0;
        }
        window.BuilderCanvas.moveBlock(uid, parentUid, newIndex);
        syncListChildren(parentUid);
        updateToolbar();
      }
    });
  }

  function destroySortable(parentUid) {
    if (sortables[parentUid]) {
      try {
        sortables[parentUid].destroy();
      } catch (e) {}
      delete sortables[parentUid];
    }
  }

  /* ===== Section: selection + toolbar ===== */

  function currentSelection() {
    if (!selectedUid) {
      return null;
    }
    var loc = window.BuilderTree.locate(tree, selectedUid);
    if (!loc) {
      return null;
    }
    return { uid: selectedUid, node: loc.node, el: els[selectedUid] || null, loc: loc };
  }

  function select(uid) {
    if (!interactive) {
      return;
    }
    if (uid === selectedUid) {
      return;
    }
    var loc = window.BuilderTree.locate(tree, uid);
    if (!loc) {
      return;
    }
    if (selectedUid && els[selectedUid]) {
      els[selectedUid].classList.remove('builder-block-selected');
    }
    selectedUid = uid;
    var el = els[uid];
    if (el) {
      el.classList.add('builder-block-selected');
    }
    updateToolbar();
    emit('builder:selection-changed', { uid: uid, node: deepCopy(loc.node), el: el || null });
  }

  function clearSelection(silent) {
    if (selectedUid && els[selectedUid]) {
      els[selectedUid].classList.remove('builder-block-selected');
    }
    selectedUid = null;
    hideToolbar();
    if (!silent) {
      emit('builder:selection-changed', { uid: null, node: null, el: null });
    }
  }

  function paintFormatState() {
    if (!toolbarEl) {
      return;
    }
    var btns = toolbarEl.querySelectorAll('[data-act="format"]');
    for (var i = 0; i < btns.length; i++) {
      var on = !!formatState[btns[i].dataset.id];
      btns[i].classList.toggle('builder-toolbar-active', on);
    }
  }

  function ensureToolbar() {
    if (toolbarEl) {
      return toolbarEl;
    }
    toolbarEl = document.createElement('div');
    toolbarEl.className = 'builder-toolbar';
    toolbarEl.classList.add('builder-is-hidden');
    toolbarEl.addEventListener('mousedown', function (e) {
      e.preventDefault();
    });
    toolbarEl.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-act]') : null;
      if (!btn) {
        return;
      }
      e.stopPropagation();
      onToolbarAction(btn);
    });
    return toolbarEl;
  }

  function hideToolbar() {
    if (toolbarEl) {
      toolbarEl.classList.add('builder-is-hidden');
    }
  }

  function positionToolbar() {
    if (!selectedUid || !toolbarEl) {
      return;
    }
    var el = els[selectedUid];
    if (!el || !document.contains(el)) {
      hideToolbar();
      return;
    }
    var parent = mountEl.offsetParent || mountEl.parentNode;
    if (toolbarEl.parentNode !== parent) {
      parent.appendChild(toolbarEl);
    }
    toolbarEl.classList.remove('builder-is-hidden');
    var pRect = parent.getBoundingClientRect();
    var sRect = el.getBoundingClientRect();
    var th = toolbarEl.offsetHeight || 40;
    var tw = toolbarEl.offsetWidth || 200;
    var scrollTop = parent.scrollTop || 0;
    var top = sRect.top - pRect.top + scrollTop - th - 8;
    if (top < scrollTop) {
      top = sRect.bottom - pRect.top + scrollTop + 8;
    }
    var left = sRect.left - pRect.left + (parent.scrollLeft || 0);
    var maxLeft = pRect.width - tw;
    if (left > maxLeft) {
      left = Math.max(0, maxLeft);
    }
    if (left < 0) {
      left = 0;
    }
    toolbarEl.style.cssText = 'top:' + top + 'px;left:' + left + 'px;'; /* dynamic geometry */
  }

  function splitCurrent(sel) {
    if (sel.node.type === TYPE_CONTAINER) {
      var count = 0;
      for (var i = 0; i < sel.node.children.length; i++) {
        if (sel.node.children[i].type === TYPE_COLUMN) {
          count++;
        }
      }
      return count;
    }
    return 1;
  }

  function updateToolbar() {
    ensureToolbar();
    if (!interactive || !selectedUid) {
      hideToolbar();
      return;
    }
    var sel = currentSelection();
    if (!sel) {
      hideToolbar();
      return;
    }
    var def = window.BuilderRegistry.getBlockType(sel.node.type);
    var isRoot = sel.loc.depth === ROOT_DEPTH;
    var parentEff = sel.loc.parent ? window.BuilderTree.effParent(sel.loc.parent) : null;
    var html = '';
    var showInsert = false;
    var showFormat = false;
    var showSplit = false;
    var showDelete = false;
    var showSettings = false;

    if (def && typeof def.renderSettingsPanel === 'function') {
      showSettings = true;
      html += '<span class="builder-toolbar-group"><button type="button" class="builder-toolbar-btn" data-act="settings" title="' + escapeHtml(STR_SETTINGS) + '" aria-label="' + escapeHtml(STR_SETTINGS) + '"><i class="ti ti-settings"></i></button></span>';
    }
    if (!isRoot && parentEff && parentEff.allows && parentEff.childTypes.length === 0 && window.BuilderTree.countNodes(tree) < MAX_BLOCKS) {
      showInsert = true;
      html += '<span class="builder-toolbar-group"><button type="button" class="builder-toolbar-btn" data-act="insert" title="' + escapeHtml(STR_ADD) + '"><i class="ti ti-plus"></i></button></span>';
    }
    if (def && def.supportsTextFormatting && formatOrder.length > 0) {
      showFormat = true;
      html += '<span class="builder-toolbar-group">';
      for (var f = 0; f < formatOrder.length; f++) {
        var cmd = formatCommands[formatOrder[f]];
        var pressed = formatState[cmd.id] ? ' builder-toolbar-active' : '';
        html += '<button type="button" class="builder-toolbar-btn' + pressed + '" data-act="format" data-id="' + escapeHtml(cmd.id) + '" title="' + escapeHtml(cmd.title) + '"><i class="ti ' + escapeHtml(cmd.icon) + '"></i></button>';
      }
      html += '</span>';
    }
    if (!isRoot && sel.node.type !== TYPE_COLUMN) {
      showSplit = true;
      var current = splitCurrent(sel);
      var deepest = window.BuilderTree.deepestDepth(sel.node, sel.loc.depth);
      html += '<span class="builder-toolbar-group builder-toolbar-split">';
      for (var n = 1; n <= 4; n++) {
        var disabled = '';
        if (sel.node.type !== TYPE_CONTAINER && n !== 1 && deepest + 2 > MAX_DEPTH) {
          disabled = ' disabled';
        }
        var active = (n === current) ? ' builder-toolbar-active' : '';
        html += '<button type="button" class="builder-toolbar-btn' + active + '" data-act="split" data-n="' + n + '"' + disabled + '>' + n + '</button>';
      }
      html += '</span>';
    }
    if (!isRoot && sel.node.type !== TYPE_COLUMN) {
      showDelete = true;
      html += '<span class="builder-toolbar-group"><button type="button" class="builder-toolbar-btn" data-act="delete" title="' + escapeHtml(STR_DELETE) + '"><i class="ti ti-trash"></i></button></span>';
    }
    if (!showInsert && !showFormat && !showSplit && !showDelete && !showSettings) {
      hideToolbar();
      return;
    }
    if (html !== toolbarHtmlCache) {
      toolbarEl.innerHTML = html;
      toolbarHtmlCache = html;
    }
    positionToolbar();
  }

  function onToolbarAction(btn) {
    var sel = currentSelection();
    if (!sel) {
      return;
    }
    var act = btn.dataset.act;
    if (act === 'settings') {
      emit('builder:settings-requested', { uid: sel.uid });
      return;
    }
    if (act === 'insert') {
      emit('builder:insert-requested', { parentUid: sel.loc.parent.uid, index: sel.loc.index + 1, anchorEl: btn });
      return;
    }
    if (act === 'format') {
      var cmd = formatCommands[btn.dataset.id];
      if (!cmd) {
        return;
      }
      emit('builder:format', { command: cmd.id, value: cmd.value, uid: sel.uid, node: deepCopy(sel.node) });
      return;
    }
    if (act === 'split') {
      applySplit(parseInt(btn.dataset.n, 10));
      return;
    }
    if (act === 'delete') {
      Swal.fire({
        title: STR_DEL_TITLE,
        text: STR_DEL_TEXT,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: STR_DEL_OK,
        cancelButtonText: STR_CANCEL,
        customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined),
        focusCancel: true
      }).then(function (result) {
        if (result && result.isConfirmed) {
          window.BuilderCanvas.removeBlock(sel.uid);
          clearSelection();
        }
      });
    }
  }

  /* ===== Section: column split / unsplit ===== */

  function columnsWithContent(containerNode, fromIndex) {
    var busy = [];
    for (var i = fromIndex; i < containerNode.children.length; i++) {
      var col = containerNode.children[i];
      if (col.children && col.children.length > 0) {
        busy.push(col);
      }
    }
    return busy;
  }

  function confirmReduce() {
    return new Promise(function (resolve) {
      Swal.fire({
        title: STR_COL_TITLE,
        text: STR_COL_TEXT,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: STR_DEL_OK,
        cancelButtonText: STR_CANCEL,
        customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined),
        focusCancel: true
      }).then(function (result) {
        resolve(!!(result && result.isConfirmed));
      });
    });
  }

  function applySplit(n) {
    var sel = currentSelection();
    if (!sel || !needEdit()) {
      return;
    }
    if (sel.node.type === TYPE_COLUMN || sel.loc.depth === ROOT_DEPTH) {
      return;
    }
    if (n < 1 || n > 4) {
      return;
    }
    if (sel.node.type === TYPE_CONTAINER) {
      adjustColumns(sel, n);
      return;
    }
    if (n === 1) {
      return;
    }
    var height = window.BuilderTree.subtreeHeight(sel.node);
    if (sel.loc.depth + height + 2 > MAX_DEPTH) {
      fail(V_TOO_DEEP);
      return;
    }
    var nodeParents = window.BuilderTree.effParentTypes(sel.node.type);
    if (nodeParents.length > 0 && nodeParents.indexOf(TYPE_COLUMN) === -1) {
      fail(V_BAD_PLACE);
      return;
    }
    var parent = sel.loc.parent;
    var msg = window.BuilderTree.checkInsert(tree, TYPE_CONTAINER, parent, sel.loc.depth - 1, 1 + n, 2 + height);
    if (msg) {
      fail(msg);
      return;
    }
    var container = makeContainer(n);
    parent.children[sel.loc.index] = container;
    container.children[0].children.push(sel.node);
    syncListChildren(parent.uid);
    select(sel.uid);
    mutated('split');
  }

  function adjustColumns(sel, n) {
    var container = sel.node;
    var current = splitCurrent(sel);
    if (n === current) {
      return;
    }
    var i;
    if (n > current) {
      if (window.BuilderTree.countNodes(tree) + (n - current) > MAX_BLOCKS) {
        fail(V_TOO_MANY);
        return;
      }
      for (i = current; i < n; i++) {
        container.children.push(makeColumn());
      }
      syncListChildren(container.uid);
      updateToolbar();
      mutated('split');
      return;
    }
    var busy = columnsWithContent(container, n);
    var proceed = function () {
      var parentLoc = window.BuilderTree.locate(tree, container.uid);
      if (!parentLoc) {
        return;
      }
      if (n === 1) {
        var survivors = container.children[0].children.slice();
        var pe = window.BuilderTree.effParent(parentLoc.parent);
        var newLen = parentLoc.parent.children.length - 1 + survivors.length;
        if (pe && (newLen < pe.min || (pe.max !== 0 && newLen > pe.max))) {
          fail(V_COUNT);
          return;
        }
        for (var s = 0; s < survivors.length; s++) {
          if (window.BuilderTree.typeKnown(survivors[s].type)) {
            if (pe && pe.childTypes.length > 0 && pe.childTypes.indexOf(survivors[s].type) === -1) {
              fail(V_BAD_PLACE);
              return;
            }
            var spa = window.BuilderTree.effParentTypes(survivors[s].type);
            if (spa.length > 0 && spa.indexOf(parentLoc.parent.type) === -1) {
              fail(V_BAD_PLACE);
              return;
            }
          }
        }
        var args = [parentLoc.index, 1].concat(survivors);
        Array.prototype.splice.apply(parentLoc.parent.children, args);
        delete els[container.uid];
        delete lists[container.uid];
        destroySortable(container.uid);
        syncListChildren(parentLoc.parent.uid);
        if (survivors.length > 0) {
          select(survivors[0].uid);
        } else {
          clearSelection();
        }
        mutated('unsplit');
      } else {
        container.children.splice(n, container.children.length - n);
        syncListChildren(container.uid);
        updateToolbar();
        mutated('split');
      }
    };
    if (busy.length > 0) {
      confirmReduce().then(function (ok) {
        if (ok) {
          proceed();
        }
      });
      return;
    }
    proceed();
  }

  /* ===== Section: public mutations ===== */

  function insertBlock(node, parentUid, index) {
    if (!needEdit()) {
      throw new Error(STR_READONLY);
    }
    if (!node || typeof node !== 'object' || typeof node.type !== 'string') {
      fail(V_UNKNOWN_TYPE);
      throw new Error(V_UNKNOWN_TYPE);
    }
    var fresh = deepCopy(node);
    if (typeof fresh.uid !== 'string' || !/^[0-9a-f]{32}$/.test(fresh.uid) || window.BuilderTree.locate(tree, fresh.uid)) {
      fresh.uid = window.BuilderCore.createUid();
    }
    if (!Array.isArray(fresh.children)) {
      fresh.children = [];
    }
    if (typeof fresh.data !== 'object' || fresh.data === null || Array.isArray(fresh.data)) {
      fresh.data = {};
    }
    var parentLoc = parentUid === null || parentUid === undefined ? null : window.BuilderTree.locate(tree, parentUid);
    if (!parentLoc) {
      fail(V_BAD_ROOT);
      throw new Error(V_BAD_ROOT);
    }
    var msg = window.BuilderTree.checkInsert(tree, fresh.type, parentLoc.node, parentLoc.depth, window.BuilderTree.subtreeSize(fresh), window.BuilderTree.subtreeHeight(fresh));
    if (msg) {
      fail(msg);
      throw new Error(msg);
    }
    var at = (typeof index === 'number') ? Math.max(0, Math.min(Math.floor(index), parentLoc.node.children.length)) : parentLoc.node.children.length;
    parentLoc.node.children.splice(at, 0, fresh);
    syncListChildren(parentLoc.node.uid);
    mutated('insert');
    return fresh.uid;
  }

  function removeBlock(uid) {
    if (!needEdit()) {
      return false;
    }
    var loc = window.BuilderTree.locate(tree, uid);
    if (!loc) {
      return false;
    }
    if (loc.depth === ROOT_DEPTH) {
      fail(V_BAD_ROOT);
      return false;
    }
    var pe = window.BuilderTree.effParent(loc.parent);
    if (pe && loc.parent.children.length - 1 < pe.min) {
      fail(V_COUNT);
      return false;
    }
    var gone = loc.parent.children[loc.index];
    loc.parent.children.splice(loc.index, 1);
    var doomed = [];
    (function collect(node) {
      doomed.push(node.uid);
      for (var i = 0; i < node.children.length; i++) {
        collect(node.children[i]);
      }
    })(gone);
    var el = els[uid];
    if (el) {
      destroyDetached(el);
    }
    for (var i = 0; i < doomed.length; i++) {
      delete els[doomed[i]];
    }
    if (selectedUid && !window.BuilderTree.locate(tree, selectedUid)) {
      clearSelection();
    }
    syncListChildren(loc.parent.uid);
    mutated('remove');
    return true;
  }

  function moveBlock(uid, parentUid, index) {
    if (!needEdit()) {
      return false;
    }
    var loc = window.BuilderTree.locate(tree, uid);
    if (!loc) {
      return false;
    }
    if (loc.depth === ROOT_DEPTH || parentUid === null || parentUid === undefined) {
      fail(V_BAD_ROOT);
      return false;
    }
    var target = window.BuilderTree.locate(tree, parentUid);
    if (!target) {
      fail(V_BAD_ROOT);
      return false;
    }
    var te = window.BuilderTree.effParent(target.node);
    if (!te || !te.allows) {
      fail(!te ? V_BAD_PLACE : V_COUNT);
      return false;
    }
    var height = window.BuilderTree.subtreeHeight(loc.node);
    if (target.depth + 1 + height > MAX_DEPTH) {
      fail(V_TOO_DEEP);
      return false;
    }
    if (window.BuilderTree.typeKnown(loc.node.type)) {
      if (te.childTypes.length > 0 && te.childTypes.indexOf(loc.node.type) === -1) {
        fail(V_BAD_PLACE);
        return false;
      }
      var allowedParents = window.BuilderTree.effParentTypes(loc.node.type);
      if (allowedParents.length > 0 && allowedParents.indexOf(target.node.type) === -1) {
        fail(V_BAD_PLACE);
        return false;
      }
      if (window.BuilderTree.isStructuralRootName(loc.node.type)) {
        fail(V_OFF_ROOT);
        return false;
      }
    }
    var sameParent = target.node.uid === (loc.parent ? loc.parent.uid : null);
    var siblings = target.node.children;
    var at = (typeof index === 'number') ? Math.max(0, Math.min(Math.floor(index), siblings.length)) : siblings.length;
    if (sameParent) {
      var cur = loc.index;
      var without = siblings.length - 1;
      if (at > without) {
        at = without;
      }
      if (at === cur) {
        return true;
      }
    } else {
      if (siblings.length + 1 < te.min || (te.max !== 0 && siblings.length + 1 > te.max)) {
        fail(V_COUNT);
        return false;
      }
    }
    loc.parent.children.splice(loc.index, 1);
    var freshTarget = window.BuilderTree.locate(tree, parentUid);
    var list = freshTarget.node.children;
    var putAt = Math.max(0, Math.min(at, list.length));
    list.splice(putAt, 0, loc.node);
    setDepthAttrs(loc.node, freshTarget.depth + 1);
    if (!sameParent) {
      syncListChildren(loc.parent ? loc.parent.uid : parentUid);
    }
    syncListChildren(parentUid);
    mutated('move');
    return true;
  }

  function updateBlockData(uid, data, opts) {
    if (!needEdit()) {
      return false;
    }
    var loc = window.BuilderTree.locate(tree, uid);
    if (!loc) {
      return false;
    }
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
      throw new Error('BuilderCanvas: data must be an object');
    }
    loc.node.data = deepCopy(data);
    if (!(opts && opts.skipRefresh)) {
      refreshContent(uid);
    }
    mutated('data');
    return true;
  }

  function refreshBlock(uid) {
    var loc = window.BuilderTree.locate(tree, uid);
    if (!loc) {
      return false;
    }
    refreshContent(uid);
    return true;
  }

  /* ===== Section: read-only ===== */

  function stripInteractive() {
    interactive = false;
    hideToolbar();
    if (toolbarEl && toolbarEl.parentNode) {
      toolbarEl.parentNode.removeChild(toolbarEl);
      toolbarEl = null;
      toolbarHtmlCache = '';
    }
    clearSelection(true);
    var uids = Object.keys(sortables);
    for (var i = 0; i < uids.length; i++) {
      destroySortable(uids[i]);
    }
    var chrome = mountEl ? mountEl.querySelectorAll('.builder-block-handle, .builder-block-add, .builder-canvas-empty') : [];
    for (var j = 0; j < chrome.length; j++) {
      chrome[j].parentNode.removeChild(chrome[j]);
    }
  }

  /* ===== Section: controller ===== */

  function render(mount, newTree, canvas) {
    mountEl = mount;
    tree = newTree;
    interactive = canEdit();
    mountEl.innerHTML = '';
    els = {};
    lists = {};
    sortables = {};
    selectedUid = null;
    ensureToolbar();
    hideToolbar();
    for (var i = 0; i < tree.length; i++) {
      mountEl.appendChild(createBlockEl(tree[i], ROOT_DEPTH));
    }
    if (!renderBound) {
      renderBound = true;
      mountEl.addEventListener('click', function (e) {
        if (!interactive) {
          return;
        }
        var t = e.target;
        var blk = t.closest ? t.closest('.builder-block') : null;
        if (blk && mountEl.contains(blk)) {
          select(blk.dataset.uid);
        } else {
          clearSelection();
        }
      });
    }
    if (!listenersBound) {
      listenersBound = true;
      var reposition = function () {
        if (selectedUid) {
          positionToolbar();
        }
      };
      document.addEventListener('scroll', reposition, true);
      window.addEventListener('resize', reposition);
      document.addEventListener('builder:saved', function () {
        if (!canEdit() && interactive) {
          stripInteractive();
        } else if (selectedUid) {
          positionToolbar();
        }
      });
    }
  }

  function getTree() {
    return deepCopy(tree);
  }

  window.BuilderCanvas = {
    getTree: getTree,
    getNode: function (uid) {
      var loc = window.BuilderTree.locate(tree, uid);
      return loc ? deepCopy(loc.node) : null;
    },
    getSelected: function () {
      if (!selectedUid) {
        return null;
      }
      var loc = window.BuilderTree.locate(tree, selectedUid);
      if (!loc) {
        return null;
      }
      return { uid: selectedUid, node: deepCopy(loc.node), el: els[selectedUid] || null };
    },
    getCanvasElement: function () {
      return mountEl;
    },
    getToolbarElement: function () {
      return toolbarEl;
    },
    getBlockElement: function (uid) {
      return els[uid] || null;
    },
    select: select,
    clearSelection: function () {
      clearSelection();
    },
    insertBlock: insertBlock,
    removeBlock: removeBlock,
    moveBlock: moveBlock,
    updateBlockData: updateBlockData,
    refreshBlock: refreshBlock,
    registerFormatCommand: function (def) {
      if (!def || typeof def !== 'object' || Array.isArray(def)) {
        throw new Error('BuilderCanvas: format command must be an object');
      }
      if (typeof def.id !== 'string' || !FORMAT_ID_RE.test(def.id)) {
        throw new Error('BuilderCanvas: invalid format command id');
      }
      if (formatCommands[def.id]) {
        throw new Error('BuilderCanvas: duplicate format command: ' + def.id);
      }
      if (typeof def.icon !== 'string' || typeof def.title !== 'string') {
        throw new Error('BuilderCanvas: format command needs icon and title strings');
      }
      formatCommands[def.id] = { id: def.id, icon: def.icon, title: def.title, group: def.group, value: def.value };
      formatOrder.push(def.id);
      if (selectedUid) {
        updateToolbar();
      }
    },
    setFormatState: function (state) {
      var next = {};
      if (state && typeof state === 'object') {
        for (var id in state) {
          if (state.hasOwnProperty(id) && formatCommands[id]) {
            next[id] = !!state[id];
          }
        }
      }
      formatState = next;
      paintFormatState();
    }
  };

  /* The built-in engine installs itself only for data-engine="blocks" (or
   * when the attribute is absent). A future engine file installs the same way
   * when the attribute holds its own name. */
  var rootEl = document.getElementById('builder-root') || { dataset: {} };
  var engine = rootEl.dataset ? rootEl.dataset.engine : null;
  if ((engine === 'blocks' || !engine) && window.BuilderShell && typeof window.BuilderShell.setCanvasController === 'function') {
    window.BuilderShell.setCanvasController({ render: render, getTree: getTree });
  }
})();
