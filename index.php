<?php
/**
 * Homepage
 * Elegance Salon
 */
$pageTitle = "Elegance Salon - Premium Beauty & Styling";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- ==================== HERO SECTION ==================== -->
<section class="hero-section" id="home">
    <div class="hero-bg" style="background-image: url('https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&q=80');"></div>
    <div class="hero-accent-line"></div>

    <div class="container hero-content">
        <div class="row align-items-center">
            <!-- Left: Text Content -->
            <div class="col-lg-6 hero-text-col">
                <div class="hero-badge">
                    <i class="fas fa-award"></i>
                    <span>Award-Winning Salon Experience</span>
                </div>

                <h1 class="hero-title">
                    YOUR STYLE<br>
                    DESERVES<br>
                    <span class="line-accent">EXTRAORDINARY</span>
                </h1>

                <p class="hero-desc">
                    From precision cuts to stunning colour transformations, Elegance Salon is where artistry meets luxury. We craft every look to reflect the unique you.
                </p>

                <div class="hero-buttons">
                    <a href="<?php echo SITE_URL; ?>/book-appointment.php" class="btn-accent">
                        <i class="fas fa-calendar-check"></i> Book Appointment
                    </a>
                    <a href="<?php echo SITE_URL; ?>/services.php" class="btn-white">
                        <i class="fas fa-sparkles"></i> Explore Services
                    </a>
                </div>

                <div class="hero-trust-bar">
                    <div class="hero-trust-item">
                        <div class="hero-trust-number">8+</div>
                        <div class="hero-trust-label">Years</div>
                    </div>
                    <div class="hero-trust-divider"></div>
                    <div class="hero-trust-item">
                        <div class="hero-trust-number">5K+</div>
                        <div class="hero-trust-label">Happy Clients</div>
                    </div>
                    <div class="hero-trust-divider"></div>
                    <div class="hero-trust-item">
                        <div class="hero-trust-number"><i class="fas fa-star"></i> 4.9</div>
                        <div class="hero-trust-label">Client Rating</div>
                    </div>
                </div>
            </div>

            <!-- Right: Decorative Image -->
            <div class="col-lg-6 hero-visual-col d-none d-lg-flex">
                <div class="hero-visual">
                    <div class="hero-visual-ring"></div>
                    <div class="hero-visual-img">
                        <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=700&q=80" alt="Premium Styling at Elegance Salon" loading="eager">
                    </div>
                    <div class="hero-floating-card hero-float-1">
                        <i class="fas fa-scissors"></i>
                        <span>Precision Styling</span>
                    </div>
                    <div class="hero-floating-card hero-float-2">
                        <i class="fas fa-leaf"></i>
                        <span>100% Premium Products</span>
                    </div>
                    <div class="hero-floating-card hero-float-3">
                        <div class="hero-float-stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <span>Top Rated Salon</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll Down Indicator -->
    <div class="hero-scroll-indicator">
        <div class="hero-scroll-mouse">
            <div class="hero-scroll-wheel"></div>
        </div>
        <span>Scroll Down</span>
    </div>
</section>

<!-- ==================== ABOUT SECTION ==================== -->
<section class="about-section section-padding" id="about">
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
                <span class="section-subtitle">About Us</span>
                <h2 class="section-title">Beauty, Crafted With<br>Elegance.</h2>
                <p class="section-desc" style="margin-bottom: 20px;">
                    At Elegance Salon, we believe beauty is an art form. Our team of skilled professionals combines time-honored techniques with modern innovation to create looks that define sophistication.
                </p>
                <p class="section-desc">
                    From a simple trim to a complete transformation, every visit is a personalized experience designed around you. We use only premium products and maintain the highest standards of hygiene and comfort.
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
                <div style="margin-top: 36px;">
                    <a href="<?php echo SITE_URL; ?>/about.php" class="btn-accent">
                        Learn More <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== SERVICES SECTION ==================== -->
