<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
require_once __DIR__ . '/../../../includes/media_registry.php';
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
$pdo=getPDO();
$st=$pdo->prepare("SELECT id, role, status FROM panel_users WHERE id=? LIMIT 1");
$st->execute([$id]);
$target=$st->fetch();
if(!$target || $target['role']!=='staff' || $target['status']==='removed'){
  jsonResponse(['success'=>false,'message'=>'کاربر نامعتبر'],400);
}
// Soft delete (D12): the row stays so created_by / uploaded_by / activity_logs keep meaning.
// Username and e-mail are anonymised so the UNIQUE keys are free again; the hash can never verify.
$unusableHash='!removed!'.bin2hex(random_bytes(16));
$av=$pdo->prepare("SELECT avatar_path FROM panel_users WHERE id=?"); $av->execute([$id]); $oldAvatar=$av->fetchColumn();
try{
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE panel_users
                    SET status='removed', removed_at=NOW(),
                        username=CONCAT('removed-', id), email=CONCAT('removed-', id, '@invalid.local'),
                        phone=NULL, avatar_path=NULL, password_hash=?, must_change_password=0, password_changed_at=?
                  WHERE id=? AND role='staff'")
      ->execute([$unusableHash,date('Y-m-d H:i:s'),$id]);
  $pdo->prepare("DELETE FROM panel_user_permissions WHERE panel_user_id=?")->execute([$id]);
  $pdo->commit();
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  error_log('staff/remove.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در حذف همکار'],500);
}
if($oldAvatar){ mediaReleasePath($pdo,(string)$oldAvatar,'avatar'); }   // after commit; never throws
logActivity('staff_remove','settings','حذف همکار '.$id);
jsonResponse(['success'=>true,'message'=>'همکار حذف شد']);
