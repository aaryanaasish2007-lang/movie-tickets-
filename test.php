<?php
require 'c:\xampp\htdocs\sample\config\db.php';
$stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
$stmt->execute([password_hash('admin', PASSWORD_DEFAULT), 'admin@gmail.com']);
echo 'Password updated for admin@gmail.com with password: admin';
?>
