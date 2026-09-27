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
    
    // Handle notification settings update
    if (isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $booking_reminders = isset($_POST['booking_reminders']) ? 1 : 0;
        $promotional_emails = isset($_POST['promotional_emails']) ? 1 : 0;
        
        // For this demo, we'll store in session (in real app, you'd have a user_settings table)
        $_SESSION['email_notifications'] = $email_notifications;
        $_SESSION['booking_reminders'] = $booking_reminders;
        $_SESSION['promotional_emails'] = $promotional_emails;
        
        $message = 'success:Pengaturan notifikasi berhasil disimpan!';
    }
    
    // Handle privacy settings update
    if (isset($_POST['action']) && $_POST['action'] === 'update_privacy') {
        $profile_visibility = $_POST['profile_visibility'];
        $show_booking_history = isset($_POST['show_booking_history']) ? 1 : 0;
        $allow_contact = isset($_POST['allow_contact']) ? 1 : 0;
        
        // For this demo, we'll store in session
        $_SESSION['profile_visibility'] = $profile_visibility;
        $_SESSION['show_booking_history'] = $show_booking_history;
        $_SESSION['allow_contact'] = $allow_contact;
        
        $message = 'success:Pengaturan privasi berhasil disimpan!';
    }
    
    // Handle account deletion request
    if (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
        $password = $_POST['delete_password'];
        $confirmation = $_POST['delete_confirmation'];
        
        if ($confirmation !== 'DELETE') {
            $message = 'error:Konfirmasi penghapusan tidak valid!';
        } else {
            // Verify password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $stored_password = $stmt->fetchColumn();
            
            if (!password_verify($password, $stored_password)) {
                $message = 'error:Password salah!';
            } else {
                // Check for active bookings
                $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
                $stmt->execute([$_SESSION['user_id']]);
                $active_bookings = $stmt->fetchColumn();
                
                if ($active_bookings > 0) {
                    $message = 'error:Tidak dapat menghapus akun karena masih ada booking aktif!';
                } else {
                    // Soft delete user account
                    $stmt = $db->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$_SESSION['user_id']])) {
                        session_destroy();
                        header('Location: ../index.php?message=account_deleted');
                        exit;
                    } else {
                        $message = 'error:Gagal menghapus akun!';
                    }
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
            (SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status IN ('pending', 'confirmed', 'in_progress')) as active_bookings,
            (SELECT COALESCE(SUM(amount), 0) FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE b.customer_id = ? AND p.payment_status = 'paid') as total_spent
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get notification settings (from session for demo)
    $email_notifications = $_SESSION['email_notifications'] ?? 1;
    $booking_reminders = $_SESSION['booking_reminders'] ?? 1;
    $promotional_emails = $_SESSION['promotional_emails'] ?? 0;
    
    // Get privacy settings (from session for demo)
    $profile_visibility = $_SESSION['profile_visibility'] ?? 'private';
    $show_booking_history = $_SESSION['show_booking_history'] ?? 0;
    $allow_contact = $_SESSION['allow_contact'] ?? 1;
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Baroon Reptile</title>
    
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
        
        .settings-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .settings-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
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
        
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .settings-card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .settings-card-body {
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
        
        .form-check-input:checked {
            background-color: #4a7c59;
            border-color: #4a7c59;
        }
        
        .form-switch .form-check-input {
            width: 3rem;
            height: 1.5rem;
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
        
        .btn-danger {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .setting-item {
            padding: 20px;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
        .setting-info {
            flex-grow: 1;
        }
        
        .setting-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .setting-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .danger-zone h5 {
            color: #e53e3e;
            margin-bottom: 15px;
        }
        
        .danger-zone p {
            color: #744210;
            margin-bottom: 20px;
        }
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
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
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
                <h4 class="mb-0">Settings</h4>
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
            
            <!-- Settings Header -->
            <div class="settings-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2"><i class="fas fa-cog me-3"></i>Account Settings</h2>
                        <p class="mb-0">Manage your account preferences and privacy settings</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="text-white-50">
                            <small>Last login: <?php echo date('d M Y, H:i', strtotime($user['updated_at'] ?? $user['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
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
            </div>
            
            <!-- Settings Tabs -->
            <ul class="nav nav-pills mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="notifications-tab" data-bs-toggle="pill" data-bs-target="#notifications" type="button" role="tab">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="privacy-tab" data-bs-toggle="pill" data-bs-target="#privacy" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Privacy
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="account-tab" data-bs-toggle="pill" data-bs-target="#account" type="button" role="tab">
                        <i class="fas fa-user-cog me-2"></i>Account
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabContent">
                <!-- Notifications Settings -->
                <div class="tab-pane fade show active" id="notifications" role="tabpanel">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                        </div>
                        <div class="settings-card-body p-0">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_notifications">
                                
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <div class="setting-title">Email Notifications</div>
                                        <div class="setting-description">Receive important updates via email</div>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <div class="setting-title">Booking Reminders</div>
                                        <div class="setting-description">Get reminded about upcoming bookings</div>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="booking_reminders" name="booking_reminders" <?php echo $booking_reminders ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <div class="setting-title">Promotional Emails</div>
                                        <div class="setting-description">Receive special offers and promotions</div>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="promotional_emails" name="promotional_emails" <?php echo $promotional_emails ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                
                                <div class="p-3 border-top">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Privacy Settings -->
                <div class="tab-pane fade" id="privacy" role="tabpanel">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Privacy Settings</h5>
                        </div>
                        <div class="settings-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_privacy">
                                
                                <div class="mb-4">
                                    <label for="profile_visibility" class="form-label">Profile Visibility</label>
                                    <select class="form-select" id="profile_visibility" name="profile_visibility">
                                        <option value="private" <?php echo $profile_visibility === 'private' ? 'selected' : ''; ?>>Private - Only visible to me</option>
                                        <option value="public" <?php echo $profile_visibility === 'public' ? 'selected' : ''; ?>>Public - Visible to other users</option>
                                    </select>
                                    <div class="form-text">Control who can see your profile information</div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="show_booking_history" name="show_booking_history" <?php echo $show_booking_history ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_booking_history">
                                            <strong>Show Booking History</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Allow others to see your booking history (if profile is public)</div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="allow_contact" name="allow_contact" <?php echo $allow_contact ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_contact">
                                            <strong>Allow Contact</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Allow other users to contact you through the platform</div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Privacy Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Account Settings -->
                <div class="tab-pane fade" id="account" role="tabpanel">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h5 class="mb-0"><i class="fas fa-user-cog me-2"></i>Account Information</h5>
                        </div>
                        <div class="settings-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Account Details</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?>
                                        </li>
                                        <li class="mb-2">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                                        </li>
                                        <li class="mb-2">
                                            <strong>Member Since:</strong> <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                        </li>
                                        <li class="mb-2">
                                            <strong>Account Status:</strong> 
                                            <span class="badge bg-success">Active</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Quick Actions</h6>
                                    <div class="d-grid gap-2">
                                        <a href="profile.php" class="btn btn-outline-primary">
                                            <i class="fas fa-edit me-2"></i>Edit Profile
                                        </a>
                                        <a href="profile.php#security" class="btn btn-outline-warning">
                                            <i class="fas fa-key me-2"></i>Change Password
                                        </a>
                                        <a href="../auth/logout.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Danger Zone -->
                    <div class="danger-zone">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                        <p>Once you delete your account, there is no going back. Please be certain.</p>
                        
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash me-2"></i>Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning!</strong> This action cannot be undone. All your data will be permanently deleted.
                        </div>
                        
                        <div class="mb-3">
                            <label for="delete_password" class="form-label">Enter your password to confirm</label>
                            <input type="password" class="form-control" id="delete_password" name="delete_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="delete_confirmation" class="form-label">Type "DELETE" to confirm</label>
                            <input type="text" class="form-control" id="delete_confirmation" name="delete_confirmation" placeholder="DELETE" required>
                        </div>
                        
                        <div class="form-text">
                            <strong>What will be deleted:</strong>
                            <ul>
                                <li>Your profile and personal information</li>
                                <li>All your reptile records</li>
                                <li>Booking history (completed bookings only)</li>
                                <li>Account preferences and settings</li>
                            </ul>
                            <strong>Note:</strong> You cannot delete your account if you have active bookings.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete My Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Confirmation for delete account
        document.getElementById('deleteAccountModal').addEventListener('show.bs.modal', function() {
            document.getElementById('delete_password').value = '';
            document.getElementById('delete_confirmation').value = '';
        });
        
        // Real-time validation for delete confirmation
        document.getElementById('delete_confirmation').addEventListener('input', function() {
            const submitBtn = this.closest('form').querySelector('button[type="submit"]');
            if (this.value === 'DELETE') {
                submitBtn.disabled = false;
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                submitBtn.disabled = true;
                this.classList.remove('is-valid');
                if (this.value.length > 0) {
                    this.classList.add('is-invalid');
                }
            }
        });
    </script>
</body>
</html>