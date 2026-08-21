<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_payment'])) {
    // Process the payment and save booking
    $user_id = $_SESSION['user_id'];
    $show_id = $_POST['show_id'];
    $seat_numbers = $_POST['seat_numbers'];
    $total_amount = $_POST['total_amount'];
    $payment_method = $_POST['payment_method'];
    
    // 1. Insert into bookings
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, show_id, seat_numbers, total_amount, status) VALUES (?, ?, ?, ?, 'confirmed')");
    $stmt->execute([$user_id, $show_id, $seat_numbers, $total_amount]);
    $booking_id = $pdo->lastInsertId();
    
    // 2. Insert into payments
    $transaction_id = 'TXN' . strtoupper(uniqid());
    $stmt = $pdo->prepare("INSERT INTO payments (booking_id, payment_method, transaction_id, payment_status) VALUES (?, ?, ?, 'success')");
    $stmt->execute([$booking_id, $payment_method, $transaction_id]);
    
    // Redirect to ticket page
    header("Location: ticket.php?id=" . $booking_id);
    exit;
}

// First load of the page via POST from booking.php
if (!isset($_POST['show_id']) || empty($_POST['show_id'])) {
    header("Location: index.php");
    exit;
}

$show_id = $_POST['show_id'];
$seat_numbers = $_POST['seat_numbers'];
$total_amount = $_POST['total_amount'];

require_once 'includes/header.php';
?>

<style>
    .payment-container {
        max-width: 600px;
        margin: 4rem auto;
        padding: 0 5%;
    }
    .payment-card {
        background: var(--card-bg);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        padding: 2.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    }
    .payment-card h2 {
        text-align: center;
        margin-bottom: 2rem;
        color: var(--primary-color);
    }
    .order-summary {
        background: rgba(0,0,0,0.3);
        padding: 1.5rem;
        border-radius: 5px;
        margin-bottom: 2rem;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
    }
    .summary-total {
        border-top: 1px solid var(--glass-border);
        padding-top: 1rem;
        margin-top: 1rem;
        font-weight: bold;
        font-size: 1.3rem;
        color: var(--primary-color);
    }
    
    .payment-options {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .pay-option {
        display: flex;
        align-items: center;
        padding: 1rem;
        border: 1px solid var(--glass-border);
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .pay-option:hover {
        background: rgba(255,255,255,0.05);
    }
    .pay-option input[type="radio"] {
        margin-right: 15px;
        transform: scale(1.2);
    }
    .pay-option i {
        font-size: 1.5rem;
        margin-right: 15px;
        color: var(--text-muted);
    }
    .btn-pay {
        width: 100%;
        background: #2ecc71;
        color: white;
        border: none;
        padding: 1.2rem;
        border-radius: 5px;
        font-size: 1.2rem;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }
    .btn-pay:hover {
        background: #27ae60;
    }
    .btn-pay:disabled {
        background: #555;
        cursor: not-allowed;
    }
</style>

<div class="payment-container fade-in">
    <div class="payment-card">
        <h2>Secure Checkout</h2>
        
        <div class="order-summary">
            <div class="summary-row">
                <span>Seats</span>
                <span><?php echo htmlspecialchars($seat_numbers); ?></span>
            </div>
            <div class="summary-row summary-total">
                <span>Total Amount</span>
                <span>₹<?php echo htmlspecialchars($total_amount); ?></span>
            </div>
        </div>

        <form action="payment.php" method="POST" id="paymentForm">
            <input type="hidden" name="process_payment" value="1">
            <input type="hidden" name="show_id" value="<?php echo htmlspecialchars($show_id); ?>">
            <input type="hidden" name="seat_numbers" value="<?php echo htmlspecialchars($seat_numbers); ?>">
            <input type="hidden" name="total_amount" value="<?php echo htmlspecialchars($total_amount); ?>">

            <h3 style="margin-bottom: 1rem;">Select Payment Method</h3>
            <div class="payment-options">
                <label class="pay-option">
                    <input type="radio" name="payment_method" value="upi" required>
                    <i class="fas fa-qrcode"></i> UPI (GPay, PhonePe, Paytm)
                </label>
                <label class="pay-option">
                    <input type="radio" name="payment_method" value="card" required>
                    <i class="far fa-credit-card"></i> Credit / Debit Card
                </label>
                <label class="pay-option">
                    <input type="radio" name="payment_method" value="netbanking" required>
                    <i class="fas fa-university"></i> Net Banking
                </label>
                <label class="pay-option">
                    <input type="radio" name="payment_method" value="wallet" required>
                    <i class="fas fa-wallet"></i> Mobile Wallets
                </label>
            </div>

            <button type="submit" class="btn-pay" id="payBtn" disabled>Pay ₹<?php echo htmlspecialchars($total_amount); ?></button>
        </form>
    </div>
</div>

<script>
    // Enable button only when a method is selected
    const radios = document.querySelectorAll('input[name="payment_method"]');
    const payBtn = document.getElementById('payBtn');
    
    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            if(document.querySelector('input[name="payment_method"]:checked')) {
                payBtn.disabled = false;
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
