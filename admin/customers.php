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
    
    // Handle customer status update
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $customer_id = $_POST['customer_id'];
        $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        
        $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND role = 'customer'");
        if ($stmt->execute([$new_status, $customer_id])) {
            $message = 'success:Status customer berhasil diupdate!';
        } else {
            $message = 'error:Gagal mengupdate status customer!';
        }
    }
    
    // Get filter
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    
    // Build query
    $where_clause = "WHERE u.role = 'customer'";
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_clause .= " AND u.status = ?";
        $params[] = $status_filter;
    }
    
    // Sort clause
    $order_clause = "ORDER BY ";
    switch ($sort_by) {
        case 'oldest':
            $order_clause .= "u.created_at ASC";
            break;
        case 'name_asc':
            $order_clause .= "u.full_name ASC";
            break;
        case 'name_desc':
            $order_clause .= "u.full_name DESC";
            break;
        case 'most_bookings':
            $order_clause .= "total_bookings DESC";
            break;
        default: // newest
            $order_clause .= "u.created_at DESC";
    }
    
    // Get all customers with statistics
    $stmt = $db->prepare("
        SELECT u.*, 
               COUNT(DISTINCT r.id) as total_reptiles,
               COUNT(DISTINCT b.id) as total_bookings,
               COUNT(DISTINCT CASE WHEN b.status IN ('confirmed', 'in_progress') THEN b.id END) as active_bookings,
               COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_price END), 0) as total_spent,
               MAX(b.created_at) as last_booking_date
        FROM users u 
        LEFT JOIN reptiles r ON u.id = r.customer_id
        LEFT JOIN bookings b ON u.id = b.customer_id
        $where_clause
        GROUP BY u.id
        $order_clause
    ");
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overall statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_customers,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_customers,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
            SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_this_week
        FROM users 
        WHERE role = 'customer'
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pelanggan - Baroon Reptile Admin</title>
    
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
        
        .customer-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .customer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .customer-header {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .customer-body {
            padding: 20px;
        }
        
        .customer-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #f1f3f4;
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .customer-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .stat-item-value {
            font-weight: 600;
            color: #2c5530;
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
        
        .customer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .activity-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
        }
        
        .activity-indicator.inactive {
            background: #dc3545;
        }
        
        .activity-indicator.warning {
            background: #ffc107;
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
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="customers.php">
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
                    <a class="nav-link" href="facilities.php">
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
                <h4 class="mb-0">Kelola Pelanggan</h4>
                <span class="badge bg-primary ms-3"><?php echo $stats['total_customers']; ?> Total</span>
            </div>
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Pengaturan</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Keluar</a></li>
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Total Pelanggan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $stats['active_customers']; ?></div>
                    <div class="stat-label">Pelanggan Aktif</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-value text-danger"><?php echo $stats['inactive_customers']; ?></div>
                    <div class="stat-label">Pelanggan Tidak Aktif</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $stats['new_today']; ?></div>
                    <div class="stat-label">Baru Hari Ini</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $stats['new_this_week']; ?></div>
                    <div class="stat-label">Baru Minggu Ini</div>
                </div>
            </div>
            
            <!-- Filter and Controls -->
            <div class="filter-controls">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="btn-group" role="group">
                                <a href="?status=all&sort=<?php echo urlencode($sort_by); ?>" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Semua</a>
                                <a href="?status=active&sort=<?php echo urlencode($sort_by); ?>" class="btn <?php echo $status_filter === 'active' ? 'btn-success' : 'btn-outline-success'; ?>">Aktif</a>
                                <a href="?status=inactive&sort=<?php echo urlencode($sort_by); ?>" class="btn <?php echo $status_filter === 'inactive' ? 'btn-danger' : 'btn-outline-danger'; ?>">Tidak Aktif</a>
                            </div>
                            
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-sort me-2"></i>Urutkan
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item <?php echo $sort_by === 'newest' ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status_filter); ?>&sort=newest">Terbaru</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'oldest' ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status_filter); ?>&sort=oldest">Terlama</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'name_asc' ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status_filter); ?>&sort=name_asc">Nama A-Z</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'name_desc' ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status_filter); ?>&sort=name_desc">Nama Z-A</a></li>
                                    <li><a class="dropdown-item <?php echo $sort_by === 'most_bookings' ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status_filter); ?>&sort=most_bookings">Paling Aktif</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari customer...">
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($customers)): ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h5>Tidak Ada Customer</h5>
                    <p class="text-muted">Tidak ada customer yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <!-- Customers Grid -->
                <div class="customer-grid" id="customersList">
                    <?php foreach ($customers as $customer): ?>
                        <?php 
                        $last_activity = $customer['last_booking_date'] ? new DateTime($customer['last_booking_date']) : null;
                        $now = new DateTime();
                        $days_since_last_activity = $last_activity ? $now->diff($last_activity)->days : null;
                        
                        // Determine activity status
                        $activity_class = 'inactive';
                        if ($customer['active_bookings'] > 0) {
                            $activity_class = '';
                        } elseif ($days_since_last_activity && $days_since_last_activity <= 30) {
                            $activity_class = 'warning';
                        }
                        ?>
                        <div class="customer-card position-relative" data-search="<?php echo strtolower($customer['full_name'] . ' ' . $customer['email'] . ' ' . $customer['username'] . ' ' . $customer['phone']); ?>">
                            <div class="activity-indicator <?php echo $activity_class; ?>" title="<?php echo $customer['active_bookings'] > 0 ? 'Memiliki booking aktif' : ($days_since_last_activity && $days_since_last_activity <= 30 ? 'Aktivitas terbaru' : 'Tidak ada aktivitas terbaru'); ?>"></div>
                            
                            <div class="customer-header">
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <?php echo strtoupper(substr($customer['full_name'], 0, 2)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($customer['full_name']); ?></h5>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($customer['email']); ?></p>
                                        <small class="text-muted">@<?php echo htmlspecialchars($customer['username']); ?></small>
                                        <div class="mt-2">
                                            <span class="status-badge status-<?php echo $customer['status']; ?>">
                                                <i class="fas fa-circle"></i>
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="customer-body">
                                <div class="customer-stats">
                                    <div class="stat-item">
                                        <div class="stat-item-label">Reptil</div>
                                        <div class="stat-item-value"><?php echo $customer['total_reptiles']; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-label">Total Booking</div>
                                        <div class="stat-item-value"><?php echo $customer['total_bookings']; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-label">Booking Aktif</div>
                                        <div class="stat-item-value text-primary"><?php echo $customer['active_bookings']; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-item-label">Total Pengeluaran</div>
                                        <div class="stat-item-value text-success">Rp <?php echo number_format($customer['total_spent'], 0, ',', '.'); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($customer['phone']): ?>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($customer['phone'] ?? ''); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($customer['address']): ?>
                                    <div class="mt-1">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($customer['address'] ?? ''); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="customer-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Bergabung: <?php echo date('d M Y', strtotime($customer['created_at'])); ?>
                                        <?php if ($customer['last_booking_date']): ?>
                                            <br><i class="fas fa-clock me-1"></i>
                                            Booking terakhir: <?php echo date('d M Y', strtotime($customer['last_booking_date'])); ?>
                                        <?php endif; ?>
                                    </small>
                                    
                                    <div class="quick-actions">
                                        <a href="view_customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-outline-primary btn-sm" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="customer_bookings.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-info btn-sm" title="Lihat Booking">
                                            <i class="fas fa-calendar-alt"></i>
                                        </a>
                                        
                                        <a href="customer_reptiles.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-success btn-sm" title="Lihat Reptil">
                                            <i class="fas fa-dragon"></i>
                                        </a>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin mengubah status customer ini?')">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $customer['status']; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $customer['status'] === 'active' ? 'danger' : 'success'; ?> btn-sm" title="<?php echo $customer['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                                <i class="fas fa-<?php echo $customer['status'] === 'active' ? 'user-times' : 'user-check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="customer_bookings.php?customer_id=<?php echo $customer['id']; ?>"><i class="fas fa-history me-2"></i>Riwayat</a></li>
                                                <li><a class="dropdown-item" href="send_message.php?customer_id=<?php echo $customer['id']; ?>"><i class="fas fa-envelope me-2"></i>Kirim Pesan</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $customer['id']; ?>)"><i class="fas fa-trash me-2"></i>Hapus</a></li>
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
            const cards = document.querySelectorAll('.customer-card');
            
            cards.forEach(card => {
                const searchData = card.dataset.search;
                const isVisible = searchData.includes(searchTerm);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
        
        // Confirm delete function
        function confirmDelete(customerId) {
            if (confirm('Yakin ingin menghapus customer ini? Tindakan ini tidak dapat dibatalkan!')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_customer">
                    <input type="hidden" name="customer_id" value="${customerId}">
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