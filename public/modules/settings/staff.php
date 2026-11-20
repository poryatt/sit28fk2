<?php
$currentModule='settings'; $pageTitle='کاربران و دسترسی‌ها'; $breadcrumb='تنظیمات پنل ‹ کاربران';
require_once __DIR__ . '/../../../includes/header.php';
if(!isOwner()){
  http_response_code(403);
  ?>
<section class="card"><div class="empty-state"><span class="empty-state__icon"><i class="ti ti-lock" aria-hidden="true"></i></span><p class="empty-state__title">فقط مالک دسترسی دارد</p></div></section>
<?php require_once __DIR__ . '/../../../includes/footer.php'; exit;
}
$pdo=getPDO();
$users=$pdo->query("SELECT id, full_name, email, status FROM panel_users WHERE role='staff' AND status<>'removed' ORDER BY id")->fetchAll();
$perms=$pdo->query("SELECT * FROM permissions ORDER BY id")->fetchAll();
$up=$pdo->prepare("SELECT p.module_key, p.module_label, pup.can_view, pup.can_edit, pup.can_delete FROM permissions p LEFT JOIN panel_user_permissions pup ON pup.permission_id=p.id AND pup.panel_user_id=? ORDER BY p.id");
?>
<div class="page-head">
  <p class="page-head__lead">همکاران را اضافه کنید و برای هر بخش مشخص کنید چه کارهایی می‌توانند انجام دهند.</p>
  <div class="page-head__actions"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#inviteModal"><i class="ti ti-user-plus" aria-hidden="true"></i>افزودن همکار جدید</button></div>
</div>
<?php if(!$users): ?>
<section class="card"><div class="empty-state">
  <span class="empty-state__icon"><i class="ti ti-users" aria-hidden="true"></i></span>
  <p class="empty-state__title">هنوز همکاری اضافه نشده است</p>
  <p class="empty-state__text">با افزودن همکار، می‌توانید دسترسی هر بخش را جداگانه تعیین کنید.</p>
</div></section>
<?php else: ?>
<section class="card table-card" aria-label="همکاران">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead><tr><th scope="col">نام</th><th scope="col">ایمیل</th><th scope="col">وضعیت</th><th scope="col">دسترسی‌ها</th><th scope="col">عملیات</th></tr></thead>
<tbody>
<?php foreach($users as $u):
  $up->execute([$u['id']]); $m=$up->fetchAll();
  $granted=[];
  foreach($m as $row){
    $r=[]; if($row['can_view']) $r[]='مشاهده'; if($row['can_edit']) $r[]='ویرایش'; if($row['can_delete']) $r[]='حذف';
    if($r) $granted[]=['label'=>(string)($row['module_label'] ?? $row['module_key']), 'rights'=>implode('، ',$r)];
  }
?>
<tr>
<td class="cell-title"><?= e($u['full_name']) ?></td>
<td><span class="ltr"><?= e($u['email']) ?></span></td>
<td><?= $u['status']==='active'?'<span class="badge-soft badge-soft-success"><i class="ti ti-circle-check" aria-hidden="true"></i>فعال</span>':'<span class="badge-soft badge-soft-danger"><i class="ti ti-lock" aria-hidden="true"></i>مسدود</span>' ?></td>
<td>
  <div class="perm-chips">
  <?php if($granted): foreach($granted as $g): ?>
    <span class="perm-chip"><?= e($g['label']) ?><span class="perm-chip__rights">· <?= e($g['rights']) ?></span></span>
  <?php endforeach; else: ?>
    <span class="perm-chip perm-chip--none">بدون دسترسی</span>
  <?php endif; ?>
  </div>
