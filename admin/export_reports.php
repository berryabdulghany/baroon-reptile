<?php
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Simple PDF generation without external libraries
class SimplePDF {
    private $content = '';
    private $title = '';
    
    public function __construct($title = 'Report') {
        $this->title = $title;
    }
    
    public function addContent($content) {
        $this->content .= $content;
    }
    
    public function output($filename = 'report.pdf') {
        // For now, we'll create an HTML version that can be printed as PDF
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        
        echo $this->generateHTML();
    }
    
    private function generateHTML() {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($this->title) . '</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        .report-date {
            font-size: 14px;
            color: #95a5a6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .summary-box {
            background-color: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .summary-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-value {
            font-size: 20px;
            font-weight: bold;
            color: #27ae60;
        }
        .summary-label {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #95a5a6;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .print-button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 20px 0;
        }
        .print-button:hover {
            background-color: #2980b9;
        }
        .currency {
            color: #27ae60;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Cetak / Simpan sebagai PDF</button>
    <button class="print-button no-print" onclick="window.close()" style="background-color: #95a5a6;">❌ Tutup</button>
    
    <div class="header">
        <div class="company-name">BAROON REPTILE</div>
        <div class="report-title">' . htmlspecialchars($this->title) . '</div>
        <div class="report-date">Dibuat pada: ' . date('d F Y, H:i:s') . '</div>
    </div>
    
    ' . $this->content . '
    
    <div class="footer">
        <p>© ' . date('Y') . ' Baroon Reptile - Laporan Bisnis</p>
        <p>Laporan ini dibuat secara otomatis oleh sistem</p>
    </div>
    
    <script>
        // Auto print dialog when page loads (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>';
    }
}

try {
    $db = getDB();
    
    // Get export parameters
    $export_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $date_filter = isset($_GET['date']) ? $_GET['date'] : 'this_month';
    $format = isset($_GET['format']) ? $_GET['format'] : 'pdf';
    
    // Build query based on filters
    $where_clause = "WHERE 1=1";
    $params = [];
    
    if ($date_filter === 'today') {
        $where_clause .= " AND report_date = CURDATE()";
        $period_title = "Laporan Hari Ini";
    } elseif ($date_filter === 'this_week') {
        $where_clause .= " AND WEEK(report_date) = WEEK(CURDATE()) AND YEAR(report_date) = YEAR(CURDATE())";
        $period_title = "Laporan Minggu Ini";
    } elseif ($date_filter === 'this_month') {
        $where_clause .= " AND MONTH(report_date) = MONTH(CURDATE()) AND YEAR(report_date) = YEAR(CURDATE())";
        $period_title = "Laporan Bulan Ini";
    } elseif ($date_filter === 'last_month') {
        $where_clause .= " AND MONTH(report_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(report_date) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        $period_title = "Laporan Bulan Lalu";
    } elseif ($date_filter === 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $where_clause .= " AND report_date BETWEEN ? AND ?";
        $params = [$_GET['start_date'], $_GET['end_date']];
        $period_title = "Laporan dari " . $_GET['start_date'] . " sampai " . $_GET['end_date'];
    } else {
        $period_title = "Semua Laporan";
    }
    
    // Get reports data
    $stmt = $db->prepare("
        SELECT * FROM daily_business_reports 
        $where_clause
        ORDER BY report_date DESC
    ");
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_reports,
            SUM(total_bookings) as total_bookings_sum,
            SUM(total_revenue) as total_revenue_sum,
            AVG(total_revenue) as avg_daily_revenue,
            AVG(total_bookings) as avg_daily_bookings,
            MAX(total_revenue) as max_daily_revenue,
            MIN(total_revenue) as min_daily_revenue
        FROM daily_business_reports
        $where_clause
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create PDF
    $pdf = new SimplePDF("Laporan Bisnis Harian - $period_title");
    
    // Add summary section
    $summary_content = '
    <div class="summary-box">
        <div class="summary-title">📊 Ringkasan Statistik</div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value">' . number_format($summary['total_reports'] ?? 0) . '</div>
                <div class="summary-label">Total Laporan</div>
            </div>
            <div class="summary-item">
                <div class="summary-value currency">Rp ' . number_format($summary['total_revenue_sum'] ?? 0, 0, ',', '.') . '</div>
                <div class="summary-label">Total Pendapatan</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">' . number_format($summary['total_bookings_sum'] ?? 0) . '</div>
                <div class="summary-label">Total Booking</div>
            </div>
            <div class="summary-item">
                <div class="summary-value currency">Rp ' . number_format($summary['avg_daily_revenue'] ?? 0, 0, ',', '.') . '</div>
                <div class="summary-label">Rata-rata Pendapatan Harian</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">' . number_format($summary['avg_daily_bookings'] ?? 0, 1) . '</div>
                <div class="summary-label">Rata-rata Booking Harian</div>
            </div>
            <div class="summary-item">
                <div class="summary-value currency">Rp ' . number_format($summary['max_daily_revenue'] ?? 0, 0, ',', '.') . '</div>
                <div class="summary-label">Pendapatan Harian Tertinggi</div>
            </div>
        </div>
    </div>';
    
    $pdf->addContent($summary_content);
    
    // Add reports table
    $table_content = '
    <h3>📋 Laporan Detail</h3>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Total Booking</th>
                <th>Total Pendapatan</th>
                <th>Reptil Aktif</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>';
    
    if (empty($reports)) {
        $table_content .= '
            <tr>
                <td colspan="5" class="text-center">Tidak ada laporan ditemukan untuk periode yang dipilih</td>
            </tr>';
    } else {
        foreach ($reports as $report) {
            $table_content .= '
            <tr>
                <td>' . date('d M Y', strtotime($report['report_date'])) . '</td>
                <td class="text-center">' . number_format($report['total_bookings']) . '</td>
                <td class="text-right currency">Rp ' . number_format($report['total_revenue'], 0, ',', '.') . '</td>
                <td class="text-center">' . number_format($report['active_reptiles']) . '</td>
                <td>' . htmlspecialchars($report['notes'] ?? '-') . '</td>
            </tr>';
        }
    }
    
    $table_content .= '
        </tbody>
    </table>';
    
    $pdf->addContent($table_content);
    
    // Output PDF
    $filename = 'Laporan_Bisnis_Harian_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->output($filename);
    
} catch (Exception $e) {
    echo '<div style="padding: 20px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">';
    echo '<h3>❌ Kesalahan Ekspor</h3>';
    echo '<p>Maaf, terjadi kesalahan saat membuat laporan:</p>';
    echo '<p><strong>' . error_detail($e) . '</strong></p>';
    echo '<p><a href="reports.php" style="color: #007bff;">← Kembali ke Laporan</a></p>';
    echo '</div>';
    error_log("Error in export_reports.php: " . $e->getMessage());
}
?>