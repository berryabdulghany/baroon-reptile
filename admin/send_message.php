<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$message = '';
$success = false;

if (!$customer_id) {
    header('Location: customers.php');
    exit;
}

try {
    $db = getDB();
    
    // Get customer info
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header('Location: customers.php?error=customer_not_found');
        exit;
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = trim($_POST['subject'] ?? '');
        $message_content = trim($_POST['message'] ?? '');
        $message_type = $_POST['message_type'] ?? 'general';
        $priority = $_POST['priority'] ?? 'normal';
        
        $errors = [];
        
        if (empty($subject)) {
            $errors[] = 'Subject is required';
        }
        
        if (empty($message_content)) {
            $errors[] = 'Message content is required';
        }
        
        if (empty($errors)) {
            try {
                // Insert the message
                $stmt = $db->prepare("
                    INSERT INTO messages (from_user_id, to_user_id, subject, message, message_type, priority) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $customer_id,
                    $subject,
                    $message_content,
                    $message_type,
                    $priority
                ]);
                
                $success = true;
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Message sent successfully!</div>';
                
                // Clear form data
                $_POST = [];
                
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error sending message: ' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>' . implode('<br>', $errors) . '</div>';
        }
    }
    
    // Get recent messages to this customer
    $stmt = $db->prepare("
        SELECT m.*, u.full_name as from_name, u.username as from_username
        FROM messages m
        LEFT JOIN users u ON m.from_user_id = u.id
        WHERE m.to_user_id = ?
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$customer_id]);
    $recent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Message - <?php echo htmlspecialchars($customer['full_name']); ?> - Baroon Reptile Admin</title>
    
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
        
        .message-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .message-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            padding: 20px 30px;
        }
        
        .message-body {
            padding: 30px;
        }
        
        .customer-info {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-right: 15px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2c5530, #1a3a1f);
            transform: translateY(-2px);
        }
        
        .recent-messages {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .message-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.3s ease;
        }
        
        .message-item:hover {
            background-color: #f8f9fa;
        }
        
        .message-item:last-child {
            border-bottom: none;
        }
        
        .priority-badge {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .priority-low {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .priority-normal {
            background: #d4edda;
            color: #155724;
        }
        
        .priority-high {
            background: #fff3cd;
            color: #856404;
        }
        
        .priority-urgent {
            background: #f8d7da;
            color: #721c24;
        }
        
        .message-type-badge {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #e9ecef;
            color: #495057;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <i class="fas fa-leaf me-2"></i>Baroon Reptile
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="customers.php" class="nav-link active">
                <i class="fas fa-users"></i>Pelanggan
            </a>
            <a href="reptiles.php" class="nav-link">
                <i class="fas fa-dragon"></i>Reptil
            </a>
            <a href="bookings.php" class="nav-link">
                <i class="fas fa-calendar-check"></i>Booking
            </a>
            <a href="payments.php" class="nav-link">
                <i class="fas fa-money-bill-wave"></i>Pembayaran
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Laporan
            </a>
            <a href="facilities.php" class="nav-link">
                <i class="fas fa-building"></i>Fasilitas
            </a>
            <a href="categories.php" class="nav-link">
                <i class="fas fa-tags"></i>Kategori
            </a>
            <a href="testimonials.php" class="nav-link">
                <i class="fas fa-star"></i>Testimoni
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>Pengaturan
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div>
                <h4 class="mb-0">Send Message</h4>
                <small class="text-muted">Send message to customer</small>
            </div>
            <div class="d-flex align-items-center">
                <a href="view_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-user me-2"></i>Customer Detail
                </a>
                <a href="customers.php" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Customers
                </a>
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['username']; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php if ($message): echo $message; endif; ?>
            
            <!-- Customer Info -->
            <div class="customer-info d-flex align-items-center">
                <div class="customer-avatar">
                    <?php echo strtoupper(substr($customer['full_name'], 0, 2)); ?>
                </div>
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($customer['full_name']); ?></h4>
                    <p class="mb-0 opacity-75">@<?php echo htmlspecialchars($customer['username']); ?> • <?php echo htmlspecialchars($customer['email']); ?></p>
                </div>
            </div>
            
            <div class="row">
                <!-- Send Message Form -->
                <div class="col-lg-8">
                    <div class="message-card">
                        <div class="message-header">
                            <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Compose Message</h5>
                        </div>
                        <div class="message-body">
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                                        <input type="text" name="subject" class="form-control" 
                                               placeholder="Enter message subject" 
                                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Message Type</label>
                                        <select name="message_type" class="form-select">
                                            <option value="general" <?php echo ($_POST['message_type'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                                            <option value="booking" <?php echo ($_POST['message_type'] ?? '') === 'booking' ? 'selected' : ''; ?>>Booking Related</option>
                                            <option value="payment" <?php echo ($_POST['message_type'] ?? '') === 'payment' ? 'selected' : ''; ?>>Payment Related</option>
                                            <option value="reminder" <?php echo ($_POST['message_type'] ?? '') === 'reminder' ? 'selected' : ''; ?>>Reminder</option>
                                            <option value="notification" <?php echo ($_POST['message_type'] ?? '') === 'notification' ? 'selected' : ''; ?>>Notification</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select">
                                        <option value="low" <?php echo ($_POST['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="normal" <?php echo ($_POST['priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                        <option value="high" <?php echo ($_POST['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="8" 
                                              placeholder="Type your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            The customer will receive this message in their account.
                                        </small>
                                    </div>
                                    <div>
                                        <button type="reset" class="btn btn-outline-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Clear
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Messages -->
                <div class="col-lg-4">
                    <div class="recent-messages">
                        <div class="message-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Messages</h5>
                        </div>
                        
                        <?php if (empty($recent_messages)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-envelope-open fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No messages sent yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_messages as $msg): ?>
                                <div class="message-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($msg['subject']); ?></h6>
                                        <div>
                                            <span class="priority-badge priority-<?php echo $msg['priority']; ?>">
                                                <?php echo ucfirst($msg['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="message-type-badge">
                                            <?php echo ucfirst(str_replace('_', ' ', $msg['message_type'])); ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo date('d M Y H:i', strtotime($msg['created_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <p class="text-muted small mb-2">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            From: <?php echo htmlspecialchars($msg['from_name']); ?>
                                        </small>
                                        <span class="badge bg-<?php echo $msg['status'] === 'read' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($msg['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($success): ?>
    <script>
        // Auto-clear success message after 3 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>