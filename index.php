<?php
require_once 'includes/header.php';

// Fetch admin setting for max movies
$max_movies_setting = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='max_movies_homepage'")->fetchColumn();
$max_movies_limit = $max_movies_setting ? (int)$max_movies_setting : 20;

// Fetch movies based on search/filter if applied
$searchQuery = "";
$genreFilter = "";
$industryFilter = isset($_GET['industry']) ? $_GET['industry'] : '';
$params = [];

$sql = "SELECT * FROM movies WHERE 1=1";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $sql .= " AND title LIKE :search";
    $params[':search'] = '%' . $_GET['search'] . '%';
    $searchQuery = htmlspecialchars($_GET['search']);
}

if (isset($_GET['genre']) && !empty($_GET['genre'])) {
    $sql .= " AND genre LIKE :genre";
    $params[':genre'] = '%' . $_GET['genre'] . '%';
    $genreFilter = htmlspecialchars($_GET['genre']);
}

if (!empty($industryFilter)) {
    $sql .= " AND industry = :industry";
    $params[':industry'] = $industryFilter;
}

$sql .= " ORDER BY created_at DESC LIMIT " . $max_movies_limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movies = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero fade-in">
    <div class="hero-content">
        <h1>Book Your Tickets Now</h1>
        <p>Experience the magic of cinema in the best theatres near you.</p>
    </div>
</section>

<!-- Search Bar -->
<div class="search-container fade-in" style="animation-delay: 0.2s;">
    <form action="index.php" method="GET" class="search-box">
        <input type="text" name="search" placeholder="Search for movies..." value="<?php echo $searchQuery; ?>">
        <select name="genre">
            <option value="">All Genres</option>
            <option value="Action" <?php echo ($genreFilter == 'Action') ? 'selected' : ''; ?>>Action</option>
            <option value="Sci-Fi" <?php echo ($genreFilter == 'Sci-Fi') ? 'selected' : ''; ?>>Sci-Fi</option>
            <option value="Drama" <?php echo ($genreFilter == 'Drama') ? 'selected' : ''; ?>>Drama</option>
            <option value="Comedy" <?php echo ($genreFilter == 'Comedy') ? 'selected' : ''; ?>>Comedy</option>
        </select>
        <button type="submit"><i class="fas fa-search"></i> Search</button>
    </form>
</div>

<!-- Industry Tabs -->
<section id="movies" class="fade-in" style="animation-delay: 0.4s;">
    <div style="padding: 0 5%; margin-bottom: 1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <a href="index.php" style="padding:.45rem 1.1rem; border-radius:20px; font-size:.85rem; font-weight:600; text-decoration:none; transition:all .25s; background:<?php echo empty($industryFilter)?'var(--primary-color)':'rgba(255,255,255,.08)'; ?>; color:<?php echo empty($industryFilter)?'white':'var(--text-muted)'; ?>;">
            🎬 All
        </a>
        <a href="?industry=Hollywood" style="padding:.45rem 1.1rem; border-radius:20px; font-size:.85rem; font-weight:600; text-decoration:none; transition:all .25s; background:<?php echo $industryFilter==='Hollywood'?'#3b82f6':'rgba(255,255,255,.08)'; ?>; color:<?php echo $industryFilter==='Hollywood'?'white':'var(--text-muted)'; ?>;">
            🇺🇸 Hollywood
        </a>
        <a href="?industry=Bollywood" style="padding:.45rem 1.1rem; border-radius:20px; font-size:.85rem; font-weight:600; text-decoration:none; transition:all .25s; background:<?php echo $industryFilter==='Bollywood'?'#f97316':'rgba(255,255,255,.08)'; ?>; color:<?php echo $industryFilter==='Bollywood'?'white':'var(--text-muted)'; ?>;">
            🇮🇳 Bollywood
        </a>
        <a href="?industry=Tollywood" style="padding:.45rem 1.1rem; border-radius:20px; font-size:.85rem; font-weight:600; text-decoration:none; transition:all .25s; background:<?php echo $industryFilter==='Tollywood'?'#22c55e':'rgba(255,255,255,.08)'; ?>; color:<?php echo $industryFilter==='Tollywood'?'white':'var(--text-muted)'; ?>;">
            🎞 Tollywood
        </a>
        <span style="margin-left:auto; font-size:.78rem; color:var(--text-muted);">Showing up to <?php echo $max_movies_limit; ?> movies</span>
    </div>
    <h2 class="section-title">
        <?php 
            if($searchQuery || $genreFilter) {
                echo "Search Results";
            } elseif($industryFilter) {
                echo $industryFilter . " Films";
            } else {
                echo "Now Showing";
            }
        ?>
    </h2>
    <div class="movie-grid">
        <?php if (count($movies) > 0): ?>
            <?php foreach ($movies as $movie): ?>
                <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" class="movie-poster">
                    <div class="movie-info">
                        <h3 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h3>
                        <p class="movie-genre"><?php echo htmlspecialchars($movie['genre']); ?></p>
                        <div class="movie-rating">
                            <i class="fas fa-star"></i> <?php echo number_format($movie['rating'], 1); ?>
                        </div>
                    </div>
                    <div class="book-btn-hover">Book Ticket</div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding: 0 5%; color: var(--text-muted);">No movies found matching your criteria.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
