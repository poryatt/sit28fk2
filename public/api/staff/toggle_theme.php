<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin();
if($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'message'=>'متد مجاز نیست'],405);
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$pdo=getPDO();
$user=currentUser();
// Idempotent form: an explicit `theme` (light|dark) sets exactly that value, so two quick clicks or two tabs cannot invert it.
// Without the field the old toggle behaviour is kept (backward compatible).
$requested=$_POST['theme'] ?? null;
if($requested==='light' || $requested==='dark'){ $new=$requested; }
else { $new=$user['theme_preference']==='dark'?'light':'dark'; }
$pdo->prepare("UPDATE panel_users SET theme_preference=? WHERE id=?")->execute([$new,$user['id']]);
jsonResponse(['success'=>true,'theme'=>$new]);
