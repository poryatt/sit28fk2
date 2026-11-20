<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin(); requirePermission('templates','edit');
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
if(!$id) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();
$st=$pdo->prepare("SELECT id, is_installed FROM templates WHERE id=?"); $st->execute([$id]); $tpl=$st->fetch();
if(!$tpl) jsonResponse(['success'=>false,'message'=>'قالب یافت نشد'],404);
if(!(int)$tpl['is_installed']) jsonResponse(['success'=>false,'message'=>'قالب ابتدا باید نصب شود'],400);
try{
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE site_settings SET active_template_id=? WHERE id=1")->execute([$id]);
  // Part 8 / D1: sync the new active template's canvas title into site_name.
  $titleStmt=$pdo->prepare("SELECT title FROM builder_canvases WHERE template_id = ? AND title <> '' LIMIT 1");
  $titleStmt->execute([$id]);
  $tplTitle=$titleStmt->fetchColumn();
  if($tplTitle!==false && $tplTitle!==null && $tplTitle!==''){
    $pdo->prepare("UPDATE site_settings SET site_name = ? WHERE id = 1")->execute([$tplTitle]);
  }
  $pdo->commit();
  logActivity('activate','templates','فعال‌سازی قالب '.$id);
  jsonResponse(['success'=>true,'message'=>'قالب با موفقیت فعال شد']);
}catch(Throwable $e){
  if($pdo->inTransaction()){ $pdo->rollBack(); }
  error_log('templates/activate.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در فعال‌سازی قالب'],500);   // STR_ACTIVATE_ERROR — never echo $e->getMessage()
}
