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
    
    // Handle cancel booking
    if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
        $booking_id = $_GET['id'];
        
        // Check if booking belongs to current user and can be cancelled
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ? AND customer_id = ? AND status IN ('pending', 'confirmed')");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            // Update booking status to cancelled
            $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$booking_id])) {
                $message = 'success:Booking berhasil dibatalkan!';
            } else {
                $message = 'error:Gagal membatalkan booking!';
            }
        } else {
            $message = 'error:Booking tidak ditemukan atau tidak dapat dibatalkan!';
        }
    }
    
    // Get filter
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    // Build query
    $where_clause = "WHERE b.customer_id = ?";
    $params = [$_SESSION['user_id']];
    
    if ($status_filter !== 'all') {
        $where_clause .= " AND b.status = ?";
        $params[] = $status_filter;
    }
    
    // Get user's bookings with reptile and payment info
    $stmt = $db->prepare("
        SELECT b.*, r.name as reptile_name, r.photo as reptile_photo,
               rc.name as category_name,
               p.payment_status, p.amount as payment_amount,
               DATEDIFF(b.end_date, b.start_date) as total_days,
               (DATEDIFF(b.end_date, b.start_date) * rc.price_per_day) as base_cost,
               (b.total_price - (DATEDIFF(b.end_date, b.start_date) * rc.price_per_day)) as facility_cost
        FROM bookings b 
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        LEFT JOIN payments p ON b.id = p.booking_id
        $where_clause
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
        FROM bookings 
        WHERE customer_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure all stats values are not null
    if (!$stats) {
        $stats = [
            'total_bookings' => 0,
            'pending_bookings' => 0,
            'confirmed_bookings' => 0,
            'active_bookings' => 0,
            'completed_bookings' => 0,
            'cancelled_bookings' => 0
        ];
    } else {
        $stats = array_merge([
            'total_bookings' => 0,
            'pending_bookings' => 0,
            'confirmed_bookings' => 0,
            'active_bookings' => 0,
            'completed_bookings' => 0,
            'cancelled_bookings' => 0
        ], $stats);
    }
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
    // Log error for debugging
    error_log('Bookings Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    // Initialize stats with default values in case of error
    $stats = [
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'confirmed_bookings' => 0,
        'active_bookings' => 0,
        'completed_bookings' => 0,
        'cancelled_bookings' => 0
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
    <title>My Bookings - Baroon Reptile</title>
    
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
            transition: margin-left 0.3s ease;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
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
        
        .cost-breakdown {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .cost-total {
            border-top: 1px solid #dee2e6;
            padding-top: 8px;
            margin-top: 8px;
            font-weight: 600;
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
                        <span class="nav-text">Reptil Saya</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="bookings.php">
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
                    <a class="nav-link" href="care_reports.php">
                        <i class="fas fa-file-alt"></i>
                        <span class="nav-text">Laporan Perawatan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>
                        <span class="nav-text">Profil</span>
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
                <h4 class="mb-0">Booking Saya</h4>
                <span class="badge bg-primary ms-3"><?php echo $stats['total_bookings']; ?> Total</span>
            </div>
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
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
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $stats['pending_bookings']; ?></div>
                    <div class="stat-label">Menunggu Konfirmasi</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $stats['confirmed_bookings']; ?></div>
                    <div class="stat-label">Dikonfirmasi</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['active_bookings']; ?></div>
                    <div class="stat-label">Sedang Berlangsung</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $stats['completed_bookings']; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
            </div>
            
            <!-- Filter and Actions -->
            <div class="filter-tabs">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3">
                            <a href="create_booking.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Buat Booking Baru
                            </a>
                            
                            <div class="btn-group" role="group">
                                <a href="?status=all" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Semua</a>
                                <a href="?status=pending" class="btn <?php echo $status_filter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Menunggu</a>
                                <a href="?status=confirmed" class="btn <?php echo $status_filter === 'confirmed' ? 'btn-info' : 'btn-outline-info'; ?>">Dikonfirmasi</a>
                                <a href="?status=in_progress" class="btn <?php echo $status_filter === 'in_progress' ? 'btn-primary' : 'btn-outline-primary'; ?>">Aktif</a>
                                <a href="?status=completed" class="btn <?php echo $status_filter === 'completed' ? 'btn-success' : 'btn-outline-success'; ?>">Selesai</a>
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
                <div class="empty-state">
                    <i class="fas fa-calendar-times empty-icon"></i>
                    <h5>Belum Ada Booking</h5>
                    <p class="text-muted mb-4">
                        <?php if ($status_filter === 'all'): ?>
                            Anda belum membuat booking apapun. Mulai dengan membuat booking pertama Anda!
                        <?php else: ?>
                            Tidak ada booking dengan status "<?php echo ucfirst($status_filter); ?>".
                        <?php endif; ?>
                    </p>
                    <a href="create_booking.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Buat Booking Pertama
                    </a>
                </div>
            <?php else: ?>
                <!-- Bookings List -->
                <div id="bookingsList">
                    <?php foreach ($bookings as $booking): ?>
                        <?php $status = $status_config[$booking['status']]; ?>
                        <div class="booking-card" data-search="<?php echo strtolower($booking['reptile_name'] . ' ' . $booking['category_name'] . ' ' . $booking['id']); ?>">
                            <div class="booking-header">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
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
                                    <div class="col-md-6 text-md-end">
                                        <div class="d-flex align-items-center justify-content-md-end gap-3">
                                            <span class="status-badge bg-<?php echo $status['class']; ?> text-white">
                                                <i class="fas fa-<?php echo $status['icon']; ?>"></i>
                                                <?php echo $status['text']; ?>
                                            </span>
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
                                
                                <div class="cost-breakdown">
                                    <div class="cost-item">
                                        <span>Biaya Dasar (<?php echo $booking['total_days']; ?> hari):</span>
                                        <span>Rp <?php echo number_format(isset($booking['base_cost']) ? $booking['base_cost'] : 0, 0, ',', '.'); ?></span>
                                    </div>
                                    <?php if (isset($booking['facility_cost']) && $booking['facility_cost'] > 0): ?>
                                        <div class="cost-item">
                                            <span>Fasilitas Tambahan:</span>
                                            <span>Rp <?php echo number_format($booking['facility_cost'], 0, ',', '.'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="cost-item cost-total">
                                        <span>Total:</span>
                                        <span>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
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
                                                Pembayaran: 
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
                                            
                                            <?php if ($booking['status'] === 'confirmed' && !$booking['payment_status']): ?>
                                                <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-credit-card me-1"></i>Bayar
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                <button class="btn btn-outline-danger btn-sm" onclick="confirmCancel(<?php echo $booking['id']; ?>)">
                                                    <i class="fas fa-times me-1"></i>Batal
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] === 'completed'): ?>
                                                <a href="care_reports.php" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-file-alt me-1"></i>Laporan
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

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Pembatalan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin membatalkan booking ini?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Tindakan ini tidak dapat dibatalkan!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tidak</button>
                    <a href="#" id="cancelLink" class="btn btn-danger">Ya, Batalkan</a>
                </div>
            </div>
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
        
        // Cancel confirmation
        function confirmCancel(bookingId) {
            document.getElementById('cancelLink').href = '?action=cancel&id=' + bookingId;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }
        
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