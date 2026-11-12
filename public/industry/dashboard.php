<?php

require_once __DIR__ . '/../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';
require_once APP_ROOT . '/app/core/Database.php';

// Hanya akun industri yang boleh masuk
AuthMiddleware::requireRole(['industry']);

$csrfToken = Csrf::token();
$user = $_SESSION['user'];

$db = Database::connection();

// Mengambil informasi perusahaan berdasarkan akun login
$industryStmt = $db->prepare("
    SELECT company_name, industry_type, verification_status
    FROM industries
    WHERE user_id = :user_id
    LIMIT 1
");

$industryStmt->execute([
    'user_id' => $user['id']
]);

$industry = $industryStmt->fetch();

// Menghitung jumlah portofolio siswa yang dapat dilihat publik
$totalCandidates = (int) $db->query("
    SELECT COUNT(*)
    FROM students
    WHERE is_public = 1
      AND student_status IN ('active', 'graduated')
")->fetchColumn();

// Menghitung jumlah proyek yang telah disetujui
$totalApprovedProjects = (int) $db->query("
    SELECT COUNT(*)
    FROM projects
    WHERE publication_status = 'approved'
")->fetchColumn();

// Mengamankan data tampilan
$username = htmlspecialchars(
    $user['username'] ?? 'Industri',
    ENT_QUOTES,
    'UTF-8'
);

$companyName = htmlspecialchars(
    $industry['company_name'] ?? 'Mitra Industri',
    ENT_QUOTES,
    'UTF-8'
);

$industryType = htmlspecialchars(
    $industry['industry_type'] ?? 'Industri',
    ENT_QUOTES,
    'UTF-8'
);

// Status verifikasi perusahaan
$verificationStatus = $industry['verification_status'] ?? 'pending';

$verificationLabels = [
    'verified' => 'Terverifikasi',
    'pending' => 'Menunggu Verifikasi',
    'rejected' => 'Ditolak'
];

$verificationLabel = $verificationLabels[$verificationStatus]
    ?? 'Status Tidak Diketahui';

// Alamat halaman portofolio industri yang sudah tersedia
$portfolioUrl = BASE_URL . '/industry/portfolios/index.php';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Industri | SIPORSI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>

        :root {
            --navy: #102e59;
            --blue: #1769aa;
            --gold: #f4b942;
            --background: #f4f7fc;
            --text: #1c2e45;
            --muted: #718096;
            --border: #e7edf5;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        /* SIDEBAR */

        .industry-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1030;
            width: 255px;
            padding: 25px 18px;
            overflow-y: auto;
            background: var(--navy);
            color: white;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 5px 10px 30px;
            color: white;
            text-decoration: none;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--gold);
            color: var(--navy);
            font-size: 20px;
            font-weight: 800;
        }

        .sidebar-brand strong {
            display: block;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .sidebar-brand small {
            color: rgba(255,255,255,.6);
            font-size: 10px;
        }

        .sidebar-label {
            margin: 18px 10px 10px;
            color: rgba(255,255,255,.45);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 5px;
            padding: 12px 13px;
            border-radius: 9px;
            color: rgba(255,255,255,.75);
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: .2s;
        }

        .sidebar-menu:hover {
            background: rgba(255,255,255,.1);
            color: white;
        }

        .sidebar-menu.active {
            background: var(--blue);
            color: white;
            font-weight: 700;
        }

        .sidebar-icon {
            width: 22px;
            font-size: 17px;
            text-align: center;
        }

        .sidebar-bottom {
            margin-top: 35px;
            padding: 15px 10px;
            border-top: 1px solid rgba(255,255,255,.13);
        }

        .sidebar-bottom small {
            color: rgba(255,255,255,.55);
        }

        /* MAIN CONTENT */

        .industry-main {
            min-height: 100vh;
            margin-left: 255px;
        }

        .industry-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 75px;
            padding: 15px 35px;
            border-bottom: 1px solid var(--border);
            background: white;
        }

        .topbar-title {
            color: var(--navy);
            font-size: 14px;
            font-weight: 700;
        }

        .industry-content {
            padding: 35px;
        }

        .welcome-title {
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .welcome-description {
            color: var(--muted);
            font-size: 13px;
        }

        /* COMPANY BANNER */

        .company-banner {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border-radius: 17px;
            background: linear-gradient(120deg, #102e59, #1769aa);
            color: white;
        }

        .company-banner::after {
            content: "";
            position: absolute;
            width: 250px;
            height: 250px;
            top: -120px;
            right: -50px;
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 50%;
            box-shadow: 0 0 0 35px rgba(255,255,255,.04);
        }

        .company-content {
            position: relative;
            z-index: 2;
        }

        .company-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 58px;
            height: 58px;
            border-radius: 15px;
            background: var(--gold);
            color: var(--navy);
            font-size: 25px;
        }

        .company-banner h4 {
            margin-top: 15px;
            font-size: 21px;
            font-weight: 800;
        }

        .company-banner p {
            margin: 5px 0 0;
            color: rgba(255,255,255,.75);
            font-size: 12px;
        }

        .verified-label {
            display: inline-block;
            margin-top: 14px;
            padding: 7px 12px;
            border-radius: 20px;
            background: rgba(255,255,255,.15);
            color: white;
            font-size: 10px;
            font-weight: 700;
        }

        /* STATISTICS */

        .stat-card {
            height: 100%;
            padding: 23px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
            transition: .2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(16,46,89,.08);
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            margin-bottom: 16px;
            border-radius: 12px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 20px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }

        .stat-number {
            margin-top: 6px;
            color: var(--navy);
            font-size: 29px;
            font-weight: 800;
        }

        /* PANELS */

        .content-panel {
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .panel-heading {
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 17px;
            font-weight: 800;
        }

        .panel-description {
            color: var(--muted);
            font-size: 12px;
        }

        /* ACTION CARDS */

        .action-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            transition: .2s;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(16,46,89,.07);
        }

        .action-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            margin-bottom: 18px;
            border-radius: 13px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 22px;
        }

        .action-card h5 {
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .action-card p {
            flex-grow: 1;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .action-link {
            display: inline-block;
            margin-top: 12px;
            padding: 11px 15px;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .action-link:hover {
            background: var(--blue);
            color: white;
        }

        /* COMPANY PROFILE */

        .profile-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }

        .profile-row:last-child {
            border-bottom: none;
        }

        .profile-label {
            color: var(--muted);
            font-size: 12px;
        }

        .profile-value {
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
            text-align: right;
        }

        .verification-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            background: #e5f5eb;
            color: #198754;
            font-size: 10px;
            font-weight: 700;
        }

        /* RECRUITMENT INFO */

        .recruitment-info {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            border: 1px solid #dce8f5;
            border-radius: 13px;
            background: #f3f8fe;
        }

        .info-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: #dceafa;
            color: var(--blue);
            font-size: 19px;
        }

        .recruitment-info h6 {
            color: var(--navy);
            font-size: 13px;
            font-weight: 800;
        }

        .recruitment-info p {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        /* RESPONSIVE */

        .mobile-toggle {
            display: none;
        }

        @media (max-width: 991px) {

            .industry-sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .industry-sidebar.show {
                transform: translateX(0);
            }

            .industry-main {
                margin-left: 0;
            }

            .mobile-toggle {
                display: inline-block;
            }

            .industry-content {
                padding: 25px 20px;
            }

            .industry-topbar {
                padding: 15px 20px;
            }

        }

        @media (max-width: 576px) {

            .welcome-title {
                font-size: 21px;
            }

            .industry-content {
                padding: 20px 14px;
            }

            .stat-number {
                font-size: 25px;
            }

            .company-banner {
                padding: 23px;
            }

            .profile-row {
                align-items: flex-start;
            }

        }

    </style>

</head>

<body>

<!-- SIDEBAR -->
<aside class="industry-sidebar" id="industrySidebar">

    <a href="<?= BASE_URL ?>/industry/dashboard.php"
       class="sidebar-brand">

        <span class="sidebar-logo">S</span>

        <span>
            <strong>SIPORSI</strong>
            <small>INDUSTRY PORTAL</small>
        </span>

    </a>

    <div class="sidebar-label">Workspace</div>

    <a href="<?= BASE_URL ?>/industry/dashboard.php"
       class="sidebar-menu active">

        <span class="sidebar-icon">⌂</span>
        Dashboard

    </a>

    <div class="sidebar-label">Talent Discovery</div>

    <a href="<?= htmlspecialchars($portfolioUrl, ENT_QUOTES, 'UTF-8') ?>"
       class="sidebar-menu">

        <span class="sidebar-icon">▤</span>
        Jelajahi Portofolio

    </a>

    <div class="sidebar-label">Komunikasi</div>

    <div class="sidebar-menu"
         style="opacity:.55; cursor:not-allowed;"
         title="Modul pesan rekrutmen belum tersedia">

        <span class="sidebar-icon">✉</span>
        Pesan Rekrutmen
        <span class="ms-auto small">Segera</span>

    </div>

    <div class="sidebar-label">Perusahaan</div>

    <a href="#profil-perusahaan"
       class="sidebar-menu">

        <span class="sidebar-icon">▣</span>
        Profil Perusahaan

    </a>

    <div class="sidebar-label">Lainnya</div>

    <a href="<?= BASE_URL ?>/"
       class="sidebar-menu">

        <span class="sidebar-icon">⌂</span>
        Halaman Publik

    </a>

    <div class="sidebar-bottom">

        <small>Perusahaan</small>

        <div class="fw-bold mt-1">
            <?= $companyName ?>
        </div>

        <small><?= $industryType ?></small>

    </div>

</aside>


<!-- MAIN -->
<div class="industry-main">

    <!-- TOPBAR -->
    <header class="industry-topbar">

        <div class="d-flex align-items-center gap-3">

            <button type="button"
                    class="btn btn-outline-secondary btn-sm mobile-toggle"
                    onclick="toggleSidebar()">
                ☰
            </button>

            <span class="topbar-title">
                SIPORSI / Industry Portal
            </span>

        </div>

        <form action="<?= BASE_URL ?>/logout.php"
              method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <button type="submit"
                    class="btn btn-sm btn-outline-danger">
                Logout
            </button>

        </form>

    </header>


    <!-- CONTENT -->
    <main class="industry-content">

        <!-- WELCOME -->
        <div class="mb-4">

            <h1 class="welcome-title">
                Dashboard Industri
            </h1>

            <p class="welcome-description">
                Selamat datang, <?= $username ?>.
                Temukan potensi dan kompetensi siswa SMK Negeri 4 Banjarmasin.
            </p>

        </div>


        <!-- COMPANY BANNER -->
        <div class="company-banner mb-4">

            <div class="company-content">

                <div class="company-icon">🏢</div>

                <h4>
                    <?= $companyName ?>
                </h4>

                <p>
                    <?= $industryType ?>
                </p>

                <span class="verified-label">
                    ✓ <?= htmlspecialchars($verificationLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>

            </div>

        </div>


        <!-- STATISTICS -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-6">

                <div class="stat-card">

                    <div class="stat-icon">🎓</div>

                    <div class="stat-label">
                        Portofolio Siswa Publik
                    </div>

                    <div class="stat-number">
                        <?= number_format($totalCandidates, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-6">

                <div class="stat-card">

                    <div class="stat-icon">▤</div>

                    <div class="stat-label">
                        Proyek Disetujui
                    </div>

                    <div class="stat-number">
                        <?= number_format($totalApprovedProjects, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- TALENT DISCOVERY -->
        <div class="content-panel mb-4">

            <div class="mb-4">

                <h5 class="panel-heading">
                    Talent Discovery
                </h5>

                <p class="panel-description">
                    Temukan siswa dengan keterampilan dan hasil karya
                    yang sesuai dengan kebutuhan industri.
                </p>

            </div>

            <div class="row g-3">

                <div class="col-lg-6">

                    <div class="action-card">

                        <div class="action-icon">▤</div>

                        <h5>Jelajahi Portofolio Siswa</h5>

                        <p>
                            Lihat profil, keterampilan, proyek,
                            pengalaman, dan prestasi siswa yang
                            telah dipublikasikan.
                        </p>

                        <a href="<?= htmlspecialchars($portfolioUrl, ENT_QUOTES, 'UTF-8') ?>"
                           class="action-link">
                            Jelajahi Portofolio →
                        </a>

                    </div>

                </div>

                <div class="col-lg-6" id="profil-perusahaan">

                    <div class="action-card">

                        <div class="action-icon">🏢</div>

                        <h5>Profil Perusahaan</h5>

                        <p>
                            Informasi perusahaan yang terdaftar
                            sebagai mitra industri SIPORSI.
                        </p>

                        <div class="profile-row">

                            <span class="profile-label">
                                Nama Perusahaan
                            </span>

                            <span class="profile-value">
                                <?= $companyName ?>
                            </span>

                        </div>

                        <div class="profile-row">

                            <span class="profile-label">
                                Bidang Industri
                            </span>

                            <span class="profile-value">
                                <?= $industryType ?>
                            </span>

                        </div>

                        <div class="profile-row">

                            <span class="profile-label">
                                Status Verifikasi
                            </span>

                            <span class="profile-value">

                                <span class="verification-badge">
                                    <?= htmlspecialchars($verificationLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>

                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- RECRUITMENT INFORMATION -->
        <div class="recruitment-info">

            <div class="info-icon">✉</div>

            <div>

                <h6>
                    Fasilitas Pesan Rekrutmen
                </h6>

                <p>
                    Fitur komunikasi antara industri dan siswa
                    merupakan bagian dari pengembangan SIPORSI.
                    Pengelolaan pesan rekrutmen akan tersedia
                    setelah modul tersebut selesai dibuat.
                </p>

            </div>

        </div>


        <!-- FOOTER -->
        <div class="text-center mt-4">

            <small class="text-secondary">
                SIPORSI — Sistem Informasi Portofolio Digital Siswa
                SMK Negeri 4 Banjarmasin
            </small>

        </div>

    </main>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('industrySidebar').classList.toggle('show');
}
</script>

</body>
</html>