</td>
<td>
  <div class="cell-actions">
    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-perm" data-id="<?= (int)$u['id'] ?>" data-name="<?= e($u['full_name']) ?>">ویرایش دسترسی</button>
    <?php if($u['status']==='active'): ?><button type="button" class="btn btn-sm btn-outline-warning btn-status" data-id="<?= (int)$u['id'] ?>" data-status="suspended">تعلیق</button><?php else: ?><button type="button" class="btn btn-sm btn-outline-success btn-status" data-id="<?= (int)$u['id'] ?>" data-status="active">فعال‌سازی</button><?php endif; ?>
    <button type="button" class="btn btn-sm btn-outline-danger btn-remove" data-id="<?= (int)$u['id'] ?>" aria-label="حذف همکار" data-tip="حذف همکار"><i class="ti ti-trash" aria-hidden="true"></i></button>
  </div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<?php endif; ?>

<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><h2 class="modal-title fs-5" id="inviteTitle">افزودن همکار</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button></div>
<form id="inviteForm" method="post"><div class="modal-body d-grid gap-3">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="field"><label class="field__label" for="invite-name">نام کامل</label><input id="invite-name" name="full_name" class="form-control" required autocomplete="off"></div>
<div class="field"><label class="field__label" for="invite-username">نام کاربری</label><input id="invite-username" name="username" class="form-control input-ltr" required autocomplete="off"></div>
<div class="field"><label class="field__label" for="invite-email">ایمیل</label><input id="invite-email" name="email" type="email" class="form-control input-ltr" required autocomplete="off"></div>
<div class="field"><label class="field__label" for="invite-password">رمز عبور</label><span class="input-icon input-icon--action"><i class="ti ti-lock" aria-hidden="true"></i><input id="invite-password" name="password" type="password" class="form-control input-ltr" required autocomplete="new-password"><button type="button" class="icon-btn" data-password-toggle aria-pressed="false" aria-label="نمایش رمز عبور" data-label-show="نمایش رمز عبور" data-label-hide="پنهان کردن رمز عبور"><i class="ti ti-eye" aria-hidden="true"></i></button></span></div>
</div><div class="modal-footer"><button type="submit" class="btn btn-primary">ایجاد</button></div></form>
</div></div></div>

<div class="modal fade" id="permModal" tabindex="-1" aria-labelledby="permTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><div><h2 class="modal-title fs-5" id="permTitle">ویرایش دسترسی</h2><p class="field__hint mb-0" id="permUserName"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button></div>
<form id="permForm" method="post"><div class="modal-body">
<input type="hidden" name="user_id" id="permUserId">
<div class="table-responsive"><table class="table perm-table align-middle mb-0"><thead><tr><th scope="col">ماژول</th><th scope="col">مشاهده</th><th scope="col">ویرایش</th><th scope="col">حذف</th></tr></thead><tbody id="permBody"></tbody></table></div>
</div><div class="modal-footer"><button type="submit" class="btn btn-primary">ذخیره</button></div></form>
</div></div></div>

