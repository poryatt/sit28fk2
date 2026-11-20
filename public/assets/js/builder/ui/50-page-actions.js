/* Part 8 — builder page actions: the permanent "new page" button, the
 * back-to-list link visibility, and the dialog shown right after a publish.
 * IIFE, no global. Exits immediately when not on the builder screen. */
(function () {
  'use strict';

  /* Persian UI strings (Part 9, P3.3): kept together at the top so no
   * Persian literal sits in the middle of logic. Values are unchanged. */
  var STR_1 = "تغییرات ذخیره‌نشده";
  var STR_2 = "تغییرات ذخیره‌نشده از بین می‌رود. ادامه می‌دهید؟";
  var STR_3 = "ادامه";
  var STR_4 = "ماندن";
  var STR_5 = "صفحه منتشر شد";
  var STR_6 = "صفحه با موفقیت ذخیره و منتشر شد.";
  var STR_7 = "ساخت صفحه جدید";
  var STR_8 = "ادامه ویرایش";
  var STR_9 = "صفحات ساخته شده";


  var root = document.getElementById('builder-root');
  if (!root) { return; }

  var ds = root.dataset;
  var canCreatePage = ds.canCreatePage === '1';
  var canViewList = ds.canViewList === '1';

  var NEW_PAGE_URL = '/modules/pages/index.php?buildable_type=page&new=1';
  var LIST_URL = '/modules/articles/index.php';

  var newPageBtn = document.getElementById('builder-btn-new-page');
  if (newPageBtn) {
    newPageBtn.addEventListener('click', function (e) {
      if (!window.BuilderShell || !window.BuilderShell.isDirty()) { return; }
      e.preventDefault();
      Swal.fire({
        title: STR_1,
        text: STR_2,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: STR_3,
        cancelButtonText: STR_4
      }).then(function (r) {
        if (r && r.isConfirmed) {
          try { window.BuilderShell.markClean(); } catch (err) { /* ignore */ }
          window.location.href = newPageBtn.getAttribute('href') || NEW_PAGE_URL;
        }
      });
    });
  }

  function syncBackList(canvas) {
    var back = document.getElementById('builder-btn-back-list');
    if (!back) { return; }
    back.hidden = !!(canvas && canvas.buildable_type === 'template');
  }

  document.addEventListener('builder:loaded', function (e) {
    syncBackList(e.detail && e.detail.canvas);
  });
  document.addEventListener('builder:saved', function (e) {
    syncBackList(e.detail && e.detail.canvas);
  });

  // builder:published is a cancelable event dispatched by builder-shell.js.
  // The shell only shows its generic toast when we do not prevent it.
  document.addEventListener('builder:published', function (e) {
    var canvas = e.detail && e.detail.canvas;
    if (!canvas || canvas.buildable_type !== 'page' || !canCreatePage) { return; }
    e.preventDefault();
    var opts = {
      title: STR_5,
      text: STR_6,
      icon: 'success',
      confirmButtonText: STR_7,
      showCancelButton: true,
      cancelButtonText: STR_8
    };
    if (canViewList) {
      opts.showDenyButton = true;
      opts.denyButtonText = STR_9;
    }
    Swal.fire(opts).then(function (r) {
      if (!r) { return; }
      if (r.isConfirmed) { window.location.href = NEW_PAGE_URL; }
      else if (r.isDenied) { window.location.href = LIST_URL; }
    });
  });
})();