<?php
require_once 'config/db.php';

// Force Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch all bookings for this user
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$today = date('Y-m-d');

$sql = "
    SELECT b.id, b.booking_date, b.seat_numbers, b.total_amount, b.status,
           m.title as movie_title, m.poster_url, m.genre, m.industry,
           s.show_date, s.show_time, s.price,
           t.name as theatre_name, t.location as theatre_location
    FROM bookings b
    JOIN shows s ON b.show_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN theatres t ON s.theatre_id = t.id
    WHERE b.user_id = ?
";

switch ($filter) {
    case 'past':
        $sql .= " AND s.show_date < '$today'";
        break;
    case 'today':
        $sql .= " AND s.show_date = '$today'";
        break;
    case 'upcoming':
        $sql .= " AND s.show_date > '$today'";
        break;
}

$sql .= " ORDER BY s.show_date DESC, s.show_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// Count stats
$count_all      = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN shows s ON b.show_id=s.id WHERE b.user_id=?");
$count_all->execute([$user_id]);
$total_all = $count_all->fetchColumn();

$count_past     = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN shows s ON b.show_id=s.id WHERE b.user_id=? AND s.show_date < CURDATE()");
$count_past->execute([$user_id]);
$total_past = $count_past->fetchColumn();

$count_today    = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN shows s ON b.show_id=s.id WHERE b.user_id=? AND s.show_date = CURDATE()");
$count_today->execute([$user_id]);
$total_today = $count_today->fetchColumn();

$count_upcoming = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN shows s ON b.show_id=s.id WHERE b.user_id=? AND s.show_date > CURDATE()");
$count_upcoming->execute([$user_id]);
$total_upcoming = $count_upcoming->fetchColumn();

$total_spent_stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bookings WHERE user_id=? AND status='confirmed'");
$total_spent_stmt->execute([$user_id]);
$total_spent = $total_spent_stmt->fetchColumn() ?: 0;

