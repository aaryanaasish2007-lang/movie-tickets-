<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: ../login.php"); exit; }

$msg = ''; $msg_type = '';

// Cancel booking action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cancel' && isset($_POST['booking_id'])) {
        $stmt = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
        $stmt->execute([(int)$_POST['booking_id']]);
        $msg = "Booking #".(int)$_POST['booking_id']." cancelled."; $msg_type = 'warn';
    } elseif ($_POST['action'] === 'confirm' && isset($_POST['booking_id'])) {
        $stmt = $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=?");
        $stmt->execute([(int)$_POST['booking_id']]);
        $msg = "Booking #".(int)$_POST['booking_id']." confirmed."; $msg_type = 'success';
    }
}

$per_page_setting = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='max_bookings_per_page'")->fetchColumn();
$per_page = (int)($per_page_setting ?: 20);

// Filters
$date_from    = isset($_GET['date_from'])    ? $_GET['date_from']    : '';
$date_to      = isset($_GET['date_to'])      ? $_GET['date_to']      : '';
$status_filter= isset($_GET['status'])       ? $_GET['status']       : '';
$user_search  = isset($_GET['user_search'])  ? trim($_GET['user_search']) : '';
$sort         = isset($_GET['sort'])         ? $_GET['sort']         : 'date_desc';
$page         = isset($_GET['page'])         ? max(1,(int)$_GET['page']) : 1;
$offset       = ($page - 1) * $per_page;

$sql = "SELECT b.id, b.booking_date, b.seat_numbers, b.total_amount, b.status,
        u.name as user_name, u.email as user_email,
        m.title as movie_title, m.industry,
        s.show_date, s.show_time,
        t.name as theatre_name
        FROM bookings b
        JOIN users u ON b.user_id=u.id
        JOIN shows s ON b.show_id=s.id
        JOIN movies m ON s.movie_id=m.id
        JOIN theatres t ON s.theatre_id=t.id
        WHERE 1=1";
$params = [];

