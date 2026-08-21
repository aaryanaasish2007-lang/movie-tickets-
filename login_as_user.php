<?php
require_once '../config/db.php';

// Must be admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$target_user_id = (int)$_POST['target_user_id'];
$admin_password = $_POST['admin_password'] ?? '';

// Fetch admin's own record
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($admin_password, $admin['password'])) {
    // Wrong password – redirect back with error
    header("Location: index.php?tab=users&error=wrong_password");
    exit;
}

// Fetch target user
$stmt2 = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
$stmt2->execute([$target_user_id]);
$target = $stmt2->fetch();

if (!$target) {
    header("Location: index.php?tab=users&error=user_not_found");
    exit;
}

// Save admin session so we can restore
$_SESSION['admin_backup'] = [
    'user_id'   => $_SESSION['user_id'],
    'user_name' => $_SESSION['user_name'],
    'user_role' => $_SESSION['user_role'],
];

// Impersonate
$_SESSION['user_id']   = $target['id'];
$_SESSION['user_name'] = $target['name'];
$_SESSION['user_role'] = $target['role'];
$_SESSION['impersonating'] = true;

header("Location: ../dashboard.php");
exit;
