<?php
require_once '../config/db.php';

// Force Admin Login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch stats
$total_movies   = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$total_users    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_theatres = $pdo->query("SELECT COUNT(*) FROM theatres")->fetchColumn();
$total_shows    = $pdo->query("SELECT COUNT(*) FROM shows")->fetchColumn();
$total_revenue  = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
if (!$total_revenue) $total_revenue = 0;

// Fetch recent bookings
$recent_bookings = $pdo->query("
    SELECT b.id, u.name as user_name, m.title, b.seat_numbers, b.total_amount, b.booking_date, b.status
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN shows s ON b.show_id = s.id
    JOIN movies m ON s.movie_id = m.id
    ORDER BY b.booking_date DESC LIMIT 10
")->fetchAll();

// Fetch all users for Users tab
$all_users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC")->fetchAll();

$error_msg = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'wrong_password') $error_msg = 'Incorrect admin password. Impersonation denied.';
    elseif ($_GET['error'] === 'user_not_found') $error_msg = 'User not found.';
}

// Fetch contact messages (for admin)
$unread_msgs = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='unread'")->fetchColumn();
$all_msgs    = $pdo->query("SELECT * FROM contact_messages ORDER BY submitted_at DESC LIMIT 50")->fetchAll();

// Mark as read if viewing messages tab
if (isset($_GET['tab']) && $_GET['tab'] === 'messages') {
    $pdo->exec("UPDATE contact_messages SET status='read' WHERE status='unread'");
    $unread_msgs = 0;
}

