<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
$pdo=getPDO();

function staffTarget(PDO $pdo, int $uid): ?array {
  if ($uid < 1) return null;
  $st=$pdo->prepare("SELECT id, role, status FROM panel_users WHERE id=? LIMIT 1");
  $st->execute([$uid]);
  $t=$st->fetch();
  if(!$t || $t['role']!=='staff' || $t['status']==='removed') return null;
  return $t;
}

if($_SERVER['REQUEST_METHOD']==='GET'){
  $uid=(int)($_GET['user_id']??0);
  if(!staffTarget($pdo,$uid)) jsonResponse(['success'=>false,'message'=>'کاربر نامعتبر'],400);
  $stmt=$pdo->prepare("SELECT p.module_key, COALESCE(pup.can_view,0) as can_view, COALESCE(pup.can_edit,0) as can_edit, COALESCE(pup.can_delete,0) as can_delete FROM permissions p LEFT JOIN panel_user_permissions pup ON pup.permission_id=p.id AND pup.panel_user_id=? ORDER BY p.id");
  $stmt->execute([$uid]); $rows=$stmt->fetchAll();
  foreach($rows as &$r){
    $f=normalizePermissionFlags(!empty($r['can_view']),!empty($r['can_edit']),!empty($r['can_delete']));
    $r['can_view']=(int)$f['view']; $r['can_edit']=(int)$f['edit']; $r['can_delete']=(int)$f['delete'];
  }
  unset($r);
  jsonResponse(['success'=>true,'data'=>$rows]);
}
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$uid=(int)($_POST['user_id']??0);
if(!staffTarget($pdo,$uid)) jsonResponse(['success'=>false,'message'=>'کاربر نامعتبر'],400);
$perms=$pdo->query("SELECT id FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
$posted=$_POST['perm'] ?? []; if(!is_array($posted)) $posted=[];
try{
  $pdo->beginTransaction();
  $stmt=$pdo->prepare("INSERT INTO panel_user_permissions (panel_user_id, permission_id, can_view, can_edit, can_delete) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_edit=VALUES(can_edit), can_delete=VALUES(can_delete)");
  foreach($perms as $pid){
    $in=(isset($posted[$pid]) && is_array($posted[$pid])) ? $posted[$pid] : [];
    $f=normalizePermissionFlags(isset($in['view']),isset($in['edit']),isset($in['delete']));
    $stmt->execute([$uid,$pid,(int)$f['view'],(int)$f['edit'],(int)$f['delete']]);
  }
  $pdo->commit();
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  error_log('staff/save_permissions.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ذخیره‌سازی دسترسی‌ها'],500);
}
logActivity('staff_permissions','settings','تغییر دسترسی‌های همکار '.$uid);
jsonResponse(['success'=>true,'message'=>'دسترسی‌ها ذخیره شد']);
