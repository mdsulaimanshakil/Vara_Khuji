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
                'SELECT id, full_name, email, role, password_hash
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );
            $statement->execute(['email' => $formData['email']]);
            $user = $statement->fetch();

            if (!$user || !password_verify($password, (string) $user['password_hash'])) {
                $errors[] = 'Invalid email or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = (string) $user['full_name'];
                $_SESSION['user_email'] = (string) $user['email'];
                $_SESSION['user_role'] = (string) $user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: ' . dashboardPathForRole((string) $user['role']));
                exit;
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
            <h1>House Rental Management System</h1>
            <p>Sign in to access your tenant or landlord dashboard. Each account is routed automatically after authentication.</p>
            <ul class="feature-list">
                <li>Secure password verification</li>
                <li>Session-based authentication</li>
                <li>Role-based dashboard routing</li>
            </ul>
        </section>

        <section class="auth-card" aria-labelledby="login-title">
            <div class="card-header">
                <h2 id="login-title">Sign in</h2>
                <p>Use the email and password you registered with.</p>
            </div>

            <?php if ($successMessage !== ''): ?>
                <div class="alert success" role="status"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
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

            <form class="register-form" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo old('email', $formData); ?>" placeholder="you@example.com" autocomplete="email" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                </div>

                <button type="submit" class="submit-btn">Sign in</button>
            </form>

            <p class="auth-links">
                Need an account? <a href="register.php">Register here</a>.
            </p>
        </section>
    </main>
</body>
</html>
