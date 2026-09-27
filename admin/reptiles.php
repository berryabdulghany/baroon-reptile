<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';

try {
    $db = getDB();
    
    // Handle reptile status update
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $reptile_id = $_POST['reptile_id'];
        $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        
        $stmt = $db->prepare("UPDATE reptiles SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$new_status, $reptile_id])) {
            $message = 'success:Status reptile berhasil diupdate!';
        } else {
            $message = 'error:Gagal mengupdate status reptile!';
        }
    }
    
    // Handle reptile deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_reptile') {
        $reptile_id = $_POST['reptile_id'];
        
        // Check if reptile has active bookings
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE reptile_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$reptile_id]);
        $active_bookings = $stmt->fetchColumn();
        
        if ($active_bookings > 0) {
            $message = 'error:Tidak dapat menghapus reptile yang memiliki booking aktif!';
        } else {
            // Get photo path for deletion
            $stmt = $db->prepare("SELECT photo FROM reptiles WHERE id = ?");
            $stmt->execute([$reptile_id]);
            $photo = $stmt->fetchColumn();
            
            // Delete reptile
            $stmt = $db->prepare("DELETE FROM reptiles WHERE id = ?");
            if ($stmt->execute([$reptile_id])) {
                // Delete photo file if exists
                if ($photo && file_exists('../' . $photo)) {
                    unlink('../' . $photo);
                }
                $message = 'success:Reptile berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus reptile!';
            }
        }
    }
    
    // Get filters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    
    // Build query
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_clause .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    if ($category_filter !== 'all') {
        $where_clause .= " AND r.category_id = ?";
        $params[] = $category_filter;
    }
    
    // Sort clause
    $order_clause = "ORDER BY ";
    switch ($sort_by) {
        case 'oldest':
            $order_clause .= "r.created_at ASC";
            break;
        case 'name_asc':
            $order_clause .= "r.name ASC";
            break;
        case 'name_desc':
            $order_clause .= "r.name DESC";
            break;
        case 'category':
            $order_clause .= "rc.name ASC, r.name ASC";
            break;
        case 'most_bookings':
            $order_clause .= "total_bookings DESC";
            break;
        default: // newest
            $order_clause .= "r.created_at DESC";
    }
    
    // Get all reptiles with statistics
    $stmt = $db->prepare("
        SELECT r.*, 
               u.full_name as owner_name, u.email as owner_email,
               rc.name as category_name, rc.price_per_day,
               COUNT(DISTINCT b.id) as total_bookings,
               COUNT(DISTINCT CASE WHEN b.status IN ('confirmed', 'in_progress') THEN b.id END) as active_bookings,
               MAX(b.created_at) as last_booking_date
        FROM reptiles r 
        LEFT JOIN users u ON r.customer_id = u.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        LEFT JOIN bookings b ON r.id = b.reptile_id
        $where_clause
        GROUP BY r.id
        $order_clause
    ");
    $stmt->execute($params);
    $reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $stmt = $db->query("SELECT * FROM reptile_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overall statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_reptiles,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_reptiles,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_reptiles,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
            SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_this_week
        FROM reptiles
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get category statistics
    $stmt = $db->query("
        SELECT rc.name, COUNT(r.id) as count
        FROM reptile_categories rc
        LEFT JOIN reptiles r ON rc.id = r.category_id
        GROUP BY rc.id, rc.name
        ORDER BY count DESC
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reptiles - Baroon Reptile Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content-area {
            padding: 30px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .reptile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .reptile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .reptile-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            position: relative;
        }
        
        .reptile-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 3rem;
        }
        
        .reptile-header {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .reptile-body {
            padding: 20px;
        }
        
        .reptile-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #f1f3f4;
        }
        
        .reptile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .filter-controls {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .category-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .reptile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item-label {
            font-size: 0.7rem;
            color: #6c757d;
            margin-bottom: 2px;
        }
        
        .stat-item-value {
            font-weight: 600;
            color: #2c5530;
            font-size: 0.9rem;
        }
        
        .owner-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .owner-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .booking-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.9);
        }
        
        .booking-active {
            color: #28a745;
        }
        
        .booking-none {
            color: #6c757d;
        }
        
        .quick-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-dragon me-2"></i>Baroon Admin
            </a>
        </div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="customers.php">
                        <i class="fas fa-users"></i>Pelanggan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="reptiles.php">
                        <i class="fas fa-dragon"></i>Reptil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check"></i>Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-money-bill-wave"></i>Pembayaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i>Laporan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="facilities.php">
                        <i class="fas fa-building"></i>Fasilitas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Pengaturan
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <h4 class="mb-0">Manage Reptiles</h4>
                <span class="badge bg-primary ms-3"><?php echo $stats['total_reptiles']; ?> Total</span>
            </div>
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if ($message): ?>
                <?php 
                $parts = explode(':', $message, 2);
                $type = $parts[0];
                $text = $parts[1];
                $alertClass = $type === 'success' ? 'alert-success' : 'alert-danger';
                $icon = $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
                ?>
                <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show">
                    <i class="fas <?php echo $icon; ?> me-2"></i><?php echo $text; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-dragon"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['total_reptiles']; ?></div>
                    <div class="stat-label">Total Reptiles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $stats['active_reptiles']; ?></div>
                    <div class="stat-label">Active Reptiles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value text-danger"><?php echo $stats['inactive_reptiles']; ?></div>
                    <div class="stat-label">Inactive Reptiles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $stats['new_today']; ?></div>
                    <div class="stat-label">New Today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $stats['new_this_week']; ?></div>
                    <div class="stat-label">New This Week</div>
                </div>
            </div>
            
            <!-- Category Statistics -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Reptiles by Category</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($category_stats as $cat): ?>
                                    <div class="col-md-3 col-sm-6 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="category-badge"><?php echo htmlspecialchars($cat['name']); ?></span>
                                            <span class="badge bg-secondary"><?php echo $cat['count']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter and Controls -->
            <div class="filter-controls">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="btn-group" role="group">
                                <a href="?status=all&category=<?php echo $category_filter; ?>&sort=<?php echo $sort_by; ?>" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Semua</a>
                                <a href="?status=active&category=<?php echo $category_filter; ?>&sort=<?php echo $sort_by; ?>" class="btn <?php echo $status_filter === 'active' ? 'btn-success' : 'btn-outline-success'; ?>">Active</a>
                                <a href="?status=inactive&category=<?php echo $category_filter; ?>&sort=<?php echo $sort_by; ?>" class="btn <?php echo $status_filter === 'inactive' ? 'btn-danger' : 'btn-outline-danger'; ?>">Inactive</a>
                            </div>
                            
                            <select class="form-select" style="width: auto;" onchange="window.location.href='?status=<?php echo $status_filter; ?>&category=' + this.value + '&sort=<?php echo $sort_by; ?>'">
                                <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>Semua Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-sort me-2"></i>Sort
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item <?php echo $sort_by === 'newest' ? 'active' : ''; ?>" href="?status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&sort=newest">Terbaru</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'oldest' ? 'active' : ''; ?>" href="?status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&sort=oldest">Terlama</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'name_asc' ? 'active' : ''; ?>" href="?status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&sort=name_asc">Nama A-Z</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'name_desc' ? 'active' : ''; ?>" href="?status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&sort=name_desc">Nama Z-A</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'category' ? 'active' : ''; ?>" href="?status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&sort=category">Kategori</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'most_bookings' ? 'active' : ''; ?>" href="?status=<?php echo $status_filter; ?>&category=<?php echo $category_filter; ?>&sort=most_bookings">Paling Populer</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari reptile...">
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($reptiles)): ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-dragon fa-4x text-muted mb-3"></i>
                    <h5>Tidak Ada Reptile</h5>
                    <p class="text-muted">Tidak ada reptile yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <!-- Reptiles Grid -->
                <div class="reptile-grid" id="reptilesList">
                    <?php foreach ($reptiles as $reptile): ?>
                        <div class="reptile-card position-relative" data-search="<?php echo strtolower($reptile['name'] . ' ' . $reptile['species'] . ' ' . $reptile['category_name'] . ' ' . $reptile['owner_name']); ?>">
                            <div class="booking-indicator <?php echo $reptile['active_bookings'] > 0 ? 'booking-active' : 'booking-none'; ?>">
                                <?php if ($reptile['active_bookings'] > 0): ?>
                                    <i class="fas fa-calendar-check"></i> Active
                                <?php else: ?>
                                    <i class="fas fa-calendar"></i> Available
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($reptile['photo']): ?>
                                <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($reptile['name']); ?>" class="reptile-image">
                            <?php else: ?>
                                <div class="reptile-image-placeholder">
                                    <i class="fas fa-dragon"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reptile-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($reptile['name']); ?></h5>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($reptile['species'] ?? ''); ?></p>
                                        <span class="category-badge"><?php echo htmlspecialchars($reptile['category_name']); ?></span>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-<?php echo $reptile['status']; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($reptile['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="reptile-body">
                                <div class="reptile-stats">
                                    <div class="stat-item">
                                        <div class="stat-item-label">Umur</div>
                                        <div class="stat-item-value"><?php echo $reptile['age']; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-label">Berat</div>
                                        <div class="stat-item-value"><?php echo $reptile['weight']; ?> gram</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-label">Panjang</div>
                                        <div class="stat-item-value"><?php echo $reptile['length']; ?> cm</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-label">Gender</div>
                                        <div class="stat-item-value"><?php echo ucfirst($reptile['gender']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <small class="text-muted">Harga per hari:</small>
                                        <div class="fw-bold text-success">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Total Booking:</small>
                                        <div class="fw-bold"><?php echo $reptile['total_bookings']; ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($reptile['special_needs']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Kebutuhan Khusus:</small>
                                        <p class="small mb-0"><?php echo nl2br(htmlspecialchars($reptile['special_needs'] ?? '')); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="owner-info">
                                    <div class="owner-avatar">
                                        <?php echo strtoupper(substr($reptile['owner_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <small class="text-muted">Pemilik:</small>
                                        <div class="small fw-bold"><?php echo htmlspecialchars($reptile['owner_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="reptile-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Terdaftar: <?php echo date('d M Y', strtotime($reptile['created_at'])); ?>
                                        <?php if ($reptile['last_booking_date']): ?>
                                            <br><i class="fas fa-clock me-1"></i>
                                            Booking terakhir: <?php echo date('d M Y', strtotime($reptile['last_booking_date'])); ?>
                                        <?php endif; ?>
                                    </small>
                                    
                                    <div class="quick-actions">
                                        <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-primary btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="reptile_bookings.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-outline-info btn-sm" title="View Bookings">
                                            <i class="fas fa-calendar-alt"></i>
                                        </a>
                                        
                                        <a href="edit_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin mengubah status reptile ini?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="reptile_id" value="<?php echo $reptile['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $reptile['status']; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $reptile['status'] === 'active' ? 'danger' : 'success'; ?> btn-sm" title="<?php echo $reptile['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $reptile['status'] === 'active' ? 'times' : 'check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="reptile_bookings.php?reptile_id=<?php echo $reptile['id']; ?>"><i class="fas fa-history me-2"></i>History</a></li>
                                                <li><a class="dropdown-item" href="create_booking.php?reptile_id=<?php echo $reptile['id']; ?>"><i class="fas fa-plus me-2"></i>Create Booking</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $reptile['id']; ?>)"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.reptile-card');
            
            cards.forEach(card => {
                const searchData = card.dataset.search;
                const isVisible = searchData.includes(searchTerm);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
        
        // Confirm delete function
        function confirmDelete(reptileId) {
            if (confirm('Yakin ingin menghapus reptile ini? Tindakan ini tidak dapat dibatalkan!')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_reptile">
                    <input type="hidden" name="reptile_id" value="${reptileId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-refresh every 60 seconds
        setInterval(function() {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 60000);
    </script>
</body>
</html>