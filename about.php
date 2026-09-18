<?php
/**
 * About Page
 * Elegance Salon
 */
$pageTitle = "About Us - Elegance Salon";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="page-header-bg" style="background-image: url('https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&q=80');"></div>
    <div class="container page-header-content">
        <div class="page-header-icon"><i class="fas fa-spa"></i></div>
        <h1>About Us</h1>
        <p class="page-header-desc">Dedicated to enhancing your natural beauty with artistry, expertise, and an unwavering commitment to excellence.</p>
        <div class="breadcrumb">
            <a href="<?php echo SITE_URL; ?>/index.php">Home</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">About Us</span>
        </div>
    </div>
</section>

<!-- About Content -->
<section class="section-padding">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 animate-on-scroll">
                <div class="about-image-wrapper">
                    <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80" alt="Elegance Salon Interior" loading="lazy">
                    <div class="about-experience-badge">
                        <span class="number">8+</span>
                        <span class="text">Years of Excellence</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 animate-on-scroll delay-2">
                <span class="section-subtitle">Our Story</span>
                <h2 class="section-title">Beauty, Crafted With<br>Elegance.</h2>
                <p class="section-desc" style="margin-bottom: 20px;">
                    Founded in 2018, Elegance Salon was born from a passion for beauty and a commitment to excellence. What started as a small studio has grown into one of the city's most sought-after beauty destinations.
                </p>
                <p class="section-desc" style="margin-bottom: 20px;">
                    Our philosophy is simple: every client deserves a personalized experience that enhances their natural beauty. We invest in continuous training, premium products, and a luxurious environment to ensure every visit exceeds expectations.
                </p>
                <p class="section-desc">
                    From the moment you step through our doors, you'll feel the difference. Our warm, inviting atmosphere combined with our team's expertise creates an experience that keeps clients coming back.
                </p>
                <div class="about-features">
                    <div class="about-feature-item">
                        <i class="fas fa-check"></i>
                        <span>Premium Products</span>
                    </div>
                    <div class="about-feature-item">
                        <i class="fas fa-check"></i>
                        <span>Expert Stylists</span>
                    </div>
                    <div class="about-feature-item">
                        <i class="fas fa-check"></i>
                        <span>Luxury Ambiance</span>
                    </div>
                    <div class="about-feature-item">
                        <i class="fas fa-check"></i>
                        <span>Custom Solutions</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="why-section section-padding">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">Why Choose Us</span>
            <h2 class="section-title">Simple. Elegant. Exceptional.</h2>
        </div>
        <div class="row g-4">
            <?php
            $whys = [
                ['icon' => 'fa-scissors', 'title' => 'Professional Stylists', 'desc' => 'Certified professionals with years of experience in the latest techniques.'],
                ['icon' => 'fa-flask', 'title' => 'Premium Products', 'desc' => 'We exclusively use top-tier, salon-grade products for the best results.'],
                ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Modern Techniques', 'desc' => 'Cutting-edge styling methods and innovative beauty treatments.'],
                ['icon' => 'fa-heart', 'title' => 'Personalized Experience', 'desc' => 'Every service begins with a consultation to understand your vision.'],
            ];
            foreach ($whys as $index => $why):
            ?>
            <div class="col-lg-3 col-md-6 animate-on-scroll delay-<?php echo $index + 1; ?>">
                <div class="why-card">
                    <div class="why-card-icon"><i class="fas <?php echo $why['icon']; ?>"></i></div>
                    <h4><?php echo $why['title']; ?></h4>
                    <p><?php echo $why['desc']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="cta-bg" style="background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1920&q=80');"></div>
    <div class="container cta-content animate-on-scroll">
        <h2 class="section-title">Ready For Your Next Look?</h2>
        <p>Experience the Elegance difference today.</p>
        <div class="cta-buttons">
            <a href="<?php echo SITE_URL; ?>/book-appointment.php" class="btn-accent"><i class="fas fa-calendar-check"></i> Book An Appointment</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
