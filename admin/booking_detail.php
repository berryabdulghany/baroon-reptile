<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
    
    // Get detailed booking information
    $stmt = $db->prepare("
        SELECT b.*, 
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               r.name as reptile_name, r.species, r.age, r.gender, r.photo as reptile_photo,
               rc.name as category_name
        FROM bookings b
        LEFT JOIN users u ON b.customer_id = u.id
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('Location: bookings.php?error=booking_not_found');
        exit;
    }
    
    // Get payment information
    $stmt = $db->prepare("SELECT * FROM payments WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get care reports for this booking
    $stmt = $db->prepare("
        SELECT dr.*, u.full_name as staff_name
        FROM daily_reports dr
        LEFT JOIN users u ON dr.created_by = u.id
        WHERE dr.booking_id = ?
        ORDER BY dr.report_date DESC
    ");
    $stmt->execute([$booking_id]);
    $care_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle status update
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $new_status = $_POST['status'];
        $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $booking_id])) {
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Booking status updated successfully!</div>';
            $booking['status'] = $new_status; // Update local data
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Failed to update booking status.</div>';
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
    <title>Booking Detail #<?php echo $booking['id']; ?> - Baroon Reptile Admin</title>
    
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
        
        .care-report-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #4a7c59;
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
                    <a class="nav-link active" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
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
            <div>
                <h5 class="mb-0">Booking Detail #<?php echo $booking['id']; ?></h5>
                <small class="text-muted">Created: <?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?></small>
            </div>
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

            <!-- Action Buttons -->
            <div class="mb-4">
                <a href="bookings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                </a>
                <?php if (!$payment): ?>
                    <a href="create_payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success btn-action">
                        <i class="fas fa-plus me-2"></i>Create Payment
                    </a>
                <?php else: ?>
                    <a href="view_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-info btn-action">
                        <i class="fas fa-eye me-2"></i>View Payment
                    </a>
                <?php endif; ?>
                <a href="print_receipt.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-action" target="_blank">
                    <i class="fas fa-print me-2"></i>Print Receipt
                </a>
            </div>

            <div class="row">
                <!-- Booking Information -->
                <div class="col-lg-8">
                    <div class="detail-card">
                        <div class="detail-header">
                            <h4><i class="fas fa-calendar-alt me-2"></i>Booking Information</h4>
                        </div>
                        <div class="detail-body">
                            <div class="info-row">
                                <span class="info-label">Booking ID:</span>
                                <span class="info-value">#<?php echo $booking['id']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span class="info-value">
                                    <?php
                                    $status_text = [
                                        'pending' => 'Pending',
                                        'confirmed' => 'Confirmed',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled'
                                    ];
                                    ?>
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo $status_text[$booking['status']]; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Check-in Date:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Check-out Date:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Duration:</span>
                                <span class="info-value"><?php echo $booking['total_days']; ?> days</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Price:</span>
                                <span class="info-value">
                                    <strong class="text-success">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></strong>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Special Requests:</span>
                                <span class="info-value"><?php echo $booking['notes'] ? htmlspecialchars($booking['notes'] ?? '') : 'None'; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <h4><i class="fas fa-user me-2"></i>Customer Information</h4>
                        </div>
                        <div class="detail-body">
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo $booking['customer_phone'] ? htmlspecialchars($booking['customer_phone'] ?? '') : 'Not provided'; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Information -->
                    <?php if ($payment): ?>
                        <div class="detail-card">
                            <div class="detail-header">
                                <h4><i class="fas fa-credit-card me-2"></i>Payment Information</h4>
                            </div>
                            <div class="detail-body">
                                <div class="info-row">
                                    <span class="info-label">Payment ID:</span>
                                    <span class="info-value">#<?php echo $payment['id']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Amount:</span>
                                    <span class="info-value">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Method:</span>
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
                                    <span class="info-label">Created:</span>
                                    <span class="info-value"><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reptile Information & Actions -->
                <div class="col-lg-4">
                    <div class="detail-card">
                        <div class="detail-header">
                            <h4><i class="fas fa-dragon me-2"></i>Reptile Information</h4>
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
                            <p class="text-muted mb-3">Species: <?php echo htmlspecialchars($booking['species'] ?? ''); ?></p>
                            
                            <div class="row text-start">
                                <div class="col-6">
                                    <small class="text-muted">Age:</small><br>
                                    <strong><?php echo $booking['age'] ? $booking['age'] : 'Unknown'; ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Gender:</small><br>
                                    <strong><?php echo $booking['gender'] ? ucfirst($booking['gender']) : 'Unknown'; ?></strong>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-start">
                                <small class="text-muted">Price per day:</small><br>
                                <strong class="text-success">Rp <?php echo number_format($booking['price_per_day'], 0, ',', '.'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Status Update -->
                    <div class="detail-card">
                        <div class="detail-header">
                            <h4><i class="fas fa-edit me-2"></i>Update Status</h4>
                        </div>
                        <div class="detail-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Booking Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="in_progress" <?php echo $booking['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Care Reports -->
            <?php if (!empty($care_reports)): ?>
                <div class="detail-card">
                    <div class="detail-header">
                        <h4><i class="fas fa-file-medical me-2"></i>Care Reports (<?php echo count($care_reports); ?>)</h4>
                    </div>
                    <div class="detail-body">
                        <?php foreach ($care_reports as $report): ?>
                            <div class="care-report-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0"><?php echo date('d M Y', strtotime($report['report_date'])); ?></h6>
                                    <small class="text-muted">by <?php echo htmlspecialchars($report['staff_name'] ?? ''); ?></small>
                                </div>
                                <p class="mb-1"><strong>Health Status:</strong> <?php echo htmlspecialchars($report['health_status']); ?></p>
                                <p class="mb-1"><strong>Food Given:</strong> <?php echo htmlspecialchars($report['feeding_notes'] ?? '-'); ?></p>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($report['notes'] ?? ''); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>