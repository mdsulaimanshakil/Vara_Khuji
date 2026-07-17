<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();

// Protect page and ensure user has 'Tenant' role
require_role(['Tenant']);

$userName = $_SESSION['user_name'] ?? 'Tenant';
$userEmail = $_SESSION['user_email'] ?? '';

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// Fetch tenant's favorited properties
$favStmt = $pdo->prepare(
    'SELECT p.*, pi.image_path, u.full_name as landlord_name 
     FROM tenant_favorites tf 
     JOIN properties p ON tf.property_id = p.id
     LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
     LEFT JOIN users u ON p.landlord_id = u.id
     WHERE tf.tenant_id = :tenant_id 
     ORDER BY tf.created_at DESC'
);
$favStmt->execute(['tenant_id' => $_SESSION['user_id']]);
$favorites = $favStmt->fetchAll();

// Fetch tenant's submitted rental requests
$reqStmt = $pdo->prepare(
    'SELECT r.*, p.title AS property_title, p.rent, p.location, pi.image_path, u.full_name AS landlord_name, u.phone AS landlord_phone
     FROM rental_requests r
     JOIN properties p ON r.property_id = p.id
     LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
     LEFT JOIN users u ON p.landlord_id = u.id
     WHERE r.tenant_id = :tenant_id
     ORDER BY r.created_at DESC'
);
$reqStmt->execute(['tenant_id' => $_SESSION['user_id']]);
$rentalRequests = $reqStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard | HRMS</title>
    <link class="stylesheet" rel="stylesheet" href="assets/css/style.css">
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
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.5rem;
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
            transition: all 0.2s ease;
        }
        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }
        .card h3 {
            margin-top: 0;
            color: var(--text);
            font-size: 1.25rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
        }
        .card p {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .btn-action {
            display: inline-block;
            background: var(--accent);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 1rem;
            transition: background 0.2s;
        }
        .btn-action:hover {
            background: var(--accent-strong);
        }
        .favorites-section {
            margin-top: 3rem;
        }
        .favorites-section h2 {
            font-size: 1.5rem;
            color: var(--text);
            margin-bottom: 1.5rem;
            font-weight: 700;
        }
        .favorites-grid {
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
            position: relative;
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
        .status-badge {
            align-self: flex-start;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 1rem;
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
        .btn-remove-fav {
            display: block;
            width: 100%;
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fca5a5;
            padding: 0.6rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-align: center;
            transition: background 0.2s;
            margin-top: auto;
        }
        .btn-remove-fav:hover {
            background: #fecaca;
        }
        .property-link {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="user-info">
                <h1>Welcome back, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                <p><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="role-badge">Tenant</span>
            </div>
            <div class="header-buttons">
                <form action="logout.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to log out?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="logout-btn" style="cursor: pointer; border: none;">Log Out</button>
                </form>
            </div>
        </header>

        <!-- Flash messages -->
        <?php if ($flashSuccess !== ''): ?>
            <div class="alert success" role="status" style="margin-bottom: 1.5rem;"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="alert error" role="alert" style="margin-bottom: 1.5rem;"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <main class="grid-cards">
            <section class="card">
                <h3>My Rentals</h3>
                <p>View your active lease contracts, payment history, and landlord details.</p>
                <a href="#" class="btn-action">View Active Leases</a>
            </section>

            <section class="card">
                <h3>Find Houses</h3>
                <p>Browse listings matching your budget, location, and requirements.</p>
                <a href="properties.php" class="btn-action">Browse Listings</a>
            </section>

            <section class="card">
                <h3>Maintenance Requests</h3>
                <p>Submit issues with water, electricity, or other household appliances directly to your landlord.</p>
                <a href="#" class="btn-action">Create Request</a>
            </section>
        </main>

        <!-- Rental Requests Section -->
        <section class="requests-section" style="margin-top: 2rem; margin-bottom: 3rem;">
            <h2 style="font-size: 1.5rem; color: var(--text); margin-bottom: 1.5rem; font-weight: 700;">My Rental Requests</h2>
            
            <?php if (empty($rentalRequests)): ?>
                <div style="background: white; border-radius: 16px; padding: 3rem; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow);">
                    <p style="color: var(--muted); font-size: 1.1rem; margin-bottom: 1.5rem;">You haven't submitted any rental requests yet.</p>
                    <a href="properties.php" class="btn-action">Find Properties to Apply</a>
                </div>
            <?php else: ?>
                <div class="favorites-grid">
                    <?php foreach ($rentalRequests as $req): ?>
                        <div class="property-card">
                            <a href="property_detail.php?id=<?php echo $req['property_id']; ?>" class="property-link">
                                <?php if ($req['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($req['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($req['property_title'], ENT_QUOTES, 'UTF-8'); ?>" class="property-img">
                                 <?php else: ?>
                                    <div class="property-img" style="display: flex; align-items: center; justify-content: center; color: var(--muted); font-style: italic;">No image uploaded</div>
                                <?php endif; ?>
                            </a>

                            <div class="property-content">
                                <h3 class="property-rent"><?php echo number_format((float) $req['rent']); ?> <span>BDT / month</span></h3>
                                <a href="property_detail.php?id=<?php echo $req['property_id']; ?>" class="property-link">
                                    <h4 class="property-title"><?php echo htmlspecialchars($req['property_title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                </a>
                                <p class="property-location">📍 <?php echo htmlspecialchars($req['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                                
                                <div style="margin-bottom: 1rem; border-top: 1px solid var(--border); padding-top: 0.8rem; display: flex; flex-direction: column; gap: 0.3rem;">
                                    <span style="font-size: 0.75rem; color: var(--muted); font-weight: 700; text-transform: uppercase;">Application Details</span>
                                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text);">Status: 
                                        <span style="font-size: 0.8rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px; text-transform: uppercase; <?php 
                                            if ($req['status'] === 'Accepted') echo 'background: #d1fae5; color: #065f46;';
                                            elseif ($req['status'] === 'Rejected') echo 'background: #fee2e2; color: #991b1b;';
                                            else echo 'background: #e0f2fe; color: #0369a1;';
                                        ?>">
                                            <?php echo htmlspecialchars($req['status'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </span>
                                    <?php if ($req['message']): ?>
                                        <p style="font-size: 0.8rem; color: var(--muted); margin: 0.25rem 0 0; line-height: 1.4;">
                                            <strong>Message:</strong> <em>"<?php echo htmlspecialchars($req['message'], ENT_QUOTES, 'UTF-8'); ?>"</em>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: auto; border-top: 1px solid var(--border); padding-top: 0.8rem;">
                                    <?php if ($req['status'] === 'Pending'): ?>
                                        <form action="cancel_rental_request.php" method="post" style="margin: 0; width: 100%;" onsubmit="return confirm('Cancel this rental request?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="btn-remove-fav" style="margin-top: 0; width: 100%;">Cancel Request</button>
                                        </form>
                                    <?php else: ?>
                                        <div style="font-size: 0.8rem; color: var(--muted); width: 100%;">
                                            <strong>Landlord:</strong> <?php echo htmlspecialchars((string) $req['landlord_name'], ENT_QUOTES, 'UTF-8'); ?><br>
                                            <strong>Phone:</strong> <?php echo htmlspecialchars((string) $req['landlord_phone'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Favorites Section -->
        <section class="favorites-section">
            <h2>My Favorited Listings</h2>
            <?php if (empty($favorites)): ?>
                <div style="background: white; border-radius: 16px; padding: 3rem; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow);">
                    <p style="color: var(--muted); font-size: 1.1rem; margin-bottom: 1.5rem;">You haven't favorited any listings yet.</p>
                    <a href="properties.php" class="btn-action">Browse Listings to Favorite</a>
                </div>
            <?php else: ?>
                <div class="favorites-grid">
                    <?php foreach ($favorites as $prop): ?>
                        <div class="property-card">
                            <a href="property_detail.php?id=<?php echo $prop['id']; ?>" class="property-link">
                                <?php if ($prop['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($prop['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($prop['title'], ENT_QUOTES, 'UTF-8'); ?>" class="property-img">
                                <?php else: ?>
                                    <div class="property-img" style="display: flex; align-items: center; justify-content: center; color: var(--muted); font-style: italic;">No image uploaded</div>
                                <?php endif; ?>
                            </a>

                            <div class="property-content">
                                <h3 class="property-rent"><?php echo number_format((float) $prop['rent']); ?> <span>BDT / month</span></h3>
                                <a href="property_detail.php?id=<?php echo $prop['id']; ?>" class="property-link">
                                    <h4 class="property-title"><?php echo htmlspecialchars($prop['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                </a>
                                <p class="property-location">📍 <?php echo htmlspecialchars($prop['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                                
                                <span class="status-badge <?php echo strtolower($prop['availability_status']); ?>">
                                    <?php echo htmlspecialchars($prop['availability_status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>

                                <form action="toggle_favorite.php" method="post" onsubmit="return confirm('Remove this property from your favorites?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="property_id" value="<?php echo $prop['id']; ?>">
                                    <button type="submit" class="btn-remove-fav">Remove from Favorites</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
