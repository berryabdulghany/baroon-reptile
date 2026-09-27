<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$reptile_id = isset($_GET['reptile_id']) ? $_GET['reptile_id'] : null;

try {
    $db = getDB();
    
    // Get reptile info if reptile_id is provided
    $reptile = null;
    if ($reptile_id) {
        $stmt = $db->prepare("SELECT r.*, rc.name as category_name, rc.price_per_day FROM reptiles r LEFT JOIN reptile_categories rc ON r.category_id = rc.id WHERE r.id = ? AND r.status = 'active'");
        $stmt->execute([$reptile_id]);
        $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get all active reptiles
    $stmt = $db->prepare("SELECT r.*, rc.name as category_name, rc.price_per_day FROM reptiles r LEFT JOIN reptile_categories rc ON r.category_id = rc.id WHERE r.status = 'active' ORDER BY r.name");
    $stmt->execute();
    $reptiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all customers
    $stmt = $db->prepare("SELECT id, full_name, email, phone FROM users WHERE role = 'customer' ORDER BY full_name");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected_reptile_id = $_POST['reptile_id'];
        $customer_id = $_POST['customer_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $notes = trim($_POST['notes']);
        
        // Validation
        $errors = [];
        
        if (empty($selected_reptile_id)) {
            $errors[] = 'Please select a reptile.';
        }
        
        if (empty($customer_id)) {
            $errors[] = 'Please select a customer.';
        }
        
        if (empty($start_date)) {
            $errors[] = 'Start date is required.';
        }
        
        if (empty($end_date)) {
            $errors[] = 'End date is required.';
        }
        
        if ($start_date && $end_date && $start_date >= $end_date) {
            $errors[] = 'End date must be after start date.';
        }
        
        if ($start_date && $start_date < date('Y-m-d')) {
            $errors[] = 'Start date cannot be in the past.';
        }
        
        // Check for conflicting bookings
        if (empty($errors) && $selected_reptile_id && $start_date && $end_date) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE reptile_id = ? 
                AND status IN ('confirmed', 'in_progress') 
                AND (
                    (start_date <= ? AND end_date > ?) OR
                    (start_date < ? AND end_date >= ?) OR
                    (start_date >= ? AND end_date <= ?)
                )
            ");
            $stmt->execute([$selected_reptile_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'The selected reptile is already booked for the specified dates.';
            }
        }
        
        if (empty($errors)) {
            try {
                // Get reptile price
                $stmt = $db->prepare("SELECT rc.price_per_day FROM reptiles r LEFT JOIN reptile_categories rc ON r.category_id = rc.id WHERE r.id = ?");
                $stmt->execute([$selected_reptile_id]);
                $price_per_day = $stmt->fetchColumn();
                
                // Calculate total days and total cost
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $total_days = $start->diff($end)->days;
                $total_price = $total_days * $price_per_day;
                
                // Create booking
                $stmt = $db->prepare("
                    INSERT INTO bookings (
                        customer_id, reptile_id, start_date, end_date, 
                        total_days, price_per_day, total_price, notes, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())
                ");
                
                if ($stmt->execute([$customer_id, $selected_reptile_id, $start_date, $end_date, $total_days, $price_per_day, $total_price, $notes])) {
                    $booking_id = $db->lastInsertId();
                    $success = 'Booking created successfully! Booking ID: #' . $booking_id;
                    
                    // Clear form data
                    $_POST = [];
                } else {
                    $errors[] = 'Failed to create booking.';
                }
            } catch (Exception $e) {
                $errors[] = 'Database error occurred.';
            }
        }
    }
    
} catch (Exception $e) {
    $error = 'System error occurred.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Booking - Baroon Reptile Admin</title>
    
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
            display: flex;
            align-items: center;
            text-decoration: none;
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
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .form-header {
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            margin: -30px -30px 30px -30px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            color: #2c5530;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .reptile-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .reptile-card:hover {
            border-color: #2c5530;
            background: #f8f9fa;
        }
        
        .reptile-card.selected {
            border-color: #2c5530;
            background: #e8f5e8;
        }
        
        .reptile-photo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .reptile-photo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .cost-calculator {
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
            font-weight: 700;
            font-size: 1.2rem;
            color: #2c5530;
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
        }
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
                        <i class="fas fa-users"></i>Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="reptiles.php">
                        <i class="fas fa-dragon"></i>Reptiles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i>Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-file-alt"></i>Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="facilities.php">
                        <i class="fas fa-cogs"></i>Facilities
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
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
                <?php if ($reptile): ?>
                    <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-arrow-left me-2"></i>Back to Reptile
                    </a>
                <?php else: ?>
                    <a href="reptiles.php" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-arrow-left me-2"></i>Back to Reptiles
                    </a>
                <?php endif; ?>
                <h4 class="mb-0">Create New Booking</h4>
            </div>
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="form-card">
                <div class="form-header">
                    <h3 class="mb-0">
                        <i class="fas fa-plus me-2"></i>Create New Booking
                        <?php if ($reptile): ?>
                            for <?php echo htmlspecialchars($reptile['name']); ?>
                        <?php endif; ?>
                    </h3>
                </div>
                
                <!-- Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <div class="mt-2">
                            <a href="bookings.php" class="btn btn-sm btn-outline-success me-2">
                                <i class="fas fa-list me-1"></i>View All Bookings
                            </a>
                            <button type="button" class="btn btn-sm btn-success" onclick="location.reload()">
                                <i class="fas fa-plus me-1"></i>Create Another
                            </button>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="bookingForm">
                    <?php echo csrf_field(); ?>
                    <!-- Reptile Selection -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-dragon"></i>Select Reptile
                        </h5>
                        
                        <?php if ($reptile): ?>
                            <input type="hidden" name="reptile_id" value="<?php echo $reptile['id']; ?>">
                            <div class="reptile-card selected">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <?php if ($reptile['photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($reptile['name']); ?>" class="reptile-photo">
                                        <?php else: ?>
                                            <div class="reptile-photo-placeholder">
                                                <i class="fas fa-dragon"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($reptile['name']); ?></h6>
                                        <p class="mb-1 text-muted"><?php echo htmlspecialchars($reptile['species'] ?? ''); ?></p>
                                        <small class="text-muted"><?php echo htmlspecialchars($reptile['category_name']); ?></small>
                                    </div>
                                    <div class="col-auto">
                                        <div class="text-end">
                                            <div class="fw-bold text-success">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?></div>
                                            <small class="text-muted">per day</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($reptiles as $r): ?>
                                    <div class="col-md-6">
                                        <div class="reptile-card" onclick="selectReptile(<?php echo $r['id']; ?>, <?php echo $r['price_per_day']; ?>)">
                                            <input type="radio" name="reptile_id" value="<?php echo $r['id']; ?>" class="d-none reptile-radio" id="reptile_<?php echo $r['id']; ?>">
                                            <div class="row align-items-center">
                                                <div class="col-auto">
                                                    <?php if ($r['photo']): ?>
                                                        <img src="../<?php echo htmlspecialchars($r['photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($r['name']); ?>" class="reptile-photo">
                                                    <?php else: ?>
                                                        <div class="reptile-photo-placeholder">
                                                            <i class="fas fa-dragon"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($r['name']); ?></h6>
                                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($r['species'] ?? ''); ?></p>
                                                    <small class="text-muted"><?php echo htmlspecialchars($r['category_name']); ?></small>
                                                </div>
                                                <div class="col-auto">
                                                    <div class="text-end">
                                                        <div class="fw-bold text-success">Rp <?php echo number_format($r['price_per_day'], 0, ',', '.'); ?></div>
                                                        <small class="text-muted">per day</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Customer Selection -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-user"></i>Select Customer
                        </h5>
                        
                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Customer <span class="required">*</span></label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['full_name']); ?> - <?php echo htmlspecialchars($customer['email']); ?>
                                        <?php if ($customer['phone']): ?>
                                            (<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Booking Details -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-calendar-alt"></i>Booking Details
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date <span class="required">*</span></label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>" 
                                           min="<?php echo date('Y-m-d'); ?>" required onchange="calculateCost()">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date <span class="required">*</span></label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required onchange="calculateCost()">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Any special instructions or notes for this booking..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Cost Calculator -->
                        <div class="cost-calculator" id="costCalculator" style="display: none;">
                            <h6 class="mb-3"><i class="fas fa-calculator me-2"></i>Cost Calculation</h6>
                            <div class="cost-item">
                                <span>Duration:</span>
                                <span id="duration">0 days</span>
                            </div>
                            <div class="cost-item">
                                <span>Price per day:</span>
                                <span id="pricePerDay">Rp 0</span>
                            </div>
                            <div class="cost-item cost-total">
                                <span>Total Cost:</span>
                                <span id="totalCost">Rp 0</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Create Booking
                        </button>
                        <a href="bookings.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <?php if ($reptile): ?>
                            <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>View Reptile
                            </a>
                        <?php else: ?>
                            <a href="reptiles.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i>Back to Reptiles
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let selectedPrice = <?php echo $reptile ? $reptile['price_per_day'] : 0; ?>;
        
        function selectReptile(reptileId, pricePerDay) {
            // Remove selected class from all cards
            document.querySelectorAll('.reptile-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById('reptile_' + reptileId).checked = true;
            
            // Update price
            selectedPrice = pricePerDay;
            
            // Recalculate cost
            calculateCost();
        }
        
        function calculateCost() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate && selectedPrice > 0) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const timeDiff = end.getTime() - start.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
                
                if (daysDiff > 0) {
                    const totalCost = daysDiff * selectedPrice;
                    
                    document.getElementById('duration').textContent = daysDiff + ' days';
                    document.getElementById('pricePerDay').textContent = 'Rp ' + selectedPrice.toLocaleString('id-ID');
                    document.getElementById('totalCost').textContent = 'Rp ' + totalCost.toLocaleString('id-ID');
                    document.getElementById('costCalculator').style.display = 'block';
                } else {
                    document.getElementById('costCalculator').style.display = 'none';
                }
            } else {
                document.getElementById('costCalculator').style.display = 'none';
            }
        }
        
        // Update end date minimum when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            if (startDate) {
                const nextDay = new Date(startDate);
                nextDay.setDate(nextDay.getDate() + 1);
                document.getElementById('end_date').min = nextDay.toISOString().split('T')[0];
            }
        });
        
        // Initial calculation if reptile is pre-selected
        <?php if ($reptile): ?>
            calculateCost();
        <?php endif; ?>
    </script>
</body>
</html>