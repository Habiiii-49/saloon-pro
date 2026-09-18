<?php
/**
 * Access Denied – shown when a logged-in user tries to open
 * a page they do not have permission to view.
 */
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Access Denied - Elegance Salon';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?></title>
    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background:
                radial-gradient(circle at 20% 20%, rgba(0,194,217,0.12), transparent 45%),
                radial-gradient(circle at 80% 80%, rgba(8,217,232,0.1), transparent 45%),
                #071827;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            overflow: hidden;
        }
        .denied-card {
            position: relative;
            text-align: center;
            max-width: 520px;
            width: 100%;
            padding: 3rem 2.5rem;
            background: rgba(11,34,53,0.6);
            -webkit-backdrop-filter: blur(20px);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.10);
            border-top: 1px solid rgba(239,68,68,0.5);
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.5);
            animation: rise 0.7s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .denied-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.4);
            color: #f87171;
            font-size: 38px;
            box-shadow: 0 0 50px rgba(239,68,68,0.2);
        }
        .denied-code {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.35em;
            color: #f87171;
            text-transform: uppercase;
            margin-bottom: 0.6rem;
        }
        h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.8rem;
        }
        p {
            color: #94A3B8;
            font-size: 0.98rem;
            line-height: 1.8;
            margin-bottom: 2rem;
        }
        .actions { display: flex; gap: 0.9rem; justify-content: center; flex-wrap: wrap; }
        .btn-denied {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.6rem;
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-primary-d {
            color: #06242e;
            background: linear-gradient(135deg, #08D9E8, #00C2D9);
            box-shadow: 0 12px 30px rgba(0,194,217,0.3);
        }
        .btn-primary-d:hover { transform: translateY(-2px); }
        .btn-secondary-d {
            color: #fff;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.16);
        }
        .btn-secondary-d:hover { background: rgba(255,255,255,0.12); }
    </style>
</head>
<body>
    <div class="denied-card">
        <div class="denied-icon"><i class="fas fa-lock"></i></div>
        <div class="denied-code"><i class="fas fa-shield-halved"></i> 403 Forbidden</div>
        <h1>Access Denied</h1>
        <p>You don't have permission to access this page. If you believe this is a mistake, please contact your administrator.</p>
        <div class="actions">
            <?php if (isLoggedIn()): ?>
            <a href="<?php echo SITE_URL; ?>/<?php echo roleHome(); ?>" class="btn-denied btn-primary-d">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/login.php" class="btn-denied btn-primary-d">
                <i class="fas fa-arrow-right-to-bracket"></i> Sign In
            </a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/index.php" class="btn-denied btn-secondary-d">
                <i class="fas fa-globe"></i> Visit Website
            </a>
        </div>
    </div>
</body>
</html>