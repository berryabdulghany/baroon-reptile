<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$message = '';

if (!$booking_id) {
    header('Location: bookings.php');
    exit;
}

try {
    $db = getDB();
    
    // Get booking details
    $stmt = $db->prepare("
        SELECT b.*, r.name as reptile_name, r.species, r.photo,
               rc.name as category_name, rc.price_per_day,
               u.username as customer_name, u.email as customer_email,
               DATEDIFF(b.end_date, b.start_date) as total_days
        FROM bookings b
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        LEFT JOIN users u ON b.customer_id = u.id
        WHERE b.id = ? AND b.status = 'confirmed'
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('Location: bookings.php');
        exit;
    }
    
    // Check if payment already exists
    $stmt = $db->prepare("SELECT id FROM payments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $existing_payment = $stmt->fetch();
    
    if ($existing_payment) {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Payment already exists for this booking. <a href="view_payment.php?id=' . $existing_payment['id'] . '">View payment</a></div>';
    }
    
    // Handle payment creation
    if (isset($_POST['action']) && $_POST['action'] === 'create_payment' && !$existing_payment) {
        $payment_method = $_POST['payment_method'];
        $amount = $_POST['amount'];
        $payment_status = $_POST['payment_status'];
        $notes = $_POST['notes'] ?? '';
        
        // Create payment
        $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt->execute([$booking_id, $amount, $payment_method, $payment_status, $notes])) {
            $payment_id = $db->lastInsertId();
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Payment created successfully! <a href="view_payment.php?id=' . $payment_id . '">View payment</a></div>';
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Failed to create payment. Please try again.</div>';
        }
    }
    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Payment - Baroon Reptile Admin</title>
    
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
        
        .payment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .payment-body {
            padding: 30px;
        }
        
        .booking-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .reptile-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4a7c59;
            box-shadow: 0 0 0 0.2rem rgba(74, 124, 89, 0.25);
        }
        
        .btn-create {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            border: none;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 10px;
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
        
        .status-completed {
            background: #d1edff;
            color: #0c5460;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
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
                    <a class="nav-link" href="reptiles.php">
                        <i class="fas fa-dragon"></i>Reptiles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="payments.php">
                        <i class="fas fa-credit-card"></i>Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="customers.php">
                        <i class="fas fa-users"></i>Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-file-medical"></i>Care Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <h5 class="mb-0">Create Payment for Booking #<?php echo $booking['id']; ?></h5>
            <div>
                <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
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

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="payment-card">
                        <div class="payment-header">
                            <h3><i class="fas fa-plus-circle me-2"></i>Create Payment</h3>
                            <p class="mb-0">Create payment record for booking</p>
                        </div>
                        
                        <div class="payment-body">
                            <!-- Booking Information -->
                            <div class="booking-info">
                                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Booking Details</h6>
                                
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-2">
                                        <?php if ($booking['photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($booking['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($booking['reptile_name']); ?>" class="reptile-image">
                                        <?php else: ?>
                                            <div class="reptile-image d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-dragon text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($booking['reptile_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($booking['category_name']); ?></small>
                                        <br><small class="text-muted">Species: <?php echo htmlspecialchars($booking['species'] ?? ''); ?></small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="h4 text-success mb-0">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></div>
                                        <small class="text-muted"><?php echo $booking['total_days']; ?> days</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Customer:</strong><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($booking['customer_email']); ?></small>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Check-in:</strong><br>
                                        <span class="text-muted"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Check-out:</strong><br>
                                        <span class="text-muted"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if (!$existing_payment): ?>
                                <!-- Payment Form -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_payment">
                                    
                                    <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="amount" class="form-label">Amount</label>
                                            <input type="number" class="form-control" id="amount" name="amount" value="<?php echo $booking['total_price']; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="payment_method" class="form-label">Payment Method</label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">Select payment method</option>
                                                <option value="transfer">Bank Transfer</option>
                                                <option value="credit_card">Credit Card</option>
                                                <option value="e_wallet">E-Wallet</option>
                                                <option value="cash">Cash</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="payment_status" class="form-label">Payment Status</label>
                                            <select class="form-select" id="payment_status" name="payment_status" required>
                                                <option value="">Select status</option>
                                                <option value="pending">Pending</option>
                                                <option value="completed">Completed</option>
                                                <option value="failed">Failed</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="notes" class="form-label">Notes (Optional)</label>
                                            <input type="text" class="form-control" id="notes" name="notes" placeholder="Additional notes...">
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-create btn-lg text-white">
                                            <i class="fas fa-plus-circle me-2"></i>Create Payment
                                        </button>
                                        <a href="bookings.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                                        </a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>Payment has already been created for this booking.
                                    </div>
                                    <a href="view_payment.php?id=<?php echo $existing_payment['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye me-2"></i>View Payment Details
                                    </a>
                                    <a href="bookings.php" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Bookings
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