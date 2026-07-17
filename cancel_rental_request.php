<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Tenant']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request method.';
    header('Location: tenant_dashboard.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Session expired. Please try again.';
    header('Location: tenant_dashboard.php');
    exit;
}

$requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;

if ($requestId <= 0) {
    $_SESSION['flash_error'] = 'Invalid request ID.';
    header('Location: tenant_dashboard.php');
    exit;
}

try {
    $tenantId = (int) $_SESSION['user_id'];

    // Verify it is pending and belongs to the tenant
    $stmt = $pdo->prepare('SELECT id FROM rental_requests WHERE id = :id AND tenant_id = :tenant_id AND status = "Pending" LIMIT 1');
    $stmt->execute(['id' => $requestId, 'tenant_id' => $tenantId]);
    
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = 'Could not cancel request. It may have already been handled by the landlord.';
        header('Location: tenant_dashboard.php');
        exit;
    }

    // Delete request
    $deleteStmt = $pdo->prepare('DELETE FROM rental_requests WHERE id = :id');
    $deleteStmt->execute(['id' => $requestId]);

    $_SESSION['flash_success'] = 'Your rental request has been cancelled.';

} catch (Exception $e) {
    error_log('Failed to cancel rental request: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'An error occurred. Please try again.';
}

header('Location: tenant_dashboard.php');
exit;
