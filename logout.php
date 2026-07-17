<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSecureSession();

$role = currentRole() ?: 'Tenant';
$redirectTarget = get_dashboard_url($role);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectTarget);
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');

if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Logout request could not be verified. Please try again.';
    header('Location: ' . $redirectTarget);
    exit;
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params['path'],
        $params['domain'],
        (bool) $params['secure'],
        (bool) $params['httponly']
    );
}

session_destroy();

header('Location: login.php?logout=1');
exit;
