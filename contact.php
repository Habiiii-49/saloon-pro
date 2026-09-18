<?php
/**
 * Contact Page
 * Elegance Salon
 */
$pageTitle = "Contact Us - Elegance Salon";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $success = "Thank you for your message! We will get back to you shortly.";
    }
}
?>

<!-- Page Header -->
<section class="page-header">
    <div class="page-header-bg" style="background-image: url('https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&q=80');"></div>
    <div class="container page-header-content">
        <div class="page-header-icon"><i class="fas fa-envelope-open-text"></i></div>
        <h1>Contact Us</h1>
        <p class="page-header-desc">We'd love to hear from you. Reach out for bookings, questions, or anything we can help you with.</p>
        <div class="breadcrumb">
            <a href="<?php echo SITE_URL; ?>/index.php">Home</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">Contact Us</span>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Info -->
            <div class="col-lg-5 animate-on-scroll">
                <span class="section-subtitle">Get In Touch</span>
                <h2 class="section-title">We'd Love To<br>Hear From You</h2>
                <p class="section-desc" style="margin-bottom: 30px;">
                    Have a question or want to book an appointment? Reach out to us and we'll get back to you as soon as possible.
                </p>

                <ul class="footer-contact" style="margin-bottom: 30px;">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo sanitize(getSetting('salon_address', '123 Luxury Avenue, Beauty District, NY 10001')); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-phone-alt"></i>
                        <a href="tel:<?php echo sanitize(getSetting('salon_phone', '+1 (555) 123-4567')); ?>"><?php echo sanitize(getSetting('salon_phone', '+1 (555) 123-4567')); ?></a>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:<?php echo sanitize(getSetting('salon_email', 'info@elegancesalon.com')); ?>"><?php echo sanitize(getSetting('salon_email', 'info@elegancesalon.com')); ?></a>
                    </li>
                    <li>
                        <i class="fas fa-clock"></i>
                        <span><?php echo sanitize(getSetting('opening_hours', 'Mon-Sat: 9:00 AM - 8:00 PM')); ?></span>
                    </li>
                </ul>

                <div class="footer-social">
                    <a href="<?php echo sanitize(getSetting('facebook_url', '#')); ?>" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?php echo sanitize(getSetting('instagram_url', '#')); ?>" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/<?php echo sanitize(getSetting('whatsapp_number', '15551234567')); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="col-lg-7 animate-on-scroll delay-2">
                <div style="background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 40px;">
                    <h3 style="font-family: var(--font-heading); margin-bottom: 8px;">Send Us A Message</h3>
                    <p style="color: var(--muted); margin-bottom: 28px; font-size: 0.95rem;">Fill out the form below and we'll respond promptly.</p>

                    <?php if ($success): ?>
                    <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); color: #22c55e; border-radius: var(--radius-sm);">
                        <i class="fas fa-check-circle me-2"></i><?php echo sanitize($success); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; border-radius: var(--radius-sm);">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo sanitize($error); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Full Name *</label>
                                <input type="text" name="name" class="form-control-custom" placeholder="Your name" required value="<?php echo sanitize($name ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Email Address *</label>
                                <input type="email" name="email" class="form-control-custom" placeholder="your@email.com" required value="<?php echo sanitize($email ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label-custom">Subject</label>
                                <input type="text" name="subject" class="form-control-custom" placeholder="How can we help?" value="<?php echo sanitize($subject ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label-custom">Message *</label>
                                <textarea name="message" class="form-control-custom" rows="5" placeholder="Write your message here..." required><?php echo sanitize($message ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-accent">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
