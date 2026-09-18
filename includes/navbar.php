<?php
/**
 * Navbar Include
 * Elegance Salon
 */
$activePage = getCurrentPage();
?>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNavbar">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand" href="<?php echo SITE_URL; ?>/index.php">
            <span class="brand-elegance">ELEGANCE</span>
            <span class="brand-salon">SALON</span>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'home' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'about' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'services' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/services.php">Services</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'gallery' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/gallery.php">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'team' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/team.php">Team</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'contact' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/contact.php">Contact</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'login' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/login.php">
                        <i class="fas fa-user me-1"></i>Login
                    </a>
                </li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-accent btn-nav-book" href="<?php echo SITE_URL; ?>/book-appointment.php">Book Appointment</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navbar -->
