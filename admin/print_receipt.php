<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Kuitansi bisa dibuka dari halaman pembayaran (?id=) atau dari detail booking (?booking_id=)
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$payment_id && !$booking_id) {
    header('Location: payments.php');
    exit;
}

try {
    $db = getDB();
    
    // Get payment details with booking and customer info
    $stmt = $db->prepare("
        SELECT p.*, 
               b.id as booking_id, b.start_date, b.end_date, b.total_price as booking_total, b.status as booking_status,
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone, u.address as customer_address,
               r.name as reptile_name, r.species, r.age, r.gender, r.special_needs,
               rc.name as category_name, rc.description as category_description,
               DATEDIFF(b.end_date, b.start_date) as total_days
        FROM payments p 
        LEFT JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN users u ON b.customer_id = u.id
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE " . ($payment_id ? "p.id = ?" : "p.booking_id = ?") . " AND p.payment_status = 'paid'
    ");
    $stmt->execute([$payment_id ?: $booking_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        header('Location: payments.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error in print_receipt.php: " . $e->getMessage());
    header('Location: payments.php');
    exit;
}

$method_labels = [
    'cash' => 'Cash',
    'transfer' => 'Bank Transfer',
    'credit_card' => 'Credit Card',
    'e_wallet' => 'E-Wallet'
];

// Duration is now calculated in SQL query as total_days
$duration = $payment['total_days'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt #<?php echo $payment['id']; ?> - Baroon Reptile</title>
    
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .receipt-container {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 30px;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-name {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .company-tagline {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .receipt-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin-top: 20px;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .info-section {
            flex: 1;
            min-width: 250px;
            margin-bottom: 20px;
        }
        
        .info-section h4 {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .payment-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .total-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: #e8f5e8;
            border-radius: 8px;
        }
        
        .status-paid {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            color: #666;
        }
        
        .print-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
        
        @media (max-width: 600px) {
            .receipt-info {
                flex-direction: column;
            }
            
            .info-section {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">BAROON REPTILE</div>
            <div class="company-tagline">Premium Reptile Boarding Services</div>
            <div class="receipt-title">PAYMENT RECEIPT</div>
        </div>
        
        <!-- Print Button -->
        <div class="text-center no-print">
            <button class="print-button" onclick="window.print()">🖨️ Print Receipt</button>
            <a href="payments.php" style="margin-left: 10px; text-decoration: none; color: #666;">← Back to Payments</a>
        </div>
        
        <!-- Receipt Information -->
        <div class="receipt-info">
            <div class="info-section">
                <h4>Receipt Details</h4>
                <div class="info-row">
                    <span class="info-label">Receipt #:</span>
                    <span class="info-value"><?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Date:</span>
                    <span class="info-value"><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?php echo $method_labels[$payment['payment_method']] ?? $payment['payment_method']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value"><span class="status-paid">PAID</span></span>
                </div>
            </div>
            
            <div class="info-section">
                <h4>Customer Information</h4>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['customer_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['customer_email']); ?></span>
                </div>
                <?php if ($payment['customer_phone']): ?>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['customer_phone'] ?? ''); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($payment['customer_address']): ?>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['customer_address'] ?? ''); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Service Details -->
        <div class="info-section">
            <h4>Service Details</h4>
            <div class="info-row">
                <span class="info-label">Booking ID:</span>
                <span class="info-value">#<?php echo $payment['booking_id']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Reptile:</span>
                <span class="info-value"><?php echo htmlspecialchars($payment['reptile_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Category:</span>
                <span class="info-value"><?php echo htmlspecialchars($payment['category_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Check-in Date:</span>
                <span class="info-value"><?php echo date('d M Y', strtotime($payment['start_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Check-out Date:</span>
                <span class="info-value"><?php echo date('d M Y', strtotime($payment['end_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Duration:</span>
                <span class="info-value"><?php echo $duration; ?> day(s)</span>
            </div>
        </div>
        
        <!-- Payment Summary -->
        <div class="payment-summary">
            <h4 style="text-align: center; margin-bottom: 20px;">Payment Summary</h4>
            <div class="info-row">
                <span class="info-label">Boarding Service (<?php echo $duration; ?> days):</span>
                <span class="info-value">Rp <?php echo number_format($payment['booking_total'], 0, ',', '.'); ?></span>
            </div>
            <?php if ($payment['amount'] != $payment['booking_total']): ?>
            <div class="info-row">
                <span class="info-label">Adjustment:</span>
                <span class="info-value">Rp <?php echo number_format($payment['amount'] - $payment['booking_total'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-amount">
                TOTAL PAID: Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?>
            </div>
        </div>
        
        <?php if ($payment['notes']): ?>
        <div class="info-section">
            <h4>Notes</h4>
            <p style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 0;">
                <?php echo nl2br(htmlspecialchars($payment['notes'] ?? '')); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for choosing Baroon Reptile!</strong></p>
            <p>For any questions about this receipt, please contact us at info@baroonreptile.com</p>
            <p style="font-size: 0.9rem; color: #999;">This is a computer-generated receipt and does not require a signature.</p>
            <p style="font-size: 0.8rem; color: #999; margin-top: 20px;">
                Generated on <?php echo date('d M Y H:i:s'); ?> | Receipt #<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?>
            </p>
        </div>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); }
        
        // Print function
        function printReceipt() {
            window.print();
        }
        
        // Keyboard shortcut for printing
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>