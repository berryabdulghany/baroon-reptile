<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

try {
    $db = getDB();
    $customer_id = $_SESSION['user_id'];
    
    // Get customer statistics
    $stats = [];
    
    // Total reptiles
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reptiles WHERE customer_id = ? AND status = 'active'");
    $stmt->execute([$customer_id]);
    $stats['reptiles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active bookings
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ? AND status IN ('confirmed', 'in_progress')");
    $stmt->execute([$customer_id]);
    $stats['active_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Completed bookings
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ? AND status = 'completed'");
    $stmt->execute([$customer_id]);
    $stats['completed_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total spent - from paid payments
    $stmt = $db->prepare("
        SELECT SUM(p.amount) as total 
        FROM payments p 
        JOIN bookings b ON p.booking_id = b.id 
        WHERE b.customer_id = ? AND p.payment_status = 'paid'
    ");
    $stmt->execute([$customer_id]);
    $stats['total_spent'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Recent bookings
    $stmt = $db->prepare("
        SELECT b.*, r.name as reptile_name, rc.name as category_name,
               DATEDIFF(b.end_date, b.start_date) as total_days
        FROM bookings b
        JOIN reptiles r ON b.reptile_id = r.id
        JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE b.customer_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$customer_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // My reptiles
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name, rc.price_per_day,
               (SELECT COUNT(*) FROM bookings WHERE reptile_id = r.id AND status IN ('confirmed', 'in_progress')) as active_booking
        FROM reptiles r
        JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE r.customer_id = ? AND r.status = 'active'
        ORDER BY r.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$customer_id]);
    $my_reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan saat memuat data.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Customer - Baroon Reptile</title>
    
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
            transition: all 0.3s ease;
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
        
        .welcome-card {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            border-left: 4px solid;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.reptiles {
            border-left-color: #28a745;
        }
        
        .stat-card.active {
            border-left-color: #007bff;
        }
        
        .stat-card.completed {
            border-left-color: #ffc107;
        }
        
        .stat-card.spent {
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
        
        .reptile-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .reptile-card:hover {
            transform: translateY(-5px);
        }
        
        .reptile-image {
            height: 200px;
            background: linear-gradient(135deg, #e9ecef, #f8f9fa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #6c757d;
        }
        
        .reptile-info {
            padding: 20px;
        }
        
        .reptile-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .reptile-category {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .reptile-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-boarded {
            background: #cce7ff;
            color: #004085;
        }
        
        .badge {
            font-size: 0.75rem;
        }
        
        .btn-toggle {
            background: none;
            border: none;
            color: #2c5530;
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-toggle:hover {
            background: rgba(44, 85, 48, 0.1);
            color: #2c5530;
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
                <span class="brand-text">Baroon</span>
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
                    <a class="nav-link" href="my_reptiles.php">
                        <i class="fas fa-dragon"></i>
                        <span class="nav-text">Reptil Saya</span>
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
                <h4 class="mb-0">Dashboard</h4>
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
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                        <p class="mb-0">Kelola reptile kesayangan Anda dengan mudah melalui dashboard ini. Pantau kondisi, booking, dan laporan perawatan harian.</p>
                    </div>
                    <div class="col-lg-4 text-end">
                        <a href="add_reptile.php" class="btn btn-light btn-lg">
                            <i class="fas fa-plus me-2"></i>Tambah Reptile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card reptiles">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-success"><?php echo number_format($stats['reptiles']); ?></div>
                                <div class="stat-label">Reptil Saya</div>
                            </div>
                            <i class="fas fa-dragon stat-icon text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card active">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-primary"><?php echo number_format($stats['active_bookings']); ?></div>
                                <div class="stat-label">Booking Aktif</div>
                            </div>
                            <i class="fas fa-calendar-check stat-icon text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card completed">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-warning"><?php echo number_format($stats['completed_bookings']); ?></div>
                                <div class="stat-label">Penitipan Selesai</div>
                            </div>
                            <i class="fas fa-check-circle stat-icon text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card spent">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number text-danger">Rp <?php echo number_format($stats['total_spent'], 0, ',', '.'); ?></div>
                                <div class="stat-label">Total Pengeluaran</div>
                            </div>
                            <i class="fas fa-wallet stat-icon text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Reptiles -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-dragon me-2"></i>Reptil Saya</h5>
                            <a href="my_reptiles.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($my_reptiles)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-dragon fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada reptile terdaftar</h5>
                                    <p class="text-muted">Tambahkan reptile pertama Anda untuk mulai menggunakan layanan kami.</p>
                                    <a href="add_reptile.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Tambah Reptile
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($my_reptiles as $reptile): ?>
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="reptile-card">
                                                <div class="reptile-image">
                                                    <?php if ($reptile['photo']): ?>
                                                        <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($reptile['name']); ?>" class="img-fluid">
                                                    <?php else: ?>
                                                        <i class="fas fa-dragon"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="reptile-info">
                                                    <div class="reptile-name"><?php echo htmlspecialchars($reptile['name']); ?></div>
                                                    <div class="reptile-category"><?php echo htmlspecialchars($reptile['category_name']); ?></div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="reptile-status <?php echo $reptile['active_booking'] > 0 ? 'status-boarded' : 'status-available'; ?>">
                                                            <?php echo $reptile['active_booking'] > 0 ? 'Sedang Dititipkan' : 'Tersedia'; ?>
                                                        </span>
                                                        <small class="text-muted">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?>/hari</small>
                                                    </div>
                                                    <div class="mt-3">
                                                        <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-sm btn-outline-primary me-2">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </a>
                                                        <?php if ($reptile['active_booking'] == 0): ?>
                                                            <a href="create_booking.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-calendar-plus"></i> Booking
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
                                            <th>Reptil</th>
                                            <th>Kategori</th>
                                            <th>Tanggal Mulai</th>
                                            <th>Tanggal Selesai</th>
                                            <th>Hari</th>
                                            <th>Total Harga</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_bookings)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">Tidak ada booking ditemukan</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td>#<?php echo $booking['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($booking['reptile_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($booking['category_name']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($booking['start_date'])); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($booking['end_date'])); ?></td>
                                                    <td><?php echo $booking['total_days']; ?> hari</td>
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