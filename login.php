<?php
/**
 * Login Page
 * Elegance Salon – PART 2
 */
require_once __DIR__ . '/includes/functions.php';

// Already logged in? Go to your dashboard.
if (isLoggedIn()) {
    redirectToRoleHome();
}

// Try to restore session from remember-me cookie.
attemptRememberLogin();
if (isLoggedIn()) {
    redirectToRoleHome();
}

$pageTitle = "Login - Elegance Salon";
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = (string)($_POST['password'] ?? '');
        $remember   = !empty($_POST['remember']);

        if ($identifier === '' || $password === '') {
            $error = 'Please enter your email/username and password.';
        } else {
            $result = authenticateUser($identifier, $password);
            if ($result['success']) {
                if ($remember) {
                    establishRememberMe((int)$result['user']['user_id']);
                }
                redirectToRoleHome();
            }
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to the Elegance Salon management portal.">
    <title><?php echo sanitize($pageTitle); ?></title>

    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg" type="image/svg+xml">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom login styles -->
    <style>
        :root {
            --dark-navy: #071827;
            --deep-navy: #0B2235;
            --navy-medium: #0f2a3f;
            --cyan: #00C2D9;
            --bright-cyan: #08D9E8;
            --muted: #94A3B8;
            --gold: #d4a843;
            --card-bg: rgba(11, 34, 53, 0.55);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            background: var(--dark-navy);
        }

        /* ---------- Background ---------- */
        .login-backdrop {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(160deg, rgba(7,24,39,0.92) 0%, rgba(11,34,53,0.82) 45%, rgba(7,24,39,0.95) 100%),
                url('https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1920&q=80') center/cover no-repeat;
        }
        .login-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.25;
            z-index: 1;
            pointer-events: none;
        }
        .glow-1 { width: 420px; height: 420px; background: var(--cyan); top: -120px; right: -80px; }
        .glow-2 { width: 360px; height: 360px; background: var(--bright-cyan); bottom: -120px; left: -100px; }

        .login-shell {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* ---------- Glass card ---------- */
        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            -webkit-backdrop-filter: blur(22px) saturate(140%);
            backdrop-filter: blur(22px) saturate(140%);
            border: 1px solid rgba(255,255,255,0.10);
            border-top: 1px solid rgba(0,194,217,0.35);
            border-radius: 22px;
            padding: 2.4rem 2.2rem;
            box-shadow: 0 30px 80px rgba(0,0,0,0.5), 0 0 40px rgba(0,194,217,0.08);
            animation: cardIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(28px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ---------- Brand ---------- */
        .login-brand {
            text-align: center;
            margin-bottom: 1.7rem;
        }
        .login-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(0,194,217,0.25), rgba(8,217,232,0.05));
            border: 1px solid rgba(0,194,217,0.4);
            color: var(--cyan);
            font-size: 26px;
            box-shadow: 0 10px 30px rgba(0,194,217,0.18);
        }
        .login-brand h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            margin: 0;
        }
        .login-brand p {
            color: var(--muted);
            font-size: 0.9rem;
            margin: 0.2rem 0 0;
        }
        .login-tag {
            display: inline-block;
            margin-top: 0.7rem;
            padding: 0.28rem 0.9rem;
            border-radius: 50rem;
            background: rgba(0,194,217,0.12);
            border: 1px solid rgba(0,194,217,0.3);
            color: var(--bright-cyan);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        /* ---------- Fields ---------- */
        .form-field {
            margin-bottom: 1.1rem;
        }
        .form-field label {
            display: block;
            font-size: 0.82rem;
            font-weight: 500;
            color: #cfdbe6;
            margin-bottom: 0.4rem;
            letter-spacing: 0.02em;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.9rem;
            transition: color 0.3s ease;
            z-index: 2;
        }
        .input-wrap .toggle-pw {
            left: auto;
            right: 14px;
            cursor: pointer;
            color: var(--muted);
        }
        .input-wrap .toggle-pw:hover { color: var(--cyan); }
        .input-wrap input {
            width: 100%;
            padding: 0.82rem 2.6rem;
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.14);
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
        }
        .input-wrap input::placeholder { color: rgba(148,163,184,0.7); }
        .input-wrap input:focus {
            border-color: rgba(0,194,217,0.7);
            background: rgba(0,194,217,0.06);
            box-shadow: 0 0 0 3px rgba(0,194,217,0.14);
        }
        .input-wrap:focus-within > i { color: var(--cyan); }

        /* ---------- Options row ---------- */
        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0.2rem 0 1.3rem;
        }
        .remember {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--muted);
            font-size: 0.85rem;
            cursor: pointer;
            user-select: none;
        }
        .remember input {
            accent-color: var(--cyan);
            width: 15px;
            height: 15px;
            cursor: pointer;
        }
        .forgot-link {
            color: var(--cyan);
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .forgot-link:hover { color: var(--bright-cyan); }

        /* ---------- Button ---------- */
        .btn-login {
            width: 100%;
            padding: 0.9rem;
            border: none;
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #06242e;
            background: linear-gradient(135deg, var(--bright-cyan), var(--cyan));
            box-shadow: 0 12px 30px rgba(0,194,217,0.32);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease, filter 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 38px rgba(0,194,217,0.42);
            filter: brightness(1.05);
        }
        .btn-login:active { transform: translateY(0); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        /* ---------- Alert ---------- */
        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1.2rem;
            font-size: 0.88rem;
            animation: cardIn 0.4s ease both;
        }
        .login-alert.error {
            background: rgba(239,68,68,0.12);
            border: 1px solid rgba(239,68,68,0.4);
            color: #fca5a5;
        }
        .login-alert.success {
            background: rgba(34,197,94,0.12);
            border: 1px solid rgba(34,197,94,0.4);
            color: #86efac;
        }
        .login-alert i { margin-top: 0.15rem; }

        /* ---------- Footer ---------- */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--muted);
            font-size: 0.82rem;
        }
        .login-footer a {
            color: var(--cyan);
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover { color: var(--bright-cyan); }

        .login-demo {
            margin-top: 1.2rem;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            background: rgba(212,168,67,0.08);
            border: 1px dashed rgba(212,168,67,0.35);
            font-size: 0.8rem;
            color: #e7d3a7;
            line-height: 1.7;
        }
        .login-demo strong { color: var(--gold); }

        @media (max-width: 480px) {
            .login-card { padding: 1.8rem 1.4rem; border-radius: 18px; }
        }
    </style>
</head>
<body>

    <div class="login-backdrop"></div>
    <div class="login-glow glow-1"></div>
    <div class="login-glow glow-2"></div>

    <div class="login-shell">
        <div class="login-card">

            <div class="login-brand">
                <div class="login-logo"><i class="fas fa-wand-magic-sparkles"></i></div>
                <h1>Elegance Salon</h1>
                <p>Management Portal</p>
                <div class="login-tag"><i class="fas fa-lock-open"></i> Secure Sign In</div>
            </div>

            <?php if ($error): ?>
            <div class="login-alert error">
                <i class="fas fa-circle-exclamation"></i>
                <span><?php echo sanitize($error); ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="" autocomplete="off" novalidate>
                <?php echo csrfField(); ?>

                <div class="form-field">
                    <label for="identifier">Email or Username</label>
                    <div class="input-wrap">
                        <i class="fas fa-user"></i>
                        <input type="text" id="identifier" name="identifier"
                               placeholder="you@example.com or username"
                               value="<?php echo sanitize($_POST['identifier'] ?? ''); ?>"
                               required autofocus>
                    </div>
                </div>

                <div class="form-field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" required>
                        <i class="fas fa-eye toggle-pw" id="togglePw" title="Show password"></i>
                    </div>
                </div>

                <div class="login-options">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-arrow-right-to-bracket"></i> Login
                </button>
            </form>

            <div class="login-demo">
                <strong>Demo Accounts</strong><br>
                Admin — admin@elegancesalon.com / admin123<br>
                Receptionist — reception@elegancesalon.com / reception123<br>
                Stylist — stylist@elegancesalon.com / stylist123
            </div>

            <div class="login-footer">
                &copy; <?php echo date('Y'); ?> Elegance Salon · <a href="<?php echo SITE_URL; ?>/index.php">Back to website</a>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var toggle = document.getElementById('togglePw');
            var pw = document.getElementById('password');
            var btn = document.getElementById('loginBtn');
            if (toggle && pw) {
                toggle.addEventListener('click', function () {
                    var show = pw.type === 'password';
                    pw.type = show ? 'text' : 'password';
                    toggle.classList.toggle('fa-eye');
                    toggle.classList.toggle('fa-eye-slash');
                });
            }
            if (btn) {
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Signing in...';
                    btn.closest('form').submit();
                });
            }
        })();
    </script>
</body>
</html>