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
    
    // Handle create daily report
    if (isset($_POST['action']) && $_POST['action'] === 'create_report') {
        $report_date = $_POST['report_date'];
        $total_bookings = $_POST['total_bookings'];
        $total_revenue = $_POST['total_revenue'];
        $active_reptiles = $_POST['active_reptiles'];
        $notes = $_POST['notes'] ?? '';
        
        // Check if report already exists for this date
        $stmt = $db->prepare("SELECT id FROM daily_business_reports WHERE report_date = ?");
        $stmt->execute([$report_date]);
        if ($stmt->fetchColumn()) {
            $message = 'error:Report untuk tanggal ini sudah ada!';
        } else {
            $stmt = $db->prepare("
                INSERT INTO daily_business_reports (report_date, total_bookings, total_revenue, active_reptiles, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            if ($stmt->execute([$report_date, $total_bookings, $total_revenue, $active_reptiles, $notes])) {
                $message = 'success:Daily report berhasil dibuat!';
            } else {
                $message = 'error:Gagal membuat daily report!';
            }
        }
    }
    
    // Handle update report
    if (isset($_POST['action']) && $_POST['action'] === 'update_report') {
        $report_id = $_POST['report_id'];
        $total_bookings = $_POST['total_bookings'];
        $total_revenue = $_POST['total_revenue'];
        $active_reptiles = $_POST['active_reptiles'];
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $db->prepare("
            UPDATE daily_business_reports 
            SET total_bookings = ?, total_revenue = ?, active_reptiles = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if ($stmt->execute([$total_bookings, $total_revenue, $active_reptiles, $notes, $report_id])) {
            $message = 'success:Daily report berhasil diupdate!';
        } else {
            $message = 'error:Gagal mengupdate daily report!';
        }
    }
    
    // Handle delete report
    if (isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $report_id = $_POST['report_id'];
        
        $stmt = $db->prepare("DELETE FROM daily_business_reports WHERE id = ?");
        if ($stmt->execute([$report_id])) {
            $message = 'success:Daily report berhasil dihapus!';
        } else {
            $message = 'error:Gagal menghapus daily report!';
        }
    }
    
    // Get filters
    $date_filter = isset($_GET['date']) ? $_GET['date'] : 'this_month';
    $sort_filter = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    
    // Build query
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($date_filter === 'today') {
        $where_clause .= " AND report_date = CURDATE()";
    } elseif ($date_filter === 'this_week') {
        $where_clause .= " AND WEEK(report_date) = WEEK(CURDATE()) AND YEAR(report_date) = YEAR(CURDATE())";
    } elseif ($date_filter === 'this_month') {
        $where_clause .= " AND MONTH(report_date) = MONTH(CURDATE()) AND YEAR(report_date) = YEAR(CURDATE())";
    } elseif ($date_filter === 'last_month') {
        $where_clause .= " AND MONTH(report_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(report_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
    }
    
    // Sort order
    $order_clause = "ORDER BY report_date DESC";
    if ($sort_filter === 'oldest') {
        $order_clause = "ORDER BY report_date ASC";
    } elseif ($sort_filter === 'revenue_high') {
        $order_clause = "ORDER BY total_revenue DESC";
    } elseif ($sort_filter === 'revenue_low') {
        $order_clause = "ORDER BY total_revenue ASC";
    } elseif ($sort_filter === 'bookings_high') {
        $order_clause = "ORDER BY total_bookings DESC";
    }
    
    // Get all daily reports
    $stmt = $db->prepare("
        SELECT * FROM daily_business_reports 
        $where_clause
        $order_clause
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_reports,
            SUM(total_bookings) as total_bookings_sum,
            SUM(total_revenue) as total_revenue_sum,
            AVG(total_revenue) as avg_daily_revenue,
            AVG(total_bookings) as avg_daily_bookings,
            MAX(total_revenue) as max_daily_revenue,
            MIN(total_revenue) as min_daily_revenue
        FROM daily_business_reports
        WHERE MONTH(report_date) = MONTH(CURDATE()) AND YEAR(report_date) = YEAR(CURDATE())
    ");
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure $summary is not null
    if (!$summary) {
        $summary = [
            'total_reports' => 0,
            'total_bookings_sum' => 0,
            'total_revenue_sum' => 0,
            'avg_daily_revenue' => 0,
            'avg_daily_bookings' => 0,
            'max_daily_revenue' => 0,
            'min_daily_revenue' => 0
        ];
    }
    
    // Get monthly comparison
    $stmt = $db->query("
        SELECT 
            MONTH(report_date) as month,
            YEAR(report_date) as year,
            SUM(total_revenue) as monthly_revenue,
            SUM(total_bookings) as monthly_bookings,
            COUNT(*) as report_days
        FROM daily_business_reports
        WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(report_date), MONTH(report_date)
        ORDER BY year DESC, month DESC
        LIMIT 6
    ");
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's auto-generated data for quick report creation
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) as today_bookings
        FROM bookings 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $today_bookings = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as today_revenue
        FROM payments 
        WHERE DATE(created_at) = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$today]);
    $today_revenue = $stmt->fetchColumn();
    
    $stmt = $db->query("
        SELECT COUNT(*) as active_reptiles
        FROM reptiles 
        WHERE status = 'active'
    ");
    $active_reptiles_count = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
    error_log("Error in reports.php: " . $e->getMessage());
    
    // Initialize variables with default values to prevent undefined variable errors
    $date_filter = isset($_GET['date']) ? $_GET['date'] : 'this_month';
    $sort_filter = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    
    $summary = [
        'total_reports' => 0,
        'total_bookings_sum' => 0,
        'total_revenue_sum' => 0,
        'avg_daily_revenue' => 0,
        'avg_daily_bookings' => 0,
        'max_daily_revenue' => 0,
        'min_daily_revenue' => 0
    ];
    $reports = [];
    $monthly_data = [];
    $today_bookings = 0;
    $today_revenue = 0;
    $active_reptiles_count = 0;
}

$month_names = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Baroon Reptile Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .report-header {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
        
        .report-body {
            padding: 20px;
        }
        
        .report-footer {
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
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .metric-display {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 10px 0;
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c5530;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .date-badge {
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-block;
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
                    <a class="nav-link active" href="reports.php">
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
                <h4 class="mb-0">Reports & Analytics</h4>
                <span class="badge bg-primary ms-3"><?php echo $summary['total_reports']; ?> Reports</span>
                <button class="btn btn-success ms-3" data-bs-toggle="modal" data-bs-target="#createReportModal">
                    <i class="fas fa-plus me-2"></i>Create Report
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
            
            <!-- Summary Statistics -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $summary['total_reports']; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-success">Rp <?php echo number_format($summary['total_revenue_sum'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $summary['total_bookings_sum']; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value text-warning">Rp <?php echo number_format($summary['avg_daily_revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Avg Daily Revenue</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="metric-display">
                            <div class="metric-value"><?php echo $today_bookings; ?></div>
                            <div class="metric-label">Today's Bookings</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-display">
                            <div class="metric-value">Rp <?php echo number_format($today_revenue, 0, ',', '.'); ?></div>
                            <div class="metric-label">Today's Revenue</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-display">
                            <div class="metric-value"><?php echo $active_reptiles_count; ?></div>
                            <div class="metric-label">Active Reptiles</div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <button class="btn btn-primary" onclick="createTodayReport()">
                        <i class="fas fa-plus me-2"></i>Create Today's Report
                    </button>
                    
                    <!-- Export Dropdown -->
                    <div class="btn-group ms-2" role="group">
                        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </button>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header"><i class="fas fa-calendar me-2"></i>By Period</h6></li>
                            <li><a class="dropdown-item" href="export_reports.php?date=today" target="_blank">
                                <i class="fas fa-calendar-day me-2"></i>Today's Reports
                            </a></li>
                            <li><a class="dropdown-item" href="export_reports.php?date=this_week" target="_blank">
                                <i class="fas fa-calendar-week me-2"></i>This Week
                            </a></li>
                            <li><a class="dropdown-item" href="export_reports.php?date=this_month" target="_blank">
                                <i class="fas fa-calendar-alt me-2"></i>This Month
                            </a></li>
                            <li><a class="dropdown-item" href="export_reports.php?date=last_month" target="_blank">
                                <i class="fas fa-calendar-minus me-2"></i>Last Month
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header"><i class="fas fa-filter me-2"></i>Current Filter</h6></li>
                            <li><a class="dropdown-item" href="export_reports.php?date=<?php echo $date_filter; ?>" target="_blank">
                                <i class="fas fa-download me-2"></i>Export Current View
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="export_reports.php" target="_blank">
                                <i class="fas fa-file-export me-2"></i>All Reports
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Trend Chart -->
            <?php if (!empty($monthly_data)): ?>
                <div class="chart-container">
                    <h6 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Monthly Trends</h6>
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>
            <?php endif; ?>
            
            <!-- Filter and Controls -->
            <div class="filter-controls">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="btn-group" role="group">
                                <a href="?date=today&sort=<?php echo $sort_filter; ?>" class="btn <?php echo $date_filter === 'today' ? 'btn-primary' : 'btn-outline-primary'; ?>">Hari Ini</a>
                                <a href="?date=this_week&sort=<?php echo $sort_filter; ?>" class="btn <?php echo $date_filter === 'this_week' ? 'btn-primary' : 'btn-outline-primary'; ?>">Minggu Ini</a>
                                <a href="?date=this_month&sort=<?php echo $sort_filter; ?>" class="btn <?php echo $date_filter === 'this_month' ? 'btn-primary' : 'btn-outline-primary'; ?>">Bulan Ini</a>
                                <a href="?date=last_month&sort=<?php echo $sort_filter; ?>" class="btn <?php echo $date_filter === 'last_month' ? 'btn-primary' : 'btn-outline-primary'; ?>">Bulan Lalu</a>
                                <a href="?date=all&sort=<?php echo $sort_filter; ?>" class="btn <?php echo $date_filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">Semua</a>
                            </div>
                            
                            <select class="form-select" style="width: auto;" onchange="window.location.href='?date=<?php echo $date_filter; ?>&sort=' + this.value">
                                <option value="newest" <?php echo $sort_filter === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                                <option value="oldest" <?php echo $sort_filter === 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                                <option value="revenue_high" <?php echo $sort_filter === 'revenue_high' ? 'selected' : ''; ?>>Revenue Tertinggi</option>
                                <option value="revenue_low" <?php echo $sort_filter === 'revenue_low' ? 'selected' : ''; ?>>Revenue Terendah</option>
                                <option value="bookings_high" <?php echo $sort_filter === 'bookings_high' ? 'selected' : ''; ?>>Bookings Terbanyak</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari report...">
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($reports)): ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h5>Tidak Ada Report</h5>
                    <p class="text-muted">Tidak ada report yang sesuai dengan filter yang dipilih.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createReportModal">
                        <i class="fas fa-plus me-2"></i>Buat Report Pertama
                    </button>
                </div>
            <?php else: ?>
                <!-- Reports List -->
                <div id="reportsList">
                    <?php foreach ($reports as $report): ?>
                        <div class="report-card" data-search="<?php echo strtolower(date('d M Y', strtotime($report['report_date'])) . ' ' . $report['notes']); ?>">
                            <div class="report-header">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="date-badge">
                                            <i class="fas fa-calendar me-2"></i>
                                            <?php echo date('d M Y', strtotime($report['report_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="metric-value">Rp <?php echo number_format($report['total_revenue'], 0, ',', '.'); ?></div>
                                        <div class="metric-label">Total Revenue</div>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="editReport(<?php echo $report['id']; ?>)"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteReport(<?php echo $report['id']; ?>)"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="report-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="metric-display">
                                            <div class="metric-value text-primary"><?php echo $report['total_bookings']; ?></div>
                                            <div class="metric-label">Total Bookings</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="metric-display">
                                            <div class="metric-value text-info"><?php echo $report['active_reptiles']; ?></div>
                                            <div class="metric-label">Active Reptiles</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="metric-display">
                                            <div class="metric-value text-success">Rp <?php echo number_format($report['total_revenue'] / max($report['total_bookings'], 1), 0, ',', '.'); ?></div>
                                            <div class="metric-label">Avg per Booking</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($report['notes']): ?>
                                    <div class="mt-3">
                                        <strong>Notes:</strong>
                                        <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($report['notes'] ?? '')); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="report-footer">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Created: <?php echo date('d M Y H:i', strtotime($report['created_at'])); ?>
                                        </small>
                                        <?php if ($report['updated_at']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-edit me-1"></i>
                                                Updated: <?php echo date('d M Y H:i', strtotime($report['updated_at'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <small class="text-muted">Report ID: #<?php echo $report['id']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Report Modal -->
    <div class="modal fade" id="createReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Daily Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_report">
                        
                        <div class="mb-3">
                            <label for="report_date" class="form-label">Report Date</label>
                            <input type="date" class="form-control" id="report_date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="total_bookings" class="form-label">Total Bookings</label>
                            <input type="number" class="form-control" id="total_bookings" name="total_bookings" value="<?php echo $today_bookings; ?>" required min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="total_revenue" class="form-label">Total Revenue</label>
                            <input type="number" class="form-control" id="total_revenue" name="total_revenue" value="<?php echo $today_revenue; ?>" required min="0" step="0.01">
                        </div>
                        
                        <div class="mb-3">
                            <label for="active_reptiles" class="form-label">Active Reptiles</label>
                            <input type="number" class="form-control" id="active_reptiles" name="active_reptiles" value="<?php echo $active_reptiles_count; ?>" required min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes about today's operations..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Report Modal -->
    <div class="modal fade" id="editReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Daily Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editReportForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_report">
                        <input type="hidden" name="report_id" id="edit_report_id">
                        
                        <div class="mb-3">
                            <label for="edit_total_bookings" class="form-label">Total Bookings</label>
                            <input type="number" class="form-control" id="edit_total_bookings" name="total_bookings" required min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_total_revenue" class="form-label">Total Revenue</label>
                            <input type="number" class="form-control" id="edit_total_revenue" name="total_revenue" required min="0" step="0.01">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_active_reptiles" class="form-label">Active Reptiles</label>
                            <input type="number" class="form-control" id="edit_active_reptiles" name="active_reptiles" required min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Report</button>
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
            const cards = document.querySelectorAll('.report-card');
            
            cards.forEach(card => {
                const searchData = card.dataset.search;
                const isVisible = searchData.includes(searchTerm);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
        
        // Create today's report with pre-filled data
        function createTodayReport() {
            document.getElementById('report_date').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('total_bookings').value = '<?php echo $today_bookings; ?>';
            document.getElementById('total_revenue').value = '<?php echo $today_revenue; ?>';
            document.getElementById('active_reptiles').value = '<?php echo $active_reptiles_count; ?>';
            
            const modal = new bootstrap.Modal(document.getElementById('createReportModal'));
            modal.show();
        }
        
        // Edit report function
        function editReport(reportId) {
            // Find the report data from the page
            const reportCard = document.querySelector(`[data-search*="${reportId}"]`);
            if (!reportCard) return;
            
            // You would typically fetch this data via AJAX
            // For now, we'll use a simple approach
            document.getElementById('edit_report_id').value = reportId;
            
            const modal = new bootstrap.Modal(document.getElementById('editReportModal'));
            modal.show();
        }
        
        // Delete report function
        function deleteReport(reportId) {
            if (confirm('Yakin ingin menghapus report ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_report">
                    <input type="hidden" name="report_id" value="${reportId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Monthly chart
        <?php if (!empty($monthly_data)): ?>
        const monthlyData = <?php echo json_encode(array_reverse($monthly_data)); ?>;
        
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => `${item.month}/${item.year}`),
                datasets: [{
                    label: 'Revenue',
                    data: monthlyData.map(item => item.monthly_revenue),
                    borderColor: '#2c5530',
                    backgroundColor: 'rgba(44, 85, 48, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Bookings',
                    data: monthlyData.map(item => item.monthly_bookings),
                    borderColor: '#4a7c59',
                    backgroundColor: 'rgba(74, 124, 89, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (Rp)'
                        },
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Bookings'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>