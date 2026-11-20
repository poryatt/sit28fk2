<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/csrf.php';
requireLogin();
if(!csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')){ jsonResponse(['success'=>false,'message'=>'توکن نامعتبر'],403); }
$pdo=getPDO();
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE panel_user_id=?")->execute([$_SESSION['user_id']]);
jsonResponse(['success'=>true,'message'=>'اعلان‌ها خوانده شد']);
