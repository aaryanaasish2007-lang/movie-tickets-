<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: ../login.php"); exit; }

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $city = isset($_POST['city']) ? trim($_POST['city']) : 'Other';
        $stmt = $pdo->prepare("INSERT INTO theatres (name, location, city, total_seats) VALUES (?,?,?,?)");
        $stmt->execute([trim($_POST['name']), trim($_POST['location']), $city, (int)$_POST['total_seats']]);
        $msg = "Theatre added successfully!"; $msg_type = 'success';
    } elseif ($_POST['action'] === 'edit' && isset($_POST['theatre_id'])) {
        $city = isset($_POST['city']) ? trim($_POST['city']) : 'Other';
        $stmt = $pdo->prepare("UPDATE theatres SET name=?, location=?, city=?, total_seats=? WHERE id=?");
        $stmt->execute([trim($_POST['name']), trim($_POST['location']), $city, (int)$_POST['total_seats'], (int)$_POST['theatre_id']]);
        $msg = "Theatre updated!"; $msg_type = 'success';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['theatre_id'])) {
        $stmt = $pdo->prepare("DELETE FROM theatres WHERE id=?");
        $stmt->execute([(int)$_POST['theatre_id']]);
        $msg = "Theatre deleted."; $msg_type = 'warn';
    }
}

$max_setting = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='max_theatres_display'")->fetchColumn();
$max_theatres = $max_setting ?: 10;

$theatres = $pdo->query("SELECT t.*, COUNT(s.id) as show_count FROM theatres t LEFT JOIN shows s ON t.id=s.theatre_id GROUP BY t.id ORDER BY t.name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theatres – Admin CineTicket</title>
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
        .info-bar{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);border-radius:8px;padding:.8rem 1.2rem;margin-bottom:1.5rem;font-size:.85rem;color:#60a5fa;display:flex;align-items:center;gap:.7rem;}
        .section-card{background:var(--card-bg);border-radius:14px;border:1px solid var(--border);overflow:hidden;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table th{padding:.9rem 1.2rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600;border-bottom:1px solid var(--border);}
        .data-table td{padding:1rem 1.2rem;font-size:.88rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
        .data-table tr:last-child td{border-bottom:none;}
        .data-table tr:hover td{background:rgba(255,255,255,.03);}
        .seat-bar-bg{background:rgba(255,255,255,.07);border-radius:4px;height:6px;width:100px;overflow:hidden;display:inline-block;}
        .seat-bar-fill{height:100%;background:var(--primary);border-radius:4px;}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);z-index:1000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:#1E1E1E;border:1px solid var(--border);border-radius:16px;padding:2rem;width:480px;max-width:95vw;animation:slideUp .3s ease;}
        @keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
        .modal-box h3{font-size:1.1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.7rem;}
        .form-group{margin-bottom:1rem;}
        .form-group label{display:block;font-size:.8rem;color:var(--muted);margin-bottom:.4rem;}
        .form-group input{width:100%;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;padding:.7rem 1rem;color:var(--text);font-size:.88rem;outline:none;transition:border-color .25s;}
        .form-group input:focus{border-color:var(--primary);}
        .modal-actions{display:flex;gap:.8rem;margin-top:1.5rem;}
        .modal-actions button{flex:1;padding:.8rem;border-radius:8px;font-weight:600;font-size:.9rem;cursor:pointer;border:none;transition:all .25s;}
        .modal-cancel{background:rgba(255,255,255,.07);color:var(--text);}.modal-cancel:hover{background:rgba(255,255,255,.12);}
        .modal-confirm{background:var(--primary);color:white;}.modal-confirm:hover{background:#b0060f;}
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
        <li><a href="theatres.php" class="active"><i class="fas fa-building"></i> Theatres</a></li>
        <li><a href="shows.php"><i class="fas fa-video"></i> Shows</a></li>
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
        <h2><i class="fas fa-building" style="color:var(--primary);margin-right:.6rem;"></i> Theatre Management</h2>
        <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Theatre</button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-triangle'; ?>"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="info-bar"><i class="fas fa-info-circle"></i> The site displays a maximum of <strong><?php echo $max_theatres; ?></strong> theatres. Manage this limit in <a href="settings.php" style="color:#93c5fd;text-decoration:underline;">Settings</a>.</div>

    <div class="section-card">
        <div class="overflow-x">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Theatre Name</th><th>Location</th><th>City</th><th>Total Seats</th><th>Shows Scheduled</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (count($theatres) > 0): ?>
                <?php foreach ($theatres as $t): ?>
                <tr>
                    <td style="color:var(--muted);"><?php echo $t['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                    <td style="color:var(--muted); font-size:.85rem;"><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:.4rem;"></i><?php echo htmlspecialchars($t['location']); ?></td>
                    <td><span style="background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.25);padding:.15rem .55rem;border-radius:4px;font-size:.72rem;font-weight:700;"><?php echo htmlspecialchars($t['city'] ?? 'Other'); ?></span></td>
                    <td>
                        <span style="font-weight:700;"><?php echo $t['total_seats']; ?></span>
                        <div style="margin-top:4px;"><div class="seat-bar-bg"><div class="seat-bar-fill" style="width:<?php echo min(100, ($t['total_seats']/200)*100); ?>%"></div></div></div>
                    </td>
                    <td><?php echo $t['show_count']; ?> show<?php echo $t['show_count']!=1?'s':''; ?></td>
                    <td style="white-space:nowrap;">
                        <button class="btn-sm btn-edit" onclick="openEditModal(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars(addslashes($t['name'])); ?>', '<?php echo htmlspecialchars(addslashes($t['location'])); ?>', '<?php echo htmlspecialchars(addslashes($t['city'] ?? 'Other')); ?>', <?php echo $t['total_seats']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this theatre? All associated shows will also be deleted.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="theatre_id" value="<?php echo $t['id']; ?>">
                            <button type="submit" class="btn-sm btn-del" style="margin-left:4px;"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No theatres found. Add one!</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
<div class="modal-box">
    <h3><i class="fas fa-plus" style="color:var(--primary);"></i> Add New Theatre</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-group"><label>Theatre Name *</label><input type="text" name="name" required placeholder="e.g. PVR IMAX"></div>
        <div class="form-group"><label>Location *</label><input type="text" name="location" required placeholder="e.g. Phoenix Mall, Bangalore"></div>
        <div class="form-group"><label>City *</label><input type="text" name="city" required placeholder="e.g. Bangalore"></div>
        <div class="form-group"><label>Total Seats *</label><input type="number" name="total_seats" required min="1" value="100"></div>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="modal-confirm"><i class="fas fa-plus"></i> Add Theatre</button>
        </div>
    </form>
</div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
<div class="modal-box">
    <h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Theatre</h3>
    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="theatre_id" id="editId">
        <div class="form-group"><label>Theatre Name *</label><input type="text" name="name" id="editName" required></div>
        <div class="form-group"><label>Location *</label><input type="text" name="location" id="editLocation" required></div>
        <div class="form-group"><label>City *</label><input type="text" name="city" id="editCity" required></div>
        <div class="form-group"><label>Total Seats *</label><input type="number" name="total_seats" id="editSeats" required min="1"></div>
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
function openEditModal(id, name, location, city, seats) {
    document.getElementById('editId').value       = id;
    document.getElementById('editName').value     = name;
    document.getElementById('editLocation').value = location;
    document.getElementById('editCity').value     = city;
    document.getElementById('editSeats').value    = seats;
    document.getElementById('editModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(o=>o.classList.remove('open')); });
</script>
</body>
</html>
