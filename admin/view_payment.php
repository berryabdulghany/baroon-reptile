<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    header('Location: payments.php');
    exit;
}

try {
    $db = getDB();
    
    // Get payment details with booking and customer info
    $stmt = $db->prepare("
        SELECT p.*, 
               b.id as booking_id, b.start_date, b.end_date, b.total_price as booking_total, b.status as booking_status,
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               r.name as reptile_name,
               rc.name as category_name
        FROM payments p 
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN users u ON b.customer_id = u.id
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        header('Location: payments.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error in view_payment.php: " . $e->getMessage());
    header('Location: payments.php');
    exit;
}

// Status configurations
$status_config = [
    'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Pending'],
    'paid' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Paid'],
    'failed' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Failed']
];

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
    <title>Payment Detail - Baroon Reptile Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .detail-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .info-row {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .amount-display {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-credit-card me-2"></i>Payment Detail</h2>
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Payments
                    </a>
                </div>
                
                <!-- Payment Overview -->
                <div class="detail-card">
                    <div class="detail-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">Payment #<?php echo $payment['id']; ?></h4>
                                <p class="mb-0">Created on <?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <?php $status = $status_config[$payment['payment_status']] ?? $status_config['pending']; ?>
                                <span class="status-badge bg-<?php echo $status['class']; ?>">
                                    <i class="fas fa-<?php echo $status['icon']; ?> me-1"></i>
                                    <?php echo $status['text']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Payment Information</h5>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Amount:</strong></div>
                                        <div class="col-8">
                                            <span class="amount-display">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Method:</strong></div>
                                        <div class="col-8"><?php echo $method_labels[$payment['payment_method']] ?? $payment['payment_method']; ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Status:</strong></div>
                                        <div class="col-8">
                                            <span class="badge bg-<?php echo $status['class']; ?>">
                                                <i class="fas fa-<?php echo $status['icon']; ?> me-1"></i>
                                                <?php echo $status['text']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($payment['notes']): ?>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Notes:</strong></div>
                                        <div class="col-8"><?php echo nl2br(htmlspecialchars($payment['notes'] ?? '')); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Customer Information</h5>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Name:</strong></div>
                                        <div class="col-8"><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Email:</strong></div>
                                        <div class="col-8"><?php echo htmlspecialchars($payment['customer_email']); ?></div>
                                    </div>
                                </div>
                                <?php if ($payment['customer_phone']): ?>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Phone:</strong></div>
                                        <div class="col-8"><?php echo htmlspecialchars($payment['customer_phone'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Booking Details -->
                <div class="detail-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Booking Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Booking ID:</strong></div>
                                        <div class="col-8">#<?php echo $payment['booking_id']; ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Reptile:</strong></div>
                                        <div class="col-8"><?php echo htmlspecialchars($payment['reptile_name']); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Category:</strong></div>
                                        <div class="col-8"><?php echo htmlspecialchars($payment['category_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Start Date:</strong></div>
                                        <div class="col-8"><?php echo date('d M Y', strtotime($payment['start_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>End Date:</strong></div>
                                        <div class="col-8"><?php echo date('d M Y', strtotime($payment['end_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4"><strong>Total Cost:</strong></div>
                                        <div class="col-8">Rp <?php echo number_format($payment['booking_total'], 0, ',', '.'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="text-center">
                    <a href="edit_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-warning me-2">
                        <i class="fas fa-edit me-1"></i>Edit Payment
                    </a>
                    
                    <?php if ($payment['payment_status'] === 'paid'): ?>
                        <a href="print_receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-info me-2" target="_blank">
                            <i class="fas fa-print me-1"></i>Print Receipt
                        </a>
                    <?php endif; ?>
                    
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>