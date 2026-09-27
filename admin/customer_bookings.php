<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$message = '';

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
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    
    // Build WHERE clause
    $where_conditions = ['b.customer_id = ?'];
    $params = [$customer_id];
    
    if ($status_filter) {
        $where_conditions[] = 'b.status = ?';
        $params[] = $status_filter;
    }
    
    if ($date_from) {
        $where_conditions[] = 'b.start_date >= ?';
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = 'b.end_date <= ?';
        $params[] = $date_to;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM bookings b $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // Get bookings with details
    $sql = "
        SELECT b.*, 
               r.name as reptile_name, 
               r.species,
               rc.name as category_name,
               p.amount as payment_amount,
               p.payment_status,
               p.payment_method
        FROM bookings b
        LEFT JOIN reptiles r ON b.reptile_id = r.id
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id
        LEFT JOIN payments p ON b.id = p.booking_id
        $where_clause
        ORDER BY b.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get booking statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_bookings,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_bookings,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_price END), 0) as total_revenue
        FROM bookings 
        WHERE customer_id = ?
    ";
    $stmt = $db->prepare($stats_sql);
    $stmt->execute([$customer_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Error: ' . error_detail($e) . '</div>';
    // Initialize stats array to prevent undefined variable warnings
    $stats = [
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'confirmed_bookings' => 0,
        'in_progress_bookings' => 0,
        'completed_bookings' => 0,
        'cancelled_bookings' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Pelanggan - <?php echo htmlspecialchars($customer['full_name']); ?> - Baroon Reptile Admin</title>
    
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
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #4a7c59;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 5px;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            padding: 20px 30px;
        }
        
        .table-responsive {
            border-radius: 0;
        }
        
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #495057;
            padding: 15px;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 5px;
            font-size: 0.875rem;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 20px;
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
            <a href="bookings.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>Booking
            </a>
            <a href="customers.php" class="nav-link active">
                <i class="fas fa-users"></i>Pelanggan
            </a>
            <a href="reptiles.php" class="nav-link">
                <i class="fas fa-dragon"></i>Reptil
            </a>
            <a href="payments.php" class="nav-link">
                <i class="fas fa-credit-card"></i>Pembayaran
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Laporan
            </a>
            <a href="facilities.php" class="nav-link">
                <i class="fas fa-building"></i>Fasilitas
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
                <h4 class="mb-0">Booking Pelanggan</h4>
                <small class="text-muted">Kelola riwayat booking pelanggan</small>
            </div>
            <div class="d-flex align-items-center">
                <a href="view_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-user me-2"></i>Detail Pelanggan
                </a>
                <a href="customers.php" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Pelanggan
                </a>
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['username']; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Keluar</a></li>
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
            
            <!-- Statistics -->
            <div class="row">
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                        <div class="stat-label">Total Booking</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stat-value text-warning"><?php echo $stats['pending_bookings']; ?></div>
                        <div class="stat-label">Menunggu</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stat-value text-primary"><?php echo $stats['confirmed_bookings']; ?></div>
                        <div class="stat-label">Dikonfirmasi</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stat-value text-info"><?php echo $stats['in_progress_bookings']; ?></div>
                        <div class="stat-label">Sedang Berlangsung</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stat-value text-success"><?php echo $stats['completed_bookings']; ?></div>
                        <div class="stat-label">Selesai</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stat-value text-danger"><?php echo $stats['cancelled_bookings']; ?></div>
                        <div class="stat-label">Dibatalkan</div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-card">
                <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter</h5>
                <form method="GET" class="row g-3">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>Sedang Berlangsung</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Dari</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Sampai</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                        <a href="customer_bookings.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Bersihkan
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Bookings Table -->
            <div class="table-card">
                <div class="table-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Riwayat Booking (<?php echo $total_records; ?> data)</h5>
                </div>
                
                <?php if (empty($bookings)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada booking ditemukan</h5>
                        <p class="text-muted">Pelanggan ini belum membuat booking apapun.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID Booking</th>
                                    <th>Reptil</th>
                                    <th>Periode Booking</th>
                                    <th>Status</th>
                                    <th>Pembayaran</th>
                                    <th>Total Harga</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?php echo $booking['id']; ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['reptile_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($booking['species'] ?? ''); ?> • 
                                                    <?php echo htmlspecialchars($booking['category_name']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo date('d M Y', strtotime($booking['start_date'])); ?></strong><br>
                                                <small class="text-muted">sampai <?php echo date('d M Y', strtotime($booking['end_date'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['status'] === 'completed' ? 'success' : 
                                                    ($booking['status'] === 'cancelled' ? 'danger' : 
                                                    ($booking['status'] === 'confirmed' ? 'primary' : 
                                                    ($booking['status'] === 'in_progress' ? 'info' : 'warning'))); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($booking['payment_status']): ?>
                                                <span class="badge bg-<?php echo $booking['payment_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($booking['payment_status']); ?>
                                                </span><br>
                                                <small class="text-muted"><?php echo ucfirst($booking['payment_method']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Belum bayar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></strong>
                                        </td>
                                        <td>
                                            <small><?php echo date('d M Y H:i', strtotime($booking['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (in_array($booking['status'], ['completed', 'cancelled'])): ?>
                                                    <a href="print_receipt.php?booking_id=<?php echo $booking['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Cetak Kwitansi" target="_blank">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center p-3">
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?customer_id=<?php echo $customer_id; ?>&page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?customer_id=<?php echo $customer_id; ?>&page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?customer_id=<?php echo $customer_id; ?>&page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>