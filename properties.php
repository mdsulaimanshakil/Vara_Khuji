<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();

$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'Available';

// Prepare SQL query with search filters
$sql = 'SELECT p.*, pi.image_path, u.full_name as landlord_name, u.phone as landlord_phone 
        FROM properties p 
        LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_primary = 1
        LEFT JOIN users u ON p.landlord_id = u.id 
        WHERE 1=1';

$params = [];

if ($searchQuery !== '') {
    $sql .= ' AND (p.title LIKE :q OR p.location LIKE :q OR p.description LIKE :q)';
    $params['q'] = '%' . $searchQuery . '%';
}

if ($statusFilter !== '') {
    $sql .= ' AND p.availability_status = :status';
    $params['status'] = $statusFilter;
}

$sql .= ' ORDER BY p.created_at DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Search query failed: ' . $e->getMessage());
    $listings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Properties | HRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .search-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .search-header {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 16px;
            padding: 2.5rem;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            margin-bottom: 2.5rem;
            text-align: center;
        }
        .search-header h1 {
            margin: 0 0 0.5rem;
            font-size: 2rem;
            font-weight: 700;
        }
        .search-header p {
            margin: 0 0 1.5rem;
            color: #94a3b8;
        }
        .search-form {
            display: flex;
            gap: 1rem;
            max-width: 700px;
            margin: 0 auto;
            flex-wrap: wrap;
        }
        .search-form input[type="text"] {
            flex: 2;
            min-width: 250px;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            border: 1px solid #334155;
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 0.95rem;
        }
        .search-form input[type="text"]::placeholder {
            color: #64748b;
        }
        .search-form select {
            flex: 1;
            min-width: 150px;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #1e293b;
            color: white;
            font-size: 0.95rem;
        }
        .search-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.8rem 1.8rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-btn:hover {
            background: var(--accent-strong);
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
        .contact-info {
            margin-top: 1rem;
            font-size: 0.85rem;
            background: #f8fafc;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body>
    <div class="search-container">
        <?php if (isLoggedIn()): ?>
            <a href="<?php echo htmlspecialchars(get_dashboard_url(currentRole() ?? 'Tenant'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-back">← Back to Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="nav-back">← Back to Login</a>
        <?php endif; ?>

        <header class="search-header">
            <h1>Find Your Next Home</h1>
            <p>Search over hundreds of active rental listings in your preferred location.</p>
            
            <form method="get" class="search-form">
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by title, location, or keywords...">
                <select name="status">
                    <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                    <option value="Booked" <?php echo $statusFilter === 'Booked' ? 'selected' : ''; ?>>Booked</option>
                    <option value="Unavailable" <?php echo $statusFilter === 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                    <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All Listings</option>
                </select>
                <button type="submit" class="search-btn">Search</button>
            </form>
        </header>

        <?php if (empty($listings)): ?>
            <div style="background: white; border-radius: 16px; padding: 4rem 2rem; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                <h3 style="margin: 0 0 0.5rem; color: var(--text);">No Listings Found</h3>
                <p style="color: var(--muted); margin: 0;">Try broadening your keywords or changing the status filter.</p>
            </div>
        <?php else: ?>
            <main class="property-grid">
                <?php foreach ($listings as $prop): ?>
                    <div class="property-card">
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

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto;">
                                <span class="status-badge <?php echo strtolower($prop['availability_status']); ?>">
                                    <?php echo htmlspecialchars($prop['availability_status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>

                            <div class="contact-info">
                                <span style="font-weight: 600; color: var(--text);">Owner:</span> <?php echo htmlspecialchars((string) $prop['landlord_name'], ENT_QUOTES, 'UTF-8'); ?><br>
                                <span style="font-weight: 600; color: var(--text);">Phone:</span> <?php echo htmlspecialchars((string) $prop['landlord_phone'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </main>
        <?php endif; ?>
    </div>
</body>
</html>
