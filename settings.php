<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: ../login.php"); exit; }

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings'])) {
    $settings_to_save = [
        'max_movies_homepage',
        'max_theatres_display',
        'max_shows_per_movie',
        'max_bookings_per_page',
    ];
    foreach ($settings_to_save as $key) {
        if (isset($_POST[$key])) {
            $val = (int)$_POST[$key];
            if ($val < 1) $val = 1;
            $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->execute([$key, $val, $val]);
        }
    }
    $msg = "Settings saved successfully!"; $msg_type = 'success';
}

// Fetch all settings
$settings_raw = $pdo->query("SELECT setting_key, setting_value, label FROM admin_settings")->fetchAll();
$settings = [];
foreach ($settings_raw as $row) { $settings[$row['setting_key']] = $row; }

// Live site stats for context
$total_movies   = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$total_theatres = $pdo->query("SELECT COUNT(*) FROM theatres")->fetchColumn();
$total_shows    = $pdo->query("SELECT COUNT(*) FROM shows WHERE show_date >= CURDATE()")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings – Admin CineTicket</title>
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
        .main-content{flex:1;padding:2rem;overflow-y:auto;min-width:0;max-width:900px;}
        .page-header{margin-bottom:2rem;}
        .page-header h2{font-size:1.6rem;margin-bottom:.3rem;}
        .page-header p{color:var(--muted);font-size:.9rem;}
        .alert{padding:.9rem 1.2rem;border-radius:8px;margin-bottom:1.5rem;font-size:.9rem;display:flex;align-items:center;gap:.7rem;}
        .alert-success{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#4ade80;}
        .context-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:2rem;}
        .ctx-card{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:1rem 1.2rem;text-align:center;}
        .ctx-card .cv{font-size:1.8rem;font-weight:800;color:var(--primary);}
        .ctx-card .cl{font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-top:.2rem;}
        .settings-form{background:var(--card-bg);border:1px solid var(--border);border-radius:16px;overflow:hidden;}
        .settings-section{padding:1.5rem 2rem;border-bottom:1px solid var(--border);}
        .settings-section:last-of-type{border-bottom:none;}
        .section-title{font-size:1rem;font-weight:700;margin-bottom:.3rem;display:flex;align-items:center;gap:.6rem;}
        .section-title i{color:var(--primary);}
        .section-desc{font-size:.82rem;color:var(--muted);margin-bottom:1.2rem;line-height:1.5;}
        .setting-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.2rem;padding:.9rem 1rem;background:rgba(255,255,255,.03);border-radius:10px;border:1px solid var(--border);}
        .setting-row:last-child{margin-bottom:0;}
        .setting-info{flex:1;}
        .setting-info .si-label{font-weight:600;font-size:.92rem;margin-bottom:.2rem;}
        .setting-info .si-desc{font-size:.78rem;color:var(--muted);line-height:1.4;}
        .setting-control{display:flex;align-items:center;gap:.8rem;flex-shrink:0;}
        .setting-control input[type=number]{width:90px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;padding:.6rem .8rem;color:var(--text);font-size:1rem;text-align:center;outline:none;font-weight:700;transition:border-color .25s;}
        .setting-control input[type=number]:focus{border-color:var(--primary);}
        .setting-control .current-val{font-size:.78rem;color:var(--muted);}
        .setting-control .current-val span{color:var(--text);font-weight:700;}
        .progress-bar{height:4px;background:rgba(255,255,255,.07);border-radius:2px;margin-top:.4rem;overflow:hidden;}
        .progress-fill{height:100%;border-radius:2px;transition:width .5s ease;}
        .form-footer{padding:1.5rem 2rem;background:rgba(255,255,255,.02);display:flex;justify-content:flex-end;gap:1rem;border-top:1px solid var(--border);}
        .btn{padding:.7rem 1.5rem;border-radius:8px;font-weight:700;font-size:.9rem;cursor:pointer;border:none;transition:all .25s;display:inline-flex;align-items:center;gap:.5rem;}
        .btn-primary{background:var(--primary);color:white;}.btn-primary:hover{background:#b0060f;}
        .btn-reset{background:rgba(255,255,255,.07);color:var(--text);}.btn-reset:hover{background:rgba(255,255,255,.12);}
        .warning-box{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:10px;padding:1rem 1.2rem;margin-bottom:1.5rem;display:flex;gap:.8rem;align-items:flex-start;}
        .warning-box i{color:#fbbf24;margin-top:.1rem;flex-shrink:0;}
        .warning-box p{font-size:.83rem;color:#fbbf24;line-height:1.5;}
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
        <li><a href="bookings.php"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
        <li><a href="index.php?tab=users"><i class="fas fa-users"></i> Users</a></li>
    </ul>
    <div class="nav-group-label">Config</div>
    <ul class="nav-links"><li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li></ul>
    <div class="sidebar-footer"><ul class="nav-links">
        <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a></li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul></div>
</div>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-cog" style="color:var(--primary);margin-right:.6rem;"></i> Site Settings</h2>
        <p>Control what users see on the website — movies, theatres, shows, and pagination limits.</p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><i class="fas fa-check-circle"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- Live Context -->
    <div class="context-grid">
        <div class="ctx-card"><div class="cv"><?php echo $total_movies; ?></div><div class="cl">Movies in DB</div></div>
        <div class="ctx-card"><div class="cv"><?php echo $total_theatres; ?></div><div class="cl">Theatres in DB</div></div>
        <div class="ctx-card"><div class="cv"><?php echo $total_shows; ?></div><div class="cl">Active Shows</div></div>
        <div class="ctx-card"><div class="cv"><?php echo $total_bookings; ?></div><div class="cl">Total Bookings</div></div>
    </div>

    <div class="warning-box">
        <i class="fas fa-exclamation-triangle"></i>
        <p>These settings control the <strong>display limits</strong> on the public-facing website. For example, if you set "Max Movies on Homepage" to 10 but have 20 movies in the database, only 10 will be shown to visitors. The actual data is always preserved.</p>
    </div>

    <form method="POST">
        <input type="hidden" name="settings" value="1">
        <div class="settings-form">

            <!-- Movies Section -->
            <div class="settings-section">
                <div class="section-title"><i class="fas fa-film"></i> Movies</div>
                <div class="section-desc">Control how many movies are shown to visitors on the homepage.</div>

                <div class="setting-row">
                    <div class="setting-info">
                        <div class="si-label">Max Movies on Homepage</div>
                        <div class="si-desc">Limits the number of movies displayed in the "Now Showing" grid. You have <?php echo $total_movies; ?> movies in the database.</div>
                        <?php $mv = (int)($settings['max_movies_homepage']['setting_value'] ?? 20); ?>
                        <div class="progress-bar" style="margin-top:.6rem;width:200px;">
                            <div class="progress-fill" style="width:<?php echo min(100, ($mv/$total_movies)*100); ?>%; background:#E50914;"></div>
                        </div>
                    </div>
                    <div class="setting-control">
                        <div class="current-val">Current: <span><?php echo $mv; ?></span></div>
                        <input type="number" name="max_movies_homepage" value="<?php echo $mv; ?>" min="1" max="<?php echo max($total_movies,1); ?>">
                    </div>
                </div>
            </div>

            <!-- Theatres Section -->
            <div class="settings-section">
                <div class="section-title"><i class="fas fa-building"></i> Theatres</div>
                <div class="section-desc">Limit how many theatres appear on the site's theatre listing page.</div>

                <div class="setting-row">
                    <div class="setting-info">
                        <div class="si-label">Max Theatres Displayed</div>
                        <div class="si-desc">You currently have <?php echo $total_theatres; ?> theatres in the database.</div>
                        <?php $tv = (int)($settings['max_theatres_display']['setting_value'] ?? 10); ?>
                        <div class="progress-bar" style="margin-top:.6rem;width:200px;">
                            <div class="progress-fill" style="width:<?php echo min(100, ($tv/max($total_theatres,1))*100); ?>%; background:#3b82f6;"></div>
                        </div>
                    </div>
                    <div class="setting-control">
                        <div class="current-val">Current: <span><?php echo $tv; ?></span></div>
                        <input type="number" name="max_theatres_display" value="<?php echo $tv; ?>" min="1" max="<?php echo max($total_theatres,1); ?>">
                    </div>
                </div>
            </div>

            <!-- Shows Section -->
            <div class="settings-section">
                <div class="section-title"><i class="fas fa-video"></i> Shows</div>
                <div class="section-desc">Limit the number of show time cards displayed per movie on the booking page.</div>

                <div class="setting-row">
                    <div class="setting-info">
                        <div class="si-label">Max Shows per Movie (Booking Page)</div>
                        <div class="si-desc">Controls how many show cards a user sees when selecting a show for a movie. <?php echo $total_shows; ?> active shows in DB.</div>
                        <?php $sv = (int)($settings['max_shows_per_movie']['setting_value'] ?? 10); ?>
                    </div>
                    <div class="setting-control">
                        <div class="current-val">Current: <span><?php echo $sv; ?></span></div>
                        <input type="number" name="max_shows_per_movie" value="<?php echo $sv; ?>" min="1" max="50">
                    </div>
                </div>
            </div>

            <!-- Bookings Section -->
            <div class="settings-section">
                <div class="section-title"><i class="fas fa-ticket-alt"></i> Bookings Admin</div>
                <div class="section-desc">Pagination setting for the admin bookings list page.</div>

                <div class="setting-row">
                    <div class="setting-info">
                        <div class="si-label">Bookings Per Page (Admin List)</div>
                        <div class="si-desc">How many booking rows appear per page in the admin bookings management table.</div>
                        <?php $bv = (int)($settings['max_bookings_per_page']['setting_value'] ?? 20); ?>
                    </div>
                    <div class="setting-control">
                        <div class="current-val">Current: <span><?php echo $bv; ?></span></div>
                        <input type="number" name="max_bookings_per_page" value="<?php echo $bv; ?>" min="5" max="100">
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <a href="settings.php" class="btn btn-reset"><i class="fas fa-undo"></i> Reset View</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Settings</button>
            </div>
        </div>
    </form>
</div>
</body>
</html>
