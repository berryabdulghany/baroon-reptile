<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$reptile_id = isset($_GET['reptile_id']) ? $_GET['reptile_id'] : null;

if (!$reptile_id) {
    header('Location: reptiles.php');
    exit;
}

try {
    $db = getDB();
    
    // Get reptile info
    $stmt = $db->prepare("SELECT * FROM reptiles WHERE id = ?");
    $stmt->execute([$reptile_id]);
    $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reptile) {
        header('Location: reptiles.php');
        exit;
    }
    
    // Handle status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $booking_id = $_POST['booking_id'];
        $new_status = $_POST['status'];
        
        $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ? AND reptile_id = ?");
        if ($stmt->execute([$new_status, $booking_id, $reptile_id])) {
            $success = 'Booking status updated successfully.';
        } else {
            $error = 'Failed to update booking status.';
        }
    }
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build query
    $where_conditions = ['b.reptile_id = ?'];
    $params = [$reptile_id];
    
    if ($status_filter) {
        $where_conditions[] = 'b.status = ?';
        $params[] = $status_filter;
    }
    
    if ($date_from) {
        $where_conditions[] = 'b.start_date >= ?';
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = 'b.end_date <= ?';
        $params[] = $date_to;
    }
    
    if ($search) {
        $where_conditions[] = '(u.full_name LIKE ? OR u.email LIKE ? OR b.id LIKE ?)';
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get bookings
    $stmt = $db->prepare("
        SELECT b.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               DATEDIFF(b.end_date, b.start_date) as total_days
        FROM bookings b
        LEFT JOIN users u ON b.customer_id = u.id
        WHERE $where_clause
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reptile Bookings - <?php echo htmlspecialchars($reptile['name']); ?> - Baroon Reptile Admin</title>
    
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
        
        .page-header {
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid #2c5530;
        }
        
        .booking-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .booking-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .booking-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .meta-item i {
            color: #6c757d;
            width: 16px;
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
                <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Reptile
                </a>
                <h4 class="mb-0">Reptile Bookings</h4>
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">Bookings for <?php echo htmlspecialchars($reptile['name']); ?></h2>
                        <p class="mb-0 opacity-75"><?php echo htmlspecialchars($reptile['species'] ?? ''); ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="create_booking.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-light">
                            <i class="fas fa-plus me-2"></i>Create New Booking
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['total_bookings']; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $stats['pending_bookings']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $stats['confirmed_bookings']; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['in_progress_bookings']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $stats['completed_bookings']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-success">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="reptile_id" value="<?php echo $reptile_id; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Customer name, email, or booking ID" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="?reptile_id=<?php echo $reptile_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Bookings List -->
            <?php if (empty($bookings)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No bookings found</h5>
                    <p class="text-muted">No bookings match your current filters.</p>
                    <a href="create_booking.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create First Booking
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1">Booking #<?php echo $booking['id']; ?></h5>
                                        <span class="booking-status status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?></small>
                                </div>
                                
                                <div class="booking-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('d M Y', strtotime($booking['start_date'])); ?> - <?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo $booking['total_days']; ?> days</span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span><strong>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></strong></span>
                                    </div>
                                </div>
                                
                                <?php if ($booking['notes']): ?>
                                    <div class="mt-3">
                                        <strong>Notes:</strong>
                                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($booking['notes'] ?? '')); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="booking-actions">
                                    <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i>Confirm
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" name="update_status" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                <i class="fas fa-times me-1"></i>Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" name="update_status" class="btn btn-info btn-sm">
                                                <i class="fas fa-play me-1"></i>Start
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['status'] === 'in_progress'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                                <i class="fas fa-check-double me-1"></i>Complete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="customers.php?search=<?php echo urlencode($booking['customer_email']); ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-user me-1"></i>Customer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>