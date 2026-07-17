<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();

$propertyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($propertyId <= 0) {
    $_SESSION['flash_error'] = 'Please select a valid property listing.';
    header('Location: properties.php');
    exit;
}

$propertyStmt = $pdo->prepare(
    'SELECT p.*, u.full_name AS landlord_name, u.email AS landlord_email, u.phone AS landlord_phone
     FROM properties p
     LEFT JOIN users u ON p.landlord_id = u.id
     WHERE p.id = :id
     LIMIT 1'
);
$propertyStmt->execute(['id' => $propertyId]);
$property = $propertyStmt->fetch();

if (!$property) {
    http_response_code(404);
}

$imageStmt = $pdo->prepare('SELECT image_path, is_primary FROM property_images WHERE property_id = :property_id ORDER BY is_primary DESC, id ASC');
$imageStmt->execute(['property_id' => $propertyId]);
$images = $imageStmt->fetchAll();

$primaryImage = null;
foreach ($images as $image) {
    if ((int) $image['is_primary'] === 1) {
        $primaryImage = $image['image_path'];
        break;
    }
}

if ($primaryImage === null && !empty($images)) {
    $primaryImage = $images[0]['image_path'];
}

$availability = $property['availability_status'] ?? 'Unavailable';
$availabilityClass = strtolower((string) $availability);
$hasArea = isset($property['area_sqft']) && $property['area_sqft'] !== null && $property['area_sqft'] !== '';
$backUrl = isLoggedIn() ? get_dashboard_url(currentRole() ?? 'Tenant') : 'properties.php';

