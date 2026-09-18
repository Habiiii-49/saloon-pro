<?php
/**
 * Header Include
 * Elegance Salon
 */
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'Elegance Salon - Premium Beauty & Styling';
$activePage = getCurrentPage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Elegance Salon - Premium beauty and styling services. Experience luxury hair, skin, and nail treatments.">
    <meta name="author" content="Elegance Salon">
    <title><?php echo sanitize($pageTitle); ?></title>

    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg" type="image/svg+xml">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body>
<?php
$flashes = getFlash();
if (!empty($flashes)): ?>
<div class="flash-container position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 9999;">
    <?php foreach ($flashes as $type => $message): ?>
    <div class="alert alert-<?php echo $type === 'error' ? 'danger' : $type; ?> alert-dismissible fade show" role="alert">
        <?php echo sanitize($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
