<?php
/**
 * Team Page
 * Elegance Salon
 */
$pageTitle = "Our Team - Elegance Salon";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="page-header-bg" style="background-image: url('https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=1920&q=80');"></div>
    <div class="container page-header-content">
        <div class="page-header-icon"><i class="fas fa-users"></i></div>
        <h1>Our Team</h1>
        <p class="page-header-desc">Meet the talented professionals behind every stunning look — passionate artists dedicated to your beauty.</p>
        <div class="breadcrumb">
            <a href="<?php echo SITE_URL; ?>/index.php">Home</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">Team</span>
        </div>
    </div>
</section>

<section class="team-section section-padding">
    <div class="container">
        <div class="text-center mb-5 animate-on-scroll">
            <span class="section-subtitle">Meet The Experts</span>
            <h2 class="section-title">Our Professional Team</h2>
            <p class="section-desc mx-auto text-center">Dedicated professionals committed to making you look and feel extraordinary.</p>
        </div>

        <div class="row g-4">
            <?php
            $team = [
                ['name' => 'Sophia Laurent', 'role' => 'Senior Hair Stylist', 'specialty' => 'Precision cuts, creative styling, bridal hair', 'image' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=500&q=80'],
                ['name' => 'Isabella Chen', 'role' => 'Beauty Specialist', 'specialty' => 'Skincare, facial treatments, makeup artistry', 'image' => 'https://images.unsplash.com/photo-1594744803329-e58b31de8bf5?w=500&q=80'],
                ['name' => 'Marcus Rivera', 'role' => 'Hair Color Expert', 'specialty' => 'Balayage, highlights, color corrections', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=500&q=80'],
                ['name' => 'Ava Thompson', 'role' => 'Skin Care Specialist', 'specialty' => 'Anti-aging, rejuvenation, deep cleansing', 'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=500&q=80'],
                ['name' => 'Elena Vasquez', 'role' => 'Nail Artist', 'specialty' => 'Gel nails, nail art, luxury pedicures', 'image' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=500&q=80'],
                ['name' => 'James Mitchell', 'role' => 'Senior Stylist', 'specialty' => 'Men grooming, texturizing, modern cuts', 'image' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=500&q=80'],
            ];
            foreach ($team as $index => $member):
            ?>
            <div class="col-lg-4 col-md-6 animate-on-scroll delay-<?php echo ($index % 3) + 1; ?>">
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
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
