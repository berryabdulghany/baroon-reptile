<?php
require_once '../config/database.php';

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';

try {
    $db = getDB();
    
    // Get categories
    $stmt = $db->query("SELECT * FROM reptile_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($_POST) {
        $name = trim($_POST['name']);
        $category_id = $_POST['category_id'];
        $species = trim($_POST['species']);
        $age = trim($_POST['age']);
        $weight = $_POST['weight'];
        $length = $_POST['length'];
        $gender = $_POST['gender'];
        $special_needs = trim($_POST['special_needs']);
        
        // Validasi input
        if (empty($name) || empty($category_id)) {
            $error = 'Nama reptile dan kategori harus diisi!';
        } else {
            // Handle file upload
            $photo_path = '';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = $_FILES['photo']['type'];
                $file_size = $_FILES['photo']['size'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $error = 'Format file harus JPG, PNG, atau GIF!';
                } elseif ($file_size > MAX_FILE_SIZE) {
                    $error = 'Ukuran file maksimal 5MB!';
                } else {
                    $upload_dir = '../' . UPLOAD_PATH . 'reptiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'reptile_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                        $photo_path = UPLOAD_PATH . 'reptiles/' . $new_filename;
                    } else {
                        $error = 'Gagal mengupload foto!';
                    }
                }
            }
            
            if (empty($error)) {
                // Insert reptile
                $stmt = $db->prepare("
                    INSERT INTO reptiles (customer_id, category_id, name, species, age, weight, length, gender, special_needs, photo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([
                    $_SESSION['user_id'], $category_id, $name, $species, $age, 
                    $weight, $length, $gender, $special_needs, $photo_path
                ])) {
                    $success = 'Reptile berhasil ditambahkan!';
                    // Reset form
                    $_POST = array();
                } else {
                    $error = 'Terjadi kesalahan saat menyimpan data.';
                }
            }
        }
    }
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Reptile - Baroon Reptile</title>
    
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
        
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 10px;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            cursor: pointer;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .file-input-wrapper:hover {
            border-color: #4a7c59;
            background: #f0f8f0;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .category-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
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
                    <a class="nav-link active" href="my_reptiles.php">
                        <i class="fas fa-dragon"></i>My Reptiles
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
                <h4 class="mb-0">Tambah Reptile Baru</h4>
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
                            <a href="dashboard.php" class="btn btn-outline-secondary me-3">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h5 class="mb-0">Informasi Reptile</h5>
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
                                    <a href="dashboard.php" class="btn btn-sm btn-outline-success me-2">
                                        <i class="fas fa-home me-1"></i>Kembali ke Dashboard
                                    </a>
                                    <a href="add_reptile.php" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus me-1"></i>Tambah Lagi
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-tag me-2"></i>Nama Reptile *
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           placeholder="Contoh: Sanca Bodo" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">
                                        <i class="fas fa-list me-2"></i>Kategori *
                                    </label>
                                    <select class="form-control" id="category_id" name="category_id" required onchange="showCategoryInfo()">
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    data-price="<?php echo $category['price_per_day']; ?>"
                                                    data-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>"
                                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="categoryInfo" class="category-info" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span id="categoryDescription"></span>
                                            <strong id="categoryPrice"></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="species" class="form-label">
                                        <i class="fas fa-dna me-2"></i>Spesies
                                    </label>
                                    <input type="text" class="form-control" id="species" name="species" 
                                           value="<?php echo isset($_POST['species']) ? htmlspecialchars($_POST['species']) : ''; ?>" 
                                           placeholder="Contoh: Python reticulatus">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="age" class="form-label">
                                        <i class="fas fa-calendar me-2"></i>Umur
                                    </label>
                                    <input type="text" class="form-control" id="age" name="age" 
                                           value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" 
                                           placeholder="Contoh: 2 tahun">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="weight" class="form-label">
                                        <i class="fas fa-weight me-2"></i>Berat (kg)
                                    </label>
                                    <input type="number" step="0.01" class="form-control" id="weight" name="weight" 
                                           value="<?php echo isset($_POST['weight']) ? $_POST['weight'] : ''; ?>" 
                                           placeholder="0.00">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="length" class="form-label">
                                        <i class="fas fa-ruler me-2"></i>Panjang (cm)
                                    </label>
                                    <input type="number" step="0.01" class="form-control" id="length" name="length" 
                                           value="<?php echo isset($_POST['length']) ? $_POST['length'] : ''; ?>" 
                                           placeholder="0.00">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="gender" class="form-label">
                                        <i class="fas fa-venus-mars me-2"></i>Jenis Kelamin
                                    </label>
                                    <select class="form-control" id="gender" name="gender">
                                        <option value="unknown" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'unknown') ? 'selected' : ''; ?>>Tidak Diketahui</option>
                                        <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Jantan</option>
                                        <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Betina</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="special_needs" class="form-label">
                                    <i class="fas fa-notes-medical me-2"></i>Kebutuhan Khusus
                                </label>
                                <textarea class="form-control" id="special_needs" name="special_needs" rows="3" 
                                          placeholder="Contoh: Alergi makanan tertentu, suhu khusus, dll."><?php echo isset($_POST['special_needs']) ? htmlspecialchars($_POST['special_needs']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-camera me-2"></i>Foto Reptile
                                </label>
                                <div class="file-input-wrapper" onclick="document.getElementById('photo').click()">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">Klik untuk upload foto</p>
                                    <small class="text-muted">Format: JPG, PNG, GIF (Max: 5MB)</small>
                                    <input type="file" id="photo" name="photo" accept="image/*" onchange="previewImage(this)">
                                </div>
                                <img id="imagePreview" class="photo-preview" style="display: none;">
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Simpan Reptile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="form-card">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Informasi Penting</h5>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Tips:</h6>
                            <ul class="mb-0">
                                <li>Pastikan informasi reptile akurat</li>
                                <li>Upload foto yang jelas</li>
                                <li>Cantumkan kebutuhan khusus jika ada</li>
                                <li>Pilih kategori yang sesuai untuk harga yang tepat</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Perhatian:</h6>
                            <p class="mb-0">Setelah reptile ditambahkan, Anda dapat langsung membuat booking untuk penitipan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function showCategoryInfo() {
            const select = document.getElementById('category_id');
            const info = document.getElementById('categoryInfo');
            const description = document.getElementById('categoryDescription');
            const price = document.getElementById('categoryPrice');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                description.textContent = option.dataset.description;
                price.textContent = 'Rp ' + parseInt(option.dataset.price).toLocaleString('id-ID') + '/hari';
                info.style.display = 'block';
            } else {
                info.style.display = 'none';
            }
        }
    </script>
</body>
</html>