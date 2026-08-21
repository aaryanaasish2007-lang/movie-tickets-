<?php
require_once 'includes/header.php';

$success = false;
$errors  = [];

// ── Handle form submission ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (strlen($name) < 2)               $errors[] = "Please enter your full name.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Please enter a valid email address.";
    if (strlen($subject) < 3)            $errors[] = "Please enter a subject (min 3 characters).";
    if (strlen($message) < 10)           $errors[] = "Please enter a message (min 10 characters).";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, $subject, $message]);
        $success = true;
    }
}
?>

<style>
/* ── Page Layout ── */
.contact-page { padding: 0 0 5rem; }

/* ── Hero ── */
.contact-hero {
    background: linear-gradient(135deg, #0f0f0f 0%, #1a0a0a 55%, #0f0f0f 100%);
    padding: 4.5rem 5% 3.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.contact-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 70% 55% at 50% 0%, rgba(229,9,20,0.16), transparent);
    pointer-events: none;
}
.contact-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: 5%; right: 5%;
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(229,9,20,0.35), transparent);
}
.contact-hero h1 {
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 800;
    margin-bottom: 0.6rem;
    position: relative;
}
.contact-hero h1 span { color: var(--primary-color); }
.contact-hero p {
    color: var(--text-muted);
    font-size: 1.05rem;
    max-width: 520px;
    margin: 0 auto;
    position: relative;
}

/* ── Main two-column layout ── */
.contact-wrap {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 2.5rem;
    padding: 3.5rem 5%;
    max-width: 1200px;
    margin: 0 auto;
}
@media(max-width: 860px) {
    .contact-wrap { grid-template-columns: 1fr; }
}

/* ── Left: info cards ── */
.contact-info { display: flex; flex-direction: column; gap: 1.2rem; }

.contact-info-head {
    margin-bottom: 0.5rem;
}
.contact-info-head h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
}
.contact-info-head p {
    color: var(--text-muted);
    font-size: 0.92rem;
    line-height: 1.6;
}

