<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my_reptiles.php');
    exit;
}

$reptile_id = $_GET['id'];
$message = '';

try {
    $db = getDB();
    
    // Get reptile details with category info
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name, rc.price_per_day,
               (SELECT COUNT(*) FROM bookings WHERE reptile_id = r.id) as total_bookings,
               (SELECT COUNT(*) FROM bookings WHERE reptile_id = r.id AND status IN ('pending', 'confirmed', 'in_progress')) as active_bookings
        FROM reptiles r 
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id 
        WHERE r.id = ? AND r.customer_id = ?
    ");
    $stmt->execute([$reptile_id, $_SESSION['user_id']]);
    $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reptile) {
        header('Location: my_reptiles.php');
        exit;
    }
    
    // Get booking history
    $stmt = $db->prepare("
        SELECT b.*,
               DATEDIFF(b.end_date, b.start_date) as total_days
        FROM bookings b
        WHERE b.reptile_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$reptile_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($reptile['name']); ?> - Baroon Reptile</title>
    
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
        
        .reptile-detail-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .reptile-image-large {
            width: 100%;
            height: 400px;
            object-fit: cover;
            background: #f8f9fa;
        }
        
        .reptile-info {
            padding: 30px;
        }
        
        .reptile-name {
            font-size: 2rem;
            font-weight: 700;
            color: #2c5530;
            margin-bottom: 10px;
        }
        
        .reptile-category {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-value {
            font-weight: 600;
            color: #2c5530;
            font-size: 1.2rem;
            display: block;
        }
        
        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
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
        
        .booking-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #4a7c59;
        }
        
        .booking-status {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #d4edda; color: #155724; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-dragon me-2"></i>Baroon
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
                    <a class="nav-link active" href="my_reptiles.php">
                        <i class="fas fa-dragon"></i>My Reptiles
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
                    <a class="nav-link" href="care_reports.php">
                        <i class="fas fa-file-alt"></i>Care Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>Profile
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
                <a href="my_reptiles.php" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
                <h4 class="mb-0">Detail Reptile</h4>
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
            
            <!-- Reptile Detail -->
            <div class="reptile-detail-card">
                <div class="row g-0">
                    <div class="col-md-5">
                        <?php if ($reptile['photo']): ?>
                            <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($reptile['name']); ?>" class="reptile-image-large">
                        <?php else: ?>
                            <div class="reptile-image-large d-flex align-items-center justify-content-center">
                                <i class="fas fa-dragon fa-5x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-7">
                        <div class="reptile-info">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h1 class="reptile-name"><?php echo htmlspecialchars($reptile['name']); ?></h1>
                                    <div class="reptile-category">
                                        <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($reptile['category_name']); ?>
                                    </div>
                                </div>
                                <span class="status-badge <?php echo $reptile['active_bookings'] > 0 ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $reptile['active_bookings'] > 0 ? 'Aktif' : 'Tidak Aktif'; ?>
                                </span>
                            </div>
                            
                            <?php if ($reptile['species']): ?>
                                <div class="mb-3">
                                    <strong><i class="fas fa-dna me-2"></i>Spesies:</strong> <?php echo htmlspecialchars($reptile['species'] ?? ''); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($reptile['description']) && $reptile['description']): ?>
                                <div class="mb-3">
                                    <strong><i class="fas fa-info-circle me-2"></i>Deskripsi:</strong>
                                    <p class="mt-2"><?php echo nl2br(htmlspecialchars($reptile['description'] ?? '')); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="info-grid">
                                <?php if ($reptile['age']): ?>
                                    <div class="info-item">
                                        <span class="info-value"><?php echo htmlspecialchars($reptile['age'] ?? ''); ?></span>
                                        <span class="info-label">Umur</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($reptile['weight']): ?>
                                    <div class="info-item">
                                        <span class="info-value"><?php echo $reptile['weight']; ?> kg</span>
                                        <span class="info-label">Berat</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <span class="info-value"><?php echo $reptile['total_bookings']; ?></span>
                                    <span class="info-label">Total Bookings</span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-value">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?></span>
                                    <span class="info-label">Per Hari</span>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-4">
                                <a href="edit_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-2"></i>Edit
                                </a>
                                <a href="create_booking.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-calendar-plus me-2"></i>Buat Booking
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Booking History -->
            <div class="reptile-detail-card">
                <div class="reptile-info">
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Riwayat Booking</h5>
                    
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada riwayat booking untuk reptile ini.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Booking #<?php echo $booking['id']; ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><?php echo $booking['total_days']; ?> hari
                                        </small>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d M Y', strtotime($booking['start_date'])); ?> - 
                                                <?php echo date('d M Y', strtotime($booking['end_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="booking-status status-<?php echo $booking['status']; ?>">
                                            <?php 
                                            $status_labels = [
                                                'pending' => 'Menunggu',
                                                'confirmed' => 'Dikonfirmasi',
                                                'in_progress' => 'Berlangsung',
                                                'completed' => 'Selesai',
                                                'cancelled' => 'Dibatalkan'
                                            ];
                                            echo $status_labels[$booking['status']] ?? $booking['status'];
                                            ?>
                                        </span>
                                        <div class="mt-1">
                                            <small class="text-success fw-bold">
                                                Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="bookings.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i>Lihat Semua Booking
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>