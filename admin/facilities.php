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
    
    // Handle create facility
    if (isset($_POST['action']) && $_POST['action'] === 'create_facility') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $capacity = $_POST['capacity'];
        $price_per_day = $_POST['price_per_day'];
        $status = $_POST['status'];
        
        // Validate input
        if (empty($name)) {
            $message = 'error:Nama facility tidak boleh kosong!';
        } elseif ($capacity <= 0) {
            $message = 'error:Kapasitas harus lebih dari 0!';
        } elseif ($price_per_day <= 0) {
            $message = 'error:Harga per hari harus lebih dari 0!';
        } else {
            // Check if facility name already exists
            $stmt = $db->prepare("SELECT id FROM facilities WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn()) {
                $message = 'error:Nama facility sudah ada!';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO facilities (name, description, capacity, price_per_day, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                if ($stmt->execute([$name, $description, $capacity, $price_per_day, $status])) {
                    $message = 'success:Facility berhasil dibuat!';
                } else {
                    $message = 'error:Gagal membuat facility!';
                }
            }
        }
    }
    
    // Handle update facility
    if (isset($_POST['action']) && $_POST['action'] === 'update_facility') {
        $facility_id = $_POST['facility_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $capacity = $_POST['capacity'];
        $price_per_day = $_POST['price_per_day'];
        $status = $_POST['status'];
        
        // Validate input
        if (empty($name)) {
            $message = 'error:Nama facility tidak boleh kosong!';
        } elseif ($capacity <= 0) {
            $message = 'error:Kapasitas harus lebih dari 0!';
        } elseif ($price_per_day <= 0) {
            $message = 'error:Harga per hari harus lebih dari 0!';
        } else {
            // Check if facility name already exists (excluding current facility)
            $stmt = $db->prepare("SELECT id FROM facilities WHERE name = ? AND id != ?");
            $stmt->execute([$name, $facility_id]);
            if ($stmt->fetchColumn()) {
                $message = 'error:Nama facility sudah ada!';
            } else {
                $stmt = $db->prepare("
                    UPDATE facilities 
                    SET name = ?, description = ?, capacity = ?, price_per_day = ?, status = ?
                    WHERE id = ?
                ");
                if ($stmt->execute([$name, $description, $capacity, $price_per_day, $status, $facility_id])) {
                    $message = 'success:Facility berhasil diupdate!';
                } else {
                    $message = 'error:Gagal mengupdate facility!';
                }
            }
        }
    }
    
    // Handle delete facility
    if (isset($_POST['action']) && $_POST['action'] === 'delete_facility') {
        $facility_id = $_POST['facility_id'];
        
        // Since booking_facilities table doesn't exist, we can proceed with deletion
        // In future, implement proper facility-booking relationship
        if (true) {
            $stmt = $db->prepare("DELETE FROM facilities WHERE id = ?");
            if ($stmt->execute([$facility_id])) {
                $message = 'success:Facility berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus facility!';
            }
        }
    }
    
    // Handle status update
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $facility_id = $_POST['facility_id'];
        $new_status = $_POST['status'];
        
        $stmt = $db->prepare("UPDATE facilities SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $facility_id])) {
            $message = 'success:Status facility berhasil diupdate!';
        } else {
            $message = 'error:Gagal mengupdate status facility!';
        }
    }
    
    // Get filters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $sort_filter = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    
    // Build query
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_clause .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    // Sort order
    $order_clause = "ORDER BY created_at DESC";
    if ($sort_filter === 'oldest') {
        $order_clause = "ORDER BY created_at ASC";
    } elseif ($sort_filter === 'name') {
        $order_clause = "ORDER BY name ASC";
    } elseif ($sort_filter === 'capacity_high') {
        $order_clause = "ORDER BY capacity DESC";
    } elseif ($sort_filter === 'capacity_low') {
        $order_clause = "ORDER BY capacity ASC";
    } elseif ($sort_filter === 'price_high') {
        $order_clause = "ORDER BY price_per_day DESC";
    } elseif ($sort_filter === 'price_low') {
        $order_clause = "ORDER BY price_per_day ASC";
    } elseif ($sort_filter === 'most_used') {
        $order_clause = "ORDER BY usage_count DESC";
    }
    
    // Get all facilities (without usage statistics since booking_facilities table doesn't exist)
    $stmt = $db->prepare("
        SELECT f.*, 
               0 as usage_count,
               0 as total_revenue
        FROM facilities f
        $where_clause
        $order_clause
    ");
    $stmt->execute($params);
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_facilities,
            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as active_facilities,
            SUM(CASE WHEN status != 'available' THEN 1 ELSE 0 END) as inactive_facilities,
            AVG(capacity) as avg_capacity,
            MAX(capacity) as max_capacity,
            MIN(capacity) as min_capacity,
            AVG(price_per_day) as avg_price
        FROM facilities
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure $stats is not null
    if (!$stats) {
        $stats = [
        'total_facilities' => 0,
        'active_facilities' => 0,
        'inactive_facilities' => 0,
        'avg_capacity' => 0,
        'max_capacity' => 0,
        'min_capacity' => 0,
        'avg_price' => 0
    ];
    }
    
    // Get facilities (without usage statistics since booking_facilities table doesn't exist)
    $stmt = $db->query("
        SELECT f.name, 0 as usage_count
        FROM facilities f
        ORDER BY f.name ASC
        LIMIT 5
    ");
    $popular_facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
    error_log("Error in facilities.php: " . $e->getMessage());
    
    // Initialize variables with default values to prevent undefined variable errors
    $stats = [
        'total_facilities' => 0,
        'active_facilities' => 0,
        'inactive_facilities' => 0,
        'avg_capacity' => 0,
        'max_capacity' => 0,
        'min_capacity' => 0,
        'avg_price' => 0
    ];
    $facilities = [];
    $popular_facilities = [];
}

// Status configurations
$status_config = [
    'active' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Aktif'],
    'inactive' => ['class' => 'secondary', 'icon' => 'pause-circle', 'text' => 'Tidak Aktif'],
    'available' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Tersedia'],
    'occupied' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Terisi'],
    'maintenance' => ['class' => 'danger', 'icon' => 'tools', 'text' => 'Maintenance']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Fasilitas - Baroon Reptile Admin</title>
    
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
        
        .facility-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .facility-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .facility-header {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .facility-body {
            padding: 20px;
        }
        
        .facility-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #f1f3f4;
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
        
        .price-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c5530;
        }
        
        .facility-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .usage-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .popular-facilities {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Pelanggan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reptiles.php">
                        <i class="fas fa-dragon"></i>
                        <span class="nav-text">Reptil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Booking</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>
                        <span class="nav-text">Pembayaran</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Laporan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="facilities.php">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Fasilitas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="nav-text">Pengaturan</span>
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
                <h4 class="mb-0">Kelola Fasilitas</h4>
                <span class="badge bg-primary ms-3"><?php echo $stats['total_facilities']; ?> Total</span>
                <button class="btn btn-success ms-3" data-bs-toggle="modal" data-bs-target="#createFacilityModal">
                    <i class="fas fa-plus me-2"></i>Tambah Fasilitas
                </button>
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
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['total_facilities']; ?></div>
                    <div class="stat-label">Total Fasilitas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $stats['active_facilities']; ?></div>
                    <div class="stat-label">Fasilitas Aktif</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-secondary">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-value text-secondary"><?php echo $stats['inactive_facilities']; ?></div>
                    <div class="stat-label">Fasilitas Tidak Aktif</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo number_format($stats['avg_capacity'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Rata-rata Kapasitas</div>
                </div>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-info">Rp <?php echo number_format($stats['avg_price'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Rata-rata Harga/Hari</div>
                </div>
            </div>
            
            <!-- Popular Facilities -->
            <?php if (!empty($popular_facilities)): ?>
                <div class="popular-facilities">
                    <h6 class="mb-3"><i class="fas fa-star me-2"></i>Fasilitas Terpopuler</h6>
                    <div class="row">
                        <?php foreach ($popular_facilities as $facility): ?>
                            <div class="col-md-2 col-sm-4 col-6 mb-2">
                                <div class="text-center">
                                    <div class="fw-bold"><?php echo htmlspecialchars($facility['name']); ?></div>
                                    <small class="text-muted"><?php echo $facility['usage_count']; ?> bookings</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filter and Controls -->
            <div class="filter-controls">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="btn-group" role="group">
                                <a href="?status=all&sort=<?php echo urlencode($sort_filter); ?>" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Semua</a>
                                <a href="?status=available&sort=<?php echo urlencode($sort_filter); ?>" class="btn <?php echo $status_filter === 'available' ? 'btn-success' : 'btn-outline-success'; ?>">Tersedia</a>
                                <a href="?status=occupied&sort=<?php echo urlencode($sort_filter); ?>" class="btn <?php echo $status_filter === 'occupied' ? 'btn-warning' : 'btn-outline-warning'; ?>">Terisi</a>
                                <a href="?status=maintenance&sort=<?php echo urlencode($sort_filter); ?>" class="btn <?php echo $status_filter === 'maintenance' ? 'btn-danger' : 'btn-outline-danger'; ?>">Maintenance</a>
                            </div>
                            
                            <select class="form-select" style="width: auto;" onchange="window.location.href='?status=<?php echo urlencode($status_filter); ?>&sort=' + this.value">
                                <option value="newest" <?php echo $sort_filter === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                                <option value="oldest" <?php echo $sort_filter === 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                                <option value="name" <?php echo $sort_filter === 'name' ? 'selected' : ''; ?>>Nama A-Z</option>
                                <option value="capacity_high" <?php echo $sort_filter === 'capacity_high' ? 'selected' : ''; ?>>Kapasitas Tertinggi</option>
                                <option value="capacity_low" <?php echo $sort_filter === 'capacity_low' ? 'selected' : ''; ?>>Kapasitas Terendah</option>
                                <option value="price_high" <?php echo $sort_filter === 'price_high' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                                <option value="price_low" <?php echo $sort_filter === 'price_low' ? 'selected' : ''; ?>>Harga Terendah</option>
                                <option value="most_used" <?php echo $sort_filter === 'most_used' ? 'selected' : ''; ?>>Paling Populer</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari facility...">
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($facilities)): ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-cogs fa-4x text-muted mb-3"></i>
                    <h5>Tidak Ada Facility</h5>
                    <p class="text-muted">Belum ada facility yang dibuat. Mulai dengan menambahkan facility pertama.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFacilityModal">
                        <i class="fas fa-plus me-2"></i>Tambah Facility Pertama
                    </button>
                </div>
            <?php else: ?>
                <!-- Facilities List -->
                <div id="facilitiesList">
                    <?php foreach ($facilities as $facility): ?>
                        <?php $status = $status_config[$facility['status']] ?? $status_config['inactive']; ?>
                        <div class="facility-card" data-search="<?php echo strtolower($facility['name'] . ' ' . $facility['description']); ?>">
                            <div class="facility-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <div class="facility-icon">
                                                <i class="fas fa-cog"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($facility['name']); ?></h5>
                                                <span class="status-badge bg-<?php echo $status['class']; ?> text-white">
                                                    <i class="fas fa-<?php echo $status['icon']; ?>"></i>
                                                    <?php echo $status['text']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <div class="capacity-display"><?php echo $facility['capacity']; ?></div>
                                        <small class="text-muted">kapasitas</small>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <div class="price-display">Rp <?php echo number_format($facility['price_per_day'], 0, ',', '.'); ?></div>
                                        <small class="text-muted">per hari</small>
                                    </div>
                                    <div class="col-md-2 text-md-end">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="editFacility(<?php echo $facility['id']; ?>, '<?php echo addslashes($facility['name']); ?>', '<?php echo addslashes($facility['description']); ?>', <?php echo $facility['capacity']; ?>, <?php echo $facility['price_per_day']; ?>, '<?php echo $facility['status']; ?>')"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php if ($facility['status'] !== 'maintenance'): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $facility['id']; ?>, 'maintenance')"><i class="fas fa-tools me-2"></i>Set Maintenance</a></li>
                                                <?php endif; ?>
                                                <?php if ($facility['status'] !== 'available'): ?>
                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $facility['id']; ?>, 'available')"><i class="fas fa-check me-2"></i>Set Tersedia</a></li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteFacility(<?php echo $facility['id']; ?>)"><i class="fas fa-trash me-2"></i>Hapus</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="facility-body">
                                <?php if ($facility['description']): ?>
                                    <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($facility['description'] ?? '')); ?></p>
                                <?php endif; ?>
                                
                                <div class="usage-stats">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="fw-bold text-primary"><?php echo $facility['usage_count']; ?></div>
                                                <small class="text-muted">Total Booking</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="fw-bold text-success">Rp <?php echo number_format($facility['total_revenue'], 0, ',', '.'); ?></div>
                                                <small class="text-muted">Total Pendapatan</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div class="fw-bold text-info">
                                                    <?php echo $facility['usage_count'] > 0 ? number_format($facility['total_revenue'] / $facility['usage_count'], 0, ',', '.') : '0'; ?>
                                                </div>
                                                <small class="text-muted">Rata-rata per Booking</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="facility-footer">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Dibuat: <?php echo date('d M Y', strtotime($facility['created_at'])); ?>
                                        </small>

                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <small class="text-muted">ID: #<?php echo $facility['id']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Facility Modal -->
    <div class="modal fade" id="createFacilityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Fasilitas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_facility">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Fasilitas</label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Jelaskan fasilitas dan fitur-fiturnya..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="capacity" class="form-label">Kapasitas</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="price_per_day" class="form-label">Harga per Hari (Rp)</label>
                            <input type="number" class="form-control" id="price_per_day" name="price_per_day" min="1" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="available">Tersedia</option>
                                <option value="occupied">Terisi</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Buat Fasilitas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Facility Modal -->
    <div class="modal fade" id="editFacilityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Fasilitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editFacilityForm">
                    <?php echo csrf_field(); ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_facility">
                        <input type="hidden" name="facility_id" id="edit_facility_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Nama Fasilitas</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_capacity" class="form-label">Kapasitas</label>
                            <input type="number" class="form-control" id="edit_capacity" name="capacity" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_price_per_day" class="form-label">Harga per Hari (Rp)</label>
                            <input type="number" class="form-control" id="edit_price_per_day" name="price_per_day" min="1" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available">Tersedia</option>
                                <option value="occupied">Terisi</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update Fasilitas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.facility-card');
            
            cards.forEach(card => {
                const searchData = card.dataset.search;
                const isVisible = searchData.includes(searchTerm);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
        
        // Edit facility function
        function editFacility(id, name, description, capacity, price_per_day, status) {
            document.getElementById('edit_facility_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_capacity').value = capacity;
            document.getElementById('edit_price_per_day').value = price_per_day;
            document.getElementById('edit_status').value = status;
            
            var editModal = new bootstrap.Modal(document.getElementById('editFacilityModal'));
            editModal.show();
        }
        
        // Update status function
        function updateStatus(facilityId, newStatus) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="facility_id" value="${facilityId}">
                <input type="hidden" name="status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Delete facility function
        function deleteFacility(facilityId) {
            if (confirm('Yakin ingin menghapus facility ini? Aksi ini tidak dapat dibatalkan.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_facility">
                    <input type="hidden" name="facility_id" value="${facilityId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>