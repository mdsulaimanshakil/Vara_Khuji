<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();

// Core security gate: restrict access to authenticated Landlords only
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

$requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($requestId <= 0 || !in_array($action, ['Accept', 'Reject'], true)) {
    $_SESSION['flash_error'] = 'Invalid request parameters.';
    header('Location: landlord_dashboard.php');
    exit;
}

try {
    // Retrieve request and confirm it belongs to a property owned by the landlord
    $stmt = $pdo->prepare('
        SELECT r.*, p.landlord_id, p.title, p.availability_status 
        FROM rental_requests r 
        JOIN properties p ON r.property_id = p.id 
        WHERE r.id = :id 
        LIMIT 1
    ');
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $_SESSION['flash_error'] = 'Rental request not found.';
        header('Location: landlord_dashboard.php');
        exit;
    }

    if ((int) $request['landlord_id'] !== (int) $_SESSION['user_id']) {
        $_SESSION['flash_error'] = 'Access Denied: You do not own the property associated with this request.';
        header('Location: landlord_dashboard.php');
        exit;
    }

    if ($action === 'Accept') {
        // Start transaction to update both request and property availability status
        $pdo->beginTransaction();

        $updateReq = $pdo->prepare('UPDATE rental_requests SET status = "Accepted" WHERE id = :id');
        $updateReq->execute(['id' => $requestId]);

        $updateProp = $pdo->prepare('UPDATE properties SET availability_status = "Booked" WHERE id = :property_id');
        $updateProp->execute(['property_id' => $request['property_id']]);

        // Auto-reject any other pending requests for this property
        $rejectOthers = $pdo->prepare('UPDATE rental_requests SET status = "Rejected" WHERE property_id = :property_id AND id != :id AND status = "Pending"');
        $rejectOthers->execute(['property_id' => $request['property_id'], 'id' => $requestId]);

        $pdo->commit();
        $_SESSION['flash_success'] = 'Rental request accepted! The property status has been updated to Booked.';
    } else {
        $updateReq = $pdo->prepare('UPDATE rental_requests SET status = "Rejected" WHERE id = :id');
        $updateReq->execute(['id' => $requestId]);
        $_SESSION['flash_success'] = 'Rental request rejected successfully.';
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Failed to handle rental request: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'An error occurred while updating the request. Please try again.';
}

header('Location: landlord_dashboard.php');
exit;