<script>
const perms=<?= json_encode($perms, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const esc=(s)=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function send(url, fd, btn){
  if(btn) btn.disabled=true;
  try{
    const res=await fetch(url,{method:'POST', body:fd, headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN}});
    return await res.json();
  }catch(err){ return {success:false, message:'خطا در ارتباط با سرور'}; }
  finally{ if(btn) btn.disabled=false; }
}
document.getElementById('inviteForm').addEventListener('submit', async(e)=>{
  e.preventDefault();
  const fd=new FormData(e.target); fd.set('csrf_token', window.CSRF_TOKEN);
  const j=await send('/api/staff/invite.php', fd, e.target.querySelector('button[type="submit"]'));
  Swal.fire(j.success?'موفق':'خطا', j.message, j.success?'success':'error').then(()=> j.success&&location.reload());
});
document.querySelectorAll('.btn-edit-perm').forEach(b=> b.addEventListener('click', async()=>{
  const uid=b.dataset.id;
  document.getElementById('permUserId').value=uid;
  document.getElementById('permUserName').textContent=b.dataset.name||'';
  let j={};
  try{ const res=await fetch('/api/staff/save_permissions.php?user_id='+encodeURIComponent(uid)); j=await res.json(); }
  catch(err){ Swal.fire('خطا','خطا در ارتباط با سرور','error'); return; }
  let html='';
  perms.forEach(p=>{
    const row=j.data?.find(x=> x.module_key===p.module_key) || {};
    const label=esc(p.module_label);
    const cell=(kind,word,on)=>`<td><input class="form-check-input" type="checkbox" name="perm[${p.id}][${kind}]" value="1" ${on?'checked':''} aria-label="${word} ${label}"></td>`;
    html+=`<tr><th scope="row" class="fw-medium">${label}</th>${cell('view','مشاهده',row.can_view)}${cell('edit','ویرایش',row.can_edit)}${cell('delete','حذف',row.can_delete)}</tr>`;
  });
  document.getElementById('permBody').innerHTML=html;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('permModal')).show();
}));
document.getElementById('permForm').addEventListener('submit', async(e)=>{
  e.preventDefault();
  const fd=new FormData(e.target); fd.set('csrf_token', window.CSRF_TOKEN);
  const j=await send('/api/staff/save_permissions.php', fd, e.target.querySelector('button[type="submit"]'));
  Swal.fire(j.success?'موفق':'خطا', j.message, j.success?'success':'error').then(()=> j.success&&location.reload());
});
document.querySelectorAll('.btn-remove').forEach(b=> b.addEventListener('click', async()=>{
  const r=await confirmDelete('همکار حذف شود؟ دسترسی او فوراً قطع می‌شود و نام کاربری و ایمیلش آزاد می‌شود، اما صفحات و رسانه‌های ساخته‌شده توسط او و لاگ فعالیتش حفظ می‌شود. اگر فقط می‌خواهید دسترسی را موقتاً قطع کنید از «تعلیق» استفاده کنید.'); if(!r.isConfirmed) return;
  const fd=new FormData(); fd.append('id', b.dataset.id); fd.append('csrf_token', window.CSRF_TOKEN);
  const j=await send('/api/staff/remove.php', fd, b);
  Swal.fire(j.success?'حذف شد':'خطا', j.message, j.success?'success':'error').then(()=> j.success&&location.reload());
}));
document.querySelectorAll('.btn-status').forEach(b=> b.addEventListener('click', async()=>{
  const target=b.dataset.status;
  const r=await Swal.fire({title: (target==='suspended' ? 'همکار تعلیق شود؟' : 'همکار فعال شود؟'), icon:'warning', showCancelButton:true, confirmButtonText:'بله', cancelButtonText:'انصراف'});
  if(!r.isConfirmed) return;
  const fd=new FormData(); fd.append('id', b.dataset.id); fd.append('status', target); fd.append('csrf_token', window.CSRF_TOKEN);
  const j=await send('/api/staff/set_status.php', fd, b);
  Swal.fire(j.success?'موفق':'خطا', j.message, j.success?'success':'error').then(()=> j.success&&location.reload());
}));
document.getElementById('permBody').addEventListener('change', e=>{
  const t=e.target;
  if(t.type!=='checkbox' || !t.name) return;
  const m=t.name.match(/^perm\[(\d+)\]\[(view|edit|delete)\]$/);
  if(!m) return;
  const pid=m[1], kind=m[2];
  const box=k=>document.querySelector(`#permBody input[name="perm[${pid}][${k}]"]`);
  if(kind==='delete' && t.checked){ const b1=box('edit'), b2=box('view'); if(b1) b1.checked=true; if(b2) b2.checked=true; }
  if(kind==='edit' && t.checked){ const b2=box('view'); if(b2) b2.checked=true; }
  if(kind==='view' && !t.checked){ const b1=box('edit'), b3=box('delete'); if(b1) b1.checked=false; if(b3) b3.checked=false; }
  if(kind==='edit' && !t.checked){ const b3=box('delete'); if(b3) b3.checked=false; }
});
</script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