require_once 'includes/header.php';
?>
<style>
    .dashboard-wrap {
        padding: 2.5rem 5%;
        max-width: 1300px;
        margin: auto;
    }
    .dash-hero {
        background: linear-gradient(135deg, rgba(229,9,20,0.15) 0%, rgba(15,15,15,0) 60%);
        border: 1px solid rgba(229,9,20,0.2);
        border-radius: 16px;
        padding: 2rem 2.5rem;
        display: flex;
        align-items: center;
        gap: 2rem;
        margin-bottom: 2rem;
    }
    .dash-avatar {
        width: 80px; height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #E50914, #ff6b35);
        display: flex; align-items: center; justify-content: center;
        font-size: 2.2rem; font-weight: 800; color: white;
        flex-shrink: 0;
    }
    .dash-hero-info h2 { font-size: 1.8rem; margin-bottom: 0.3rem; }
    .dash-hero-info p { color: var(--text-muted); font-size: 0.95rem; }
    .dash-hero-info .role-badge {
        display: inline-block;
        background: rgba(229,9,20,0.2);
        color: #E50914;
        border: 1px solid rgba(229,9,20,0.4);
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-top: 0.5rem;
    }

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.2rem;
        margin-bottom: 2.5rem;
    }
    .stat-box {
        background: var(--card-bg);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 1.4rem 1.2rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        transition: border-color 0.3s, transform 0.3s;
    }
    .stat-box:hover { border-color: var(--primary-color); transform: translateY(-3px); }
    .stat-box .s-label { color: var(--text-muted); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-box .s-val { font-size: 2rem; font-weight: 800; }
    .stat-box.red .s-val { color: var(--primary-color); }
    .stat-box.green .s-val { color: #22c55e; }
    .stat-box.blue .s-val { color: #3b82f6; }
    .stat-box.yellow .s-val { color: #f59e0b; }

    /* Tabs */
    .tab-bar {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        background: var(--card-bg);
        border: 1px solid rgba(255,255,255,0.08);
        padding: 0.4rem;
        border-radius: 12px;
        width: fit-content;
    }
    .tab-btn {
        padding: 0.6rem 1.4rem;
        border-radius: 8px;
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.25s;
    }
    .tab-btn:hover { color: var(--text-color); background: rgba(255,255,255,0.05); }
    .tab-btn.active { background: var(--primary-color); color: white; }
    .tab-btn .count-badge {
        background: rgba(255,255,255,0.2);
        padding: 0.1rem 0.5rem;
        border-radius: 10px;
        font-size: 0.75rem;
    }
    .tab-btn.active .count-badge { background: rgba(255,255,255,0.25); }

    /* Table */
    .hist-table-wrap {
        background: var(--card-bg);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        overflow: hidden;
    }
    .hist-table-wrap h3 {
        padding: 1.5rem 1.5rem 0;
        font-size: 1.1rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.85rem;
    }
    .hist-table { width: 100%; border-collapse: collapse; }
    .hist-table thead { background: rgba(255,255,255,0.03); }
    .hist-table th {
        padding: 1rem 1.2rem;
        text-align: left;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--text-muted);
        font-weight: 600;
        border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .hist-table td {
        padding: 1.1rem 1.2rem;
        font-size: 0.92rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        vertical-align: middle;
    }
    .hist-table tr:last-child td { border-bottom: none; }
    .hist-table tr:hover td { background: rgba(255,255,255,0.03); }

    .movie-thumb {
        display: flex;
        align-items: center;
        gap: 0.9rem;
    }
    .movie-thumb img {
        width: 42px; height: 58px;
        object-fit: cover;
        border-radius: 5px;
        flex-shrink: 0;
    }
    .movie-thumb .mt-info strong { font-size: 0.95rem; display: block; }
    .movie-thumb .mt-info span { font-size: 0.78rem; color: var(--text-muted); }

    .ind-badge {
        font-size: 0.68rem;
        padding: 0.15rem 0.5rem;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        display: inline-block;
        margin-top: 2px;
    }
    .ind-Hollywood { background: rgba(59,130,246,0.2); color: #60a5fa; }
    .ind-Bollywood { background: rgba(249,115,22,0.2); color: #fb923c; }
    .ind-Tollywood { background: rgba(34,197,94,0.2); color: #4ade80; }

    .status-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-confirmed { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
    .status-pending   { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
    .status-cancelled { background: rgba(229,9,20,0.15);  color: #f87171; border: 1px solid rgba(229,9,20,0.3); }

    .period-badge {
        font-size: 0.72rem;
        padding: 0.15rem 0.6rem;
        border-radius: 4px;
        font-weight: 600;
    }
    .period-past     { background: rgba(255,255,255,0.07); color: #9ca3af; }
    .period-today    { background: rgba(245,158,11,0.15);  color: #fbbf24; }
    .period-upcoming { background: rgba(34,197,94,0.15);   color: #4ade80; }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-muted);
    }
    .empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.4; }
    .empty-state p { font-size: 1rem; }
    .empty-state a { color: var(--primary-color); text-decoration: underline; }

    .ticket-link {
        color: var(--primary-color);
        font-size: 0.82rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: opacity 0.2s;
    }
    .ticket-link:hover { opacity: 0.7; }
</style>

<div class="dashboard-wrap fade-in">

    <!-- Profile Hero -->
    <div class="dash-hero">
        <div class="dash-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
        <div class="dash-hero-info">
            <h2><?php echo htmlspecialchars($user['name']); ?></h2>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
            <span class="role-badge"><i class="fas fa-user"></i> <?php echo ucfirst($user['role']); ?></span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-box red">
            <span class="s-label"><i class="fas fa-ticket-alt"></i> Total Bookings</span>
            <span class="s-val"><?php echo $total_all; ?></span>
        </div>
        <div class="stat-box">
            <span class="s-label"><i class="fas fa-history"></i> Past Shows</span>
            <span class="s-val"><?php echo $total_past; ?></span>
        </div>
        <div class="stat-box yellow">
            <span class="s-label"><i class="fas fa-calendar-day"></i> Today</span>
            <span class="s-val"><?php echo $total_today; ?></span>
        </div>
        <div class="stat-box green">
            <span class="s-label"><i class="fas fa-calendar-alt"></i> Upcoming</span>
            <span class="s-val"><?php echo $total_upcoming; ?></span>
        </div>
        <div class="stat-box blue">
            <span class="s-label"><i class="fas fa-rupee-sign"></i> Total Spent</span>
            <span class="s-val">₹<?php echo number_format($total_spent, 0); ?></span>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="tab-bar">
        <a href="?filter=all"      class="tab-btn <?php echo ($filter=='all')      ? 'active' : ''; ?>">All <span class="count-badge"><?php echo $total_all; ?></span></a>
        <a href="?filter=past"     class="tab-btn <?php echo ($filter=='past')     ? 'active' : ''; ?>"><i class="fas fa-history"></i> Past <span class="count-badge"><?php echo $total_past; ?></span></a>
        <a href="?filter=today"    class="tab-btn <?php echo ($filter=='today')    ? 'active' : ''; ?>"><i class="fas fa-calendar-day"></i> Today <span class="count-badge"><?php echo $total_today; ?></span></a>
        <a href="?filter=upcoming" class="tab-btn <?php echo ($filter=='upcoming') ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Upcoming <span class="count-badge"><?php echo $total_upcoming; ?></span></a>
    </div>

    <!-- Booking History Table -->
    <div class="hist-table-wrap">
        <?php if (count($bookings) > 0): ?>
        <div style="overflow-x:auto;">
        <table class="hist-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Movie</th>
                    <th>Theatre</th>
                    <th>Show Date & Time</th>
                    <th>Seats</th>
                    <th>Amount</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Ticket</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b):
                    $show_dt = strtotime($b['show_date']);
                    $today_ts = strtotime($today);
                    if ($show_dt < $today_ts)       $period = 'past';
                    elseif ($show_dt === $today_ts) $period = 'today';
                    else                             $period = 'upcoming';
                    $ind = $b['industry'] ?: 'Hollywood';
                ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:0.82rem;">#<?php echo $b['id']; ?></td>
                    <td>
                        <div class="movie-thumb">
                            <img src="<?php echo htmlspecialchars($b['poster_url']); ?>" alt="<?php echo htmlspecialchars($b['movie_title']); ?>">
                            <div class="mt-info">
                                <strong><?php echo htmlspecialchars($b['movie_title']); ?></strong>
                                <span><?php echo htmlspecialchars($b['genre']); ?></span>
                                <span class="ind-badge ind-<?php echo $ind; ?>"><?php echo $ind; ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($b['theatre_name']); ?></div>
                        <div style="font-size:0.78rem; color:var(--text-muted);"><?php echo htmlspecialchars($b['theatre_location']); ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?php echo date('d M Y', strtotime($b['show_date'])); ?></div>
                        <div style="font-size:0.78rem; color:var(--text-muted);"><?php echo date('h:i A', strtotime($b['show_time'])); ?></div>
                    </td>
                    <td style="font-size:0.85rem;"><?php echo htmlspecialchars($b['seat_numbers']); ?></td>
                    <td style="font-weight:700; color:#fbbf24;">₹<?php echo number_format($b['total_amount'], 2); ?></td>
                    <td><span class="period-badge period-<?php echo $period; ?>"><?php echo ucfirst($period); ?></span></td>
                    <td><span class="status-badge status-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                    <td>
                        <?php if ($b['status'] === 'confirmed'): ?>
                        <a href="ticket.php?booking_id=<?php echo $b['id']; ?>" class="ticket-link" target="_blank">
                            <i class="fas fa-ticket-alt"></i> View
                        </a>
                        <?php else: echo '—'; endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-ticket-alt"></i>
            <p>No bookings found<?php echo ($filter !== 'all') ? ' for this period' : ''; ?>.</p>
            <?php if ($filter !== 'all'): ?>
                <p style="margin-top:0.5rem;"><a href="?filter=all">View all bookings</a> or <a href="index.php">book a movie now</a>.</p>
            <?php else: ?>
                <p style="margin-top:0.5rem;"><a href="index.php">Browse movies and book your first ticket!</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
