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
    
    // Handle status update
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $booking_id = $_POST['booking_id'];
        $new_status = $_POST['status'];
        
        // Validate status
        $valid_statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $db->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$new_status, $booking_id])) {
                $message = 'success:Status booking berhasil diupdate!';
            } else {
                $message = 'error:Gagal mengupdate status booking!';
            }
        } else {
            $message = 'error:Status tidak valid!';
        }
    }
    
    // Get filter
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';
    
    // Build query
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_clause .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_filter === 'today') {
        $where_clause .= " AND DATE(b.start_date) = CURDATE()";
    } elseif ($date_filter === 'this_week') {
        $where_clause .= " AND WEEK(b.start_date) = WEEK(CURDATE()) AND YEAR(b.start_date) = YEAR(CURDATE())";
    } elseif ($date_filter === 'this_month') {
        $where_clause .= " AND MONTH(b.start_date) = MONTH(CURDATE()) AND YEAR(b.start_date) = YEAR(CURDATE())";
    }
    
    // Get all bookings with customer and reptile info
    $stmt = $db->prepare("
        SELECT b.*, 
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               r.name as reptile_name, r.photo as reptile_photo,
               rc.name as category_name,
               p.payment_status, p.amount as payment_amount,
               DATEDIFF(b.end_date, b.start_date) as total_days
        FROM bookings b 
        LEFT JOIN users u ON b.customer_id = u.id
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        LEFT JOIN payments p ON b.id = p.booking_id
        $where_clause
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(total_price) as total_revenue
        FROM bookings
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure $stats is properly initialized if query returns null
    if (!$stats) {
        $stats = [
            'total_bookings' => 0,
            'pending_bookings' => 0,
            'confirmed_bookings' => 0,
            'active_bookings' => 0,
            'completed_bookings' => 0,
            'cancelled_bookings' => 0,
            'total_revenue' => 0
        ];
    }
    
} catch (Exception $e) {
    // Log the actual error for debugging (in production, log to file instead)
    error_log("Booking page error: " . $e->getMessage());
    $message = 'error:Terjadi kesalahan sistem: ' . $e->getMessage();
    
    // Initialize $stats with default values to prevent undefined variable errors
    $stats = [
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'confirmed_bookings' => 0,
        'active_bookings' => 0,
        'completed_bookings' => 0,
        'cancelled_bookings' => 0,
        'total_revenue' => 0
    ];
    $bookings = [];
}

