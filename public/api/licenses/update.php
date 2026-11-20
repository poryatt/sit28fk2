<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/secrets.php';
requireLogin(); requirePermission('licenses','edit');
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$type=(string)($_POST['license_type']??'');
if(!in_array($type,['enamad','zarinpal','business_license'],true)) jsonResponse(['success'=>false,'message'=>'نوع مجوز نامعتبر'],400);

function postedText(string $key, int $max): string {
  $v=trim((string)($_POST[$key]??''));
  if(mb_strlen($v,'UTF-8')>$max) jsonResponse(['success'=>false,'message'=>'درخواست نامعتبر'],400);
  return $v;
}
$creds=[]; $status='pending';
if($type==='enamad'){
  $creds['enamad_code']=postedText('enamad_code',190);
} elseif($type==='zarinpal'){
  $mid=postedText('merchant_id',64);
  if($mid!=='' && !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D',$mid))
    jsonResponse(['success'=>false,'message'=>'مرچنت آیدی نامعتبر است'],400);
  $creds['merchant_id']=$mid;
  $plainKey=trim((string)($_POST['api_key']??'')); if(strlen($plainKey)>300) jsonResponse(['success'=>false,'message'=>'کلید API یا رمز API بیش از حد طولانی است'],400);
} else {
  $creds['license_number']=postedText('license_number',190);
  $status=(string)($_POST['status']??'pending');
  if(!in_array($status,['pending','verified','rejected'],true)) $status='pending';   // 'not_connected' is system-set only
}

$pdo=getPDO();
$old=$pdo->prepare("SELECT certificate_file FROM licenses WHERE license_type=?"); $old->execute([$type]);
$oldCert=$old->fetchColumn();
if($oldCert===false) jsonResponse(['success'=>false,'message'=>'نوع مجوز نامعتبر'],400);

$newCert=null;
$f=$_FILES['certificate'] ?? null;
if($type!=='zarinpal' && is_array($f) && (int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
  try{
    $newCert=FileStore::storePrivate($f,'licenses',
      ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'],MAX_CERT_BYTES);
  }catch(FileStoreException $e){
    $m=$e->getMessage();
    jsonResponse(['success'=>false,'message'=>($m==='فرمت فایل مجاز نیست' ? 'فرمت مجاز: PDF/JPG/PNG/WEBP' : $m)],400);
  }
}
$credsJson=null;
if($type==='zarinpal'){
  $cur=$pdo->prepare("SELECT credentials FROM licenses WHERE license_type='zarinpal'"); $cur->execute();
  $curCreds=json_decode((string)$cur->fetchColumn(), true);
  $curKey=is_array($curCreds) ? (string)($curCreds['api_key']??'') : '';
  try{
    if($plainKey!=='')       { $creds['api_key']=secret_encrypt($plainKey); }   // new key typed
    elseif($curKey!=='')     { $creds['api_key']=secret_is_encrypted($curKey) ? $curKey : secret_encrypt($curKey); }   // blank = keep (and upgrade legacy plaintext)
    else                     { $creds['api_key']=''; }
  }catch(Throwable $e){
    if($newCert) FileStore::removePrivate($newCert['rel']);
    error_log('licenses/update.php: '.$e->getMessage());
    jsonResponse(['success'=>false,'message'=>'خطا در رمزنگاری اطلاعات'],500);   // STR_SECRET_ERROR
  }
}
if($credsJson===null) $credsJson=json_encode($creds, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
try{
  if($newCert){
    $pdo->prepare("UPDATE licenses SET credentials=?, certificate_file=?, status=?, updated_by=? WHERE license_type=?")
        ->execute([$credsJson,$newCert['rel'],$status,(int)$_SESSION['user_id'],$type]);
  } else {
    $pdo->prepare("UPDATE licenses SET credentials=?, status=?, updated_by=? WHERE license_type=?")
        ->execute([$credsJson,$status,(int)$_SESSION['user_id'],$type]);
  }
}catch(Throwable $e){
  if($newCert) FileStore::removePrivate($newCert['rel']);
  error_log('licenses/update.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ذخیره‌سازی.'],500);
}
if($newCert && $oldCert){                                 // after the row changed: drop the previous certificate
  if(isSafePrivatePath((string)$oldCert)) FileStore::removePrivate((string)$oldCert);
  else FileStore::remove((string)$oldCert);               // legacy "/uploads/licenses/…" value (no-op when not a safe public path)
}
logActivity('license_update','licenses','بروزرسانی مجوز '.$type);
jsonResponse(['success'=>true,'message'=>'اطلاعات مجوز بروزرسانی شد']);
