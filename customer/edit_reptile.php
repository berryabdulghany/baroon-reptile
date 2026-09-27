<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$reptile = null;

try {
    $db = getDB();
    
    // Get reptile ID from URL
    $reptile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$reptile_id) {
        header('Location: my_reptiles.php');
        exit;
    }
    
    // Get reptile data and verify ownership
    $stmt = $db->prepare("
        SELECT r.*, rc.name as category_name, rc.description as category_description, rc.price_per_day
        FROM reptiles r
        JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE r.id = ? AND r.customer_id = ?
    ");
    $stmt->execute([$reptile_id, $_SESSION['user_id']]);
    $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reptile) {
        header('Location: my_reptiles.php');
        exit;
    }
    
    // Check if reptile has active bookings
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM bookings 
        WHERE reptile_id = ? AND status IN ('pending', 'confirmed', 'in_progress')
    ");
    $stmt->execute([$reptile_id]);
    $has_active_bookings = $stmt->fetchColumn() > 0;
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $species = trim($_POST['species']);
        $age = trim($_POST['age']); // Changed from (int) to string as per database schema
        $weight = (float)$_POST['weight'];
        $length = (float)$_POST['length'];
        $gender = $_POST['gender'];
        $special_needs = trim($_POST['special_needs']);
        $status = $_POST['status'];
        
        // Validate input
        if (empty($name)) {
            $message = 'error:Nama reptile tidak boleh kosong!';
        } elseif (empty($species)) {
            $message = 'error:Spesies tidak boleh kosong!';
        } elseif (empty($age)) {
            $message = 'error:Umur tidak boleh kosong!';
        } elseif ($weight <= 0) {
            $message = 'error:Berat harus lebih dari 0!';
        } elseif ($length <= 0) {
            $message = 'error:Panjang harus lebih dari 0!';
        } else {
            // Handle photo upload if provided
            $photo_path = $reptile['photo'];
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/reptiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_info = pathinfo($_FILES['photo']['name']);
                $extension = strtolower($file_info['extension']);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($extension, $allowed_extensions)) {
                    $message = 'error:Format file tidak didukung! Gunakan JPG, PNG, atau GIF.';
                } elseif ($_FILES['photo']['size'] > MAX_FILE_SIZE) {
                    $message = 'error:Ukuran file terlalu besar! Maksimal 5MB.';
                } else {
                    $new_filename = 'reptile_' . $reptile_id . '_' . time() . '.' . $extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                        // Delete old photo if exists
                        if ($reptile['photo'] && file_exists('../' . $reptile['photo'])) {
                            unlink('../' . $reptile['photo']);
                        }
                        $photo_path = 'uploads/reptiles/' . $new_filename;
                    } else {
                        $message = 'error:Gagal mengupload foto!';
                    }
                }
            }
            
            if (!$message) {
                // Update reptile data
                $stmt = $db->prepare("
                    UPDATE reptiles 
                    SET name = ?, species = ?, age = ?, weight = ?, length = ?, gender = ?, 
                        special_needs = ?, status = ?, photo = ?, updated_at = NOW()
                    WHERE id = ? AND customer_id = ?
                ");
                
                if ($stmt->execute([$name, $species, $age, $weight, $length, $gender, $special_needs, $status, $photo_path, $reptile_id, $_SESSION['user_id']])) {
                    $message = 'success:Data reptile berhasil diupdate!';
                    
                    // Refresh reptile data
                    $stmt = $db->prepare("
                        SELECT r.*, rc.name as category_name, rc.description as category_description, rc.price_per_day
                        FROM reptiles r
                        JOIN reptile_categories rc ON r.category_id = rc.id
                        WHERE r.id = ? AND r.customer_id = ?
                    ");
                    $stmt->execute([$reptile_id, $_SESSION['user_id']]);
                    $reptile = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = 'error:Gagal mengupdate data reptile!';
                }
            }
        }
    }
    
    // Get all categories for reference
    $stmt = $db->query("SELECT * FROM reptile_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
    // Log error for debugging
    error_log('Edit Reptile Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reptile - Baroon Reptile</title>
    
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
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            color: white;
            padding: 20px;
        }
        
        .form-body {
            padding: 30px;
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
        
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .category-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .reptile-stats {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c5530;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
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
                    <a class="nav-link active" href="my_reptiles.php">
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
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center">
                <h4 class="mb-0">Edit Reptile</h4>
                <nav aria-label="breadcrumb" class="ms-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="my_reptiles.php">My Reptiles</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($reptile['name']); ?></li>
                    </ol>
                </nav>
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
            
            <?php if ($has_active_bookings): ?>
                <div class="warning-box">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle text-warning me-3 fa-2x"></i>
                        <div>
                            <h6 class="mb-1">Reptile Sedang Dalam Booking Aktif</h6>
                            <p class="mb-0">Beberapa informasi tidak dapat diubah karena reptile sedang dalam booking aktif. Anda hanya dapat mengubah informasi dasar seperti nama dan catatan khusus.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Reptile Stats -->
            <div class="reptile-stats">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo htmlspecialchars($reptile['category_name']); ?></div>
                            <div class="stat-label">Category</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-value">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?></div>
                            <div class="stat-label">Price per Day</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo date('d M Y', strtotime($reptile['created_at'])); ?></div>
                            <div class="stat-label">Registered</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-item">
                            <div class="stat-value">
                                <span class="badge bg-<?php echo $reptile['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($reptile['status']); ?>
                                </span>
                            </div>
                            <div class="stat-label">Status</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form -->
            <div class="form-card">
                <div class="form-header">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Reptile Information</h5>
                </div>
                <div class="form-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Reptile Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($reptile['name']); ?>" required maxlength="100">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="species" class="form-label">Species *</label>
                                        <input type="text" class="form-control" id="species" name="species" value="<?php echo htmlspecialchars($reptile['species'] ?? ''); ?>" required maxlength="100" <?php echo $has_active_bookings ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="age" class="form-label">Age *</label>
                                        <input type="text" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($reptile['age'] ?? ''); ?>" placeholder="Contoh: 2 tahun" required <?php echo $has_active_bookings ? 'readonly' : ''; ?>>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="weight" class="form-label">Weight (kg) *</label>
                                        <input type="number" class="form-control" id="weight" name="weight" value="<?php echo $reptile['weight']; ?>" required min="0.01" step="0.01" <?php echo $has_active_bookings ? 'readonly' : ''; ?>>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="length" class="form-label">Length (cm) *</label>
                                        <input type="number" class="form-control" id="length" name="length" value="<?php echo $reptile['length']; ?>" required min="0.1" step="0.1" <?php echo $has_active_bookings ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="gender" class="form-label">Gender *</label>
                                        <select class="form-select" id="gender" name="gender" required <?php echo $has_active_bookings ? 'disabled' : ''; ?>>
                                            <option value="male" <?php echo $reptile['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo $reptile['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="unknown" <?php echo $reptile['gender'] === 'unknown' ? 'selected' : ''; ?>>Unknown</option>
                                        </select>
                                        <?php if ($has_active_bookings): ?>
                                            <input type="hidden" name="gender" value="<?php echo $reptile['gender']; ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo $reptile['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $reptile['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="special_needs" class="form-label">Special Needs / Notes</label>
                                    <textarea class="form-control" id="special_needs" name="special_needs" rows="4" placeholder="Any special care requirements, medical conditions, dietary restrictions, etc..."><?php echo htmlspecialchars($reptile['special_needs'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="photo" class="form-label">Reptile Photo</label>
                                    <?php if ($reptile['photo']): ?>
                                        <div class="mb-3">
                                            <img src="../<?php echo htmlspecialchars($reptile['photo'] ?? ''); ?>" alt="Current Photo" class="photo-preview img-fluid">
                                            <div class="form-text">Current photo</div>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <div class="form-text">Upload new photo to replace current one. Max 5MB. Formats: JPG, PNG, GIF</div>
                                </div>
                                
                                <!-- Category Information -->
                                <div class="category-info">
                                    <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Category: <?php echo htmlspecialchars($reptile['category_name']); ?></h6>
                                    <p class="small mb-2"><?php echo htmlspecialchars($reptile['category_description'] ?? ''); ?></p>
                                    <div class="fw-bold text-success">Rp <?php echo number_format($reptile['price_per_day'], 0, ',', '.'); ?> / day</div>
                                </div>
                                
                                <?php if ($has_active_bookings): ?>
                                    <div class="alert alert-warning mt-3">
                                        <small><i class="fas fa-info-circle me-1"></i>Some fields are locked due to active bookings</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Last updated: <?php echo (isset($reptile['updated_at']) && $reptile['updated_at']) ? date('d M Y H:i', strtotime($reptile['updated_at'])) : 'Never'; ?>
                                </small>
                            </div>
                            <div>
                                <a href="my_reptiles.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to My Reptiles
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Reptile
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Photo preview
        document.getElementById('photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create or update preview
                    let preview = document.querySelector('.photo-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.className = 'photo-preview img-fluid mb-2';
                        e.target.parentNode.insertBefore(preview, e.target);
                    }
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const weight = parseFloat(document.getElementById('weight').value);
            const length = parseFloat(document.getElementById('length').value);
            const age = parseInt(document.getElementById('age').value);
            
            if (weight <= 0) {
                alert('Berat harus lebih dari 0!');
                e.preventDefault();
                return;
            }
            
            if (length <= 0) {
                alert('Panjang harus lebih dari 0!');
                e.preventDefault();
                return;
            }
            
            if (age < 0 || age > 100) {
                alert('Umur harus antara 0-100 tahun!');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>