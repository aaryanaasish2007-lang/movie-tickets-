<?php
require_once 'config/db.php';

// Force Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['movie_id']) || empty($_GET['movie_id'])) {
    header("Location: index.php");
    exit;
}

$movie_id = (int)$_GET['movie_id'];

// Fetch Movie details
$stmt = $pdo->prepare("SELECT title FROM movies WHERE id = ?");
$stmt->execute([$movie_id]);
$movie = $stmt->fetch();

if (!$movie) {
    header("Location: index.php");
    exit;
}

// Fetch Shows for this movie
$stmt = $pdo->prepare("
    SELECT s.id as show_id, s.show_date, s.show_time, s.price, t.name as theatre_name 
    FROM shows s 
    JOIN theatres t ON s.theatre_id = t.id 
    WHERE s.movie_id = ? AND s.show_date >= CURDATE()
    ORDER BY s.show_date ASC, s.show_time ASC
");
$stmt->execute([$movie_id]);
$shows = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<style>
    .booking-container {
        padding: 3rem 5%;
        max-width: 1200px;
        margin: auto;
    }
    .booking-header {
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--glass-border);
        padding-bottom: 1rem;
    }
    .booking-header h1 {
        color: var(--primary-color);
    }
    
    .shows-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }
    
    .show-card {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        padding: 1.5rem;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .show-card:hover, .show-card.selected {
        border-color: var(--primary-color);
        box-shadow: 0 0 15px rgba(229, 9, 20, 0.4);
    }
    
    .show-card h3 {
        margin-bottom: 0.5rem;
    }
    .show-card .time {
        font-size: 1.2rem;
        font-weight: bold;
        color: var(--primary-color);
        margin: 10px 0;
    }

    .seat-selection {
        display: none;
        background: var(--card-bg);
        padding: 2rem;
        border-radius: 10px;
        border: 1px solid var(--glass-border);
        margin-bottom: 3rem;
        text-align: center;
    }

    .screen {
        height: 50px;
        background: linear-gradient(to bottom, #fff, transparent);
        margin-bottom: 2rem;
        transform: perspective(200px) rotateX(-5deg);
        box-shadow: 0 10px 20px rgba(255,255,255,0.2);
    }

    .seats-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }

    .seat-row {
        display: flex;
        gap: 10px;
    }

    .seat {
        width: 30px;
        height: 30px;
        background: #444;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .seat:hover { background: #666; }
    .seat.selected { background: var(--primary-color); }
    .seat.occupied { background: #E50914; opacity: 0.2; cursor: not-allowed; }

    .booking-summary {
        display: none;
        background: rgba(255,255,255,0.05);
        padding: 1.5rem;
        border-radius: 10px;
        margin-top: 2rem;
        text-align: left;
    }

    .btn-proceed {
        display: inline-block;
        background: var(--primary-color);
        color: white;
        padding: 1rem 2rem;
        border: none;
        border-radius: 5px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        margin-top: 1.5rem;
        width: 100%;
        transition: background 0.3s;
    }
    .btn-proceed:hover {
        background: #c10710;
    }
</style>

<div class="booking-container fade-in">
    <div class="booking-header">
        <h1>Book Tickets: <?php echo htmlspecialchars($movie['title']); ?></h1>
        <p>Step 1: Select Date and Theatre</p>
    </div>

    <?php if (count($shows) > 0): ?>
        <div class="shows-grid">
            <?php foreach ($shows as $show): ?>
                <div class="show-card" onclick="selectShow(<?php echo $show['show_id']; ?>, <?php echo $show['price']; ?>, this)">
                    <h3><?php echo htmlspecialchars($show['theatre_name']); ?></h3>
                    <div class="time"><?php echo date('h:i A', strtotime($show['show_time'])); ?></div>
                    <p><i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($show['show_date'])); ?></p>
                    <p style="margin-top: 5px; color: var(--text-muted);">Price: ₹<?php echo $show['price']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="seat-selection" id="seatSelection">
            <h2 style="margin-bottom: 2rem;">Step 2: Select Seats</h2>
            <div class="screen">SCREEN</div>
            
            <div class="seats-grid" id="seatsGrid">
                <!-- Seats will be generated here by JS -->
            </div>

            <div style="display:flex; justify-content:center; gap: 20px; margin-top: 20px;">
                <div style="display:flex; align-items:center; gap:5px;"><div class="seat"></div> Available</div>
                <div style="display:flex; align-items:center; gap:5px;"><div class="seat selected"></div> Selected</div>
                <div style="display:flex; align-items:center; gap:5px;"><div class="seat occupied"></div> Occupied</div>
            </div>

            <div class="booking-summary" id="bookingSummary">
                <h3>Booking Summary</h3>
                <p>Selected Seats: <span id="selectedSeatsText">None</span></p>
                <p>Total Amount: ₹<span id="totalAmountText">0</span></p>
                
                <form action="payment.php" method="POST" id="bookingForm">
                    <input type="hidden" name="show_id" id="formShowId">
                    <input type="hidden" name="seat_numbers" id="formSeatNumbers">
                    <input type="hidden" name="total_amount" id="formTotalAmount">
                    <button type="submit" class="btn-proceed" id="proceedBtn" disabled>Proceed to Payment</button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <p>No shows available for this movie at the moment.</p>
    <?php endif; ?>
</div>

<script>
    let selectedShowId = null;
    let selectedPrice = 0;
    let selectedSeatsArr = [];

    function selectShow(showId, price, element) {
        // Highlight selected show
        document.querySelectorAll('.show-card').forEach(c => c.classList.remove('selected'));
        element.classList.add('selected');
        
        selectedShowId = showId;
        selectedPrice = price;
        selectedSeatsArr = [];
        updateSummary();

        // Show seat selection area
        document.getElementById('seatSelection').style.display = 'block';
        
        // Generate seats (mocking 6 rows of 8 seats)
        generateSeats();
    }

    function generateSeats() {
        const grid = document.getElementById('seatsGrid');
        grid.innerHTML = '';
        
        const rows = ['A', 'B', 'C', 'D', 'E', 'F'];
        const cols = 8;
        
        rows.forEach(row => {
            const rowDiv = document.createElement('div');
            rowDiv.className = 'seat-row';
            
            for(let i=1; i<=cols; i++) {
                const seatId = row + i;
                const seatDiv = document.createElement('div');
                seatDiv.className = 'seat';
                
                // Randomly mock some occupied seats for demo
                if(Math.random() < 0.2) {
                    seatDiv.classList.add('occupied');
                } else {
                    seatDiv.onclick = function() { toggleSeat(this, seatId); };
                }
                
                rowDiv.appendChild(seatDiv);
            }
            grid.appendChild(rowDiv);
        });
    }

    function toggleSeat(seatElement, seatId) {
        if(seatElement.classList.contains('occupied')) return;
        
        if(seatElement.classList.contains('selected')) {
            seatElement.classList.remove('selected');
            selectedSeatsArr = selectedSeatsArr.filter(id => id !== seatId);
        } else {
            seatElement.classList.add('selected');
            selectedSeatsArr.push(seatId);
        }
        
        updateSummary();
    }

    function updateSummary() {
        const summaryDiv = document.getElementById('bookingSummary');
        const proceedBtn = document.getElementById('proceedBtn');
        
        if(selectedSeatsArr.length > 0) {
            summaryDiv.style.display = 'block';
            document.getElementById('selectedSeatsText').textContent = selectedSeatsArr.join(', ');
            
            const total = selectedSeatsArr.length * selectedPrice;
            document.getElementById('totalAmountText').textContent = total;
            
            document.getElementById('formShowId').value = selectedShowId;
            document.getElementById('formSeatNumbers').value = selectedSeatsArr.join(',');
            document.getElementById('formTotalAmount').value = total;
            
            proceedBtn.disabled = false;
        } else {
            document.getElementById('selectedSeatsText').textContent = 'None';
            document.getElementById('totalAmountText').textContent = '0';
            proceedBtn.disabled = true;
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
