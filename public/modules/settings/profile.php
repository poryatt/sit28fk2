<?php
$currentModule='settings'; $pageTitle='پروفایل من'; $breadcrumb='تنظیمات پنل ‹ پروفایل';
require_once __DIR__ . '/../../../includes/page_boot.php';   // login + guard, nothing printed yet
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/media_registry.php';
$pdo=getPDO();
$user=currentUser();
$msg=''; $msgClass='success';

if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  $full_name=trim((string)($_POST['full_name']??''));
  $email=trim((string)($_POST['email']??''));
  $phone=trim((string)($_POST['phone']??''));
  $themeIn=(string)($_POST['theme_preference']??'');
  $theme=in_array($themeIn,['light','dark'],true) ? $themeIn : ($user['theme_preference']??'light');
  $cur=(string)($_POST['current_password']??'');
  $new=(string)($_POST['new_password']??'');
  $wantsPw=($cur!=='' || $new!=='');
  $mustChange=!empty($user['must_change_password']);

  // 1) validate everything BEFORE touching the disk or the database
  $err=null;
  if(mb_strlen($full_name,'UTF-8')<1 || mb_strlen($full_name,'UTF-8')>150) $err='نام و نام خانوادگی باید بین ۱ تا ۱۵۰ کاراکتر باشد.';
  elseif(mb_strlen($phone,'UTF-8')>20) $err='شماره تلفن حداکثر ۲۰ کاراکتر است.';
  elseif(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) $err='ایمیل نامعتبر است.';
  elseif($mustChange && $new==='') $err='برای ادامه باید رمز عبور جدید تعیین کنید.';
  elseif($wantsPw){
    $row=$pdo->prepare("SELECT password_hash FROM panel_users WHERE id=?"); $row->execute([(int)$user['id']]);
    $hash=(string)$row->fetchColumn();
    if($cur==='') $err='برای تغییر رمز، رمز فعلی را وارد کنید.';
    elseif(($p=validatePasswordPolicy($new))!==null) $err=$p;
    elseif(!password_verify($cur,$hash)) $err='رمز فعلی اشتباه است.';
    elseif(hash_equals($cur,$new)) $err='رمز جدید باید با رمز فعلی متفاوت باشد.';
    elseif(password_verify($new,SEED_PASSWORD_HASH)) $err='این رمز عبور مجاز نیست. رمز دیگری انتخاب کنید.';
  }

  if($err!==null){
    $msg=$err; $msgClass='danger';
  } else {
    // 2) avatar (P4): FileStore → registry inside the same transaction as the profile
    $avatarPath=$user['avatar_path']; $newAvatar=null; $oldAvatar=null;
    $f=$_FILES['avatar'] ?? null;
    if(is_array($f) && (int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
      try{ $newAvatar=FileStore::storeImage($f,'avatars'); }
      catch(FileStoreException $e){ $msg=$e->getMessage(); $msgClass='danger'; $newAvatar=false; }
    }
    if($newAvatar!==false){
      try{
        $pdo->beginTransaction();
        if($newAvatar){
          $reg=mediaRegisterFile($pdo,$newAvatar,'avatar-'.(int)$user['id'],'avatar',(int)$user['id']);
          $oldAvatar=$user['avatar_path']; $avatarPath=$reg['path'];
        }
        $pdo->prepare("UPDATE panel_users SET full_name=?, email=?, phone=?, avatar_path=?, theme_preference=? WHERE id=?")
            ->execute([$full_name,$email,$phone,$avatarPath,$theme,(int)$user['id']]);
        $now=time();
        if($wantsPw){
          $pdo->prepare("UPDATE panel_users SET password_hash=?, must_change_password=0, password_changed_at=? WHERE id=?")
              ->execute([hashPassword($new),date('Y-m-d H:i:s',$now),(int)$user['id']]);
        }
        $pdo->commit();
        if($oldAvatar!==null && $oldAvatar!==$avatarPath){ mediaReleasePath($pdo,$oldAvatar,'avatar'); }   // only after the commit
        if($wantsPw){
          session_regenerate_id(true);
          $_SESSION['auth_at']=$now;                 // this session survives, every older one is stale (sessionIsStale)
          logActivity('password_change','settings','تغییر رمز عبور');
          $msg='رمز عبور با موفقیت تغییر کرد.';
        } else {
          logActivity('profile_update','settings','ویرایش پروفایل');
          $msg='اطلاعات با موفقیت ذخیره شد.';
        }
      }catch(PDOException $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        if($newAvatar){ FileStore::remove($newAvatar['path']); }      // harmless if mediaRegisterFile already reused/removed it
        if((int)($e->errorInfo[1]??0)===1062){ $msg='این ایمیل قبلاً ثبت شده است.'; }
        else { error_log('profile.php: '.$e->getMessage()); $msg='خطا در ذخیره‌سازی.'; }
        $msgClass='danger';
      }
    }
  }
  $user=currentUser(true);
}
require_once __DIR__ . '/../../../includes/header.php';
?>
<?php if(!empty($user['must_change_password'])): ?><div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span>برای ادامه کار با پنل، رمز عبور جدید تعیین کنید.</span></div><?php endif; ?>
<?php if($msg): ?><div class="alert alert-<?= e($msgClass) ?> d-flex align-items-start gap-2" role="<?= $msgClass==='danger' ? 'alert' : 'status' ?>"><i class="ti <?= $msgClass==='danger' ? 'ti-alert-circle' : 'ti-circle-check' ?>" aria-hidden="true"></i><span><?= e($msg) ?></span></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="row g-3">
<?= csrf_field() ?>
  <div class="col-12 col-xl-7">
    <section class="card h-100" aria-labelledby="profile-account-title">
      <div class="card-head"><h2 class="card-head__title" id="profile-account-title">اطلاعات حساب</h2></div>
      <div class="card-pad d-grid gap-4">
        <div class="profile-avatar">
          <?php if($user['avatar_path']): ?><img src="<?= e($user['avatar_path']) ?>" alt="" width="80" height="80" class="avatar-lg"><?php else: ?><span class="avatar-lg avatar-lg--empty" aria-hidden="true"><i class="ti ti-user-circle"></i></span><?php endif; ?>
          <div class="field flex-grow-1"><label class="field__label" for="profile-avatar">آواتار</label><input id="profile-avatar" type="file" name="avatar" accept="image/*" class="form-control"></div>
        </div>
        <div class="form-grid">
          <div class="field"><label class="field__label" for="profile-name">نام و نام خانوادگی</label><input id="profile-name" name="full_name" value="<?= e($user['full_name']) ?>" class="form-control" maxlength="150" autocomplete="name"></div>
          <div class="field"><label class="field__label" for="profile-email">ایمیل</label><input id="profile-email" name="email" type="email" value="<?= e($user['email']) ?>" class="form-control input-ltr" maxlength="190" autocomplete="email"></div>
          <div class="field"><label class="field__label" for="profile-phone">تلفن</label><input id="profile-phone" name="phone" value="<?= e($user['phone']) ?>" class="form-control input-ltr" maxlength="20" inputmode="tel" autocomplete="tel"></div>
          <div class="field"><label class="field__label" for="profile-theme">تم</label><select id="profile-theme" name="theme_preference" class="form-select"><option value="light" <?= $user['theme_preference']==='light'?'selected':'' ?>>روشن</option><option value="dark" <?= $user['theme_preference']==='dark'?'selected':'' ?>>تیره</option></select></div>
        </div>
      </div>
    </section>
  </div>
  <div class="col-12 col-xl-5">
    <section class="card h-100" aria-labelledby="profile-security-title">
      <div class="card-head"><h2 class="card-head__title" id="profile-security-title">تغییر رمز عبور</h2></div>
      <div class="card-pad d-grid gap-4">
        <p class="field__hint mb-0">برای تغییر رمز، رمز فعلی و رمز جدید را وارد کنید. اگر نمی‌خواهید رمز را تغییر دهید، این بخش را خالی بگذارید.</p>
        <div class="field"><label class="field__label" for="profile-current">رمز فعلی</label><span class="input-icon input-icon--action"><i class="ti ti-lock" aria-hidden="true"></i><input id="profile-current" type="password" name="current_password" class="form-control input-ltr" autocomplete="current-password"><button type="button" class="icon-btn" data-password-toggle aria-pressed="false" aria-label="نمایش رمز عبور" data-label-show="نمایش رمز عبور" data-label-hide="پنهان کردن رمز عبور"><i class="ti ti-eye" aria-hidden="true"></i></button></span></div>
        <div class="field"><label class="field__label" for="profile-new">رمز جدید</label><span class="input-icon input-icon--action"><i class="ti ti-lock" aria-hidden="true"></i><input id="profile-new" type="password" name="new_password" class="form-control input-ltr" autocomplete="new-password"><button type="button" class="icon-btn" data-password-toggle aria-pressed="false" aria-label="نمایش رمز عبور" data-label-show="نمایش رمز عبور" data-label-hide="پنهان کردن رمز عبور"><i class="ti ti-eye" aria-hidden="true"></i></button></span></div>
      </div>
    </section>
  </div>
  <div class="col-12">
    <div class="form-actions form-actions--sticky"><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i>ذخیره تغییرات</button></div>
  </div>
</form>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
