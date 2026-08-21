    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>CineTicket</h3>
                <p>Your ultimate destination for booking movie tickets online with a seamless and modern experience.</p>
                <div style="margin-top: 14px; display:flex; gap:10px;">
                    <a href="#" title="Facebook"  style="width:34px;height:34px;border-radius:8px;background:rgba(24,119,242,0.18);border:1px solid rgba(24,119,242,0.3);color:#4267B2;display:flex;align-items:center;justify-content:center;transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter"   style="width:34px;height:34px;border-radius:8px;background:rgba(29,161,242,0.15);border:1px solid rgba(29,161,242,0.3);color:#1DA1F2;display:flex;align-items:center;justify-content:center;transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="Instagram" style="width:34px;height:34px;border-radius:8px;background:rgba(225,48,108,0.15);border:1px solid rgba(225,48,108,0.3);color:#E1306C;display:flex;align-items:center;justify-content:center;transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="YouTube"   style="width:34px;height:34px;border-radius:8px;background:rgba(255,0,0,0.13);border:1px solid rgba(255,0,0,0.25);color:#FF0000;display:flex;align-items:center;justify-content:center;transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home" style="width:14px;margin-right:6px;color:var(--primary-color);"></i>Home</a></li>
                    <li><a href="index.php#movies"><i class="fas fa-film" style="width:14px;margin-right:6px;color:var(--primary-color);"></i>Now Showing</a></li>
                    <li><a href="theatres.php"><i class="fas fa-building" style="width:14px;margin-right:6px;color:var(--primary-color);"></i>Theatres</a></li>
                    <li><a href="contact.php"><i class="fas fa-envelope" style="width:14px;margin-right:6px;color:var(--primary-color);"></i>Contact Us</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <li><a href="dashboard.php"><i class="fas fa-ticket-alt" style="width:14px;margin-right:6px;color:var(--primary-color);"></i>My Bookings</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Contact Us</h3>
                <p style="margin-bottom:8px;">
                    <i class="fas fa-envelope" style="color:var(--primary-color);width:16px;margin-right:6px;"></i>
                    <a href="mailto:support@cineticket.com" style="color:var(--text-muted);">support@cineticket.com</a>
                </p>
                <p style="margin-bottom:8px;">
                    <i class="fas fa-phone-alt" style="color:var(--primary-color);width:16px;margin-right:6px;"></i>
                    <a href="tel:+918001234567" style="color:var(--text-muted);">+91 800 123 4567</a>
                </p>
                <p style="margin-bottom:14px;">
                    <i class="fas fa-map-marker-alt" style="color:var(--primary-color);width:16px;margin-right:6px;"></i>
                    <span style="color:var(--text-muted);">MG Road, Bangalore – 560001</span>
                </p>
                <a href="contact.php" style="display:inline-flex;align-items:center;gap:6px;background:var(--primary-color);color:white;padding:0.5rem 1.1rem;border-radius:8px;font-size:0.83rem;font-weight:700;text-decoration:none;transition:background .25s;" onmouseover="this.style.background='#c10710'" onmouseout="this.style.background='var(--primary-color)'">
                    <i class="fas fa-paper-plane"></i> Send a Message
                </a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date("Y"); ?> CineTicket. All rights reserved. &nbsp;|&nbsp;
            <a href="contact.php" style="color:var(--primary-color);font-weight:600;">Contact Support</a>
        </div>
    </footer>

    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
