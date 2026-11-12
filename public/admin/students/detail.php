
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();

// Validasi ID siswa
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('ID siswa tidak valid.');
}

// Mengambil detail siswa, jurusan, dan akun pengguna
$sql = "
    SELECT
        s.*,
        m.major_name,
        u.username,
        u.email,
        u.account_status
    FROM students s
    INNER JOIN majors m ON m.id = s.major_id
    INNER JOIN users u ON u.id = s.user_id
    WHERE s.id = :id
    LIMIT 1
";

$stmt = $db->prepare($sql);
$stmt->execute(['id' => $id]);

$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

function escape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Label status siswa
$studentStatusLabels = [
    'active' => 'Aktif',
    'graduated' => 'Lulus',
    'inactive' => 'Tidak Aktif'
];

$studentStatusColors = [
    'active' => 'success',
    'graduated' => 'primary',
    'inactive' => 'secondary'
];

$studentStatus = $student['student_status'] ?? '';
$studentStatusLabel = $studentStatusLabels[$studentStatus] ?? ucfirst($studentStatus);
$studentStatusColor = $studentStatusColors[$studentStatus] ?? 'secondary';

// Label status akun
$accountStatusLabels = [
    'active' => 'Aktif',
    'inactive' => 'Tidak Aktif',
    'suspended' => 'Ditangguhkan'
];

$accountStatus = $student['account_status'] ?? '';
$accountStatusLabel = $accountStatusLabels[$accountStatus] ?? ucfirst($accountStatus);

$accountStatusColor = $accountStatus === 'active'
    ? 'success'
    : ($accountStatus === 'suspended' ? 'danger' : 'secondary');

