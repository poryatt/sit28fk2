<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin(); if(!isOwner()) jsonResponse(['success'=>false,'message'=>'فقط مالک'],403);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$full_name=trim($_POST['full_name']??''); $username=trim($_POST['username']??''); $email=trim($_POST['email']??''); $password=$_POST['password']??'';
if($full_name===''||$username===''||$email===''||$password==='') jsonResponse(['success'=>false,'message'=>'تمام فیلدها الزامی است'],400);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'message'=>'ایمیل نامعتبر'],400);
if(!preg_match('/^[A-Za-z0-9_.-]{3,30}$/D',$username) || stripos($username,'removed-')===0) jsonResponse(['success'=>false,'message'=>'نام کاربری باید ۳ تا ۳۰ کاراکتر و فقط شامل حروف انگلیسی، عدد و . _ - باشد'],400);
if(($pwErr=validatePasswordPolicy($password))!==null) jsonResponse(['success'=>false,'message'=>$pwErr],400);
if(mb_strlen($full_name,'UTF-8')>150 || strlen($email)>190) jsonResponse(['success'=>false,'message'=>'درخواست نامعتبر'],400);
$hash=hashPassword($password);
$pdo=getPDO();
try{
  $pdo->beginTransaction();
  // must_change_password=1: the owner knows the first password, so the new colleague must replace it (D13).
  $pdo->prepare("INSERT INTO panel_users (full_name, username, email, password_hash, must_change_password, role) VALUES (?,?,?,?,1,'staff')")
      ->execute([$full_name,$username,$email,$hash]);
  $newId=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO panel_user_permissions (panel_user_id, permission_id, can_view, can_edit, can_delete)
                 SELECT ?, id, 1, 0, 0 FROM permissions WHERE module_key = 'dashboard'")->execute([$newId]);
  $pdo->commit();
}catch(PDOException $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  if((int)($e->errorInfo[1]??0)===1062) jsonResponse(['success'=>false,'message'=>'نام کاربری یا ایمیل تکراری است'],400);
  error_log('staff/invite.php: '.$e->getMessage());
  jsonResponse(['success'=>false,'message'=>'خطا در ایجاد همکار'],500);
}
logActivity('staff_invite','settings','افزودن همکار '.$newId);
jsonResponse(['success'=>true,'message'=>'همکار با موفقیت ایجاد شد']);
