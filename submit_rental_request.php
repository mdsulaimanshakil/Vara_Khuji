<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Tenant']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request method.';
    header('Location: properties.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Session expired. Please try again.';
    header('Location: properties.php');
    exit;
}

$propertyId = isset($_POST['property_id']) ? (int) $_POST['property_id'] : 0;
$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

if ($propertyId <= 0) {
    $_SESSION['flash_error'] = 'Invalid property listing.';
    header('Location: properties.php');
    exit;
}

// 1. Verify property exists and is available
try {
    $propStmt = $pdo->prepare('SELECT id, availability_status FROM properties WHERE id = :id LIMIT 1');
    $propStmt->execute(['id' => $propertyId]);
    $property = $propStmt->fetch();

    if (!$property) {
        $_SESSION['flash_error'] = 'Property not found.';
        header('Location: properties.php');
        exit;
    }

    if ($property['availability_status'] !== 'Available') {
        $_SESSION['flash_error'] = 'This property is no longer available for rent.';
        header('Location: property_detail.php?id=' . $propertyId);
        exit;
    }

    $tenantId = (int) $_SESSION['user_id'];

    // 2. Check if a request already exists
    $checkStmt = $pdo->prepare('SELECT id FROM rental_requests WHERE tenant_id = :tenant_id AND property_id = :property_id LIMIT 1');
    $checkStmt->execute(['tenant_id' => $tenantId, 'property_id' => $propertyId]);
    if ($checkStmt->fetch()) {
        $_SESSION['flash_error'] = 'You have already submitted a rental request for this property.';
        header('Location: property_detail.php?id=' . $propertyId);
        exit;
    }

    // 3. Insert the rental request
    $insertStmt = $pdo->prepare('
        INSERT INTO rental_requests (tenant_id, property_id, message, status, created_at) 
        VALUES (:tenant_id, :property_id, :message, "Pending", NOW())
    ');
    $insertStmt->execute([
        'tenant_id' => $tenantId,
        'property_id' => $propertyId,
        'message' => $message !== '' ? $message : null
    ]);

    $_SESSION['flash_success'] = 'Your rental request has been submitted successfully! The landlord has been notified.';

} catch (Exception $e) {
    error_log('Failed to submit rental request: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not submit request. Please try again.';
}

header('Location: property_detail.php?id=' . $propertyId);
exit;
