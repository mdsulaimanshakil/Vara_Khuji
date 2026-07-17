<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireLogin('Tenant');

$userName = htmlspecialchars((string) ($_SESSION['user_name'] ?? 'Tenant'), ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars((string) ($_SESSION['user_email'] ?? ''), ENT_QUOTES, 'UTF-8');
$csrf = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard | House Rental Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="dashboard-shell">
        <section class="dashboard-card">
            <div class="dashboard-header">
                <div>
                    <span class="role-pill">Tenant</span>
                    <h1>Welcome, <?php echo $userName; ?></h1>
                    <p><?php echo $userEmail; ?></p>
                </div>

                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>

            <div class="dashboard-grid">
                <article class="dashboard-tile">
                    <h2>Tenant overview</h2>
                    <p>Your session is active and protected with server-side authentication.</p>
                </article>
                <article class="dashboard-tile">
                    <h2>Next step</h2>
                    <p>Connect this page to your listings, booking requests, and rent payment modules.</p>
                </article>
            </div>
        </section>
    </main>
</body>
</html>
