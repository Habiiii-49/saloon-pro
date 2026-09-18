<?php
/**
 * Services Page – Elegance Salon
 * 5 categories × 6 cards each
 */
$pageTitle = "Our Services - Elegance Salon";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="page-header-bg" style="background-image: url('https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=1920&q=80');"></div>
    <div class="container page-header-content">
        <div class="page-header-icon"><i class="fas fa-scissors"></i></div>
        <h1>Our Services</h1>
        <p class="page-header-desc">Discover our curated collection of premium beauty services designed to make you look and feel extraordinary.</p>
        <div class="breadcrumb">
            <a href="<?php echo SITE_URL; ?>/index.php">Home</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">Services</span>
        </div>
    </div>
</section>

<section class="services-section section-padding" id="services">
    <div class="container">

        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">What We Offer</span>
            <h2 class="section-title">Our Premium Services</h2>
            <p class="section-desc mx-auto text-center">Explore our full range of expert services crafted to bring out your best self.</p>
        </div>

        <!-- Quick Jump Nav -->
        <nav class="services-quick-nav animate-on-scroll" aria-label="Jump to service category">
            <a href="#cat-hair"><i class="fas fa-scissors"></i> Hair &amp; Styling</a>
            <a href="#cat-beard"><i class="fas fa-cut"></i> Beard &amp; Grooming</a>
            <a href="#cat-nails"><i class="fas fa-hand-sparkles"></i> Nails &amp; Care</a>
            <a href="#cat-makeup"><i class="fas fa-wand-magic-sparkles"></i> Makeup &amp; Bridal</a>
            <a href="#cat-skin"><i class="fas fa-spa"></i> Skin &amp; Facial</a>
        </nav>

        <?php
        /* ========================================
           SERVICE CATEGORIES (5 × 6 cards)
           ======================================== */
        $categories = [

            /* ---- 1. HAIR & STYLING ---- */
            [
                'key'    => 'hair',
                'name'   => 'Hair & Styling',
                'icon'   => 'fa-scissors',
                'desc'   => 'Haircuts, colour, treatments &amp; styling for every hair type and occasion.',
                'services' => [
                    [
                        'name'      => 'Signature Hair Cut',
                        'desc'      => 'A precision haircut tailored to your face shape, lifestyle and personal flair.',
                        'price'     => 45,
                        'duration'  => '45 min',
                        'image'     => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=600&q=80',
                        'features'  => ['Hair Consultation', 'Precision Cutting', 'Shampoo &amp; Blow Dry', 'Styling Tips'],
                        'popular'   => true,
                    ],
                    [
                        'name'      => 'Blow Dry &amp; Styling',
                        'desc'      => 'Voluminous blow-dry finished with elegant setting for any occasion.',
                        'price'     => 25,
                        'duration'  => '30 min',
                        'image'     => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&q=80',
                        'features'  => ['Shampoo Rinse', 'Volumising Blow Dry', 'Curling or Straightening', 'Finishing Spray'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Hair Coloring',
                        'desc'      => 'Full-coverage global colour using premium, gentle formulations.',
                        'price'     => 85,
                        'duration'  => '90 min',
                        'image'     => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=600&q=80',
                        'features'  => ['Colour Consultation', 'Global Colour Application', 'Balancing Toner', 'Post-Colour Aftercare'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Highlights &amp; Balayage',
                        'desc'      => 'Sun-kissed dimension with hand-painted balayage or foil highlights.',
                        'price'     => 75,
                        'duration'  => '60 min',
                        'image'     => 'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=600&q=80',
                        'features'  => ['Shade Matching', 'Foil or Balayage', 'Toning Treatment', 'Gloss Finish'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Keratin Smoothing Treatment',
                        'desc'      => 'Eliminate frizz and restore smoothness with salon-grade keratin therapy.',
                        'price'     => 95,
                        'duration'  => '120 min',
                        'image'     => 'https://images.unsplash.com/photo-1559599101-f09722fb4948?w=600&q=80',
                        'features'  => ['Hair Health Analysis', 'Keratin Application', 'Heat Sealing', 'Aftercare Kit'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Scalp &amp; Hair Spa',
                        'desc'      => 'A deep-cleansing scalp treatment paired with a relaxing head massage.',
                        'price'     => 50,
                        'duration'  => '50 min',
                        'image'     => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?w=600&q=80',
                        'features'  => ['Scalp Analysis', 'Steam Therapy', 'Nourishing Hair Mask', 'Relaxing Massage'],
                        'popular'   => false,
                    ],
                ],
            ],

            /* ---- 2. BEARD & GROOMING ---- */
            [
                'key'    => 'beard',
                'name'   => 'Beard &amp; Grooming',
                'icon'   => 'fa-cut',
                'desc'   => 'Sharp, masculine grooming to keep your beard and look always on point.',
                'services' => [
                    [
                        'name'      => 'Beard Trim &amp; Shape',
                        'desc'      => 'Expert beard trimming and sculpting for a clean, defined silhouette.',
                        'price'     => 15,
                        'duration'  => '25 min',
                        'image'     => 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=600&q=80',
                        'features'  => ['Beard Consultation', 'Trim &amp; Shape', 'Edge Line-up', 'Beard Oil Finish'],
                        'popular'   => true,
                    ],
                    [
                        'name'      => 'Beard Coloring',
                        'desc'      => 'Restore depth and cover greys with natural-looking beard tint.',
                        'price'     => 20,
                        'duration'  => '30 min',
                        'image'     => 'https://images.unsplash.com/photo-1519345182560-3f2917c472ef?w=600&q=80',
                        'features'  => ['Shade Matching', 'Grey Coverage', 'Colour Blending', 'Conditioning Rinse'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Hot Towel Shave',
                        'desc'      => 'A traditional straight-razor shave with a luxurious hot towel ritual.',
                        'price'     => 25,
                        'duration'  => '40 min',
                        'image'     => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=600&q=80',
                        'features'  => ['Hot Towel Prep', 'Exfoliation', 'Straight Razor Shave', 'Aftershave Balm'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Beard Spa &amp; Therapy',
                        'desc'      => 'Nourish and condition your beard with steam, deep oil treatment and styling.',
                        'price'     => 22,
                        'duration'  => '35 min',
                        'image'     => 'https://images.unsplash.com/photo-1621607512214-68297480165e?w=600&q=80',
                        'features'  => ['Beard Wash', 'Steam Therapy', 'Deep Oil Massage', 'Styling Wax'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Royal Grooming Package',
                        'desc'      => 'Full head-to-beard grooming: facial, scrub, beard care and head massage.',
                        'price'     => 45,
                        'duration'  => '70 min',
                        'image'     => 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=600&q=80',
                        'features'  => ['Face Cleansing', 'Detan Scrub', 'Beard Grooming', 'Head Massage'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Cut &amp; Beard Combo',
                        'desc'      => 'Complete session: precision haircut plus full beard styling in one visit.',
                        'price'     => 35,
                        'duration'  => '60 min',
                        'image'     => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&q=80',
                        'features'  => ['Precision Hair Cut', 'Beard Sculpting', 'Face Scrub', 'Styling Finish'],
                        'popular'   => true,
                    ],
                ],
            ],

            /* ---- 3. NAILS & CARE ---- */
            [
                'key'    => 'nails',
                'name'   => 'Nails &amp; Care',
                'icon'   => 'fa-hand-sparkles',
                'desc'   => 'Manicures, pedicures and nail art for immaculate hands and feet.',
                'services' => [
                    [
                        'name'      => 'Classic Manicure',
                        'desc'      => 'A timeless manicure with expert shaping, cuticle care and premium polish.',
                        'price'     => 30,
                        'duration'  => '40 min',
                        'image'     => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80',
                        'features'  => ['Nail Shaping', 'Cuticle Care', 'Hand Massage', 'Premium Polish'],
                        'popular'   => true,
                    ],
                    [
                        'name'      => 'Classic Pedicure',
                        'desc'      => 'A relaxing pedicure with foot soak, exfoliation and intensive massage.',
                        'price'     => 35,
                        'duration'  => '45 min',
                        'image'     => 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?w=600&q=80',
                        'features'  => ['Foot Soak', 'Gentle Exfoliation', 'Deep Foot Massage', 'Nail Polish Finish'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Gel Nail Polish',
                        'desc'      => 'Long-lasting, chip-free gel polish cured to a high-gloss finish.',
                        'price'     => 40,
                        'duration'  => '45 min',
                        'image'     => 'https://images.unsplash.com/photo-1610992015732-2449b76344bc?w=600&q=80',
                        'features'  => ['Nail Prep &amp; Base', 'Gel Polish Application', 'LED Cure', 'Cuticle Oil Finish'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Nail Art &amp; Design',
                        'desc'      => 'Custom nail art from minimalist accents to bold, statement designs.',
                        'price'     => 45,
                        'duration'  => '50 min',
                        'image'     => 'https://images.unsplash.com/photo-1617038260897-41a1f14a8ca0?w=600&q=80',
                        'features'  => ['Design Consultation', 'Custom Art Application', 'Sealing Top Coat', 'Aftercare Advice'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Luxury Deluxe Manicure',
                        'desc'      => 'An indulgent spa manicure with honey soak, paraffin treatment and massage.',
                        'price'     => 55,
                        'duration'  => '60 min',
                        'image'     => 'https://images.unsplash.com/photo-1600003014755-ba31aa59c4b6?w=600&q=80',
                        'features'  => ['Honey Soak', 'Paraffin Wax Dip', 'Sugar Scrub &amp; Mask', 'Deep Massage &amp; Gloss'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Gel Extensions &amp; Sculpt',
                        'desc'      => 'Custom-length gel extensions sculpted and shaped for a flawless, lasting finish.',
                        'price'     => 65,
                        'duration'  => '70 min',
                        'image'     => 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?w=600&q=80',
                        'features'  => ['Length Consultation', 'Gel Extension Build', 'Precision Shaping', 'Colour &amp; Art Finish'],
                        'popular'   => false,
                    ],
                ],
            ],

            /* ---- 4. MAKEUP & BRIDAL ---- */
            [
                'key'    => 'makeup',
                'name'   => 'Makeup &amp; Bridal',
                'icon'   => 'fa-wand-magic-sparkles',
                'desc'   => 'Flawless, camera-ready makeup for weddings, parties and every special moment.',
                'services' => [
                    [
                        'name'      => 'Bridal Makeup',
                        'desc'      => 'A complete bridal makeup package designed to look radiant in person and on camera.',
                        'price'     => 200,
                        'duration'  => '120 min',
                        'image'     => 'https://images.unsplash.com/photo-1519741497674-611481863552?w=600&q=80',
                        'features'  => ['Trial Session', 'HD Airbrush Base', 'Waterproof Finish', 'Emergency Touch-up Kit'],
                        'popular'   => true,
                    ],
                    [
                        'name'      => 'Party Makeup',
                        'desc'      => 'Statement looks for parties, dinners and celebratory events.',
                        'price'     => 60,
                        'duration'  => '60 min',
                        'image'     => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=600&q=80',
                        'features'  => ['Skin Prep', 'Bold or Glam Look', 'Lash Application', 'Long-wear Setting Spray'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Engagement Makeup',
                        'desc'      => 'Elegant, photoshoot-proof makeup curated for your engagement celebration.',
                        'price'     => 80,
                        'duration'  => '75 min',
                        'image'     => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?w=600&q=80',
                        'features'  => ['Look Trial', 'Airbrush Foundation', 'Hair Styling Coordination', 'Photography Ready Finish'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'HD Cinematic Makeup',
                        'desc'      => 'Ultra-HD, camera-proof makeup for film, photoshoots and high-end events.',
                        'price'     => 90,
                        'duration'  => '75 min',
                        'image'     => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=600&q=80',
                        'features'  => ['HD Camera-Ready Base', 'Professional Contouring', 'Eye &amp; Lip Enhancements', 'Touch-up Guide Included'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Makeup Trial Session',
                        'desc'      => 'Explore and perfect your desired look ahead of the big day or event.',
                        'price'     => 50,
                        'duration'  => '45 min',
                        'image'     => 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?w=600&q=80',
                        'features'  => ['Skin Analysis', 'Look Exploration', 'Product Matching', 'Photo &amp; Booking Notes'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Bridal Hair &amp; Makeup Package',
                        'desc'      => 'All-in-one bridal package: professional hairstyling plus full makeup coverage.',
                        'price'     => 350,
                        'duration'  => '4 hrs',
                        'image'     => 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=600&q=80',
                        'features'  => ['Hair Styling &amp; Updo', 'Full HD Makeup', 'Trial Session Included', 'On-call Day-of Touch-ups'],
                        'popular'   => false,
                    ],
                ],
            ],

            /* ---- 5. SKIN & FACIAL ---- */
            [
                'key'    => 'skin',
                'name'   => 'Skin &amp; Facial',
                'icon'   => 'fa-spa',
                'desc'   => 'Glowing, healthy skin with expert facials, treatments and body care.',
                'services' => [
                    [
                        'name'      => 'Classic Facial',
                        'desc'      => 'A thorough cleansing facial customised to your skin type for renewed radiance.',
                        'price'     => 55,
                        'duration'  => '60 min',
                        'image'     => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=600&q=80',
                        'features'  => ['Skin Analysis', 'Deep Cleansing', 'Exfoliation', 'Hydrating Mask &amp; Moisturiser'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Anti-Aging Facial',
                        'desc'      => 'Collagen-boosting firming treatment with serum infusion and lymphatic massage.',
                        'price'     => 70,
                        'duration'  => '60 min',
                        'image'     => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=600&q=80',
                        'features'  => ['Collagen Boost Serum', 'Firming Mask', 'Lymphatic Massage', 'Age-Spot Treatment'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Deep Cleansing Facial',
                        'desc'      => 'Steam-assisted deep cleansing with careful extraction and purifying mask.',
                        'price'     => 60,
                        'duration'  => '55 min',
                        'image'     => 'https://images.unsplash.com/photo-1594744803329-e58b31de8bf5?w=600&q=80',
                        'features'  => ['Steam Preparation', 'Gentle Extraction', 'Purifying Clay Mask', 'Soothing Finish'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => '24K Gold Facial',
                        'desc'      => 'Luxury 24-karat gold mask for instant radiance, firming and glow.',
                        'price'     => 100,
                        'duration'  => '75 min',
                        'image'     => 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?w=600&q=80',
                        'features'  => ['24K Gold Sheet Mask', 'Lifting Massage', 'Antioxidant Serum', 'Glow-Boosting Finish'],
                        'popular'   => true,
                    ],
                    [
                        'name'      => 'HydraGlow Facial',
                        'desc'      => 'Hydra-dermabrasion treatment delivering deep hydration and luminous skin.',
                        'price'     => 85,
                        'duration'  => '70 min',
                        'image'     => 'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?w=600&q=80',
                        'features'  => ['Hydra-Dermabrasion', 'Deep Hydration Infusion', 'Oxygen Mist', 'Brightening Serum'],
                        'popular'   => false,
                    ],
                    [
                        'name'      => 'Body Spa &amp; Detox',
                        'desc'      => 'Full back-and-body spa: scrub, mask, massage and hydrating finish.',
                        'price'     => 65,
                        'duration'  => '60 min',
                        'image'     => 'https://images.unsplash.com/photo-1513201099705-a9746e1e201f?w=600&q=80',
                        'features'  => ['Body Cleansing', 'Salt or Sugar Scrub', 'Full Body Massage', 'Hydrating Lotion Finish'],
                        'popular'   => false,
                    ],
                ],
            ],
        ];

        $categoriesCount = count($categories);
        $totalServices   = 0;
        foreach ($categories as $cat) { $totalServices += count($cat['services']); }
        ?>

        <p class="text-center animate-on-scroll" style="color:var(--muted); margin-bottom:2.5rem; font-size:0.95rem;">
            <strong style="color:#fff;"><?php echo $totalServices; ?> services</strong> across
            <strong style="color:#fff;"><?php echo $categoriesCount; ?> categories</strong> — choose your experience.
        </p>

        <?php foreach ($categories as $catIndex => $category): ?>
        <div class="services-category animate-on-scroll" id="cat-<?php echo htmlspecialchars($category['key']); ?>">
            <div class="category-header">
                <div class="category-icon"><i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i></div>
                <div>
                    <h3 class="category-title"><?php echo htmlspecialchars_decode($category['name']); ?></h3>
                    <p class="category-desc"><?php echo htmlspecialchars_decode($category['desc']); ?></p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($category['services'] as $sIndex => $service): ?>
                <div class="col-lg-4 col-md-6 animate-on-scroll delay-<?php echo ($sIndex % 3) + 1; ?>">
                    <div class="service-card">
                        <div class="service-card-image">
                            <img src="<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" loading="lazy">
                            <?php if ($service['popular']): ?>
                            <span class="service-badge"><i class="fas fa-fire"></i> Popular</span>
                            <?php endif; ?>
                        </div>
                        <div class="service-card-body">
                            <h4><?php echo htmlspecialchars_decode($service['name']); ?></h4>
                            <p class="service-desc"><?php echo htmlspecialchars_decode($service['desc']); ?></p>
                            <div class="service-price">
                                $<?php echo (int) $service['price']; ?>
                                <span>/ <?php echo htmlspecialchars($service['duration']); ?></span>
                            </div>
                            <ul class="service-features">
                                <?php foreach ($service['features'] as $feature): ?>
                                <li><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars_decode($feature); ?></li>
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
        </div>

        <?php if ($catIndex < $categoriesCount - 1): ?>
        <hr class="category-divider animate-on-scroll">
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Bottom CTA -->
        <div class="services-note animate-on-scroll">
            <h3><i class="fas fa-comment-dots" style="color:var(--cyan); margin-right:0.5rem;"></i> Can't find what you're looking for?</h3>
            <p>Our team can create a custom package tailored specifically to your needs. Get in touch and we'll design the perfect experience for you.</p>
            <a href="<?php echo SITE_URL; ?>/contact.php" class="btn-accent" style="display:inline-flex;">
                <i class="fas fa-envelope"></i> Contact Us
            </a>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
