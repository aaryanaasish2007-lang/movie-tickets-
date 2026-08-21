<?php
require_once '../config/db.php';

// Restore admin session from impersonation
if (isset($_SESSION['admin_backup'])) {
    $backup = $_SESSION['admin_backup'];
    $_SESSION['user_id']   = $backup['user_id'];
    $_SESSION['user_name'] = $backup['user_name'];
    $_SESSION['user_role'] = $backup['user_role'];
    unset($_SESSION['admin_backup']);
    unset($_SESSION['impersonating']);
}

header("Location: index.php");
exit;