.info-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 1.3rem 1.4rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    transition: border-color 0.3s, transform 0.3s;
    animation: slideInLeft 0.5s ease both;
}
.info-card:hover {
    border-color: rgba(229,9,20,0.35);
    transform: translateX(4px);
}
.info-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.icon-red   { background: rgba(229,9,20,0.15);  color: var(--primary-color); border: 1px solid rgba(229,9,20,0.25); }
.icon-blue  { background: rgba(59,130,246,0.15); color: #60a5fa;             border: 1px solid rgba(59,130,246,0.25); }
.icon-green { background: rgba(34,197,94,0.15);  color: #4ade80;             border: 1px solid rgba(34,197,94,0.25); }
.icon-amber { background: rgba(245,158,11,0.15); color: #fbbf24;             border: 1px solid rgba(245,158,11,0.25); }

.info-card-body h4 {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    margin-bottom: 0.3rem;
}
.info-card-body p, .info-card-body a {
    font-size: 0.95rem;
    color: var(--text-color);
    font-weight: 500;
    text-decoration: none;
    display: block;
    line-height: 1.5;
}
.info-card-body a:hover { color: var(--primary-color); }

/* Social icons */
.social-row {
    display: flex;
    gap: 0.7rem;
    margin-top: 0.4rem;
}
.social-btn {
    width: 36px; height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    text-decoration: none;
    transition: transform 0.2s, opacity 0.2s;
    border: 1px solid rgba(255,255,255,0.1);
    color: #fff;
    background: rgba(255,255,255,0.06);
}
.social-btn:hover { transform: translateY(-3px); opacity: 0.85; }
.social-fb   { background: rgba(24,119,242,0.2);  border-color: rgba(24,119,242,0.3);  color: #4267B2; }
.social-tw   { background: rgba(29,161,242,0.15); border-color: rgba(29,161,242,0.3);  color: #1DA1F2; }
.social-ig   { background: rgba(225,48,108,0.15); border-color: rgba(225,48,108,0.3);  color: #E1306C; }
.social-yt   { background: rgba(255,0,0,0.15);    border-color: rgba(255,0,0,0.25);    color: #FF0000; }

/* Hours table */
.hours-table { width: 100%; font-size: 0.88rem; border-collapse: collapse; margin-top: 0.3rem; }
.hours-table tr td { padding: 0.2rem 0; color: var(--text-muted); }
.hours-table tr td:last-child { text-align: right; color: var(--text-color); font-weight: 500; }
.hours-table tr.today td { color: #4ade80; font-weight: 700; }

/* ── Right: Contact Form ── */
.contact-form-box {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 18px;
    padding: 2.2rem 2rem;
    animation: slideInRight 0.5s ease both;
}
.contact-form-box h2 {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 0.4rem;
}
.contact-form-box p.form-sub {
    color: var(--text-muted);
    font-size: 0.88rem;
    margin-bottom: 1.8rem;
}

/* Alert boxes */
.cf-alert {
    padding: 0.9rem 1.2rem;
    border-radius: 10px;
    margin-bottom: 1.4rem;
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
    font-size: 0.9rem;
}
.cf-alert i { margin-top: 1px; flex-shrink: 0; }
.cf-success { background: rgba(34,197,94,0.1);  border: 1px solid rgba(34,197,94,0.3);  color: #4ade80; }
.cf-error   { background: rgba(229,9,20,0.1);   border: 1px solid rgba(229,9,20,0.3);   color: #f87171; }
.cf-error ul { padding-left: 1rem; margin-top: 0.3rem; }
.cf-error li { list-style: disc; }

/* Form fields */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media(max-width:560px) { .form-row { grid-template-columns: 1fr; } }

.cf-group { margin-bottom: 1.1rem; }
.cf-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.45rem;
}
.cf-group label .req { color: var(--primary-color); margin-left: 2px; }

.cf-input, .cf-select, .cf-textarea {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 0.78rem 1rem;
    color: #fff;
    font-size: 0.92rem;
    font-family: 'Inter', sans-serif;
    outline: none;
    transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
}
.cf-input:focus, .cf-select:focus, .cf-textarea:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(229,9,20,0.15);
    background: rgba(255,255,255,0.07);
}
.cf-input::placeholder, .cf-textarea::placeholder { color: #555; }

.cf-select { appearance: none; cursor: pointer; }
.cf-select option { background: #1a1a1a; }

.cf-textarea { resize: vertical; min-height: 130px; line-height: 1.6; }

/* Character counter */
.char-count {
    font-size: 0.73rem;
    color: var(--text-muted);
    text-align: right;
    margin-top: 0.3rem;
}
.char-count.warn { color: #fbbf24; }
.char-count.over { color: #f87171; }

/* Submit btn */
.cf-submit {
    width: 100%;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 0.95rem;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    transition: background 0.25s, transform 0.2s, box-shadow 0.25s;
    margin-top: 0.5rem;
}
.cf-submit:hover {
    background: #c10710;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(229,9,20,0.35);
}
.cf-submit:active { transform: translateY(0); }

/* ── FAQ Strip ── */
.faq-strip {
    padding: 0 5% 1rem;
    max-width: 1200px;
    margin: 0 auto;
}
.faq-strip h2 {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.faq-strip h2 i { color: var(--primary-color); }

.faq-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}
.faq-card {
    background: var(--card-bg);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 12px;
    padding: 1.2rem 1.3rem;
    transition: border-color 0.3s;
}
.faq-card:hover { border-color: rgba(229,9,20,0.3); }
.faq-card h4 {
    font-size: 0.92rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}
.faq-card h4 i { color: var(--primary-color); margin-top: 2px; flex-shrink: 0; }
.faq-card p { font-size: 0.84rem; color: var(--text-muted); line-height: 1.6; }

/* Animations */
@keyframes slideInLeft  { from { opacity:0; transform:translateX(-20px); } to { opacity:1; transform:translateX(0); } }
@keyframes slideInRight { from { opacity:0; transform:translateX(20px);  } to { opacity:1; transform:translateX(0); } }
</style>

<div class="contact-page">

    <!-- ── Hero ── -->
    <div class="contact-hero fade-in">
        <h1>Get in <span>Touch</span></h1>
        <p>Have a question about bookings, shows, or theatres? We're here to help you 24/7.</p>
    </div>

    <!-- ── Main: Info + Form ── -->
    <div class="contact-wrap">

        <!-- LEFT: Contact Info Cards -->
        <div class="contact-info">
            <div class="contact-info-head">
                <h2>Contact Information</h2>
                <p>Reach us through any of the channels below or fill out the form and we'll get back to you within 24 hours.</p>
            </div>

            <div class="info-card" style="animation-delay:0.1s;">
                <div class="info-icon icon-red"><i class="fas fa-envelope"></i></div>
                <div class="info-card-body">
                    <h4>Email</h4>
                    <a href="mailto:support@cineticket.com">support@cineticket.com</a>
                    <a href="mailto:admin@cineticket.com" style="color:var(--text-muted);font-size:0.85rem;">admin@cineticket.com</a>
                </div>
            </div>

            <div class="info-card" style="animation-delay:0.18s;">
                <div class="info-icon icon-blue"><i class="fas fa-phone-alt"></i></div>
                <div class="info-card-body">
                    <h4>Phone</h4>
                    <a href="tel:+918001234567">+91 800 123 4567</a>
                    <a href="tel:+918009876543" style="color:var(--text-muted);font-size:0.85rem;">+91 800 987 6543 (Bookings)</a>
                </div>
            </div>

            <div class="info-card" style="animation-delay:0.26s;">
                <div class="info-icon icon-green"><i class="fas fa-map-marker-alt"></i></div>
                <div class="info-card-body">
                    <h4>Head Office</h4>
                    <p>12th Floor, Cinema Tower,<br>MG Road, Bangalore – 560001<br>Karnataka, India</p>
                </div>
            </div>

            <div class="info-card" style="animation-delay:0.34s;">
                <div class="info-icon icon-amber"><i class="fas fa-clock"></i></div>
                <div class="info-card-body">
                    <h4>Support Hours</h4>
                    <table class="hours-table">
                        <tr class="<?php echo in_array(date('N'), [1,2,3,4,5]) ? 'today' : ''; ?>">
                            <td>Mon – Fri</td><td>9:00 AM – 10:00 PM</td>
                        </tr>
                        <tr class="<?php echo date('N') == 6 ? 'today' : ''; ?>">
                            <td>Saturday</td><td>10:00 AM – 8:00 PM</td>
                        </tr>
                        <tr class="<?php echo date('N') == 7 ? 'today' : ''; ?>">
                            <td>Sunday</td><td>11:00 AM – 6:00 PM</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Social -->
            <div class="info-card" style="animation-delay:0.42s;">
                <div class="info-icon icon-red"><i class="fas fa-share-alt"></i></div>
                <div class="info-card-body">
                    <h4>Follow Us</h4>
                    <div class="social-row">
                        <a href="#" class="social-btn social-fb"  title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-btn social-tw"  title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-btn social-ig"  title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-btn social-yt"  title="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Contact Form -->
        <div class="contact-form-box">
            <h2>Send Us a Message</h2>
            <p class="form-sub">We typically respond within 1–2 business hours.</p>

            <?php if ($success): ?>
            <div class="cf-alert cf-success">
                <i class="fas fa-check-circle fa-lg"></i>
                <div>
                    <strong>Message sent!</strong><br>
                    Thank you for reaching out. Our team will get back to you at <strong><?php echo htmlspecialchars($_POST['email']); ?></strong> shortly.
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="cf-alert cf-error">
                <i class="fas fa-exclamation-circle fa-lg"></i>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul>
                        <?php foreach($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" id="contactForm" novalidate>
                <div class="form-row">
                    <div class="cf-group">
                        <label for="cf_name">Full Name <span class="req">*</span></label>
                        <input
                            type="text"
                            id="cf_name"
                            name="name"
                            class="cf-input"
                            placeholder="John Doe"
                            value="<?php echo htmlspecialchars($_POST['name'] ?? (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '')); ?>"
                            required
                        >
                    </div>
                    <div class="cf-group">
                        <label for="cf_email">Email Address <span class="req">*</span></label>
                        <input
                            type="email"
                            id="cf_email"
                            name="email"
                            class="cf-input"
                            placeholder="you@example.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="cf-group">
                    <label for="cf_subject">Subject <span class="req">*</span></label>
                    <select id="cf_subject" name="subject" class="cf-select" required>
                        <option value="" disabled <?php echo empty($_POST['subject']) ? 'selected' : ''; ?>>Select a topic…</option>
                        <option value="Booking Issue"            <?php echo (($_POST['subject'] ?? '') === 'Booking Issue') ? 'selected' : ''; ?>>🎟 Booking Issue</option>
                        <option value="Payment Problem"          <?php echo (($_POST['subject'] ?? '') === 'Payment Problem') ? 'selected' : ''; ?>>💳 Payment Problem</option>
                        <option value="Refund Request"           <?php echo (($_POST['subject'] ?? '') === 'Refund Request') ? 'selected' : ''; ?>>💰 Refund Request</option>
                        <option value="Theatre Enquiry"          <?php echo (($_POST['subject'] ?? '') === 'Theatre Enquiry') ? 'selected' : ''; ?>>🏛 Theatre Enquiry</option>
                        <option value="Account / Login Issue"    <?php echo (($_POST['subject'] ?? '') === 'Account / Login Issue') ? 'selected' : ''; ?>>🔑 Account / Login Issue</option>
                        <option value="Feedback & Suggestions"   <?php echo (($_POST['subject'] ?? '') === 'Feedback & Suggestions') ? 'selected' : ''; ?>>💡 Feedback &amp; Suggestions</option>
                        <option value="Other"                    <?php echo (($_POST['subject'] ?? '') === 'Other') ? 'selected' : ''; ?>>📌 Other</option>
                    </select>
                </div>

                <div class="cf-group">
                    <label for="cf_message">Your Message <span class="req">*</span></label>
                    <textarea
                        id="cf_message"
                        name="message"
                        class="cf-textarea"
                        placeholder="Describe your issue or question in detail…"
                        maxlength="1000"
                        required
                    ><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    <div class="char-count" id="charCount">0 / 1000</div>
                </div>

                <button type="submit" name="submit_contact" class="cf-submit">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
            <?php else: ?>
            <div style="text-align:center; padding: 1.5rem 0;">
                <a href="contact.php" style="display:inline-flex;align-items:center;gap:0.5rem;color:var(--primary-color);font-weight:600;border:1px solid rgba(229,9,20,0.4);padding:0.7rem 1.5rem;border-radius:10px;text-decoration:none;transition:background 0.25s;" onmouseover="this.style.background='rgba(229,9,20,0.1)'" onmouseout="this.style.background='transparent'">
                    <i class="fas fa-redo"></i> Send Another Message
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── FAQ Strip ── -->
    <div class="faq-strip fade-in" style="animation-delay:0.4s;">
        <h2><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>
        <div class="faq-grid">
            <div class="faq-card">
                <h4><i class="fas fa-ticket-alt"></i> How do I cancel a booking?</h4>
                <p>Log in to your Dashboard, find the booking under "Upcoming" shows, and use the cancel option. Refunds are processed within 5–7 business days.</p>
            </div>
            <div class="faq-card">
                <h4><i class="fas fa-undo"></i> What is the refund policy?</h4>
                <p>Cancellations made more than 2 hours before the show time are eligible for a full refund. Last-minute cancellations may incur a convenience fee.</p>
            </div>
            <div class="faq-card">
                <h4><i class="fas fa-credit-card"></i> Which payment methods are accepted?</h4>
                <p>We accept UPI, Debit/Credit Cards, Net Banking, and popular digital wallets. All transactions are secured with SSL encryption.</p>
            </div>
            <div class="faq-card">
                <h4><i class="fas fa-user-edit"></i> How do I update my account details?</h4>
                <p>Go to your Dashboard after logging in. Account settings and profile edits are available there. For email changes, please contact support.</p>
            </div>
            <div class="faq-card">
                <h4><i class="fas fa-map-marker-alt"></i> How do I find theatres near me?</h4>
                <p>Visit the <a href="theatres.php" style="color:var(--primary-color);font-weight:600;">Theatres</a> page and filter by your city to see all cinemas in your area along with current shows.</p>
            </div>
            <div class="faq-card">
                <h4><i class="fas fa-print"></i> How do I get my ticket?</h4>
                <p>After a successful booking, your e-ticket is available in Dashboard → My Bookings. You can print it or show it on your phone at the entrance.</p>
            </div>
        </div>
    </div>

</div>

<script>
// Live character counter for textarea
const msgArea   = document.getElementById('cf_message');
const charCount = document.getElementById('charCount');
if (msgArea && charCount) {
    function updateCount() {
        const len = msgArea.value.length;
        charCount.textContent = len + ' / 1000';
        charCount.className = 'char-count' + (len > 900 ? ' over' : len > 700 ? ' warn' : '');
    }
    msgArea.addEventListener('input', updateCount);
    updateCount();
}
</script>

<?php require_once 'includes/footer.php'; ?>
