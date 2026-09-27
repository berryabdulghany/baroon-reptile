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
    
    // Handle payment status update
    if (isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
        $payment_id = $_POST['payment_id'];
        $new_status = $_POST['status'];
        
        // Validate status
        $valid_statuses = ['pending', 'paid', 'failed'];
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $db->prepare("UPDATE payments SET payment_status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $payment_id])) {
                $message = 'success:Status payment berhasil diupdate!';
            } else {
                $message = 'error:Gagal mengupdate status payment!';
            }
        } else {
            $message = 'error:Status tidak valid!';
        }
    }
    
    // Handle create payment
    if (isset($_POST['action']) && $_POST['action'] === 'create_payment') {
        $booking_id = $_POST['booking_id'];
        $amount = $_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';
        
        // Check if payment already exists for this booking
        $stmt = $db->prepare("SELECT id FROM payments WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        if ($stmt->fetchColumn()) {
            $message = 'error:Payment sudah ada untuk booking ini!';
        } else {
            $stmt = $db->prepare("
                INSERT INTO payments (booking_id, amount, payment_method, payment_status, notes, created_at) 
                VALUES (?, ?, ?, 'pending', ?, NOW())
            ");
            if ($stmt->execute([$booking_id, $amount, $payment_method, $notes])) {
                $message = 'success:Payment berhasil dibuat!';
            } else {
                $message = 'error:Gagal membuat payment!';
            }
        }
    }
    
    // Get filters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $method_filter = isset($_GET['method']) ? $_GET['method'] : 'all';
    $date_filter = isset($_GET['date']) ? $_GET['date'] : 'all';
    
    // Build query
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($status_filter !== 'all') {
        $where_clause .= " AND p.payment_status = ?";
        $params[] = $status_filter;
    }
    
    if ($method_filter !== 'all') {
        $where_clause .= " AND p.payment_method = ?";
        $params[] = $method_filter;
    }
    
    if ($date_filter === 'today') {
        $where_clause .= " AND DATE(p.created_at) = CURDATE()";
    } elseif ($date_filter === 'this_week') {
        $where_clause .= " AND WEEK(p.created_at) = WEEK(CURDATE()) AND YEAR(p.created_at) = YEAR(CURDATE())";
    } elseif ($date_filter === 'this_month') {
        $where_clause .= " AND MONTH(p.created_at) = MONTH(CURDATE()) AND YEAR(p.created_at) = YEAR(CURDATE())";
    }
    
    // Get all payments with booking and customer info
    $stmt = $db->prepare("
        SELECT p.*, 
               b.id as booking_id, b.start_date, b.end_date, b.total_price as booking_total,
               u.full_name as customer_name, u.email as customer_email,
               r.name as reptile_name,
               rc.name as category_name
        FROM payments p 
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN users u ON b.customer_id = u.id
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_payments,
            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
            SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount
        FROM payments
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure $stats is not null
    if (!$stats) {
        $stats = [
            'total_payments' => 0,
            'pending_payments' => 0,
            'paid_payments' => 0,
            'failed_payments' => 0,
            'total_revenue' => 0,
            'pending_amount' => 0
        ];
    }
    
    // Get payment methods statistics
    $stmt = $db->query("
        SELECT payment_method, COUNT(*) as count, SUM(amount) as total_amount
        FROM payments 
        WHERE payment_status = 'paid'
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $method_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure $method_stats is not null
    if (!$method_stats) {
        $method_stats = [];
    }
    
    // Get bookings without payments for create payment modal
    $stmt = $db->query("
        SELECT b.id, b.total_price, u.full_name as customer_name, r.name as reptile_name
        FROM bookings b
        LEFT JOIN payments p ON b.id = p.booking_id
        LEFT JOIN users u ON b.customer_id = u.id
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        WHERE p.id IS NULL AND b.status IN ('confirmed', 'completed')
        ORDER BY b.created_at DESC
    ");
    $unpaid_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure $unpaid_bookings is not null
    if (!$unpaid_bookings) {
        $unpaid_bookings = [];
    }
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
    error_log("Error in payments.php: " . $e->getMessage());
    
    // Initialize variables with default values to prevent undefined variable errors
    $stats = [
        'total_payments' => 0,
        'pending_payments' => 0,
        'paid_payments' => 0,
        'failed_payments' => 0,
        'total_revenue' => 0,
        'pending_amount' => 0
    ];
    $method_stats = [];
    $payments = [];
    $unpaid_bookings = [];
}

// Status configurations
$status_config = [
    'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Pending'],
    'paid' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Paid'],
    'failed' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Failed']
];

$payment_methods = ['cash', 'transfer', 'credit_card', 'e_wallet'];
$method_labels = [
    'cash' => 'Cash',
    'transfer' => 'Bank Transfer',
    'credit_card' => 'Credit Card',
    'e_wallet' => 'E-Wallet'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Baroon Reptile Admin</title>
    
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
        
        .payment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .payment-header {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .payment-body {
            padding: 20px;
        }
        
        .payment-footer {
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
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .method-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .amount-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c5530;
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
        
        .booking-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .status-select {
            border: none;
            background: transparent;
            font-weight: 600;
            cursor: pointer;
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
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check"></i>Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="payments.php">
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
                <h4 class="mb-0">Manage Payments</h4>
                <span class="badge bg-primary ms-3"><?php echo $stats['total_payments']; ?> Total</span>
                <button class="btn btn-success ms-3" data-bs-toggle="modal" data-bs-target="#createPaymentModal">
                    <i class="fas fa-plus me-2"></i>Create Payment
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
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-success">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-warning">Rp <?php echo number_format($stats['pending_amount'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Pending Amount</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['paid_payments']; ?></div>
                    <div class="stat-label">Paid Payments</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $stats['pending_payments']; ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value text-danger"><?php echo $stats['failed_payments']; ?></div>
                    <div class="stat-label">Failed Payments</div>
                </div>
            </div>
            
            <!-- Payment Methods Statistics -->
            <?php if (!empty($method_stats)): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Payment Methods</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($method_stats as $method): ?>
                                        <div class="col-md-3 col-sm-6 mb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="method-badge"><?php echo $method_labels[$method['payment_method']] ?? $method['payment_method']; ?></span>
                                                <div class="text-end">
                                                    <div class="fw-bold"><?php echo $method['count']; ?></div>
                                                    <small class="text-muted">Rp <?php echo number_format($method['total_amount'], 0, ',', '.'); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filter and Controls -->
            <div class="filter-controls">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="btn-group" role="group">
                                <a href="?status=all&method=<?php echo $method_filter; ?>&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Semua</a>
                                <a href="?status=pending&method=<?php echo $method_filter; ?>&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">Pending</a>
                                <a href="?status=paid&method=<?php echo $method_filter; ?>&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'paid' ? 'btn-success' : 'btn-outline-success'; ?>">Paid</a>
                                <a href="?status=failed&method=<?php echo $method_filter; ?>&date=<?php echo $date_filter; ?>" class="btn <?php echo $status_filter === 'failed' ? 'btn-danger' : 'btn-outline-danger'; ?>">Failed</a>
                            </div>
                            
                            <select class="form-select" style="width: auto;" onchange="window.location.href='?status=<?php echo $status_filter; ?>&method=' + this.value + '&date=<?php echo $date_filter; ?>'">
                                <option value="all" <?php echo $method_filter === 'all' ? 'selected' : ''; ?>>Semua Metode</option>
                                <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo $method; ?>" <?php echo $method_filter === $method ? 'selected' : ''; ?>>
                                        <?php echo $method_labels[$method]; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div class="btn-group" role="group">
                                <a href="?status=<?php echo $status_filter; ?>&method=<?php echo $method_filter; ?>&date=all" class="btn <?php echo $date_filter === 'all' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Semua</a>
                                <a href="?status=<?php echo $status_filter; ?>&method=<?php echo $method_filter; ?>&date=today" class="btn <?php echo $date_filter === 'today' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Hari Ini</a>
                                <a href="?status=<?php echo $status_filter; ?>&method=<?php echo $method_filter; ?>&date=this_week" class="btn <?php echo $date_filter === 'this_week' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Minggu Ini</a>
                                <a href="?status=<?php echo $status_filter; ?>&method=<?php echo $method_filter; ?>&date=this_month" class="btn <?php echo $date_filter === 'this_month' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Bulan Ini</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari payment...">
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($payments)): ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                    <h5>Tidak Ada Payment</h5>
                    <p class="text-muted">Tidak ada payment yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <!-- Payments List -->
                <div id="paymentsList">
                    <?php foreach ($payments as $payment): ?>
                        <?php $status = $status_config[$payment['payment_status']] ?? $status_config['pending']; ?>
                        <div class="payment-card" data-search="<?php echo strtolower($payment['customer_name'] . ' ' . $payment['reptile_name'] . ' ' . $payment['payment_method'] . ' ' . $payment['id']); ?>">
                            <div class="payment-header">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="customer-info">
                                            <div class="customer-avatar">
                                                <?php echo strtoupper(substr($payment['customer_name'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($payment['customer_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['customer_email']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="amount-display">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></div>
                                        <span class="method-badge"><?php echo $method_labels[$payment['payment_method']] ?? $payment['payment_method']; ?></span>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="d-flex align-items-center justify-content-md-end gap-3">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_payment_status">
                                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                <select name="status" class="status-select bg-<?php echo $status['class']; ?> text-white rounded px-2 py-1" onchange="this.form.submit()">
                                                    <?php foreach ($status_config as $key => $config): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $payment['payment_status'] === $key ? 'selected' : ''; ?>>
                                                            <?php echo $config['text']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <small class="text-muted">ID: #<?php echo $payment['id']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="payment-body">
                                <div class="booking-info">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Booking Details:</strong>
                                            <div class="mt-2">
                                                <div><strong>Reptile:</strong> <?php echo htmlspecialchars($payment['reptile_name']); ?></div>
                                                <div><strong>Category:</strong> <?php echo htmlspecialchars($payment['category_name']); ?></div>
                                                <div><strong>Booking ID:</strong> #<?php echo $payment['booking_id']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Booking Period:</strong>
                                            <div class="mt-2">
                                                <div><strong>Start:</strong> <?php echo date('d M Y', strtotime($payment['start_date'])); ?></div>
                                                <div><strong>End:</strong> <?php echo date('d M Y', strtotime($payment['end_date'])); ?></div>
                                                <div><strong>Total Cost:</strong> Rp <?php echo number_format($payment['booking_total'], 0, ',', '.'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($payment['notes']): ?>
                                    <div class="mt-3">
                                        <strong>Notes:</strong>
                                        <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($payment['notes'] ?? '')); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="payment-footer">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Created: <?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?>
                                        </small>

                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <div class="btn-group" role="group">
                                            <a href="view_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </a>
                                            
                                            <a href="edit_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-outline-warning btn-sm">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </a>
                                            
                                            <?php if ($payment['payment_status'] === 'paid'): ?>
                                                <a href="print_receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-outline-info btn-sm" target="_blank">
                                                    <i class="fas fa-print me-1"></i>Receipt
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($payment['payment_status'] === 'paid'): ?>
                                                <button class="btn btn-outline-danger btn-sm" onclick="confirmRefund(<?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-undo me-1"></i>Refund
                                                </button>
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

    <!-- Create Payment Modal -->
    <div class="modal fade" id="createPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_payment">
                        
                        <div class="mb-3">
                            <label for="booking_id" class="form-label">Booking</label>
                            <select class="form-select" id="booking_id" name="booking_id" required onchange="updateAmount()">
                                <option value="">Pilih Booking</option>
                                <?php foreach ($unpaid_bookings as $booking): ?>
                                    <option value="<?php echo $booking['id']; ?>" data-amount="<?php echo $booking['total_price']; ?>">
                                        #<?php echo $booking['id']; ?> - <?php echo htmlspecialchars($booking['customer_name']); ?> (<?php echo htmlspecialchars($booking['reptile_name']); ?>) - Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" class="form-control" id="amount" name="amount" required min="1">
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Pilih Metode</option>
                                <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo $method; ?>"><?php echo $method_labels[$method]; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Payment</button>
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
            const cards = document.querySelectorAll('.payment-card');
            
            cards.forEach(card => {
                const searchData = card.dataset.search;
                const isVisible = searchData.includes(searchTerm);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
        
        // Update amount when booking is selected
        function updateAmount() {
            const bookingSelect = document.getElementById('booking_id');
            const amountInput = document.getElementById('amount');
            const selectedOption = bookingSelect.options[bookingSelect.selectedIndex];
            
            if (selectedOption.dataset.amount) {
                amountInput.value = selectedOption.dataset.amount;
            } else {
                amountInput.value = '';
            }
        }
        
        // Confirm refund function
        function confirmRefund(paymentId) {
            if (confirm('Yakin ingin melakukan refund untuk payment ini?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_payment_status">
                    <input type="hidden" name="payment_id" value="${paymentId}">
                    <input type="hidden" name="status" value="refunded">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            // Only refresh if no modals are open
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>