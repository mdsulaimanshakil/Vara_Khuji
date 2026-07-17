<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Landlord']);

$errors = [];
$successMessage = '';

$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Verify property ownership
$stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id AND landlord_id = :landlord_id LIMIT 1');
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

// Fetch current images
$imgStmt = $pdo->prepare('SELECT * FROM property_images WHERE property_id = :property_id ORDER BY is_primary DESC, id ASC');
$imgStmt->execute(['property_id' => $propertyId]);
$images = $imgStmt->fetchAll();

// Handle Operations: Set Primary / Delete Image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        $errors[] = 'The form session expired. Please refresh the page and try again.';
    }

    if (!$errors) {
        $action = $_POST['action'];
        $imageId = isset($_POST['image_id']) ? (int) $_POST['image_id'] : 0;

        if ($action === 'set_primary') {
            try {
                $pdo->beginTransaction();
                // Set all images of this property to non-primary
                $clearPrimary = $pdo->prepare('UPDATE property_images SET is_primary = 0 WHERE property_id = :property_id');
                $clearPrimary->execute(['property_id' => $propertyId]);

                // Set selected image to primary
                $setPrimary = $pdo->prepare('UPDATE property_images SET is_primary = 1 WHERE id = :id AND property_id = :property_id');
                $setPrimary->execute(['id' => $imageId, 'property_id' => $propertyId]);

                $pdo->commit();
                $_SESSION['flash_success'] = 'Primary image updated successfully.';
                header("Location: property_edit.php?id=" . $propertyId);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Failed to update primary image.';
            }
        } elseif ($action === 'delete_image') {
            // Find image
            $findImg = $pdo->prepare('SELECT * FROM property_images WHERE id = :id AND property_id = :property_id LIMIT 1');
            $findImg->execute(['id' => $imageId, 'property_id' => $propertyId]);
            $targetImg = $findImg->fetch();

            if ($targetImg) {
                try {
                    $pdo->beginTransaction();

                    // Delete record
                    $deleteRec = $pdo->prepare('DELETE FROM property_images WHERE id = :id');
                    $deleteRec->execute(['id' => $imageId]);

                    // If we deleted the primary image, make another one primary if possible
                    if ($targetImg['is_primary'] == 1) {
                        $nextImg = $pdo->prepare('SELECT id FROM property_images WHERE property_id = :property_id LIMIT 1');
                        $nextImg->execute(['property_id' => $propertyId]);
                        $fallback = $nextImg->fetch();
                        if ($fallback) {
                            $makePrimary = $pdo->prepare('UPDATE property_images SET is_primary = 1 WHERE id = :id');
                            $makePrimary->execute(['id' => $fallback['id']]);
                        }
                    }

                    $pdo->commit();

                    // Delete file from disk
                    $filePath = __DIR__ . '/' . $targetImg['image_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }

                    $_SESSION['flash_success'] = 'Image deleted successfully.';
                    header("Location: property_edit.php?id=" . $propertyId);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Failed to delete image.';
                }
            }
        }
    }
}

