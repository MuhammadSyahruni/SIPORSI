
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();
$csrfToken = Csrf::token();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('ID siswa tidak valid.');
}

// Mengambil data siswa dan akun pengguna
$stmt = $db->prepare("
    SELECT
        s.id,
        s.user_id,
        s.full_name,
        s.nis,
        s.student_status,
        u.account_status
    FROM students s
    INNER JOIN users u ON u.id = s.user_id
    WHERE s.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid.');
    }

    try {

        $db->beginTransaction();

        $updateStudent = $db->prepare("
            UPDATE students
            SET student_status = 'inactive'
            WHERE id = :id
        ");

        $updateStudent->execute(['id' => $id]);

        $updateUser = $db->prepare("
            UPDATE users
            SET account_status = 'inactive'
            WHERE id = :user_id AND role_id = 2
        ");

        $updateUser->execute([
            'user_id' => $student['user_id']
        ]);

        if ($updateUser->rowCount() !== 1) {
            throw new RuntimeException('Akun siswa gagal dinonaktifkan.');
        }

        $db->commit();

        header(
            'Location: ' . BASE_URL .
            '/admin/students/index.php?deactivated=1'
        );

        exit;

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log($e->getMessage());
        $error = 'Siswa gagal dinonaktifkan. Silakan coba kembali.';
    }
}

function escape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

$currentStatus = $student['student_status'] ?? '';

$currentStatusLabels = [
    'active' => 'Aktif',
    'graduated' => 'Lulus',
    'inactive' => 'Tidak Aktif'
];

$currentStatusColors = [
    'active' => 'success',
    'graduated' => 'primary',
    'inactive' => 'secondary'
];

$statusLabel = $currentStatusLabels[$currentStatus] ?? ucfirst($currentStatus);
$statusColor = $currentStatusColors[$currentStatus] ?? 'secondary';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nonaktifkan Siswa | SIPORSI Admin</title>

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
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 75px);
            padding: 35px;
        }

        .confirmation-wrapper {
            width: 100%;
            max-width: 650px;
        }

        /* PAGE HEADER */

        .page-title {
            margin-bottom: 7px;
            color: var(--navy);
            font-size: 24px;
            font-weight: 800;
        }

        .page-description {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        /* CONFIRMATION PANEL */

        .confirmation-card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: 0 12px 35px rgba(16,46,89,.07);
        }

        .confirmation-accent {
            height: 5px;
            background: #dc3545;
        }

        .confirmation-body {
            padding: 30px;
        }

        .warning-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 59px;
            height: 59px;
            margin-bottom: 20px;
            border-radius: 16px;
            background: #fdebed;
            color: #dc3545;
            font-size: 27px;
            font-weight: 800;
        }

        .confirmation-heading {
            margin-bottom: 9px;
            color: #bd2e3c;
            font-size: 21px;
            font-weight: 800;
        }

        .confirmation-description {
            margin-bottom: 24px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        /* STUDENT INFORMATION */

        .student-information {
            padding: 20px;
            border: 1px solid #f2dfb0;
            border-radius: 12px;
            background: #fffaf0;
        }

        .student-information-title {
            margin-bottom: 15px;
            color: #805b0b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .student-info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 9px 0;
            border-bottom: 1px solid rgba(149,107,10,.12);
        }

        .student-info-row:last-child {
            border-bottom: none;
        }

        .student-info-label {
            color: #8a7448;
            font-size: 11px;
        }

        .student-info-value {
            color: #573d08;
            font-size: 12px;
            font-weight: 700;
            text-align: right;
            overflow-wrap: anywhere;
        }

        /* WARNING */

        .warning-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 22px 0;
            padding: 15px;
            border: 1px solid #f1d1d5;
            border-radius: 10px;
            background: #fff6f6;
        }

        .notice-icon {
            color: #dc3545;
            font-size: 19px;
        }

        .warning-notice strong {
            display: block;
            margin-bottom: 5px;
            color: #a82e3a;
            font-size: 11px;
        }

        .warning-notice p {
            margin: 0;
            color: #78545a;
            font-size: 11px;
            line-height: 1.8;
        }

        /* BUTTONS */

        .btn-deactivate {
            min-height: 43px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: #c83e4b;
            color: white;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-deactivate:hover {
            background: #a82e3a;
            color: white;
            transform: translateY(-2px);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
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

        .btn-cancel:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .page-footer {
            margin-top: 22px;
            color: #8996a8;
            font-size: 10px;
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

            .confirmation-body {
                padding: 22px 18px;
            }

            .confirmation-heading {
                font-size: 19px;
            }

            .student-information {
                padding: 15px;
            }

            .confirmation-actions {
                display: flex;
                flex-direction: column;
            }

            .btn-deactivate,
            .btn-cancel {
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
                SIPORSI / Data Siswa / Nonaktifkan
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/students/index.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Data Siswa
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <div class="confirmation-wrapper">

            <!-- PAGE HEADER -->
            <div class="mb-4">

                <h1 class="page-title">
                    Konfirmasi Nonaktifkan Siswa
                </h1>

                <p class="page-description">
                    Periksa kembali identitas siswa sebelum melakukan tindakan ini.
                </p>

            </div>


            <!-- CONFIRMATION CARD -->
            <div class="confirmation-card">

                <div class="confirmation-accent"></div>

                <div class="confirmation-body">

                    <div class="warning-icon">
                        !
                    </div>

                    <h2 class="confirmation-heading">
                        Nonaktifkan Akun Siswa?
                    </h2>

                    <p class="confirmation-description">
                        Apakah kamu yakin ingin menonaktifkan siswa berikut?
                        Pastikan identitasnya sudah benar sebelum melanjutkan.
                    </p>


                    <!-- STUDENT INFORMATION -->
                    <div class="student-information">

                        <div class="student-information-title">
                            Identitas Siswa
                        </div>

                        <div class="student-info-row">

                            <span class="student-info-label">
                                Nama Lengkap
                            </span>

                            <span class="student-info-value">
                                <?= escape($student['full_name']) ?>
                            </span>

                        </div>

                        <div class="student-info-row">

                            <span class="student-info-label">
                                NIS
                            </span>

                            <span class="student-info-value">
                                <?= escape($student['nis']) ?>
                            </span>

                        </div>

                        <div class="student-info-row">

                            <span class="student-info-label">
                                Status Siswa Saat Ini
                            </span>

                            <span class="student-info-value">
                                <?= escape(ucfirst($currentStatusLabels[$student['student_status']] ?? $student['student_status'])) ?>
                            </span>

                        </div>

                    </div>


                    <!-- WARNING NOTICE -->
                    <div class="warning-notice">

                        <div class="notice-icon">ⓘ</div>

                        <div>

                            <strong>Perhatian: Tindakan Ini Akan Menonaktifkan Akun</strong>

                            <p>
                                Akun siswa tidak dapat digunakan untuk login
                                setelah dinonaktifkan. Namun, seluruh data
                                portofolio, proyek, keterampilan, pengalaman,
                                dan riwayat tetap tersimpan di database.
                            </p>

                        </div>

                    </div>


                    <!-- ERROR NOTIFICATION -->
                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger rounded-3 small">

                            <strong>Gagal!</strong>
                            <?= escape($error) ?>

                        </div>

                    <?php endif; ?>


                    <!-- CONFIRMATION FORM -->
                    <form method="POST">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= escape($csrfToken) ?>">

                        <div class="confirmation-actions d-flex flex-wrap gap-2">

                            <button type="submit"
                                    class="btn-deactivate">
                                Ya, Nonaktifkan Siswa
                            </button>

                            <a href="<?= BASE_URL ?>/admin/students/detail.php?id=<?= (int) $id ?>"
                               class="btn-cancel">
                                Batal
                            </a>

                        </div>

                    </form>

                </div>

            </div>


            <!-- FOOTER -->
            <div class="page-footer">

                SIPORSI — Sistem Informasi Portofolio Digital Siswa
                SMK Negeri 4 Banjarmasin

            </div>

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