// Handle message deletion
if (isset($_POST['delete_msg_id'])) {
    $del = $pdo->prepare("DELETE FROM contact_messages WHERE id=?");
    $del->execute([(int)$_POST['delete_msg_id']]);
    header("Location: ?tab=messages");
    exit;
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – CineTicket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #E50914;
            --primary-dark: #b0060f;
            --sidebar-bg: #111;
            --main-bg: #0A0A0A;
            --card-bg: #1A1A1A;
            --text: #f1f1f1;
            --muted: #888;
            --border: rgba(255,255,255,0.08);
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        body { display:flex; background:var(--main-bg); color:var(--text); min-height:100vh; }

        /* ── Sidebar ── */
        .sidebar { width:260px; background:var(--sidebar-bg); display:flex; flex-direction:column; border-right:1px solid var(--border); position:sticky; top:0; height:100vh; overflow-y:auto; flex-shrink:0; }
        .sidebar-logo { padding:1.8rem 1.5rem; font-size:1.4rem; font-weight:800; color:var(--primary); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:.6rem; }
        .sidebar-logo span { color:var(--text); }
        .nav-group-label { font-size:.65rem; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); padding:.8rem 1.5rem .3rem; }
        .nav-links { list-style:none; }
        .nav-links li a {
            display:flex; align-items:center; gap:.8rem;
            padding:.85rem 1.5rem; color:#aaa; text-decoration:none; font-size:.9rem; font-weight:500;
            transition: all .25s; border-left:3px solid transparent;
        }
        .nav-links li a i { width:18px; text-align:center; }
        .nav-links li a:hover { color:var(--text); background:rgba(255,255,255,.04); }
        .nav-links li a.active { color:var(--primary); background:rgba(229,9,20,.08); border-left-color:var(--primary); }
        .sidebar-footer { margin-top:auto; border-top:1px solid var(--border); }

        /* ── Main ── */
        .main-content { flex:1; padding:2rem; overflow-y:auto; min-width:0; }
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .page-header h2 { font-size:1.6rem; }
        .page-header .admin-badge { background:rgba(229,9,20,.15); color:var(--primary); border:1px solid rgba(229,9,20,.3); padding:.3rem .9rem; border-radius:20px; font-size:.8rem; font-weight:700; }

        /* Impersonation bar */
        .impersonation-bar { background: rgba(229,9,20,0.12); border:1px solid rgba(229,9,20,0.3); border-radius:8px; padding:.8rem 1.2rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem; color:#f87171; font-size:.9rem; }
        .impersonation-bar a { color:var(--primary); font-weight:700; text-decoration:none; margin-left:auto; }

        /* Alert */
        .alert-err { background:rgba(229,9,20,.12); border:1px solid rgba(229,9,20,.3); color:#f87171; border-radius:8px; padding:.9rem 1.2rem; margin-bottom:1.5rem; font-size:.9rem; display:flex; align-items:center; gap:.7rem; }

        /* Stats grid */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1.2rem; margin-bottom:2rem; }
        .stat-card {
            background:var(--card-bg); padding:1.4rem; border-radius:12px;
            border:1px solid var(--border); display:flex; align-items:center; gap:1rem;
            transition: border-color .3s, transform .3s;
        }
        .stat-card:hover { border-color:var(--primary); transform:translateY(-3px); }
        .stat-icon { font-size:2rem; color:var(--primary); width:48px; text-align:center; }
        .stat-info h3 { font-size:1.7rem; margin-bottom:.1rem; }
        .stat-info p { color:var(--muted); font-size:.82rem; }

        /* Tables */
        .section-card { background:var(--card-bg); border-radius:14px; border:1px solid var(--border); overflow:hidden; margin-bottom:1.5rem; }
        .section-card-header { display:flex; justify-content:space-between; align-items:center; padding:1.2rem 1.5rem; border-bottom:1px solid var(--border); }
        .section-card-header h3 { font-size:1rem; }
        .section-card-header a, .section-card-header button { font-size:.82rem; color:var(--primary); font-weight:600; text-decoration:none; background:none; border:none; cursor:pointer; }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th { padding:.9rem 1.2rem; text-align:left; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); font-weight:600; border-bottom:1px solid var(--border); }
        .data-table td { padding:1rem 1.2rem; font-size:.88rem; border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle; }
        .data-table tr:last-child td { border-bottom:none; }
        .data-table tr:hover td { background:rgba(255,255,255,.03); }

        /* Status badges */
        .badge { padding:.25rem .7rem; border-radius:20px; font-size:.72rem; font-weight:700; text-transform:uppercase; }
        .badge-confirmed { background:rgba(34,197,94,.15); color:#4ade80; }
        .badge-pending   { background:rgba(245,158,11,.15); color:#fbbf24; }
        .badge-cancelled { background:rgba(229,9,20,.15);  color:#f87171; }

        /* Action btn */
        .btn-sm { padding:.35rem .9rem; border-radius:6px; font-size:.78rem; font-weight:600; cursor:pointer; border:none; transition:all .25s; }
        .btn-danger { background:rgba(229,9,20,.15); color:#f87171; border:1px solid rgba(229,9,20,.3); }
        .btn-danger:hover { background:var(--primary); color:white; }
        .btn-primary { background:var(--primary); color:white; }
        .btn-primary:hover { background:var(--primary-dark); }
        .btn-outline { background:transparent; color:var(--muted); border:1px solid var(--border); }
        .btn-outline:hover { border-color:var(--primary); color:var(--primary); }

        /* ── Modal ── */
        .modal-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,.75);
            backdrop-filter:blur(6px); z-index:1000; align-items:center; justify-content:center;
        }
        .modal-overlay.open { display:flex; }
        .modal-box {
            background:#1E1E1E; border:1px solid var(--border); border-radius:16px;
            padding:2rem; width:420px; max-width:95vw;
            animation: slideUp .3s ease;
        }
        @keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
        .modal-box h3 { font-size:1.1rem; margin-bottom:.4rem; }
        .modal-box p { color:var(--muted); font-size:.85rem; margin-bottom:1.5rem; line-height:1.6; }
        .modal-box .user-info { background:rgba(255,255,255,.04); border-radius:8px; padding:.9rem 1rem; margin-bottom:1.5rem; display:flex; gap:.8rem; align-items:center; }
        .modal-box .user-info .avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#E50914,#ff6b35); display:flex; align-items:center; justify-content:center; font-weight:800; flex-shrink:0; }
        .modal-box .user-info strong { display:block; font-size:.9rem; }
        .modal-box .user-info span { color:var(--muted); font-size:.78rem; }
        .form-group { margin-bottom:1rem; }
        .form-group label { display:block; font-size:.82rem; color:var(--muted); margin-bottom:.4rem; }
        .form-group input { width:100%; background:rgba(255,255,255,.06); border:1px solid var(--border); border-radius:8px; padding:.75rem 1rem; color:var(--text); font-size:.9rem; outline:none; transition:border-color .25s; }
        .form-group input:focus { border-color:var(--primary); }
        .modal-actions { display:flex; gap:.8rem; margin-top:1.5rem; }
        .modal-actions button { flex:1; padding:.8rem; border-radius:8px; font-weight:600; font-size:.9rem; cursor:pointer; border:none; transition:all .25s; }
        .modal-cancel { background:rgba(255,255,255,.07); color:var(--text); }
        .modal-cancel:hover { background:rgba(255,255,255,.12); }
        .modal-confirm { background:var(--primary); color:white; }
        .modal-confirm:hover { background:var(--primary-dark); }

        /* Tabs content sections */
        .tab-section { display:none; }
        .tab-section.active { display:block; }

        /* Scrollable overflow */
        .overflow-x { overflow-x:auto; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-logo"><i class="fas fa-film"></i>Cine<span>Ticket</span></div>

    <div class="nav-group-label">Main</div>
    <ul class="nav-links">
        <li><a href="?tab=dashboard" class="<?php echo $active_tab=='dashboard' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
    </ul>

    <div class="nav-group-label">Manage</div>
    <ul class="nav-links">
        <li><a href="movies.php"><i class="fas fa-film"></i> Movies</a></li>
        <li><a href="theatres.php"><i class="fas fa-building"></i> Theatres</a></li>
        <li><a href="shows.php"><i class="fas fa-video"></i> Shows</a></li>
        <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
        <li><a href="?tab=users"     class="<?php echo $active_tab=='users'    ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="?tab=messages"  class="<?php echo $active_tab=='messages' ? 'active' : ''; ?>" style="position:relative;">
            <i class="fas fa-envelope"></i> Messages
            <?php if($unread_msgs > 0): ?>
                <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:var(--primary);color:#fff;font-size:.65rem;font-weight:800;padding:.1rem .45rem;border-radius:10px;min-width:18px;text-align:center;"><?php echo $unread_msgs; ?></span>
            <?php endif; ?>
        </a></li>
    </ul>

    <div class="nav-group-label">Config</div>
    <ul class="nav-links">
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>

    <div class="sidebar-footer">
        <ul class="nav-links">
            <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="page-header">
        <h2><?php echo $active_tab === 'users' ? 'User Management' : 'Dashboard Overview'; ?></h2>
        <span class="admin-badge"><i class="fas fa-shield-alt"></i> Admin: <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
    </div>

    <?php if ($error_msg): ?>
    <div class="alert-err"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- ══════════ DASHBOARD TAB ══════════ -->
    <div class="tab-section <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-film"></i></div>
                <div class="stat-info"><h3><?php echo $total_movies; ?></h3><p>Total Movies</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-info"><h3><?php echo $total_theatres; ?></h3><p>Theatres</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-video"></i></div>
                <div class="stat-info"><h3><?php echo $total_shows; ?></h3><p>Active Shows</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info"><h3><?php echo $total_users; ?></h3><p>Registered Users</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="stat-info"><h3><?php echo $total_bookings; ?></h3><p>Total Bookings</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                <div class="stat-info"><h3>₹<?php echo number_format($total_revenue, 0); ?></h3><p>Total Revenue</p></div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="section-card">
            <div class="section-card-header">
                <h3><i class="fas fa-clock" style="color:var(--primary); margin-right:.5rem;"></i> Recent Bookings</h3>
                <a href="bookings.php">View All →</a>
            </div>
            <div class="overflow-x">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#ID</th><th>User</th><th>Movie</th><th>Seats</th><th>Amount</th><th>Date</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_bookings) > 0): ?>
                        <?php foreach ($recent_bookings as $b): ?>
                        <tr>
                            <td style="color:var(--muted)">#<?php echo $b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['title']); ?></td>
                            <td style="font-size:.8rem;"><?php echo htmlspecialchars($b['seat_numbers']); ?></td>
                            <td style="color:#fbbf24;font-weight:700;">₹<?php echo number_format($b['total_amount'],2); ?></td>
                            <td style="color:var(--muted);font-size:.82rem;"><?php echo date('d M Y, h:i A', strtotime($b['booking_date'])); ?></td>
                            <td><span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--muted); padding:2rem;">No bookings yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- ══════════ USERS TAB ══════════ -->
    <div class="tab-section <?php echo $active_tab === 'users' ? 'active' : ''; ?>">
        <div class="section-card">
            <div class="section-card-header">
                <h3><i class="fas fa-users" style="color:var(--primary); margin-right:.5rem;"></i> All Users (<?php echo count($all_users); ?>)</h3>
            </div>
            <div class="overflow-x">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th><th>Name</th><th>Email</th><th>Registered</th><th>Bookings</th><th>Login as User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $u):
                        $bcount = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=?");
                        $bcount->execute([$u['id']]);
                        $ub = $bcount->fetchColumn();
                    ?>
                    <tr>
                        <td style="color:var(--muted)"><?php echo $u['id']; ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:.7rem;">
                                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#E50914,#ff6b35);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($u['name'],0,1)); ?>
                                </div>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </div>
                        </td>
                        <td style="color:var(--muted); font-size:.85rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td style="color:var(--muted); font-size:.82rem;"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <a href="../dashboard.php?user_preview=<?php echo $u['id']; ?>" style="color:#60a5fa; font-size:.85rem; font-weight:600;">
                                <?php echo $ub; ?> booking<?php echo $ub!=1?'s':''; ?>
                            </a>
                        </td>
                        <td>
                            <button class="btn-sm btn-danger" onclick="openLoginModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['name'])); ?>', '<?php echo htmlspecialchars(addslashes($u['email'])); ?>')">
                                <i class="fas fa-user-secret"></i> Login as User
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($all_users) === 0): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No users registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- ══════════ MESSAGES TAB ══════════ -->
    <?php if ($active_tab === 'messages'): ?>
    <style>
        .msg-grid { display:flex; flex-direction:column; gap:1rem; }
        .msg-card {
            background:var(--card-bg);
            border:1px solid var(--border);
            border-radius:12px;
            padding:1.3rem 1.5rem;
            transition:border-color .25s;
            position:relative;
        }
        .msg-card.unread { border-left:3px solid var(--primary); }
        .msg-card:hover { border-color:rgba(229,9,20,0.35); }
        .msg-card-header { display:flex; align-items:center; gap:1rem; margin-bottom:.8rem; flex-wrap:wrap; }
        .msg-avatar {
            width:38px; height:38px; border-radius:50%;
            background:linear-gradient(135deg,var(--primary),#ff6b35);
            display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:1rem; color:#fff; flex-shrink:0;
        }
        .msg-meta { flex:1; min-width:0; }
        .msg-meta strong { font-size:.92rem; display:block; }
        .msg-meta span { font-size:.78rem; color:var(--muted); }
        .msg-subject {
            font-size:.85rem; font-weight:700;
            background:rgba(229,9,20,0.1); color:#f87171;
            border:1px solid rgba(229,9,20,0.2);
            padding:.2rem .65rem; border-radius:20px;
            white-space:nowrap;
        }
        .msg-status-badge { font-size:.7rem; font-weight:700; padding:.18rem .6rem; border-radius:4px; text-transform:uppercase; }
        .badge-unread { background:rgba(245,158,11,.15); color:#fbbf24; border:1px solid rgba(245,158,11,.3); }
        .badge-read   { background:rgba(34,197,94,.12);  color:#4ade80; border:1px solid rgba(34,197,94,.25); }
        .msg-body {
            font-size:.88rem; color:var(--muted);
            background:rgba(255,255,255,.03);
            border:1px solid var(--border);
            border-radius:8px; padding:.9rem 1rem;
            line-height:1.65; white-space:pre-wrap;
        }
        .msg-actions { margin-top:.9rem; display:flex; align-items:center; gap:.8rem; }
        .msg-timestamp { font-size:.75rem; color:var(--muted); margin-left:auto; }
        .btn-reply { background:rgba(59,130,246,.15); color:#60a5fa; border:1px solid rgba(59,130,246,.3); border-radius:6px; padding:.35rem .85rem; font-size:.78rem; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; transition:background .2s; }
        .btn-reply:hover { background:#3b82f6; color:#fff; }
        .btn-del-msg { background:rgba(229,9,20,.12); color:#f87171; border:1px solid rgba(229,9,20,.3); border-radius:6px; padding:.35rem .8rem; font-size:.78rem; font-weight:700; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:.4rem; transition:background .2s; }
        .btn-del-msg:hover { background:var(--primary); color:#fff; }
        .msg-empty { text-align:center; padding:4rem 2rem; color:var(--muted); }
        .msg-empty i { font-size:3rem; opacity:.3; display:block; margin-bottom:1rem; }
    </style>

    <div style="margin-top:0;">
        <div class="page-header" style="margin-bottom:1.5rem;">
            <h2 style="font-size:1.4rem;"><i class="fas fa-envelope" style="color:var(--primary);margin-right:.6rem;"></i> Contact Messages
                <span style="font-size:.85rem;font-weight:400;color:var(--muted);margin-left:.6rem;"><?php echo count($all_msgs); ?> total</span>
            </h2>
            <a href="../contact.php" target="_blank" class="btn btn-primary" style="font-size:.82rem;padding:.5rem 1rem;">
                <i class="fas fa-external-link-alt"></i> View Contact Page
            </a>
        </div>

        <?php if (count($all_msgs) > 0): ?>
        <div class="msg-grid">
            <?php foreach ($all_msgs as $m): ?>
            <div class="msg-card <?php echo $m['status']==='unread' ? 'unread' : ''; ?>">
                <div class="msg-card-header">
                    <div class="msg-avatar"><?php echo strtoupper(substr($m['name'],0,1)); ?></div>
                    <div class="msg-meta">
                        <strong><?php echo htmlspecialchars($m['name']); ?></strong>
                        <span><a href="mailto:<?php echo htmlspecialchars($m['email']); ?>" style="color:var(--muted);"><?php echo htmlspecialchars($m['email']); ?></a></span>
                    </div>
                    <span class="msg-subject"><?php echo htmlspecialchars($m['subject']); ?></span>
                    <span class="msg-status-badge badge-<?php echo $m['status']; ?>"><?php echo ucfirst($m['status']); ?></span>
                </div>
                <div class="msg-body"><?php echo htmlspecialchars($m['message']); ?></div>
                <div class="msg-actions">
                    <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>?subject=Re: <?php echo urlencode($m['subject']); ?>" class="btn-reply">
                        <i class="fas fa-reply"></i> Reply via Email
                    </a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?')">
                        <input type="hidden" name="delete_msg_id" value="<?php echo $m['id']; ?>">
                        <button type="submit" class="btn-del-msg"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                    <span class="msg-timestamp">
                        <i class="fas fa-clock"></i>
                        <?php echo date('d M Y, h:i A', strtotime($m['submitted_at'])); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="msg-empty">
            <i class="fas fa-inbox"></i>
            <p>No messages yet. When users contact you via the <a href="../contact.php" target="_blank" style="color:var(--primary);">Contact page</a>, they'll appear here.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</div>


<!-- ══════════ LOGIN AS USER MODAL ══════════ -->
<div class="modal-overlay" id="loginModal">
    <div class="modal-box">
        <h3><i class="fas fa-user-secret" style="color:var(--primary); margin-right:.5rem;"></i> Login as User</h3>
        <p>Enter your <strong>admin password</strong> to impersonate this user. You can restore your admin session anytime.</p>
        <div class="user-info" id="modalUserInfo">
            <div class="avatar" id="modalAvatar">?</div>
            <div>
                <strong id="modalUserName">–</strong>
                <span id="modalUserEmail">–</span>
            </div>
        </div>
        <form action="login_as_user.php" method="POST" id="loginAsUserForm">
            <input type="hidden" name="target_user_id" id="targetUserId">
            <div class="form-group">
                <label for="admin_password"><i class="fas fa-lock"></i> Your Admin Password</label>
                <input type="password" name="admin_password" id="adminPasswordField" placeholder="Enter admin password..." required>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeLoginModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="modal-confirm"><i class="fas fa-sign-in-alt"></i> Impersonate</button>
            </div>
        </form>
    </div>
</div>

<script>
function openLoginModal(userId, userName, userEmail) {
    document.getElementById('targetUserId').value     = userId;
    document.getElementById('modalUserName').textContent  = userName;
    document.getElementById('modalUserEmail').textContent = userEmail;
    document.getElementById('modalAvatar').textContent = userName.charAt(0).toUpperCase();
    document.getElementById('adminPasswordField').value = '';
    document.getElementById('loginModal').classList.add('open');
    setTimeout(() => document.getElementById('adminPasswordField').focus(), 200);
}
function closeLoginModal() {
    document.getElementById('loginModal').classList.remove('open');
}
// Close on overlay click
document.getElementById('loginModal').addEventListener('click', function(e) {
    if (e.target === this) closeLoginModal();
});
// Keyboard shortcut
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLoginModal();
});
</script>

</body>
</html>
