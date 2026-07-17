<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

startSecureSession();

$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'Available';
$minPrice = (isset($_GET['min_price']) && $_GET['min_price'] !== '') ? (float) $_GET['min_price'] : null;
$maxPrice = (isset($_GET['max_price']) && $_GET['max_price'] !== '') ? (float) $_GET['max_price'] : null;
$minArea = (isset($_GET['min_area']) && $_GET['min_area'] !== '') ? (int) $_GET['min_area'] : null;
$maxArea = (isset($_GET['max_area']) && $_GET['max_area'] !== '') ? (int) $_GET['max_area'] : null;
$bedrooms = (isset($_GET['bedrooms']) && $_GET['bedrooms'] !== '') ? (int) $_GET['bedrooms'] : null;
$bathrooms = (isset($_GET['bathrooms']) && $_GET['bathrooms'] !== '') ? (int) $_GET['bathrooms'] : null;

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

if ($minPrice !== null) {
    $sql .= ' AND p.rent >= :min_price';
    $params['min_price'] = $minPrice;
}

if ($maxPrice !== null) {
    $sql .= ' AND p.rent <= :max_price';
    $params['max_price'] = $maxPrice;
}

if ($minArea !== null) {
    $sql .= ' AND p.area_sqft >= :min_area';
    $params['min_area'] = $minArea;
}

if ($maxArea !== null) {
    $sql .= ' AND p.area_sqft <= :max_area';
    $params['max_area'] = $maxArea;
}

if ($bedrooms !== null) {
    $sql .= ' AND p.bedrooms = :bedrooms';
    $params['bedrooms'] = $bedrooms;
}

