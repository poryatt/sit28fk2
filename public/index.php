<?php
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: /login.php'); exit; }
header('Location: /modules/dashboard/index.php'); exit;
