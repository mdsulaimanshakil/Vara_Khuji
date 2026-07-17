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

$propertyId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;

// Verify property exists
$propStmt = $pdo->prepare('SELECT id FROM properties WHERE id = :id LIMIT 1');
$propStmt->execute(['id' => $propertyId]);
if (!$propStmt->fetch()) {
    $_SESSION['flash_error'] = 'Property not found.';
    header('Location: tenant_dashboard.php');
    exit;
}

$tenantId = (int) $_SESSION['user_id'];

try {
    // Check if already favorited
    $checkStmt = $pdo->prepare('SELECT id FROM tenant_favorites WHERE tenant_id = :tenant_id AND property_id = :property_id LIMIT 1');
    $checkStmt->execute(['tenant_id' => $tenantId, 'property_id' => $propertyId]);
    $favorite = $checkStmt->fetch();

    if ($favorite) {
        // Delete favorite
        $deleteStmt = $pdo->prepare('DELETE FROM tenant_favorites WHERE id = :id');
        $deleteStmt->execute(['id' => $favorite['id']]);
        $_SESSION['flash_success'] = 'Property removed from favorites.';
    } else {
        // Add favorite
        $insertStmt = $pdo->prepare('INSERT INTO tenant_favorites (tenant_id, property_id, created_at) VALUES (:tenant_id, :property_id, NOW())');
        $insertStmt->execute(['tenant_id' => $tenantId, 'property_id' => $propertyId]);
        $_SESSION['flash_success'] = 'Property added to favorites!';
    }
} catch (Exception $e) {
    error_log('Failed to toggle favorite: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Operation failed. Please try again.';
}

// Redirect back to referring page if possible, otherwise dashboard
$referer = $_SERVER['HTTP_REFERER'] ?? 'tenant_dashboard.php';
header('Location: ' . $referer);
exit;
