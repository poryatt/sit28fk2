// admin.js - generic AJAX helpers and the shared delete confirmation.
// The theme toggle is NOT handled here any more: PanelUI.theme (app.js) owns it.
// Load order on every panel page: app.js (defer, in <head>) -> Swal -> admin.js (layout/close.php).

function csrfHeaders(extra = {}) {
  return { 'X-CSRF-TOKEN': window.CSRF_TOKEN, ...extra };
}

async function ajaxDelete(url, data = {}) {
  const fd = new FormData();
  for (const k in data) fd.append(k, data[k]);
  fd.append('csrf_token', window.CSRF_TOKEN);
  const res = await fetch(url, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN } });
  return res.json();
}

function confirmDelete(msg = 'آیا از حذف اطمینان دارید؟') {
  const opts = {
    title: 'تأیید حذف',
    text: msg,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'بله، حذف کن',
    cancelButtonText: 'انصراف',
    focusCancel: true
  };
  // Colours come from the themed Swal classes (tokens), never from a hard-coded hex.
  if (window.PanelUI && window.PanelUI.swalClasses) opts.customClass = window.PanelUI.swalClasses.danger;
  return Swal.fire(opts);
}