// Status configurations
$status_config = [
    'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Menunggu Konfirmasi'],
    'confirmed' => ['class' => 'info', 'icon' => 'check-circle', 'text' => 'Dikonfirmasi'],
    'in_progress' => ['class' => 'primary', 'icon' => 'play-circle', 'text' => 'Sedang Berlangsung'],
    'completed' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Selesai'],
    'cancelled' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Dibatalkan']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Baroon Reptile Admin</title>
    
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
        
        .booking-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .booking-header {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .booking-body {
            padding: 20px;
        }
        
        .booking-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #f1f3f4;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .customer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .reptile-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .reptile-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .booking-dates {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .date-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .date-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .date-value {
            font-weight: 600;
            color: #2c5530;
        }
        
        .filter-tabs {
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
        
        .status-select {
            border: none;
            background: transparent;
            font-weight: 600;
            cursor: pointer;
        }
        
        .priority-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .priority-high {
            background: #dc3545;
            color: white;
        }
        
        .priority-medium {
            background: #ffc107;
            color: #000;
        }
        
        .priority-low {
            background: #28a745;
            color: white;
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
                    <a class="nav-link" href="reptiles.php">
                        <i class="fas fa-dragon"></i>Reptil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="bookings.php">
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
                <h4 class="mb-0">Manage Bookings</h4>
                <span class="badge bg-primary ms-3"><?php echo $stats['total_bookings']; ?> Total</span>
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
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock"></i>
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
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['active_bookings']; ?></div>
                    <div class="stat-label">Active</div>
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
            
            <!-- Filter and Actions -->
            <div class="filter-tabs">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="btn-group" role="group">
                                <a href="?status=all&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Semua</a>
                                <a href="?status=pending&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pending</a>
                                <a href="?status=confirmed&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'confirmed' ? 'btn-info' : 'btn-outline-info'; ?>">Confirmed</a>
                                <a href="?status=in_progress&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'in_progress' ? 'btn-primary' : 'btn-outline-primary'; ?>">Active</a>
                                <a href="?status=completed&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'completed' ? 'btn-success' : 'btn-outline-success'; ?>">Completed</a>
                            </div>
                            
                            <div class="btn-group" role="group">
                                <a href="?status=<?php echo $status_filter; ?>&date=all" class="btn <?php echo $date_filter === 'all' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Semua Tanggal</a>
                                <a href="?status=<?php echo $status_filter; ?>&date=today" class="btn <?php echo $date_filter === 'today' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Hari Ini</a>
                                <a href="?status=<?php echo $status_filter; ?>&date=this_week" class="btn <?php echo $date_filter === 'this_week' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Minggu Ini</a>
                                <a href="?status=<?php echo $status_filter; ?>&date=this_month" class="btn <?php echo $date_filter === 'this_month' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Bulan Ini</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari booking...">
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($bookings)): ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h5>Tidak Ada Booking</h5>
                    <p class="text-muted">Tidak ada booking yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <!-- Bookings List -->
                <div id="bookingsList">
                    <?php foreach ($bookings as $booking): ?>
                        <?php 
                        $status = $status_config[$booking['status']];
                        $start_date = new DateTime($booking['start_date']);
                        $today = new DateTime();
                        $days_until_start = $start_date->diff($today)->days;
                        
                        // Determine priority
                        $priority = '';
                        if ($booking['status'] === 'pending' && $days_until_start <= 1) {
                            $priority = 'high';
                        } elseif ($booking['status'] === 'confirmed' && $days_until_start <= 3) {
                            $priority = 'medium';
                        } elseif ($booking['status'] === 'in_progress') {
                            $priority = 'low';
                        }
                        ?>
                        <div class="booking-card position-relative" data-search="<?php echo strtolower($booking['customer_name'] . ' ' . $booking['reptile_name'] . ' ' . $booking['category_name'] . ' ' . $booking['id']); ?>">
                            <?php if ($priority): ?>
                                <div class="priority-badge priority-<?php echo $priority; ?>">
                                    <?php echo $priority === 'high' ? 'URGENT' : ($priority === 'medium' ? 'SOON' : 'ACTIVE'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="booking-header">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="customer-info">
                                            <div class="customer-avatar">
                                                <?php echo strtoupper(substr($booking['customer_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($booking['customer_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['customer_email']); ?></small>
                                                <?php if ($booking['customer_phone']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($booking['customer_phone'] ?? ''); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="reptile-info">
                                            <?php if ($booking['reptile_photo']): ?>
                                                <img src="../<?php echo htmlspecialchars($booking['reptile_photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($booking['reptile_name']); ?>" class="reptile-image">
                                            <?php else: ?>
                                                <div class="reptile-image d-flex align-items-center justify-content-center bg-light">
                                                    <i class="fas fa-dragon text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($booking['reptile_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['category_name']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="d-flex align-items-center justify-content-md-end gap-3">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <select name="status" class="status-select bg-<?php echo $status['class']; ?> text-white rounded px-2 py-1" onchange="this.form.submit()">
                                                    <?php foreach ($status_config as $key => $config): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $booking['status'] === $key ? 'selected' : ''; ?>>
                                                            <?php echo $config['text']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <small class="text-muted">ID: #<?php echo $booking['id']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="booking-body">
                                <div class="booking-dates">
                                    <div class="date-item">
                                        <div class="date-label">Tanggal Mulai</div>
                                        <div class="date-value"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></div>
                                    </div>
                                    <div class="date-item">
                                        <div class="date-label">Tanggal Selesai</div>
                                        <div class="date-value"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></div>
                                    </div>
                                    <div class="date-item">
                                        <div class="date-label">Durasi</div>
                                        <div class="date-value"><?php echo $booking['total_days']; ?> Hari</div>
                                    </div>
                                    <div class="date-item">
                                        <div class="date-label">Total Biaya</div>
                                        <div class="date-value text-success">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></div>
                                    </div>
                                </div>
                                
                                <?php if (isset($booking['facilities']) && $booking['facilities']): ?>
                    <div class="mt-3">
                        <strong>Fasilitas Tambahan:</strong>
                        <span class="text-muted"><?php echo htmlspecialchars($booking['facilities']); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($booking['special_instructions']) && $booking['special_instructions']): ?>
                    <div class="mt-2">
                        <strong>Instruksi Khusus:</strong>
                        <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($booking['special_instructions'])); ?></p>
                    </div>
                <?php endif; ?>
                            </div>
                            
                            <div class="booking-footer">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Dibuat: <?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?>
                                        </small>
                                        <?php if ($booking['payment_status']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-credit-card me-1"></i>
                                                Payment: 
                                                <span class="badge bg-<?php echo $booking['payment_status'] === 'paid' ? 'success' : ($booking['payment_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($booking['payment_status']); ?>
                                                </span>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <div class="btn-group" role="group">
                                            <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'in_progress'): ?>
                                                <a href="daily_report.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-file-alt me-1"></i>Laporan
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!$booking['payment_status'] && $booking['status'] === 'confirmed'): ?>
                                                <a href="create_payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-plus me-1"></i>Payment
                                                </a>
                                            <?php endif; ?>
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
            const cards = document.querySelectorAll('.booking-card');
            
            cards.forEach(card => {
                const searchData = card.dataset.search;
                const isVisible = searchData.includes(searchTerm);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
        
        // Auto-refresh every 30 seconds for real-time updates
        setInterval(function() {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>