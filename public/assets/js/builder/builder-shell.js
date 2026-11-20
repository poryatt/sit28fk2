/* Visual page builder — shell wiring (Part 1).
 * Load/save plumbing for the builder screen plus the seam Part 2 plugs its
 * canvas renderer into. No block rendering here: #builder-canvas stays empty
 * until a canvas controller is installed via BuilderShell.setCanvasController.
 * Exposes exactly one global: window.BuilderShell.
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
  var STR_11 = "نشست منقضی شده";
  var STR_12 = "لطفاً دوباره وارد شوید.";
  var STR_13 = "ورود";
  var STR_14 = "شما اجازه‌ی ویرایش این بوم را ندارید.";
  var STR_15 = "منتشر شده";
  var STR_16 = "پیش‌نویس";
  var STR_17 = "قالب سایت";
  var STR_18 = "صفحه";
  var STR_19 = "لغو انتشار";
  var STR_20 = "انتشار";
  var STR_21 = "<div class=\"spinner-border\" role=\"status\"></div><div class=\"mt-2\">در حال بارگذاری بوم...</div>";
  var STR_22 = "<div class=\"d-flex align-items-center justify-content-center gap-2\" role=\"alert\"><i class=\"ti ti-alert-circle\" aria-hidden=\"true\"></i><span>خطا در بارگذاری بوم</span></div>";
  var STR_23 = "<button id=\"builder-retry\" class=\"btn btn-outline-secondary mt-3\">تلاش دوباره</button>";
  var STR_24 = "<div>بوم بارگذاری شد — نمایش و ویرایش بلاک‌ها در بخش بعدی اضافه می‌شود.</div>";
  var STR_25 = "خطا";
  var STR_26 = "عنوان بوم را بنویسید";
  var STR_27 = "عنوان نباید بیشتر از ۲۵۵ نویسه باشد";
  var STR_28 = "خطا در خواندن محتوای بوم";
  var STR_29 = "موفق";
  var STR_30 = " خطا)";
  var STR_31 = "پیش‌نمایش";
  var STR_32 = "تغییرات ذخیره شد. نمایش سایت هنوز ساخته نشده است.";


  var FA_DIGITS = [STR_1, STR_2, STR_3, STR_4, STR_5, STR_6, STR_7, STR_8, STR_9, STR_10];

  function toFa(value) {
    return String(value).replace(/[0-9]/g, function (d) {
      return FA_DIGITS[Number(d)];
    });
  }

  function $(id) {
    return document.getElementById(id);
  }

  function deepCopy(value) {
    return JSON.parse(JSON.stringify(value));
  }

  var root = $('builder-root');
  if (!root) {
    return;
  }

  var ds = root.dataset;
  var LOAD_URL = ds.loadUrl;
  var SAVE_URL = ds.saveUrl;
  var CSRF_TOKEN = ds.csrfToken;
  var LOGIN_URL = ds.loginUrl || '/login.php';
  var PAGES_EDIT = ds.canEdit === '1';
  var TEMPLATES_EDIT = ds.canEditTemplates === '1';
  var ACTIVE_TEMPLATE_ID = ds.activeTemplateId || '';

  var titleInput = $('builder-title');
  var canvasEl = $('builder-canvas');
  var stateEl = $('builder-canvas-state');
  var actionsEl = $('builder-actions');

  var state = {
    canvas: null,
    tree: null,
    dirty: false,
    canEdit: PAGES_EDIT,
    controller: null,
    controllerInstalled: false,
    treeLoaded: false,
    renderCalled: false,
    saving: false,
    titleTouched: false,
    blockCount: null
  };

  function emit(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { detail: detail }));
  }

  function showState(html) {
    if (!stateEl) {
      return;
    }
    stateEl.innerHTML = html;
    stateEl.classList.remove('builder-is-hidden');
    var retry = $('builder-retry');
    if (retry) {
      retry.addEventListener('click', function () {
        loadCanvas();
      });
    }
  }

  function hideState() {
    if (!stateEl) {
      return;
    }
    stateEl.innerHTML = '';
    stateEl.classList.add('builder-is-hidden');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* Session-expiry handling (§9.3): requireLogin() answers with a 302 to the
   * login page, so fetch follows the redirect and yields HTML, not JSON. */
  function readJsonResponse(response) {
    var contentType = response.headers.get('Content-Type') || '';
    if (response.status === 401 || response.redirected || contentType.indexOf('application/json') === -1) {
      return { expired: true };
    }
    return response.text().then(function (text) {
      try {
        return { expired: false, status: response.status, ok: response.ok, body: JSON.parse(text) };
      } catch (e) {
        return { expired: true };
      }
    });
  }

  function handleExpiredSession() {
    Swal.fire({
      title: STR_11,
      text: STR_12,
      icon: 'warning',
      confirmButtonText: STR_13
    }).then(function () {
      window.location.href = LOGIN_URL;
    });
  }

  /* Blank-canvas normalisation (§9.2): when the canvas has no id yet, fill
   * empty buildable_type / template_id from the descriptor attributes. */
  function normaliseCanvas(canvas) {
    if (canvas && canvas.id === null) {
      if (!canvas.buildable_type && ds.entryType) {
        canvas.buildable_type = ds.entryType;
      }
      if ((canvas.template_id === null || canvas.template_id === undefined || canvas.template_id === '') && ds.entryTemplateId) {
        canvas.template_id = parseInt(ds.entryTemplateId, 10);
      }
      if (canvas.template_id === '') {
        canvas.template_id = null;
      }
    }
    return canvas;
  }

  function updateHint() {
    var hint = $('builder-title-hint');
    if (!hint || !state.canvas) {
      return;
    }
    var isTemplate = state.canvas.buildable_type === 'template';
    var tid = Number(state.canvas.template_id);
    var active = Number(ACTIVE_TEMPLATE_ID);
    if (isTemplate && tid > 0 && active > 0 && tid === active) {
      hint.removeAttribute('hidden');
    } else {
      hint.setAttribute('hidden', 'hidden');
    }
  }

  function reevaluateEdit() {
    if (!state.canvas) {
      return;
    }
    var edit = PAGES_EDIT;
    if (state.canvas.buildable_type === 'template') {
      edit = PAGES_EDIT && TEMPLATES_EDIT;
    }
    state.canEdit = edit;
    if (!edit) {
      enterReadonly();
    }
  }

  function enterReadonly() {
    if (titleInput) {
      titleInput.disabled = true;
    }
    if (actionsEl && actionsEl.parentNode) {
      actionsEl.parentNode.removeChild(actionsEl);
      actionsEl = null;
    }
    var main = root.querySelector('.col-12.col-lg-9');
    if (main && !$('builder-readonly-banner')) {
      var banner = document.createElement('div');
      banner.id = 'builder-readonly-banner';
      banner.className = 'alert alert-info d-flex align-items-start gap-2';
      banner.setAttribute('role', 'status');
      var bannerIcon = document.createElement('i');
      bannerIcon.className = 'ti ti-lock';
      bannerIcon.setAttribute('aria-hidden', 'true');
      var bannerText = document.createElement('span');
      bannerText.textContent = STR_14;
      banner.appendChild(bannerIcon);
      banner.appendChild(bannerText);
      main.insertBefore(banner, main.firstChild);
    }
  }

  function countNodes(tree) {
    var count = 0;
    if (window.BuilderCore && typeof window.BuilderCore.walkTree === 'function') {
      window.BuilderCore.walkTree(tree, function () {
        count++;
      });
    } else if (Array.isArray(tree)) {
      count = tree.length;
    }
    return count;
  }

  /* Status / dirty badges: A's soft badge = icon + word (never color alone). */
  function setBadge(el, tone, icon, text) {
    el.className = 'badge-soft badge-soft-' + tone;
    el.textContent = '';
    var i = document.createElement('i');
    i.className = 'ti ' + icon;
    i.setAttribute('aria-hidden', 'true');
    el.appendChild(i);
    el.appendChild(document.createTextNode(text));
  }

  function paintSide() {
    var c = state.canvas;
    if (!c) {
      return;
    }
    var statusBadge = $('builder-status-badge');
    if (statusBadge) {
      if (c.status === 'published') {
        setBadge(statusBadge, 'success', 'ti-circle-check', STR_15);
      } else {
        setBadge(statusBadge, 'muted', 'ti-pencil', STR_16);
      }
    }
    var dirtyBadge = $('builder-dirty-badge');
    if (dirtyBadge) {
      dirtyBadge.hidden = !state.dirty;
    }
    var updated = $('builder-updated');
    if (updated) {
      updated.textContent = (c.updated_at_jalali === null || c.updated_at_jalali === undefined || c.updated_at_jalali === '-' || c.updated_at_jalali === '') ? '\u2014' : c.updated_at_jalali;
    }
    var typeEl = $('builder-type');
    if (typeEl) {
      typeEl.textContent = c.buildable_type === 'template' ? STR_17 : STR_18;
    }
    var slugEl = $('builder-slug');
    if (slugEl) {
      slugEl.textContent = (c.slug === null || c.slug === undefined || c.slug === '') ? '\u2014' : c.slug;
    }
    var countEl = $('builder-count');
    if (countEl) {
      var n = (state.blockCount !== null && state.blockCount !== undefined) ? state.blockCount : countNodes(state.tree);
      countEl.textContent = toFa(n);
    }
    var idEl = $('builder-id');
    if (idEl) {
      idEl.textContent = (c.id === null || c.id === undefined) ? '\u2014' : toFa(c.id);
    }
    paintPublishButton();
  }

  function paintPublishButton() {
    var btn = $('builder-btn-publish');
    if (!btn || !state.canvas) {
      return;
    }
    if (state.canvas.status === 'published') {
      btn.className = 'btn btn-outline-danger';
      btn.textContent = STR_19;
      btn.dataset.actionStatus = 'draft';
    } else {
      btn.className = 'btn btn-success';
      btn.textContent = STR_20;
      btn.dataset.actionStatus = 'published';
    }
  }

  function tryRender() {
    if (state.renderCalled || !state.controllerInstalled || !state.treeLoaded) {
      return;
    }
    if (!state.tree) {
      return;
    }
    state.renderCalled = true;
    hideState();
    var mountEl = canvasEl;
    var treeCopy = deepCopy(state.tree);
    var canvasCopy = deepCopy(state.canvas);
    emit('builder:loaded', { canvas: canvasCopy, tree: treeCopy });
    state.controller.render(mountEl, deepCopy(state.tree), deepCopy(state.canvas));
  }

  function loadCanvas() {
    showState(STR_21);
    return fetch(LOAD_URL, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(readJsonResponse)
      .then(function (res) {
        if (res.expired) {
          handleExpiredSession();
          emit('builder:error', { stage: 'load', status: 0, message: '' });
          return;
        }
        if (!res.ok || !res.body || res.body.success !== true || !res.body.data) {
          var message = (res.body && res.body.message) ? res.body.message : '';
          var html = STR_22;
          if (message) {
            html += '<div class="mt-2 small">' + escapeHtml(message) + '</div>';
          }
          html += STR_23;
          showState(html);
          emit('builder:error', { stage: 'load', status: res.status, message: message });
          return;
        }
        state.canvas = normaliseCanvas(res.body.data.canvas);
        state.tree = res.body.data.tree;
        state.blockCount = null;
        state.treeLoaded = true;
        if (titleInput && !state.titleTouched) {
          titleInput.value = state.canvas.title || '';
        }
        paintSide();
        updateHint();
        reevaluateEdit();
        if (!state.controllerInstalled) {
          showState(STR_24);
        }
        if (state.controllerInstalled) {
          tryRender();
        } else {
          emit('builder:loaded', { canvas: deepCopy(state.canvas), tree: deepCopy(state.tree) });
        }
      })
      .catch(function () {
        handleExpiredSession();
        emit('builder:error', { stage: 'load', status: 0, message: '' });
      });
  }

  function setButtonsEnabled(enabled, pressedBtn) {
    var ids = ['builder-btn-save', 'builder-btn-preview', 'builder-btn-publish'];
    for (var i = 0; i < ids.length; i++) {
      var btn = $(ids[i]);
      if (!btn) {
        continue;
      }
      if (enabled) {
        btn.disabled = false;
        if (btn.dataset.originalHtml !== undefined && btn.dataset.originalHtml !== '') {
          btn.innerHTML = btn.dataset.originalHtml;
          btn.dataset.originalHtml = '';
        }
      } else {
        btn.disabled = true;
        if (btn === pressedBtn) {
          if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
          }
          btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> ' + btn.dataset.originalHtml;
        }
      }
    }
  }

  function save(options) {
    return new Promise(function (resolve, reject) {
      if (!state.canEdit) {
        reject(new Error('read-only'));
        return;
      }
      if (state.saving) {
        reject(new Error('save-in-flight'));
        return;
      }
      emit('builder:before-save', {});
      var title = titleInput ? titleInput.value.trim() : '';
      if (!title) {
        Swal.fire({ title: STR_25, text: STR_26, icon: 'error' }).then(function () {
          if (titleInput) {
            titleInput.focus();
          }
        });
        reject(new Error('empty-title'));
        return;
      }
      if (title.length > 255) {
        Swal.fire({ title: STR_25, text: STR_27, icon: 'error' });
        reject(new Error('title-too-long'));
        return;
      }
      var tree;
      try {
        tree = window.BuilderShell.getTree();
      } catch (e) {
        Swal.fire({ title: STR_25, text: STR_28, icon: 'error' });
        reject(e);
        return;
      }
      if (!Array.isArray(tree)) {
        Swal.fire({ title: STR_25, text: STR_28, icon: 'error' });
        reject(new Error('bad-tree'));
        return;
      }
      var status = (options && (options.status === 'draft' || options.status === 'published')) ? options.status : (state.canvas ? state.canvas.status : 'draft');
      var body = {
        canvas_id: state.canvas && state.canvas.id !== null ? state.canvas.id : null,
        buildable_type: state.canvas ? state.canvas.buildable_type : null,
        template_id: state.canvas ? state.canvas.template_id : null,
        title: title,
        status: status,
        tree: tree,
        expected_version: (state.canvas && state.canvas.id !== null) ? state.canvas.version : null
      };
      state.saving = true;
      var pressed = document.activeElement && document.activeElement.id ? $(document.activeElement.id) : null;
      setButtonsEnabled(false, pressed);
      // settle() clears the in-flight flag synchronously, before resolve/reject,
      // so back-to-back save() calls observe a clean state. The trailing
      // .then() below repeats the reset idempotently as a safety net.
      function settle() {
        state.saving = false;
        setButtonsEnabled(true, null);
      }
      fetch(SAVE_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify(body)
      })
        .then(readJsonResponse)
        .then(function (res) {
          if (res.expired) {
            handleExpiredSession();
            emit('builder:error', { stage: 'save', status: 0, message: '' });
            settle();
            reject(new Error('expired'));
            return;
          }
          if (res.ok && res.body && res.body.success === true && res.body.data) {
            var wasNull = !state.canvas || state.canvas.id === null;
            var previousStatus = state.canvas ? state.canvas.status : null;
            state.canvas = normaliseCanvas(res.body.data.canvas);
            state.blockCount = res.body.data.block_count;
            if (titleInput) {
              titleInput.value = state.canvas.title || '';
              state.titleTouched = false;
            }
            paintSide();
            updateHint();
            window.BuilderShell.markClean();
            if (wasNull && state.canvas.id !== null) {
              try {
                window.history.replaceState(null, '', '?canvas_id=' + state.canvas.id);
              } catch (e) {}
            }
            var publishedNow = previousStatus !== 'published' && state.canvas.status === 'published';
            emit('builder:saved', {
              canvas: deepCopy(state.canvas),
              block_count: res.body.data.block_count,
              buildable_type_ignored: !!res.body.data.buildable_type_ignored,
              published_now: publishedNow
            });
            var savedData = res.body.data;
            var savedMessage = res.body.message;
            settle();
            resolve(savedData);
            if (publishedNow) {
              var publishedEv = new CustomEvent('builder:published', { detail: { canvas: deepCopy(state.canvas) }, cancelable: true });
              document.dispatchEvent(publishedEv);
              if (!publishedEv.defaultPrevented) {
                Swal.fire({ title: STR_29, text: savedMessage, icon: 'success', timer: 2500, showConfirmButton: false });
              }
            } else {
              Swal.fire({ title: STR_29, text: savedMessage, icon: 'success', timer: 2500, showConfirmButton: false });
            }
          } else {
            var message = (res.body && res.body.message) ? res.body.message : '';
            var text = message;
            if (res.body && Array.isArray(res.body.errors) && res.body.errors.length > 0) {
              text += '(' + toFa(res.body.errors.length) + STR_30;
            }
            Swal.fire({ title: STR_25, text: text, icon: 'error' });
            emit('builder:error', { stage: 'save', status: res.status, message: message });
            settle();
            reject(new Error(message || 'save-failed'));
          }
        })
        .catch(function (err) {
          if (err && err.message === 'expired') {
            return;
          }
          handleExpiredSession();
          emit('builder:error', { stage: 'save', status: 0, message: '' });
          settle();
          reject(err);
        })
        .then(function () {
          state.saving = false;
          setButtonsEnabled(true, null);
        });
    });
  }

  function wireButton(id, status) {
    var btn = $(id);
    if (!btn) {
      return;
    }
    btn.addEventListener('click', function () {
      if (status === 'preview') {
        window.BuilderShell.save().then(function () {
          if (typeof window.BuilderPreview === 'function') {
            window.BuilderPreview(window.BuilderShell.getCanvas());
          } else {
            Swal.fire({ title: STR_31, text: STR_32, icon: 'info' });
          }
        }, function () {});
      } else if (status === 'toggle-publish') {
        var next = (state.canvas && state.canvas.status === 'published') ? 'draft' : 'published';
        window.BuilderShell.save({ status: next }).then(function () {}, function () {});
      } else {
        window.BuilderShell.save().then(function () {}, function () {});
      }
    });
  }

  window.BuilderShell = {
    getCanvas: function () {
      return state.canvas === null ? null : deepCopy(state.canvas);
    },
    getTree: function () {
      if (state.controllerInstalled && state.controller) {
        return state.controller.getTree();
      }
      return state.tree === null ? null : deepCopy(state.tree);
    },
    setCanvasController: function (controller) {
      if (!controller || typeof controller !== 'object' || Array.isArray(controller)) {
        throw new Error('BuilderShell: controller must be an object');
      }
      if (typeof controller.render !== 'function' || typeof controller.getTree !== 'function') {
        throw new Error('BuilderShell: controller must expose render() and getTree()');
      }
      if (state.controllerInstalled) {
        throw new Error('BuilderShell: a canvas controller is already installed');
      }
      state.controller = controller;
      state.controllerInstalled = true;
      hideState();
      tryRender();
    },
    isDirty: function () {
      return state.dirty;
    },
    markDirty: function () {
      state.dirty = true;
      var dirtyBadge = $('builder-dirty-badge');
      if (dirtyBadge) {
        dirtyBadge.hidden = false;
      }
    },
    markClean: function () {
      state.dirty = false;
      var dirtyBadge = $('builder-dirty-badge');
      if (dirtyBadge) {
        dirtyBadge.hidden = true;
      }
    },
    canEdit: function () {
      return state.canEdit;
    },
    save: save
  };

  function onBeforeUnload(e) {
    if (state.dirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  }
  window.addEventListener('beforeunload', onBeforeUnload);

  document.addEventListener('DOMContentLoaded', function () {
    if (!state.canEdit) {
      enterReadonly();
    }
    if (titleInput) {
      titleInput.addEventListener('input', function () {
        state.titleTouched = true;
        window.BuilderShell.markDirty();
      });
    }
    wireButton('builder-btn-save', 'save');
    wireButton('builder-btn-preview', 'preview');
    wireButton('builder-btn-publish', 'toggle-publish');
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        window.BuilderShell.save().then(function () {}, function () {});
      }
    });
    loadCanvas();
  });
})();
