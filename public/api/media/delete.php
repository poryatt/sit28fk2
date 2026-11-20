<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/builder/media_refs.php';
require_once __DIR__ . '/../../../includes/file_store.php';
requireLogin(); requirePermission('media','delete');
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
if(!$id) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();

function mediaInUseResponse(PDO $pdo, int $id): void {
  $total=BuilderMediaRefs::countUsing($pdo,$id);
  $titles=array_map(function($c){ return $c['title']; }, BuilderMediaRefs::canvasesUsing($pdo,$id,6));
  $msg='این فایل در صفحه‌های زیر استفاده شده و قابل حذف نیست: '.implode('، ',$titles);
  if($total>count($titles)) $msg.=' و '.toPersianDigits($total-count($titles)).' مورد دیگر';
  jsonResponse(['success'=>false,'message'=>$msg],409);
}

// Only library files can be deleted here: avatars and site assets are released by their own screens.
$m=$pdo->prepare("SELECT id, file_path FROM media_library WHERE id=? AND purpose='library'"); $m->execute([$id]); $row=$m->fetch();
if(!$row) jsonResponse(['success'=>false,'message'=>'یافت نشد'],404);
if(BuilderMediaRefs::countUsing($pdo,$id)>0) mediaInUseResponse($pdo,$id);
try{
  $pdo->prepare("DELETE FROM media_library WHERE id=? AND purpose='library'")->execute([$id]);
}catch(PDOException $e){
  if((int)($e->errorInfo[1]??0)===1451){ mediaInUseResponse($pdo,$id); }
  error_log('media/delete.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در حذف فایل'],500);
}
FileStore::remove((string)$row['file_path']);       // after the row is gone; logs (never throws) when the unlink fails
logActivity('media_delete','media','حذف رسانه '.$id);
jsonResponse(['success'=>true,'message'=>'فایل حذف شد']);
