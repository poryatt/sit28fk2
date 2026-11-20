<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$id=(int)($_POST['id']??0);
$status=(string)($_POST['status']??'');
if(!in_array($status,['active','suspended'],true)) jsonResponse(['success'=>false,'message'=>'درخواست نامعتبر'],400);
$pdo=getPDO();
$st=$pdo->prepare("SELECT id, role, status FROM panel_users WHERE id=? LIMIT 1");
$st->execute([$id]);
$target=$st->fetch();
if(!$target || $target['role']!=='staff' || $target['status']==='removed') jsonResponse(['success'=>false,'message'=>'کاربر نامعتبر'],400);
try{
  $pdo->prepare("UPDATE panel_users SET status=? WHERE id=? AND role='staff'")->execute([$status,$id]);
}catch(Throwable $e){
  error_log('staff/set_status.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در تغییر وضعیت'],500);
}
logActivity('staff_status','settings','تغییر وضعیت همکار '.$id.' به '.$status);
jsonResponse(['success'=>true,'message'=>'وضعیت همکار تغییر کرد']);
