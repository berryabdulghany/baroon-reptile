<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$reptile_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$reptile_id) {
    header('Location: reptiles.php');
    exit;
}

try {
    $db = getDB();
    
    // Get reptile details with owner and category info
    $stmt = $db->prepare("
        SELECT r.*, 
               u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone,
               rc.name as category_name, rc.price_per_day, rc.description as category_description
        FROM reptiles r 
        LEFT JOIN users u ON r.customer_id = u.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reptile_id]);
    $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reptile) {
        header('Location: reptiles.php');
        exit;
    }
    
    // Get booking statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(total_price) as total_revenue
        FROM bookings 
        WHERE reptile_id = ?
    ");
    $stmt->execute([$reptile_id]);
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent bookings
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as customer_name, u.email as customer_email
        FROM bookings b
        LEFT JOIN users u ON b.customer_id = u.id
        WHERE b.reptile_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reptile_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get care reports
    $stmt = $db->prepare("
        SELECT * FROM daily_reports 
        WHERE reptile_id = ?
        ORDER BY report_date DESC, created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$reptile_id]);
    $care_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reptile - <?php echo htmlspecialchars($reptile['name']); ?> - Baroon Reptile Admin</title>
    
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
        
        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content-area {
            padding: 30px;
        }
        
        .reptile-header {
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .reptile-photo {
            width: 150px;
            height: 150px;
            border-radius: 15px;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.2);
        }
        
        .reptile-photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 15px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            border: 4px solid rgba(255,255,255,0.2);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .stat-grid {
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
        
        .booking-item {
            border-left: 4px solid #2c5530;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .booking-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .care-report-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .health-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .health-excellent { background: #d4edda; color: #155724; }
        .health-good { background: #d1ecf1; color: #0c5460; }
        .health-fair { background: #fff3cd; color: #856404; }
        .health-poor { background: #f8d7da; color: #721c24; }
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
                        <i class="fas fa-users"></i>Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="reptiles.php">
                        <i class="fas fa-dragon"></i>Reptiles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-file-alt"></i>Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="facilities.php">
                        <i class="fas fa-cogs"></i>Facilities
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
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
                <a href="reptiles.php" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Reptiles
                </a>
                <h4 class="mb-0">Reptile Details</h4>
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
            <!-- Reptile Header -->
            <div class="reptile-header">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <?php if ($reptile['photo']): ?>
                            <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($reptile['name']); ?>" class="reptile-photo">
                        <?php else: ?>
                            <div class="reptile-photo-placeholder">
                                <i class="fas fa-dragon"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-2"><?php echo htmlspecialchars($reptile['name']); ?></h2>
                        <p class="mb-2 opacity-75"><?php echo htmlspecialchars($reptile['species'] ?? ''); ?></p>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($reptile['category_name']); ?></span>
                            <span class="status-badge status-<?php echo $reptile['status']; ?>">
                                <i class="fas fa-circle me-1"></i><?php echo ucfirst($reptile['status']); ?>
                            </span>
                        </div>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-user me-2"></i>Owner: <?php echo htmlspecialchars($reptile['owner_name']); ?>
                        </p>
                    </div>
                    <div class="col-md-3 text-end">
                        <div class="mb-3">
                            <div class="h4 mb-1">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?></div>
                            <small class="opacity-75">per hari</small>
                        </div>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="edit_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Edit
                            </a>
                            <a href="create_booking.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i>Book
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $booking_stats['total_bookings']; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $booking_stats['completed_bookings']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $booking_stats['in_progress_bookings']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $booking_stats['pending_bookings']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-success">Rp <?php echo number_format($booking_stats['total_revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
            
            <div class="row">
                <!-- Reptile Information -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="mb-4"><i class="fas fa-info-circle me-2"></i>Reptile Information</h5>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Age:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($reptile['age'] ?? 'Not specified'); ?></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Gender:</strong></div>
                            <div class="col-sm-8"><?php echo ucfirst($reptile['gender'] ?? 'unknown'); ?></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Weight:</strong></div>
                            <div class="col-sm-8"><?php echo $reptile['weight'] ?? 'Not specified'; ?> grams</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Length:</strong></div>
                            <div class="col-sm-8"><?php echo $reptile['length'] ?? 'Not specified'; ?> cm</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Color:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($reptile['color'] ?? 'Not specified'); ?></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Registered:</strong></div>
                            <div class="col-sm-8"><?php echo date('d M Y H:i', strtotime($reptile['created_at'])); ?></div>
                        </div>
                        
                        <?php if ($reptile['description'] ?? ''): ?>
                            <div class="mt-4">
                                <strong>Description:</strong>
                                <p class="mt-2"><?php echo nl2br(htmlspecialchars($reptile['description'] ?? '')); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($reptile['special_needs']): ?>
                            <div class="mt-4">
                                <strong>Special Needs:</strong>
                                <p class="mt-2"><?php echo nl2br(htmlspecialchars($reptile['special_needs'] ?? '')); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Owner Information -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="mb-4"><i class="fas fa-user me-2"></i>Owner Information</h5>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Name:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($reptile['owner_name']); ?></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Email:</strong></div>
                            <div class="col-sm-8">
                                <a href="mailto:<?php echo htmlspecialchars($reptile['owner_email']); ?>">
                                    <?php echo htmlspecialchars($reptile['owner_email']); ?>
                                </a>
                            </div>
                        </div>
                        
                        <?php if ($reptile['owner_phone']): ?>
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Phone:</strong></div>
                                <div class="col-sm-8">
                                    <a href="tel:<?php echo htmlspecialchars($reptile['owner_phone']); ?>">
                                        <?php echo htmlspecialchars($reptile['owner_phone']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="customers.php?search=<?php echo urlencode($reptile['owner_email']); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-user me-2"></i>View Customer Profile
                            </a>
                        </div>
                    </div>
                    
                    <!-- Category Information -->
                    <div class="info-card">
                        <h5 class="mb-4"><i class="fas fa-tags me-2"></i>Category Information</h5>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Category:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($reptile['category_name']); ?></div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Price/Day:</strong></div>
                            <div class="col-sm-8">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?></div>
                        </div>
                        
                        <?php if ($reptile['category_description']): ?>
                            <div class="mt-3">
                                <strong>Category Description:</strong>
                                <p class="mt-2"><?php echo nl2br(htmlspecialchars($reptile['category_description'] ?? '')); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Bookings -->
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Recent Bookings</h5>
                            <a href="reptile_bookings.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
                        </div>
                        
                        <?php if (empty($recent_bookings)): ?>
                            <p class="text-muted text-center py-3">No bookings found</p>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <div class="booking-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong>#<?php echo $booking['id']; ?></strong>
                                            <span class="booking-status status-<?php echo $booking['status']; ?> ms-2">
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?php echo date('d M Y', strtotime($booking['created_at'])); ?></small>
                                    </div>
                                    <div class="small">
                                        <strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?><br>
                                        <strong>Period:</strong> <?php echo date('d M Y', strtotime($booking['start_date'])); ?> - <?php echo date('d M Y', strtotime($booking['end_date'])); ?><br>
                                        <strong>Total:</strong> Rp <?php echo number_format($booking['total_price'] ?? 0, 0, ',', '.'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Care Reports -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5 class="mb-4"><i class="fas fa-file-medical me-2"></i>Recent Care Reports</h5>
                        
                        <?php if (empty($care_reports)): ?>
                            <p class="text-muted text-center py-3">No care reports found</p>
                        <?php else: ?>
                            <?php foreach ($care_reports as $report): ?>
                                <div class="care-report-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?php echo date('d M Y', strtotime($report['report_date'])); ?></strong>
                                            <?php if ($report['feeding_time']): ?>
                                                <small class="text-muted ms-2"><?php echo date('H:i', strtotime($report['feeding_time'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="health-badge health-<?php echo $report['health_status']; ?>">
                                            <?php echo ucfirst($report['health_status']); ?>
                                        </span>
                                    </div>
                                    <div class="small">
                                        <strong>Food:</strong> <?php echo htmlspecialchars($report['feeding_notes'] ?? 'Not specified'); ?><br>
                                        <?php if ($report['temperature'] || $report['humidity']): ?>
                                            <strong>Environment:</strong>
                                            <?php if ($report['temperature']): ?>
                                                <?php echo $report['temperature']; ?>°C
                                            <?php endif; ?>
                                            <?php if ($report['humidity']): ?>
                                                <?php echo $report['temperature'] ? ', ' : ''; ?><?php echo $report['humidity']; ?>% humidity
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>