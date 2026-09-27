<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    header('Location: bookings.php');
    exit;
}

try {
    $db = getDB();
    
    // Handle create care report
    if (isset($_POST['action']) && $_POST['action'] === 'create_care_report') {
        $reptile_id = $_POST['reptile_id'];
        $report_date = $_POST['report_date'];
        $health_status = $_POST['health_status'];
        $food_given = $_POST['food_given'];
        $notes = $_POST['notes'] ?? '';
        $staff_id = $_SESSION['user_id'];
        
        // Check if report already exists for this date and reptile
        $stmt = $db->prepare("SELECT id FROM daily_reports WHERE reptile_id = ? AND booking_id = ? AND report_date = ?");
        $stmt->execute([$reptile_id, $booking_id, $report_date]);
        if ($stmt->fetchColumn()) {
            $message = 'error:Laporan untuk tanggal ini sudah ada!';
        } else {
            $stmt = $db->prepare("
                INSERT INTO daily_reports (reptile_id, booking_id, report_date, health_status, feeding_notes, notes, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            if ($stmt->execute([$reptile_id, $booking_id, $report_date, $health_status, $food_given, $notes, $staff_id])) {
                $message = 'success:Laporan perawatan berhasil dibuat!';
            } else {
                $message = 'error:Gagal membuat laporan perawatan!';
            }
        }
    }
    
    // Handle update care report
    if (isset($_POST['action']) && $_POST['action'] === 'update_care_report') {
        $report_id = $_POST['report_id'];
        $health_status = $_POST['health_status'];
        $food_given = $_POST['food_given'];
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $db->prepare("
            UPDATE daily_reports 
            SET health_status = ?, feeding_notes = ?, notes = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$health_status, $food_given, $notes, $report_id])) {
            $message = 'success:Laporan perawatan berhasil diupdate!';
        } else {
            $message = 'error:Gagal mengupdate laporan perawatan!';
        }
    }
    
    // Handle delete care report
    if (isset($_POST['action']) && $_POST['action'] === 'delete_care_report') {
        $report_id = $_POST['report_id'];
        
        $stmt = $db->prepare("DELETE FROM daily_reports WHERE id = ?");
        if ($stmt->execute([$report_id])) {
            $message = 'success:Laporan perawatan berhasil dihapus!';
        } else {
            $message = 'error:Gagal menghapus laporan perawatan!';
        }
    }
    
    // Get booking details
    $stmt = $db->prepare("
        SELECT 
            b.*,
            u.full_name as customer_name,
            u.email as customer_email,
            u.phone as customer_phone,
            r.name as reptile_name,
            r.species,
            r.age,
            r.gender,
            r.photo as reptile_photo,
            rc.name as category_name
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        JOIN reptiles r ON b.reptile_id = r.id
        JOIN reptile_categories rc ON r.category_id = rc.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('Location: bookings.php');
        exit;
    }
    
    // Get care reports for this booking
    $stmt = $db->prepare("
        SELECT 
            dr.*,
            u.full_name as staff_name
        FROM daily_reports dr
        LEFT JOIN users u ON dr.created_by = u.id
        WHERE dr.booking_id = ?
        ORDER BY dr.report_date DESC
    ");
    $stmt->execute([$booking_id]);
    $care_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = 'error:Terjadi kesalahan sistem.';
    error_log("Error in daily_report.php: " . $e->getMessage());
    
    // Initialize variables to prevent undefined variable errors
    $booking = [
        'id' => $booking_id,
        'reptile_name' => 'Unknown',
        'reptile_photo' => '',
        'category_name' => 'Unknown',
        'species' => 'Unknown',
        'customer_name' => 'Unknown',
        'start_date' => '1970-01-01',
        'end_date' => '1970-01-01',
        'status' => 'unknown',
        'reptile_id' => 0
    ];
    $care_reports = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harian Perawatan - Baroon Reptile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .care-report-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .care-report-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
        }
        .report-body {
            padding: 20px;
        }
        .health-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .health-excellent { background-color: #d4edda; color: #155724; }
        .health-good { background-color: #d1ecf1; color: #0c5460; }
        .health-fair { background-color: #fff3cd; color: #856404; }
        .health-poor { background-color: #f8d7da; color: #721c24; }
        .reptile-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .reptile-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
        }
        .btn-create-report {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-create-report:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-dragon me-2"></i>Baroon Reptile Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="bookings.php">
                    <i class="fas fa-arrow-left me-1"></i>Kembali ke Bookings
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Alert Messages -->
        <?php if ($message): ?>
            <?php 
            $parts = explode(':', $message, 2);
            $type = $parts[0];
            $text = $parts[1];
            $alert_class = $type === 'success' ? 'alert-success' : 'alert-danger';
            ?>
            <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo htmlspecialchars($text); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-file-medical me-2"></i>Laporan Harian Perawatan</h2>
                <p class="text-muted mb-0">Booking ID: #<?php echo $booking['id']; ?> - <?php echo htmlspecialchars($booking['reptile_name']); ?></p>
            </div>
            <button class="btn btn-create-report" data-bs-toggle="modal" data-bs-target="#createReportModal">
                <i class="fas fa-plus me-2"></i>Buat Laporan Baru
            </button>
        </div>

        <!-- Booking & Reptile Info -->
        <div class="reptile-info">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php if ($booking['reptile_photo']): ?>
                        <img src="../<?php echo htmlspecialchars($booking['reptile_photo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($booking['reptile_name']); ?>" class="reptile-image">
                    <?php else: ?>
                        <div class="reptile-image d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-dragon fa-2x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-1"><?php echo htmlspecialchars($booking['reptile_name']); ?></h5>
                            <p class="text-muted mb-1"><?php echo htmlspecialchars($booking['category_name']); ?> - <?php echo htmlspecialchars($booking['species'] ?? ''); ?></p>
                            <p class="mb-0"><strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Periode:</strong> <?php echo date('d M Y', strtotime($booking['start_date'])); ?> - <?php echo date('d M Y', strtotime($booking['end_date'])); ?></p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-info"><?php echo ucfirst($booking['status']); ?></span></p>
                            <p class="mb-0"><strong>Total Laporan:</strong> <?php echo count($care_reports); ?> laporan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Care Reports -->
        <?php if (empty($care_reports)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-medical fa-4x text-muted mb-3"></i>
                <h5>Belum Ada Laporan Perawatan</h5>
                <p class="text-muted">Mulai buat laporan harian untuk reptile ini.</p>
            </div>
        <?php else: ?>
            <?php foreach ($care_reports as $report): ?>
                <div class="care-report-card">
                    <div class="report-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    <i class="fas fa-calendar-day me-2"></i>
                                    <?php echo date('d F Y', strtotime($report['report_date'])); ?>
                                </h6>
                                <small class="opacity-75">Dibuat oleh: <?php echo htmlspecialchars($report['staff_name'] ?? 'Unknown'); ?></small>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="editReport(<?php echo $report['id']; ?>, '<?php echo $report['health_status']; ?>', '<?php echo htmlspecialchars($report['feeding_notes'] ?? ''); ?>', '<?php echo htmlspecialchars($report['notes'] ?? ''); ?>')">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </a></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteReport(<?php echo $report['id']; ?>)">
                                        <i class="fas fa-trash me-2"></i>Hapus
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="report-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Status Kesehatan:</strong><br>
                                <span class="health-status health-<?php echo strtolower($report['health_status']); ?>">
                                    <?php echo ucfirst($report['health_status']); ?>
                                </span>
                            </div>
                            <div class="col-md-4">
                                <strong>Makanan Diberikan:</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($report['feeding_notes'] ?? ''); ?></span>
                            </div>
                            <div class="col-md-5">
                                <strong>Catatan:</strong><br>
                                <span class="text-muted"><?php echo $report['notes'] ? htmlspecialchars($report['notes'] ?? '') : 'Tidak ada catatan'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Create Report Modal -->
    <div class="modal fade" id="createReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Buat Laporan Perawatan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_care_report">
                        <input type="hidden" name="reptile_id" value="<?php echo $booking['reptile_id']; ?>">
                        
                        <div class="mb-3">
                            <label for="report_date" class="form-label">Tanggal Laporan</label>
                            <input type="date" class="form-control" id="report_date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="health_status" class="form-label">Status Kesehatan</label>
                            <select class="form-select" id="health_status" name="health_status" required>
                                <option value="">Pilih Status Kesehatan</option>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="food_given" class="form-label">Makanan Diberikan</label>
                            <input type="text" class="form-control" id="food_given" name="food_given" placeholder="Contoh: Jangkrik 5 ekor, sayuran" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Catatan</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Catatan tambahan tentang kondisi reptile..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Report Modal -->
    <div class="modal fade" id="editReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Laporan Perawatan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_care_report">
                        <input type="hidden" name="report_id" id="edit_report_id">
                        
                        <div class="mb-3">
                            <label for="edit_health_status" class="form-label">Status Kesehatan</label>
                            <select class="form-select" id="edit_health_status" name="health_status" required>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_food_given" class="form-label">Makanan Diberikan</label>
                            <input type="text" class="form-control" id="edit_food_given" name="food_given" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Catatan</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus laporan perawatan ini?</p>
                    <p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete_care_report">
                        <input type="hidden" name="report_id" id="delete_report_id">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editReport(id, health_status, food_given, notes) {
            document.getElementById('edit_report_id').value = id;
            document.getElementById('edit_health_status').value = health_status;
            document.getElementById('edit_food_given').value = food_given;
            document.getElementById('edit_notes').value = notes;
            
            var editModal = new bootstrap.Modal(document.getElementById('editReportModal'));
            editModal.show();
        }
        
        function deleteReport(id) {
            document.getElementById('delete_report_id').value = id;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteReportModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>