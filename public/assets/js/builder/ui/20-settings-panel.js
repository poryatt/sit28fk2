/* Visual page builder — block settings panel container & lifecycle (Part 3).
 * Opens on builder:selection-changed for blocks whose type defines
 * renderSettingsPanel. Closes on deselection, outside click, Escape, or
 * its own close button. Calls the type's hook once per open with a fresh
 * container. Never builds any settings form itself. Pure chrome.
 * Exposes exactly one global: window.BuilderSettingsPanel.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "تنظیمات ";
  var STR_2 = "بستن";
  var STR_3 = "خطا در بارگذاری تنظیمات این بلاک";


  var STR_SETTINGS_PREFIX = STR_1;
  var STR_CLOSE = STR_2;
  var STR_ERROR_LOAD = STR_3;

  var panelEl = null;
  var headerEl = null;
  var headerIconEl = null;
  var headerTextEl = null;
  var bodyEl = null;
  var openUid = null;
  var isOpen = false;
  var mediaQuery = null;
  var resizeHandler = null;
  var outsideClickHandler = null;
  var escapeHandler = null;

  function createPanel() {
    panelEl = document.createElement('div');
    panelEl.className = 'builder-settings-panel';
    panelEl.classList.add('builder-is-hidden');

    var header = document.createElement('div');
    header.className = 'builder-settings-panel-header';
    headerEl = document.createElement('div');
    headerEl.className = 'builder-settings-panel-title';
    headerIconEl = document.createElement('i');
    headerIconEl.className = 'builder-settings-panel-icon';
    headerIconEl.setAttribute('aria-hidden', 'true');
    headerTextEl = document.createElement('span');
    headerTextEl.className = 'builder-settings-panel-title-text';
    headerEl.appendChild(headerIconEl);
    headerEl.appendChild(headerTextEl);
    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'builder-settings-panel-close';
    closeBtn.title = STR_CLOSE;
    closeBtn.setAttribute('aria-label', STR_CLOSE);
    closeBtn.innerHTML = '<i class="ti ti-x"></i>';
    closeBtn.addEventListener('click', close);
    header.appendChild(headerEl);
    header.appendChild(closeBtn);
    panelEl.appendChild(header);

    bodyEl = document.createElement('div');
    bodyEl.className = 'builder-settings-panel-body';
    panelEl.appendChild(bodyEl);

    // Detached until the first open: nothing is mounted into the page
    // at script load. open() appends panelEl to #builder-root on demand.
    mediaQuery = window.matchMedia('(min-width: 768px)');
    if (mediaQuery.addEventListener) {
      mediaQuery.addEventListener('change', onResize);
    } else {
      mediaQuery.addListener(onResize);
    }
    resizeHandler = onResize;
    window.addEventListener('resize', resizeHandler);
  }

  function onResize() {
    if (!isOpen) {
      return;
    }
    updateLayoutClass();
  }

  function updateLayoutClass() {
    if (!panelEl) {
      return;
    }
    var isWide = window.matchMedia('(min-width: 768px)').matches;
    if (isWide) {
      panelEl.classList.add('builder-settings-panel--drawer');
      panelEl.classList.remove('builder-settings-panel--sheet');
    } else {
      panelEl.classList.add('builder-settings-panel--sheet');
      panelEl.classList.remove('builder-settings-panel--drawer');
    }
  }

  function open(uid, node, type) {
    if (!panelEl) {
      createPanel();
    }
    // First open mounts the pre-built (detached) node as the last child
    // of #builder-root, never document.body, so the theme custom
    // properties defined there cascade into the panel.
    if (!panelEl.parentNode) {
      var root = document.getElementById('builder-root');
      if (!root) {
        return;
      }
      root.appendChild(panelEl);
    }
    if (openUid === uid && isOpen) {
      return;
    }
    openUid = uid;
    isOpen = true;
    // Header text and icon are read fresh from the registry on every open,
    // never cached from a previous open.
    headerTextEl.textContent = STR_SETTINGS_PREFIX + type.label;
    headerIconEl.className = 'builder-settings-panel-icon ti ' + type.icon;
    bodyEl.innerHTML = '';

    try {
      type.renderSettingsPanel(node, bodyEl);
    } catch (e) {
      bodyEl.textContent = STR_ERROR_LOAD;
    }

    updateLayoutClass();
    panelEl.classList.remove('builder-is-hidden');

    if (!outsideClickHandler) {
      outsideClickHandler = function (e) {
        if (!isOpen) {
          return;
        }
        var sel = window.BuilderCanvas ? window.BuilderCanvas.getSelected() : null;
        var blockEl = sel ? sel.el : null;
        var onToolbar = e.target && e.target.closest ? e.target.closest('.builder-toolbar, .builder-media-picker') : null;
        if (!onToolbar && !panelEl.contains(e.target) && (!blockEl || !blockEl.contains(e.target))) {
          close();
        }
      };
      document.addEventListener('mousedown', outsideClickHandler);
      document.addEventListener('pointerdown', outsideClickHandler);
    }
    if (!escapeHandler) {
      escapeHandler = function (e) {
        if (isOpen && e.key === 'Escape') {
          close();
        }
      };
      document.addEventListener('keydown', escapeHandler);
    }
  }

  function close() {
    if (!isOpen) {
      return;
    }
    isOpen = false;
    openUid = null;
    if (panelEl) {
      panelEl.classList.add('builder-is-hidden');
    }
    if (outsideClickHandler) {
      document.removeEventListener('mousedown', outsideClickHandler);
      document.removeEventListener('pointerdown', outsideClickHandler);
      outsideClickHandler = null;
    }
    if (escapeHandler) {
      document.removeEventListener('keydown', escapeHandler);
      escapeHandler = null;
    }
  }

  function onSelectionChanged(e) {
    var detail = e.detail || {};
    var uid = detail.uid;
    var node = detail.node;

    if (!uid || !node) {
      close();
      return;
    }

    var type = window.BuilderRegistry ? window.BuilderRegistry.getBlockType(node.type) : null;
    if (type && typeof type.renderSettingsPanel === 'function' && type.settingsOnSelect !== false) {
      open(uid, node, type);
    } else {
      close();
    }
  }

  // Exactly one panel DOM node for the page lifetime, built detached at
  // script load and reused on every open; exactly one selection listener.
  createPanel();
  document.addEventListener('builder:selection-changed', onSelectionChanged);
  document.addEventListener('builder:settings-requested', function (e) {
    var uid = e.detail && e.detail.uid;
    var node = uid && window.BuilderCanvas ? window.BuilderCanvas.getNode(uid) : null;
    var type = node && window.BuilderRegistry ? window.BuilderRegistry.getBlockType(node.type) : null;
    if (!type || typeof type.renderSettingsPanel !== 'function') { return; }
    if (isOpen && openUid === uid) { close(); return; }
    open(uid, node, type);
  });

  window.BuilderSettingsPanel = {
    isOpen: function () {
      return isOpen;
    },
    getOpenUid: function () {
      return openUid;
    }
  };
})();