$studentInitial = strtoupper(
    substr($student['full_name'] ?? 'S', 0, 1)
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Siswa | SIPORSI Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="<?= BASE_URL ?>/assets/css/style.css"
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

        /* MAIN */

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
            max-width: 1200px;
            margin: 0 auto;
            padding: 35px;
        }

        /* PAGE HEADER */

        .page-title {
            margin-bottom: 7px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .page-description {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 13px;
        }

        /* PROFILE BANNER */

        .student-banner {
            position: relative;
            overflow: hidden;
            padding: 27px;
            border-radius: 16px;
            background: linear-gradient(120deg, #102e59, #1769aa);
            color: white;
        }

        .student-banner::after {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            top: -115px;
            right: -55px;
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 50%;
            box-shadow: 0 0 0 35px rgba(255,255,255,.04);
        }

        .student-banner-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .student-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 65px;
            height: 65px;
            border: 3px solid rgba(255,255,255,.25);
            border-radius: 17px;
            background: var(--gold);
            color: var(--navy);
            font-size: 27px;
            font-weight: 800;
        }

        .student-banner h4 {
            margin-bottom: 5px;
            font-size: 21px;
            font-weight: 800;
        }

        .student-banner p {
            margin: 0;
            color: rgba(255,255,255,.75);
            font-size: 12px;
        }

        .student-banner .badge {
            margin-top: 10px;
            font-size: 10px;
        }

        /* DETAIL PANEL */

        .detail-panel {
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .panel-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .panel-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 39px;
            height: 39px;
            border-radius: 10px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 18px;
        }

        /* DETAIL ROWS */

        .detail-row {
            display: grid;
            grid-template-columns: minmax(150px, 30%) 1fr;
            gap: 15px;
            padding: 14px 9px;
            border-bottom: 1px solid var(--border);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }

        .detail-value {
            color: var(--navy);
            font-size: 12px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .biography-value {
            font-weight: 400;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .detail-divider {
            margin: 25px 0;
            border: 0;
            border-top: 1px solid var(--border);
        }

        /* STATUS */

        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        /* ACTION BUTTONS */

        .btn-edit {
            min-height: 42px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: var(--gold);
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-edit:hover {
            background: #ffd16d;
            color: var(--navy);
            transform: translateY(-2px);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 18px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
        }

        .btn-back:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        /* FOOTER */

        .page-footer {
            margin-top: 25px;
            color: #8996a8;
            font-size: 11px;
            text-align: center;
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

            .admin-topbar {
                padding: 15px 20px;
            }

            .admin-content {
                padding: 25px 20px;
            }

        }

        @media (max-width: 576px) {

            .admin-content {
                padding: 20px 14px;
            }

            .page-title {
                font-size: 21px;
            }

            .detail-panel {
                padding: 17px;
            }

            .student-banner {
                padding: 20px;
            }

            .student-banner-content {
                align-items: flex-start;
                gap: 13px;
            }

            .student-avatar {
                width: 52px;
                height: 52px;
                font-size: 22px;
            }

            .student-banner h4 {
                font-size: 17px;
            }

            .detail-row {
                grid-template-columns: 1fr;
                gap: 5px;
                padding: 12px 5px;
            }

            .detail-label,
            .detail-value {
                font-size: 11px;
            }

            .action-buttons {
                display: flex;
                flex-direction: column;
            }

            .btn-edit,
            .btn-back {
                width: 100%;
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

    <a href="<?= BASE_URL ?>/admin/dashboard.php"
       class="sidebar-menu">

        <span class="sidebar-icon">⌂</span>
        Dashboard

    </a>

    <a href="<?= BASE_URL ?>/admin/students/index.php"
       class="sidebar-menu active">

        <span class="sidebar-icon">♙</span>
        Data Siswa

    </a>

    <a href="<?= BASE_URL ?>/admin/majors/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▦</span>
        Data Jurusan

    </a>

    <a href="<?= BASE_URL ?>/admin/industries/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▣</span>
        Data Industri

    </a>

    <a href="<?= BASE_URL ?>/admin/skills/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">✦</span>
        Data Keterampilan

    </a>

    <a href="<?= BASE_URL ?>/admin/projects/index.php"
       class="sidebar-menu">

        <span class="sidebar-icon">▤</span>
        Manajemen Proyek

    </a>

    <a href="<?= BASE_URL ?>/admin/certificates/index.php"
    class="sidebar-menu">

        <span class="sidebar-icon">▧</span>
        Verifikasi Sertifikat

    </a>

    <div class="sidebar-label">Lainnya</div>

    <a href="<?= BASE_URL ?>/"
       class="sidebar-menu">

        <span class="sidebar-icon">⌂</span>
        Halaman Publik

    </a>

    <div class="sidebar-bottom">

        <small>Area Pengelolaan</small>

        <div class="fw-bold mt-1">
            Administrator
        </div>

        <small>SIPORSI Sekolah</small>

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
                SIPORSI / Data Siswa / Detail
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/students/index.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Data Siswa
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h1 class="page-title">
                Detail Siswa
            </h1>

            <p class="page-description">
                Informasi lengkap profil siswa SIPORSI.
            </p>

        </div>


        <!-- STUDENT PROFILE BANNER -->
        <div class="student-banner mb-4">

            <div class="student-banner-content">

                <div class="student-avatar">
                    <?= escape($studentInitial) ?>
                </div>

                <div>

                    <h4>
                        <?= escape($student['full_name']) ?>
                    </h4>

                    <p>
                        <?= escape($student['major_name']) ?>
                    </p>

                    <span class="badge bg-light text-dark">
                        <?= escape($student['class_level']) ?>
                    </span>

                    <span class="badge bg-light text-dark">
                        NIS: <?= escape($student['nis']) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- IDENTITAS SISWA -->
        <div class="detail-panel mb-4">

            <div class="panel-heading">

                <span class="panel-icon">♙</span>

                Identitas Siswa

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    NIS
                </div>

                <div class="detail-value">
                    <?= escape($student['nis']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    NISN
                </div>

                <div class="detail-value">
                    <?= escape($student['nisn']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Nama Lengkap
                </div>

                <div class="detail-value">
                    <?= escape($student['full_name']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Jenis Kelamin
                </div>

                <div class="detail-value">

                    <?php if ($student['gender'] === 'L'): ?>

                        Laki-laki

                    <?php elseif ($student['gender'] === 'P'): ?>

                        Perempuan

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Jurusan
                </div>

                <div class="detail-value">
                    <?= escape($student['major_name']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Kelas
                </div>

                <div class="detail-value">
                    <?= escape($student['class_level']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Tahun Masuk
                </div>

                <div class="detail-value">
                    <?= escape($student['enrollment_year']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Tahun Lulus
                </div>

                <div class="detail-value">
                    <?= escape($student['graduation_year']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Status Siswa
                </div>

                <div class="detail-value">

                    <span class="status-badge bg-<?= escape($studentStatusColor) ?>">
                        <?= escape($studentStatusLabel) ?>
                    </span>

                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Biografi Singkat
                </div>

                <div class="detail-value">
                    <?= escape($student['short_bio']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Biografi Lengkap
                </div>

                <div class="detail-value biography-value">
                    <?= escape($student['biography']) ?>
                </div>

            </div>

        </div>


        <!-- INFORMASI AKUN -->
        <div class="detail-panel mb-4">

            <div class="panel-heading">

                <span class="panel-icon">🔐</span>

                Informasi Akun

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Username
                </div>

                <div class="detail-value">
                    <?= escape($student['username']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Email
                </div>

                <div class="detail-value">
                    <?= escape($student['email']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Status Akun
                </div>

                <div class="detail-value">

                    <span class="status-badge bg-<?= escape($accountStatusColor) ?>">
                        <?= escape($accountStatusLabel) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- ACTION BUTTONS -->
        <div class="d-flex flex-wrap gap-2 action-buttons">

            <a href="<?= BASE_URL ?>/admin/students/index.php"
               class="btn-back">
                ← Kembali ke Daftar Siswa
            </a>

            <a href="<?= BASE_URL ?>/admin/students/edit.php?id=<?= (int) $student['id'] ?>"
               class="btn-edit">
                Edit Data Siswa
            </a>

        </div>


        <!-- FOOTER -->
        <div class="page-footer">

            SIPORSI — Sistem Informasi Portofolio Digital Siswa
            SMK Negeri 4 Banjarmasin

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
