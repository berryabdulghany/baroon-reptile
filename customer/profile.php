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
    
    // Handle profile update
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Validate input
        if (empty($full_name)) {
            $message = 'error:Nama lengkap tidak boleh kosong!';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'error:Email tidak valid!';
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetchColumn()) {
                $message = 'error:Email sudah digunakan!';
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                if ($stmt->execute([$full_name, $email, $phone, $address, $_SESSION['user_id']])) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $message = 'success:Profil berhasil diupdate!';
                } else {
                    $message = 'error:Gagal mengupdate profil!';
                }
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'error:Semua field password harus diisi!';
        } elseif ($new_password !== $confirm_password) {
            $message = 'error:Konfirmasi password tidak cocok!';
        } elseif (strlen($new_password) < 6) {
            $message = 'error:Password baru minimal 6 karakter!';
        } else {
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $stored_password = $stmt->fetchColumn();
            
            if (!password_verify($current_password, $stored_password)) {
                $message = 'error:Password saat ini salah!';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $message = 'success:Password berhasil diubah!';
                } else {
                    $message = 'error:Gagal mengubah password!';
                }
            }
        }
    }
    
    // Get current user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user statistics
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM reptiles WHERE customer_id = ?) as total_reptiles,
            (SELECT COUNT(*) FROM reptiles WHERE customer_id = ? AND status = 'active') as active_reptiles,
            (SELECT COUNT(*) FROM bookings WHERE customer_id = ?) as total_bookings,
            (SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status IN ('confirmed', 'in_progress')) as active_bookings,
            (SELECT COALESCE(SUM(amount), 0) FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE b.customer_id = ? AND p.payment_status = 'paid') as total_spent
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure all stats values are not null
    $stats = array_merge([
        'total_reptiles' => 0,
        'active_reptiles' => 0,
        'total_bookings' => 0,
        'active_bookings' => 0,
        'total_spent' => 0
    ], $stats ?: []);
    
    // Get recent bookings
    $stmt = $db->prepare("
        SELECT b.*, r.name as reptile_name, r.species, rc.name as category_name
        FROM bookings b
        JOIN reptiles r ON b.reptile_id = r.id
        JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE b.customer_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent reptiles
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name
        FROM reptiles r
        JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE r.customer_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
}

