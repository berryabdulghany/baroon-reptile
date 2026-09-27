-- Baroon Reptile - Data Awal dan Akun Demo
--
-- Jalankan setelah schema.sql. Semua nama dan data di sini fiktif.
-- Tanggal dihitung relatif terhadap hari import, jadi grafik dashboard
-- selalu berisi data beberapa bulan terakhir.
--
-- Akun demo:
--   admin     / admin123     (administrator)
--   customer1 / customer123  (pelanggan)
--   sari      / customer123  (pelanggan)
--   dimas     / customer123  (pelanggan)

SET NAMES utf8mb4;

INSERT INTO users (id, username, email, password, full_name, phone, address, role, status) VALUES
(1, 'admin', 'admin@baroonreptile.com', '$2y$10$OQwGvF3JP81.qfzuAPx6cu4Z81SkpfdWMbJTp0DlXG9Xm4AeXXrEu', 'Administrator', '081200000001', 'Jl. Dipati Ukur No. 112, Bandung', 'admin', 'active'),
(2, 'customer1', 'budi@example.com', '$2y$10$5FqpPkrwH2OMSUxeU5eRx.5Kv/GIw/WDVAZ9AvOIaNM2VFa8DOYr2', 'Budi Santoso', '081234567801', 'Jl. Cihampelas No. 20, Bandung', 'customer', 'active'),
(3, 'sari', 'sari@example.com', '$2y$10$5FqpPkrwH2OMSUxeU5eRx.5Kv/GIw/WDVAZ9AvOIaNM2VFa8DOYr2', 'Sari Wulandari', '081234567802', 'Jl. Setiabudi No. 45, Bandung', 'customer', 'active'),
(4, 'dimas', 'dimas@example.com', '$2y$10$5FqpPkrwH2OMSUxeU5eRx.5Kv/GIw/WDVAZ9AvOIaNM2VFa8DOYr2', 'Dimas Pratama', '081234567803', 'Jl. Buah Batu No. 8, Bandung', 'customer', 'active');

INSERT INTO reptile_categories (id, name, description, price_per_day) VALUES
(1, 'Ular Kecil', 'Ular dengan panjang kurang dari 1 meter', 25000.00),
(2, 'Ular Sedang', 'Ular dengan panjang 1-2 meter', 35000.00),
(3, 'Ular Besar', 'Ular dengan panjang lebih dari 2 meter', 50000.00),
(4, 'Kadal Kecil', 'Gecko, tokek, dan kadal kecil lainnya', 20000.00),
(5, 'Kadal Sedang', 'Iguana, bearded dragon, monitor kecil', 40000.00),
(6, 'Kadal Besar', 'Monitor besar, iguana dewasa', 60000.00),
(7, 'Kura-kura Darat', 'Kura-kura darat berbagai ukuran', 30000.00),
(8, 'Kura-kura Air', 'Kura-kura air dan semi-aquatic', 35000.00);

INSERT INTO facilities (id, name, description, capacity, price_per_day, status) VALUES
(1, 'Terrarium Kecil A1', 'Terrarium 60x40x40cm untuk reptil kecil', 1, 25000.00, 'occupied'),
(2, 'Terrarium Kecil A2', 'Terrarium 60x40x40cm untuk reptil kecil', 1, 25000.00, 'available'),
(3, 'Terrarium Sedang B1', 'Terrarium 100x50x50cm untuk reptil sedang', 1, 35000.00, 'available'),
(4, 'Terrarium Sedang B2', 'Terrarium 100x50x50cm untuk reptil sedang', 1, 35000.00, 'maintenance'),
(5, 'Terrarium Besar C1', 'Terrarium 150x80x80cm untuk reptil besar', 1, 50000.00, 'occupied'),
(6, 'Aquarium Kura-kura D1', 'Aquarium 100x50x40cm untuk kura-kura air', 1, 30000.00, 'available'),
(7, 'Kandang Outdoor E1', 'Kandang outdoor untuk kura-kura darat', 2, 40000.00, 'available');

