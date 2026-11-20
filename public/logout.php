<?php
require_once __DIR__ . '/../includes/auth.php';
logActivity('logout', 'auth', 'خروج از پنل');
logoutUser();
header('Location: /login.php'); exit;
