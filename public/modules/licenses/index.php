<?php
$currentModule='licenses'; $pageTitle='مجوزات'; $breadcrumb='پیشخوان ‹ مجوزات';
require_once __DIR__ . '/../../../includes/header.php';
requirePermission('licenses','view');
$pdo=getPDO();
$licenses=$pdo->query("SELECT * FROM licenses ORDER BY id")->fetchAll();
$canEditLicenses=hasPermission('licenses','edit');
function badge($s){
  return match($s){
    'not_connected'=>'<span class="badge-soft badge-soft-muted"><i class="ti ti-circle-dashed" aria-hidden="true"></i>متصل نیست</span>',
    'pending'=>'<span class="badge-soft badge-soft-warning"><i class="ti ti-clock" aria-hidden="true"></i>در انتظار بررسی</span>',
    'verified'=>'<span class="badge-soft badge-soft-success"><i class="ti ti-circle-check" aria-hidden="true"></i>تأیید شده</span>',
    'rejected'=>'<span class="badge-soft badge-soft-danger"><i class="ti ti-circle-x" aria-hidden="true"></i>رد شده</span>',
    default=> e($s)
  };
}
?>
<div class="page-head">
  <p class="page-head__lead"><?= $canEditLicenses ? 'مجوزها و اطلاعات درگاه پرداخت سایت را ثبت کنید و وضعیت بررسی آن‌ها را ببینید.' : 'وضعیت مجوزها و درگاه پرداخت سایت را ببینید.' ?></p>
</div>
<div class="row g-3">
<?php foreach($licenses as $lic): $creds=$lic['credentials']? json_decode($lic['credentials'],true):[]; $lid=(int)$lic['id']; ?>
<div class="col-12 col-lg-6 col-xl-4">
  <section class="card license-card h-100" aria-labelledby="lic-<?= $lid ?>-title">
    <div class="card-head"><h2 class="card-head__title" id="lic-<?= $lid ?>-title"><?= e($lic['display_name']) ?></h2><?= badge($lic['status']) ?></div>
    <form class="card-pad license-card__form license-form" data-type="<?= e($lic['license_type']) ?>">
      <fieldset class="license-card__fields"<?= $canEditLicenses ? '' : ' disabled' ?>>
    <?php if($lic['license_type']==='enamad'): ?>
        <div class="field"><label class="field__label" for="lic-<?= $lid ?>-code">کد اینماد</label><input id="lic-<?= $lid ?>-code" name="enamad_code" value="<?= e($creds['enamad_code']??'') ?>" class="form-control input-ltr"></div>
        <div class="field"><label class="field__label" for="lic-<?= $lid ?>-file">گواهی (PDF یا تصویر)</label><input id="lic-<?= $lid ?>-file" type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control"></div>
        <?php if(!empty($lic['certificate_file'])): ?><a class="license-card__link" target="_blank" rel="noopener" href="/file.php?type=license&amp;id=<?= $lid ?>"><i class="ti ti-external-link" aria-hidden="true"></i>مشاهده گواهی</a><?php endif; ?>
      </fieldset>
      <?php if($canEditLicenses): ?><div class="license-card__actions"><button type="submit" class="btn btn-primary">ثبت درخواست</button></div><?php endif; ?>
    <?php elseif($lic['license_type']==='zarinpal'): ?>
        <div class="field"><label class="field__label" for="lic-<?= $lid ?>-merchant">مرچنت آیدی</label><input id="lic-<?= $lid ?>-merchant" name="merchant_id" value="<?= e($creds['merchant_id']??'') ?>" class="form-control input-ltr"></div>
        <div class="field"><label class="field__label" for="lic-<?= $lid ?>-key">کلید API</label><input id="lic-<?= $lid ?>-key" name="api_key" autocomplete="new-password" class="form-control input-ltr" aria-describedby="lic-<?= $lid ?>-key-hint"><p class="field__hint" id="lic-<?= $lid ?>-key-hint">برای تغییر، کلید جدید را وارد کنید (در غیر این صورت خالی بگذارید)<?php if(!empty($creds['api_key'])): ?> · کلید ثبت شده است ••••••••<?php endif; ?></p></div>
      </fieldset>
      <?php if($canEditLicenses): ?><div class="license-card__actions"><button type="submit" class="btn btn-primary">اتصال به زرین‌پال</button><?php if($lic['status']==='verified'): ?><button type="button" class="btn btn-outline-secondary btn-test" disabled>تست تراکنش آزمایشی</button><?php endif; ?></div><?php endif; ?>
    <?php else: ?>
        <div class="field"><label class="field__label" for="lic-<?= $lid ?>-number">شماره مجوز</label><input id="lic-<?= $lid ?>-number" name="license_number" value="<?= e($creds['license_number']??'') ?>" class="form-control input-ltr"></div>
        <div class="field"><label class="field__label" for="lic-<?= $lid ?>-file">گواهی (PDF یا تصویر)</label><input id="lic-<?= $lid ?>-file" type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control"></div>
        <?php if(!empty($lic['certificate_file'])): ?><a class="license-card__link" target="_blank" rel="noopener" href="/file.php?type=license&amp;id=<?= $lid ?>"><i class="ti ti-external-link" aria-hidden="true"></i>مشاهده گواهی</a><?php endif; ?>
        <div class="field"><label class="field__label" for="lic-<?= $lid ?>-status">وضعیت</label><select id="lic-<?= $lid ?>-status" name="status" class="form-select"><option value="pending" <?= $lic['status']==='pending'?'selected':'' ?>>در انتظار</option><option value="verified" <?= $lic['status']==='verified'?'selected':'' ?>>تأیید شده</option><option value="rejected" <?= $lic['status']==='rejected'?'selected':'' ?>>رد شده</option></select></div>
      </fieldset>
      <?php if($canEditLicenses): ?><div class="license-card__actions"><button type="submit" class="btn btn-primary">ذخیره</button></div><?php endif; ?>
    <?php endif; ?>
    </form>
    <div class="card-foot"><small class="text-muted">آخرین بروزرسانی: <?= $lic['updated_at']? toPersianDigits(toJalali($lic['updated_at'])): '—' ?></small></div>
  </section>
</div>
<?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.license-form').forEach(f=> f.addEventListener('submit', async(e)=>{
  e.preventDefault();
  const submit=f.querySelector('button[type="submit"]'); if(submit) submit.disabled=true;
  const fd=new FormData(f);
  fd.append('license_type', f.dataset.type);
  fd.append('csrf_token', window.CSRF_TOKEN);
  try{
    const res=await fetch('/api/licenses/update.php',{method:'POST', body:fd, headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN}});
    const j=await res.json();
    await Swal.fire(j.success?'موفق':'خطا', j.message, j.success?'success':'error');
    if(j.success){ location.reload(); return; }
  }catch(err){ await Swal.fire('خطا','خطا در ارتباط با سرور','error'); }
  if(submit) submit.disabled=false;
}));
</script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
