<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/file_store.php';
requireLogin(); requirePermission('templates','edit');
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
$id=(int)($_POST['id']??0);
if(!$id) jsonResponse(['success'=>false,'message'=>'شناسه نامعتبر'],400);
$pdo=getPDO();
$st=$pdo->prepare("SELECT id, source, package_path, is_installed FROM templates WHERE id=?"); $st->execute([$id]); $tpl=$st->fetch();
if(!$tpl) jsonResponse(['success'=>false,'message'=>'قالب یافت نشد'],404);
if((int)$tpl['is_installed']) jsonResponse(['success'=>true,'message'=>'قالب نصب شد']);          // idempotent
if($tpl['source']==='uploaded' && FileStore::privateAbsolute($tpl['package_path'])===null)
  jsonResponse(['success'=>false,'message'=>'فایل بسته‌ی قالب پیدا نشد'],400);                   // STR_PACKAGE_MISSING
$pdo->prepare("UPDATE templates SET is_installed=1, installed_at=NOW() WHERE id=? AND is_installed=0")->execute([$id]);
logActivity('install','templates','نصب قالب '.$id);
jsonResponse(['success'=>true,'message'=>'قالب نصب شد']);
