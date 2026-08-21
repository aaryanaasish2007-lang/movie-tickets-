<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: ../login.php"); exit; }

$msg = ''; $msg_type = '';

// Get setting for max movies
$max_setting = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='max_movies_homepage'")->fetchColumn();
$max_movies = $max_setting ?: 20;

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO movies (title,description,genre,language,duration,rating,cast,trailer_url,poster_url,status,industry) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['title']), trim($_POST['description']), trim($_POST['genre']),
            trim($_POST['language']), (int)$_POST['duration'], (float)$_POST['rating'],
            trim($_POST['cast']), trim($_POST['trailer_url']), trim($_POST['poster_url']),
            $_POST['status'], $_POST['industry']
        ]);
        $msg = "Movie added successfully!"; $msg_type = 'success';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['movie_id'])) {
        $stmt = $pdo->prepare("DELETE FROM movies WHERE id=?");
        $stmt->execute([(int)$_POST['movie_id']]);
        $msg = "Movie deleted."; $msg_type = 'warn';
    } elseif ($_POST['action'] === 'edit' && isset($_POST['movie_id'])) {
        $stmt = $pdo->prepare("UPDATE movies SET title=?,description=?,genre=?,language=?,duration=?,rating=?,cast=?,trailer_url=?,poster_url=?,status=?,industry=? WHERE id=?");
        $stmt->execute([
            trim($_POST['title']), trim($_POST['description']), trim($_POST['genre']),
            trim($_POST['language']), (int)$_POST['duration'], (float)$_POST['rating'],
            trim($_POST['cast']), trim($_POST['trailer_url']), trim($_POST['poster_url']),
            $_POST['status'], $_POST['industry'], (int)$_POST['movie_id']
        ]);
        $msg = "Movie updated!"; $msg_type = 'success';
    }
}