// Handle Form Submission (General Update + New Image Uploads)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        $errors[] = 'The form session expired. Please refresh the page and try again.';
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $rent = trim((string) ($_POST['rent'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $bedrooms = trim((string) ($_POST['bedrooms'] ?? ''));
    $bathrooms = trim((string) ($_POST['bathrooms'] ?? ''));
    $area_sqft = trim((string) ($_POST['area_sqft'] ?? ''));
    $availability_status = trim((string) ($_POST['availability_status'] ?? 'Available'));

    if ($title === '') $errors[] = 'Property title is required.';
    if ($description === '') $errors[] = 'Property description is required.';
    if ($rent === '' || !is_numeric($rent) || (float) $rent <= 0) $errors[] = 'Enter a valid rent amount.';
    if ($location === '') $errors[] = 'Property location is required.';
    if ($bedrooms === '' || !ctype_digit($bedrooms)) $errors[] = 'Enter valid bedrooms.';
    if ($bathrooms === '' || !ctype_digit($bathrooms)) $errors[] = 'Enter valid bathrooms.';
    if ($area_sqft !== '' && !ctype_digit($area_sqft)) $errors[] = 'Enter a valid area size.';
    if (!in_array($availability_status, ['Available', 'Rented', 'Unavailable'], true)) $errors[] = 'Invalid availability status.';

    // New Image Uploads
    $uploadedImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        $files = $_FILES['images'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $uploadDir = __DIR__ . '/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            if ($files['size'][$i] > $maxSize) {
                $errors[] = "Image " . htmlspecialchars($files['name'][$i], ENT_QUOTES, 'UTF-8') . " exceeds 5MB size limit.";
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($files['tmp_name'][$i]);
            if (!in_array($mimeType, $allowedTypes, true)) {
                $errors[] = "Image " . htmlspecialchars($files['name'][$i], ENT_QUOTES, 'UTF-8') . " is not a valid format.";
                continue;
            }

            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            if (!$ext) {
                $ext = $mimeType === 'image/webp' ? 'webp' : ($mimeType === 'image/png' ? 'png' : 'jpg');
            }
            $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $newFilename)) {
                $uploadedImages[] = 'uploads/' . $newFilename;
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare(
                'UPDATE properties
                 SET title = :title, description = :description, rent = :rent, location = :location,
                     bedrooms = :bedrooms, bathrooms = :bathrooms, area_sqft = :area_sqft, availability_status = :availability_status
                 WHERE id = :id'
            );

            $update->execute([
                'title' => $title,
                'description' => $description,
                'rent' => $rent,
                'location' => $location,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'area_sqft' => $area_sqft !== '' ? (int) $area_sqft : null,
                'availability_status' => $availability_status,
                'id' => $propertyId,
            ]);

            // Save new images
            if (!empty($uploadedImages)) {
                // If there are currently no images, set the first new one as primary
                $hasImagesStmt = $pdo->prepare('SELECT COUNT(*) FROM property_images WHERE property_id = :property_id');
                $hasImagesStmt->execute(['property_id' => $propertyId]);
                $hasAny = (int) $hasImagesStmt->fetchColumn() > 0;

                $insertImage = $pdo->prepare(
                    'INSERT INTO property_images (property_id, image_path, is_primary, created_at)
                     VALUES (:property_id, :image_path, :is_primary, NOW())'
                );

                foreach ($uploadedImages as $idx => $path) {
                    $insertImage->execute([
                        'property_id' => $propertyId,
                        'image_path' => $path,
                        'is_primary' => (!$hasAny && $idx === 0) ? 1 : 0,
                    ]);
                }
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Property listing updated successfully!';
            header("Location: property_edit.php?id=" . $propertyId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            foreach ($uploadedImages as $path) {
                if (file_exists(__DIR__ . '/' . $path)) {
                    unlink(__DIR__ . '/' . $path);
                }
            }
            error_log('Failed to update property: ' . $e->getMessage());
            $errors[] = 'Failed to save changes. Please try again.';
        }
    }
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Property | HRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2.5rem;
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
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
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
        .image-manager {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .image-card {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid var(--border);
            background: #fff;
        }
        .image-card.primary {
            border-color: var(--accent);
        }
        .image-card img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            display: block;
        }
        .image-actions {
            display: flex;
            justify-content: space-around;
            padding: 0.5rem;
            background: #f1f5f9;
        }
        .action-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0.25rem;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .action-icon-btn:hover {
            background: rgba(0,0,0,0.05);
        }
        .action-icon-btn.primary-badge {
            color: var(--accent);
            font-weight: bold;
            cursor: default;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header-actions">
            <div>
                <h2>Edit Property Listing</h2>
                <p style="color: var(--muted); margin: 0.25rem 0 0;">Update details or manage photos for this property.</p>
            </div>
            <a href="landlord_dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>

        <?php if ($flashSuccess !== ''): ?>
            <div class="alert success" role="status"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert error" role="alert">
                <strong>Please fix the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Images Section -->
        <section class="image-manager">
            <h3>Manage Property Photos</h3>
            <?php if (empty($images)): ?>
                <p style="color: var(--muted); font-style: italic;">No photos uploaded yet.</p>
            <?php else: ?>
                <div class="image-grid">
                    <?php foreach ($images as $img): ?>
                        <div class="image-card <?php echo $img['is_primary'] ? 'primary' : ''; ?>">
                            <img src="<?php echo htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Property Image">
                            <div class="image-actions">
                                <?php if ($img['is_primary']): ?>
                                    <span class="action-icon-btn primary-badge" title="Primary Photo">★ Primary</span>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="set_primary">
                                        <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                        <button type="submit" class="action-icon-btn" style="color: #eab308;" title="Make Primary">☆ Set Primary</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this photo?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="image_id" value="<?php echo $img['id']; ?>">
                                    <button type="submit" class="action-icon-btn" style="color: var(--error);" title="Delete Photo">🗑️ Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- General Details Form -->
        <form class="register-form" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="field">
                <label for="title">Property Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($property['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" style="width: 100%; border-radius: 12px; border: 1px solid var(--border); padding: 0.8rem; font-family: inherit; font-size: 0.95rem; resize: vertical;" required><?php echo htmlspecialchars($property['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="rent">Monthly Rent (BDT)</label>
                    <input type="number" id="rent" name="rent" value="<?php echo htmlspecialchars((string) $property['rent'], ENT_QUOTES, 'UTF-8'); ?>" min="1" step="0.01" required>
                </div>

                <div class="field">
                    <label for="location">Location / Address</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($property['location'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="bedrooms">Bedrooms</label>
                    <input type="number" id="bedrooms" name="bedrooms" value="<?php echo htmlspecialchars((string) $property['bedrooms'], ENT_QUOTES, 'UTF-8'); ?>" min="0" required>
                </div>

                <div class="field">
                    <label for="bathrooms">Bathrooms</label>
                    <input type="number" id="bathrooms" name="bathrooms" value="<?php echo htmlspecialchars((string) $property['bathrooms'], ENT_QUOTES, 'UTF-8'); ?>" min="0" required>
                </div>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="area_sqft">Area (Sq Ft)</label>
                    <input type="number" id="area_sqft" name="area_sqft" value="<?php echo htmlspecialchars((string) ($property['area_sqft'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="field">
                    <label for="availability_status">Availability Status</label>
                    <select id="availability_status" name="availability_status" required>
                        <option value="Available" <?php echo $property['availability_status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Rented" <?php echo $property['availability_status'] === 'Rented' ? 'selected' : ''; ?>>Rented</option>
                        <option value="Unavailable" <?php echo $property['availability_status'] === 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="images">Add More Images</label>
                <input type="file" id="images" name="images[]" multiple accept="image/*" style="border: 1px dashed var(--accent); padding: 1rem; background: rgba(30,124,255,0.02); cursor: pointer; border-radius: 12px;">
            </div>

            <button type="submit" class="submit-btn" style="margin-top: 1.5rem;">Save Changes</button>
        </form>
    </div>
</body>
</html>
