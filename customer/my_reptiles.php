<?php
require_once '../config/database.php';
require_once '../includes/upload.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';

try {
    $db = getDB();
    
    // Handle delete action
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $reptile_id = (int)$_POST['id'];
        
        // Check if reptile belongs to current user
        $stmt = $db->prepare("SELECT * FROM reptiles WHERE id = ? AND customer_id = ?");
        $stmt->execute([$reptile_id, $_SESSION['user_id']]);
        $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reptile) {
            // Check if reptile has active bookings
            $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE reptile_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
            $stmt->execute([$reptile_id]);
            $active_bookings = $stmt->fetchColumn();
            
            if ($active_bookings > 0) {
                $message = 'error:Tidak dapat menghapus reptile yang memiliki booking aktif!';
            } else {
                delete_reptile_photo($reptile['photo']);
                
                // Delete reptile
                $stmt = $db->prepare("DELETE FROM reptiles WHERE id = ? AND customer_id = ?");
                if ($stmt->execute([$reptile_id, $_SESSION['user_id']])) {
                    $message = 'success:Reptile berhasil dihapus!';
                } else {
                    $message = 'error:Gagal menghapus reptile!';
                }
            }
        } else {
            $message = 'error:Reptile tidak ditemukan!';
        }
    }
    
    // Get user's reptiles with category info
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name, rc.price_per_day,
               (SELECT COUNT(*) FROM bookings WHERE reptile_id = r.id) as total_bookings,
               (SELECT COUNT(*) FROM bookings WHERE reptile_id = r.id AND status IN ('pending', 'confirmed', 'in_progress')) as active_bookings
        FROM reptiles r 
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id 
        WHERE r.customer_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reptiles - Baroon Reptile</title>
    
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
            transition: width 0.3s ease;
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
            transition: margin-left 0.3s ease;
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
        
        .reptile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .reptile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .reptile-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f8f9fa;
        }
        
        .reptile-info {
            padding: 20px;
        }
        
        .reptile-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c5530;
            margin-bottom: 5px;
        }
        
        .reptile-category {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .reptile-stats {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-value {
            font-weight: 600;
            color: #2c5530;
            display: block;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .reptile-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.875rem;
            border-radius: 8px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .filter-tabs {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .search-box {
            max-width: 300px;
        }
        
        .reptile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand d-flex align-items-center">
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
                    <a class="nav-link active" href="my_reptiles.php">
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
                    <a class="nav-link" href="payments.php">
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
                <button class="btn btn-link me-3 p-0" onclick="toggleSidebar()" style="color: #2c5530;">
                    <i class="fas fa-bars fa-lg"></i>
                </button>
                <h4 class="mb-0">My Reptiles</h4>
                <span class="badge bg-primary ms-3"><?php echo count($reptiles); ?> Reptiles</span>
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
            
            <!-- Filter and Search -->
            <div class="filter-tabs">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <a href="add_reptile.php" class="btn btn-primary me-3">
                                <i class="fas fa-plus me-2"></i>Tambah Reptile
                            </a>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="filter" id="all" autocomplete="off" checked>
                                <label class="btn btn-outline-secondary" for="all">Semua</label>
                                
                                <input type="radio" class="btn-check" name="filter" id="active" autocomplete="off">
                                <label class="btn btn-outline-success" for="active">Aktif</label>
                                
                                <input type="radio" class="btn-check" name="filter" id="inactive" autocomplete="off">
                                <label class="btn btn-outline-danger" for="inactive">Tidak Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="search-box ms-auto">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Cari reptile...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (empty($reptiles)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-dragon empty-icon"></i>
                    <h5>Belum Ada Reptile</h5>
                    <p class="text-muted mb-4">Anda belum menambahkan reptile apapun. Mulai dengan menambahkan reptile pertama Anda!</p>
                    <a href="add_reptile.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Tambah Reptile Pertama
                    </a>
                </div>
            <?php else: ?>
                <!-- Reptiles Grid -->
                <div class="reptile-grid" id="reptilesGrid">
                    <?php foreach ($reptiles as $reptile): ?>
                        <div class="reptile-card" data-name="<?php echo strtolower($reptile['name']); ?>" data-category="<?php echo strtolower($reptile['category_name']); ?>" data-status="<?php echo $reptile['active_bookings'] > 0 ? 'active' : 'inactive'; ?>">
                            <?php if ($reptile['photo']): ?>
                                <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($reptile['name']); ?>" class="reptile-image">
                            <?php else: ?>
                                <div class="reptile-image d-flex align-items-center justify-content-center">
                                    <i class="fas fa-dragon fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reptile-info">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="reptile-name"><?php echo htmlspecialchars($reptile['name']); ?></div>
                                        <div class="reptile-category">
                                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($reptile['category_name']); ?>
                                        </div>
                                    </div>
                                    <span class="status-badge <?php echo $reptile['active_bookings'] > 0 ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $reptile['active_bookings'] > 0 ? 'Aktif' : 'Tidak Aktif'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($reptile['species']): ?>
                                    <div class="text-muted mb-2">
                                        <i class="fas fa-dna me-1"></i><?php echo htmlspecialchars($reptile['species'] ?? ''); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="reptile-stats">
                                    <?php if ($reptile['age']): ?>
                                        <div class="stat-item">
                                            <span class="stat-value"><?php echo htmlspecialchars($reptile['age'] ?? ''); ?></span>
                                            <span class="stat-label">Umur</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($reptile['weight']): ?>
                                        <div class="stat-item">
                                            <span class="stat-value"><?php echo $reptile['weight']; ?> kg</span>
                                            <span class="stat-label">Berat</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo $reptile['total_bookings']; ?></span>
                                        <span class="stat-label">Bookings</span>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-success fw-bold">
                                        Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?>/hari
                                    </div>
                                    <div class="reptile-actions">
                                        <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-primary btn-sm" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($reptile['active_bookings'] == 0): ?>
                                            <button class="btn btn-outline-danger btn-sm" title="Hapus" onclick="confirmDelete(<?php echo $reptile['id']; ?>, '<?php echo htmlspecialchars($reptile['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="create_booking.php?reptile_id=<?php echo $reptile['id']; ?>" class="btn btn-success btn-sm" title="Buat Booking">
                                            <i class="fas fa-calendar-plus"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus reptile <strong id="reptileName"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Tindakan ini tidak dapat dibatalkan!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" class="d-inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteReptileId">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.reptile-card');
            
            cards.forEach(card => {
                const name = card.dataset.name;
                const category = card.dataset.category;
                const isVisible = name.includes(searchTerm) || category.includes(searchTerm);
                card.style.display = isVisible ? 'block' : 'none';
            });
        });
        
        // Filter functionality
        document.querySelectorAll('input[name="filter"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const filter = this.id;
                const cards = document.querySelectorAll('.reptile-card');
                
                cards.forEach(card => {
                    const status = card.dataset.status;
                    let isVisible = true;
                    
                    if (filter === 'active' && status !== 'active') {
                        isVisible = false;
                    } else if (filter === 'inactive' && status !== 'inactive') {
                        isVisible = false;
                    }
                    
                    card.style.display = isVisible ? 'block' : 'none';
                });
            });
        });
        
        // Delete confirmation
        function confirmDelete(id, name) {
            document.getElementById('reptileName').textContent = name;
            document.getElementById('deleteReptileId').value = id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }
    </script>
</body>
</html>