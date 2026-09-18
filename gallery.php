<?php
/**
 * Gallery Page – Elegance Salon
 * 48 curated images across 6 categories
 */
$pageTitle = "Gallery - Elegance Salon";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="page-header-bg" style="background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1920&q=80');"></div>
    <div class="container page-header-content">
        <div class="page-header-icon"><i class="fas fa-images"></i></div>
        <h1>Gallery</h1>
        <p class="page-header-desc">A glimpse into our world of transformations, artistry, and stunning beauty creations.</p>
        <div class="breadcrumb">
            <a href="<?php echo SITE_URL; ?>/index.php">Home</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">Gallery</span>
        </div>
    </div>
</section>

<section class="gallery-section section-padding">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">Our Portfolio</span>
            <h2 class="section-title">Our Work Speaks For Itself</h2>
            <p class="section-desc mx-auto text-center">Browse through our collection of 48 stunning transformations and expert styling work.</p>
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

                /* ===== HAIR (9) ===== */
                ['title' => 'Elegant Updo',             'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&q=80'],
                ['title' => 'Balayage Perfection',       'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1527799820374-dcf8d9d4a388?w=600&q=80'],
                ['title' => 'Classic Blowout',           'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=600&q=80'],
                ['title' => 'Colour Transformation',     'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1559599101-f09722fb4948?w=600&q=80'],
                ['title' => 'Voluminous Curls',          'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=600&q=80'],
                ['title' => 'Sleek & Straight',          'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1519699047748-de8e457a634e?w=600&q=80'],
                ['title' => 'Natural Waves',             'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?w=600&q=80'],
                ['title' => 'Modern Haircut',            'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=600&q=80'],
                ['title' => 'Keratin Treatment',         'category' => 'hair',  'image' => 'https://images.unsplash.com/photo-1488229297570-58520851e868?w=600&q=80'],

                /* ===== MAKEUP (9) ===== */
                ['title' => 'Evening Glam',              'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=600&q=80'],
                ['title' => 'Natural Glow',              'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=600&q=80'],
                ['title' => 'Professional Glam',         'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?w=600&q=80'],
                ['title' => 'Eye Makeup Art',            'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1551232864-3f0890e580d9?w=600&q=80'],
                ['title' => 'Bold Lip Look',             'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=600&q=80'],
                ['title' => 'Smokey Eye',                'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1553531384-411a247ccd73?w=600&q=80'],
                ['title' => 'Dewy Radiance',             'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=600&q=80'],
                ['title' => 'Editorial Look',            'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&q=80'],
                ['title' => 'Highlight & Contour',       'category' => 'makeup', 'image' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=600&q=80'],

                /* ===== FACIAL (7) ===== */
                ['title' => 'Radiant Glow Facial',       'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=600&q=80'],
                ['title' => 'Deep Cleansing',            'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1512290923902-8a9f81dc236c?w=600&q=80'],
                ['title' => 'Anti-Aging Treatment',      'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1594744803329-e58b31de8bf5?w=600&q=80'],
                ['title' => '24K Gold Facial',           'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?w=600&q=80'],
                ['title' => 'HydraGlow Treatment',       'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?w=600&q=80'],
                ['title' => 'Spa Essentials',            'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1583001931096-959e9a1a6223?w=600&q=80'],
                ['title' => 'Body Spa & Detox',          'category' => 'facial', 'image' => 'https://images.unsplash.com/photo-1513201099705-a9746e1e201f?w=600&q=80'],

                /* ===== NAILS (7) ===== */
                ['title' => 'French Manicure',           'category' => 'nails',  'image' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=600&q=80'],
                ['title' => 'Gel Nail Art',              'category' => 'nails',  'image' => 'https://images.unsplash.com/photo-1607779097040-26e80aa78e66?w=600&q=80'],
                ['title' => 'Classic Pedicure',          'category' => 'nails',  'image' => 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?w=600&q=80'],
                ['title' => 'Nail Art Design',           'category' => 'nails',  'image' => 'https://images.unsplash.com/photo-1610992015732-2449b76344bc?w=600&q=80'],
                ['title' => 'Luxury Manicure',           'category' => 'nails',  'image' => 'https://images.unsplash.com/photo-1617038260897-41a1f14a8ca0?w=600&q=80'],
                ['title' => 'Gel Extensions',            'category' => 'nails',  'image' => 'https://images.unsplash.com/photo-1600003014755-ba31aa59c4b6?w=600&q=80'],
                ['title' => 'Polish Collection',         'category' => 'nails',  'image' => 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?w=600&q=80'],

                /* ===== SALON (8) ===== */
                ['title' => 'Salon Interior',            'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=600&q=80'],
                ['title' => 'Luxury Interior',           'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=600&q=80'],
                ['title' => 'Premium Setup',             'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=600&q=80'],
                ['title' => 'Styling Station',           'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=600&q=80'],
                ['title' => 'Barber Station',            'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=600&q=80'],
                ['title' => 'Salon Chair',               'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1521510895919-46920266ddb3?w=600&q=80'],
                ['title' => 'Product Display',           'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1524650359799-842906ca1c06?w=600&q=80'],
                ['title' => 'Reception Area',            'category' => 'salon',  'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=600&q=80'],

                /* ===== BRIDAL (8) ===== */
                ['title' => 'Bridal Glamour',            'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1519741497674-611481863552?w=600&q=80'],
                ['title' => 'Bridal Styling',            'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?w=600&q=80'],
                ['title' => 'Bridal Beauty',             'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=600&q=80'],
                ['title' => 'Wedding Makeup',            'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=600&q=80'],
                ['title' => 'Bridal Portrait',           'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1488426862026-3ee34a7d66df?w=600&q=80'],
                ['title' => 'Bridal Hair',               'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1502823403499-6ccfcf4fb453?w=600&q=80'],
                ['title' => 'Bride & Groom',             'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1542596768-5d1d21f1cf98?w=600&q=80'],
                ['title' => 'Bridal Suite',              'category' => 'bridal', 'image' => 'https://images.unsplash.com/photo-1523875194681-bedd468c58bf?w=600&q=80'],
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
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
