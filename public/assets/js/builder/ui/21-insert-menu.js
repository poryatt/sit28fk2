/* Visual page builder — "+" insert dropdown (Part 3).
 * Opens on builder:insert-requested, lists all insertable (content-category)
 * block types from the registry with their registered label + icon. Clicking
 * an entry creates that type at the requested position via BuilderCanvas.
 * Closes on select, outside click, Escape, new insert-requested, or
 * builder:selection-changed. Pure chrome — knows nothing of specific types.
 * Exposes exactly one global: window.BuilderInsertMenu.
 */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "هنوز نوع بلاکی برای افزودن ثبت نشده است";
  var STR_2 = "خطا";


  var STR_EMPTY = STR_1;
  var STR_ERROR_TITLE = STR_2;

  var menuEl = null;
  var isOpen = false;
  var anchorParentUid = null;
  var currentAnchorEl = null;
  var resizeScrollHandler = null;
  var outsideClickHandler = null;
  var escapeHandler = null;
  var treeChangedHandler = null;

  function createMenu() {
    menuEl = document.createElement('div');
    menuEl.className = 'builder-insert-menu';
    menuEl.classList.add('builder-is-hidden');

    var root = document.getElementById('builder-root');
    if (root) {
      root.appendChild(menuEl);
    }

    resizeScrollHandler = function () {
      if (isOpen && currentAnchorEl) {
        positionMenu();
      }
    };
    window.addEventListener('resize', resizeScrollHandler);
    window.addEventListener('scroll', resizeScrollHandler, true);
  }

  function positionMenu() {
    if (!menuEl || !currentAnchorEl || !document.contains(currentAnchorEl)) {
      return;
    }
    var rect = currentAnchorEl.getBoundingClientRect();
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var menuW = menuEl.offsetWidth || 220;
    var menuH = menuEl.offsetHeight || 200;

    // Space actually available on each side, measured from the edge the
    // menu would be anchored to and extending toward the opposite edge of
    // the viewport - not the space on the anchor's own side, which is what
    // the previous version compared against by mistake.
    var spaceForRightAlign = rect.right;       // menu would span [rect.right - menuW, rect.right]
    var spaceForLeftAlign = vw - rect.left;     // menu would span [rect.left, rect.left + menuW]
    var spaceBelow = vh - rect.bottom;          // menu would span [rect.bottom + 4, rect.bottom + 4 + menuH]
    var spaceAbove = rect.top;                  // menu would span [rect.top - 4 - menuH, rect.top - 4]

    // Default: hang from the inline-start side (the right edge, under this
    // project's RTL) and extend toward inline-end; flip only if that side
    // truly does not fit and the other side has more room.
    var horizontal;
    if (spaceForRightAlign >= menuW || spaceForRightAlign >= spaceForLeftAlign) {
      horizontal = 'right:' + Math.max(0, vw - rect.right) + 'px;';
    } else {
      horizontal = 'left:' + Math.max(0, Math.min(rect.left, vw - menuW)) + 'px;';
    }

    // Default: open downward; flip upward only if downward truly does not
    // fit and upward has more room.
    var vertical;
    if (spaceBelow >= menuH || spaceBelow >= spaceAbove) {
      vertical = 'top:' + (rect.bottom + 4) + 'px;';
    } else {
      vertical = 'bottom:' + (vh - rect.top + 4) + 'px;';
    }
    menuEl.style.cssText = horizontal + vertical; /* dynamic geometry */
  }

  function buildMenuContent(types) {
    if (!menuEl) {
      return;
    }
    menuEl.innerHTML = '';
    if (!types || types.length === 0) {
      var emptyRow = document.createElement('div');
      emptyRow.className = 'builder-insert-menu-empty';
      emptyRow.textContent = STR_EMPTY;
      menuEl.appendChild(emptyRow);
      return;
    }
    for (var i = 0; i < types.length; i++) {
      var type = types[i];
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'builder-insert-menu-item';
      item.setAttribute('data-type', type.type);
      var icon = document.createElement('i');
      icon.className = 'ti ' + type.icon;
      item.appendChild(icon);
      var label = document.createElement('span');
      label.textContent = type.label;
      item.appendChild(label);
      item.addEventListener('click', (function (t) {
        return function () {
          insertType(t);
        };
      })(type));
      menuEl.appendChild(item);
    }
  }

  function insertType(type) {
    if (!window.BuilderCore || !window.BuilderCanvas) {
      return;
    }
    var parentUid = anchorParentUid;
    var index = menuEl ? parseInt(menuEl.getAttribute('data-index'), 10) : 0;
    try {
      var node = window.BuilderCore.createNode(type.type);
      var uid = window.BuilderCanvas.insertBlock(node, parentUid, index);
      window.BuilderCanvas.select(uid);
    } catch (err) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: STR_ERROR_TITLE, text: err.message });
      } else {
        alert(STR_ERROR_TITLE + ': ' + err.message);
      }
    } finally {
      close();
    }
  }

  function open(detail) {
    var parentUid = detail.parentUid;
    var index = detail.index;
    var anchorEl = detail.anchorEl;

    if (!document.contains(anchorEl)) {
      return;
    }

    if (isOpen) {
      close();
    }

    if (!menuEl) {
      createMenu();
    }

    anchorParentUid = parentUid;
    currentAnchorEl = anchorEl;
    isOpen = true;

    var types = window.BuilderRegistry ? window.BuilderRegistry.getInsertableBlockTypes() : [];
    buildMenuContent(types);

    menuEl.setAttribute('data-index', String(index));
    positionMenu();
    menuEl.classList.remove('builder-is-hidden');

    if (!outsideClickHandler) {
      outsideClickHandler = function (e) {
        if (!isOpen) {
          return;
        }
        if (!menuEl.contains(e.target) && !currentAnchorEl.contains(e.target)) {
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
    if (!treeChangedHandler) {
      treeChangedHandler = function () {
        if (isOpen && currentAnchorEl && !document.contains(currentAnchorEl)) {
          close();
        }
      };
      document.addEventListener('builder:tree-changed', treeChangedHandler);
    }
  }

  function close() {
    if (!isOpen) {
      return;
    }
    isOpen = false;
    anchorParentUid = null;
    currentAnchorEl = null;
    if (menuEl) {
      menuEl.classList.add('builder-is-hidden');
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
    if (treeChangedHandler) {
      document.removeEventListener('builder:tree-changed', treeChangedHandler);
      treeChangedHandler = null;
    }
  }

  function onInsertRequested(e) {
    var detail = e.detail || {};
    if (!detail.parentUid || !detail.anchorEl) {
      return;
    }
    open(detail);
  }

  function onSelectionChanged() {
    if (isOpen) {
      close();
    }
  }

  document.addEventListener('builder:insert-requested', onInsertRequested);
  document.addEventListener('builder:selection-changed', onSelectionChanged);

  window.BuilderInsertMenu = {
    isOpen: function () {
      return isOpen;
    },
    getAnchorParentUid: function () {
      return anchorParentUid;
    }
  };
})();