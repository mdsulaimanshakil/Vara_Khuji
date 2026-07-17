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

function dashboardPathForRole(?string $role): string
{
    return $role === 'Landlord' ? 'landlord-dashboard.php' : 'tenant-dashboard.php';
}

function redirectIfLoggedIn(): void
{
    if (!isLoggedIn()) {
        return;
    }

    header('Location: ' . dashboardPathForRole(currentRole()));
    exit;
}

function requireLogin(?string $role = null): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    if ($role !== null && currentRole() !== $role) {
        header('Location: ' . dashboardPathForRole(currentRole()));
        exit;
    }
}
