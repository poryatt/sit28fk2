<?php
$currentModule='settings'; $pageTitle='تنظیمات عمومی'; $breadcrumb='تنظیمات پنل ‹ عمومی';
require_once __DIR__ . '/../../../includes/page_boot.php';
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/media_registry.php';
requirePermission('settings','view');
$canEdit=hasPermission('settings','edit');
$pdo=getPDO();
$site=$pdo->query("SELECT id, site_name, site_domain, logo_path, favicon_path FROM site_settings WHERE id=1")->fetch();
$error=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!$canEdit){ denyAccess(); }
  requireCsrf();
  $site_name=trim((string)($_POST['site_name']??''));
  $site_domain=trim((string)($_POST['site_domain']??''));
  if(mb_strlen($site_name,'UTF-8')<1 || mb_strlen($site_name,'UTF-8')>150) $error='نام سایت باید بین ۱ تا ۱۵۰ کاراکتر باشد.';
  elseif(strlen($site_domain)>190) $error='دامنه حداکثر ۱۹۰ کاراکتر است.';

  $stored=[];            // files written in this request (rolled back on any failure)
  $newPath=['logo_path'=>$site['logo_path'],'favicon_path'=>$site['favicon_path']];
  $oldPaths=[];
  if($error===null){
    try{
      foreach(['logo'=>'logo_path','favicon'=>'favicon_path'] as $field=>$col){
        $f=$_FILES[$field]??null;
        if(!is_array($f) || (int)($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
        if($field==='logo'){
          $s=FileStore::storeImage($f,'media');                     // jpg / png / webp, re-encoded; SVG is no longer accepted (H2)
        } else {
          $fi=finfo_open(FILEINFO_MIME_TYPE); $mime=$fi? (string)finfo_file($fi,(string)($f['tmp_name']??'')):''; if($fi) finfo_close($fi);
          if(isIcoMime($mime))            $s=FileStore::storeIco($f);
          elseif($mime==='image/png')     $s=FileStore::storeImage($f,'media');
          else throw new FileStoreException('فاوآیکون باید PNG یا ICO باشد');
        }
        $stored[$col]=[$s,(string)($f['name']??'file')];
      }
    }catch(FileStoreException $e){
      foreach($stored as $pair){ FileStore::remove($pair[0]['path']); }
      $stored=[]; $error=$e->getMessage();
    }
  }
  if($error===null){
    try{
      $pdo->beginTransaction();
      foreach($stored as $col=>$pair){
        $reg=mediaRegisterFile($pdo,$pair[0],$pair[1],'site_asset',(int)$_SESSION['user_id']);
        if($site[$col]!==$reg['path']) $oldPaths[]=$site[$col];
        $newPath[$col]=$reg['path'];
      }
      $pdo->prepare("UPDATE site_settings SET site_name=?, site_domain=?, logo_path=?, favicon_path=? WHERE id=1")
          ->execute([$site_name,$site_domain,$newPath['logo_path'],$newPath['favicon_path']]);
      $pdo->commit();
    }catch(Throwable $e){
      if($pdo->inTransaction()) $pdo->rollBack();
      foreach($stored as $pair){ FileStore::remove($pair[0]['path']); }   // reused rows keep their older file (remove() only touches the new name)
      error_log('general.php: '.$e->getMessage());
      $error='خطا در ذخیره‌سازی.';
    }
  }
  if($error===null){
    foreach($oldPaths as $old){ mediaReleasePath($pdo,$old,'site_asset'); }   // only after the commit
    logActivity('site_settings_update','settings','ویرایش تنظیمات عمومی');
    header('Location: general.php?saved=1'); exit;
  }
  $site['site_name']=$site_name; $site['site_domain']=$site_domain;           // keep what the user typed
}
require_once __DIR__ . '/../../../includes/header.php';
$dis=$canEdit?'':'disabled';
?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success d-flex align-items-start gap-2" role="status"><i class="ti ti-circle-check" aria-hidden="true"></i><span>تنظیمات ذخیره شد.</span></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="ti ti-alert-circle" aria-hidden="true"></i><span><?= e($error) ?></span></div><?php endif; ?>
<?php if(!$canEdit): ?><div class="alert alert-info d-flex align-items-start gap-2" role="status"><i class="ti ti-lock" aria-hidden="true"></i><span>شما فقط اجازه‌ی مشاهده‌ی این تنظیمات را دارید.</span></div><?php endif; ?>
<div class="row">
  <div class="col-12 col-xl-8">
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?>
      <fieldset class="card-pad settings-fieldset"<?= $canEdit ? '' : ' disabled' ?>>
        <section class="settings-section" aria-labelledby="general-id-title">
          <h2 class="settings-section__title" id="general-id-title">هویت سایت</h2>
          <div class="form-grid">
            <div class="field"><label class="field__label" for="general-name">نام سایت</label><input id="general-name" name="site_name" value="<?= e($site['site_name']) ?>" class="form-control" maxlength="150"></div>
            <div class="field"><label class="field__label" for="general-domain">دامنه</label><input id="general-domain" name="site_domain" value="<?= e($site['site_domain']) ?>" class="form-control input-ltr" placeholder="example.com" maxlength="190"></div>
          </div>
        </section>
        <section class="settings-section" aria-labelledby="general-brand-title">
          <h2 class="settings-section__title" id="general-brand-title">تصاویر برند</h2>
          <div class="form-grid">
            <div class="field">
              <label class="field__label" for="general-logo">لوگو</label>
              <input id="general-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="form-control" aria-describedby="general-logo-hint">
              <p class="field__hint" id="general-logo-hint">فرمت‌های مجاز: JPG، PNG یا WebP</p>
              <?php if($site['logo_path']): ?><div class="asset-preview"><img src="<?= e($site['logo_path']) ?>" alt="" class="asset-preview__img"><span class="asset-preview__cap">لوگوی فعلی</span></div><?php endif; ?>
            </div>
            <div class="field">
              <label class="field__label" for="general-favicon">فاوآیکون</label>
              <input id="general-favicon" type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" class="form-control" aria-describedby="general-favicon-hint">
              <p class="field__hint" id="general-favicon-hint">فرمت‌های مجاز: PNG یا ICO</p>
              <?php if($site['favicon_path']): ?><div class="asset-preview"><img src="<?= e($site['favicon_path']) ?>" alt="" class="asset-preview__img asset-preview__img--icon"><span class="asset-preview__cap">فاوآیکون فعلی</span></div><?php endif; ?>
            </div>
          </div>
        </section>
      </fieldset>
      <?php if($canEdit): ?><div class="card-foot"><div class="form-actions"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i>ذخیره تنظیمات</button></div></div><?php endif; ?>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