if ($bathrooms !== null) {
    $sql .= ' AND p.bathrooms = :bathrooms';
    $params['bathrooms'] = $bathrooms;
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

$favoritedIds = [];
if (isLoggedIn() && currentRole() === 'Tenant') {
    $favStmt = $pdo->prepare('SELECT property_id FROM tenant_favorites WHERE tenant_id = :tenant_id');
    $favStmt->execute(['tenant_id' => $_SESSION['user_id']]);
    $favoritedIds = array_map('intval', $favStmt->fetchAll(PDO::FETCH_COLUMN));
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
            flex-direction: column;
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .search-main-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            width: 100%;
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
        .search-form select, .search-form input[type="number"] {
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #1e293b;
            color: white;
            font-size: 0.95rem;
        }
        .search-form select {
            flex: 1;
            min-width: 150px;
        }
        .advanced-toggle-btn {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid #334155;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .advanced-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.15);
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
        .advanced-filters-panel {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            background: rgba(15, 23, 42, 0.6);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #334155;
            animation: fadeIn 0.3s ease-out;
            text-align: left;
        }
        .advanced-filters-panel.active {
            display: grid;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .filter-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .filter-group-range {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .filter-group-range input {
            width: 100%;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
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
            padding: 0.75rem 1rem;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: white;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .view-details-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(20, 88, 199, 0.22);
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
        .fav-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            color: #94a3b8;
            font-size: 1.25rem;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
        }
        .fav-btn:hover {
            transform: scale(1.1);
            background: white;
            color: #ef4444;
        }
        .fav-btn.active {
            color: #ef4444;
            background: white;
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

        <?php
        $flashSuccess = $_SESSION['flash_success'] ?? '';
        unset($_SESSION['flash_success']);
        $flashError = $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_error']);
        ?>
        <?php if ($flashSuccess !== ''): ?>
            <div class="alert success" role="status" style="margin-bottom: 1.5rem;"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="alert error" role="alert" style="margin-bottom: 1.5rem;"><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <header class="search-header">
            <h1>Find Your Next Home</h1>
            <p>Search over hundreds of active rental listings in your preferred location.</p>
            
            <form method="get" class="search-form">
                <div class="search-main-row">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by title, location, or keywords..." style="flex: 2; min-width: 250px;">
                    <select name="status">
                        <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Booked" <?php echo $statusFilter === 'Booked' ? 'selected' : ''; ?>>Booked</option>
                        <option value="Unavailable" <?php echo $statusFilter === 'Unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                        <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All Statuses</option>
                    </select>
                    <button type="button" class="advanced-toggle-btn" onclick="toggleAdvancedFilters()">
                        ⚙️ Filters
                    </button>
                    <button type="submit" class="search-btn">Search</button>
                </div>

                <div class="advanced-filters-panel <?php echo ($minPrice !== null || $maxPrice !== null || $minArea !== null || $maxArea !== null || $bedrooms !== null || $bathrooms !== null) ? 'active' : ''; ?>" id="advanced-filters">
                    <div class="filter-group">
                        <label>Price Range (BDT)</label>
                        <div class="filter-group-range">
                            <input type="number" name="min_price" value="<?php echo $minPrice !== null ? htmlspecialchars((string) $minPrice, ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Min" min="0">
                            <span style="color: #64748b;">-</span>
                            <input type="number" name="max_price" value="<?php echo $maxPrice !== null ? htmlspecialchars((string) $maxPrice, ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Max" min="0">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>Area (Sq Ft)</label>
                        <div class="filter-group-range">
                            <input type="number" name="min_area" value="<?php echo $minArea !== null ? htmlspecialchars((string) $minArea, ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Min" min="0">
                            <span style="color: #64748b;">-</span>
                            <input type="number" name="max_area" value="<?php echo $maxArea !== null ? htmlspecialchars((string) $maxArea, ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="Max" min="0">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>Bedrooms</label>
                        <select name="bedrooms">
                            <option value="">Any</option>
                            <option value="1" <?php echo $bedrooms === 1 ? 'selected' : ''; ?>>1 Bed</option>
                            <option value="2" <?php echo $bedrooms === 2 ? 'selected' : ''; ?>>2 Beds</option>
                            <option value="3" <?php echo $bedrooms === 3 ? 'selected' : ''; ?>>3 Beds</option>
                            <option value="4" <?php echo $bedrooms === 4 ? 'selected' : ''; ?>>4 Beds</option>
                            <option value="5" <?php echo $bedrooms === 5 ? 'selected' : ''; ?>>5+ Beds</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Bathrooms</label>
                        <select name="bathrooms">
                            <option value="">Any</option>
                            <option value="1" <?php echo $bathrooms === 1 ? 'selected' : ''; ?>>1 Bath</option>
                            <option value="2" <?php echo $bathrooms === 2 ? 'selected' : ''; ?>>2 Baths</option>
                            <option value="3" <?php echo $bathrooms === 3 ? 'selected' : ''; ?>>3 Baths</option>
                            <option value="4" <?php echo $bathrooms === 4 ? 'selected' : ''; ?>>4+ Baths</option>
                        </select>
                    </div>
                </div>
            </form>

            <script>
                function toggleAdvancedFilters() {
                    const panel = document.getElementById('advanced-filters');
                    panel.classList.toggle('active');
                }
            </script>
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
                    <div style="position: relative;">
                        <?php if (isLoggedIn() && currentRole() === 'Tenant'): ?>
                            <form action="toggle_favorite.php" method="post" style="position: absolute; top: 15px; right: 15px; z-index: 10;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="property_id" value="<?php echo $prop['id']; ?>">
                                <button type="submit" class="fav-btn <?php echo in_array((int)$prop['id'], $favoritedIds, true) ? 'active' : ''; ?>" title="<?php echo in_array((int)$prop['id'], $favoritedIds, true) ? 'Remove from Favorites' : 'Add to Favorites'; ?>">
                                    ❤
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="property_detail.php?id=<?php echo $prop['id']; ?>" class="property-card-link">
                            <div class="property-card" style="height: 100%;">
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

                                    <span class="view-details-btn">View Details</span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </main>
        <?php endif; ?>
    </div>
</body>
</html>
