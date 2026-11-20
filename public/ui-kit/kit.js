/* public/ui-kit/kit.js — dev-only helper of the living style guide (static HTML, no PHP).
 * Elements marked data-cdn="URL" are tried locally first (public/assets/vendor/...); if the local file is missing the
 * element is re-created with the pinned CDN URL. Mirrors includes/vendor.php for a page that cannot run PHP. */
(function () {
  'use strict';
  document.addEventListener('error', function (e) {
    var el = e.target;
    if (!el || !el.getAttribute || !el.getAttribute('data-cdn') || el.getAttribute('data-fell-back')) { return; }
    var cdn = el.getAttribute('data-cdn');
    if (el.tagName === 'LINK') {
      el.setAttribute('data-fell-back', '1');
      el.href = cdn;
    } else if (el.tagName === 'SCRIPT') {
      var copy = document.createElement('script');
      copy.src = cdn;
      copy.setAttribute('data-fell-back', '1');
      if (el.integrity) { copy.integrity = el.integrity; copy.crossOrigin = 'anonymous'; }
      copy.defer = el.defer;
      el.parentNode.insertBefore(copy, el.nextSibling);
    }
  }, true);
  document.addEventListener('submit', function (e) { e.preventDefault(); });
  window.CSRF_TOKEN = 'kit';
})();