INSERT INTO reptiles (id, customer_id, category_id, name, species, age, weight, length, gender, special_needs, status) VALUES
(1, 2, 5, 'Rex', 'Bearded Dragon (Pogona vitticeps)', '3 tahun', 0.45, 50.00, 'male', 'Butuh lampu UVB 10-12 jam per hari', 'active'),
(2, 2, 2, 'Mochi', 'Ball Python (Python regius)', '4 tahun', 1.80, 120.00, 'female', 'Makan tikus ukuran sedang setiap 10 hari', 'active'),
(3, 3, 4, 'Kiko', 'Leopard Gecko (Eublepharis macularius)', '2 tahun', 0.07, 20.00, 'female', 'Suka jangkrik, taburi kalsium', 'active'),
(4, 3, 7, 'Tanky', 'Sulcata Tortoise (Centrochelys sulcata)', '6 tahun', 8.50, 35.00, 'male', 'Diet sayuran hijau dan rumput', 'active'),
(5, 4, 3, 'Naga', 'Reticulated Python (Malayopython reticulatus)', '7 tahun', 25.00, 380.00, 'male', 'Wajib ditangani dua orang', 'active'),
(6, 4, 8, 'Pino', 'Red-eared Slider (Trachemys scripta elegans)', '5 tahun', 1.20, 22.00, 'female', 'Air diganti dua hari sekali', 'active');

-- total_days = end_date - start_date, total_price = total_days x tarif kategori
INSERT INTO bookings (id, customer_id, reptile_id, start_date, end_date, total_days, price_per_day, total_price, status, notes, created_at) VALUES
(1,  2, 1, CURDATE() - INTERVAL 170 DAY, CURDATE() - INTERVAL 165 DAY, 5, 40000.00, 200000.00, 'completed', 'Titip selama mudik', NOW() - INTERVAL 175 DAY),
(2,  3, 3, CURDATE() - INTERVAL 140 DAY, CURDATE() - INTERVAL 133 DAY, 7, 20000.00, 140000.00, 'completed', NULL, NOW() - INTERVAL 145 DAY),
(3,  4, 5, CURDATE() - INTERVAL 110 DAY, CURDATE() - INTERVAL 106 DAY, 4, 50000.00, 200000.00, 'completed', 'Sudah makan sebelum dititip', NOW() - INTERVAL 115 DAY),
(4,  2, 2, CURDATE() - INTERVAL 80 DAY,  CURDATE() - INTERVAL 70 DAY, 10, 35000.00, 350000.00, 'completed', NULL, NOW() - INTERVAL 85 DAY),
(5,  3, 4, CURDATE() - INTERVAL 50 DAY,  CURDATE() - INTERVAL 44 DAY,  6, 30000.00, 180000.00, 'completed', 'Mohon dijemur pagi hari', NOW() - INTERVAL 55 DAY),
(6,  4, 6, CURDATE() - INTERVAL 25 DAY,  CURDATE() - INTERVAL 22 DAY,  3, 35000.00, 105000.00, 'completed', NULL, NOW() - INTERVAL 28 DAY),
(7,  2, 1, CURDATE() - INTERVAL 12 DAY,  CURDATE() - INTERVAL 7 DAY,   5, 40000.00, 200000.00, 'completed', NULL, NOW() - INTERVAL 15 DAY),
(8,  3, 3, CURDATE() - INTERVAL 3 DAY,   CURDATE() + INTERVAL 4 DAY,   7, 20000.00, 140000.00, 'in_progress', 'Kiko sedang ganti kulit', NOW() - INTERVAL 6 DAY),
(9,  4, 5, CURDATE() - INTERVAL 1 DAY,   CURDATE() + INTERVAL 4 DAY,   5, 50000.00, 250000.00, 'in_progress', NULL, NOW() - INTERVAL 4 DAY),
(10, 2, 2, CURDATE() + INTERVAL 5 DAY,   CURDATE() + INTERVAL 9 DAY,   4, 35000.00, 140000.00, 'confirmed', NULL, NOW() - INTERVAL 2 DAY),
(11, 3, 4, CURDATE() + INTERVAL 10 DAY,  CURDATE() + INTERVAL 13 DAY,  3, 30000.00,  90000.00, 'pending', 'Jemput di rumah jika memungkinkan', NOW()),
(12, 4, 6, CURDATE() + INTERVAL 7 DAY,   CURDATE() + INTERVAL 9 DAY,   2, 35000.00,  70000.00, 'cancelled', 'Batal karena jadwal berubah', NOW() - INTERVAL 1 DAY);

