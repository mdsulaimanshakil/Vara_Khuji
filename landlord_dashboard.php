<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Protect page and ensure user has 'Landlord' role
startSecureSession();
require_role(['Landlord']);

$userName = $_SESSION['user_name'] ?? 'Landlord';
$userEmail = $_SESSION['user_email'] ?? '';

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// Fetch landlord properties with their primary image
$stmt = $pdo->prepare(
    'SELECT p.*, pi.image_path 
     FROM properties p 
     LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
     WHERE p.landlord_id = :landlord_id 
     ORDER BY p.created_at DESC'
);
$stmt->execute(['landlord_id' => $_SESSION['user_id']]);
$properties = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Dashboard | HRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            color: #fff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .user-info h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
        }
        .user-info p {
            margin: 0.25rem 0 0;
            color: #94a3b8;
            font-size: 0.95rem;
        }
        .role-badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.5rem;
        }
        .header-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .logout-form {
            display: inline;
        }
        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2.5rem 0 1.5rem;
        }
        .add-property-btn {
            background: var(--accent);
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .add-property-btn:hover {
            background: var(--accent-strong);
        }
        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }
        .property-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .property-card:hover {
            transform: translateY(-5px);
        }
        .property-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background-color: #f1f5f9;
        }
        .property-content {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .property-rent {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--accent);
            margin: 0;
        }
        .property-rent span {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: normal;
        }
        .property-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: var(--text);
        }
        .property-location {
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 1rem;
        }
        .property-specs {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--muted);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 0;
            margin-bottom: 1rem;
        }
        .property-card-link {
            color: inherit;
            text-decoration: none;
            display: block;
        }
        .property-card-link:hover .property-card {
            transform: translateY(-5px);
        }
        .view-details-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 1rem;
            width: 100%;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: white;
            font-weight: 700;
            text-decoration: none;
        }
        .status-badge {
            align-self: flex-start;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
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
        .property-actions {
            margin-top: auto;
            display: flex;
            gap: 0.75rem;
        }
        .btn-edit, .btn-delete {
            flex: 1;
            text-align: center;
            padding: 0.55rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-edit {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .btn-edit:hover {
            background: #e2e8f0;
        }
        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fca5a5;
        }
        .btn-delete:hover {
            background: #fecaca;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="user-info">
                <h1>Welcome back, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                <p><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="role-badge">Landlord</span>
            </div>
            <div class="header-buttons">
                <form action="logout.php" method="post" class="logout-form" onsubmit="return confirm('Are you sure you want to log out?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="logout-btn">Log Out</button>
                </form>
            </div>
        </header>

        <!-- Flash messages -->
        <?php if ($flashSuccess !== ''): ?>
            <div class="alert success" role="status"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="alert error" role="alert"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="section-header">
            <h2>Your Properties</h2>
            <a href="property_create.php" class="add-property-btn">+ Add Property</a>
        </div>

        <?php if (empty($properties)): ?>
            <div style="background: white; border-radius: 16px; padding: 3rem; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow);">
                <p style="color: var(--muted); font-size: 1.1rem; margin-bottom: 1.5rem;">You haven't listed any properties yet.</p>
                <a href="property_create.php" class="add-property-btn">Publish Your First Property</a>
            </div>
        <?php else: ?>
            <main class="property-grid">
                <?php foreach ($properties as $prop): ?>
                    <div class="property-card">
                        <a href="property_detail.php?id=<?php echo $prop['id']; ?>" class="property-card-link">
                            <?php if ($prop['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($prop['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($prop['title'], ENT_QUOTES, 'UTF-8'); ?>" class="property-img">
                            <?php else: ?>
                                <div class="property-img" style="display: flex; align-items: center; justify-content: center; color: var(--muted); font-style: italic;">No image uploaded</div>
                            <?php endif; ?>

                            <div class="property-content">
                                <h3 class="property-rent"><?php echo number_format((float) $prop['rent']); ?> <span>BDT / month</span></h3>
                                <h4 class="property-title"><?php echo htmlspecialchars($prop['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="property-location">📍 <?php echo htmlspecialchars($prop['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                                
                                <div class="property-specs">
                                    <span>🛏️ <?php echo $prop['bedrooms']; ?> Beds</span>
                                    <span>🛁 <?php echo $prop['bathrooms']; ?> Baths</span>
                                    <?php if ($prop['area_sqft']): ?>
                                        <span>📐 <?php echo $prop['area_sqft']; ?> Sq Ft</span>
                                    <?php endif; ?>
                                </div>

                                <span class="status-badge <?php echo strtolower($prop['availability_status']); ?>">
                                    <?php echo htmlspecialchars($prop['availability_status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>

                                <span class="view-details-btn">View Details</span>
                            </div>
                        </a>

                        <div class="property-actions" style="margin-top: 1.5rem;">
                            <a href="property_edit.php?id=<?php echo $prop['id']; ?>" class="btn-edit">Edit</a>
                            
                            <form action="property_delete.php" method="post" style="display: inline; flex: 1;" onsubmit="return confirm('Are you sure you want to delete this property listing? This action cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id" value="<?php echo $prop['id']; ?>">
                                <button type="submit" class="btn-delete" style="width: 100%;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </main>
        <?php endif; ?>
    </div>
</body>
</html>
