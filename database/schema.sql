-- Baroon Reptile - Skema Database
--
-- Membuat seluruh tabel dari nol. Jalankan pada database kosong, lalu
-- lanjutkan dengan seed.sql untuk data awal dan akun demo.
--
--   mysql -u root baroon_reptile < database/schema.sql
--   mysql -u root baroon_reptile < database/seed.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS testimonials;
DROP TABLE IF EXISTS daily_business_reports;
DROP TABLE IF EXISTS daily_reports;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS facilities;
DROP TABLE IF EXISTS reptiles;
DROP TABLE IF EXISTS reptile_categories;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Pengguna sistem: admin dan pelanggan
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    role ENUM('admin', 'customer') DEFAULT 'customer',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategori reptil beserta tarif penitipan per hari
CREATE TABLE reptile_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT DEFAULT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reptil milik pelanggan
CREATE TABLE reptiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    species VARCHAR(100) DEFAULT NULL,
    age VARCHAR(20) DEFAULT NULL,
    weight DECIMAL(5,2) DEFAULT NULL,
    length DECIMAL(5,2) DEFAULT NULL,
    gender ENUM('male', 'female', 'unknown') DEFAULT NULL,
    special_needs TEXT DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES reptile_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fasilitas penitipan (terrarium, aquarium, kandang)
CREATE TABLE facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    capacity INT DEFAULT 1,
    price_per_day DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pemesanan penitipan
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    reptile_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reptile_id) REFERENCES reptiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pembayaran untuk setiap booking
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'transfer', 'credit_card') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_date TIMESTAMP NULL DEFAULT NULL,
    proof_image VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laporan perawatan harian per reptil (dilihat pelanggan)
CREATE TABLE daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reptile_id INT NOT NULL,
    booking_id INT NOT NULL,
    report_date DATE NOT NULL,
    feeding_time TIME DEFAULT NULL,
    feeding_notes TEXT DEFAULT NULL,
    health_status ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    activity_level ENUM('very_active', 'active', 'normal', 'low', 'inactive') DEFAULT 'normal',
    temperature DECIMAL(4,1) DEFAULT NULL,
    humidity DECIMAL(4,1) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    photos VARCHAR(500) DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reptile_id) REFERENCES reptiles(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rekap bisnis harian (dibuat admin di menu Laporan)
CREATE TABLE daily_business_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL UNIQUE,
    total_bookings INT DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0.00,
    active_reptiles INT DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Testimoni pelanggan
CREATE TABLE testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    rating INT DEFAULT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pesan dari admin ke pelanggan
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('general', 'booking', 'payment', 'reminder', 'notification') DEFAULT 'general',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('unread', 'read', 'archived') DEFAULT 'unread',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_to_user (to_user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
