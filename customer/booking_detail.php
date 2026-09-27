<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if (!$booking_id) {
    header('Location: bookings.php');
    exit;
}

try {
    $db = getDB();
    
    // Get detailed booking information (only for current customer)
    $stmt = $db->prepare("
        SELECT b.*, 
               r.name as reptile_name, r.species, r.age, r.gender, r.photo as reptile_photo,
               rc.name as category_name
        FROM bookings b
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE b.id = ? AND b.customer_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('Location: bookings.php?error=booking_not_found');
        exit;
    }
    
    // Get payment information
    $stmt = $db->prepare("SELECT * FROM payments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    

    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . error_detail($e) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Detail #<?php echo $booking['id']; ?> - Baroon Reptile</title>
    
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
        
        .reptile-image {
            width: 120px;
            height: 120px;
            border-radius: 15px;
            object-fit: cover;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-in_progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-action {
            margin: 0 5px;
        }
        

        
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4a7c59;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 13px;
            top: 20px;
            width: 2px;
            height: calc(100% + 10px);
            background: #e9ecef;
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
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
                    <a class="nav-link" href="my_reptiles.php">
                        <i class="fas fa-dragon"></i>Reptil Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>Pembayaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="care_reports.php">
                        <i class="fas fa-file-medical"></i>Laporan Perawatan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>Profil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Pengaturan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i>Keluar
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div>
                <h5 class="mb-0">Detail Booking Saya #<?php echo $booking['id']; ?></h5>
                <small class="text-muted">Dibuat: <?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?></small>
            </div>
            <div>
                <span class="text-muted">Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm ms-2">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if ($message): ?>
                <?php echo $message; ?>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="mb-4">
                <a href="bookings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Booking Saya
                </a>
                <?php if (!$payment && $booking['status'] === 'confirmed'): ?>
                    <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success btn-action">
                        <i class="fas fa-credit-card me-2"></i>Buat Pembayaran
                    </a>
                <?php elseif ($payment): ?>
                    <a href="payments.php" class="btn btn-info btn-action">
                        <i class="fas fa-eye me-2"></i>Lihat Pembayaran
                    </a>
                <?php endif; ?>
            </div>

            <div class="row">
                <!-- Booking Information -->
                <div class="col-lg-8">
                    <div class="detail-card">
                        <div class="detail-header">
                            <h4><i class="fas fa-calendar-alt me-2"></i>Informasi Booking</h4>
                        </div>
                        <div class="detail-body">
                            <div class="info-row">
                                <span class="info-label">ID Booking:</span>
                                <span class="info-value">#<?php echo $booking['id']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span class="info-value">
                                    <?php
                                    $status_text = [
                                        'pending' => 'Menunggu Konfirmasi',
                                        'confirmed' => 'Dikonfirmasi',
                                        'in_progress' => 'Sedang Berlangsung',
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    ?>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo $status_text[$booking['status']]; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tanggal Check-in:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tanggal Check-out:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Durasi:</span>
                                <span class="info-value"><?php echo $booking['total_days']; ?> hari</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Harga:</span>
                                <span class="info-value">
                                    <strong class="text-success">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></strong>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Permintaan Khusus:</span>
                                <span class="info-value"><?php echo $booking['notes'] ? htmlspecialchars($booking['notes'] ?? '') : 'Tidak ada'; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <?php if ($payment): ?>
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4><i class="fas fa-credit-card me-2"></i>Informasi Pembayaran</h4>
                            </div>
                            <div class="detail-body">
                                <div class="info-row">
                                    <span class="info-label">ID Pembayaran:</span>
                                    <span class="info-value">#<?php echo $payment['id']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Jumlah:</span>
                                    <span class="info-value">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Metode:</span>
                                    <span class="info-value"><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value">
                                        <span class="status-badge payment-status-<?php echo $payment['payment_status']; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Dibuat:</span>
                                    <span class="info-value"><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4><i class="fas fa-credit-card me-2"></i>Informasi Pembayaran</h4>
                            </div>
                            <div class="detail-body text-center">
                                <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                <h6>Belum Ada Pembayaran</h6>
                                <p class="text-muted">Pembayaran akan tersedia setelah konfirmasi booking.</p>
                                <?php if ($booking['status'] === 'confirmed'): ?>
                                    <a href="payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Buat Pembayaran
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reptile Information -->
                <div class="col-lg-4">
                    <div class="detail-card">
                        <div class="detail-header">
                            <h4><i class="fas fa-dragon me-2"></i>Informasi Reptil</h4>
                        </div>
                        <div class="detail-body text-center">
                            <?php if ($booking['reptile_photo']): ?>
                                <img src="../<?php echo htmlspecialchars($booking['reptile_photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($booking['reptile_name']); ?>" class="reptile-image mb-3">
                            <?php else: ?>
                                <div class="reptile-image d-flex align-items-center justify-content-center bg-light mb-3 mx-auto">
                                    <i class="fas fa-dragon fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <h5 class="mb-2"><?php echo htmlspecialchars($booking['reptile_name']); ?></h5>
                            <p class="text-muted mb-1"><?php echo htmlspecialchars($booking['category_name']); ?></p>
                            <p class="text-muted mb-3">Spesies: <?php echo htmlspecialchars($booking['species'] ?? ''); ?></p>
                            
                            <div class="row text-start">
                                <div class="col-6">
                                    <small class="text-muted">Umur:</small><br>
                                    <strong><?php echo $booking['age'] ? htmlspecialchars($booking['age']) : 'Tidak diketahui'; ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Jenis Kelamin:</small><br>
                                    <strong><?php echo $booking['gender'] ? ucfirst($booking['gender']) : 'Tidak diketahui'; ?></strong>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-start">
                                <small class="text-muted">Harga per hari:</small><br>
                                <strong class="text-success">Rp <?php echo number_format($booking['price_per_day'], 0, ',', '.'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Timeline -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <h4><i class="fas fa-history me-2"></i>Timeline Booking</h4>
                        </div>
                        <div class="detail-body">
                            <div class="timeline-item">
                                <h6 class="mb-1">Booking Dibuat</h6>
                                <small class="text-muted"><?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?></small>
                            </div>
                            
                            <?php if ($booking['status'] !== 'pending'): ?>
                                <div class="timeline-item">
                                    <h6 class="mb-1">Status: <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?></h6>
                                    <small class="text-muted">Diperbarui oleh admin</small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($payment): ?>
                                <div class="timeline-item">
                                    <h6 class="mb-1">Pembayaran Dibuat</h6>
                                    <small class="text-muted"><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] === 'completed'): ?>
                                <div class="timeline-item">
                                    <h6 class="mb-1">Booking Selesai</h6>
                                    <small class="text-muted">Layanan selesai dengan sukses</small>
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