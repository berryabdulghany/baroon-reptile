<?php
require_once '../config/database.php';
require_once '../includes/upload.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$reptile_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$reptile_id) {
    header('Location: reptiles.php');
    exit;
}

try {
    $db = getDB();
    
    // Get reptile data
    $stmt = $db->prepare("SELECT * FROM reptiles WHERE id = ?");
    $stmt->execute([$reptile_id]);
    $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reptile) {
        header('Location: reptiles.php');
        exit;
    }
    
    // Get categories
    $stmt = $db->prepare("SELECT * FROM reptile_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get customers
    $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE role = 'customer' ORDER BY full_name");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $species = trim($_POST['species']);
        $category_id = $_POST['category_id'];
        $customer_id = $_POST['customer_id'];
        $age = $_POST['age'];
        $gender = $_POST['gender'];
        $weight = $_POST['weight'];
        $length = $_POST['length'];
        $special_needs = trim($_POST['special_needs']);
        $status = $_POST['status'];
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Name is required.';
        }
        
        if (empty($species)) {
            $errors[] = 'Species is required.';
        }
        
        if (empty($category_id)) {
            $errors[] = 'Category is required.';
        }
        
        if (empty($customer_id)) {
            $errors[] = 'Customer is required.';
        }
        
        if (trim($age) === '') {
            $errors[] = 'Age is required.';
        }
        
        if (empty($weight) || $weight < 0) {
            $errors[] = 'Valid weight is required.';
        }
        
        if (empty($length) || $length < 0) {
            $errors[] = 'Valid length is required.';
        }
        // Handle photo upload
        $photo_path = $reptile['photo']; // Keep existing photo by default
        
        if (empty($errors)) {
            [$new_photo, $upload_error] = upload_reptile_photo('photo');
            if ($upload_error) {
                $errors[] = $upload_error;
            } elseif ($new_photo) {
                delete_reptile_photo($reptile['photo']);
                $photo_path = $new_photo;
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE reptiles SET 
                        name = ?, species = ?, category_id = ?, customer_id = ?, 
                        age = ?, gender = ?, weight = ?, length = ?,
                        special_needs = ?, status = ?, photo = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                if ($stmt->execute([
                    $name, $species, $category_id, $customer_id,
                    $age, $gender, $weight, $length,
                    $special_needs, $status, $photo_path,
                    $reptile_id
                ])) {
                    $success = 'Reptile updated successfully!';
                    
                    // Refresh reptile data
                    $stmt = $db->prepare("SELECT * FROM reptiles WHERE id = ?");
                    $stmt->execute([$reptile_id]);
                    $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $errors[] = 'Failed to update reptile.';
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
    <title>Edit Reptile - <?php echo htmlspecialchars($reptile['name']); ?> - Baroon Reptile Admin</title>
    
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
        
        .current-photo {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .photo-placeholder {
            width: 200px;
            height: 200px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 3rem;
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
                    <a class="nav-link active" href="reptiles.php">
                        <i class="fas fa-dragon"></i>Reptil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check"></i>Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-money-bill-wave"></i>Pembayaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i>Laporan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="facilities.php">
                        <i class="fas fa-building"></i>Fasilitas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
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
                <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Reptile
                </a>
                <h4 class="mb-0">Edit Reptile</h4>
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
                        <i class="fas fa-edit me-2"></i>Edit Reptile: <?php echo htmlspecialchars($reptile['name']); ?>
                    </h3>
                </div>
                
                <!-- Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
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
                
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-info-circle"></i>Basic Information
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($reptile['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="species" class="form-label">Species <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="species" name="species" value="<?php echo htmlspecialchars($reptile['species'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category <span class="required">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $reptile['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?> - Rp <?php echo number_format($category['price_per_day'], 0, ',', '.'); ?>/day
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customer_id" class="form-label">Owner <span class="required">*</span></label>
                                    <select class="form-select" id="customer_id" name="customer_id" required>
                                        <option value="">Select Owner</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>" <?php echo $reptile['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($customer['full_name']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="required">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo $reptile['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $reptile['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Physical Characteristics -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-ruler"></i>Physical Characteristics
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="age" class="form-label">Age <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($reptile['age'] ?? ''); ?>" placeholder="e.g. 2 tahun" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="gender" class="form-label">Gender <span class="required">*</span></label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="male" <?php echo $reptile['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo $reptile['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="unknown" <?php echo $reptile['gender'] === 'unknown' ? 'selected' : ''; ?>>Unknown</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="weight" class="form-label">Weight (kg) <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="weight" name="weight" value="<?php echo $reptile['weight']; ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="length" class="form-label">Length (cm) <span class="required">*</span></label>
                                    <input type="number" class="form-control" id="length" name="length" value="<?php echo $reptile['length']; ?>" min="0" step="0.1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Photo -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-camera"></i>Photo
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="photo" class="form-label">Upload New Photo</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <div class="form-text">Leave empty to keep current photo. Max size: 5MB. Formats: JPEG, PNG, GIF</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Current Photo</label>
                                <div>
                                    <?php if ($reptile['photo']): ?>
                                        <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="Current photo" class="current-photo">
                                    <?php else: ?>
                                        <div class="photo-placeholder">
                                            <i class="fas fa-dragon"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="fas fa-file-alt"></i>Additional Information
                        </h5>
                        
                        <div class="mb-3">
                            <label for="special_needs" class="form-label">Special Needs</label>
                            <textarea class="form-control" id="special_needs" name="special_needs" rows="3" placeholder="Any special care requirements..."><?php echo htmlspecialchars($reptile['special_needs'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Reptile
                        </button>
                        <a href="view_reptile.php?id=<?php echo $reptile['id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <a href="reptiles.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>Back to List
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>