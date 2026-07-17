<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();
require_role(['Admin']);

$userEmail = $_SESSION['user_email'] ?? '';
$adminId = $_SESSION['user_id'] ?? 0;

$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// Query all users except current admin
try {
    $stmt = $pdo->prepare('
        SELECT id, full_name, email, phone, role, status, created_at 
        FROM users 
        WHERE id != :admin_id 
        ORDER BY created_at DESC
    ');
    $stmt->execute(['admin_id' => $adminId]);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch users: ' . $e->getMessage());
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | HRMS</title>
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
        .nav-back {
            display: inline-block;
            margin-bottom: 1.5rem;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .nav-back:hover {
            text-decoration: underline;
        }
        .users-section {
            margin-top: 2rem;
        }
        .users-section h2 {
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
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-badge.active {
            background: #d1fae5;
            color: #059669;
        }
        .status-badge.suspended {
            background: #fee2e2;
            color: #dc2626;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .btn-suspend, .btn-activate, .btn-delete {
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            color: white;
        }
        .btn-suspend {
            background: #f59e0b;
        }
        .btn-suspend:hover {
            background: #d97706;
        }
        .btn-activate {
            background: #10b981;
        }
        .btn-activate:hover {
            background: #059669;
        }
        .btn-delete {
            background: #ef4444;
        }
        .btn-delete:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <a href="admin_dashboard.php" class="nav-back">← Back to Admin Dashboard</a>

        <header class="dashboard-header">
            <div class="user-info">
                <h1>User Management</h1>
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

        <section class="users-section">
            <h2>Landlord and Tenant Accounts</h2>
            <?php if (empty($users)): ?>
                <div style="background: white; border-radius: 16px; padding: 3rem; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow);">
                    <p style="color: var(--muted); font-size: 1.1rem;">No registered tenant or landlord accounts found.</p>
                </div>
            <?php else: ?>
                <table class="listing-table">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text);"><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--muted);">ID: #<?php echo $user['id']; ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--muted);"><?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: <?php echo $user['role'] === 'Landlord' ? '#10b981' : '#3b82f6'; ?>;">
                                        <?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($user['status']); ?>">
                                        <?php echo htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem;"><?php echo htmlspecialchars(date('M j, Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Suspend / Unsuspend action -->
                                        <form action="handle_user_action.php" method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <?php if ($user['status'] === 'Active'): ?>
                                                <input type="hidden" name="user_action" value="Suspend">
                                                <button type="submit" class="btn-suspend" onclick="return confirm('Are you sure you want to suspend this account? The user will be blocked from logging in.');">Suspend</button>
                                            <?php else: ?>
                                                <input type="hidden" name="user_action" value="Activate">
                                                <button type="submit" class="btn-activate" onclick="return confirm('Restore this user account to active status?');">Activate</button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Delete action -->
                                        <form action="handle_user_action.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this account? This action will cascade-delete all their properties, listings, and requests, and cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="user_action" value="Delete">
                                            <button type="submit" class="btn-delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
