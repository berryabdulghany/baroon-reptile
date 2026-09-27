<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';
$reptile = null;

try {
    $db = getDB();
    
    // Get reptile if ID provided
    if (isset($_GET['reptile_id'])) {
        $stmt = $db->prepare("
            SELECT r.*, rc.name as category_name, rc.price_per_day 
            FROM reptiles r 
            LEFT JOIN reptile_categories rc ON r.category_id = rc.id 
            WHERE r.id = ? AND r.customer_id = ?
        ");
        $stmt->execute([$_GET['reptile_id'], $_SESSION['user_id']]);
        $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reptile) {
            header('Location: my_reptiles.php');
            exit;
        }
    }
    
    // Get user's reptiles
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name, rc.price_per_day 
        FROM reptiles r 
        LEFT JOIN reptile_categories rc ON r.category_id = rc.id 
        WHERE r.customer_id = ? 
        ORDER BY r.name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get facilities
    $stmt = $db->query("SELECT * FROM facilities WHERE status = 'available' ORDER BY name");
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($_POST) {
        $reptile_id = $_POST['reptile_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $facility_ids = isset($_POST['facilities']) ? $_POST['facilities'] : [];
        $special_instructions = trim($_POST['special_instructions']);
        
        // Validasi input
        if (empty($reptile_id) || empty($start_date) || empty($end_date)) {
            $error = 'Reptile, tanggal mulai, dan tanggal selesai harus diisi!';
        } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
            $error = 'Tanggal mulai tidak boleh kurang dari hari ini!';
        } elseif (strtotime($end_date) <= strtotime($start_date)) {
            $error = 'Tanggal selesai harus lebih besar dari tanggal mulai!';
        } else {
            // Check if reptile belongs to user
            $stmt = $db->prepare("SELECT * FROM reptiles r LEFT JOIN reptile_categories rc ON r.category_id = rc.id WHERE r.id = ? AND r.customer_id = ?");
            $stmt->execute([$reptile_id, $_SESSION['user_id']]);
            $selected_reptile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$selected_reptile) {
                $error = 'Reptile tidak ditemukan!';
            } else {
                // Check for overlapping bookings
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM bookings 
                    WHERE reptile_id = ? 
                    AND status IN ('pending', 'confirmed', 'in_progress')
                    AND (
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date >= ? AND end_date <= ?)
                    )
                ");
                $stmt->execute([
                    $reptile_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date
                ]);
                $overlapping = $stmt->fetchColumn();
                
                if ($overlapping > 0) {
                    $error = 'Sudah ada booking aktif untuk reptile ini pada tanggal yang dipilih!';
                } else {
                    // Calculate total days and cost
                    $start = new DateTime($start_date);
                    $end = new DateTime($end_date);
                    $days = $end->diff($start)->days;
                    
                    $base_cost = $days * $selected_reptile['price_per_day'];
                    $facility_cost = 0;
                    
                    // Calculate facility costs
                    if (!empty($facility_ids)) {
                        $facility_placeholders = str_repeat('?,', count($facility_ids) - 1) . '?';
                        $stmt = $db->prepare("SELECT SUM(price_per_day) FROM facilities WHERE id IN ($facility_placeholders) AND status = 'available'");
                        $stmt->execute($facility_ids);
                        $facility_cost = $stmt->fetchColumn() * $days;
                    }
                    
                    $total_price = $base_cost + $facility_cost;
                    
                    // Insert booking
                    $stmt = $db->prepare("
                        INSERT INTO bookings (customer_id, reptile_id, start_date, end_date, total_days, price_per_day, total_price, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    
                    if ($stmt->execute([
                        $_SESSION['user_id'], $reptile_id, $start_date, $end_date, 
                        $days, $selected_reptile['price_per_day'], $total_price, $special_instructions
                    ])) {
                        $booking_id = $db->lastInsertId();
                        
                        // Note: Facility booking relationship not implemented yet
                        // booking_facilities table doesn't exist in current schema
                        
                        $success = 'Booking berhasil dibuat! ID Booking: ' . $booking_id;
                        // Reset form
                        $_POST = array();
                    } else {
                        $error = 'Terjadi kesalahan saat menyimpan booking.';
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $error = error_detail($e);
    // Log the error for debugging
    error_log('Booking creation error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Booking - Baroon Reptile</title>
    
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
        
        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
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
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 85, 48, 0.3);
        }
        
        .reptile-preview {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }
        
        .reptile-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .facility-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .facility-card:hover {
            border-color: #4a7c59;
        }
        
        .facility-card.selected {
            border-color: #4a7c59;
            background: #f0f8f0;
        }
        
        .cost-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .cost-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .cost-total {
            border-top: 2px solid #dee2e6;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .date-info {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-dragon me-2"></i>Baroon
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
                    <a class="nav-link active" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="care_reports.php">
                        <i class="fas fa-file-alt"></i>Care Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
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
                <h4 class="mb-0">Buat Booking Baru</h4>
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
            <div class="row">
                <div class="col-lg-8">
                    <div class="form-card">
                        <div class="d-flex align-items-center mb-4">
                            <a href="bookings.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h5 class="mb-0">Informasi Booking</h5>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <div class="mt-2">
                                    <a href="bookings.php" class="btn btn-sm btn-outline-success me-2">
                                        <i class="fas fa-list me-1"></i>Lihat Bookings
                                    </a>
                                    <a href="create_booking.php" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus me-1"></i>Buat Booking Lagi
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($user_reptiles)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>Anda belum memiliki reptile. 
                                <a href="add_reptile.php" class="alert-link">Tambah reptile terlebih dahulu</a>.
                            </div>
                        <?php else: ?>
                            <form method="POST" id="bookingForm">
                                <?php echo csrf_field(); ?>
                                <div class="mb-3">
                                    <label for="reptile_id" class="form-label">
                                        <i class="fas fa-dragon me-2"></i>Pilih Reptile *
                                    </label>
                                    <select class="form-control" id="reptile_id" name="reptile_id" required onchange="updateReptilePreview()">
                                        <option value="">Pilih Reptile</option>
                                        <?php foreach ($user_reptiles as $r): ?>
                                            <option value="<?php echo $r['id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($r['name']); ?>"
                                                    data-category="<?php echo htmlspecialchars($r['category_name']); ?>"
                                                    data-price="<?php echo $r['price_per_day']; ?>"
                                                    data-photo="<?php echo htmlspecialchars($r['photo'] ?? ''); ?>"
                                                    data-species="<?php echo htmlspecialchars($r['species'] ?? ''); ?>"
                                                    <?php echo ($reptile && $reptile['id'] == $r['id']) ? 'selected' : ''; ?>
                                                    <?php echo (isset($_POST['reptile_id']) && $_POST['reptile_id'] == $r['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($r['name']); ?> - <?php echo htmlspecialchars($r['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <div id="reptilePreview" class="reptile-preview" style="display: none;">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <img id="reptileImage" class="reptile-image" src="" alt="">
                                            </div>
                                            <div class="col">
                                                <h6 id="reptileName" class="mb-1"></h6>
                                                <p id="reptileCategory" class="text-muted mb-1"></p>
                                                <p id="reptileSpecies" class="text-muted mb-0"></p>
                                            </div>
                                            <div class="col-auto">
                                                <div class="text-success fw-bold" id="reptilePrice"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="start_date" class="form-label">
                                            <i class="fas fa-calendar-alt me-2"></i>Tanggal Mulai *
                                        </label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>" 
                                               min="<?php echo date('Y-m-d'); ?>" required onchange="calculateCost()">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="end_date" class="form-label">
                                            <i class="fas fa-calendar-check me-2"></i>Tanggal Selesai *
                                        </label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>" 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required onchange="calculateCost()">
                                        <div id="dateInfo" class="date-info" style="display: none;">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <span id="daysCount"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-cogs me-2"></i>Fasilitas Tambahan
                                    </label>
                                    <div class="row">
                                        <?php foreach ($facilities as $facility): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="facility-card" onclick="toggleFacility(<?php echo $facility['id']; ?>)">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               id="facility_<?php echo $facility['id']; ?>" 
                                                               name="facilities[]" 
                                                               value="<?php echo $facility['id']; ?>"
                                                               data-price="<?php echo $facility['price_per_day']; ?>"
                                                               onchange="calculateCost()"
                                                               <?php echo (isset($_POST['facilities']) && in_array($facility['id'], $_POST['facilities'])) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label w-100" for="facility_<?php echo $facility['id']; ?>">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($facility['name']); ?></strong>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($facility['description'] ?? ''); ?></small>
                                                                </div>
                                                                <div class="text-success fw-bold">
                                                                    +Rp <?php echo number_format($facility['price_per_day'], 0, ',', '.'); ?>/hari
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="special_instructions" class="form-label">
                                        <i class="fas fa-notes-medical me-2"></i>Instruksi Khusus
                                    </label>
                                    <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3" 
                                              placeholder="Contoh: Beri makan setiap 3 hari, jaga suhu 28-30°C, dll."><?php echo isset($_POST['special_instructions']) ? htmlspecialchars($_POST['special_instructions']) : ''; ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="bookings.php" class="btn btn-outline-secondary me-md-2">
                                        <i class="fas fa-times me-2"></i>Batal
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-calendar-plus me-2"></i>Buat Booking
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="form-card">
                        <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>Ringkasan Biaya</h5>
                        
                        <div id="costSummary" class="cost-summary" style="display: none;">
                            <div class="cost-item">
                                <span>Biaya Dasar:</span>
                                <span id="baseCost">Rp 0</span>
                            </div>
                            <div class="cost-item">
                                <span>Fasilitas Tambahan:</span>
                                <span id="facilityCost">Rp 0</span>
                            </div>
                            <div class="cost-total">
                                <div class="cost-item">
                                    <span>Total Biaya:</span>
                                    <span id="totalCost">Rp 0</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-info-circle me-2"></i>Informasi:</h6>
                            <ul class="mb-0">
                                <li>Booking akan berstatus "Pending" sampai dikonfirmasi admin</li>
                                <li>Pembayaran dapat dilakukan setelah booking dikonfirmasi</li>
                                <li>Biaya dihitung per hari</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateReptilePreview();
            calculateCost();
        });
        
        function updateReptilePreview() {
            const select = document.getElementById('reptile_id');
            const preview = document.getElementById('reptilePreview');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const name = option.dataset.name;
                const category = option.dataset.category;
                const species = option.dataset.species;
                const photo = option.dataset.photo;
                const price = option.dataset.price;
                
                document.getElementById('reptileName').textContent = name;
                document.getElementById('reptileCategory').textContent = category;
                document.getElementById('reptileSpecies').textContent = species || 'Spesies tidak diketahui';
                document.getElementById('reptilePrice').textContent = 'Rp ' + parseInt(price).toLocaleString('id-ID') + '/hari';
                
                const img = document.getElementById('reptileImage');
                if (photo) {
                    img.src = '../' + photo;
                    img.style.display = 'block';
                } else {
                    img.style.display = 'none';
                }
                
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
            
            calculateCost();
        }
        
        function toggleFacility(facilityId) {
            const checkbox = document.getElementById('facility_' + facilityId);
            const card = checkbox.closest('.facility-card');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            
            calculateCost();
        }
        
        function calculateCost() {
            const reptileSelect = document.getElementById('reptile_id');
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const facilityCheckboxes = document.querySelectorAll('input[name="facilities[]"]:checked');
            
            if (!reptileSelect.value || !startDate || !endDate) {
                document.getElementById('costSummary').style.display = 'none';
                document.getElementById('dateInfo').style.display = 'none';
                return;
            }
            
            const start = new Date(startDate);
            const end = new Date(endDate);
            const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            
            if (days <= 0) {
                document.getElementById('costSummary').style.display = 'none';
                document.getElementById('dateInfo').style.display = 'none';
                return;
            }
            
            // Show date info
            document.getElementById('daysCount').textContent = days + ' hari penitipan';
            document.getElementById('dateInfo').style.display = 'block';
            
            // Calculate costs
            const reptilePrice = parseInt(reptileSelect.options[reptileSelect.selectedIndex].dataset.price);
            const baseCost = days * reptilePrice;
            
            let facilityCost = 0;
            facilityCheckboxes.forEach(checkbox => {
                facilityCost += days * parseInt(checkbox.dataset.price);
            });
            
            const totalCost = baseCost + facilityCost;
            
            // Update display
            document.getElementById('baseCost').textContent = 'Rp ' + baseCost.toLocaleString('id-ID');
            document.getElementById('facilityCost').textContent = 'Rp ' + facilityCost.toLocaleString('id-ID');
            document.getElementById('totalCost').textContent = 'Rp ' + totalCost.toLocaleString('id-ID');
            document.getElementById('costSummary').style.display = 'block';
        }
        
        // Update end date minimum when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('end_date');
            
            if (startDate) {
                const minEndDate = new Date(startDate);
                minEndDate.setDate(minEndDate.getDate() + 1);
                endDateInput.min = minEndDate.toISOString().split('T')[0];
                
                // Clear end date if it's before the new minimum
                if (endDateInput.value && endDateInput.value <= startDate) {
                    endDateInput.value = '';
                }
            }
        });
    </script>
</body>
</html>