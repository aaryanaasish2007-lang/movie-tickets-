<?php
require_once 'includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$movie_id = (int)$_GET['id'];

// Fetch movie details
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch();

if (!$movie) {
    echo "<div style='text-align:center; padding: 5rem;'><h2 style='color: var(--primary-color);'>Movie not found.</h2><a href='index.php' class='btn btn-primary' style='display:inline-block; margin-top:1rem;'>Back to Home</a></div>";
    require_once 'includes/footer.php';
    exit;
}
?>

<style>
    .movie-header {
        position: relative;
        height: 50vh;
        background: linear-gradient(to top, var(--bg-color), rgba(15,15,15,0.5)), url('<?php echo htmlspecialchars($movie['poster_url']); ?>') center/cover;
        display: flex;
        align-items: flex-end;
        padding: 3rem 5%;
    }
    
    .movie-header-content {
        display: flex;
        gap: 2rem;
        align-items: flex-end;
        z-index: 10;
        width: 100%;
    }

    .poster-img {
        width: 250px;
        border-radius: 10px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.8);
        border: 2px solid var(--glass-border);
    }

    .movie-meta h1 {
        font-size: 3rem;
        margin-bottom: 0.5rem;
    }

    .tags {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .tag {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }

    .tag i {
        color: var(--primary-color);
        margin-right: 5px;
    }

    .movie-details-section {
        padding: 3rem 5%;
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 3rem;
    }

    .synopsis h2 {
        margin-bottom: 1rem;
        color: var(--primary-color);
    }

    .synopsis p {
        font-size: 1.1rem;
        color: var(--text-muted);
        line-height: 1.8;
    }

    .trailer-container {
        margin-top: 2rem;
        position: relative;
        padding-bottom: 56.25%; /* 16:9 */
        height: 0;
        overflow: hidden;
        border-radius: 10px;
    }

    .trailer-container iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }

    .booking-card {
        background: var(--card-bg);
        padding: 2rem;
        border-radius: 10px;
        border: 1px solid var(--glass-border);
        text-align: center;
        height: fit-content;
        position: sticky;
        top: 100px;
    }

    .booking-card h3 {
        margin-bottom: 1rem;
    }

    .btn-book-large {
        display: block;
        width: 100%;
        background: var(--primary-color);
        color: white;
        padding: 1rem;
        border: none;
        border-radius: 5px;
        font-size: 1.2rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
        margin-top: 1rem;
    }

    .btn-book-large:hover {
        background: #c10710;
    }

    @media (max-width: 768px) {
        .movie-header-content {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .movie-details-section {
            grid-template-columns: 1fr;
        }
        .tags {
            justify-content: center;
        }
    }
</style>

<div class="movie-header fade-in">
    <div class="movie-header-content">
        <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Poster" class="poster-img">
        <div class="movie-meta">
            <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
            <div class="tags">
                <span class="tag"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($movie['duration']); ?> min</span>
                <span class="tag"><i class="fas fa-film"></i> <?php echo htmlspecialchars($movie['genre']); ?></span>
                <span class="tag"><i class="fas fa-language"></i> <?php echo htmlspecialchars($movie['language']); ?></span>
                <span class="tag"><i class="fas fa-star"></i> <?php echo number_format($movie['rating'], 1); ?>/10</span>
            </div>
        </div>
    </div>
</div>

<div class="movie-details-section fade-in" style="animation-delay: 0.2s;">
    <div class="left-col">
        <div class="synopsis">
            <h2>Synopsis</h2>
            <p><?php echo nl2br(htmlspecialchars($movie['description'])); ?></p>
        </div>

        <div class="synopsis" style="margin-top: 2rem;">
            <h2>Cast</h2>
            <p><?php echo htmlspecialchars($movie['cast']); ?></p>
        </div>

        <?php if (!empty($movie['trailer_url'])): ?>
        <div class="trailer-container">
            <!-- Ensure trailer_url in DB is embeddable URL like https://www.youtube.com/embed/... -->
            <iframe src="<?php echo htmlspecialchars($movie['trailer_url']); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        <?php endif; ?>
    </div>

    <div class="right-col">
        <div class="booking-card">
            <h3>Ready to Watch?</h3>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Select your preferred date, theatre, and show time.</p>
            <a href="booking.php?movie_id=<?php echo $movie['id']; ?>" class="btn-book-large">Book Tickets Now</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
