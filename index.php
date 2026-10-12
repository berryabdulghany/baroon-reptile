<?php
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baroon Reptile - Penitipan Hewan Reptile Terpercaya</title>
    
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
        
        .hero-section {
            background: linear-gradient(135deg, #2c5530 0%, #4a7c59 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="reptile" patternUnits="userSpaceOnUse" width="20" height="20"><circle cx="10" cy="10" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23reptile)"/></svg>') repeat;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            color: #e8f5e8;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .btn-custom {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        
        .btn-primary-custom {
            background: #ff6b35;
            color: white;
            border: 2px solid #ff6b35;
        }
        
        .btn-primary-custom:hover {
            background: transparent;
            color: #ff6b35;
            transform: translateY(-2px);
        }
        
        .btn-outline-custom {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-outline-custom:hover {
            background: white;
            color: #2c5530;
            transform: translateY(-2px);
        }
        
        .features-section {
            padding: 80px 0;
            background: #f8f9fa;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4a7c59, #2c5530);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c5530;
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .stats-section {
            background: linear-gradient(135deg, #2c5530, #4a7c59);
            padding: 60px 0;
            color: white;
        }
        
        .stat-item {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            display: block;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .testimonial-section {
            padding: 80px 0;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .navbar-custom {
            background: rgba(44, 85, 48, 0.95) !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .footer {
            background: #2c5530;
            color: white;
            padding: 50px 0 20px;
        }

        @media (max-width: 575.98px) {
            .hero-section {
                min-height: 100svh;
                padding: 6rem 0 3rem;
            }

            .hero-title {
                font-size: clamp(2.25rem, 10vw, 3rem);
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .hero-buttons {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .btn-custom {
                width: 100%;
                margin: 0;
            }

            .hero-section .text-center .fa-dragon {
                font-size: clamp(6rem, 35vw, 10rem) !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-dragon me-2"></i>Baroon Reptile
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#layanan">Layanan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tentang">Tentang</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#kontak">Kontak</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light ms-2 px-3" href="auth/login.php">Masuk</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="hero-title">Baroon Reptile</h1>
                        <p class="hero-subtitle">
                            Tempat penitipan hewan reptile terpercaya dengan fasilitas modern dan perawatan profesional. 
                            Kami menjamin keamanan dan kenyamanan reptile kesayangan Anda.
                        </p>
                        <div class="hero-buttons">
                            <a href="auth/register.php" class="btn btn-custom btn-primary-custom">
                                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                            </a>
                            <a href="#layanan" class="btn btn-custom btn-outline-custom">
                                <i class="fas fa-info-circle me-2"></i>Pelajari Lebih Lanjut
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-dragon" style="font-size: 15rem; color: rgba(255,255,255,0.1);"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="layanan" class="features-section">
        <div class="container">
            <h2 class="section-title">Layanan Kami</h2>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <h4>Penitipan Harian</h4>
                        <p>Layanan penitipan harian dengan fasilitas terrarium modern dan kontrol suhu yang tepat untuk berbagai jenis reptile.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <h4>Perawatan Kesehatan</h4>
                        <p>Monitoring kesehatan harian oleh staff berpengalaman dengan laporan rutin kondisi reptile Anda.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h4>Pemberian Makan</h4>
                        <p>Jadwal pemberian makan yang teratur sesuai dengan kebutuhan spesifik setiap jenis reptile.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h4>Dokumentasi Harian</h4>
                        <p>Foto dan video harian reptile Anda sebagai bukti perawatan yang baik selama masa penitipan.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-thermometer-half"></i>
                        </div>
                        <h4>Kontrol Lingkungan</h4>
                        <p>Sistem kontrol suhu dan kelembaban otomatis untuk menjaga kondisi optimal habitat reptile.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Keamanan 24/7</h4>
                        <p>Sistem keamanan dan monitoring 24 jam untuk memastikan keselamatan reptile kesayangan Anda.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number">500+</span>
                        <span class="stat-label">Reptile Dititipkan</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number">200+</span>
                        <span class="stat-label">Customer Puas</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number">3</span>
                        <span class="stat-label">Tahun Pengalaman</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-item">
                        <span class="stat-number">15</span>
                        <span class="stat-label">Fasilitas Modern</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="tentang" class="features-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="section-title text-start">Tentang Baroon Reptile</h2>
                    <p class="lead">Baroon Reptile adalah tempat penitipan hewan reptile terpercaya yang telah melayani para pecinta reptile selama bertahun-tahun.</p>
                    <p>Kami memahami bahwa setiap reptile memiliki kebutuhan khusus, oleh karena itu kami menyediakan fasilitas dan perawatan yang disesuaikan dengan spesies masing-masing reptile.</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Staff berpengalaman dan terlatih</li>
                        <li><i class="fas fa-check text-success me-2"></i>Fasilitas modern dan higienis</li>
                        <li><i class="fas fa-check text-success me-2"></i>Sistem monitoring 24/7</li>
                        <li><i class="fas fa-check text-success me-2"></i>Laporan harian kondisi reptile</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-leaf" style="font-size: 12rem; color: #4a7c59; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="kontak" class="stats-section">
        <div class="container">
            <h2 class="section-title text-white">Hubungi Kami</h2>
            <div class="row">
                <div class="col-lg-4 text-center mb-4">
                    <div class="feature-icon mx-auto mb-3">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h5>Alamat</h5>
                    <p>Jl. Reptile Paradise No. 123<br>Jakarta Selatan, 12345</p>
                </div>
                <div class="col-lg-4 text-center mb-4">
                    <div class="feature-icon mx-auto mb-3">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h5>Telepon</h5>
                    <p>+62 21 1234 5678<br>+62 812 3456 7890</p>
                </div>
                <div class="col-lg-4 text-center mb-4">
                    <div class="feature-icon mx-auto mb-3">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5>Email</h5>
                    <p>info@baroonreptile.com<br>support@baroonreptile.com</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <h5><i class="fas fa-dragon me-2"></i>Baroon Reptile</h5>
                    <p>Tempat penitipan hewan reptile terpercaya dengan fasilitas modern dan perawatan profesional.</p>
                </div>
                <div class="col-lg-4">
                    <h5>Jam Operasional</h5>
                    <p>Senin - Jumat: 08:00 - 18:00<br>
                    Sabtu - Minggu: 09:00 - 17:00</p>
                </div>
                <div class="col-lg-4">
                    <h5>Ikuti Kami</h5>
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2024 Baroon Reptile. Semua hak dilindungi.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(44, 85, 48, 0.98)';
            } else {
                navbar.style.background = 'rgba(44, 85, 48, 0.95)';
            }
        });
    </script>
</body>
</html>
