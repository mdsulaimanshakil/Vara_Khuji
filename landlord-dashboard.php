<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

header('Location: landlord_dashboard.php');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Dashboard | House Rental Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="dashboard-shell">
        <section class="dashboard-card">
            <div class="dashboard-header">
                <div>
                    <span class="role-pill">Landlord</span>
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
                    <h2>Landlord overview</h2>
                    <p>Your session is active and protected with server-side authentication.</p>
                </article>
                <article class="dashboard-tile">
                    <h2>Next step</h2>
                    <p>Connect this page to your property listings, tenant management, and rental reports.</p>
                </article>
            </div>
        </section>
    </main>
</body>
</html>
