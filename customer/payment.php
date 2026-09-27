<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
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
               DATEDIFF(b.end_date, b.start_date) as total_days
        FROM bookings b
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE b.id = ? AND b.customer_id = ? AND b.status = 'confirmed'
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
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
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Payment already exists for this booking. <a href="payments.php">View payments</a></div>';
    }
    
    // Handle payment creation
    if (isset($_POST['action']) && $_POST['action'] === 'create_payment' && !$existing_payment) {
        $payment_method = $_POST['payment_method'];
        $amount = $booking['total_price'];
        
        // Create payment
        $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        if ($stmt->execute([$booking_id, $amount, $payment_method])) {
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Payment created successfully! Please complete the payment process.</div>';
            // Refresh to show the payment exists
            header("Location: payment.php?booking_id=$booking_id&success=1");
            exit;
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Failed to create payment. Please try again.</div>';
        }
    }
    
    if (isset($_GET['success'])) {
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Payment created successfully! Please complete the payment process.</div>';
    }
    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . error_detail($e) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Baroon Reptile</title>
    
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
        
        .amount-display {
            font-size: 2rem;
            font-weight: 700;
            color: #2c5530;
        }
        
        .payment-method-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method-card:hover {
            border-color: #4a7c59;
            background: #f8f9fa;
        }
        
        .payment-method-card.selected {
            border-color: #4a7c59;
            background: #e8f5e8;
        }
        
        .btn-payment {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            border: none;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 10px;
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
                        <i class="fas fa-dragon"></i>My Reptiles
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
                    <a class="nav-link" href="care_reports.php">
                        <i class="fas fa-file-medical"></i>Care Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>Profile
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
            <h5 class="mb-0">Payment for Booking #<?php echo $booking['id']; ?></h5>
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
                            <h3><i class="fas fa-credit-card me-2"></i>Payment Details</h3>
                            <p class="mb-0">Complete your payment for reptile boarding</p>
                        </div>
                        
                        <div class="payment-body">
                            <!-- Booking Information -->
                            <div class="booking-info">
                                <div class="row align-items-center">
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
                                        <div class="amount-display">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></div>
                                        <small class="text-muted"><?php echo $booking['total_days']; ?> days</small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Check-in:</strong><br>
                                        <span class="text-muted"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Check-out:</strong><br>
                                        <span class="text-muted"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if (!$existing_payment): ?>
                                <!-- Payment Form -->
                                <form method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="create_payment">
                                    
                                    <h6 class="mb-3">Select Payment Method</h6>
                                    
                                    <div class="payment-method-card" onclick="selectPaymentMethod('transfer')">
                                        <input type="radio" name="payment_method" value="transfer" id="transfer" required>
                                        <label for="transfer" class="ms-2">
                                            <i class="fas fa-university me-2"></i>Bank Transfer
                                        </label>
                                    </div>
                                    
                                    <div class="payment-method-card" onclick="selectPaymentMethod('credit_card')">
                                        <input type="radio" name="payment_method" value="credit_card" id="credit_card" required>
                                        <label for="credit_card" class="ms-2">
                                            <i class="fas fa-credit-card me-2"></i>Credit Card
                                        </label>
                                    </div>
                                    
                                    <div class="payment-method-card" onclick="selectPaymentMethod('e_wallet')">
                                        <input type="radio" name="payment_method" value="e_wallet" id="e_wallet" required>
                                        <label for="e_wallet" class="ms-2">
                                            <i class="fas fa-wallet me-2"></i>E-Wallet
                                        </label>
                                    </div>
                                    
                                    <div class="payment-method-card" onclick="selectPaymentMethod('cash')">
                                        <input type="radio" name="payment_method" value="cash" id="cash" required>
                                        <label for="cash" class="ms-2">
                                            <i class="fas fa-money-bill me-2"></i>Cash
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-payment btn-lg text-white">
                                            <i class="fas fa-credit-card me-2"></i>Create Payment
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
                                    <a href="payments.php" class="btn btn-primary">
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
    
    <script>
        function selectPaymentMethod(method) {
            // Remove selected class from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById(method).checked = true;
        }
        
        // Add click event to radio buttons
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-method-card').forEach(card => {
                    card.classList.remove('selected');
                });
                this.closest('.payment-method-card').classList.add('selected');
            });
        });
    </script>
</body>
</html>