$hasRequested = false;
$requestStatus = '';
$requestMessage = '';
if (isLoggedIn() && currentRole() === 'Tenant') {
    $reqStmt = $pdo->prepare('SELECT status, message FROM rental_requests WHERE tenant_id = :tenant_id AND property_id = :property_id LIMIT 1');
    $reqStmt->execute([
        'tenant_id' => $_SESSION['user_id'],
        'property_id' => $propertyId
    ]);
    $req = $reqStmt->fetch();
    if ($req) {
        $hasRequested = true;
        $requestStatus = $req['status'];
        $requestMessage = $req['message'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $property ? htmlspecialchars($property['title'], ENT_QUOTES, 'UTF-8') . ' | Property Details' : 'Property Not Found | HRMS'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .detail-shell {
            position: relative;
            z-index: 1;
            max-width: 1240px;
            margin: 0 auto;
            padding: clamp(1.25rem, 3vw, 2.5rem);
        }
        .detail-topbar {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .back-link {
            color: #e8f1ff;
            text-decoration: none;
            font-weight: 700;
            padding: 0.7rem 1rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(12px);
        }
        .back-link:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        .detail-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(320px, 0.95fr);
            gap: 1.5rem;
            align-items: start;
        }
        .gallery-card,
        .info-card,
        .section-card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(16, 32, 51, 0.12);
            border-radius: 24px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }
        .gallery-card {
            overflow: hidden;
        }
        .hero-image-wrap {
            position: relative;
            background: linear-gradient(135deg, #102033, #1e3a5f);
        }
        .hero-image {
            display: block;
            width: 100%;
            height: clamp(280px, 56vw, 520px);
            object-fit: cover;
            background: #dfe8f4;
        }
        .gallery-empty {
            display: grid;
            place-items: center;
            height: clamp(280px, 56vw, 520px);
            color: rgba(255, 255, 255, 0.72);
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .gallery-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
            gap: 0.75rem;
            padding: 1rem;
            background: rgba(16, 32, 51, 0.94);
        }
        .gallery-thumb {
            width: 100%;
            height: 84px;
            object-fit: cover;
            border-radius: 14px;
            border: 2px solid transparent;
            cursor: pointer;
            opacity: 0.75;
            transition: transform 0.2s ease, opacity 0.2s ease, border-color 0.2s ease;
        }
        .gallery-thumb:hover,
        .gallery-thumb.active {
            transform: translateY(-2px);
            opacity: 1;
            border-color: rgba(255, 255, 255, 0.92);
        }
        .info-card {
            padding: 1.5rem;
            display: grid;
            gap: 1rem;
        }
        .eyebrow {
            display: inline-flex;
            align-self: start;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            background: rgba(30, 124, 255, 0.12);
            color: var(--accent-strong);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .title-block h1 {
            margin: 0.4rem 0 0.5rem;
            font-size: clamp(1.9rem, 3vw, 3rem);
            line-height: 1.05;
        }
        .title-block p {
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.6;
        }
        .price-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }
        .price-badge {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--accent-strong);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .status-badge.available {
            background: #d1fae5;
            color: #065f46;
        }
        .status-badge.booked {
            background: #fef3c7;
            color: #92400e;
        }
        .status-badge.unavailable {
            background: #fee2e2;
            color: #991b1b;
        }
        .spec-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }
        .spec-item {
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 0.95rem 1rem;
            background: #f8fbff;
        }
        .spec-label {
            display: block;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 0.3rem;
        }
        .spec-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
        }
        .section-card {
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        .section-card h2 {
            margin: 0 0 1rem;
            font-size: 1.25rem;
        }
        .description {
            margin: 0;
            color: var(--text);
            line-height: 1.8;
            white-space: pre-line;
        }
        .amenity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.8rem;
        }
        .amenity {
            padding: 0.95rem 1rem;
            border-radius: 16px;
            background: #f8fbff;
            border: 1px solid var(--border);
            color: var(--text);
            font-weight: 700;
        }
        .landlord-card {
            display: grid;
            gap: 0.9rem;
        }
        .landlord-avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
        }
        .landlord-row {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .landlord-name {
            margin: 0 0 0.25rem;
            font-size: 1.1rem;
            font-weight: 800;
        }
        .landlord-meta {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }
        .detail-footer {
            margin-top: 1.5rem;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .footer-chip {
            border-radius: 18px;
            padding: 1rem;
            background: rgba(16, 32, 51, 0.08);
            color: var(--text);
            font-weight: 700;
        }
        .not-found {
            max-width: 680px;
            margin: 6rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(16, 32, 51, 0.12);
            border-radius: 24px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        .not-found h1 {
            margin-top: 0;
        }
        @media (max-width: 920px) {
            .detail-hero {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .detail-topbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .spec-grid {
                grid-template-columns: 1fr;
            }
            .info-card,
            .section-card {
                padding: 1.15rem;
            }
        }
    </style>
</head>
<body>
    <div class="detail-shell">
        <div class="detail-topbar">
            <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>" class="back-link">← Back</a>
            <a href="properties.php" class="back-link">Browse More Listings</a>
        </div>

        <!-- Flash messages -->
        <?php 
        $flashSuccess = $_SESSION['flash_success'] ?? '';
        unset($_SESSION['flash_success']);
        $flashError = $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_error']);
        ?>
        <?php if ($flashSuccess !== ''): ?>
            <div style="background: rgba(16, 185, 129, 0.15); color: #065f46; border: 1px solid rgba(16, 185, 129, 0.3); padding: 1rem; border-radius: 12px; font-weight: 600; margin-bottom: 1.5rem;" role="status">
                <?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div style="background: rgba(239, 68, 68, 0.15); color: #991b1b; border: 1px solid rgba(239, 68, 68, 0.3); padding: 1rem; border-radius: 12px; font-weight: 600; margin-bottom: 1.5rem;" role="alert">
                <?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!$property): ?>
            <div class="not-found">
                <h1>Property not found</h1>
                <p style="color: var(--muted); margin: 0 0 1.5rem; line-height: 1.7;">The listing you tried to open no longer exists or was removed.</p>
                <a href="properties.php" class="back-link" style="color: var(--text); background: #eef4ff; border-color: rgba(16, 32, 51, 0.1);">Return to listings</a>
            </div>
        <?php else: ?>
            <section class="detail-hero">
                <div class="gallery-card">
                    <div class="hero-image-wrap">
                        <?php if ($primaryImage !== null): ?>
                            <img src="<?php echo htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($property['title'], ENT_QUOTES, 'UTF-8'); ?>" class="hero-image" id="hero-image">
                        <?php else: ?>
                            <div class="gallery-empty" id="hero-image">No photos uploaded yet</div>
                        <?php endif; ?>
                    </div>

                    <div class="gallery-strip" aria-label="Property photo gallery">
                        <?php if (!empty($images)): ?>
                            <?php foreach ($images as $index => $image): ?>
                                <img
                                    src="<?php echo htmlspecialchars($image['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars($property['title'], ENT_QUOTES, 'UTF-8') . ' photo ' . ($index + 1); ?>"
                                    class="gallery-thumb<?php echo (($primaryImage !== null && $image['image_path'] === $primaryImage) || ($primaryImage === null && $index === 0)) ? ' active' : ''; ?>"
                                    data-full="<?php echo htmlspecialchars($image['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                >
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="color: rgba(255,255,255,0.7); font-weight: 600; padding: 0.5rem 0;">Photo gallery will appear here once the landlord uploads images.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="info-card">
                    <div class="title-block">
                        <span class="eyebrow"><?php echo htmlspecialchars((string) $property['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <h1><?php echo htmlspecialchars((string) $property['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p><?php echo htmlspecialchars((string) $property['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="price-row">
                        <div class="price-badge"><?php echo number_format((float) $property['rent']); ?> BDT / month</div>
                        <span class="status-badge <?php echo htmlspecialchars($availabilityClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $availability, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="spec-grid">
                        <div class="spec-item">
                            <span class="spec-label">Bedrooms</span>
                            <span class="spec-value"><?php echo htmlspecialchars((string) $property['bedrooms'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Bathrooms</span>
                            <span class="spec-value"><?php echo htmlspecialchars((string) $property['bathrooms'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Area</span>
                            <span class="spec-value"><?php echo $hasArea ? htmlspecialchars((string) $property['area_sqft'], ENT_QUOTES, 'UTF-8') . ' Sq Ft' : 'Not specified'; ?></span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Listed</span>
                            <span class="spec-value"><?php echo htmlspecialchars(date('M j, Y', strtotime((string) $property['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>

                    <div class="landlord-card section-card" style="margin-top: 0;">
                        <h2 style="margin-bottom: 0.2rem;">Landlord Information</h2>
                        <div class="landlord-row">
                            <div class="landlord-avatar"><?php echo strtoupper(substr((string) ($property['landlord_name'] ?? 'L'), 0, 1)); ?></div>
                            <div>
                                <p class="landlord-name"><?php echo htmlspecialchars((string) ($property['landlord_name'] ?? 'Unknown landlord'), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="landlord-meta">Email: <?php echo htmlspecialchars((string) ($property['landlord_email'] ?? 'Not available'), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="landlord-meta">Phone: <?php echo htmlspecialchars((string) ($property['landlord_phone'] ?? 'Not available'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    </div>

                    <?php if (isLoggedIn() && currentRole() === 'Tenant'): ?>
                        <div class="rental-request-card section-card" style="margin-top: 1.5rem;">
                            <h2 style="margin-bottom: 0.5rem; color: var(--text);">Rental Application</h2>
                            
                            <?php if ($hasRequested): ?>
                                <div style="padding: 1rem; border-radius: 12px; font-weight: 700; margin-bottom: 1rem; text-align: center; <?php 
                                    if ($requestStatus === 'Accepted') echo 'background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3);';
                                    elseif ($requestStatus === 'Rejected') echo 'background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3);';
                                    else echo 'background: rgba(59, 130, 246, 0.15); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3);';
                                ?>">
                                    Status: <?php echo htmlspecialchars($requestStatus, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php if ($requestMessage !== ''): ?>
                                    <p style="font-size: 0.85rem; color: var(--muted); margin: 0; line-height: 1.5;">
                                        <strong>Your message:</strong><br>
                                        <em>"<?php echo htmlspecialchars($requestMessage, ENT_QUOTES, 'UTF-8'); ?>"</em>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($availability === 'Available'): ?>
                                    <p style="color: var(--muted); font-size: 0.85rem; line-height: 1.5; margin: 0 0 1rem;">
                                        Interested in this property? Express your interest. Include an optional message to introduce yourself to the landlord.
                                    </p>
                                    <form action="submit_rental_request.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="property_id" value="<?php echo $propertyId; ?>">
                                        <div style="margin-bottom: 1rem;">
                                            <textarea name="message" placeholder="Introduce yourself, move-in date, occupancy details..." style="width: 100%; min-height: 90px; padding: 0.7rem 0.9rem; border-radius: 8px; border: 1px solid var(--border); font-family: inherit; font-size: 0.85rem; resize: vertical; box-sizing: border-box;"></textarea>
                                        </div>
                                        <button type="submit" class="btn-action" style="width: 100%; border: none; cursor: pointer; margin-top: 0; padding: 0.8rem; border-radius: 8px;">Express Interest</button>
                                    </form>
                                <?php else: ?>
                                    <div style="padding: 1rem; border-radius: 12px; background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); font-weight: 700; text-align: center; font-size: 0.9rem;">
                                        This property is currently <?php echo htmlspecialchars($availability, ENT_QUOTES, 'UTF-8'); ?>.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </section>

            <section class="section-card">
                <h2>Property Overview</h2>
                <p class="description"><?php echo htmlspecialchars((string) $property['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            </section>

            <section class="section-card">
                <h2>Room Details</h2>
                <div class="amenity-grid">
                    <div class="amenity"><?php echo htmlspecialchars((string) $property['bedrooms'], ENT_QUOTES, 'UTF-8'); ?> Bedrooms</div>
                    <div class="amenity"><?php echo htmlspecialchars((string) $property['bathrooms'], ENT_QUOTES, 'UTF-8'); ?> Bathrooms</div>
                    <div class="amenity"><?php echo $hasArea ? htmlspecialchars((string) $property['area_sqft'], ENT_QUOTES, 'UTF-8') . ' Sq Ft' : 'Area not specified'; ?></div>
                    <div class="amenity">Rent: <?php echo number_format((float) $property['rent']); ?> BDT</div>
                    <div class="amenity">Status: <?php echo htmlspecialchars((string) $availability, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="amenity">Location: <?php echo htmlspecialchars((string) $property['location'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </section>

            <div class="detail-footer">
                <div class="footer-chip">Rent: <?php echo number_format((float) $property['rent']); ?> BDT / month</div>
                <div class="footer-chip">Availability: <?php echo htmlspecialchars((string) $availability, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="footer-chip">Landlord: <?php echo htmlspecialchars((string) ($property['landlord_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>

            <script>
                (function () {
                    let hero = document.getElementById('hero-image');
                    const thumbs = document.querySelectorAll('.gallery-thumb');

                    thumbs.forEach((thumb) => {
                        thumb.addEventListener('click', () => {
                            if (!hero || !thumb.dataset.full) {
                                return;
                            }

                            thumbs.forEach((item) => item.classList.remove('active'));
                            thumb.classList.add('active');

                            if (hero && hero.tagName === 'IMG') {
                                hero.src = thumb.dataset.full;
                                hero.alt = thumb.alt || '';
                                return;
                            }

                            const nextImage = document.createElement('img');
                            nextImage.src = thumb.dataset.full;
                            nextImage.alt = thumb.alt || '';
                            nextImage.className = 'hero-image';
                            nextImage.id = 'hero-image';
                            hero.replaceWith(nextImage);
                            hero = nextImage;
                        });
                    });
                }());
            </script>
        <?php endif; ?>
    </div>
</body>
</html>