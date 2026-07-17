<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request method.';
    header('Location: admin_dashboard.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Session expired. Please try again.';
    header('Location: admin_dashboard.php');
    exit;
}

$propertyId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$action = trim((string) ($_POST['status_action'] ?? ''));

if ($propertyId <= 0 || !in_array($action, ['Approved', 'Rejected'], true)) {
    $_SESSION['flash_error'] = 'Invalid property listing or status action.';
    header('Location: admin_dashboard.php');
    exit;
}

try {
    // Verify listing existence
    $checkStmt = $pdo->prepare('SELECT id FROM properties WHERE id = :id LIMIT 1');
    $checkStmt->execute(['id' => $propertyId]);
    if (!$checkStmt->fetch()) {
        $_SESSION['flash_error'] = 'Property listing not found.';
        header('Location: admin_dashboard.php');
        exit;
    }

    // Update status
    $updateStmt = $pdo->prepare('UPDATE properties SET verification_status = :status WHERE id = :id');
    $updateStmt->execute(['status' => $action, 'id' => $propertyId]);

    $_SESSION['flash_success'] = 'Property listing status updated to ' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . ' successfully.';
} catch (Exception $e) {
    error_log('Failed to verify listing: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Failed to update property verification status.';
}

header('Location: admin_dashboard.php');
exit;
