<?php
require_once 'includes/header.php';

// ── Filters from GET ──────────────────────────────────────────────
$cityFilter  = isset($_GET['city'])   ? trim($_GET['city'])   : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// ── All distinct cities for tabs ──────────────────────────────────
$cities = $pdo->query("SELECT DISTINCT city FROM theatres WHERE city IS NOT NULL AND city != '' ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);

// ── Fetch theatres (with optional filters) ────────────────────────
$sql    = "SELECT * FROM theatres WHERE 1=1";
$params = [];

if ($cityFilter !== '') {
    $sql .= " AND city = :city";
    $params[':city'] = $cityFilter;
}
if ($searchQuery !== '') {
    $sql .= " AND (name LIKE :search OR location LIKE :search OR city LIKE :search)";
    $params[':search'] = '%' . $searchQuery . '%';
}

$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$theatres = $stmt->fetchAll();

// ── For each theatre fetch its currently-playing shows + movies ───
$theatre_shows = [];
foreach ($theatres as $t) {
    $showStmt = $pdo->prepare("
        SELECT s.id as show_id, s.show_time, s.show_date, s.price,
               m.id as movie_id, m.title, m.poster_url, m.genre, m.rating, m.duration, m.industry
        FROM shows s
        JOIN movies m ON s.movie_id = m.id
        WHERE s.theatre_id = ? AND s.show_date >= CURDATE()
        ORDER BY m.title ASC, s.show_date ASC, s.show_time ASC
    ");
    $showStmt->execute([$t['id']]);
    $rows = $showStmt->fetchAll();

    // Group by movie
    $movies_map = [];
    foreach ($rows as $row) {
        $mid = $row['movie_id'];
        if (!isset($movies_map[$mid])) {
            $movies_map[$mid] = [
                'movie_id'   => $row['movie_id'],
                'title'      => $row['title'],
                'poster_url' => $row['poster_url'],
                'genre'      => $row['genre'],
                'rating'     => $row['rating'],
                'duration'   => $row['duration'],
                'industry'   => $row['industry'],
                'times'      => [],
                'min_price'  => $row['price'],
            ];
        }
        $movies_map[$mid]['times'][] = [
            'show_id'   => $row['show_id'],
            'show_time' => $row['show_time'],
            'show_date' => $row['show_date'],
            'price'     => $row['price'],
        ];
        if ($row['price'] < $movies_map[$mid]['min_price']) {
            $movies_map[$mid]['min_price'] = $row['price'];
        }
    }
    $theatre_shows[$t['id']] = array_values($movies_map);
}
?>

<style>
/* ── Hero ── */
.th-hero {
    background: linear-gradient(135deg, #0f0f0f 0%, #1a0505 50%, #0f0f0f 100%);
    padding: 4rem 5% 3rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.th-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(229,9,20,0.18), transparent);
    pointer-events: none;
}
.th-hero h1 {
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 800;
    margin-bottom: 0.6rem;
    line-height: 1.15;
    position: relative;
}
.th-hero h1 span { color: var(--primary-color); }
.th-hero p {
    color: var(--text-muted);
    font-size: 1.05rem;
    margin-bottom: 2rem;
    position: relative;
}

/* ── Search bar ── */
.th-search-form {
    display: flex;
    max-width: 520px;
    margin: 0 auto;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 50px;
    overflow: hidden;
    transition: box-shadow 0.3s, border-color 0.3s;
    position: relative;
}
.th-search-form:focus-within {
    box-shadow: 0 0 0 3px rgba(229,9,20,0.25);
    border-color: var(--primary-color);
}
.th-search-form input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    padding: 0.85rem 1.4rem;
    color: #fff;
    font-size: 0.95rem;
    font-family: 'Inter', sans-serif;
}
.th-search-form input::placeholder { color: #888; }
.th-search-form button {
    background: var(--primary-color);
    border: none;
    padding: 0 1.4rem;
    color: white;
    cursor: pointer;
    font-size: 0.9rem;
    transition: background 0.25s;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    white-space: nowrap;
}
.th-search-form button:hover { background: #c10710; }

/* ── City tabs ── */
.city-tabs-wrap {
    padding: 0 5%;
    margin: 2rem 0 0;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.city-tab {
    padding: 0.45rem 1.2rem;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.25s;
    border: 1px solid rgba(255,255,255,0.1);
    color: var(--text-muted);
    background: rgba(255,255,255,0.05);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.city-tab:hover { border-color: var(--primary-color); color: #fff; }
.city-tab.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
    box-shadow: 0 0 14px rgba(229,9,20,0.4);
}

/* ── Section ── */
.th-section {
    padding: 2rem 5% 4rem;
}
.th-section-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 1.8rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    flex-wrap: wrap;
}
.th-section-title i { color: var(--primary-color); }
.th-count-chip {
    font-size: 0.78rem;
    background: rgba(229,9,20,0.15);
    color: var(--primary-color);
    border: 1px solid rgba(229,9,20,0.3);
    padding: 0.2rem 0.7rem;
    border-radius: 20px;
    font-weight: 600;
}

/* ── Theatre grid & cards ── */
.theatre-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(370px, 1fr));
    gap: 1.8rem;
}
.theatre-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    overflow: hidden;
    transition: border-color 0.3s, box-shadow 0.3s, transform 0.3s;
    display: flex;
    flex-direction: column;
    animation: fadeSlideUp 0.45s ease both;
}
.theatre-card:hover {
    border-color: rgba(229,9,20,0.4);
    box-shadow: 0 8px 40px rgba(229,9,20,0.12);
    transform: translateY(-4px);
}

/* Theatre header */
.tc-header {
    padding: 1.3rem 1.4rem 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.tc-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(229,9,20,0.2), rgba(229,9,20,0.05));
    border: 1px solid rgba(229,9,20,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--primary-color);
    flex-shrink: 0;
}
.tc-info { flex: 1; min-width: 0; }
.tc-info h3 {
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tc-location {
    color: var(--text-muted);
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tc-location i { color: var(--primary-color); font-size: 0.72rem; flex-shrink: 0; }
.tc-badges { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.tc-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.18rem 0.55rem;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-city  { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.25); }
.badge-seats { background: rgba(34,197,94,0.12);  color: #4ade80; border: 1px solid rgba(34,197,94,0.25); }

/* Movies list */
.tc-movies {
    padding: 1rem 1.4rem 0.8rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.tc-movies-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted);
    font-weight: 600;
    margin-bottom: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.tc-movies-label i { color: var(--primary-color); }

.tc-movie-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.65rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: padding-left 0.2s;
}
.tc-movie-row:last-child { border-bottom: none; }
.tc-movie-row:hover { padding-left: 4px; }

.tc-movie-poster {
    width: 36px; height: 50px;
    object-fit: cover;
    border-radius: 5px;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,0.1);
    background: #1e1e1e;
}
.tc-movie-details { flex: 1; min-width: 0; }
.tc-movie-title {
    font-size: 0.86rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.2rem;
}
.tc-movie-meta {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.tc-movie-meta .dot { opacity: 0.35; }
.tc-movie-meta .rating { color: #f59e0b; font-weight: 600; }

.tc-times {
    display: flex;
    gap: 0.28rem;
    flex-wrap: wrap;
    margin-top: 0.3rem;
}
.time-chip {
    font-size: 0.67rem;
    font-weight: 600;
    background: rgba(229,9,20,0.1);
    color: #f87171;
    border: 1px solid rgba(229,9,20,0.2);
    padding: 0.12rem 0.45rem;
    border-radius: 4px;
    white-space: nowrap;
}
.time-chip-more {
    font-size: 0.67rem;
    font-weight: 600;
    background: rgba(255,255,255,0.05);
    color: #888;
    border: 1px solid rgba(255,255,255,0.08);
    padding: 0.12rem 0.45rem;
    border-radius: 4px;
}

.tc-book-btn {
    padding: 0.38rem 0.85rem;
    border-radius: 6px;
    background: var(--primary-color);
    color: white;
    font-size: 0.72rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: background 0.2s, transform 0.15s;
    flex-shrink: 0;
}
.tc-book-btn:hover { background: #c10710; transform: scale(1.05); }

.tc-no-shows {
    color: var(--text-muted);
    font-size: 0.84rem;
    padding: 1.2rem 0;
    text-align: center;
    opacity: 0.65;
}
.tc-no-shows i { margin-right: 0.4rem; }

/* Card footer */
.tc-footer {
    padding: 0.75rem 1.4rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    background: rgba(255,255,255,0.02);
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.78rem;
    color: var(--text-muted);
}
.tc-footer-count { display: flex; align-items: center; gap: 0.4rem; }
.tc-footer-count i { color: var(--primary-color); }
.tc-browse-link {
    color: var(--primary-color);
    font-weight: 600;
    text-decoration: none;
    font-size: 0.76rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.tc-browse-link:hover { text-decoration: underline; }

/* Empty state */
.th-empty {
    text-align: center;
    padding: 5rem 2rem;
    color: var(--text-muted);
}
.th-empty i { font-size: 3.5rem; display: block; margin-bottom: 1rem; opacity: 0.3; }
.th-empty p { font-size: 1rem; margin-bottom: 1.2rem; }
.th-empty a {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--primary-color);
    font-weight: 600;
    text-decoration: none;
    padding: 0.6rem 1.4rem;
    border: 1px solid rgba(229,9,20,0.4);
    border-radius: 8px;
    transition: background 0.25s;
}
.th-empty a:hover { background: rgba(229,9,20,0.1); }

/* Animation */
@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}

@media (max-width: 600px) {
    .theatre-grid { grid-template-columns: 1fr; }
    .th-hero h1 { font-size: 1.8rem; }
    .tc-header { gap: 0.7rem; }
}
</style>

<!-- HERO -->
<section class="th-hero fade-in">
    <h1>Find <span>Theatres</span> Near You</h1>
    <p>Browse cinemas by city &amp; book tickets instantly for your favourite movies</p>

    <form action="theatres.php" method="GET" class="th-search-form">
        <?php if ($cityFilter): ?>
            <input type="hidden" name="city" value="<?php echo htmlspecialchars($cityFilter); ?>">
        <?php endif; ?>
        <input
            type="text"
            name="search"
            id="theatreSearchInput"
            placeholder="Search theatre name or area…"
            value="<?php echo htmlspecialchars($searchQuery); ?>"
            autocomplete="off"
        >
        <button type="submit"><i class="fas fa-search"></i> Search</button>
    </form>
</section>

<!-- CITY FILTER TABS -->
<div class="city-tabs-wrap fade-in" style="animation-delay:0.15s;">
    <?php
        $baseSearch = $searchQuery ? '?search='.urlencode($searchQuery) : '';
    ?>
    <a href="theatres.php<?php echo $baseSearch; ?>"
       class="city-tab <?php echo $cityFilter === '' ? 'active' : ''; ?>">
        <i class="fas fa-globe-asia"></i> All Cities
    </a>
    <?php foreach ($cities as $city): ?>
        <?php
            $cityHref = 'theatres.php?city=' . urlencode($city);
            if ($searchQuery) $cityHref .= '&search=' . urlencode($searchQuery);
        ?>
        <a href="<?php echo $cityHref; ?>"
           class="city-tab <?php echo $cityFilter === $city ? 'active' : ''; ?>">
            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($city); ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- THEATRES GRID -->
<section class="th-section fade-in" style="animation-delay:0.28s;">
    <div class="th-section-title">
        <i class="fas fa-building"></i>
        <?php
            if ($cityFilter)      echo htmlspecialchars($cityFilter) . ' Theatres';
            elseif ($searchQuery) echo 'Search Results';
            else                  echo 'All Theatres';
        ?>
        <span class="th-count-chip"><?php echo count($theatres); ?> found</span>
    </div>

    <?php if (count($theatres) > 0): ?>
    <div class="theatre-grid">
        <?php foreach ($theatres as $idx => $t):
            $t_movies = $theatre_shows[$t['id']];
            $total_shows = array_sum(array_map(fn($m) => count($m['times']), $t_movies));
            $delay = $idx * 0.07;
        ?>
        <div class="theatre-card" style="animation-delay:<?php echo $delay; ?>s;">

            <!-- Header -->
            <div class="tc-header">
                <div class="tc-icon"><i class="fas fa-film"></i></div>
                <div class="tc-info">
                    <h3><?php echo htmlspecialchars($t['name']); ?></h3>
                    <div class="tc-location" title="<?php echo htmlspecialchars($t['location']); ?>">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($t['location']); ?>
                    </div>
                    <div class="tc-badges">
                        <span class="tc-badge badge-city">
                            <i class="fas fa-city"></i> <?php echo htmlspecialchars($t['city']); ?>
                        </span>
                        <span class="tc-badge badge-seats">
                            <i class="fas fa-chair"></i> <?php echo number_format($t['total_seats']); ?> seats
                        </span>
                    </div>
                </div>
            </div>

            <!-- Movies currently showing at this theatre -->
            <div class="tc-movies">
                <div class="tc-movies-label">
                    <i class="fas fa-clapperboard"></i>
                    Now Showing — <?php echo count($t_movies); ?> film<?php echo count($t_movies) != 1 ? 's' : ''; ?>
                </div>

                <?php if (count($t_movies) > 0): ?>
                    <?php foreach ($t_movies as $m): ?>
                    <div class="tc-movie-row">
                        <img
                            class="tc-movie-poster"
                            src="<?php echo htmlspecialchars($m['poster_url']); ?>"
                            alt="<?php echo htmlspecialchars($m['title']); ?>"
                            onerror="this.style.opacity='0';"
                            loading="lazy"
                        >
                        <div class="tc-movie-details">
                            <div class="tc-movie-title"><?php echo htmlspecialchars($m['title']); ?></div>
                            <div class="tc-movie-meta">
                                <span><?php echo htmlspecialchars($m['genre']); ?></span>
                                <span class="dot">•</span>
                                <span><?php echo $m['duration']; ?> min</span>
                                <span class="dot">•</span>
                                <span class="rating">
                                    <i class="fas fa-star" style="font-size:0.62rem;"></i>
                                    <?php echo number_format($m['rating'],1); ?>
                                </span>
                                <span class="dot">•</span>
                                <span>From ₹<?php echo number_format($m['min_price'],0); ?></span>
                            </div>
                            <div class="tc-times">
                                <?php foreach (array_slice($m['times'], 0, 4) as $st): ?>
                                    <span class="time-chip"><?php echo date('h:i A', strtotime($st['show_time'])); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($m['times']) > 4): ?>
                                    <span class="time-chip-more">+<?php echo count($m['times']) - 4; ?> more</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="booking.php?movie_id=<?php echo $m['movie_id']; ?>" class="tc-book-btn">
                            <i class="fas fa-ticket-alt"></i> Book
                        </a>
                    </div>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="tc-no-shows">
                        <i class="fas fa-calendar-times"></i>
                        No shows scheduled right now
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="tc-footer">
                <span class="tc-footer-count">
                    <i class="fas fa-ticket-alt"></i>
                    <?php echo $total_shows; ?> show<?php echo $total_shows != 1 ? 's' : ''; ?> available
                </span>
                <a href="index.php" class="tc-browse-link">
                    Browse all movies <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                </a>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="th-empty">
        <i class="fas fa-building"></i>
        <p>
            <?php
                if ($searchQuery)   echo 'No theatres found for &ldquo;' . htmlspecialchars($searchQuery) . '&rdquo;';
                elseif ($cityFilter) echo 'No theatres found in &ldquo;' . htmlspecialchars($cityFilter) . '&rdquo;';
                else                 echo 'No theatres available at the moment.';
            ?>
        </p>
        <a href="theatres.php"><i class="fas fa-redo"></i> View all theatres</a>
    </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
