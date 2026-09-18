<?php
/**
 * Stylist Dashboard
 * Elegance Salon
 * NOTE: Full stylist dashboard will be implemented in Part 3.
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('stylist');

$pageTitle = "Stylist Dashboard - Elegance Salon";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<section class="section-padding" style="padding-top: 140px;">
    <div class="container">
        <div class="text-center animate-on-scroll" style="background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 60px 40px;">
            <div class="why-card-icon" style="margin: 0 auto 24px; width: 90px; height: 90px; font-size: 2.2rem;">
                <i class="fas fa-cut"></i>
            </div>
            <span class="section-subtitle">Stylist Area</span>
            <h2 class="section-title">Welcome, <?php echo sanitize(currentUserName() ?: 'Stylist'); ?></h2>
            <p style="color: var(--muted); max-width: 500px; margin: 0 auto 16px;" class="section-desc">
                Your professional dashboard with schedule management, commission tracking, and client management will be available in Part 3.
            </p>
            <a href="<?php echo SITE_URL; ?>/index.php" class="btn-accent mt-3">
                <i class="fas fa-home"></i> Back to Website
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>