// Fetch movies grouped by industry
$industry_filter = isset($_GET['industry']) ? $_GET['industry'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM movies WHERE 1=1";
$params = [];
if ($industry_filter !== 'all') { $sql .= " AND industry=?"; $params[] = $industry_filter; }
if ($search) { $sql .= " AND title LIKE ?"; $params[] = "%$search%"; }
$sql .= " ORDER BY industry, title ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$movies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies – Admin CineTicket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#E50914; --sidebar-bg:#111; --main-bg:#0A0A0A; --card-bg:#1A1A1A; --text:#f1f1f1; --muted:#888; --border:rgba(255,255,255,0.08); }
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
        .filter-row{display:flex;gap:.8rem;margin-bottom:1.5rem;align-items:center;flex-wrap:wrap;}
        .filter-row input{background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:8px;padding:.6rem 1rem;color:var(--text);font-size:.88rem;outline:none;width:240px;}
        .filter-row input:focus{border-color:var(--primary);}
        .tab-bar{display:flex;gap:.4rem;background:var(--card-bg);border:1px solid var(--border);padding:.3rem;border-radius:10px;}
        .tab-btn{padding:.45rem 1rem;border-radius:7px;font-size:.82rem;font-weight:600;color:var(--muted);text-decoration:none;border:none;background:transparent;cursor:pointer;transition:all .25s;}
        .tab-btn:hover{color:var(--text);}
        .tab-btn.active{background:var(--primary);color:white;}
        .section-card{background:var(--card-bg);border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:1.5rem;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table th{padding:.9rem 1.2rem;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:600;border-bottom:1px solid var(--border);}
        .data-table td{padding:.9rem 1.2rem;font-size:.87rem;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
        .data-table tr:last-child td{border-bottom:none;}
        .data-table tr:hover td{background:rgba(255,255,255,.03);}
        .poster-thumb{width:36px;height:50px;object-fit:cover;border-radius:4px;}
        .ind-badge{font-size:.65rem;padding:.15rem .5rem;border-radius:4px;font-weight:700;text-transform:uppercase;}
        .ind-Hollywood{background:rgba(59,130,246,.2);color:#60a5fa;}
        .ind-Bollywood{background:rgba(249,115,22,.2);color:#fb923c;}
        .ind-Tollywood{background:rgba(34,197,94,.2);color:#4ade80;}
        .ind-Other{background:rgba(255,255,255,.1);color:#aaa;}
        .status-now{background:rgba(34,197,94,.15);color:#4ade80;padding:.2rem .6rem;border-radius:4px;font-size:.72rem;font-weight:700;}
        .status-up{background:rgba(245,158,11,.15);color:#fbbf24;padding:.2rem .6rem;border-radius:4px;font-size:.72rem;font-weight:700;}
        .overflow-x{overflow-x:auto;}
        /* Modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);z-index:1000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:#1E1E1E;border:1px solid var(--border);border-radius:16px;padding:2rem;width:600px;max-width:95vw;max-height:90vh;overflow-y:auto;animation:slideUp .3s ease;}
        @keyframes slideUp{from{transform:translateY(20px);opacity:0;}to{transform:translateY(0);opacity:1;}}
        .modal-box h3{font-size:1.1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.7rem;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
        .form-group{margin-bottom:1rem;}
        .form-group.full{grid-column:1/-1;}
        .form-group label{display:block;font-size:.8rem;color:var(--muted);margin-bottom:.4rem;}
        .form-group input,.form-group select,.form-group textarea{width:100%;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:8px;padding:.7rem 1rem;color:var(--text);font-size:.88rem;outline:none;transition:border-color .25s;font-family:inherit;}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--primary);}
        .form-group textarea{resize:vertical;min-height:80px;}
        .form-group select option{background:#1E1E1E;}
        .modal-actions{display:flex;gap:.8rem;margin-top:1.5rem;}
        .modal-actions button{flex:1;padding:.8rem;border-radius:8px;font-weight:600;font-size:.9rem;cursor:pointer;border:none;transition:all .25s;}
        .modal-cancel{background:rgba(255,255,255,.07);color:var(--text);}.modal-cancel:hover{background:rgba(255,255,255,.12);}
        .modal-confirm{background:var(--primary);color:white;}.modal-confirm:hover{background:#b0060f;}
    </style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo"><i class="fas fa-film"></i>Cine<span>Ticket</span></div>
    <div class="nav-group-label">Main</div>
    <ul class="nav-links"><li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li></ul>
    <div class="nav-group-label">Manage</div>
    <ul class="nav-links">
        <li><a href="movies.php" class="active"><i class="fas fa-film"></i> Movies</a></li>
        <li><a href="theatres.php"><i class="fas fa-building"></i> Theatres</a></li>
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
        <h2><i class="fas fa-film" style="color:var(--primary);margin-right:.6rem;"></i> Movie Management</h2>
        <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Movie</button>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msg_type; ?>"><i class="fas fa-<?php echo $msg_type==='success'?'check-circle':'exclamation-triangle'; ?>"></i> <?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="filter-row">
        <form method="GET" style="display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" placeholder="Search movies..." value="<?php echo htmlspecialchars($search); ?>">
            <div class="tab-bar">
                <a href="?industry=all" class="tab-btn <?php echo $industry_filter==='all'?'active':''; ?>">All (<?php echo count($movies); ?>)</a>
                <a href="?industry=Hollywood" class="tab-btn <?php echo $industry_filter==='Hollywood'?'active':''; ?>">🎬 Hollywood</a>
                <a href="?industry=Bollywood" class="tab-btn <?php echo $industry_filter==='Bollywood'?'active':''; ?>">🎥 Bollywood</a>
                <a href="?industry=Tollywood" class="tab-btn <?php echo $industry_filter==='Tollywood'?'active':''; ?>">🎞 Tollywood</a>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
        <div style="margin-left:auto;color:var(--muted);font-size:.82rem;"><i class="fas fa-info-circle"></i> Homepage shows max <strong style="color:var(--text);"><?php echo $max_movies; ?></strong> movies · <a href="settings.php" style="color:var(--primary);">Change in Settings</a></div>
    </div>

    <div class="section-card">
        <div class="overflow-x">
        <table class="data-table">
            <thead>
                <tr><th>Poster</th><th>Title</th><th>Industry</th><th>Genre</th><th>Language</th><th>Duration</th><th>Rating</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (count($movies) > 0): ?>
                <?php foreach ($movies as $m): ?>
                <tr>
                    <td><img src="<?php echo htmlspecialchars($m['poster_url']); ?>" class="poster-thumb" onerror="this.style.display='none'"></td>
                    <td><strong><?php echo htmlspecialchars($m['title']); ?></strong></td>
                    <td><span class="ind-badge ind-<?php echo $m['industry'] ?: 'Other'; ?>"><?php echo $m['industry'] ?: 'N/A'; ?></span></td>
                    <td style="font-size:.82rem; color:var(--muted);"><?php echo htmlspecialchars($m['genre']); ?></td>
                    <td><?php echo htmlspecialchars($m['language']); ?></td>
                    <td><?php echo $m['duration']; ?> min</td>
                    <td style="color:#fbbf24;">⭐ <?php echo number_format($m['rating'],1); ?></td>
                    <td>
                        <?php if ($m['status']==='now_showing'): ?>
                            <span class="status-now">Now Showing</span>
                        <?php else: ?>
                            <span class="status-up">Upcoming</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <button class="btn-sm btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($m)); ?>)"><i class="fas fa-edit"></i> Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this movie?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="movie_id" value="<?php echo $m['id']; ?>">
                            <button type="submit" class="btn-sm btn-del" style="margin-left:4px;"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No movies found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
<div class="modal-box">
    <h3><i class="fas fa-plus" style="color:var(--primary);"></i> Add New Movie</h3>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="form-group full"><label>Movie Title *</label><input type="text" name="title" required placeholder="e.g. Inception"></div>
            <div class="form-group full"><label>Description</label><textarea name="description" placeholder="Brief plot summary..."></textarea></div>
            <div class="form-group"><label>Genre</label><input type="text" name="genre" placeholder="e.g. Action, Sci-Fi"></div>
            <div class="form-group"><label>Language</label><input type="text" name="language" placeholder="e.g. English"></div>
            <div class="form-group"><label>Duration (min)</label><input type="number" name="duration" placeholder="148" min="1"></div>
            <div class="form-group"><label>Rating (0-10)</label><input type="number" name="rating" step="0.1" min="0" max="10" placeholder="8.5"></div>
            <div class="form-group full"><label>Cast</label><input type="text" name="cast" placeholder="e.g. Actor 1, Actor 2"></div>
            <div class="form-group full"><label>Trailer URL (YouTube embed)</label><input type="url" name="trailer_url" placeholder="https://www.youtube.com/embed/..."></div>
            <div class="form-group full"><label>Poster URL</label><input type="url" name="poster_url" placeholder="https://..."></div>
            <div class="form-group"><label>Industry</label>
                <select name="industry">
                    <option value="Hollywood">Hollywood</option>
                    <option value="Bollywood">Bollywood</option>
                    <option value="Tollywood">Tollywood</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="now_showing">Now Showing</option>
                    <option value="upcoming">Upcoming</option>
                </select>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-cancel" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="modal-confirm"><i class="fas fa-plus"></i> Add Movie</button>
        </div>
    </form>
</div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
<div class="modal-box">
    <h3><i class="fas fa-edit" style="color:#60a5fa;"></i> Edit Movie</h3>
    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="movie_id" id="editMovieId">
        <div class="form-grid">
            <div class="form-group full"><label>Movie Title *</label><input type="text" name="title" id="editTitle" required></div>
            <div class="form-group full"><label>Description</label><textarea name="description" id="editDesc"></textarea></div>
            <div class="form-group"><label>Genre</label><input type="text" name="genre" id="editGenre"></div>
            <div class="form-group"><label>Language</label><input type="text" name="language" id="editLang"></div>
            <div class="form-group"><label>Duration (min)</label><input type="number" name="duration" id="editDur"></div>
            <div class="form-group"><label>Rating (0-10)</label><input type="number" name="rating" step="0.1" id="editRating"></div>
            <div class="form-group full"><label>Cast</label><input type="text" name="cast" id="editCast"></div>
            <div class="form-group full"><label>Trailer URL</label><input type="url" name="trailer_url" id="editTrailer"></div>
            <div class="form-group full"><label>Poster URL</label><input type="url" name="poster_url" id="editPoster"></div>
            <div class="form-group"><label>Industry</label>
                <select name="industry" id="editIndustry">
                    <option value="Hollywood">Hollywood</option>
                    <option value="Bollywood">Bollywood</option>
                    <option value="Tollywood">Tollywood</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="editStatus">
                    <option value="now_showing">Now Showing</option>
                    <option value="upcoming">Upcoming</option>
                </select>
            </div>
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
function openEditModal(m) {
    document.getElementById('editMovieId').value  = m.id;
    document.getElementById('editTitle').value    = m.title;
    document.getElementById('editDesc').value     = m.description;
    document.getElementById('editGenre').value    = m.genre;
    document.getElementById('editLang').value     = m.language;
    document.getElementById('editDur').value      = m.duration;
    document.getElementById('editRating').value   = m.rating;
    document.getElementById('editCast').value     = m.cast;
    document.getElementById('editTrailer').value  = m.trailer_url;
    document.getElementById('editPoster').value   = m.poster_url;
    document.getElementById('editIndustry').value = m.industry;
    document.getElementById('editStatus').value   = m.status;
    document.getElementById('editModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); }));
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(o=>o.classList.remove('open')); });
</script>
</body>
</html>
