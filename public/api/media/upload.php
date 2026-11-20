<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/media_registry.php';
requireLogin(); requirePermission('media','edit');
// GET ?id=… : info about one library file
if($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['id'])){
  $pdo=getPDO();
  $stmt=$pdo->prepare("SELECT id, file_name, file_path, file_type, mime_type, file_size_kb, width, height, created_at FROM media_library WHERE id=? AND purpose='library'"); $stmt->execute([(int)$_GET['id']]);
  $row=$stmt->fetch(); if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
  jsonResponse(['success'=>true,'data'=>$row]);
}
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }

$file=$_FILES['file'] ?? null;
if(!is_array($file)) jsonResponse(['success'=>false,'message'=>'فایلی ارسال نشده'],400);
if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) jsonResponse(['success'=>false,'message'=>uploadErrorMessage((int)$file['error'])],400);

// Decide image vs video from the real content, never from the client's name or type.
$finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=$finfo? (string)finfo_file($finfo,(string)$file['tmp_name']) : '';
if($finfo) finfo_close($finfo);
$isImage=imageExtForMime($mime)!==null; $isVideo=videoExtForMime($mime)!==null;
if(!$isImage && !$isVideo) jsonResponse(['success'=>false,'message'=>'فرمت فایل مجاز نیست (jpg/png/webp/mp4/webm)'],400);

try{
  $stored=$isImage ? FileStore::storeImage($file,'media') : FileStore::storeVideo($file);
}catch(FileStoreException $e){
  jsonResponse(['success'=>false,'message'=>$e->getMessage()],400);
}

$pdo=getPDO();
try{
  $reg=mediaRegisterFile($pdo,$stored,(string)($file['name']??'file'),'library',(int)$_SESSION['user_id']);
}catch(Throwable $e){
  FileStore::remove($stored['path']);               // no row ⇒ the file must not stay behind
  error_log('media/upload.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ذخیره فایل'],500);
}
if($reg['reused']){
  jsonResponse(['success'=>true,'duplicate'=>true,'message'=>'این فایل قبلاً آپلود شده است','data'=>['id'=>$reg['id'],'file_path'=>$reg['path']]]);
}
logActivity('media_upload','media','آپلود رسانه '.$reg['id']);
jsonResponse(['success'=>true,'message'=>'آپلود موفق','data'=>['id'=>$reg['id'],'file_path'=>$reg['path'],'file_name'=>sanitizeClientFileName((string)($file['name']??'file'))]]);
