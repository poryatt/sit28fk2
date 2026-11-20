<?php
// Serves private files (licence certificates, template packages) from storage/private/ after a permission check.
// Not under /modules/, so it does its own checks instead of the page guard.   /file.php?type=license|template&id=N
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/file_store.php';
requireLogin();

$type=(string)($_GET['type']??''); $id=(int)($_GET['id']??0);
$pdo=getPDO();
if($type==='license'){
  requirePermission('licenses','view');
  $st=$pdo->prepare("SELECT certificate_file FROM licenses WHERE id=?");
} elseif($type==='template'){
  requireOwner();                                  // the package is source code: owner only
  $st=$pdo->prepare("SELECT package_path FROM templates WHERE id=?");
} else { http_response_code(404); exit; }
$st->execute([$id]);
$rel=$st->fetchColumn();
$abs=($rel===false || $rel===null) ? null : FileStore::privateAbsolute((string)$rel);
if($abs===null){ http_response_code(404); exit; }

// The Content-Type comes from OUR extension whitelist, never from the client or the file content.
$ext=strtolower(pathinfo($abs,PATHINFO_EXTENSION));
$types=['pdf'=>'application/pdf','jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','zip'=>'application/zip'];
if(!isset($types[$ext])){ http_response_code(404); exit; }
header('Content-Type: '.$types[$ext]);
header('Content-Length: '.filesize($abs));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Disposition: '.($ext==='zip'?'attachment':'inline').'; filename="'.($type==='license'?'certificate-':'template-').$id.'.'.$ext.'"');
readfile($abs);
exit;
