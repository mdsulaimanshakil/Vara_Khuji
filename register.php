<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
redirectIfLoggedIn();

if (empty($_SESSION['csrf_token'])) {
    csrfToken();
}

function old(string $field, array $formData): string
{
    return htmlspecialchars($formData[$field] ?? '', ENT_QUOTES, 'UTF-8');
}

function selected(string $field, string $expected, array $formData): string
{
    return (($formData[$field] ?? '') === $expected) ? 'selected' : '';
}

function validatePassword(string $password): array
{
    $passwordErrors = [];

    if (strlen($password) < 8) {
        $passwordErrors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $passwordErrors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $passwordErrors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $passwordErrors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $passwordErrors[] = 'Password must contain at least one special character.';
    }

    return $passwordErrors;
}

$formData = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'Tenant',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken((string) $csrfToken)) {
        $errors[] = 'The form session expired. Please refresh and try again.';
    }

    $formData['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $formData['email'] = trim((string) ($_POST['email'] ?? ''));
    $formData['phone'] = trim((string) ($_POST['phone'] ?? ''));
    $formData['role'] = trim((string) ($_POST['role'] ?? 'Tenant'));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($formData['full_name'] === '' || !preg_match('/^[\p{L}\s.\'-]{3,100}$/u', $formData['full_name'])) {
        $errors[] = 'Enter a valid full name using at least 3 characters.';
    }

    if ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    $normalizedPhone = preg_replace('/\D+/', '', $formData['phone']);
    if ($normalizedPhone === null || strlen($normalizedPhone) < 10 || strlen($normalizedPhone) > 15) {
        $errors[] = 'Enter a valid phone number with 10 to 15 digits.';
    }

    if (!in_array($formData['role'], ['Tenant', 'Landlord'], true)) {
        $errors[] = 'Select a valid role.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } else {
        $errors = array_merge($errors, validatePassword($password));
    }

    if ($confirmPassword === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $lookup = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $lookup->execute(['email' => $formData['email']]);

            if ($lookup->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO users (full_name, email, phone, password_hash, role, created_at)
                     VALUES (:full_name, :email, :phone, :password_hash, :role, NOW())'
                );

                $insert->execute([
                    'full_name' => $formData['full_name'],
                    'email' => $formData['email'],
                    'phone' => $normalizedPhone,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $formData['role'],
                ]);

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $exception) {
            error_log('Registration failed: ' . $exception->getMessage());
            $errors[] = 'We could not create your account right now. Please try again later.';
        }
    }
}

$roleOptions = ['Tenant', 'Landlord'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | House Rental Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script defer src="assets/js/script.js"></script>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-intro">
            <div class="brand-mark">HRMS</div>
            <h1>House Rental Management System</h1>
            <p>Create your account to manage homes, inquiries, and rentals with a fast, secure registration flow.</p>
            <ul class="feature-list">
                <li>Secure password hashing</li>
                <li>Tenant and landlord roles</li>
                <li>Responsive mobile-first layout</li>
            </ul>
        </section>

        <section class="auth-card" aria-labelledby="register-title">
            <div class="card-header">
                <h2 id="register-title">Create account</h2>
                <p>All fields are required.</p>
            </div>

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

            <form class="register-form" id="registerForm" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="field">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo old('full_name', $formData); ?>" placeholder="e.g. Rahim Ahmed" autocomplete="name" required>
                    <span class="field-error" data-error-for="full_name"></span>
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo old('email', $formData); ?>" placeholder="you@example.com" autocomplete="email" required>
                        <span class="field-error" data-error-for="email"></span>
                    </div>

                    <div class="field">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo old('phone', $formData); ?>" placeholder="01XXXXXXXXX" autocomplete="tel" required>
                        <span class="field-error" data-error-for="phone"></span>
                    </div>
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <?php foreach ($roleOptions as $option): ?>
                                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo selected('role', $option, $formData); ?>><?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-error" data-error-for="role"></span>
                    </div>

                    <div class="field password-meter-wrap">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Create a strong password" autocomplete="new-password" required>
                        <div class="meter" aria-hidden="true"><span id="strengthBar"></span></div>
                        <small id="strengthText" class="strength-text">Use 8+ characters with upper, lower, number, and symbol.</small>
                        <span class="field-error" data-error-for="password"></span>
                    </div>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" autocomplete="new-password" required>
                    <span class="field-error" data-error-for="confirm_password"></span>
                </div>

                <button type="submit" class="submit-btn">Register account</button>
            </form>

            <p class="auth-links">
                Already have an account? <a href="login.php">Sign in here</a>.
            </p>
        </section>
    </main>
</body>
</html>
