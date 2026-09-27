<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

if (!$payment_id) {
    header('Location: payments.php');
    exit;
}

try {
    $db = getDB();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount = $_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $payment_status = $_POST['payment_status'];
        $notes = $_POST['notes'] ?? '';
        
        // Validate inputs
        if (empty($amount) || $amount <= 0) {
            $message = 'error:Amount harus lebih dari 0!';
        } elseif (empty($payment_method)) {
            $message = 'error:Payment method harus dipilih!';
        } elseif (empty($payment_status)) {
            $message = 'error:Payment status harus dipilih!';
        } else {
            // Update payment
            $stmt = $db->prepare("
                UPDATE payments 
                SET amount = ?, payment_method = ?, payment_status = ?, notes = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$amount, $payment_method, $payment_status, $notes, $payment_id])) {
                $message = 'success:Payment berhasil diupdate!';
            } else {
                $message = 'error:Gagal mengupdate payment!';
            }
        }
    }
    
    // Get payment details
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
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        header('Location: payments.php');
        exit;
    }
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
    error_log("Error in edit_payment.php: " . $e->getMessage());
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
    <title>Edit Payment - Baroon Reptile Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .edit-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .edit-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .info-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-edit me-2"></i>Edit Payment</h2>
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Payments
                    </a>
                </div>
                
                <?php if ($message): ?>
                    <?php $msg_type = strpos($message, 'success:') === 0 ? 'success' : 'danger'; ?>
                    <?php $msg_text = substr($message, strpos($message, ':') + 1); ?>
                    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($msg_text); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="edit-card">
                    <div class="edit-header">
                        <h4 class="mb-1">Edit Payment #<?php echo $payment['id']; ?></h4>
                        <p class="mb-0">Created on <?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></p>
                    </div>
                    
                    <div class="card-body">
                        <!-- Booking Information -->
                        <div class="info-section">
                            <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Booking Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($payment['customer_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['customer_email']); ?></p>
                                    <p><strong>Booking ID:</strong> #<?php echo $payment['booking_id']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Reptile:</strong> <?php echo htmlspecialchars($payment['reptile_name']); ?></p>
                                    <p><strong>Category:</strong> <?php echo htmlspecialchars($payment['category_name']); ?></p>
                                    <p><strong>Booking Total:</strong> Rp <?php echo number_format($payment['booking_total'], 0, ',', '.'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Form -->
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" id="amount" name="amount" 
                                                   value="<?php echo $payment['amount']; ?>" required min="1" step="1">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value="">Pilih Metode</option>
                                            <?php foreach ($payment_methods as $method): ?>
                                                <option value="<?php echo $method; ?>" 
                                                        <?php echo $payment['payment_method'] === $method ? 'selected' : ''; ?>>
                                                    <?php echo $method_labels[$method]; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="payment_status" class="form-label">Payment Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="payment_status" name="payment_status" required>
                                            <option value="">Pilih Status</option>
                                            <?php foreach ($status_config as $key => $config): ?>
                                                <option value="<?php echo $key; ?>" 
                                                        <?php echo $payment['payment_status'] === $key ? 'selected' : ''; ?>>
                                                    <?php echo $config['text']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Current Status</label>
                                        <div class="form-control-plaintext">
                                            <?php $status = $status_config[$payment['payment_status']] ?? $status_config['pending']; ?>
                                            <span class="badge bg-<?php echo $status['class']; ?>">
                                                <i class="fas fa-<?php echo $status['icon']; ?> me-1"></i>
                                                <?php echo $status['text']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4" 
                                          placeholder="Add any notes about this payment..."><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <div>
                                    <a href="view_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-outline-info">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                                <div>
                                    <a href="payments.php" class="btn btn-secondary me-2">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Update Payment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-format amount input
        document.getElementById('amount').addEventListener('input', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) {
                this.value = parseInt(value);
            }
        });
        
        // Confirm before updating
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Yakin ingin mengupdate payment ini?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>