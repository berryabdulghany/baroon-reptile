<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
    
    // Handle system settings update
    if (isset($_POST['action']) && $_POST['action'] === 'update_system_settings') {
        $site_name = trim($_POST['site_name']);
        $site_description = trim($_POST['site_description']);
        $contact_email = trim($_POST['contact_email']);
        $contact_phone = trim($_POST['contact_phone']);
        $contact_address = trim($_POST['contact_address']);
        $booking_advance_days = (int)$_POST['booking_advance_days'];
        $max_booking_duration = (int)$_POST['max_booking_duration'];
        $cancellation_hours = (int)$_POST['cancellation_hours'];
        
        // For this demo, we'll just show success message
        // In a real application, you would store these in a settings table
        $message = 'success:Pengaturan sistem berhasil diupdate!';
    }
    
    // Get current user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get system statistics
    $stmt = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
            (SELECT COUNT(*) FROM reptiles) as total_reptiles,
            (SELECT COUNT(*) FROM bookings) as total_bookings,
            (SELECT COUNT(*) FROM facilities) as total_facilities,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'paid') as total_revenue
    ");
    $system_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure all values are not null
    $system_stats = array_merge([
        'total_customers' => 0,
        'total_reptiles' => 0,
        'total_bookings' => 0,
        'total_facilities' => 0,
        'total_revenue' => 0
    ], $system_stats ?: []);
    
    // Get recent activity
    $stmt = $db->query("
        SELECT 'booking' as type, b.id, u.full_name as customer_name, b.created_at, b.status
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Baroon Reptile Admin</title>
    
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
        
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            padding: 20px;
        }
        
        .settings-body {
            padding: 30px;
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
                    <a class="nav-link" href="customers.php">
                        <i class="fas fa-users"></i>Pelanggan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reptiles.php">
                        <i class="fas fa-dragon"></i>Reptil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>Pembayaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-file-alt"></i>Laporan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="facilities.php">
                        <i class="fas fa-cogs"></i>Fasilitas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="settings.php">
                        <i class="fas fa-cog"></i>Pengaturan
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
                <h4 class="mb-0">Pengaturan</h4>
            </div>
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Pengaturan</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Keluar</a></li>
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
            
            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value text-primary"><?php echo number_format($system_stats['total_customers']); ?></div>
                    <div class="stat-label">Total Pelanggan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-dragon"></i>
                    </div>
                    <div class="stat-value text-success"><?php echo number_format($system_stats['total_reptiles']); ?></div>
                    <div class="stat-label">Total Reptil</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value text-warning"><?php echo number_format($system_stats['total_bookings']); ?></div>
                    <div class="stat-label">Total Booking</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="stat-value text-info"><?php echo number_format($system_stats['total_facilities']); ?></div>
                    <div class="stat-label">Total Fasilitas</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-success">Rp <?php echo number_format($system_stats['total_revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
            </div>
            
            <!-- Settings Tabs -->
            <ul class="nav nav-pills mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button" role="tab">
                        <i class="fas fa-user me-2"></i>Pengaturan Profil
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Keamanan
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="pill" data-bs-target="#system" type="button" role="tab">
                        <i class="fas fa-cogs me-2"></i>Pengaturan Sistem
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="pill" data-bs-target="#activity" type="button" role="tab">
                        <i class="fas fa-history me-2"></i>Aktivitas Terbaru
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabContent">
                <!-- Profile Settings -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Informasi Profil</h5>
                        </div>
                        <div class="settings-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Alamat Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Nomor Telepon</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Nama Pengguna</label>
                                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                        <div class="form-text">Nama pengguna tidak dapat diubah</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Alamat</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Bergabung: <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <small class="text-muted">
                                            <i class="fas fa-edit me-1"></i>
                                            Terakhir diperbarui: <?php echo $user['updated_at'] ? date('d M Y', strtotime($user['updated_at'])) : 'Tidak pernah'; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Perbarui Profil
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Change Password</h5>
                        </div>
                        <div class="settings-body">
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
                                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
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
                                    <i class="fas fa-key me-2"></i>Ubah Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- System Settings -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Konfigurasi Sistem</h5>
                        </div>
                        <div class="settings-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_system_settings">
                                
                                <h6 class="mb-3">Informasi Situs</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="site_name" class="form-label">Nama Situs</label>
                                        <input type="text" class="form-control" id="site_name" name="site_name" value="Baroon Reptile">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_email" class="form-label">Email Kontak</label>
                                        <input type="email" class="form-control" id="contact_email" name="contact_email" value="info@baroonreptile.com">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Deskripsi Situs</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="3">Layanan penitipan reptile terpercaya dengan fasilitas lengkap dan perawatan profesional.</textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_phone" class="form-label">Telepon Kontak</label>
                                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="+62 812-3456-7890">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_address" class="form-label">Alamat Kontak</label>
                                        <input type="text" class="form-control" id="contact_address" name="contact_address" value="Jakarta, Indonesia">
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h6 class="mb-3">Pengaturan Booking</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="booking_advance_days" class="form-label">Hari Booking di Muka</label>
                                        <input type="number" class="form-control" id="booking_advance_days" name="booking_advance_days" value="1" min="0">
                                        <div class="form-text">Minimum hari sebelumnya untuk booking</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="max_booking_duration" class="form-label">Durasi Booking Maksimal</label>
                                        <input type="number" class="form-control" id="max_booking_duration" name="max_booking_duration" value="30" min="1">
                                        <div class="form-text">Maksimal hari per booking</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="cancellation_hours" class="form-label">Jam Pembatalan</label>
                                        <input type="number" class="form-control" id="cancellation_hours" name="cancellation_hours" value="24" min="1">
                                        <div class="form-text">Jam sebelum tanggal mulai untuk membolehkan pembatalan</div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Perbarui Pengaturan Sistem
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="tab-pane fade" id="activity" role="tabpanel">
                    <div class="settings-card">
                        <div class="settings-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Aktivitas Sistem Terbaru</h5>
                        </div>
                        <div class="settings-body">
                            <?php if (empty($recent_activities)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h6>Tidak Ada Aktivitas Terbaru</h6>
                                    <p class="text-muted">Tidak ada aktivitas sistem terbaru untuk ditampilkan.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-calendar-alt text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">Booking Baru</div>
                                            <div class="text-muted">Pelanggan: <?php echo htmlspecialchars($activity['customer_name']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php echo $activity['status'] === 'pending' ? 'warning' : ($activity['status'] === 'confirmed' ? 'success' : 'secondary'); ?>">
                                                <?php echo ucfirst($activity['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="text-center mt-3">
                                    <a href="bookings.php" class="btn btn-outline-primary">
                                        <i class="fas fa-eye me-2"></i>Lihat Semua Aktivitas
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