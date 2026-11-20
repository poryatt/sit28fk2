/* Pages manager (Part 8) — row delete with confirmation.
 * One delegated listener; no global. Reads window.CSRF_TOKEN (set by header.php). */
(function () {
  'use strict';

  var DELETE_URL = '/api/builder/delete.php';

  function readJson(response) {
    var contentType = response.headers.get('Content-Type') || '';
    if (response.redirected || contentType.indexOf('application/json') === -1) {
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

  function sessionExpired() {
    Swal.fire({ title: 'نشست منقضی شده', text: 'لطفاً دوباره وارد شوید.', icon: 'warning' }).then(function () {
      window.location.href = '/login.php';
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-act="delete-page"]') : null;
    if (!btn) { return; }
    e.preventDefault();
    if (btn.disabled) { return; }

    var id = btn.getAttribute('data-id');
    var title = btn.getAttribute('data-title') || '';

    Swal.fire({
      title: 'حذف صفحه',
      text: 'صفحه «' + title + '» برای همیشه حذف می‌شود. این کار قابل بازگشت نیست.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'حذف',
      cancelButtonText: 'انصراف',
      customClass: (window.PanelUI ? window.PanelUI.swalClasses.danger : undefined),
      focusCancel: true
    }).then(function (result) {
      if (!result || !result.isConfirmed) { return; }
      btn.disabled = true;
      var fd = new FormData();
      fd.append('canvas_id', id);
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      fetch(DELETE_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN || '', Accept: 'application/json' },
        body: fd
      }).then(readJson).then(function (res) {
        if (res.expired) { sessionExpired(); return; }
        var body = res.body || {};
        if (res.ok && body.success === true) {
          Swal.fire({ title: 'موفق', text: body.message || 'صفحه حذف شد', icon: 'success', timer: 1500, showConfirmButton: false }).then(function () {
            window.location.reload();
          });
        } else {
          btn.disabled = false;
          Swal.fire({ title: 'خطا', text: body.message || 'خطا در ارتباط با سرور', icon: 'error' });
        }
      }).catch(function () {
        btn.disabled = false;
        Swal.fire({ title: 'خطا', text: 'خطا در ارتباط با سرور', icon: 'error' });
      });
    });
  });
})();