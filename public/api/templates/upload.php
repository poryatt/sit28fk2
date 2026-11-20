<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
requireLogin(); requirePermission('templates','edit');
if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک می‌تواند قالب آپلود کند'],403);
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$file=$_FILES['template_zip'] ?? null;
if(!is_array($file) || (int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) jsonResponse(['success'=>false,'message'=>'فایلی انتخاب نشده'],400);

$name=sanitizeClientFileName(pathinfo((string)($file['name']??''),PATHINFO_FILENAME));
if(mb_strlen($name,'UTF-8')>150) jsonResponse(['success'=>false,'message'=>'نام قالب بیش از ۱۵۰ کاراکتر است'],400);   // checked BEFORE the file is stored

try{
  $stored=FileStore::storePrivate($file,'templates',['application/zip'=>'zip','application/x-zip-compressed'=>'zip','multipart/x-zip'=>'zip'],MAX_TEMPLATE_ZIP_BYTES);
}catch(FileStoreException $e){
  jsonResponse(['success'=>false,'message'=>$e->getMessage()],400);
}

// Inspect the archive WITHOUT extracting it: zip-slip, symlinks, forbidden extensions, entry count, unpacked size (zip bomb).
function rejectZip(array $stored, string $message): void {
  FileStore::removePrivate($stored['rel']);
  jsonResponse(['success'=>false,'message'=>$message],400);
}
$zip=new ZipArchive();
if($zip->open($stored['abs'],ZipArchive::RDONLY)!==true) rejectZip($stored,'فایل ZIP خراب است');
if($zip->numFiles<1){ $zip->close(); rejectZip($stored,'ZIP خالی است'); }
if($zip->numFiles>MAX_ZIP_FILES){ $zip->close(); rejectZip($stored,'تعداد فایل‌های ZIP بیش از حد مجاز است'); }
$unpacked=0;
for($i=0;$i<$zip->numFiles;$i++){
  $st=$zip->statIndex($i);
  $entry=(string)($st['name']??'');
  if(!zipEntryIsSafe($entry)){ $zip->close(); rejectZip($stored,'ساختار فایل ZIP امن نیست'); }
  $opsys=0; $attr=0;
  if($zip->getExternalAttributesIndex($i,$opsys,$attr) && $opsys===ZipArchive::OPSYS_UNIX && ((($attr>>16)&0170000)===0120000)){
    $zip->close(); rejectZip($stored,'ساختار فایل ZIP امن نیست');                       // symlink entry
  }
  if(!zipEntryExtensionAllowed($entry)){ $zip->close(); rejectZip($stored,'فایل ZIP شامل فایل غیرمجاز است'); }
  $unpacked+=(int)($st['size']??0);
  if($unpacked>MAX_ZIP_UNPACKED_BYTES){ $zip->close(); rejectZip($stored,'حجم بازشده‌ی ZIP بیش از حد مجاز است'); }
}
$zip->close();

$pdo=getPDO();
try{
  $pdo->prepare("INSERT INTO templates (category_id, name, version, thumbnail_path, source, is_installed, uploaded_by, package_path, package_sha256) VALUES (NULL, ?, '1.0.0', NULL, 'uploaded', 0, ?, ?, ?)")
      ->execute([$name,(int)$_SESSION['user_id'],$stored['rel'],$stored['sha256']]);
  $newId=(int)$pdo->lastInsertId();
}catch(Throwable $e){
  FileStore::removePrivate($stored['rel']);                       // no row ⇒ no orphan file
  error_log('templates/upload.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ذخیره‌سازی.'],500);
}
logActivity('template_upload','templates','آپلود قالب '.$newId);
jsonResponse(['success'=>true,'message'=>'قالب با موفقیت آپلود شد']);
