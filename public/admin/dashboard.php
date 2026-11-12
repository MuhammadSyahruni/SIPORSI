<?php

require_once __DIR__ . '/../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';
require_once APP_ROOT . '/app/core/Database.php';

// Hanya admin yang boleh masuk
AuthMiddleware::requireRole(['admin']);

$csrfToken = Csrf::token();
$user = $_SESSION['user'];

$db = Database::connection();

// Mengambil statistik langsung dari database
$totalStudents = (int) $db->query("
    SELECT COUNT(*) FROM students
")->fetchColumn();

$totalIndustries = (int) $db->query("
    SELECT COUNT(*) FROM industries
")->fetchColumn();

$totalProjects = (int) $db->query("
    SELECT COUNT(*) FROM projects
")->fetchColumn();

$totalMajors = (int) $db->query("
    SELECT COUNT(*) FROM majors
")->fetchColumn();

$pendingIndustries = (int) $db->query("
    SELECT COUNT(*)
    FROM industries
    WHERE verification_status = 'pending'
")->fetchColumn();

$pendingProjects = (int) $db->query("
    SELECT COUNT(*)
    FROM projects
    WHERE publication_status = 'pending'
")->fetchColumn();


// Statistik sertifikat siswa.
$certificateStats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(
            CASE WHEN verification_status = 'pending'
            THEN 1 ELSE 0 END
        ) AS pending,
        SUM(
            CASE WHEN verification_status = 'verified'
            THEN 1 ELSE 0 END
        ) AS verified,
        SUM(
            CASE WHEN verification_status = 'rejected'
            THEN 1 ELSE 0 END
        ) AS rejected
    FROM certificates
")->fetch(PDO::FETCH_ASSOC);

$totalCertificates = (int) ($certificateStats['total'] ?? 0);
$pendingCertificates = (int) ($certificateStats['pending'] ?? 0);
$verifiedCertificates = (int) ($certificateStats['verified'] ?? 0);
$rejectedCertificates = (int) ($certificateStats['rejected'] ?? 0);


// Total seluruh pekerjaan yang menunggu pemeriksaan.
$totalPending = $pendingIndustries
    + $pendingProjects
    + $pendingCertificates;

$safeUsername = htmlspecialchars(
    $user['username'] ?? 'Administrator',
    ENT_QUOTES,
    'UTF-8'
);

$menus = [
    [
        'label' => 'Dashboard',
        'icon' => '⌂',
        'url' => BASE_URL . '/admin/dashboard.php',
        'active' => true
    ],
    [
        'label' => 'Data Siswa',
        'icon' => '♙',
        'url' => BASE_URL . '/admin/students/index.php'
    ],
    [
        'label' => 'Data Jurusan',
        'icon' => '▦',
        'url' => BASE_URL . '/admin/majors/index.php'
    ],
    [
        'label' => 'Data Industri',
        'icon' => '▣',
        'url' => BASE_URL . '/admin/industries/index.php'
    ],
    [
        'label' => 'Data Keterampilan',
        'icon' => '✦',
        'url' => BASE_URL . '/admin/skills/index.php'
    ],
    [
        'label' => 'Manajemen Proyek',
        'icon' => '▤',
        'url' => BASE_URL . '/admin/projects/index.php'
    ],
    [
        'label' => 'Verifikasi Sertifikat',
        'icon' => '▧',
        'url' => BASE_URL . '/admin/certificates/index.php'
    ],
];

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin | SIPORSI</title>

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

        .admin-sidebar {
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
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: var(--gold);
            color: var(--navy);
            font-size: 19px;
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
            margin: 17px 10px 10px;
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

        .admin-main {
            min-height: 100vh;
            margin-left: 255px;
        }

        .admin-topbar {
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

        .admin-content {
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

        /* STATISTICS */

        .stat-card {
            position: relative;
            height: 100%;
            padding: 23px;
            overflow: hidden;
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
            width: 43px;
            height: 43px;
            margin-bottom: 17px;
            border-radius: 12px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 20px;
        }

        /* STATUS SERTIFIKAT */

        .stat-icon.status-pending {
            background: #fff4d9;
            color: #956b0a;
        }

        .stat-icon.status-verified {
            background: #e5f6ed;
            color: #198754;
        }

        .stat-icon.status-rejected {
            background: #fdebed;
            color: #c23c49;
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

        /* MANAGEMENT */

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

        .module-card {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            height: 100%;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: inherit;
            text-decoration: none;
            transition: .2s;
        }

        .module-card:hover {
            border-color: #b9d2ed;
            background: #f8fbff;
            transform: translateY(-3px);
        }

        .module-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 43px;
            height: 43px;
            border-radius: 11px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 19px;
        }

        .module-card h6 {
            margin-bottom: 6px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 800;
        }

        .module-card p {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .pending-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 18px;
            border: 1px solid #f2dfb0;
            border-radius: 12px;
            background: #fffaf0;
        }

        .pending-box h6 {
            margin-bottom: 5px;
            color: #805b0b;
            font-weight: 800;
        }

        .pending-box p {
            margin: 0;
            color: #8a7448;
            font-size: 11px;
        }

        .pending-count {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 43px;
            height: 43px;
            border-radius: 12px;
            background: #f4b942;
            color: #573d08;
            font-size: 17px;
            font-weight: 800;
        }

        /* RESPONSIVE */

        .mobile-toggle {
            display: none;
        }

        @media (max-width: 991px) {

            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-main {
                margin-left: 0;
            }

            .mobile-toggle {
                display: inline-block;
            }

            .admin-content {
                padding: 25px 20px;
            }

            .admin-topbar {
                padding: 15px 20px;
            }

        }

        @media (max-width: 576px) {

            .welcome-title {
                font-size: 21px;
            }

            .admin-content {
                padding: 20px 14px;
            }

            .stat-number {
                font-size: 25px;
            }

        }

    </style>

</head>

<body>

<!-- SIDEBAR -->
<aside class="admin-sidebar" id="adminSidebar">

    <a href="<?= BASE_URL ?>/admin/dashboard.php"
       class="sidebar-brand">

        <span class="sidebar-logo">S</span>

        <span>
            <strong>SIPORSI</strong>
            <small>ADMINISTRATOR PANEL</small>
        </span>

    </a>

    <div class="sidebar-label">Menu Utama</div>

    <?php foreach ($menus as $menu): ?>

        <a href="<?= htmlspecialchars($menu['url'], ENT_QUOTES, 'UTF-8') ?>"
           class="sidebar-menu <?= !empty($menu['active']) ? 'active' : '' ?>">

            <span class="sidebar-icon">
                <?= $menu['icon'] ?>
            </span>

            <?= htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') ?>

        </a>

    <?php endforeach; ?>

    <div class="sidebar-label">Lainnya</div>

    <a href="<?= BASE_URL ?>/"
       class="sidebar-menu">

        <span class="sidebar-icon">⌂</span>
        Halaman Publik

    </a>

    <div class="sidebar-bottom">

        <small>Login sebagai</small>

        <div class="fw-bold mt-1">
            <?= $safeUsername ?>
        </div>

        <small>Administrator SIPORSI</small>

    </div>

</aside>


<!-- MAIN -->
<div class="admin-main">

    <!-- TOPBAR -->
    <header class="admin-topbar">

        <div class="d-flex align-items-center gap-3">

            <button type="button"
                    class="btn btn-outline-secondary btn-sm mobile-toggle"
                    onclick="toggleSidebar()">
                ☰
            </button>

            <span class="topbar-title">
                SIPORSI / Dashboard Admin
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
    <main class="admin-content">

        <!-- WELCOME -->
        <div class="mb-4">

            <h1 class="welcome-title">
                Dashboard Admin
            </h1>

            <p class="welcome-description">
                Selamat datang, <?= $safeUsername ?>.
                Kelola seluruh informasi dan aktivitas SIPORSI
                melalui halaman administrator.
            </p>

        </div>


        <!-- STATISTIK -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-icon">♙</div>

                    <div class="stat-label">
                        Total Siswa
                    </div>

                    <div class="stat-number">
                        <?= number_format($totalStudents, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-icon">▣</div>

                    <div class="stat-label">
                        Mitra Industri
                    </div>

                    <div class="stat-number">
                        <?= number_format($totalIndustries, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-icon">▤</div>

                    <div class="stat-label">
                        Total Proyek
                    </div>

                    <div class="stat-number">
                        <?= number_format($totalProjects, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-icon">▦</div>

                    <div class="stat-label">
                        Total Jurusan
                    </div>

                    <div class="stat-number">
                        <?= number_format($totalMajors, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

        </div>

        <!-- STATISTIK SERTIFIKAT -->

<div class="mb-3">

    <h5 class="panel-heading">
        Ringkasan Sertifikat
    </h5>

    <p class="panel-description">
        Pantau perkembangan pemeriksaan sertifikat siswa.
    </p>

</div>

<div class="row g-3 mb-4">

    <!-- MENUNGGU VERIFIKASI -->

    <div class="col-md-4">

        <div class="stat-card">

            <div class="stat-icon status-pending">
                ◷
            </div>

            <div class="stat-label">
                Menunggu Verifikasi
            </div>

            <div class="stat-number">
                <?= number_format($pendingCertificates, 0, ',', '.') ?>
            </div>

        </div>

    </div>


    <!-- TERVERIFIKASI -->

    <div class="col-md-4">

        <div class="stat-card">

            <div class="stat-icon status-verified">
                ✓
            </div>

            <div class="stat-label">
                Terverifikasi
            </div>

            <div class="stat-number">
                <?= number_format($verifiedCertificates, 0, ',', '.') ?>
            </div>

        </div>

    </div>


        <!-- DITOLAK -->

        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-icon status-rejected">
                    !
                </div>

                <div class="stat-label">
                    Perlu Perbaikan
                </div>

                <div class="stat-number">
                    <?= number_format($rejectedCertificates, 0, ',', '.') ?>
                </div>

            </div>

        </div>

    </div>
        <!-- VERIFIKASI -->
        <div class="pending-box mb-4">

            <div>

                <h6>Menunggu Verifikasi</h6>

                <p>
                    Terdapat <?= number_format($pendingIndustries, 0, ',', '.') ?>
                    industri,
                    <?= number_format($pendingProjects, 0, ',', '.') ?>
                    proyek, dan
                    <?= number_format($pendingCertificates, 0, ',', '.') ?>
                    sertifikat yang menunggu pemeriksaan.
                </p>

            </div>

            <div class="d-flex align-items-center gap-3">

                <span class="pending-count">
                    <?= number_format($totalPending, 0, ',', '.') ?>
                </span>

                <a href="<?= BASE_URL ?>/admin/projects/index.php"
                   class="btn btn-sm btn-outline-warning">
                    Periksa Proyek
                </a>

                <a href="<?= BASE_URL ?>/admin/industries/index.php"
                   class="btn btn-sm btn-outline-warning">
                    Periksa Industri
                </a>
                <a href="<?= BASE_URL ?>/admin/certificates/index.php"
                   class="btn btn-sm btn-outline-warning">
                    Periksa Sertifikat
                </a>

            </div>

        </div>


        <!-- MANAJEMEN -->
        <div class="content-panel">

            <div class="mb-4">

                <h5 class="panel-heading">
                    Manajemen Sistem
                </h5>

                <p class="panel-description">
                    Pilih modul yang ingin kamu kelola.
                </p>

            </div>

            <div class="row g-3">

                <div class="col-md-6 col-xl-4">

                    <a href="<?= BASE_URL ?>/admin/students/index.php"
                       class="module-card">

                        <div class="module-icon">♙</div>

                        <div>
                            <h6>Manajemen Siswa</h6>
                            <p>Mengelola data, profil, dan status siswa.</p>
                        </div>

                    </a>

                </div>


                <div class="col-md-6 col-xl-4">

                    <a href="<?= BASE_URL ?>/admin/majors/index.php"
                       class="module-card">

                        <div class="module-icon">▦</div>

                        <div>
                            <h6>Manajemen Jurusan</h6>
                            <p>Mengelola tujuh jurusan resmi sekolah.</p>
                        </div>

                    </a>

                </div>


                <div class="col-md-6 col-xl-4">

                    <a href="<?= BASE_URL ?>/admin/industries/index.php"
                       class="module-card">

                        <div class="module-icon">▣</div>

                        <div>
                            <h6>Manajemen Industri</h6>
                            <p>Mengelola akun dan verifikasi mitra industri.</p>
                        </div>

                    </a>

                </div>


                <div class="col-md-6 col-xl-4">

                    <a href="<?= BASE_URL ?>/admin/skills/index.php"
                       class="module-card">

                        <div class="module-icon">✦</div>

                        <div>
                            <h6>Manajemen Keterampilan</h6>
                            <p>Mengelola daftar kompetensi siswa.</p>
                        </div>

                    </a>

                </div>


                <div class="col-md-6 col-xl-4">

                    <a href="<?= BASE_URL ?>/admin/projects/index.php"
                       class="module-card">

                        <div class="module-icon">▤</div>

                        <div>
                            <h6>Manajemen Proyek</h6>
                            <p>Memeriksa dan memvalidasi hasil karya siswa.</p>
                        </div>

                    </a>

                </div>

                <div class="col-md-6 col-xl-4">

                    <a href="<?= BASE_URL ?>/admin/certificates/index.php"
                    class="module-card">

                        <div class="module-icon">▧</div>

                        <div>
                            <h6>Verifikasi Sertifikat</h6>
                            <p>Memeriksa, menyetujui, dan menolak sertifikat siswa.</p>
                        </div>

                    </a>

                </div>

            </div>

        </div>


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
    document.getElementById('adminSidebar').classList.toggle('show');
}
</script>

</body>
</html>