if ($date_from)     { $sql .= " AND DATE(b.booking_date) >= ?"; $params[] = $date_from; }
if ($date_to)       { $sql .= " AND DATE(b.booking_date) <= ?"; $params[] = $date_to; }
if ($status_filter) { $sql .= " AND b.status = ?";              $params[] = $status_filter; }
if ($user_search)   { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$user_search%"; $params[] = "%$user_search%"; }

$count_sql = str_replace("SELECT b.id, b.booking_date, b.seat_numbers, b.total_amount, b.status,\n        u.name as user_name, u.email as user_email,\n        m.title as movie_title, m.industry,\n        s.show_date, s.show_time,\n        t.name as theatre_name", "SELECT COUNT(*)", $sql);
$count_stmt = $pdo->prepare($count_sql); $count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

switch ($sort) {
    case 'date_asc':    $sql .= " ORDER BY b.booking_date ASC"; break;
    case 'amount_desc': $sql .= " ORDER BY b.total_amount DESC"; break;
    case 'amount_asc':  $sql .= " ORDER BY b.total_amount ASC"; break;
    default:            $sql .= " ORDER BY b.booking_date DESC";
}
$sql .= " LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$bookings = $stmt->fetchAll();

// Daily stats for today
$today_revenue = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE DATE(booking_date)=CURDATE() AND status='confirmed'")->fetchColumn() ?: 0;
$today_count   = $pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(booking_date)=CURDATE()")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM bookings WHERE status='confirmed'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings – Admin CineTicket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#E50914;--sidebar-bg:#111;--main-bg:#0A0A0A;--card-bg:#1A1A1A;--text:#f1f1f1;--muted:#888;--border:rgba(255,255,255,0.08);}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
        body{display:flex;background:var(--main-bg);color:var(--text);min-height:100vh;}
        .sidebar{width:260px;background:var(--sidebar-bg);display:flex;flex-direction:column;border-right:1px solid var(--border);position:sticky;top:0;height:100vh;overflow-y:auto;flex-shrink:0;}
        .sidebar-logo{padding:1.8rem 1.5rem;font-size:1.4rem;font-weight:800;color:var(--primary);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.6rem;}
        .sidebar-logo span{color:var(--text);}
        .nav-group-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:.8rem 1.5rem .3rem;}
        .nav-links{list-style:none;}
        .nav-links li a{display:flex;align-items:center;gap:.8rem;padding:.85rem 1.5rem;color:#aaa;text-decoration:none;font-size:.9rem;font-weight:500;transition:all .25s;border-left:3px solid transparent;}
        .nav-links li a i{width:18px;text-align:center;}
        .nav-links li a:hover{color:var(--text);background:rgba(255,255,255,.04);}
        .nav-links li a.active{color:var(--primary);background:rgba(229,9,20,.08);border-left-color:var(--primary);}
        .sidebar-footer{margin-top:auto;border-top:1px solid var(--border);}
        .main-content{flex:1;padding:2rem;overflow-y:auto;min-width:0;}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;}
        .page-header h2{font-size:1.6rem;}
        .btn{padding:.6rem 1.2rem;border-radius:8px;font-weight:600;font-size:.88rem;cursor:pointer;border:none;transition:all .25s;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;}
        .btn-primary{background:var(--primary);color:white;}.btn-primary:hover{background:#b0060f;}
        .btn-sm{padding:.3rem .7rem;font-size:.75rem;border-radius:5px;font-weight:600;cursor:pointer;border:none;}
        .btn-confirm{background:rgba(34,197,94,.15);color:#4ade80;border:1px solid rgba(34,197,94,.3);}.btn-confirm:hover{background:#22c55e;color:white;}
        .btn-cancel{background:rgba(229,9,20,.15);color:#f87171;border:1px solid rgba(229,9,20,.3);}.btn-cancel:hover{background:var(--primary);color:white;}
        .alert{padding:.9rem 1.2rem;border-radius:8px;margin-bottom:1.5rem;font-size:.9rem;display:flex;align-items:center;gap:.7rem;}
        .alert-success{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#4ade80;}
        .alert-warn{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fbbf24;}
        .stats-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;}
        .stat-mini{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:1.1rem 1.3rem;}
        .stat-mini .label{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;}
        .stat-mini .val{font-size:1.6rem;font-weight:800;}
        .val.green{color:#4ade80;} .val.yellow{color:#fbbf24;} .val.red{color:var(--primary);}
        .filter-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:1.2rem 1.5rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;gap:.8rem;align-items:flex-end;}
        .filter-item{display:flex;flex-direction:column;gap:.3rem;}
        .filter-item label{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
        .filter-item select,.filter-item input{background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;padding:.6rem 1rem;color:var(--text);font-size:.85rem;outline:none;}
        .filter-item select:focus,.filter-item input:focus{border-color:var(--primary);}
        .filter-item select option{background:#1a1a1a;}
        .section-card{background:var(--card-bg);border-radius:14px;border:1px solid var(--border);overflow:hidden;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table th{padding:.9rem 1.2rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600;border-bottom:1px solid var(--border);}
        .data-table td{padding:.95rem 1.2rem;font-size:.87rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
        .data-table tr:last-child td{border-bottom:none;}
        .data-table tr:hover td{background:rgba(255,255,255,.03);}
        .badge{padding:.25rem .7rem;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;}
        .badge-confirmed{background:rgba(34,197,94,.15);color:#4ade80;}
        .badge-pending{background:rgba(245,158,11,.15);color:#fbbf24;}
        .badge-cancelled{background:rgba(229,9,20,.15);color:#f87171;}
        .ind-badge{font-size:.65rem;padding:.15rem .5rem;border-radius:4px;font-weight:700;text-transform:uppercase;}
        .ind-Hollywood{background:rgba(59,130,246,.2);color:#60a5fa;}
        .ind-Bollywood{background:rgba(249,115,22,.2);color:#fb923c;}
        .ind-Tollywood{background:rgba(34,197,94,.2);color:#4ade80;}
        .pagination{display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;}
        .page-btn{padding:.5rem .9rem;border-radius:7px;font-size:.82rem;font-weight:600;text-decoration:none;border:1px solid var(--border);color:var(--muted);background:var(--card-bg);transition:all .25s;}
        .page-btn:hover{border-color:var(--primary);color:var(--primary);}
        .page-btn.active{background:var(--primary);border-color:var(--primary);color:white;}
        .overflow-x{overflow-x:auto;}
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo"><i class="fas fa-film"></i>Cine<span>Ticket</span></div>
    <div class="nav-group-label">Main</div>
    <ul class="nav-links"><li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li></ul>
    <div class="nav-group-label">Manage</div>
    <ul class="nav-links">
        <li><a href="movies.php"><i class="fas fa-film"></i> Movies</a></li>
        <li><a href="theatres.php"><i class="fas fa-building"></i> Theatres</a></li>
        <li><a href="shows.php"><i class="fas fa-video"></i> Shows</a></li>
        <li><a href="bookings.php" class="active"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
        <li><a href="index.php?tab=users"><i class="fas fa-users"></i> Users</a></li>
    </ul>
    <div class="nav-group-label">Config</div>
    <ul class="nav-links"><li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li></ul>
    <div class="sidebar-footer"><ul class="nav-links">
        <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul></div>
</div>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-ticket-alt" style="color:var(--primary);margin-right:.6rem;"></i> Booking Management</h2>
        <div style="color:var(--muted); font-size:.85rem;">Showing <?php echo count($bookings); ?> of <?php echo $total_rows; ?> bookings</div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-triangle'; ?>"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="stats-mini">
        <div class="stat-mini"><div class="label"><i class="fas fa-calendar-day"></i> Today's Bookings</div><div class="val yellow"><?php echo $today_count; ?></div></div>
        <div class="stat-mini"><div class="label"><i class="fas fa-rupee-sign"></i> Today's Revenue</div><div class="val green">₹<?php echo number_format($today_revenue,2); ?></div></div>
        <div class="stat-mini"><div class="label"><i class="fas fa-chart-line"></i> Total Revenue</div><div class="val red">₹<?php echo number_format($total_revenue,0); ?></div></div>
        <div class="stat-mini"><div class="label"><i class="fas fa-list"></i> Total Bookings</div><div class="val"><?php echo $total_rows; ?></div></div>
    </div>

    <!-- Filters -->
    <form method="GET">
    <div class="filter-bar">
        <div class="filter-item">
            <label>From Date</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        <div class="filter-item">
            <label>To Date</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="confirmed" <?php echo $status_filter==='confirmed'?'selected':''; ?>>Confirmed</option>
                <option value="pending"   <?php echo $status_filter==='pending'?'selected':''; ?>>Pending</option>
                <option value="cancelled" <?php echo $status_filter==='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="filter-item">
            <label>User Search</label>
            <input type="text" name="user_search" value="<?php echo htmlspecialchars($user_search); ?>" placeholder="Name or email...">
        </div>
        <div class="filter-item">
            <label>Sort By</label>
            <select name="sort">
                <option value="date_desc"   <?php echo $sort==='date_desc'?'selected':''; ?>>Date ↓ Newest</option>
                <option value="date_asc"    <?php echo $sort==='date_asc'?'selected':''; ?>>Date ↑ Oldest</option>
                <option value="amount_desc" <?php echo $sort==='amount_desc'?'selected':''; ?>>Amount ↓ High</option>
                <option value="amount_asc"  <?php echo $sort==='amount_asc'?'selected':''; ?>>Amount ↑ Low</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-filter"></i> Apply</button>
        <a href="bookings.php" class="btn" style="align-self:flex-end;background:rgba(255,255,255,.07);color:var(--text);"><i class="fas fa-times"></i> Clear</a>
    </div>
    </form>

    <div class="section-card">
        <div class="overflow-x">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>User</th><th>Movie</th><th>Theatre</th><th>Show Date</th><th>Seats</th><th>Amount</th><th>Booked On</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (count($bookings) > 0): ?>
                <?php foreach ($bookings as $b): ?>
                <tr>
                    <td style="color:var(--muted);">#<?php echo $b['id']; ?></td>
                    <td>
                        <div style="font-weight:600;font-size:.88rem;"><?php echo htmlspecialchars($b['user_name']); ?></div>
                        <div style="font-size:.75rem;color:var(--muted);"><?php echo htmlspecialchars($b['user_email']); ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:.88rem;"><?php echo htmlspecialchars($b['movie_title']); ?></div>
                        <span class="ind-badge ind-<?php echo $b['industry']?:'Hollywood'; ?>"><?php echo $b['industry']?:'–'; ?></span>
                    </td>
                    <td style="font-size:.85rem;"><?php echo htmlspecialchars($b['theatre_name']); ?></td>
                    <td>
                        <div style="font-weight:600;font-size:.85rem;"><?php echo date('d M Y', strtotime($b['show_date'])); ?></div>
                        <div style="font-size:.75rem;color:var(--muted);"><?php echo date('h:i A', strtotime($b['show_time'])); ?></div>
                    </td>
                    <td style="font-size:.8rem;max-width:100px;"><?php echo htmlspecialchars($b['seat_numbers']); ?></td>
                    <td style="color:#fbbf24;font-weight:700;">₹<?php echo number_format($b['total_amount'],2); ?></td>
                    <td style="font-size:.78rem;color:var(--muted);"><?php echo date('d M Y\nh:i A', strtotime($b['booking_date'])); ?></td>
                    <td><span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                    <td style="white-space:nowrap;">
                        <?php if ($b['status'] !== 'confirmed'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="confirm">
                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" class="btn-sm btn-confirm"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($b['status'] !== 'cancelled'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking?')">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" class="btn-sm btn-cancel" style="margin-left:3px;"><i class="fas fa-times"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2.5rem;">No bookings found for the selected filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>" class="page-btn"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($p=max(1,$page-3); $p<=min($total_pages,$page+3); $p++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$p])); ?>" class="page-btn <?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>" class="page-btn"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
