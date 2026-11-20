/* app.js — PanelUI: the one global of the UI layer. Loaded with `defer` on every page, before admin.js.
 *
 * Contracts (all hooks are data-* attributes):
 *   <html data-bs-theme="light|dark" data-sidebar="expanded|collapsed" data-drawer="open" data-theme-url="...">
 *   [data-theme-toggle]    theme button            [data-sidebar-toggle]  sidebar button (rail on desktop, drawer on phone)
 *   [data-drawer-close]    backdrop / close button [data-tip="text"]      tooltip (sidebar items: only while collapsed)
 *   [data-countup="123"]   stat number (server already rendered the final text)
 *   [data-password-toggle] button next to a password input (data-label-show / data-label-hide carry the Persian labels)
 * Events on document: panel:theme-changed {theme}, panel:sidebar-changed {state}.
 * Storage: theme -> panel_users.theme_preference (authoritative, POST theme=) + localStorage['panel-theme'] (login page, cross-tab);
 *          sidebar -> cookie panel_sidebar=collapsed|expanded (server reads it to avoid a flash). Nothing else.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var DESKTOP = window.matchMedia('(min-width: 992px)');
  var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)');
  var PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

  function emit(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { detail: detail }));
  }
  function toPersianDigits(n) {
    return String(n).replace(/[0-9]/g, function (d) { return PERSIAN[+d]; });
  }
  function safeStorage(op, key, value) {
    try {
      if (op === 'get') { return window.localStorage.getItem(key); }
      window.localStorage.setItem(key, value);
    } catch (e) { /* private mode / blocked: the UI works without it */ }
    return null;
  }

  /* ---------------------------------------------------------------- theme */
  var theme = {
    get: function () { return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light'; },
    initial: function () {
      var stored = safeStorage('get', 'panel-theme');
      if (stored === 'light' || stored === 'dark') { return stored; }
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    },
    apply: function (value) {
      root.setAttribute('data-bs-theme', value);
      var buttons = document.querySelectorAll('[data-theme-toggle]');
      for (var i = 0; i < buttons.length; i++) { buttons[i].setAttribute('aria-pressed', value === 'dark' ? 'true' : 'false'); }
      emit('panel:theme-changed', { theme: value });
    },
    persist: function (value) {
      safeStorage('set', 'panel-theme', value);
      var url = root.getAttribute('data-theme-url');
      if (!url || !window.fetch) { return; }
      var body = new FormData();
      body.append('theme', value);
      body.append('csrf_token', window.CSRF_TOKEN || '');
      // A failed POST never blocks the UI; the next page load simply shows the stored preference.
      fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN || '' } })
        .catch(function () { /* ignore */ });
    },
    set: function (value, options) {
      options = options || {};
      if (value !== 'light' && value !== 'dark') { return; }
      if (value === theme.get() && !options.force) { return; }
      var doApply = function () { theme.apply(value); };
      var origin = options.origin; // {x, y} of the toggle, for the circular reveal
      if (!options.silent && !REDUCED.matches && typeof document.startViewTransition === 'function' && origin) {
        var radius = Math.hypot(Math.max(origin.x, window.innerWidth - origin.x), Math.max(origin.y, window.innerHeight - origin.y));
        var transition = document.startViewTransition(doApply);
        transition.ready.then(function () {
          // dynamic geometry: the clip-path circle starts at the toggle and grows to cover the viewport
          root.animate(
            { clipPath: ['circle(0px at ' + origin.x + 'px ' + origin.y + 'px)', 'circle(' + radius + 'px at ' + origin.x + 'px ' + origin.y + 'px)'] },
            { duration: 520, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)', pseudoElement: '::view-transition-new(root)' }
          );
        }).catch(function () { /* transition skipped */ });
      } else if (!options.silent && !REDUCED.matches) {
        root.classList.add('theme-fade');
        doApply();
        window.setTimeout(function () { root.classList.remove('theme-fade'); }, 280);
      } else {
        doApply();
      }
      if (options.persist !== false) { theme.persist(value); }
    },
    toggle: function (origin) { theme.set(theme.get() === 'dark' ? 'light' : 'dark', { origin: origin }); }
  };

  /* -------------------------------------------------------------- sidebar */
  function setCookie(name, value) {
    document.cookie = name + '=' + value + '; path=/; max-age=31536000; samesite=lax';
  }
  var sidebar = {
    state: function () { return root.getAttribute('data-sidebar') === 'collapsed' ? 'collapsed' : 'expanded'; },
    isDrawerOpen: function () { return root.getAttribute('data-drawer') === 'open'; },
    syncToggles: function () {
      var expanded = DESKTOP.matches ? sidebar.state() === 'expanded' : sidebar.isDrawerOpen();
      var buttons = document.querySelectorAll('[data-sidebar-toggle]');
      for (var i = 0; i < buttons.length; i++) { buttons[i].setAttribute('aria-expanded', expanded ? 'true' : 'false'); }
    },
    setState: function (state) {
      if (state !== 'collapsed' && state !== 'expanded') { return; }
      root.setAttribute('data-sidebar', state);
      setCookie('panel_sidebar', state);
      sidebar.syncToggles();
      tips.hide();
      emit('panel:sidebar-changed', { state: state });
    },
    openDrawer: function () {
      var aside = document.getElementById('app-sidebar');
      var main = document.querySelector('.app-main');
      if (!aside) { return; }
      sidebar.lastFocus = document.activeElement;
      root.setAttribute('data-drawer', 'open');
      if (main) { main.setAttribute('inert', ''); }
      sidebar.syncToggles();
      var first = aside.querySelector('a[href], button');
      if (first) { window.setTimeout(function () { first.focus(); }, 30); }
    },
    closeDrawer: function (restoreFocus) {
      if (!sidebar.isDrawerOpen()) { return; }
      var main = document.querySelector('.app-main');
      root.removeAttribute('data-drawer');
      if (main) { main.removeAttribute('inert'); }
      sidebar.syncToggles();
      if (restoreFocus !== false && sidebar.lastFocus && sidebar.lastFocus.focus) { sidebar.lastFocus.focus(); }
    },
    toggle: function () {
      if (DESKTOP.matches) { sidebar.setState(sidebar.state() === 'expanded' ? 'collapsed' : 'expanded'); }
      else if (sidebar.isDrawerOpen()) { sidebar.closeDrawer(); }
      else { sidebar.openDrawer(); }
    }
  };

  /* -------------------------------------------------------------- tooltip */
  var tips = (function () {
    var el = null;
    var current = null;
    function ensure() {
      if (el) { return el; }
      el = document.createElement('div');
      el.className = 'app-tip';
      el.id = 'app-tip';
      el.setAttribute('role', 'tooltip');
      document.body.appendChild(el);
      return el;
    }
    function allowed(target) {
      // Sidebar items only need a tooltip while the rail is collapsed on desktop.
      if (target.closest && target.closest('.app-sidebar')) { return DESKTOP.matches && sidebar.state() === 'collapsed'; }
      return true;
    }
    function show(target) {
      var text = target.getAttribute('data-tip');
      if (!text || !allowed(target)) { return; }
      var tip = ensure();
      tip.textContent = text;
      tip.classList.remove('is-visible');
      var rect = target.getBoundingClientRect();
      var rtl = getComputedStyle(root).direction === 'rtl';
      var w = tip.offsetWidth;
      var h = tip.offsetHeight;
      var x, y;
      var gap = 10;
      if (target.closest('.app-sidebar')) {
        // Documented physical exception: beside the rail, on the side that faces the content (left in RTL).
        y = rect.top + (rect.height - h) / 2;
        x = rtl ? rect.left - w - gap : rect.right + gap;
      } else {
        x = rect.left + (rect.width - w) / 2;
        y = target.getAttribute('data-tip-pos') === 'top' ? rect.top - h - gap : rect.bottom + gap;
      }
      x = Math.max(8, Math.min(x, window.innerWidth - w - 8));
      y = Math.max(8, Math.min(y, window.innerHeight - h - 8));
      tip.style.left = x + 'px'; // dynamic geometry
      tip.style.top = y + 'px';  // dynamic geometry
      current = target;
      target.setAttribute('aria-describedby', 'app-tip');
      tip.classList.add('is-visible');
    }
    function hide() {
      if (el) { el.classList.remove('is-visible'); }
      if (current) { current.removeAttribute('aria-describedby'); current = null; }
    }
    function bind() {
      document.addEventListener('mouseover', function (e) { var t = e.target.closest && e.target.closest('[data-tip]'); if (t) { show(t); } });
      document.addEventListener('mouseout', function (e) { var t = e.target.closest && e.target.closest('[data-tip]'); if (t) { hide(); } });
      document.addEventListener('focusin', function (e) { var t = e.target.closest && e.target.closest('[data-tip]'); if (t) { show(t); } });
      document.addEventListener('focusout', function (e) { var t = e.target.closest && e.target.closest('[data-tip]'); if (t) { hide(); } });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
      window.addEventListener('scroll', hide, true);
      window.addEventListener('resize', hide);
    }
    return { bind: bind, show: show, hide: hide };
  })();

  /* ------------------------------------------------------------- count-up */
  function countUp() {
    var nodes = document.querySelectorAll('[data-countup]');
    if (!nodes.length || REDUCED.matches || !('IntersectionObserver' in window)) { return; }
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) { return; }
        observer.unobserve(entry.target);
        run(entry.target);
      });
    }, { threshold: 0.4 });
    nodes.forEach(function (n) { observer.observe(n); });
    function run(node) {
      var target = parseInt(node.getAttribute('data-countup'), 10);
      var finalText = node.textContent; // the server-rendered text is the truth and is restored at the end
      if (!isFinite(target) || target < 0) { return; }
      var start = null;
      var duration = 900;
      function frame(ts) {
        if (start === null) { start = ts; }
        var p = Math.min(1, (ts - start) / duration);
        var eased = 1 - Math.pow(1 - p, 3);
        node.textContent = toPersianDigits(Math.round(target * eased));
        if (p < 1) { window.requestAnimationFrame(frame); } else { node.textContent = finalText; }
      }
      window.requestAnimationFrame(frame);
    }
  }

  /* ------------------------------------------------------ password toggle */
  function bindPasswordToggles() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-password-toggle]');
      if (!btn) { return; }
      var wrap = btn.closest('.input-icon') || btn.parentElement;
      var input = wrap && wrap.querySelector('input');
      if (!input) { return; }
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', btn.getAttribute(show ? 'data-label-hide' : 'data-label-show') || '');
      var icon = btn.querySelector('i');
      if (icon) { icon.className = show ? 'ti ti-eye-off' : 'ti ti-eye'; }
    });
  }

  /* ---------------------------------------------------- SweetAlert2 theme */
  var swalClasses = {
    primary: { popup: 'swal-panel', confirmButton: 'btn btn-primary', cancelButton: 'btn btn-outline-secondary', denyButton: 'btn btn-danger' },
    danger: { popup: 'swal-panel', confirmButton: 'btn btn-danger', cancelButton: 'btn btn-outline-secondary', denyButton: 'btn btn-danger' }
  };
  function themeSwal() {
    // Swal is loaded by a classic script before this deferred file runs. Replace the global once with a
    // pre-configured subclass: every existing Swal.fire(...) call keeps working and picks up the theme classes.
    if (!window.Swal || window.Swal.__panelThemed) { return; }
    var Themed = window.Swal.mixin({ buttonsStyling: false, customClass: swalClasses.primary, heightAuto: false, scrollbarPadding: false });
    Themed.__panelThemed = true;
    window.Swal = Themed;
  }

  /* ----------------------------------------------------------------- init */
  function init() {
    // Login page (no server-rendered preference): follow localStorage, then the system.
    if (!root.hasAttribute('data-bs-theme')) { theme.apply(theme.initial()); }
    theme.apply(theme.get()); // sync aria-pressed

    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('[data-theme-toggle]');
      if (t) {
        var r = t.getBoundingClientRect();
        theme.toggle({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
        return;
      }
      if (e.target.closest && e.target.closest('[data-sidebar-toggle]')) { sidebar.toggle(); return; }
      if (e.target.closest && e.target.closest('[data-drawer-close]')) { sidebar.closeDrawer(); return; }
      var link = e.target.closest && e.target.closest('.app-sidebar a[href]');
      if (link && sidebar.isDrawerOpen()) { sidebar.closeDrawer(false); }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.isDrawerOpen()) { sidebar.closeDrawer(); return; }
      // Focus trap inside the open drawer (the rest of the page is inert as well).
      if (e.key === 'Tab' && sidebar.isDrawerOpen()) {
        var aside = document.getElementById('app-sidebar');
        var items = aside ? aside.querySelectorAll('a[href], button:not([disabled])') : [];
        if (!items.length) { return; }
        var first = items[0];
        var last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });

    // Crossing the breakpoint: never leave a drawer open on desktop.
    var onChange = function () { sidebar.closeDrawer(false); sidebar.syncToggles(); tips.hide(); };
    if (DESKTOP.addEventListener) { DESKTOP.addEventListener('change', onChange); } else { DESKTOP.addListener(onChange); }

    // Cross-tab theme sync.
    window.addEventListener('storage', function (e) {
      if (e.key === 'panel-theme' && (e.newValue === 'light' || e.newValue === 'dark')) { theme.set(e.newValue, { persist: false, silent: true }); }
    });

    sidebar.syncToggles();
    tips.bind();
    countUp();
    bindPasswordToggles();
    themeSwal();
  }

  window.PanelUI = { theme: theme, sidebar: sidebar, tips: tips, swalClasses: swalClasses, toPersianDigits: toPersianDigits };

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
