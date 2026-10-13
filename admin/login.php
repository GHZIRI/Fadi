<?php
/**
 * THREAD&CO - Administrator Login Page (admin/login.php)
 * 
 * Handles secure authentication for store administrators:
 * - Redirects active admin sessions straight to dashboard (index.php)
 * - CSRF token verification on login form submission
 * - Secure PDO credential checking with bcrypt (password_verify)
 * - Session fixation prevention via session_regenerate_id
 * - Clean, elegant authentication interface with demo credentials hint
 */

// Initialize configuration and helper utilities
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// If user is already logged in as administrator, redirect to dashboard
if (isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$emailInput = '';

// Handle POST authentication request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Validate CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $emailInput = trim($_POST['email'] ?? '');
        $passwordInput = $_POST['password'] ?? '';

        if (empty($emailInput) || empty($passwordInput)) {
            $error = 'Please enter both your email address and password.';
        } else {
            try {
                // 2. Query admin user by email and strict 'admin' role
                $stmt = $pdo->prepare("
                    SELECT id, full_name, email, password, role 
                    FROM users 
                    WHERE email = :email AND role = 'admin' 
                    LIMIT 1
                ");
                $stmt->execute(['email' => $emailInput]);
                $adminUser = $stmt->fetch();

                // 3. Verify bcrypt password hash
                if ($adminUser && password_verify($passwordInput, $adminUser['password'])) {
                    
                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Establish authenticated admin session
                    $_SESSION['user_id']    = (int)$adminUser['id'];
                    $_SESSION['user_name']  = $adminUser['full_name'];
                    $_SESSION['user_email'] = $adminUser['email'];
                    $_SESSION['user_role']  = $adminUser['role'];

                    setFlashMessage('success', "Welcome back, " . htmlspecialchars($adminUser['full_name']) . "!");
                    header('Location: index.php');
                    exit;

                } else {
                    $error = 'Invalid email credentials or insufficient administrator privileges.';
                }

            } catch (PDOException $e) {
                $error = 'Database error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Login | THREAD&CO</title>
    
    <!-- Link to Admin Stylesheet -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <!-- Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body.admin-login-body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            margin: 0;
        }

        .login-card {
            background: #ffffff;
            color: #0f172a;
            width: 100%;
            max-width: 440px;
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }

        .login-brand {
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .login-badge {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .login-subtitle {
            color: #64748b;
            font-size: 0.92rem;
            margin-bottom: 28px;
        }

        .demo-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.82rem;
            color: #475569;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .demo-box strong {
            color: #0f172a;
        }

        .login-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 0.88rem;
            margin-bottom: 22px;
        }

        .store-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 24px;
            font-size: 0.85rem;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
        }

        .store-back-link:hover {
            color: #0f172a;
        }
    </style>
</head>
<body class="admin-login-body">

    <div class="login-card">
        
        <!-- Header / Branding -->
        <div class="login-brand">
            THREAD&CO <span class="login-badge">Admin Portal</span>
        </div>
        <p class="login-subtitle">Sign in with your administrator credentials.</p>

        <!-- Demo Credentials Box -->
        <div class="demo-box">
            🔑 <strong>Demo Admin Credentials:</strong><br>
            Email: <code>admin@store.com</code><br>
            Password: <code>password</code>
        </div>

        <!-- Error Message Display -->
        <?php if (!empty($error)): ?>
            <div class="login-error">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Authentication Form -->
        <form action="login.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">

            <!-- Email Field -->
            <div class="admin-form-group">
                <label for="email" class="admin-form-label">Administrator Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="admin-form-control" 
                       placeholder="admin@store.com"
                       value="<?= htmlspecialchars($emailInput); ?>" 
                       required 
                       autofocus>
            </div>

            <!-- Password Field -->
            <div class="admin-form-group" style="margin-bottom: 28px;">
                <label for="password" class="admin-form-label">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="admin-form-control" 
                       placeholder="••••••••" 
                       required>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; padding: 13px; font-size: 1rem;">
                Secure Sign In &rarr;
            </button>
        </form>

        <!-- Link Back to Storefront -->
        <div style="text-align: center;">
            <a href="../index.php" class="store-back-link">
                &larr; Return to Customer Storefront
            </a>
        </div>

    </div>

</body>
</html>
