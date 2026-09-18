<?php
/**
 * Footer Include
 * Elegance Salon
 */
$siteName = getSetting('salon_name', 'Elegance Salon');
$siteEmail = getSetting('salon_email', 'info@elegancesalon.com');
$sitePhone = getSetting('salon_phone', '+1 (555) 123-4567');
$siteAddress = getSetting('salon_address', '123 Luxury Avenue, Beauty District, NY 10001');
$openingHours = getSetting('opening_hours', 'Mon-Sat: 9:00 AM - 8:00 PM | Sun: 10:00 AM - 6:00 PM');
$facebookUrl = getSetting('facebook_url', '#');
$instagramUrl = getSetting('instagram_url', '#');
$whatsappNumber = getSetting('whatsapp_number', '15551234567');
$currentPage = getCurrentPage();
?>
<!-- Footer -->
<footer class="site-footer">
    <!-- Footer Top -->
    <div class="footer-top">
        <div class="container">
            <div class="row g-4">
                <!-- Brand Column -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <a href="<?php echo SITE_URL; ?>/index.php" class="footer-logo">
                            <span class="brand-elegance">ELEGANCE</span>
                            <span class="brand-salon">SALON</span>
                        </a>
                        <p class="footer-desc">
                            Experience the art of beauty at Elegance Salon. We blend luxury, precision, and care to deliver an unforgettable styling experience.
                        </p>
                        <div class="footer-social">
                            <a href="<?php echo sanitize($facebookUrl); ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="<?php echo sanitize($instagramUrl); ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="https://wa.me/<?php echo sanitize($whatsappNumber); ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6">
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>/index.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/about.php" class="<?php echo $currentPage === 'about' ? 'active' : ''; ?>">About Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/services.php" class="<?php echo $currentPage === 'services' ? 'active' : ''; ?>">Services</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/gallery.php" class="<?php echo $currentPage === 'gallery' ? 'active' : ''; ?>">Gallery</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/team.php" class="<?php echo $currentPage === 'team' ? 'active' : ''; ?>">Our Team</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/contact.php" class="<?php echo $currentPage === 'contact' ? 'active' : ''; ?>">Contact</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div class="col-lg-3 col-md-6">
                    <h4 class="footer-heading">Our Services</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>/services.php">Hair Styling</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/services.php">Hair Coloring</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/services.php">Facial Treatment</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/services.php">Manicure & Pedicure</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/services.php">Hair Treatment</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/services.php">Bridal Makeup</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="col-lg-3 col-md-6">
                    <h4 class="footer-heading">Contact Info</h4>
                    <ul class="footer-contact">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo sanitize($siteAddress); ?></span>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <a href="tel:<?php echo sanitize($sitePhone); ?>"><?php echo sanitize($sitePhone); ?></a>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo sanitize($siteEmail); ?>"><?php echo sanitize($siteEmail); ?></a>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span><?php echo sanitize($openingHours); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo sanitize($siteName); ?>. All Rights Reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p>Designed with <i class="fas fa-heart text-accent"></i> for premium beauty.</p>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- End Footer -->

<!-- Back to Top -->
<button id="backToTop" class="back-to-top" aria-label="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Main JS -->
<script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
