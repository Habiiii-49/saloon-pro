<?php
/**
 * Forgot Password – placeholder notice.
 * Intentionally contains NO password-reset functionality in PART 2.
 * Password resets are performed securely by an admin via user management.
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirectToRoleHome();
}

$pageTitle = 'Forgot Password - Elegance Salon';
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem;
            background:
                radial-gradient(circle at 25% 20%, rgba(0,194,217,0.12), transparent 45%),
                radial-gradient(circle at 80% 80%, rgba(212,168,67,0.08), transparent 45%),
                #071827;
        }
        .box {
            max-width: 520px; width: 100%; text-align: center;
            padding: 3rem 2.5rem;
            background: rgba(11,34,53,0.6);
            -webkit-backdrop-filter: blur(20px); backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-top: 1px solid rgba(0,194,217,0.35);
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.5);
            animation: rise 0.7s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes rise { from { opacity:0; transform: translateY(30px); } to { opacity:1; transform:none; } }
        .icon {
            width: 84px; height: 84px; margin: 0 auto 1.4rem;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(0,194,217,0.12);
            border: 1px solid rgba(0,194,217,0.4);
            color: #08D9E8; font-size: 34px;
        }
        h1 { font-family:'Montserrat',sans-serif; font-size:1.6rem; font-weight:800; color:#fff; margin-bottom:0.9rem; }
        p { color:#94A3B8; font-size:0.95rem; line-height:1.8; margin-bottom:1.9rem; }
        .btn-back {
            display:inline-flex; align-items:center; gap:0.5rem;
            padding:0.85rem 1.7rem; border-radius:12px;
            font-family:'Montserrat',sans-serif; font-weight:600; font-size:0.9rem;
            text-decoration:none; color:#06242e;
            background:linear-gradient(135deg,#08D9E8,#00C2D9);
            box-shadow:0 12px 30px rgba(0,194,217,0.3);
            transition:transform .3s ease;
        }
        .btn-back:hover { transform:translateY(-2px); }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon"><i class="fas fa-key"></i></div>
        <h1>Forgot Password?</h1>
        <p>Self-service password reset is not yet available. Please contact your salon administrator or receptionist to securely reset your password.</p>
        <a href="<?php echo SITE_URL; ?>/login.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</body>
</html>