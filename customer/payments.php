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
    
    // Handle payment creation
    if (isset($_POST['action']) && $_POST['action'] === 'create_payment') {
        $booking_id = $_POST['booking_id'];
        $payment_method = $_POST['payment_method'];
        $amount = $_POST['amount'];
        
        // Validate booking belongs to current user
        $stmt = $db->prepare("SELECT id, total_price FROM bookings WHERE id = ? AND customer_id = ? AND status = 'confirmed'");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if ($booking && $amount == $booking['total_price']) {
            // Check if payment already exists
            $stmt = $db->prepare("SELECT id FROM payments WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            
            if (!$stmt->fetch()) {
                // Create payment
                $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
                $stmt->execute([$booking_id, $amount, $payment_method]);
                
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Payment created successfully!</div>';
            } else {
                $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Payment already exists for this booking.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Invalid booking or amount.</div>';
        }
    }
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    // Build query
    $where_conditions = ["p.booking_id = b.id", "b.customer_id = ?"];
    $params = [$_SESSION['user_id']];
    
    if ($status_filter) {
        $where_conditions[] = "p.payment_status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $where_conditions[] = "(r.name LIKE ? OR r.species LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(p.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(p.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get payments
    $stmt = $db->prepare("
        SELECT p.*, b.start_date, b.end_date, b.total_price as booking_total,
               r.name as reptile_name, r.species, r.photo,
               rc.name as category_name
        FROM payments p, bookings b
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN p.payment_status = 'pending' THEN p.amount ELSE 0 END) as total_pending,
        COUNT(CASE WHEN p.payment_status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN p.payment_status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN p.payment_status = 'failed' THEN 1 END) as failed_count
        FROM payments p, bookings b
        WHERE p.booking_id = b.id AND b.customer_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
    
    // Ensure all stats values are not null
    $stats = array_merge([
        'total_payments' => 0,
        'total_paid' => 0,
        'total_pending' => 0,
        'paid_count' => 0,
        'pending_count' => 0,
        'failed_count' => 0
    ], $stats ?: []);
    
    // Get unpaid bookings
    $stmt = $db->prepare("
        SELECT b.*, r.name as reptile_name, r.species, r.photo,
               rc.name as category_name
        FROM bookings b
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        LEFT JOIN payments p ON b.id = p.booking_id
        WHERE b.customer_id = ? AND b.status = 'confirmed' AND p.id IS NULL
        ORDER BY b.start_date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unpaid_bookings = $stmt->fetchAll();
    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>';
}

$status_labels = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'failed' => 'Failed',
    'refunded' => 'Refunded'
];

$method_labels = [
    'transfer' => 'Bank Transfer',
    'credit_card' => 'Credit Card',
    'e_wallet' => 'E-Wallet',
    'cash' => 'Cash'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments - Baroon Reptile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            color: white;
            position: fixed;
            width: 250px;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand {
            color: white;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .sidebar-nav {
            padding: 1rem 0;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .nav-text {
            font-size: 0.9rem;
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
            margin-left: 250px;
        }
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-toggle {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.2rem;
        }
        .dropdown-toggle::after {
            display: none;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .payment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .payment-card:hover {
            transform: translateY(-2px);
        }
        .payment-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        .payment-body {
            padding: 1.5rem;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d1edff; color: #0c5460; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #d4edda; color: #155724; }
        .amount-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .reptile-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .reptile-photo {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
        }
        .method-badge {
            background: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .unpaid-alert {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .content-area {
            padding: 2rem;
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_reptiles.php">
                        <i class="fas fa-dragon"></i>
                        <span class="nav-text">My Reptiles</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="nav-text">Bookings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="payments.php">
                        <i class="fas fa-credit-card"></i>
                        <span class="nav-text">Payments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="care_reports.php">
                        <i class="fas fa-file-alt"></i>
                        <span class="nav-text">Care Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>
                        <span class="nav-text">Profile</span>
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
                <h4 class="mb-0">My Payments</h4>
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
                        <?php echo $message; ?>

                        <!-- Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stats-card text-center">
                                    <div class="text-primary mb-2">
                                        <i class="fas fa-credit-card fa-2x"></i>
                                    </div>
                                    <h4 class="mb-1"><?php echo $stats['total_payments']; ?></h4>
                                    <small class="text-muted">Total Payments</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card text-center">
                                    <div class="text-success mb-2">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                    <h4 class="mb-1">Rp <?php echo number_format($stats['total_paid'], 0, ',', '.'); ?></h4>
                                    <small class="text-muted">Total Paid</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card text-center">
                                    <div class="text-warning mb-2">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                    <h4 class="mb-1">Rp <?php echo number_format($stats['total_pending'], 0, ',', '.'); ?></h4>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card text-center">
                                    <div class="text-info mb-2">
                                        <i class="fas fa-percentage fa-2x"></i>
                                    </div>
                                    <h4 class="mb-1"><?php echo $stats['total_payments'] > 0 ? round(($stats['paid_count'] / $stats['total_payments']) * 100) : 0; ?>%</h4>
                                    <small class="text-muted">Success Rate</small>
                                </div>
                            </div>
                        </div>

                        <!-- Unpaid Bookings Alert -->
                        <?php if (count($unpaid_bookings) > 0): ?>
                        <div class="unpaid-alert">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Unpaid Bookings</h5>
                                    <p class="mb-0">You have <?php echo count($unpaid_bookings); ?> confirmed booking(s) that require payment.</p>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#unpaidModal">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Filters -->
                        <div class="filter-card">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All Status</option>
                                        <?php foreach ($status_labels as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Reptile name..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i>Filter
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Payments List -->
                        <div class="row">
                            <div class="col-12">
                                <?php if (count($payments) > 0): ?>
                                    <?php foreach ($payments as $payment): ?>
                                        <div class="payment-card">
                                            <div class="payment-header">
                                                <div class="row align-items-center">
                                                    <div class="col-md-4">
                                                        <div class="reptile-info">
                                                            <?php if ($payment['photo']): ?>
                                                                <img src="../uploads/reptiles/<?php echo htmlspecialchars($payment['photo'] ?? ''); ?>" 
                                                                     class="reptile-photo" alt="Reptile">
                                                            <?php else: ?>
                                                                <div class="reptile-photo bg-secondary d-flex align-items-center justify-content-center">
                                                                    <i class="fas fa-dragon text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($payment['reptile_name']); ?></h6>
                                                                <small class="text-muted"><?php echo htmlspecialchars($payment['species'] ?? ''); ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 text-center">
                                                        <div class="amount-display">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></div>
                                                        <span class="method-badge"><?php echo $method_labels[$payment['payment_method']] ?? $payment['payment_method']; ?></span>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                            <?php echo $status_labels[$payment['payment_status']] ?? 'Unknown'; ?>
                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="payment-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div><strong>Booking Period:</strong></div>
                                                        <div><?php echo date('d M Y', strtotime($payment['start_date'])); ?> - <?php echo date('d M Y', strtotime($payment['end_date'])); ?></div>
                                                        <div><strong>Total Cost:</strong> Rp <?php echo number_format($payment['booking_total'], 0, ',', '.'); ?></div>
                                                    </div>
                                                    <div class="col-md-6 text-end">
                                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Created: <?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?>
                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No payments found</h5>
                                        <p class="text-muted">You haven't made any payments yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                     </div>
        </div>
    </div>

    <!-- Unpaid Bookings Modal -->
    <div class="modal fade" id="unpaidModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Unpaid Bookings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php foreach ($unpaid_bookings as $booking): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="reptile-info">
                                            <?php if ($booking['photo']): ?>
                                                <img src="../uploads/reptiles/<?php echo htmlspecialchars($booking['photo'] ?? ''); ?>" 
                                                     class="reptile-photo" alt="Reptile">
                                            <?php else: ?>
                                                <div class="reptile-photo bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-dragon text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($booking['reptile_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['species'] ?? ''); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div><strong>Period:</strong></div>
                                        <div><?php echo date('d M Y', strtotime($booking['start_date'])); ?> - <?php echo date('d M Y', strtotime($booking['end_date'])); ?></div>
                                        <div><strong>Total:</strong> Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button class="btn btn-primary btn-sm" onclick="createPayment(<?php echo $booking['id']; ?>, <?php echo $booking['total_price']; ?>)">
                                            <i class="fas fa-credit-card me-1"></i>Pay Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Create Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_payment">
                        <input type="hidden" name="booking_id" id="payment_booking_id">
                        <input type="hidden" name="amount" id="payment_amount">
                        
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <div class="form-control" id="display_amount"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="">Select payment method</option>
                                <option value="transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="e_wallet">E-Wallet</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-credit-card me-1"></i>Create Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function createPayment(bookingId, amount) {
            document.getElementById('payment_booking_id').value = bookingId;
            document.getElementById('payment_amount').value = amount;
            document.getElementById('display_amount').textContent = 'Rp ' + amount.toLocaleString('id-ID');
            
            var modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    </script>
</body>
</html>