<section class="services-section section-padding" id="services">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">What We Offer</span>
            <h2 class="section-title">Our Premium Services</h2>
            <p class="section-desc mx-auto text-center">Discover our range of expert beauty services, each designed to enhance your natural radiance.</p>
        </div>

        <div class="row g-4">
            <?php
            $services = [
                [
                    'name' => 'Hair Styling',
                    'desc' => 'Professional hair styling including cuts, blow-dry, and updos tailored to your unique look.',
                    'price' => 45,
                    'duration' => '45 min',
                    'image' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=600&q=80',
                    'features' => ['Consultation', 'Shampoo & Cut', 'Blow Dry', 'Styling Tips'],
                    'popular' => true,
                ],
                [
                    'name' => 'Hair Coloring',
                    'desc' => 'Expert coloring services including highlights, balayage, and full color transformations.',
                    'price' => 85,
                    'duration' => '90 min',
                    'image' => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=600&q=80',
                    'features' => ['Color Consultation', 'Premium Dyes', 'Root Touch-up', 'Aftercare'],
                    'popular' => false,
                ],
                [
                    'name' => 'Facial Treatment',
                    'desc' => 'Rejuvenating facial treatments customized for your skin type for a radiant, youthful glow.',
                    'price' => 55,
                    'duration' => '60 min',
                    'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=600&q=80',
                    'features' => ['Skin Analysis', 'Deep Cleansing', 'Hydration Mask', 'Moisturizer'],
                    'popular' => false,
                ],
                [
                    'name' => 'Manicure',
                    'desc' => 'Luxurious manicure services including shaping, cuticle care, and premium polish application.',
                    'price' => 30,
                    'duration' => '40 min',
                    'image' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80',
                    'features' => ['Nail Shaping', 'Cuticle Care', 'Hand Massage', 'Premium Polish'],
                    'popular' => false,
                ],
                [
                    'name' => 'Pedicure',
                    'desc' => 'Relaxing pedicure with exfoliation, massage, and a flawless finishing touch.',
                    'price' => 35,
                    'duration' => '45 min',
                    'image' => 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?w=600&q=80',
                    'features' => ['Foot Soak', 'Exfoliation', 'Foot Massage', 'Nail Polish'],
                    'popular' => false,
                ],
                [
                    'name' => 'Hair Treatment',
                    'desc' => 'Deep conditioning, keratin, and restorative treatments for strong, healthy, silky hair.',
                    'price' => 65,
                    'duration' => '60 min',
                    'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&q=80',
                    'features' => ['Hair Analysis', 'Deep Conditioning', 'Keratin Treatment', 'Steam Therapy'],
                    'popular' => true,
                ],
            ];

            foreach ($services as $index => $service):
            ?>
            <div class="col-lg-4 col-md-6 animate-on-scroll delay-<?php echo ($index % 3) + 1; ?>">
                <div class="service-card">
                    <div class="service-card-image">
                        <img src="<?php echo $service['image']; ?>" alt="<?php echo sanitize($service['name']); ?>" loading="lazy">
                        <?php if ($service['popular']): ?>
                        <span class="service-badge">Popular</span>
                        <?php endif; ?>
                    </div>
                    <div class="service-card-body">
                        <h4><?php echo sanitize($service['name']); ?></h4>
                        <p class="service-desc"><?php echo sanitize($service['desc']); ?></p>
                        <div class="service-price">
                            Starting at $<?php echo $service['price']; ?>
                            <span>/ <?php echo $service['duration']; ?></span>
                        </div>
                        <ul class="service-features">
                            <?php foreach ($service['features'] as $feature): ?>
                            <li><i class="fas fa-check-circle"></i> <?php echo sanitize($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?php echo SITE_URL; ?>/book-appointment.php" class="btn-accent">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5 animate-on-scroll">
            <a href="<?php echo SITE_URL; ?>/services.php" class="btn-outline-accent">
                View All Services <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ==================== WHY CHOOSE US ==================== -->
<section class="why-section section-padding" id="why">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">Why Elegance</span>
            <h2 class="section-title">Simple. Elegant. Exceptional.</h2>
            <p class="section-desc mx-auto text-center">Every detail matters when it comes to your beauty experience.</p>
        </div>

        <div class="row g-4">
            <?php
            $whys = [
                ['icon' => 'fa-scissors', 'title' => 'Professional Stylists', 'desc' => 'Our team comprises certified professionals with years of experience in the latest hair and beauty techniques.'],
                ['icon' => 'fa-flask', 'title' => 'Premium Products', 'desc' => 'We exclusively use top-tier, salon-grade products that deliver results while caring for your hair and skin.'],
                ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Modern Techniques', 'desc' => 'Stay ahead with cutting-edge styling methods and innovative beauty treatments updated regularly.'],
                ['icon' => 'fa-heart', 'title' => 'Personalized Experience', 'desc' => 'Every service begins with a thorough consultation to understand your vision and exceed your expectations.'],
            ];

            foreach ($whys as $index => $why):
            ?>
            <div class="col-lg-3 col-md-6 animate-on-scroll delay-<?php echo $index + 1; ?>">
                <div class="why-card">
                    <div class="why-card-icon">
                        <i class="fas <?php echo $why['icon']; ?>"></i>
                    </div>
                    <h4><?php echo $why['title']; ?></h4>
                    <p><?php echo $why['desc']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== IMAGE SHOWCASE ==================== -->
<section class="showcase-section section-padding" id="showcase">
    <div class="container">
        <div class="row align-items-center mb-5">
            <div class="col-lg-6 animate-on-scroll">
                <span class="section-subtitle">Our Work</span>
                <h2 class="section-title">See It For Yourself.</h2>
            </div>
            <div class="col-lg-6 text-lg-end animate-on-scroll delay-2">
                <a href="<?php echo SITE_URL; ?>/gallery.php" class="btn-outline-accent mt-3 mt-lg-0">
                    Explore Our Work <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="showcase-grid animate-on-scroll">
            <div class="showcase-item">
                <img src="https://images.unsplash.com/photo-1560066984-138dadb4c035?w=800&q=80" alt="Salon Interior" loading="lazy">
            </div>
            <div class="showcase-item">
                <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&q=80" alt="Hair Styling" loading="lazy">
            </div>
            <div class="showcase-item">
                <img src="https://images.unsplash.com/photo-1562322140-8baeececf3df?w=600&q=80" alt="Hair Coloring" loading="lazy">
            </div>
            <div class="showcase-item">
                <img src="https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=600&q=80" alt="Facial Treatment" loading="lazy">
            </div>
            <div class="showcase-item">
                <img src="https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80" alt="Manicure" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- ==================== STATISTICS ==================== -->
<section class="stats-section animate-on-scroll">
    <div class="container">
        <div class="row">
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="5000" data-suffix="+">0</div>
                    <div class="stat-label">Happy Clients</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="99" data-suffix="%">0</div>
                    <div class="stat-label">Client Satisfaction</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="2018" data-suffix="">0</div>
                    <div class="stat-label">Established</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="12" data-suffix="+">0</div>
                    <div class="stat-label">Professional Stylists</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== TEAM PREVIEW ==================== -->
<section class="team-section section-padding" id="team">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">Our Experts</span>
            <h2 class="section-title">Meet Our Team</h2>
            <p class="section-desc mx-auto text-center">Skilled professionals dedicated to making you look and feel your absolute best.</p>
        </div>

        <div class="row g-4">
            <?php
            $team = [
                ['name' => 'Sophia Laurent', 'role' => 'Senior Hair Stylist', 'specialty' => 'Precision cuts & creative styling', 'image' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=500&q=80'],
                ['name' => 'Isabella Chen', 'role' => 'Beauty Specialist', 'specialty' => 'Skincare & facial treatments', 'image' => 'https://images.unsplash.com/photo-1594744803329-e58b31de8bf5?w=500&q=80'],
                ['name' => 'Marcus Rivera', 'role' => 'Hair Color Expert', 'specialty' => 'Balayage & color transformations', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=500&q=80'],
                ['name' => 'Ava Thompson', 'role' => 'Skin Care Specialist', 'specialty' => 'Anti-aging & rejuvenation', 'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=500&q=80'],
            ];

            foreach ($team as $index => $member):
            ?>
            <div class="col-lg-3 col-md-6 animate-on-scroll delay-<?php echo $index + 1; ?>">
                <div class="team-card">
                    <div class="team-card-image">
                        <img src="<?php echo $member['image']; ?>" alt="<?php echo sanitize($member['name']); ?>" loading="lazy">
                    </div>
                    <div class="team-card-body">
                        <h4><?php echo sanitize($member['name']); ?></h4>
                        <div class="team-role"><?php echo sanitize($member['role']); ?></div>
                        <p class="team-specialty"><?php echo sanitize($member['specialty']); ?></p>
                        <div class="team-social">
                            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5 animate-on-scroll">
            <a href="<?php echo SITE_URL; ?>/team.php" class="btn-outline-accent">
                Meet Our Full Team <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ==================== GALLERY PREVIEW ==================== -->
<section class="gallery-section section-padding" id="gallery">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">Our Portfolio</span>
            <h2 class="section-title">Gallery</h2>
            <p class="section-desc mx-auto text-center">Browse through our collection of transformations and styling work.</p>
        </div>

        <div class="gallery-filter animate-on-scroll">
            <button class="gallery-filter-btn active" data-filter="all">All</button>
            <button class="gallery-filter-btn" data-filter="hair">Hair</button>
            <button class="gallery-filter-btn" data-filter="makeup">Makeup</button>
            <button class="gallery-filter-btn" data-filter="facial">Facial</button>
            <button class="gallery-filter-btn" data-filter="nails">Nails</button>
            <button class="gallery-filter-btn" data-filter="salon">Salon</button>
            <button class="gallery-filter-btn" data-filter="bridal">Bridal</button>
        </div>

        <div class="gallery-grid">
            <?php
            $galleryImages = [
                ['title' => 'Elegant Updo', 'category' => 'hair', 'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&q=80'],
                ['title' => 'Balayage Perfection', 'category' => 'hair', 'image' => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=600&q=80'],
                ['title' => 'Bridal Glamour', 'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1519741497674-611481863552?w=600&q=80'],
                ['title' => 'Classic Blowout', 'category' => 'hair', 'image' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=600&q=80'],
                ['title' => 'Glow Facial', 'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=600&q=80'],
                ['title' => 'French Manicure', 'category' => 'nails', 'image' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
                ['title' => 'Evening Makeup', 'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=600&q=80'],
                ['title' => 'Salon Interior', 'category' => 'salon', 'image' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=600&q=80'],
                ['title' => 'Bridal Styling', 'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?w=600&q=80'],
                ['title' => 'Gel Nails Art', 'category' => 'nails', 'image' => 'https://images.unsplash.com/photo-1607779097040-26e80aa78e66?w=600&q=80'],
                ['title' => 'Deep Cleansing', 'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?w=600&q=80'],
                ['title' => 'Luxury Interior', 'category' => 'salon', 'image' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=600&q=80'],
            ];

            foreach ($galleryImages as $img):
            ?>
            <div class="gallery-item gallery-item-visible" data-category="<?php echo $img['category']; ?>" data-title="<?php echo sanitize($img['title']); ?>">
                <img src="<?php echo $img['image']; ?>" alt="<?php echo sanitize($img['title']); ?>" loading="lazy">
                <div class="gallery-item-overlay">
                    <div class="gallery-zoom"><i class="fas fa-expand"></i></div>
                    <div class="gallery-title"><?php echo sanitize($img['title']); ?></div>
                    <div class="gallery-category"><?php echo ucfirst($img['category']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5 animate-on-scroll">
            <a href="<?php echo SITE_URL; ?>/gallery.php" class="btn-outline-accent">
                View Full Gallery <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ==================== CTA SECTION ==================== -->
<section class="cta-section" id="cta">
    <div class="cta-bg" style="background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1920&q=80');"></div>
    <div class="container cta-content animate-on-scroll">
        <span class="section-subtitle">Ready For A Change?</span>
        <h2 class="section-title">Ready For Your<br>Next Look?</h2>
        <p>Book your appointment today and let our expert stylists create a look that's uniquely you.</p>
        <div class="cta-buttons">
            <a href="<?php echo SITE_URL; ?>/book-appointment.php" class="btn-accent">
                <i class="fas fa-calendar-check"></i> Book An Appointment
            </a>
            <a href="<?php echo SITE_URL; ?>/services.php" class="btn-white">
                <i class="fas fa-eye"></i> View Services
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
