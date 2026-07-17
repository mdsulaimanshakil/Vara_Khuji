<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();

// Protect page and ensure user has 'Admin' role
require_role(['Admin']);

$userEmail = $_SESSION['user_email'] ?? '';

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// Fetch all property listings requiring verification (Pending status first, then Approved/Rejected)
try {
    $stmt = $pdo->query(
        'SELECT p.*, pi.image_path, u.full_name as landlord_name, u.email as landlord_email 
         FROM properties p 
         LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
         LEFT JOIN users u ON p.landlord_id = u.id 
         ORDER BY FIELD(p.verification_status, "Pending", "Approved", "Rejected") ASC, p.created_at DESC'
    );
    $properties = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch properties: ' . $e->getMessage());
    $properties = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | HRMS</title>
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
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
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
            cursor: pointer;
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
            background: #6366f1;
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
            background: #4f46e5;
        }
        .verification-section {
            margin-top: 2rem;
        }
        .verification-section h2 {
            font-size: 1.5rem;
            color: var(--text);
            margin-bottom: 1.5rem;
            font-weight: 700;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
        }
        .listing-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .listing-table th, .listing-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .listing-table th {
            background: #f8fafc;
            color: var(--text);
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .listing-table tr:last-child td {
            border-bottom: none;
        }
        .listing-img {
            width: 70px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            background-color: #f1f5f9;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }
        .status-badge.approved {
            background: #d1fae5;
            color: #059669;
        }
        .status-badge.rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .btn-approve, .btn-reject {
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-approve {
            background: #10b981;
            color: white;
        }
        .btn-approve:hover {
            background: #059669;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        .btn-reject:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="user-info">
                <h1>Welcome, System Admin</h1>
                <p><?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="role-badge">Administrator</span>
            </div>
            <div class="header-buttons">
                <form action="logout.php" method="post" onsubmit="return confirm('Are you sure you want to log out?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="logout-btn">Log Out</button>
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
                <h3>User Management</h3>
                <p>Approve new landlords, suspend malicious accounts, and search active users.</p>
                <a href="#" class="btn-action">Manage Users</a>
            </section>

            <section class="card">
                <h3>System Logs & Settings</h3>
                <p>Monitor security parameters, login events, system backups, and server resources.</p>
                <a href="#" class="btn-action">View Settings</a>
            </section>
        </main>

        <section class="verification-section">
            <h2>Rental Listing Verification</h2>
            <?php if (empty($properties)): ?>
                <div style="background: white; border-radius: 16px; padding: 3rem; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow);">
                    <p style="color: var(--muted); font-size: 1.1rem;">No property listings in the system.</p>
                </div>
            <?php else: ?>
                <table class="listing-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Property</th>
                            <th>Landlord</th>
                            <th>Rent</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($properties as $prop): ?>
                            <tr>
                                <td>
                                    <?php if ($prop['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($prop['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Listing Photo" class="listing-img">
                                    <?php else: ?>
                                        <div class="listing-img" style="display: flex; align-items: center; justify-content: center; font-size: 0.65rem; color: var(--muted); font-style: italic;">No Photo</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text);"><?php echo htmlspecialchars($prop['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--muted);">📍 <?php echo htmlspecialchars($prop['location'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars((string)$prop['landlord_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--muted);"><?php echo htmlspecialchars((string)$prop['landlord_email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td><?php echo number_format((float)$prop['rent']); ?> BDT</td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($prop['verification_status']); ?>">
                                        <?php echo htmlspecialchars($prop['verification_status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($prop['verification_status'] === 'Pending'): ?>
                                        <div class="action-buttons">
                                            <form action="verify_listing.php" method="post" style="display: inline;" onsubmit="return confirm('Approve this listing? It will become visible to public tenants.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="property_id" value="<?php echo $prop['id']; ?>">
                                                <input type="hidden" name="status_action" value="Approved">
                                                <button type="submit" class="btn-approve">Approve</button>
                                            </form>
                                            <form action="verify_listing.php" method="post" style="display: inline;" onsubmit="return confirm('Reject this listing?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="property_id" value="<?php echo $prop['id']; ?>">
                                                <input type="hidden" name="status_action" value="Rejected">
                                                <button type="submit" class="btn-reject">Reject</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: var(--muted); font-style: italic;">Verified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php foreach ($listings as $listing) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
