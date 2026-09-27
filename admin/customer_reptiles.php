<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$message = '';

if (!$customer_id) {
    header('Location: customers.php');
    exit;
}

try {
    $db = getDB();
    
    // Get customer info
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header('Location: customers.php?error=customer_not_found');
        exit;
    }
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build WHERE clause
    $where_conditions = ['r.customer_id = ?'];
    $params = [$customer_id];
    
    if ($status_filter) {
        $where_conditions[] = 'r.status = ?';
        $params[] = $status_filter;
    }
    
    if ($category_filter) {
        $where_conditions[] = 'r.category_id = ?';
        $params[] = $category_filter;
    }
    
    if ($search) {
        $where_conditions[] = '(r.name LIKE ? OR r.species LIKE ? OR r.special_needs LIKE ?)';
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM reptiles r $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Get reptiles with details
    $sql = "
        SELECT r.*, 
               rc.name as category_name,
               rc.price_per_day as daily_rate,
               COUNT(b.id) as total_bookings,
               COUNT(CASE WHEN b.status IN ('confirmed', 'in_progress') THEN 1 END) as active_bookings,
               MAX(b.end_date) as last_booking_end
        FROM reptiles r
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        LEFT JOIN bookings b ON r.id = b.reptile_id
        $where_clause
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get reptile statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_reptiles,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_reptiles,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as inactive_reptiles,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_reptiles
        FROM reptiles 
        WHERE customer_id = ?
    ";
    $stmt = $db->prepare($stats_sql);
    $stmt->execute([$customer_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $stmt = $db->query("SELECT * FROM reptile_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . error_detail($e) . '</div>';
    // Initialize stats with default values if there's an error
    $stats = [
        'total_reptiles' => 0,
        'active_reptiles' => 0,
        'inactive_reptiles' => 0,
        'completed_reptiles' => 0
    ];
    $reptiles = [];
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reptil Pelanggan - <?php echo htmlspecialchars($customer['full_name']); ?> - Baroon Reptile Admin</title>
    
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
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #4a7c59;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 5px;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .reptile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
        }
        
        .reptile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .reptile-image {
            height: 200px;
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .reptile-body {
            padding: 20px;
        }
        
        .reptile-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c5530;
            margin-bottom: 5px;
        }
        
        .reptile-species {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .reptile-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .customer-info {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-right: 15px;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-action {
            padding: 8px 12px;
            margin: 0 2px;
            border-radius: 8px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <i class="fas fa-leaf me-2"></i>Baroon Reptile
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="bookings.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>Booking
            </a>
            <a href="customers.php" class="nav-link active">
                <i class="fas fa-users"></i>Pelanggan
            </a>
            <a href="reptiles.php" class="nav-link">
                <i class="fas fa-dragon"></i>Reptil
            </a>
            <a href="payments.php" class="nav-link">
                <i class="fas fa-credit-card"></i>Pembayaran
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Laporan
            </a>
            <a href="facilities.php" class="nav-link">
                <i class="fas fa-building"></i>Fasilitas
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>Pengaturan
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div>
                <h4 class="mb-0">Reptil Pelanggan</h4>
                <small class="text-muted">Kelola koleksi reptil pelanggan</small>
            </div>
            <div class="d-flex align-items-center">
                <a href="view_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-user me-2"></i>Detail Pelanggan
                </a>
                <a href="customers.php" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Pelanggan
                </a>
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['username']; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Keluar</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if ($message): echo $message; endif; ?>
            
            <!-- Customer Info -->
            <div class="customer-info d-flex align-items-center">
                <div class="customer-avatar">
                    <?php echo strtoupper(substr($customer['full_name'], 0, 2)); ?>
                </div>
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($customer['full_name']); ?></h4>
                    <p class="mb-0 opacity-75">@<?php echo htmlspecialchars($customer['username']); ?> • <?php echo htmlspecialchars($customer['email']); ?></p>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-value"><?php echo $stats['total_reptiles']; ?></div>
                        <div class="stat-label">Total Reptil</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-value text-success"><?php echo $stats['active_reptiles']; ?></div>
                        <div class="stat-label">Aktif</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-value text-warning"><?php echo $stats['inactive_reptiles']; ?></div>
                        <div class="stat-label">Dibatalkan</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-value text-primary"><?php echo $stats['completed_reptiles']; ?></div>
                        <div class="stat-label">Selesai</div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-card">
                <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter</h5>
                <form method="GET" class="row g-3">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">Cari</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama, spesies, deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Kategori</label>
                        <select name="category" class="form-select">
                            <option value="">Semua Kategori</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                        <a href="customer_reptiles.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Bersihkan
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Reptiles Grid -->
            <?php if (empty($reptiles)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-dragon fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada reptil ditemukan</h5>
                    <p class="text-muted">Pelanggan ini belum mendaftarkan reptil apapun.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($reptiles as $reptile): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="reptile-card">
                                <div class="reptile-image">
                                    <i class="fas fa-dragon"></i>
                                </div>
                                <div class="reptile-body">
                                    <div class="reptile-name"><?php echo htmlspecialchars($reptile['name']); ?></div>
                                    <div class="reptile-species"><?php echo htmlspecialchars($reptile['species'] ?? ''); ?></div>
                                    
                                    <div class="reptile-info">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($reptile['category_name']); ?></span>
                                        <span class="status-badge bg-<?php 
                                            echo $reptile['status'] === 'active' ? 'success' : 'secondary';
                                        ?>">
                                            <?php echo ucfirst($reptile['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <small class="text-muted">Umur</small><br>
                                            <strong><?php echo $reptile['age'] ? htmlspecialchars($reptile['age']) : 'Tidak diketahui'; ?></strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Jenis Kelamin</small><br>
                                            <strong><?php echo $reptile['gender'] ? ucfirst($reptile['gender']) : 'Tidak diketahui'; ?></strong>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Booking</small><br>
                                            <strong><?php echo $reptile['total_bookings']; ?></strong>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($reptile['description']) && $reptile['description']): ?>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars(substr($reptile['description'], 0, 100)) . (strlen($reptile['description']) > 100 ? '...' : ''); ?></p>
                    <?php endif; ?>
                                    
                                    <?php if (isset($reptile['special_needs']) && $reptile['special_needs']): ?>
                        <div class="alert alert-warning py-2 mb-3">
                            <small><i class="fas fa-exclamation-triangle me-1"></i> Kebutuhan khusus: <?php echo htmlspecialchars($reptile['special_needs'] ?? ''); ?></small>
                        </div>
                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">Tarif Harian</small><br>
                                            <strong class="text-success">Rp <?php echo number_format($reptile['daily_rate'], 0, ',', '.'); ?></strong>
                                        </div>
                                        <div class="btn-group">
                                            <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($reptile['active_bookings'] > 0): ?>
                                                <a href="customer_bookings.php?customer_id=<?php echo $customer_id; ?>&reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-sm btn-outline-info" title="Booking Aktif">
                                                    <i class="fas fa-calendar-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="edit_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-sm btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <?php if ($reptile['last_booking_end']): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">Booking terakhir berakhir: <?php echo date('d M Y', strtotime($reptile['last_booking_end'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?customer_id=<?php echo $customer_id; ?>&page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?customer_id=<?php echo $customer_id; ?>&page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?customer_id=<?php echo $customer_id; ?>&page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>