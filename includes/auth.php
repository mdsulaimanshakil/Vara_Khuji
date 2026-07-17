<?php

declare(strict_types=1);

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('vara_khuji_session');
    session_start();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentRole(): ?string
{
    return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : null;
}

/**
 * Ensures the user is logged in. If not, redirects to the login page.
 */
function require_login(): void
{
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'You must log in to access this page.';
        header('Location: login.php');
        exit;
    }
}

/**
 * Ensures the logged-in user has one of the allowed roles.
 * If not, displays an unauthorized access message or redirects.
 * 
 * @param array $allowedRoles Array of allowed roles (e.g. ['Tenant', 'Landlord', 'Admin'])
 */
function require_role(array $allowedRoles): void
{
    require_login();

    $userRole = currentRole() ?? '';

    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied | HRMS</title>
            <link rel="stylesheet" href="assets/css/style.css">
        </head>
        <body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background-color: #0f172a; font-family: system-ui, -apple-system, sans-serif;">
            <div style="background-color: #1e293b; color: #f1f5f9; padding: 2.5rem; border-radius: 12px; max-width: 480px; width: 90%; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3); border: 1px solid #334155; text-align: center;">
                <div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;">⚠️</div>
                <h1 style="font-size: 1.5rem; margin: 0 0 0.5rem; font-weight: 700;">Access Denied</h1>
                <p style="color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin: 0 0 2rem;">
                    You do not have the required permissions to access this page. Your role is <strong><?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?></strong>.
                </p>
                <a href="<?php echo htmlspecialchars(get_dashboard_url($userRole), ENT_QUOTES, 'UTF-8'); ?>" style="display: inline-block; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: transform 0.2s;">
                    Go to Dashboard
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Returns the correct dashboard URL for a given role.
 */
function get_dashboard_url(string $role): string
{
    switch ($role) {
        case 'Admin':
            return 'admin_dashboard.php';
        case 'Landlord':
            return 'landlord_dashboard.php';
        case 'Tenant':
        default:
            return 'tenant_dashboard.php';
    }
}

/**
 * Redirects the user to their appropriate dashboard based on their role.
 */
function redirect_by_role(string $role): void
{
    header('Location: ' . get_dashboard_url($role));
    exit;
}

function redirectIfLoggedIn(): void
{
    if (isLoggedIn()) {
        redirect_by_role(currentRole() ?? 'Tenant');
    }
}
