<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: ../login.php"); exit; }

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO shows (movie_id, theatre_id, show_date, show_time, price) VALUES (?,?,?,?,?)");
        $stmt->execute([(int)$_POST['movie_id'], (int)$_POST['theatre_id'], $_POST['show_date'], $_POST['show_time'], (float)$_POST['price']]);
        $msg = "Show added successfully!"; $msg_type = 'success';
    } elseif ($_POST['action'] === 'edit' && isset($_POST['show_id'])) {
        $stmt = $pdo->prepare("UPDATE shows SET movie_id=?, theatre_id=?, show_date=?, show_time=?, price=? WHERE id=?");
        $stmt->execute([(int)$_POST['movie_id'], (int)$_POST['theatre_id'], $_POST['show_date'], $_POST['show_time'], (float)$_POST['price'], (int)$_POST['show_id']]);
        $msg = "Show updated!"; $msg_type = 'success';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['show_id'])) {
        $stmt = $pdo->prepare("DELETE FROM shows WHERE id=?");
        $stmt->execute([(int)$_POST['show_id']]);
        $msg = "Show deleted."; $msg_type = 'warn';
    }
}

$max_setting = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='max_shows_per_movie'")->fetchColumn();
$max_shows = $max_setting ?: 10;

// Filters
$movie_filter   = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;
$theatre_filter = isset($_GET['theatre_id']) ? (int)$_GET['theatre_id'] : 0;
$date_filter    = isset($_GET['show_date']) ? $_GET['show_date'] : '';
$sort           = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc';

$sql = "SELECT s.*, m.title as movie_title, m.industry, t.name as theatre_name, 
        (SELECT COUNT(*) FROM bookings b WHERE b.show_id=s.id) as booking_count
        FROM shows s JOIN movies m ON s.movie_id=m.id JOIN theatres t ON s.theatre_id=t.id WHERE 1=1";
$params = [];
if ($movie_filter)   { $sql .= " AND s.movie_id=?";   $params[] = $movie_filter; }
if ($theatre_filter) { $sql .= " AND s.theatre_id=?"; $params[] = $theatre_filter; }
if ($date_filter)    { $sql .= " AND s.show_date=?";  $params[] = $date_filter; }

switch ($sort) {
    case 'date_desc': $sql .= " ORDER BY s.show_date DESC, s.show_time DESC"; break;
    case 'price_asc': $sql .= " ORDER BY s.price ASC"; break;
    case 'price_desc': $sql .= " ORDER BY s.price DESC"; break;
    default:          $sql .= " ORDER BY s.show_date ASC, s.show_time ASC";
}

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$shows = $stmt->fetchAll();

