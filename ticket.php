<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$booking_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch complex booking data
$stmt = $pdo->prepare("
    SELECT b.seat_numbers, b.total_amount, b.booking_date, b.status as booking_status,
           m.title, m.poster_url, m.duration,
           s.show_date, s.show_time,
           t.name as theatre_name, t.location,
           u.name as user_name,
           p.transaction_id, p.payment_method
    FROM bookings b
    JOIN shows s ON b.show_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN theatres t ON s.theatre_id = t.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$booking_id, $user_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo "<h2 style='text-align:center; color:white; margin-top: 5rem;'>Ticket not found or unauthorized access.</h2>";
    exit;
}

require_once 'includes/header.php';
?>

<style>
    .ticket-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 2rem 5%;
    }

    .ticket {
        background: white;
        color: #333;
        width: 100%;
        max-width: 800px;
        display: flex;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 15px 30px rgba(0,0,0,0.8);
        position: relative;
    }

    /* Serrated Edge effect */
    .ticket::before, .ticket::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        width: 20px;
        background-size: 20px 20px;
    }
    
    .ticket-left {
        flex: 1.5;
        padding: 2rem;
        position: relative;
        border-right: 2px dashed #ccc;
    }
    
    .ticket-right {
        flex: 1;
        padding: 2rem;
        background: #f9f9f9;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
    }

    .movie-poster-sm {
        width: 100px;
        border-radius: 5px;
        margin-bottom: 1rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }

    .ticket-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .ticket-title {
        font-size: 2rem;
        font-weight: 800;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
        line-height: 1.1;
    }

    .ticket-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .info-box span {
        display: block;
        font-size: 0.8rem;
        color: #777;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.2rem;
    }
    
    .info-box strong {
        font-size: 1.1rem;
        color: #222;
    }

    .barcode-container {
        text-align: center;
        margin-top: auto;
    }

    .barcode {
        font-family: 'Libre Barcode 39', cursive; /* Ideal to load a barcode font, using simple lines for mock */
        font-size: 3rem;
        letter-spacing: 2px;
        color: #000;
    }

    .status-badge {
        background: #2ecc71;
        color: white;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .ticket {
            flex-direction: column;
        }
        .ticket-left {
            border-right: none;
            border-bottom: 2px dashed #ccc;
        }
    }
</style>

<!-- Add a barcode font for aesthetics -->
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">

<div class="ticket-container fade-in">
    <div class="ticket">
        <div class="ticket-left">
            <div class="ticket-header">
                <div>
                    <h1 class="ticket-title"><?php echo htmlspecialchars($ticket['title']); ?></h1>
                    <p style="color: #555;"><?php echo htmlspecialchars($ticket['theatre_name']); ?> - <?php echo htmlspecialchars($ticket['location']); ?></p>
                </div>
                <span class="status-badge">CONFIRMED</span>
            </div>

            <div class="ticket-info-grid">
                <div class="info-box">
                    <span>Date</span>
                    <strong><?php echo date('d M Y', strtotime($ticket['show_date'])); ?></strong>
                </div>
                <div class="info-box">
                    <span>Time</span>
                    <strong><?php echo date('h:i A', strtotime($ticket['show_time'])); ?></strong>
                </div>
                <div class="info-box">
                    <span>Seats</span>
                    <strong><?php echo htmlspecialchars($ticket['seat_numbers']); ?></strong>
                </div>
                <div class="info-box">
                    <span>Booked By</span>
                    <strong><?php echo htmlspecialchars($ticket['user_name']); ?></strong>
                </div>
            </div>

            <div style="border-top: 1px solid #eee; padding-top: 1rem; margin-top: 1rem;">
                <p style="font-size: 0.9rem; color: #666;">Booking ID: #CINE-<?php echo $booking_id; ?> | TXN: <?php echo htmlspecialchars($ticket['transaction_id']); ?></p>
            </div>
        </div>
        
        <div class="ticket-right">
            <img src="<?php echo htmlspecialchars($ticket['poster_url']); ?>" alt="Poster" class="movie-poster-sm">
            
            <div style="text-align:center; width:100%;">
                <div class="info-box" style="margin-bottom: 1rem;">
                    <span>Total Paid</span>
                    <strong style="color: var(--primary-color); font-size: 1.5rem;">₹<?php echo htmlspecialchars($ticket['total_amount']); ?></strong>
                </div>
            </div>

            <div class="barcode-container">
                <div class="barcode">*CINE<?php echo $booking_id; ?>*</div>
                <p style="font-size: 0.7rem; color: #888; margin-top: 5px;">Scan at the entrance</p>
            </div>
            
            <button onclick="window.print()" class="btn btn-primary" style="margin-top:1rem; width:100%;"><i class="fas fa-print"></i> Print Ticket</button>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
