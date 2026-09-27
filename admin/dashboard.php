<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize variables to prevent undefined variable errors
$error = '';
$stats = [
    'customers' => 0,
    'reptiles' => 0,
    'active_bookings' => 0,
    'pending_bookings' => 0,
    'monthly_revenue' => 0
];
$monthly_data = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
$month_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$category_labels = ['No Data'];
$category_data = [0];
$recent_bookings = [];

try {
    $db = getDB();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get statistics
    
    // Total customers
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
    $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total reptiles
    $stmt = $db->query("SELECT COUNT(*) as count FROM reptiles WHERE status = 'active'");
    $stats['reptiles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active bookings
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status IN ('confirmed', 'in_progress')");
    $stats['active_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending bookings
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
    $stats['pending_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Monthly revenue
    $stmt = $db->query("SELECT SUM(total_price) as revenue FROM bookings WHERE status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;
    
    // Booking statistics by month (last 12 months)
    $stmt = $db->query("
        SELECT 
            MONTH(created_at) as month,
            YEAR(created_at) as year,
            COUNT(*) as count
        FROM bookings 
        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY year, month
    ");
    $booking_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare monthly data for chart
    $monthly_data = array_fill(0, 12, 0);
    $month_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    foreach ($booking_stats as $stat) {
        $month_index = $stat['month'] - 1;
        $monthly_data[$month_index] = (int)$stat['count'];
    }
    
    // Debug: Check if we have data
    error_log('Booking stats: ' . print_r($booking_stats, true));
    error_log('Monthly data: ' . print_r($monthly_data, true));
    
    // Reptile categories statistics
    $stmt = $db->query("
        SELECT 
            rc.name as category_name,
            COUNT(r.id) as count
        FROM reptile_categories rc
        LEFT JOIN reptiles r ON rc.id = r.category_id AND r.status = 'active'
        GROUP BY rc.id, rc.name
        ORDER BY count DESC
    ");
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare category data for chart
    $category_labels = [];
    $category_data = [];
    foreach ($category_stats as $stat) {
        $category_labels[] = $stat['category_name'];
        $category_data[] = (int)$stat['count'];
    }
    
    // Debug: Check category data
    error_log('Category stats: ' . print_r($category_stats, true));
    error_log('Category labels: ' . print_r($category_labels, true));
    error_log('Category data: ' . print_r($category_data, true));
    
    // Ensure we have some default data if empty
    if (empty($category_labels)) {
        $category_labels = ['No Data'];
        $category_data = [0];
    }
    if (array_sum($monthly_data) == 0) {
        $monthly_data = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    }
    
    // Recent bookings
    $stmt = $db->query("
        SELECT b.*, u.full_name as customer_name, r.name as reptile_name, rc.name as category_name
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        JOIN reptiles r ON b.reptile_id = r.id
        JOIN reptile_categories rc ON r.category_id = rc.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat memuat data.';
    
    // Initialize default values if error occurs
    if (!isset($monthly_data)) {
        $monthly_data = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    }
    if (!isset($month_labels)) {
        $month_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    }
    if (!isset($category_labels)) {
        $category_labels = ['No Data'];
    }
    if (!isset($category_data)) {
        $category_data = [0];
    }
    if (!isset($recent_bookings)) {
        $recent_bookings = [];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Baroon Reptile</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js removed - using HTML tables instead -->
    </script>
    
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
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 70px;
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
        
        .nav-item {
            margin-bottom: 5px;
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
            transition: all 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 70px;
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
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.customers {
            border-left-color: #007bff;
        }
        
        .stat-card.reptiles {
            border-left-color: #28a745;
        }
        
        .stat-card.bookings {
            border-left-color: #ffc107;
        }
        
        .stat-card.revenue {
            border-left-color: #dc3545;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.3;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .badge {
            font-size: 0.75rem;
        }
        
        .btn-toggle {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.2rem;
        }
        
        .dropdown-toggle::after {
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .content-area {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-dragon me-2"></i>
                <span class="brand-text">Baroon Admin</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
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
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-toggle me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0">Dashboard</h4>
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
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card customers">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-primary"><?php echo number_format($stats['customers']); ?></div>
                                <div class="stat-label">Total Pelanggan</div>
                            </div>
                            <i class="fas fa-users stat-icon text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card reptiles">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-success"><?php echo number_format($stats['reptiles']); ?></div>
                                <div class="stat-label">Reptil Aktif</div>
                            </div>
                            <i class="fas fa-dragon stat-icon text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card bookings">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-warning"><?php echo number_format($stats['active_bookings']); ?></div>
                                <div class="stat-label">Booking Aktif</div>
                                <?php if ($stats['pending_bookings'] > 0): ?>
                                    <small class="text-danger"><?php echo $stats['pending_bookings']; ?> menunggu</small>
                                <?php endif; ?>
                            </div>
                            <i class="fas fa-calendar-alt stat-icon text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card revenue">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-danger">Rp <?php echo number_format($stats['monthly_revenue'], 0, ',', '.'); ?></div>
                                <div class="stat-label">Pendapatan Bulanan</div>
                            </div>
                            <i class="fas fa-chart-line stat-icon text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Tables Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistik Booking Bulanan</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Bulan</th>
                                      <th>Total Booking</th>
                                      <th>Persentase</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_bookings = array_sum($monthly_data);
                                        for ($i = 0; $i < count($month_labels); $i++): 
                                            $percentage = $total_bookings > 0 ? round(($monthly_data[$i] / $total_bookings) * 100, 1) : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo $month_labels[$i]; ?></strong></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $monthly_data[$i]; ?></span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%" 
                                                         aria-valuenow="<?php echo $percentage; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th>Total</th>
                                            <th><span class="badge bg-dark"><?php echo $total_bookings; ?></span></th>
                                            <th>100%</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Kategori Reptil</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Jumlah</th>
                                            <th>Persentase</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_categories = array_sum($category_data);
                                        $colors = ['primary', 'success', 'warning', 'info', 'secondary', 'danger'];
                                        for ($i = 0; $i < count($category_labels); $i++): 
                                            $percentage = $total_categories > 0 ? round(($category_data[$i] / $total_categories) * 100, 1) : 0;
                                            $color = $colors[$i % count($colors)];
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($category_labels[$i]); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $category_data[$i]; ?></span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%" 
                                                         aria-valuenow="<?php echo $percentage; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th>Total</th>
                                            <th><span class="badge bg-dark"><?php echo $total_categories; ?></span></th>
                                            <th>100%</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Booking Terbaru</h5>
                            <a href="bookings.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Pelanggan</th>
                                            <th>Reptil</th>
                                            <th>Kategori</th>
                                            <th>Tanggal Mulai</th>
                                            <th>Tanggal Selesai</th>
                                            <th>Total Harga</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_bookings)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">Tidak ada booking ditemukan</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td>#<?php echo $booking['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($booking['reptile_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($booking['category_name']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($booking['start_date'])); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($booking['end_date'])); ?></td>
                                                    <td>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class = [
                                                            'pending' => 'warning',
                                                            'confirmed' => 'info',
                                                            'in_progress' => 'primary',
                                                            'completed' => 'success',
                                                            'cancelled' => 'danger'
                                                        ];
                                                        $status_text = [
                                                            'pending' => 'Menunggu',
                                                            'confirmed' => 'Dikonfirmasi',
                                                            'in_progress' => 'Sedang Berlangsung',
                                                            'completed' => 'Selesai',
                                                            'cancelled' => 'Dibatalkan'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class[$booking['status']]; ?>">
                                                            <?php echo $status_text[$booking['status']]; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
        

    </script>
</body>
</html>