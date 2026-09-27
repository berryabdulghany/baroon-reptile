<?php
// Proteksi CSRF untuk semua form.
//
// Setiap form POST menyertakan csrf_field(), dan config/database.php
// memanggil verify_csrf() sehingga POST tanpa token yang cocok ditolak
// sebelum halaman sempat memproses datanya.

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi formulir sudah kedaluwarsa. Silakan kembali, muat ulang halaman, lalu coba lagi.');
    }
}
