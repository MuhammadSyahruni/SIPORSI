
<?php

require_once __DIR__ . '/../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';
require_once APP_ROOT . '/app/core/Database.php';

AuthMiddleware::requireRole(['student']);

$csrfToken = Csrf::token();
$user = $_SESSION['user'];

$db = Database::connection();

// Mengambil data siswa berdasarkan akun yang sedang login.
$studentStmt = $db->prepare("
    SELECT id, full_name, public_slug, is_public, student_status
    FROM students
    WHERE user_id = :user_id
    LIMIT 1
");

$studentStmt->execute([
    'user_id' => $user['id']
]);

$student = $studentStmt->fetch();

$studentId = $student ? (int) $student['id'] : 0;

// Statistik portofolio milik siswa.
$totalSkills = 0;
$totalProjects = 0;
$totalExperiences = 0;
$totalAchievements = 0;
$totalCertificates = 0;
$approvedProjects = 0;
$pendingProjects = 0;

if ($studentId > 0) {

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM student_skills
        WHERE student_id = :student_id
    ");

    $stmt->execute(['student_id' => $studentId]);
    $totalSkills = (int) $stmt->fetchColumn();

    // Menghitung seluruh sertifikat milik siswa.
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM certificates
        WHERE student_id = :student_id
    ");

    $stmt->execute(['student_id' => $studentId]);
    $totalCertificates = (int) $stmt->fetchColumn();


    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM projects
        WHERE student_id = :student_id
    ");

    $stmt->execute(['student_id' => $studentId]);
    $totalProjects = (int) $stmt->fetchColumn();


    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM experiences
        WHERE student_id = :student_id
    ");

    $stmt->execute(['student_id' => $studentId]);
    $totalExperiences = (int) $stmt->fetchColumn();


    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM achievements
        WHERE student_id = :student_id
    ");

    $stmt->execute(['student_id' => $studentId]);
    $totalAchievements = (int) $stmt->fetchColumn();


    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN publication_status = 'approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN publication_status = 'pending' THEN 1 ELSE 0 END) AS pending
        FROM projects
        WHERE student_id = :student_id
    ");

    $stmt->execute(['student_id' => $studentId]);

    $projectStats = $stmt->fetch();

    $approvedProjects = (int) ($projectStats['approved'] ?? 0);
    $pendingProjects = (int) ($projectStats['pending'] ?? 0);
}

// Informasi akun.
$username = htmlspecialchars(
    $user['username'] ?? 'Siswa',
    ENT_QUOTES,
    'UTF-8'
);

$studentName = htmlspecialchars(
    $student['full_name'] ?? $user['username'] ?? 'Siswa',
    ENT_QUOTES,
    'UTF-8'
);

$studentInitial = htmlspecialchars(
    mb_strtoupper(
        mb_substr(
            $student['full_name'] ?? $user['username'] ?? 'S',
            0,
            1,
            'UTF-8'
        ),
        'UTF-8'
    ),
    ENT_QUOTES,
    'UTF-8'
);

// URL portofolio publik.
$portfolioUrl = null;

if ($student && !empty($student['public_slug'])) {
    $portfolioUrl = BASE_URL . '/portfolio.php?slug=' .
        rawurlencode($student['public_slug']);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Siswa | SIPORSI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

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

        .student-sidebar {
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

        /* MAIN */

        .student-main {
            min-height: 100vh;
            margin-left: 255px;
        }

        .student-topbar {
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

        .student-content {
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

        /* PROFILE BANNER */

        .profile-banner {
            position: relative;
            overflow: hidden;
            padding: 28px;
            border-radius: 16px;
            background: linear-gradient(120deg, #102e59, #1769aa);
            color: white;
        }

        .profile-banner h4 {
            font-size: 20px;
            font-weight: 800;
        }

        .profile-banner p {
            margin-bottom: 0;
            color: rgba(255,255,255,.75);
            font-size: 12px;
        }

        .profile-status {
            display: inline-block;
            margin-top: 13px;
            padding: 6px 11px;
            border-radius: 20px;
            background: rgba(255,255,255,.15);
            color: white;
            font-size: 10px;
            font-weight: 600;
        }

        .profile-symbol {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: var(--gold);
            color: var(--navy);
            font-size: 26px;
            font-weight: 800;
        }

        /* STATISTICS */

        .stat-card {
            height: 100%;
            padding: 22px;
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
            margin-bottom: 16px;
            border-radius: 12px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 19px;
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

        /* PANEL */

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

        /* MODULE */

        .module-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
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

        /* PORTFOLIO STATUS */

        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }

        .status-row:last-child {
            border-bottom: none;
        }

        .status-label {
            color: var(--muted);
        }

        .status-value {
            color: var(--navy);
            font-weight: 700;
        }

        /* RESPONSIVE */

        .mobile-toggle {
            display: none;
        }

        @media (max-width: 991px) {

            .student-sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .student-sidebar.show {
                transform: translateX(0);
            }

            .student-main {
                margin-left: 0;
            }

            .mobile-toggle {
                display: inline-block;
            }

            .student-content {
                padding: 25px 20px;
            }

            .student-topbar {
                padding: 15px 20px;
            }

        }

        @media (max-width: 576px) {

            .welcome-title {
                font-size: 21px;
            }

            .student-content {
                padding: 20px 14px;
            }

            .stat-number {
                font-size: 25px;
            }

            .profile-banner {
                padding: 22px;
            }

        }

    </style>

</head>

<body>


<!-- SIDEBAR - DISELARASKAN DENGAN KETERAMPILAN -->
<!-- SIDEBAR -->
<aside class="student-sidebar" id="studentSidebar">

    <a href="<?= BASE_URL ?>/student/dashboard.php"
       class="sidebar-brand">

        <span class="sidebar-logo">S</span>

        <span>
            <strong>SIPORSI</strong>
            <small>STUDENT PANEL</small>
        </span>

    </a>


    <div class="sidebar-label">
        Menu Utama
    </div>


    <a href="<?= BASE_URL ?>/student/dashboard.php"
       class="sidebar-menu active">

        <span class="sidebar-icon">⌂</span>
        Dashboard

    </a>


    <div class="sidebar-label">
        Portofolio Saya
    </div>


    <a href="<?= BASE_URL ?>/student/skills/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">✦</span>
        Keterampilan

    </a>


    <a href="<?= BASE_URL ?>/student/projects/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▤</span>
        Proyek dan Karya

    </a>


    <a href="<?= BASE_URL ?>/student/experiences/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▣</span>
        Pengalaman PKL

    </a>


    <a href="<?= BASE_URL ?>/student/achievements/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">★</span>
        Prestasi

    </a>
    <a href="<?= BASE_URL ?>/student/certificates/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▧</span>
        Sertifikat

    </a>

    <div class="sidebar-label">
        Lainnya
    </div>


    <a href="<?= BASE_URL ?>/"
       class="sidebar-menu">

        <span class="sidebar-icon">◉</span>
        Halaman Publik

    </a>


    <div class="sidebar-bottom">

        <small>Area Pengguna</small>

        <div class="fw-bold mt-1">
            Siswa SIPORSI
        </div>

        <small>SMK Negeri 4 Banjarmasin</small>

    </div>

</aside>


<!-- MAIN -->
<div class="student-main">


    <!-- TOPBAR -->
    <header class="student-topbar">

        <div class="d-flex align-items-center gap-3">

            <button type="button"
                    class="btn btn-outline-secondary btn-sm mobile-toggle"
                    onclick="toggleSidebar()">

                ☰

            </button>

            <span class="topbar-title">
                SIPORSI / Dashboard Siswa
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
    <main class="student-content">


        <!-- WELCOME -->
        <div class="mb-4">

            <h1 class="welcome-title">
                Dashboard Siswa
            </h1>

            <p class="welcome-description">

                Selamat datang, <?= $username ?>.
                Kelola portofolio digital dan kembangkan potensi dirimu.

            </p>

        </div>


        <?php if (!$student): ?>


            <div class="alert alert-warning">

                Data profil siswa belum ditemukan.
                Silakan hubungi Admin Sekolah untuk pemeriksaan akun.

            </div>


        <?php else: ?>


            <!-- PROFILE BANNER -->
            <div class="profile-banner mb-4">


                <div class="row align-items-center g-3">


                    <div class="col-auto">

                        <div class="profile-symbol">
                            <?= $studentInitial ?>
                        </div>

                    </div>


                    <div class="col">


                        <h4>
                            Halo, <?= $studentName ?>!
                        </h4>


                        <p>
                            Selamat mengembangkan portofolio digitalmu
                            bersama SIPORSI.
                        </p>


                        <span class="profile-status">

                            <?php if ((int) $student['is_public'] === 1): ?>

                                Portofolio Publik Aktif

                            <?php else: ?>

                                Portofolio Belum Dipublikasikan

                            <?php endif; ?>

                        </span>


                    </div>


                    <?php if ($portfolioUrl && (int) $student['is_public'] === 1): ?>


                        <div class="col-12 col-lg-auto">


                            <a href="<?= htmlspecialchars($portfolioUrl, ENT_QUOTES, 'UTF-8') ?>"
                               target="_blank"
                               rel="noopener"
                               class="btn btn-warning btn-sm fw-bold">

                                Lihat Portofolio →

                            </a>


                        </div>


                    <?php endif; ?>


                </div>


            </div>


            <!-- STATISTIK -->
            <div class="row g-3 mb-4">


                <div class="col-6 col-md-4 col-xl">

                    <div class="stat-card">

                        <div class="stat-icon">✦</div>

                        <div class="stat-label">
                            Keterampilan
                        </div>

                        <div class="stat-number">
                            <?= number_format($totalSkills, 0, ',', '.') ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-md-4 col-xl">

                    <div class="stat-card">

                        <div class="stat-icon">▤</div>

                        <div class="stat-label">
                            Proyek dan Karya
                        </div>

                        <div class="stat-number">
                            <?= number_format($totalProjects, 0, ',', '.') ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-md-4 col-xl">

                    <div class="stat-card">

                        <div class="stat-icon">▣</div>

                        <div class="stat-label">
                            Pengalaman PKL
                        </div>

                        <div class="stat-number">
                            <?= number_format($totalExperiences, 0, ',', '.') ?>
                        </div>

                    </div>

                </div>


                <div class="col-6 col-md-4 col-xl">

                    <div class="stat-card">

                        <div class="stat-icon">🏆</div>

                        <div class="stat-label">
                            Prestasi
                        </div>

                        <div class="stat-number">
                            <?= number_format($totalAchievements, 0, ',', '.') ?>
                        </div>

                    </div>

                </div>

                <div class="col-6 col-md-4 col-xl">

                    <div class="stat-card">

                        <div class="stat-icon">▧</div>

                        <div class="stat-label">
                            Sertifikat
                        </div>

                        <div class="stat-number">
                            <?= number_format($totalCertificates, 0, ',', '.') ?>
                        </div>

                    </div>

                </div>


            </div>


            <!-- KELOLA PORTOFOLIO -->
            <div class="content-panel mb-4">


                <div class="mb-4">

                    <h5 class="panel-heading">
                        Kelola Portofolio
                    </h5>

                    <p class="panel-description">
                        Pilih bagian portofolio yang ingin kamu kelola.
                    </p>

                </div>


                <div class="row g-3">


                    <div class="col-md-6 col-xl-4">

                        <a href="<?= BASE_URL ?>/student/skills/index.php"
                           class="module-card">

                            <div class="module-icon">✦</div>

                            <div>
                                <h6>Keterampilan</h6>
                                <p>Kelola kompetensi dan tingkat penguasaanmu.</p>
                            </div>

                        </a>

                    </div>


                    <div class="col-md-6 col-xl-4">

                        <a href="<?= BASE_URL ?>/student/projects/index.php"
                           class="module-card">

                            <div class="module-icon">▤</div>

                            <div>
                                <h6>Proyek dan Karya</h6>
                                <p>Tambahkan dan kelola hasil karya terbaikmu.</p>
                            </div>

                        </a>

                    </div>


                    <div class="col-md-6 col-xl-4">

                        <a href="<?= BASE_URL ?>/student/experiences/index.php"
                           class="module-card">

                            <div class="module-icon">▣</div>

                            <div>
                                <h6>Pengalaman PKL</h6>
                                <p>Dokumentasikan pengalaman praktik kerjamu.</p>
                            </div>

                        </a>

                    </div>


                    <div class="col-md-6 col-xl-4">

                        <a href="<?= BASE_URL ?>/student/achievements/index.php"
                           class="module-card">

                            <div class="module-icon">🏆</div>

                            <div>
                                <h6>Prestasi</h6>
                                <p>Catat penghargaan dan pencapaianmu.</p>
                            </div>

                        </a>

                    </div>

                    <div class="col-md-6 col-xl-4">

                        <a href="<?= BASE_URL ?>/student/certificates/index.php"
                        class="module-card">

                            <div class="module-icon">▧</div>

                            <div>
                                <h6>Sertifikat</h6>
                                <p>Unggah, kelola, dan pantau verifikasi sertifikatmu.</p>
                            </div>

                        </a>

                    </div>


                </div>


            </div>


            <!-- STATUS PORTOFOLIO -->
            <div class="content-panel">


                <h5 class="panel-heading">
                    Status Portofolio
                </h5>


                <p class="panel-description">
                    Ringkasan status karya dan publikasi portofoliomu.
                </p>


                <div class="status-row">

                    <span class="status-label">
                        Proyek disetujui
                    </span>

                    <span class="status-value">
                        <?= number_format($approvedProjects, 0, ',', '.') ?> proyek
                    </span>

                </div>


                <div class="status-row">

                    <span class="status-label">
                        Proyek menunggu verifikasi
                    </span>

                    <span class="status-value">
                        <?= number_format($pendingProjects, 0, ',', '.') ?> proyek
                    </span>

                </div>


                <div class="status-row">

                    <span class="status-label">
                        Status publikasi portofolio
                    </span>

                    <span class="status-value">

                        <?php if ((int) $student['is_public'] === 1): ?>

                            <span class="text-success">Aktif</span>

                        <?php else: ?>

                            <span class="text-secondary">Belum aktif</span>

                        <?php endif; ?>

                    </span>

                </div>


            </div>


        <?php endif; ?>


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

// Sidebar untuk tampilan mobile.
function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
}

</script>

</body>
</html>