INSERT INTO payments (booking_id, amount, payment_method, payment_status, payment_date, notes, created_at) VALUES
(1,  200000.00, 'transfer', 'paid', NOW() - INTERVAL 174 DAY, NULL, NOW() - INTERVAL 175 DAY),
(2,  140000.00, 'cash', 'paid', NOW() - INTERVAL 140 DAY, 'Bayar di tempat', NOW() - INTERVAL 145 DAY),
(3,  200000.00, 'transfer', 'paid', NOW() - INTERVAL 114 DAY, NULL, NOW() - INTERVAL 115 DAY),
(4,  350000.00, 'credit_card', 'paid', NOW() - INTERVAL 84 DAY, NULL, NOW() - INTERVAL 85 DAY),
(5,  180000.00, 'transfer', 'paid', NOW() - INTERVAL 54 DAY, NULL, NOW() - INTERVAL 55 DAY),
(6,  105000.00, 'cash', 'paid', NOW() - INTERVAL 25 DAY, NULL, NOW() - INTERVAL 28 DAY),
(7,  200000.00, 'transfer', 'paid', NOW() - INTERVAL 14 DAY, NULL, NOW() - INTERVAL 15 DAY),
(8,  140000.00, 'transfer', 'paid', NOW() - INTERVAL 5 DAY, NULL, NOW() - INTERVAL 6 DAY),
(9,  250000.00, 'cash', 'paid', NOW() - INTERVAL 1 DAY, NULL, NOW() - INTERVAL 4 DAY),
(10, 140000.00, 'transfer', 'pending', NULL, 'Menunggu konfirmasi transfer', NOW() - INTERVAL 2 DAY);

INSERT INTO daily_reports (reptile_id, booking_id, report_date, feeding_time, feeding_notes, health_status, activity_level, temperature, humidity, notes, created_by) VALUES
(1, 7, CURDATE() - INTERVAL 11 DAY, '08:00:00', 'Jangkrik 10 ekor dan sayuran', 'excellent', 'very_active', 32.0, 40.0, 'Berjemur di bawah lampu basking cukup lama', 1),
(1, 7, CURDATE() - INTERVAL 9 DAY,  '08:15:00', 'Ulat hongkong 8 ekor', 'good', 'active', 31.5, 42.0, NULL, 1),
(3, 8, CURDATE() - INTERVAL 2 DAY,  '19:00:00', 'Jangkrik 5 ekor, ditaburi kalsium', 'good', 'normal', 28.0, 55.0, 'Mulai proses ganti kulit, kelembapan dinaikkan', 1),
(3, 8, CURDATE() - INTERVAL 1 DAY,  '19:10:00', 'Menolak makan', 'fair', 'low', 28.5, 60.0, 'Wajar saat ganti kulit, dipantau terus', 1),
(3, 8, CURDATE(),                   '19:05:00', 'Jangkrik 6 ekor', 'good', 'active', 28.0, 55.0, 'Ganti kulit selesai dengan bersih', 1),
(5, 9, CURDATE(),                   NULL, 'Tidak dijadwalkan makan hari ini', 'excellent', 'normal', 30.0, 65.0, 'Berendam di water bowl sore hari', 1);

-- Rekap bisnis harian dua hari sekali selama 180 hari terakhir
INSERT INTO daily_business_reports (report_date, total_bookings, total_revenue, active_reptiles, notes)
WITH RECURSIVE days (n) AS (
    SELECT 0
    UNION ALL
    SELECT n + 2 FROM days WHERE n < 178
)
SELECT
    CURDATE() - INTERVAL n DAY,
    1 + (n * 7) % 5,
    (1 + (n * 7) % 5) * 45000 + (n % 4) * 10000,
    3 + n % 4,
    CASE WHEN n % 14 = 0 THEN 'Operasional berjalan normal' END
FROM days;

INSERT INTO messages (from_user_id, to_user_id, subject, message, message_type, priority, status, created_at) VALUES
(1, 3, 'Kiko sedang ganti kulit', 'Halo Kak Sari, Kiko mulai ganti kulit hari ini. Kelembapan terrarium sudah kami naikkan dan kondisinya aman.', 'booking', 'normal', 'read', NOW() - INTERVAL 2 DAY),
(1, 2, 'Pengingat pembayaran', 'Halo Pak Budi, pembayaran untuk booking Mochi masih menunggu konfirmasi transfer. Terima kasih.', 'payment', 'high', 'unread', NOW() - INTERVAL 1 DAY);

INSERT INTO testimonials (customer_id, rating, comment, status) VALUES
(2, 5, 'Rex pulang dalam kondisi sehat dan laporan hariannya lengkap.', 'approved'),
(3, 5, 'Staf paham betul soal gecko yang sedang ganti kulit.', 'approved'),
(4, 4, 'Kandangnya bersih, hanya saja ingin ada foto harian.', 'pending');
