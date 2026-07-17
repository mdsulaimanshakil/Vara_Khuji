<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Landlord']);

$errors = [];
$successMessage = '';

$formData = [
    'title' => '',
    'description' => '',
    'rent' => '',
    'location' => '',
    'bedrooms' => '',
    'bathrooms' => '',
    'area_sqft' => '',
    'availability_status' => 'Available',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        $errors[] = 'The form session expired. Please refresh the page and try again.';
    }

    $formData['title'] = trim((string) ($_POST['title'] ?? ''));
    $formData['description'] = trim((string) ($_POST['description'] ?? ''));
    $formData['rent'] = trim((string) ($_POST['rent'] ?? ''));
    $formData['location'] = trim((string) ($_POST['location'] ?? ''));
    $formData['bedrooms'] = trim((string) ($_POST['bedrooms'] ?? ''));
    $formData['bathrooms'] = trim((string) ($_POST['bathrooms'] ?? ''));
    $formData['area_sqft'] = trim((string) ($_POST['area_sqft'] ?? ''));
    $formData['availability_status'] = trim((string) ($_POST['availability_status'] ?? 'Available'));

    // Validation
    if ($formData['title'] === '') {
        $errors[] = 'Property title is required.';
    }
    if ($formData['description'] === '') {
        $errors[] = 'Property description is required.';
    }
    if ($formData['rent'] === '' || !is_numeric($formData['rent']) || (float) $formData['rent'] <= 0) {
        $errors[] = 'Enter a valid rent amount greater than 0.';
    }
    if ($formData['location'] === '') {
        $errors[] = 'Property location is required.';
    }
    if ($formData['bedrooms'] === '' || !ctype_digit($formData['bedrooms'])) {
        $errors[] = 'Enter a valid number of bedrooms.';
    }
    if ($formData['bathrooms'] === '' || !ctype_digit($formData['bathrooms'])) {
        $errors[] = 'Enter a valid number of bathrooms.';
    }
    if ($formData['area_sqft'] !== '' && !ctype_digit($formData['area_sqft'])) {
        $errors[] = 'Enter a valid area size in sqft.';
    }
    if (!in_array($formData['availability_status'], ['Available', 'Booked', 'Unavailable'], true)) {
        $errors[] = 'Select a valid availability status.';
    }

    // Image Upload Validation
    $uploadedImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        $files = $_FILES['images'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = "Error uploading image: " . htmlspecialchars($files['name'][$i], ENT_QUOTES, 'UTF-8');
                }
                continue;
            }

            if ($files['size'][$i] > $maxSize) {
                $errors[] = "Image " . htmlspecialchars($files['name'][$i], ENT_QUOTES, 'UTF-8') . " exceeds maximum size of 5MB.";
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($files['tmp_name'][$i]);
            if (!in_array($mimeType, $allowedTypes, true)) {
                $errors[] = "Image " . htmlspecialchars($files['name'][$i], ENT_QUOTES, 'UTF-8') . " must be a JPEG, PNG, GIF, or WebP file.";
                continue;
            }

            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            if (!$ext) {
                $ext = $mimeType === 'image/webp' ? 'webp' : ($mimeType === 'image/png' ? 'png' : 'jpg');
            }
            $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
            $destPath = $uploadDir . $newFilename;

            if (move_uploaded_file($files['tmp_name'][$i], $destPath)) {
                $uploadedImages[] = 'uploads/' . $newFilename;
            } else {
                $errors[] = "Failed to save image: " . htmlspecialchars($files['name'][$i], ENT_QUOTES, 'UTF-8');
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $insertProperty = $pdo->prepare(
                'INSERT INTO properties (landlord_id, title, description, rent, location, bedrooms, bathrooms, area_sqft, availability_status, created_at)
                 VALUES (:landlord_id, :title, :description, :rent, :location, :bedrooms, :bathrooms, :area_sqft, :availability_status, NOW())'
            );

            $insertProperty->execute([
                'landlord_id' => $_SESSION['user_id'],
                'title' => $formData['title'],
                'description' => $formData['description'],
                'rent' => $formData['rent'],
                'location' => $formData['location'],
                'bedrooms' => $formData['bedrooms'],
                'bathrooms' => $formData['bathrooms'],
                'area_sqft' => $formData['area_sqft'] !== '' ? (int) $formData['area_sqft'] : null,
                'availability_status' => $formData['availability_status'],
            ]);

            $propertyId = (int) $pdo->lastInsertId();

            if (!empty($uploadedImages)) {
                $insertImage = $pdo->prepare(
                    'INSERT INTO property_images (property_id, image_path, is_primary, created_at)
                     VALUES (:property_id, :image_path, :is_primary, NOW())'
                );

                foreach ($uploadedImages as $index => $imagePath) {
                    $insertImage->execute([
                        'property_id' => $propertyId,
                        'image_path' => $imagePath,
                        'is_primary' => $index === 0 ? 1 : 0, // First image is primary
                    ]);
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Property added successfully!';
            header('Location: landlord_dashboard.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            // Delete uploaded files on transaction rollback
            foreach ($uploadedImages as $imagePath) {
                if (file_exists(__DIR__ . '/' . $imagePath)) {
                    unlink(__DIR__ . '/' . $imagePath);
                }
            }
            error_log('Failed to create property: ' . $e->getMessage());
            $errors[] = 'Failed to save property. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Property | HRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .back-btn {
            background: #475569;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .back-btn:hover {
            background: #334155;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header-actions">
            <h2>Add New Property Listing</h2>
            <a href="landlord_dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>

        <?php if ($errors): ?>
            <div class="alert error" role="alert">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="register-form" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field">
                <label for="title">Property Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Cozy 2 Bedroom Apartment in Dhanmondi" required>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" style="width: 100%; border-radius: 12px; border: 1px solid var(--border); padding: 0.8rem; font-family: inherit; font-size: 0.95rem; resize: vertical;" placeholder="Detailed description of the property, amenities, utilities, etc." required><?php echo htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="rent">Monthly Rent (BDT)</label>
                    <input type="number" id="rent" name="rent" value="<?php echo htmlspecialchars($formData['rent'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 25000" min="1" step="0.01" required>
                </div>

                <div class="field">
                    <label for="location">Location / Address</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($formData['location'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. House 42, Road 10, Dhanmondi" required>
                </div>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="bedrooms">Bedrooms</label>
                    <input type="number" id="bedrooms" name="bedrooms" value="<?php echo htmlspecialchars($formData['bedrooms'], ENT_QUOTES, 'UTF-8'); ?>" min="0" placeholder="e.g. 2" required>
                </div>

                <div class="field">
                    <label for="bathrooms">Bathrooms</label>
                    <input type="number" id="bathrooms" name="bathrooms" value="<?php echo htmlspecialchars($formData['bathrooms'], ENT_QUOTES, 'UTF-8'); ?>" min="0" placeholder="e.g. 2" required>
                </div>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="area_sqft">Area (Sq Ft)</label>
                    <input type="number" id="area_sqft" name="area_sqft" value="<?php echo htmlspecialchars($formData['area_sqft'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 1200">
                </div>

                <div class="field">
                    <label for="availability_status">Availability Status</label>
                    <select id="availability_status" name="availability_status" required>
                        <option value="Available" <?php echo $formData['availability_status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Booked" <?php echo $formData['availability_status'] === 'Booked' ? 'selected' : ''; ?>>Booked</option>
                        <option value="Unavailable" <?php echo $formData['availability_status'] === 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="images">Property Images (Multiple Allowed, Max 5MB each)</label>
                <input type="file" id="images" name="images[]" multiple accept="image/*" style="border: 1px dashed var(--accent); padding: 1.5rem; text-align: center; background: rgba(30,124,255,0.02); cursor: pointer; border-radius: 12px;">
            </div>

            <button type="submit" class="submit-btn" style="margin-top: 1rem;">Publish Property</button>
        </form>
    </div>
</body>
</html>
