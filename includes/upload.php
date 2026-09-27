<?php
// Upload foto reptil.
//
// Jenis file ditentukan dari isi file (finfo), bukan dari nama file atau
// Content-Type yang dikirim browser, dan nama file dibuat acak. Dengan
// begitu file .php yang disamarkan sebagai gambar tidak bisa lolos.

const REPTILE_PHOTO_DIR = 'uploads/reptiles/';

const ALLOWED_PHOTO_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

/**
 * Simpan foto dari $_FILES[$field].
 *
 * Mengembalikan [path, error]. Path relatif terhadap root aplikasi
 * (misalnya "uploads/reptiles/reptile_ab12.jpg"), atau null jika tidak
 * ada file yang diunggah atau terjadi error.
 */
function upload_reptile_photo($field = 'photo') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Gagal mengupload foto!'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return [null, 'Ukuran file maksimal 5MB!'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(ALLOWED_PHOTO_TYPES[$mime])) {
        return [null, 'Format file harus JPG, PNG, GIF, atau WEBP!'];
    }

    $dir = __DIR__ . '/../' . REPTILE_PHOTO_DIR;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'reptile_' . bin2hex(random_bytes(8)) . '.' . ALLOWED_PHOTO_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return [null, 'Gagal mengupload foto!'];
    }

    return [REPTILE_PHOTO_DIR . $filename, null];
}

/**
 * Hapus foto lama. Hanya file di dalam folder foto reptil yang boleh
 * dihapus, supaya path dari database tidak bisa dipakai menghapus file lain.
 */
function delete_reptile_photo($path) {
    if (!$path || strpos($path, REPTILE_PHOTO_DIR) !== 0 || strpos($path, '..') !== false) {
        return;
    }

    $full_path = __DIR__ . '/../' . $path;
    if (is_file($full_path)) {
        unlink($full_path);
    }
}
