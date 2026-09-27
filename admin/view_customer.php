<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if (!$customer_id) {
    header('Location: customers.php');
    exit;
}

try {
    $db = getDB();
    
    // Get customer details with statistics
    $stmt = $db->prepare("
        SELECT u.*, 
               COUNT(DISTINCT r.id) as total_reptiles,
               COUNT(DISTINCT b.id) as total_bookings,
               COUNT(DISTINCT CASE WHEN b.status IN ('confirmed', 'in_progress') THEN b.id END) as active_bookings,
               COUNT(DISTINCT CASE WHEN b.status = 'completed' THEN b.id END) as completed_bookings,
               COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.id END) as cancelled_bookings,
               COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_price END), 0) as total_spent,
               MAX(b.created_at) as last_booking_date,
               MIN(b.created_at) as first_booking_date
        FROM users u 
        LEFT JOIN reptiles r ON u.id = r.customer_id
        LEFT JOIN bookings b ON u.id = b.customer_id
        WHERE u.id = ? AND u.role = 'customer'
        GROUP BY u.id
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header('Location: customers.php?error=customer_not_found');
        exit;
    }
    
    // Get recent bookings
    $stmt = $db->prepare("
        SELECT b.*, r.name as reptile_name, rc.name as category_name
        FROM bookings b
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE b.customer_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$customer_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get customer's reptiles
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name
        FROM reptiles r
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE r.customer_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$customer_id]);
    $customer_reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pelanggan - <?php echo htmlspecialchars($customer['full_name']); ?> - Baroon Reptile Admin</title>
    
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
        
        .detail-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .detail-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            padding: 20px 30px;
        }
        
        .detail-body {
            padding: 30px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }
        
        .info-value {
            color: #212529;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .customer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 20px;
        }
        
        .stats-grid {
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
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
        }
        
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 5px;
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
                <h4 class="mb-0">Detail Pelanggan</h4>
                <small class="text-muted">Lihat informasi detail pelanggan</small>
            </div>
            <div class="d-flex align-items-center">
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
            
            <div class="row">
                <!-- Customer Information -->
                <div class="col-lg-4">
                    <div class="detail-card">
                        <div class="detail-header text-center">
                            <div class="customer-avatar">
                                <?php echo strtoupper(substr($customer['full_name'], 0, 2)); ?>
                            </div>
                            <h4><?php echo htmlspecialchars($customer['full_name']); ?></h4>
                            <p class="mb-0">@<?php echo htmlspecialchars($customer['username']); ?></p>
                        </div>
                        <div class="detail-body">
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span class="info-value">
                                    <span class="status-badge status-<?php echo $customer['status']; ?>">
                                        <?php echo ucfirst($customer['status']); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($customer['email']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo $customer['phone'] ? htmlspecialchars($customer['phone'] ?? '') : 'Not provided'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?php echo $customer['address'] ? htmlspecialchars($customer['address'] ?? '') : 'Not provided'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Joined:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($customer['created_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Last Updated:</span>
                                <span class="info-value"><?php echo date('d M Y H:i', strtotime($customer['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <h5><i class="fas fa-bolt me-2"></i>Aksi Cepat</h5>
                        </div>
                        <div class="detail-body">
                            <div class="d-grid gap-2">
                                <a href="customer_bookings.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-calendar-alt me-2"></i>Lihat Semua Booking
                                </a>
                                <a href="customer_reptiles.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-success">
                                    <i class="fas fa-dragon me-2"></i>Lihat Semua Reptil
                                </a>
                                <a href="send_message.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-info">
                                    <i class="fas fa-envelope me-2"></i>Kirim Pesan
                                </a>
                                <a href="create_booking.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-warning">
                                    <i class="fas fa-plus me-2"></i>Buat Booking
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics & Details -->
                <div class="col-lg-8">
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $customer['total_reptiles']; ?></div>
                            <div class="stat-label">Total Reptil</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $customer['total_bookings']; ?></div>
                            <div class="stat-label">Total Booking</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value text-primary"><?php echo $customer['active_bookings']; ?></div>
                            <div class="stat-label">Booking Aktif</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value text-success">Rp <?php echo number_format($customer['total_spent'], 0, ',', '.'); ?></div>
                            <div class="stat-label">Total Pengeluaran</div>
                        </div>
                    </div>
                    
                    <!-- Recent Bookings -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <h5><i class="fas fa-calendar-alt me-2"></i>Booking Terbaru</h5>
                        </div>
                        <div class="detail-body">
                            <?php if (empty($recent_bookings)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada booking ditemukan</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Reptil</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                                <th>Total</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td>#<?php echo $booking['id']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($booking['reptile_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($booking['category_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo date('d M', strtotime($booking['start_date'])); ?> - 
                                                        <?php echo date('d M Y', strtotime($booking['end_date'])); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $booking['status'] === 'completed' ? 'success' : 
                                                                ($booking['status'] === 'cancelled' ? 'danger' : 
                                                                ($booking['status'] === 'confirmed' ? 'primary' : 'warning')); 
                                                        ?>">
                                                            <?php echo ucfirst($booking['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></td>
                                                    <td>
                                                        <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="customer_bookings.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-primary">
                                        Lihat Semua Booking
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Customer's Reptiles -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <h5><i class="fas fa-dragon me-2"></i>Reptil Pelanggan</h5>
                        </div>
                        <div class="detail-body">
                            <?php if (empty($customer_reptiles)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-dragon fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada reptil ditemukan</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Spesies</th>
                                                <th>Kategori</th>
                                                <th>Umur</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customer_reptiles as $reptile): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($reptile['name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($reptile['species'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($reptile['category_name']); ?></td>
                                                    <td><?php echo $reptile['age'] ? $reptile['age'] : 'Tidak diketahui'; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $reptile['status'] === 'active' ? 'success' : 
                                                                ($reptile['status'] === 'completed' ? 'primary' : 'danger'); 
                                                        ?>">
                                                            <?php echo ucfirst($reptile['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="customer_reptiles.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-outline-success">
                                        Lihat Semua Reptil
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>