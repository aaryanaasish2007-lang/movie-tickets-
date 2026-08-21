<?php
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineTicket - Modern Movie Booking</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="index.php" class="logo">Cine<span>Ticket</span></a>
        
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#movies">Movies</a></li>
            <li><a href="theatres.php">Theatres</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>

        <div class="auth-buttons">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="btn btn-login">Dashboard</a>
                <a href="logout.php" class="btn btn-register">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-login">Log In</a>
                <a href="register.php" class="btn btn-register">Sign Up</a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if (isset($_SESSION['impersonating']) && $_SESSION['impersonating']): ?>
    <div style="background:rgba(229,9,20,0.15); border-bottom:2px solid #E50914; padding:.6rem 5%; display:flex; align-items:center; gap:1rem; font-size:.88rem; color:#f87171; position:sticky; top:65px; z-index:999;">
        <i class="fas fa-user-secret" style="font-size:1rem;"></i>
        <span>You are viewing the site as <strong style="color:white;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> (impersonation mode)</span>
        <a href="admin/restore_admin.php" style="margin-left:auto; background:#E50914; color:white; padding:.35rem .9rem; border-radius:6px; font-weight:700; text-decoration:none; font-size:.8rem;">
            <i class="fas fa-undo"></i> Back to Admin
        </a>
    </div>
    <?php endif; ?>
