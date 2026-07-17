<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
redirectIfLoggedIn();

$errors = [];
$formData = [
    'email' => '',
];

$successMessage = '';

if (isset($_GET['registered'])) {
    $successMessage = 'Registration successful. You can now sign in.';
}

if (isset($_GET['logout'])) {
    $successMessage = 'You have been logged out successfully.';
}

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (!validateCsrfToken($csrfToken)) {
        $errors[] = 'The login form expired. Please refresh the page and try again.';
    }

    $formData['email'] = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        try {
            $statement = $pdo->prepare(
                'SELECT id, full_name, email, role, password_hash, status
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
             );
            $statement->execute(['email' => $formData['email']]);
            $user = $statement->fetch();

            if (!$user || !password_verify($password, (string) $user['password_hash'])) {
                $errors[] = 'Invalid email or password.';
            } elseif (isset($user['status']) && $user['status'] === 'Suspended') {
                $errors[] = 'Your account has been suspended. Please contact system support.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = (string) $user['full_name'];
                $_SESSION['user_email'] = (string) $user['email'];
                $_SESSION['user_role'] = (string) $user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                redirect_by_role((string) $user['role']);
            }
        } catch (PDOException $exception) {
            error_log('Login failed: ' . $exception->getMessage());
            $errors[] = 'We could not sign you in right now. Please try again later.';
        }
    }
}

function old(string $field, array $formData): string
{
    return htmlspecialchars($formData[$field] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | House Rental Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script defer src="assets/js/script.js"></script>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-intro">
            <div class="brand-mark">HRMS</div>
            <h1>Welcome Back</h1>
            <p>Sign in to your House Rental Management System dashboard to manage inquiries, check rentals, or browse available properties.</p>
            <ul class="feature-list">
                <li>Instant role-based dashboard redirection</li>
                <li>Secure session management</li>
                <li>Optimized security protocols</li>
            </ul>
        </section>

        <section class="auth-card" aria-labelledby="login-title">
            <div class="card-header">
                <h2 id="login-title">Sign in</h2>
                <p>Enter your email and password to access your dashboard.</p>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="alert success" role="status"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($flashError !== ''): ?>
                <div class="alert error" role="alert"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert error" role="alert">
                    <strong>Please fix the following:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="register-form" id="loginForm" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo old('email', $formData); ?>" placeholder="you@example.com" autocomplete="email" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                </div>

                <button type="submit" class="submit-btn">Sign in</button>
                
                <p style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--muted);">
                    Don't have an account? <a href="register.php" style="color: var(--accent); text-decoration: none; font-weight: 600;">Register here</a>.
                </p>
            </form>
        </section>
    </main>
</body>
</html>
