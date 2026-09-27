<?php
// Konfigurasi Baroon Reptile
//
// Semua nilai dibaca dari environment variable. Default-nya disesuaikan
// dengan instalasi XAMPP, jadi di lokal tidak perlu mengubah apa pun.
// Di server (misalnya lewat Docker), isi variabel yang sama di file .env.

function env($key, $default = null) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

define('APP_DEBUG', filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN));
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

// Pesan exception untuk ditampilkan ke pengguna: detail lengkap hanya saat
// APP_DEBUG, selebihnya cukup dicatat di log server.
function error_detail(Throwable $e) {
    error_log($e->getMessage());
    return APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Terjadi kesalahan pada sistem.';
}

class Database {
    private $conn;

    public function getConnection() {
        $this->conn = null;

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'baroon_reptile')
        );

        try {
            $this->conn = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''));
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            error_log('Koneksi database gagal: ' . $exception->getMessage());
            http_response_code(500);
            echo APP_DEBUG
                ? 'Koneksi database gagal: ' . htmlspecialchars($exception->getMessage())
                : 'Layanan sedang tidak tersedia. Silakan coba beberapa saat lagi.';
            exit;
        }

        return $this->conn;
    }
}

// Fungsi helper untuk koneksi cepat
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

// Pengaturan session
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

require_once __DIR__ . '/../includes/security.php';
verify_csrf();
