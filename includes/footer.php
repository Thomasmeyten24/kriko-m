<?php
/**
 * Reusable Footer Layout Component
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/db.php';
$settings = read_db('settings');

$contact_email = isset($settings['contact_email']) ? $settings['contact_email'] : 'groepsleiding@kriko-m.be';
$contact_phone = isset($settings['contact_phone']) ? $settings['contact_phone'] : '+32 3 776 00 00';
$contact_address = isset($settings['contact_address']) ? $settings['contact_address'] : 'Industriepark-Noord 33, 9100 Sint-Niklaas';
$scouts_year = isset($settings['scouts_year']) ? $settings['scouts_year'] : '2026-2027';
?>
    <!-- 4. Main Page Footer -->
    <?php if ($current_page !== 'ouderportaal.php' && $current_page !== 'admin.php'): ?>
        <footer class="footer">
            <div class="container footer-grid">
                <!-- Brand Section -->
                <div class="footer-col">
                    <div class="footer-logo">
                        <?php if (file_exists(dirname(__DIR__) . '/assets/images/logo.png')): ?>
                            <img src="assets/images/logo.png" alt="Logo Kriko-M" style="height: 40px; width: auto; border-radius: 50%; border: 1px solid var(--color-accent);">
                        <?php else: ?>
                            <!-- Scouts logo inside footer -->
                            <svg style="width: 35px; height: 35px; fill: var(--color-accent);" viewBox="0 0 24 24">
                                <path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm0 2.18l6 2.25v4.66c0 4.14-2.56 8-6 9.07-3.44-1.07-6-4.93-6-9.07V6.43l6-2.25zM12 7c-1.66 0-3 1.34-3 3 0 2.25 3 5 3 5s3-2.75 3-5c0-1.66-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5S11.17 8.5 12 8.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                            </svg>
                        <?php endif; ?>
                        <h3 style="color: var(--color-bg-white); margin-bottom: 0; font-size: 1.4rem;">Scouts Kriko-M</h3>
                    </div>
                    <p class="footer-desc">
                        Scouts Kriko-M is een levendige scoutsgroep voor jongens uit Sint-Niklaas. Al sinds jaar en dag bezorgen we honderden kinderen elk weekend een fantastisch avontuur.
                    </p>
                </div>

                <!-- Quick Links -->
                <div class="footer-col">
                    <h3>Snelle Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php" class="footer-link">&raquo; Home</a></li>
                        <li><a href="takken.php" class="footer-link">&raquo; Onze Takken</a></li>
                        <li><a href="echos.php" class="footer-link">&raquo; Kriko Echo's</a></li>
                        <li><a href="ouderportaal.php?show_webshop=1" class="footer-link">&raquo; Kledij Webshop</a></li>
                        <li><a href="inschrijven.php" class="footer-link">&raquo; Lid Worden</a></li>
                        <li><a href="contact.php" class="footer-link">&raquo; Contacteer Ons</a></li>
                    </ul>
                </div>

                <!-- Contact Information -->
                <div class="footer-col">
                    <h3>Contact & Lokalen</h3>
                    <div class="footer-contact-item">
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span><?php echo htmlspecialchars($contact_address); ?></span>
                    </div>
                    <div class="footer-contact-item">
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="footer-link"><?php echo htmlspecialchars($contact_email); ?></a>
                    </div>
                    <div class="footer-contact-item">
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <span><?php echo htmlspecialchars($contact_phone); ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom Bar -->
            <div class="container footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Scouts Kriko-M Sint-Niklaas. Alle rechten voorbehouden. Aangesloten bij Scouts en Gidsen Vlaanderen.</p>
                <div class="footer-bottom-links">
                    <a href="login.php">Leiding Portaal</a>
                    <span>&bull;</span>
                    <a href="https://www.hopper.be" target="_blank" rel="noopener noreferrer">Hopper Winkel</a>
                </div>
            </div>
        </footer>
    <?php endif; ?>

    <!-- JavaScripts -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/cart.js"></script>
</body>
</html>
