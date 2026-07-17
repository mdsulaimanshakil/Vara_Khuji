<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Protect page and ensure user has 'Tenant' role
require_role(['Tenant']);

$userName = $_SESSION['user_name'] ?? 'Tenant';
$userEmail = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard | HRMS</title>
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
            <a href="logout.php" class="logout-btn">Log Out</a>
        </header>

        <main class="grid-cards">
            <section class="card">
                <h3>My Rentals</h3>
                <p>View your active lease contracts, payment history, and landlord details.</p>
                <a href="#" class="btn-action">View Active Leases</a>
            </section>

            <section class="card">
                <h3>Find Houses</h3>
                <p>Browse listings matching your budget, location, and requirements.</p>
                <a href="#" class="btn-action">Browse Listings</a>
            </section>

            <section class="card">
                <h3>Maintenance Requests</h3>
                <p>Submit issues with water, electricity, or other household appliances directly to your landlord.</p>
                <a href="#" class="btn-action">Create Request</a>
            </section>
        </main>
    </div>
</body>
</html>
