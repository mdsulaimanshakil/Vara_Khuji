<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Landlord']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_error'] = 'Invalid request method.';
    header('Location: landlord_dashboard.php');
    exit;
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Session expired. Please try again.';
    header('Location: landlord_dashboard.php');
    exit;
}

$propertyId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

// Verify property ownership and existence
$stmt = $pdo->prepare('SELECT id FROM properties WHERE id = :id AND landlord_id = :landlord_id LIMIT 1');
$stmt->execute([
    'id' => $propertyId,
    'landlord_id' => $_SESSION['user_id']
]);
$property = $stmt->fetch();

if (!$property) {
    $_SESSION['flash_error'] = 'Property not found or access denied.';
    header('Location: landlord_dashboard.php');
    exit;
}

try {
    // Retrieve all images of the property to delete physical files
    $imgStmt = $pdo->prepare('SELECT image_path FROM property_images WHERE property_id = :property_id');
    $imgStmt->execute(['property_id' => $propertyId]);
    $images = $imgStmt->fetchAll();

    $pdo->beginTransaction();

    // Delete property from database (cascades to property_images)
    $deleteProperty = $pdo->prepare('DELETE FROM properties WHERE id = :id AND landlord_id = :landlord_id');
    $deleteProperty->execute([
        'id' => $propertyId,
        'landlord_id' => $_SESSION['user_id']
    ]);

    $pdo->commit();

    // Delete physical files from disk
    foreach ($images as $img) {
        $filePath = __DIR__ . '/' . $img['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $_SESSION['flash_success'] = 'Property listing deleted successfully.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Failed to delete property: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Failed to delete property. Please try again.';
}

header('Location: landlord_dashboard.php');
exit;
