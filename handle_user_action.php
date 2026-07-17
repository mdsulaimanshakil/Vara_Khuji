<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request method.';
    header('Location: manage_users.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Session expired. Please try again.';
    header('Location: manage_users.php');
    exit;
}

$targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$action = isset($_POST['user_action']) ? trim((string) $_POST['user_action']) : '';
$adminId = (int) ($_SESSION['user_id'] ?? 0);

if ($targetUserId <= 0 || !in_array($action, ['Suspend', 'Activate', 'Delete'], true)) {
    $_SESSION['flash_error'] = 'Invalid request parameters.';
    header('Location: manage_users.php');
    exit;
}

if ($targetUserId === $adminId) {
    $_SESSION['flash_error'] = 'You cannot modify or delete your own admin account.';
    header('Location: manage_users.php');
    exit;
}

try {
    // 1. Verify target user exists and is not an Admin
    $userStmt = $pdo->prepare('SELECT id, role, full_name FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $targetUserId]);
    $user = $userStmt->fetch();

    if (!$user) {
        $_SESSION['flash_error'] = 'Target user account not found.';
        header('Location: manage_users.php');
        exit;
    }

    if ($user['role'] === 'Admin') {
        $_SESSION['flash_error'] = 'Administrative accounts cannot be modified or suspended from this module.';
        header('Location: manage_users.php');
        exit;
    }

    $userName = htmlspecialchars((string) $user['full_name'], ENT_QUOTES, 'UTF-8');

    // 2. Perform the database actions
    if ($action === 'Suspend') {
        $stmt = $pdo->prepare('UPDATE users SET status = "Suspended" WHERE id = :id');
        $stmt->execute(['id' => $targetUserId]);
        $_SESSION['flash_success'] = "Account for user '{$userName}' has been suspended.";
    } elseif ($action === 'Activate') {
        $stmt = $pdo->prepare('UPDATE users SET status = "Active" WHERE id = :id');
        $stmt->execute(['id' => $targetUserId]);
        $_SESSION['flash_success'] = "Account for user '{$userName}' has been reactivated.";
    } elseif ($action === 'Delete') {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $targetUserId]);
        $_SESSION['flash_success'] = "Account for user '{$userName}' has been permanently deleted.";
    }

} catch (PDOException $e) {
    error_log('User management action failed: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database transaction failed. Please try again.';
}

header('Location: manage_users.php');
exit;
