<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Helper.php';

$routes = require APP_ROOT . '/routes/web.php';
$pageTitle = APP_NAME . ' - Portofolio Digital Siswa SMK';

$loginUrl = BASE_URL . '/login.php';
$registerIndustryUrl = BASE_URL . '/register-industry.php';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($pageTitle) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --siporsi-navy: #102e59;
            --siporsi-blue: #1769aa;
            --siporsi-gold: #f4b942;
            --siporsi-light: #f4f7fc;
            --siporsi-text: #192b43;
            --siporsi-muted: #687991;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body {
            min-height: 100vh;
            background: var(--siporsi-light);
            color: var(--siporsi-text);
            font-family: 'Inter', sans-serif;
        }

        /* NAVBAR */

        .main-navbar {
            background: rgba(16, 46, 89, .97);
            backdrop-filter: blur(12px);
            box-shadow: 0 3px 18px rgba(16, 46, 89, .12);
        }

        .navbar-brand {
            font-size: 23px;
            font-weight: 800;
            letter-spacing: 1.5px;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            margin-right: 9px;
            border-radius: 11px;
            background: var(--siporsi-gold);
            color: var(--siporsi-navy);
            font-size: 17px;
            font-weight: 800;
        }

        .main-navbar .nav-link {
            color: rgba(255,255,255,.8);
            font-size: 13px;
            font-weight: 500;
            padding: 10px 13px !important;
            transition: .2s;
        }

        .main-navbar .nav-link:hover {
            color: var(--siporsi-gold);
        }

        .navbar-login {
            border: 1px solid rgba(255,255,255,.6);
            color: white;
            border-radius: 8px;
            padding: 9px 19px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .navbar-login:hover {
            background: white;
            color: var(--siporsi-navy);
        }

        .navbar-register {
            background: var(--siporsi-gold);
            color: var(--siporsi-navy);
            border-radius: 8px;
            padding: 10px 17px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .navbar-register:hover {
            background: #ffd16d;
            color: var(--siporsi-navy);
        }

        /* HERO */

        .hero-wrapper {
            padding: 65px 0 55px;
            background:
                radial-gradient(circle at 85% 15%, rgba(36,126,202,.12), transparent 30%),
                var(--siporsi-light);
        }

        .hero-panel {
            position: relative;
            overflow: hidden;
            padding: 65px 55px;
            border-radius: 25px;
            background: linear-gradient(120deg, #102e59 0%, #14518b 65%, #1977b7 100%);
            color: white;
            box-shadow: 0 20px 45px rgba(16,46,89,.15);
        }

        .hero-panel::after {
            content: "";
            position: absolute;
            width: 380px;
            height: 380px;
            right: -100px;
            top: -160px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50%;
            box-shadow:
                0 0 0 35px rgba(255,255,255,.035),
                0 0 0 75px rgba(255,255,255,.025);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 30px;
            background: rgba(255,255,255,.1);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }

        .hero-title {
            max-width: 650px;
            margin-top: 24px;
            font-size: clamp(34px, 4vw, 53px);
            line-height: 1.18;
            font-weight: 800;
            letter-spacing: -1.8px;
        }

        .hero-description {
            max-width: 580px;
            margin-top: 20px;
            color: rgba(255,255,255,.78);
            font-size: 15px;
            line-height: 1.9;
        }

        .btn-hero-primary {
            display: inline-block;
            padding: 13px 23px;
            border-radius: 9px;
            background: var(--siporsi-gold);
            color: var(--siporsi-navy);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-hero-primary:hover {
            background: #ffd16d;
            color: var(--siporsi-navy);
            transform: translateY(-2px);
        }

        .btn-hero-outline {
            display: inline-block;
            padding: 12px 22px;
            border: 1px solid rgba(255,255,255,.6);
            border-radius: 9px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
        }

        .btn-hero-outline:hover {
            background: white;
            color: var(--siporsi-navy);
        }

        .hero-visual {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 300px;
        }

        .visual-card {
            width: 290px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 20px;
            background: rgba(255,255,255,.12);
            backdrop-filter: blur(12px);
            box-shadow: 0 20px 45px rgba(0,0,0,.12);
            transform: rotate(3deg);
        }

        .visual-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            border-radius: 15px;
            background: var(--siporsi-gold);
            color: var(--siporsi-navy);
            font-size: 25px;
            margin-bottom: 17px;
        }

        .visual-line {
            height: 8px;
            margin-top: 12px;
            border-radius: 10px;
            background: rgba(255,255,255,.25);
        }

        .visual-line.short {
            width: 60%;
        }

        .visual-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 20px;
        }

        .visual-tags span {
            padding: 6px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,.16);
            font-size: 10px;
        }

        /* SECTION GLOBAL */

        .section-padding {
            padding: 75px 0;
        }

        .section-label {
            color: var(--siporsi-blue);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .section-heading {
            margin-top: 12px;
            color: var(--siporsi-navy);
            font-size: clamp(26px, 3vw, 36px);
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .section-description {
            max-width: 650px;
            margin: 14px auto 0;
            color: var(--siporsi-muted);
            font-size: 14px;
            line-height: 1.9;
        }

        /* ABOUT */

        .about-box {
            height: 100%;
            padding: 32px;
            border: 1px solid #e8edf5;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 25px rgba(20,50,90,.04);
        }

        .about-box h3 {
            color: var(--siporsi-navy);
            font-size: 22px;
            font-weight: 800;
        }

        .about-box p {
            color: var(--siporsi-muted);
            font-size: 14px;
            line-height: 1.9;
        }

        .objective-box {
            height: 100%;
            padding: 28px;
            border-radius: 18px;
            background: #eaf2fc;
        }

        .objective-box h4 {
            color: var(--siporsi-navy);
            font-size: 17px;
            font-weight: 800;
        }

        .objective-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 18px;
            color: #53657d;
            font-size: 13px;
        }

        .check-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #d3e7fa;
            color: var(--siporsi-blue);
            font-weight: 800;
        }

        /* FEATURE */

        .feature-card {
            height: 100%;
            padding: 29px;
            border: 1px solid #e8edf5;
            border-radius: 17px;
            background: white;
            transition: .25s ease;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 35px rgba(16,46,89,.09);
            border-color: #c7d9ed;
        }

        .feature-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 55px;
            height: 55px;
            margin-bottom: 22px;
            border-radius: 15px;
            background: #eaf2fc;
            color: var(--siporsi-blue);
            font-size: 25px;
        }

        .feature-card h4 {
            color: var(--siporsi-navy);
            font-size: 17px;
            font-weight: 800;
        }

        .feature-card p {
            margin: 0;
            color: var(--siporsi-muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* USER ACCESS */

        .access-section {
            padding: 55px;
            border: 1px solid #e8edf5;
            border-radius: 23px;
            background: white;
            box-shadow: 0 12px 35px rgba(16,46,89,.05);
        }

        .access-card {
            height: 100%;
            padding: 28px;
            border: 1px solid #e5ebf3;
            border-radius: 17px;
            background: white;
            transition: .25s ease;
        }

        .access-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(16,46,89,.08);
        }

        .access-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 53px;
            height: 53px;
            margin-bottom: 20px;
            border-radius: 14px;
            background: #eaf2fc;
            font-size: 24px;
        }

        .access-card h4 {
            color: var(--siporsi-navy);
            font-size: 18px;
            font-weight: 800;
        }

        .access-card p {
            min-height: 65px;
            color: var(--siporsi-muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .access-login {
            display: block;
            padding: 11px;
            border: 1px solid var(--siporsi-blue);
            border-radius: 8px;
            color: var(--siporsi-blue);
            text-align: center;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
        }

        .access-login:hover {
            background: var(--siporsi-blue);
            color: white;
        }

        .access-register {
            display: block;
            margin-top: 14px;
            color: var(--siporsi-blue);
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .access-register:hover {
            text-decoration: underline;
        }

        /* FOOTER */

        .main-footer {
            padding: 48px 0 22px;
            background: var(--siporsi-navy);
            color: rgba(255,255,255,.75);
        }

        .footer-brand {
            color: white;
            font-size: 23px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .footer-description {
            max-width: 430px;
            margin-top: 12px;
            font-size: 13px;
            line-height: 1.8;
        }

        .footer-link {
            display: block;
            margin-bottom: 10px;
            color: rgba(255,255,255,.7);
            font-size: 13px;
            text-decoration: none;
        }

        .footer-link:hover {
            color: var(--siporsi-gold);
        }

        .footer-bottom {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,.15);
            font-size: 12px;
        }

        /* RESPONSIVE */

        @media (max-width: 991px) {
            .hero-panel {
                padding: 45px 35px;
            }

            .hero-visual {
                min-height: 250px;
            }

            .access-section {
                padding: 35px;
            }
        }

        @media (max-width: 767px) {
            .hero-wrapper {
                padding: 30px 0;
            }

            .hero-panel {
                padding: 38px 25px;
                border-radius: 18px;
            }

            .hero-title {
                font-size: 35px;
                letter-spacing: -1px;
            }

            .hero-visual {
                min-height: 240px;
                margin-top: 25px;
            }

            .section-padding {
                padding: 50px 0;
            }

            .access-section {
                padding: 25px 18px;
            }

            .about-box {
                padding: 25px;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark main-navbar sticky-top">
    <div class="container">

        <a class="navbar-brand d-flex align-items-center"
           href="<?= e(BASE_URL) ?>/">

            <span class="brand-mark">S</span>
            SIPORSI

        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Buka navigasi">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

                <li class="nav-item">
                    <a class="nav-link" href="#beranda">Beranda</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#tentang">Tentang</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#fitur">Fitur</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#pengguna">Pengguna</a>
                </li>

                <li class="nav-item ms-lg-2">
                    <a class="navbar-login" href="<?= e($loginUrl) ?>">
                        Login
                    </a>
                </li>

                <li class="nav-item ms-lg-2 mt-3 mt-lg-0">
                    <a class="navbar-register"
                       href="<?= e($registerIndustryUrl) ?>">
                        Daftar Industri
                    </a>
                </li>

            </ul>

        </div>
    </div>
</nav>


<main>

<!-- HERO -->
<section id="beranda" class="hero-wrapper">

    <div class="container">

        <div class="hero-panel">

            <div class="row align-items-center">

                <div class="col-lg-7">

                    <div class="hero-content">

                        <span class="hero-label">
                            ✦ Sistem Informasi Portofolio Digital Siswa
                        </span>

                        <h1 class="hero-title">
                            Tunjukkan Kompetensimu,
                            Bangun Masa Depanmu.
                        </h1>

                        <p class="hero-description">
                            SIPORSI merupakan platform digital SMK Negeri 4
                            Banjarmasin untuk memperkenalkan profil,
                            keterampilan, proyek, pengalaman, dan prestasi
                            siswa kepada dunia industri.
                        </p>

                        <div class="d-flex flex-wrap gap-3 mt-4">

                            <a href="<?= e($loginUrl) ?>"
                               class="btn-hero-primary">
                                Masuk ke SIPORSI &nbsp; →
                            </a>

                            <a href="#tentang"
                               class="btn-hero-outline">
                                Pelajari Platform
                            </a>

                        </div>

                    </div>

                </div>

                <div class="col-lg-5">

                    <div class="hero-visual">

                        <div class="visual-card">

                            <div class="visual-avatar">🎓</div>

                            <div class="fw-bold fs-5">
                                Portofolio Digital
                            </div>

                            <div class="small opacity-75 mt-1">
                                Kompetensi dan karya siswa
                            </div>

                            <div class="visual-line"></div>
                            <div class="visual-line short"></div>

                            <div class="visual-tags">
                                <span>Profil</span>
                                <span>Keterampilan</span>
                                <span>Proyek</span>
                                <span>Prestasi</span>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- TENTANG SIPORSI -->
<section id="tentang" class="section-padding">

    <div class="container">

        <div class="row g-4 align-items-stretch">

            <div class="col-lg-7">

                <div class="about-box">

                    <span class="section-label">
                        Tentang Platform
                    </span>

                    <h2 class="section-heading">
                        Mengenal SIPORSI
                    </h2>

                    <p class="mt-4">
                        SIPORSI adalah Sistem Informasi Portofolio Digital
                        Siswa SMK Negeri 4 Banjarmasin yang dirancang untuk
                        mendokumentasikan dan memperkenalkan kompetensi
                        siswa melalui media digital.
                    </p>

                    <p>
                        Melalui platform ini, siswa dapat membangun
                        portofolio sebagai gambaran kemampuan dan
                        pengalaman mereka. Dunia industri juga dapat
                        mengenal potensi calon tenaga kerja berdasarkan
                        kompetensi yang dimiliki.
                    </p>

                </div>

            </div>

            <div class="col-lg-5">

                <div class="objective-box">

                    <h4>Tujuan SIPORSI</h4>

                    <div class="objective-item">
                        <span class="check-icon">✓</span>
                        <span>Mendokumentasikan kompetensi siswa.</span>
                    </div>

                    <div class="objective-item">
                        <span class="check-icon">✓</span>
                        <span>Menampilkan karya dan pengalaman siswa.</span>
                    </div>

                    <div class="objective-item">
                        <span class="check-icon">✓</span>
                        <span>Mempermudah industri mengenal talenta.</span>
                    </div>

                    <div class="objective-item">
                        <span class="check-icon">✓</span>
                        <span>Mendukung pengembangan karier lulusan.</span>
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- FITUR -->
<section id="fitur" class="section-padding">

    <div class="container">

        <div class="text-center mb-5">

            <span class="section-label">Fitur Platform</span>

            <h2 class="section-heading">
                Semua Kompetensi dalam Satu Platform
            </h2>

            <p class="section-description">
                SIPORSI membantu siswa mendokumentasikan perjalanan,
                kemampuan, serta hasil karya dalam satu portofolio digital.
            </p>

        </div>

        <div class="row g-4">

            <div class="col-md-4">

                <div class="feature-card">

                    <div class="feature-icon">👤</div>

                    <h4>Profil Siswa</h4>

                    <p>
                        Menampilkan informasi profil dan identitas
                        profesional siswa sebagai bagian dari portofolio.
                    </p>

                </div>

            </div>

            <div class="col-md-4">

                <div class="feature-card">

                    <div class="feature-icon">💻</div>

                    <h4>Proyek dan Hasil Karya</h4>

                    <p>
                        Mendokumentasikan proyek, hasil karya,
                        deskripsi pekerjaan, dan teknologi yang digunakan.
                    </p>

                </div>

            </div>

            <div class="col-md-4">

                <div class="feature-card">

                    <div class="feature-icon">⚙️</div>

                    <h4>Keterampilan</h4>

                    <p>
                        Memperlihatkan kemampuan dan tingkat penguasaan
                        keterampilan yang dimiliki setiap siswa.
                    </p>

                </div>

            </div>

            <div class="col-md-4">

                <div class="feature-card">

                    <div class="feature-icon">🏢</div>

                    <h4>Pengalaman PKL</h4>

                    <p>
                        Mendokumentasikan pengalaman praktik kerja
                        lapangan dan pengalaman profesional siswa.
                    </p>

                </div>

            </div>

            <div class="col-md-4">

                <div class="feature-card">

                    <div class="feature-icon">🏆</div>

                    <h4>Prestasi dan Penghargaan</h4>

                    <p>
                        Menampilkan pencapaian dan penghargaan
                        yang pernah diperoleh siswa.
                    </p>

                </div>

            </div>

            <div class="col-md-4">

                <div class="feature-card">

                    <div class="feature-icon">🤝</div>

                    <h4>Hubungan Industri</h4>

                    <p>
                        Membantu dunia industri mengenal potensi
                        dan kompetensi calon tenaga kerja.
                    </p>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- AREA PENGGUNA -->
<section id="pengguna" class="section-padding">

    <div class="container">

        <div class="access-section">

            <div class="text-center mb-5">

                <span class="section-label">Area Pengguna</span>

                <h2 class="section-heading">
                    Mulai dari Area Pengguna Anda
                </h2>

                <p class="section-description">
                    Masuk ke SIPORSI untuk mengakses fasilitas
                    sesuai dengan peran dan hak akses masing-masing.
                </p>

            </div>

            <div class="row g-4">

                <!-- ADMIN -->
                <div class="col-lg-4">

                    <div class="access-card">

                        <div class="access-icon">🛡️</div>

                        <h4>Admin Sekolah</h4>

                        <p>
                            Mengelola data siswa, jurusan, keterampilan,
                            akun industri, serta melakukan verifikasi
                            portofolio.
                        </p>

                        <a href="<?= e($loginUrl) ?>"
                           class="access-login">
                            Masuk sebagai Admin →
                        </a>

                    </div>

                </div>

                <!-- SISWA -->
                <div class="col-lg-4">

                    <div class="access-card">

                        <div class="access-icon">🎓</div>

                        <h4>Siswa</h4>

                        <p>
                            Membangun profil digital, mengelola
                            keterampilan, mengunggah proyek, serta
                            menampilkan pengalaman dan prestasi.
                        </p>

                        <a href="<?= e($loginUrl) ?>"
                           class="access-login">
                            Masuk sebagai Siswa →
                        </a>

                    </div>

                </div>

                <!-- INDUSTRI -->
                <div class="col-lg-4">

                    <div class="access-card">

                        <div class="access-icon">🏢</div>

                        <h4>Industri / HRD</h4>

                        <p>
                            Menjelajahi portofolio siswa, mengenal
                            kompetensi kandidat, dan menggunakan
                            fasilitas rekrutmen.
                        </p>

                        <a href="<?= e($loginUrl) ?>"
                           class="access-login">
                            Masuk sebagai Industri →
                        </a>

                        <a href="<?= e($registerIndustryUrl) ?>"
                           class="access-register">
                            Daftar sebagai Mitra Industri
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

</main>


<!-- FOOTER -->
<footer class="main-footer">

    <div class="container">

        <div class="row g-4">

            <div class="col-lg-6">

                <div class="footer-brand">SIPORSI</div>

                <p class="footer-description">
                    Sistem Informasi Portofolio Digital Siswa
                    SMK Negeri 4 Banjarmasin sebagai media
                    dokumentasi kompetensi dan karya siswa.
                </p>

            </div>

            <div class="col-lg-3">

                <h6 class="text-white fw-bold mb-3">
                    Navigasi
                </h6>

                <a href="#beranda" class="footer-link">Beranda</a>
                <a href="#tentang" class="footer-link">Tentang SIPORSI</a>
                <a href="#fitur" class="footer-link">Fitur Platform</a>
                <a href="#pengguna" class="footer-link">Area Pengguna</a>

            </div>

            <div class="col-lg-3">

                <h6 class="text-white fw-bold mb-3">
                    Akses
                </h6>

                <a href="<?= e($loginUrl) ?>" class="footer-link">
                    Login Pengguna
                </a>

                <a href="<?= e($registerIndustryUrl) ?>" class="footer-link">
                    Registrasi Industri
                </a>

            </div>

        </div>

        <div class="footer-bottom text-center">
            &copy; <?= date('Y') ?> SIPORSI —
            Sistem Informasi Portofolio Digital Siswa.
            Seluruh hak cipta dilindungi.
        </div>

    </div>

</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/app.js"></script>

</body>
</html>