$all_movies   = $pdo->query("SELECT id, title FROM movies ORDER BY title ASC")->fetchAll();
$all_theatres = $pdo->query("SELECT id, name FROM theatres ORDER BY name ASC")->fetchAll();
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shows – Admin CineTicket</title>
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
        .btn-sm{padding:.35rem .8rem;font-size:.78rem;border-radius:6px;font-weight:600;cursor:pointer;border:none;}
        .btn-edit{background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.3);}.btn-edit:hover{background:#3b82f6;color:white;}
        .btn-del{background:rgba(229,9,20,.15);color:#f87171;border:1px solid rgba(229,9,20,.3);}.btn-del:hover{background:var(--primary);color:white;}
        .alert{padding:.9rem 1.2rem;border-radius:8px;margin-bottom:1.5rem;font-size:.9rem;display:flex;align-items:center;gap:.7rem;}
        .alert-success{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#4ade80;}
        .alert-warn{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#fbbf24;}
        .filter-bar{background:var(--card-bg);border:1px solid var(--border);border-radius:12px;padding:1.2rem 1.5rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;gap:.8rem;align-items:flex-end;}
        .filter-item{display:flex;flex-direction:column;gap:.3rem;}
        .filter-item label{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);}
        .filter-item select,.filter-item input{background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;padding:.6rem 1rem;color:var(--text);font-size:.85rem;outline:none;cursor:pointer;}
        .filter-item select:focus,.filter-item input:focus{border-color:var(--primary);}
        .filter-item select option{background:#1a1a1a;}
        .section-card{background:var(--card-bg);border-radius:14px;border:1px solid var(--border);overflow:hidden;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table th{padding:.9rem 1.2rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600;border-bottom:1px solid var(--border);}
        .data-table td{padding:1rem 1.2rem;font-size:.88rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
        .data-table tr:last-child td{border-bottom:none;}
        .data-table tr:hover td{background:rgba(255,255,255,.03);}
        .ind-badge{font-size:.65rem;padding:.15rem .5rem;border-radius:4px;font-weight:700;text-transform:uppercase;}
        .ind-Hollywood{background:rgba(59,130,246,.2);color:#60a5fa;}
        .ind-Bollywood{background:rgba(249,115,22,.2);color:#fb923c;}
        .ind-Tollywood{background:rgba(34,197,94,.2);color:#4ade80;}
        .period-past{background:rgba(255,255,255,.07);color:#9ca3af;}
        .period-today{background:rgba(245,158,11,.15);color:#fbbf24;}
        .period-upcoming{background:rgba(34,197,94,.15);color:#4ade80;}
        .period-badge{font-size:.7rem;padding:.2rem .6rem;border-radius:4px;font-weight:600;}
        .info-bar{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:8px;padding:.8rem 1.2rem;margin-bottom:1.5rem;font-size:.85rem;color:#60a5fa;display:flex;align-items:center;gap:.7rem;}
        .overflow-x{overflow-x:auto;}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);z-index:1000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:#1E1E1E;border:1px solid var(--border);border-radius:16px;padding:2rem;width:520px;max-width:95vw;animation:slideUp .3s ease;}
        @keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
        .modal-box h3{font-size:1.1rem;margin-bottom:1.5rem;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
        .form-group{margin-bottom:1rem;}
        .form-group.full{grid-column:1/-1;}
        .form-group label{display:block;font-size:.8rem;color:var(--muted);margin-bottom:.4rem;}
        .form-group input,.form-group select{width:100%;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;padding:.7rem 1rem;color:var(--text);font-size:.88rem;outline:none;transition:border-color .25s;font-family:inherit;}
        .form-group input:focus,.form-group select:focus{border-color:var(--primary);}
        .form-group select option{background:#1E1E1E;}
        .modal-actions{display:flex;gap:.8rem;margin-top:1.5rem;}
        .modal-actions button{flex:1;padding:.8rem;border-radius:8px;font-weight:600;font-size:.9rem;cursor:pointer;border:none;transition:all .25s;}
        .modal-cancel{background:rgba(255,255,255,.07);color:var(--text);}.modal-cancel:hover{background:rgba(255,255,255,.12);}
        .modal-confirm{background:var(--primary);color:white;}.modal-confirm:hover{background:#b0060f;}
        .sort-link{color:var(--muted);font-size:.72rem;text-decoration:none;margin-left:.3rem;}
        .sort-link:hover{color:var(--primary);}
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
        <li><a href="shows.php" class="active"><i class="fas fa-video"></i> Shows</a></li>
        <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
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
        <h2><i class="fas fa-video" style="color:var(--primary);margin-right:.6rem;"></i> Show Management <span style="font-size:.9rem; font-weight:400; color:var(--muted);">(<?php echo count($shows); ?> found)</span></h2>
        <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Show</button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-triangle'; ?>"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="info-bar"><i class="fas fa-info-circle"></i> Max shows per movie on site: <strong><?php echo $max_shows; ?></strong> &nbsp;·&nbsp; <a href="settings.php" style="color:#93c5fd;text-decoration:underline;">Change in Settings</a></div>

    <!-- Filter Bar -->
    <form method="GET">
    <div class="filter-bar">
        <div class="filter-item">
            <label>Movie</label>
            <select name="movie_id">
                <option value="">All Movies</option>
                <?php foreach ($all_movies as $m): ?>
                <option value="<?php echo $m['id']; ?>" <?php echo $movie_filter==$m['id']?'selected':''; ?>><?php echo htmlspecialchars($m['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Theatre</label>
            <select name="theatre_id">
                <option value="">All Theatres</option>
                <?php foreach ($all_theatres as $t): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo $theatre_filter==$t['id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>Date</label>
            <input type="date" name="show_date" value="<?php echo $date_filter; ?>">
        </div>
        <div class="filter-item">
            <label>Sort By</label>
            <select name="sort">
                <option value="date_asc"  <?php echo $sort==='date_asc'?'selected':''; ?>>Date ↑ Earliest</option>
                <option value="date_desc" <?php echo $sort==='date_desc'?'selected':''; ?>>Date ↓ Latest</option>
                <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Price ↑</option>
                <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price ↓</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="align-self:flex-end;"><i class="fas fa-filter"></i> Apply</button>
        <a href="shows.php" class="btn" style="align-self:flex-end;background:rgba(255,255,255,.07);color:var(--text);"><i class="fas fa-times"></i> Clear</a>
    </div>
    </form>

    <div class="section-card">
        <div class="overflow-x">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Movie</th><th>Theatre</th><th>Date</th><th>Time</th><th>Price</th><th>Period</th><th>Bookings</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (count($shows) > 0): ?>
                <?php foreach ($shows as $s):
                    $sd = strtotime($s['show_date']);
                    $td = strtotime($today);
                    if ($sd < $td) $period='past'; elseif ($sd===$td) $period='today'; else $period='upcoming';
                ?>
                <tr>
                    <td style="color:var(--muted);">#<?php echo $s['id']; ?></td>
                    <td>
                        <div style="font-weight:600;font-size:.9rem;"><?php echo htmlspecialchars($s['movie_title']); ?></div>
                        <span class="ind-badge ind-<?php echo $s['industry']?:'Hollywood'; ?>"><?php echo $s['industry']?:'–'; ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($s['theatre_name']); ?></td>
                    <td style="font-weight:600;"><?php echo date('d M Y', strtotime($s['show_date'])); ?></td>
                    <td style="color:var(--primary); font-weight:700;"><?php echo date('h:i A', strtotime($s['show_time'])); ?></td>
                    <td style="color:#fbbf24; font-weight:700;">₹<?php echo number_format($s['price'],2); ?></td>
                    <td><span class="period-badge period-<?php echo $period; ?>"><?php echo ucfirst($period); ?></span></td>
                    <td><?php echo $s['booking_count']; ?></td>
                    <td style="white-space:nowrap;">
                        <button class="btn-sm btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($s)); ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this show?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="show_id" value="<?php echo $s['id']; ?>">
                            <button type="submit" class="btn-sm btn-del" style="margin-left:4px;"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No shows found for the selected filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
<div class="modal-box">
    <h3><i class="fas fa-plus" style="color:var(--primary);margin-right:.5rem;"></i> Add New Show</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="form-group full">
                <label>Movie *</label>
                <select name="movie_id" required>
                    <option value="">Select movie...</option>
                    <?php foreach ($all_movies as $m): ?>
                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>Theatre *</label>
                <select name="theatre_id" required>
                    <option value="">Select theatre...</option>
                    <?php foreach ($all_theatres as $t): ?>
                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Show Date *</label><input type="date" name="show_date" required min="<?php echo $today; ?>"></div>
            <div class="form-group"><label>Show Time *</label><input type="time" name="show_time" required></div>
            <div class="form-group full"><label>Price (₹) *</label><input type="number" name="price" step="0.01" min="0" required placeholder="e.g. 200.00"></div>
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="modal-confirm"><i class="fas fa-plus"></i> Add Show</button>
        </div>
    </form>
</div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
<div class="modal-box">
    <h3><i class="fas fa-edit" style="color:#60a5fa;margin-right:.5rem;"></i> Edit Show</h3>
    <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="show_id" id="editShowId">
        <div class="form-grid">
            <div class="form-group full">
                <label>Movie *</label>
                <select name="movie_id" id="editMovieId" required>
                    <?php foreach ($all_movies as $m): ?>
                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>Theatre *</label>
                <select name="theatre_id" id="editTheatreId" required>
                    <?php foreach ($all_theatres as $t): ?>
                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Show Date *</label><input type="date" name="show_date" id="editDate" required></div>
            <div class="form-group"><label>Show Time *</label><input type="time" name="show_time" id="editTime" required></div>
            <div class="form-group full"><label>Price (₹) *</label><input type="number" name="price" step="0.01" id="editPrice" required></div>
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" onclick="closeModal('editModal')">Cancel</button>
            <button type="submit" class="modal-confirm"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </form>
</div>
</div>

<script>
function openAddModal() { document.getElementById('addModal').classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openEditModal(s) {
    document.getElementById('editShowId').value    = s.id;
    document.getElementById('editMovieId').value   = s.movie_id;
    document.getElementById('editTheatreId').value = s.theatre_id;
    document.getElementById('editDate').value      = s.show_date;
    document.getElementById('editTime').value      = s.show_time;
    document.getElementById('editPrice').value     = s.price;
    document.getElementById('editModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(o=>o.classList.remove('open')); });
</script>
</body>
</html>
