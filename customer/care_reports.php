<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';

try {
    $db = getDB();
    $customer_id = $_SESSION['user_id'];
    
    // Note: Customers can only view care reports, not create or delete them
    // Care reports are created by admin staff only
    
    // Get user's reptiles for dropdown
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name 
        FROM reptiles r 
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id 
        WHERE r.customer_id = ? AND r.status = 'active'
        ORDER BY r.name
    ");
    $stmt->execute([$customer_id]);
    $user_reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get filters
    $reptile_filter = isset($_GET['reptile']) ? $_GET['reptile'] : 'all';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    // Build query for care reports
    $where_clause = "WHERE r.customer_id = ?";
    $params = [$customer_id];
    
    if ($reptile_filter !== 'all') {
        $where_clause .= " AND dr.reptile_id = ?";
        $params[] = $reptile_filter;
    }
    
    if ($date_from) {
        $where_clause .= " AND dr.report_date >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_clause .= " AND dr.report_date <= ?";
        $params[] = $date_to;
    }
    
    // Get care reports
    $stmt = $db->prepare("
        SELECT dr.*, r.name as reptile_name, r.photo as reptile_photo, rc.name as category_name
        FROM daily_reports dr
        JOIN reptiles r ON dr.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        $where_clause
        ORDER BY dr.report_date DESC, dr.created_at DESC
    ");
    $stmt->execute($params);
    $care_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_reports,
            COUNT(DISTINCT dr.reptile_id) as reptiles_with_reports,
            SUM(CASE WHEN dr.report_date = CURDATE() THEN 1 ELSE 0 END) as reports_today,
            SUM(CASE WHEN dr.report_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as reports_this_week
        FROM daily_reports dr
        JOIN reptiles r ON dr.reptile_id = r.id
        WHERE r.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
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
    <title>Care Reports - Baroon Reptile</title>
    
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
            display: flex;
            align-items: center;
            text-decoration: none;
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
            min-height: 100vh;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .brand-text,
        .sidebar.collapsed .nav-text {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-brand {
            justify-content: center;
        }
        
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-toggle {
            background: none;
            border: none;
            color: #2c5530;
            font-size: 1.2rem;
            margin-right: 15px;
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
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
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
        
        .filter-controls {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .report-body {
            padding: 20px;
        }
        
        .health-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .health-excellent {
            background: #d4edda;
            color: #155724;
        }
        
        .health-good {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .health-fair {
            background: #fff3cd;
            color: #856404;
        }
        
        .health-poor {
            background: #f8d7da;
            color: #721c24;
        }
        
        .reptile-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .reptile-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .care-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .metric-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .metric-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c5530;
        }
        
        .metric-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-dragon me-2"></i>
                <span class="brand-text">Baroon</span>
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
                    <a class="nav-link" href="my_reptiles.php">
                        <i class="fas fa-dragon"></i>
                        <span class="nav-text">My Reptiles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Bookings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>
                        <span class="nav-text">Payments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="care_reports.php">
                        <i class="fas fa-file-alt"></i>
                        <span class="nav-text">Care Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>
                        <span class="nav-text">Profile</span>
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
                <h4 class="mb-0">Care Reports</h4>
                <span class="badge bg-primary ms-3"><?php echo $stats['total_reports']; ?> Reports</span>
            </div>
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['total_reports']; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-dragon"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $stats['reptiles_with_reports']; ?></div>
                    <div class="stat-label">Reptiles with Reports</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $stats['reports_today']; ?></div>
                    <div class="stat-label">Reports Today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $stats['reports_this_week']; ?></div>
                    <div class="stat-label">Reports This Week</div>
                </div>
            </div>
            
            <!-- Filter and Add Report -->
            <div class="filter-controls">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="form-group">
                                <label class="form-label mb-1">Reptile:</label>
                                <select name="reptile" class="form-select form-select-sm">
                                    <option value="all">All Reptiles</option>
                                    <?php foreach ($user_reptiles as $reptile): ?>
                                        <option value="<?php echo $reptile['id']; ?>" <?php echo $reptile_filter == $reptile['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($reptile['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label mb-1">From:</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label mb-1">To:</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            
                            <a href="care_reports.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <!-- Customers can only view care reports -->
                    </div>
                </div>
            </div>
            
            <!-- Care Reports List -->
            <?php if (empty($care_reports)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No care reports found</h5>
                    <p class="text-muted">Care reports will be created by our staff during your reptile's stay.</p>
                </div>
            <?php else: ?>
                <?php foreach ($care_reports as $report): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <strong><?php echo date('d M Y', strtotime($report['report_date'])); ?></strong>
                                <?php if ($report['feeding_time']): ?>
                                    <span class="ms-3">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('H:i', strtotime($report['feeding_time'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php
                                $health_class = '';
                                switch ($report['health_status']) {
                                    case 'excellent': $health_class = 'health-excellent'; break;
                                    case 'good': $health_class = 'health-good'; break;
                                    case 'fair': $health_class = 'health-fair'; break;
                                    case 'poor': $health_class = 'health-poor'; break;
                                }
                                ?>
                                <span class="health-badge <?php echo $health_class; ?>">
                                    <?php echo ucfirst($report['health_status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="report-body">
                            <div class="reptile-info">
                                <?php if ($report['reptile_photo']): ?>
                                    <img src="../<?php echo htmlspecialchars($report['reptile_photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($report['reptile_name']); ?>" class="reptile-photo">
                                <?php else: ?>
                                    <div class="reptile-photo bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="fas fa-dragon text-white"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($report['reptile_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($report['category_name']); ?></small>
                                </div>
                            </div>
                            
                            <div class="care-metrics">
                                <?php if ($report['feeding_notes']): ?>
                                    <div class="metric-item">
                                        <div class="metric-value"><?php echo htmlspecialchars($report['feeding_notes'] ?? ''); ?></div>
                                        <div class="metric-label">Feeding Notes</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($report['activity_level']): ?>
                                    <div class="metric-item">
                                        <div class="metric-value"><?php echo ucfirst(str_replace('_', ' ', $report['activity_level'])); ?></div>
                                        <div class="metric-label">Activity Level</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($report['temperature']): ?>
                                    <div class="metric-item">
                                        <div class="metric-value"><?php echo $report['temperature']; ?>°C</div>
                                        <div class="metric-label">Temperature</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($report['humidity']): ?>
                                    <div class="metric-item">
                                        <div class="metric-value"><?php echo $report['humidity']; ?>%</div>
                                        <div class="metric-label">Humidity</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($report['notes']): ?>
                                <div class="mt-3">
                                    <h6><i class="fas fa-notes-medical me-2"></i>Notes:</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['notes'] ?? '')); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($report['photos']): ?>
                                <div class="mt-3">
                                    <h6><i class="fas fa-camera me-2"></i>Photos:</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($report['photos']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>



    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    </script>
</body>
</html>