// Status configurations
$status_config = [
    'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Pending'],
    'confirmed' => ['class' => 'info', 'icon' => 'check', 'text' => 'Confirmed'],
    'active' => ['class' => 'success', 'icon' => 'play', 'text' => 'Active'],
    'completed' => ['class' => 'primary', 'icon' => 'flag-checkered', 'text' => 'Completed'],
    'cancelled' => ['class' => 'danger', 'icon' => 'times', 'text' => 'Cancelled']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Baroon Reptile</title>
    
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
        
        .profile-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .stats-grid {
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
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .profile-card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-card-body {
            padding: 30px;
        }
        
        .nav-pills .nav-link {
            color: #6c757d;
            border-radius: 10px;
            margin-right: 10px;
            padding: 10px 20px;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
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
            background: linear-gradient(135deg, #2c5530, #1a3d1f);
            transform: translateY(-2px);
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-dragon me-2"></i>Baroon Reptile
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
                    <a class="nav-link" href="add_reptile.php">
                        <i class="fas fa-plus"></i>Add Reptile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>My Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="create_booking.php">
                        <i class="fas fa-plus-circle"></i>New Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="profile.php">
                        <i class="fas fa-user"></i>Profile
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
                <h4 class="mb-0">My Profile</h4>
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
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h2 class="mb-2"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <p class="mb-2"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                        <?php if ($user['phone']): ?>
                            <p class="mb-2"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($user['phone'] ?? ''); ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><i class="fas fa-calendar me-2"></i>Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-dragon"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo $stats['total_reptiles']; ?></div>
                    <div class="stat-label">Total Reptiles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo $stats['active_reptiles']; ?></div>
                    <div class="stat-label">Active Reptiles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo $stats['total_bookings']; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-play"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo $stats['active_bookings']; ?></div>
                    <div class="stat-label">Active Bookings</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-success">Rp <?php echo number_format($stats['total_spent'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
            
            <!-- Profile Tabs -->
            <ul class="nav nav-pills mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="pill" data-bs-target="#info" type="button" role="tab">
                        <i class="fas fa-user me-2"></i>Profile Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="pill" data-bs-target="#activity" type="button" role="tab">
                        <i class="fas fa-history me-2"></i>Recent Activity
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="profileTabContent">
                <!-- Profile Information -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                        </div>
                        <div class="profile-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                        <div class="form-text">Username cannot be changed</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Joined: <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <small class="text-muted">
                                            <i class="fas fa-edit me-1"></i>
                                            Last updated: <?php echo $user['updated_at'] ? date('d M Y', strtotime($user['updated_at'])) : 'Never'; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="profile-card">
                        <div class="profile-card-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Change Password</h5>
                        </div>
                        <div class="profile-card-body">
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <div class="form-text" id="passwordHelp">Password must be at least 6 characters long</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text" id="confirmHelp"></div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="profile-card">
                                <div class="profile-card-header">
                                    <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Recent Bookings</h6>
                                </div>
                                <div class="profile-card-body p-0">
                                    <?php if (empty($recent_bookings)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                            <h6>No Bookings Yet</h6>
                                            <p class="text-muted">You haven't made any bookings yet.</p>
                                            <a href="create_booking.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus me-2"></i>Create First Booking
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                            <?php $status = $status_config[$booking['status']]; ?>
                                            <div class="activity-item">
                                                <div class="activity-icon">
                                                    <i class="fas fa-calendar-alt text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($booking['reptile_name']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($booking['species'] ?? ''); ?> - <?php echo htmlspecialchars($booking['category_name']); ?></div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('d M Y', strtotime($booking['start_date'])); ?> - <?php echo date('d M Y', strtotime($booking['end_date'])); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="status-badge bg-<?php echo $status['class']; ?> text-white">
                                                        <i class="fas fa-<?php echo $status['icon']; ?>"></i>
                                                        <?php echo $status['text']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="text-center p-3">
                                            <a href="bookings.php" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-2"></i>View All Bookings
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="profile-card">
                                <div class="profile-card-header">
                                    <h6 class="mb-0"><i class="fas fa-dragon me-2"></i>Recent Reptiles</h6>
                                </div>
                                <div class="profile-card-body p-0">
                                    <?php if (empty($recent_reptiles)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-dragon fa-3x text-muted mb-3"></i>
                                            <h6>No Reptiles Yet</h6>
                                            <p class="text-muted">You haven't registered any reptiles yet.</p>
                                            <a href="add_reptile.php" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus me-2"></i>Add First Reptile
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recent_reptiles as $reptile): ?>
                                            <div class="activity-item">
                                                <div class="activity-icon">
                                                    <?php if ($reptile['photo']): ?>
                                                        <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($reptile['name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <i class="fas fa-dragon text-success"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($reptile['name']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($reptile['species'] ?? ''); ?> - <?php echo htmlspecialchars($reptile['category_name']); ?></div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Added <?php echo date('d M Y', strtotime($reptile['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="status-badge bg-<?php echo $reptile['status'] === 'active' ? 'success' : 'secondary'; ?> text-white">
                                                        <i class="fas fa-<?php echo $reptile['status'] === 'active' ? 'check' : 'pause'; ?>"></i>
                                                        <?php echo ucfirst($reptile['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="text-center p-3">
                                            <a href="my_reptiles.php" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye me-2"></i>View All Reptiles
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                button.classList.remove('fa-eye');
                button.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                button.classList.remove('fa-eye-slash');
                button.classList.add('fa-eye');
            }
        }
        
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const helpText = document.getElementById('passwordHelp');
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength++;
            else feedback.push('at least 6 characters');
            
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('lowercase letter');
            
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('uppercase letter');
            
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('number');
            
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('special character');
            
            // Update strength bar
            strengthBar.className = 'password-strength';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                helpText.textContent = 'Weak password. Add: ' + feedback.slice(0, 2).join(', ');
                helpText.className = 'form-text text-danger';
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-medium');
                helpText.textContent = 'Medium strength. Consider adding: ' + feedback.slice(0, 1).join(', ');
                helpText.className = 'form-text text-warning';
            } else {
                strengthBar.classList.add('strength-strong');
                helpText.textContent = 'Strong password!';
                helpText.className = 'form-text text-success';
            }
        });
        
        // Confirm password checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            const helpText = document.getElementById('confirmHelp');
            
            if (confirm === '') {
                helpText.textContent = '';
                helpText.className = 'form-text';
            } else if (password === confirm) {
                helpText.textContent = 'Passwords match!';
                helpText.className = 'form-text text-success';
            } else {
                helpText.textContent = 'Passwords do not match';
                helpText.className = 'form-text text-danger';
            }
        });
    </script>
</body>
</html>