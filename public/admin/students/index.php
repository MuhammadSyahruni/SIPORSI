
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();

// Pencarian siswa
$keyword = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        s.id,
        s.nis,
        s.full_name,
        s.class_level,
        s.student_status,
        m.major_name AS major_name
    FROM students s
    INNER JOIN majors m ON m.id = s.major_id
";

$params = [];

if ($keyword !== '') {
    $sql .= "
        WHERE s.full_name LIKE :name
        OR s.nis LIKE :nis
        OR m.major_name LIKE :major
    ";

    $search = '%' . $keyword . '%';

    $params = [
        'name' => $search,
        'nis' => $search,
        'major' => $search
    ];
}

$sql .= " ORDER BY s.full_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$students = $stmt->fetchAll();

// Notifikasi
$successMessage = '';

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $successMessage = 'Siswa berhasil ditambahkan.';
} elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $successMessage = 'Data siswa berhasil diperbarui.';
} elseif (isset($_GET['deactivated']) && $_GET['deactivated'] === '1') {
    $successMessage = 'Siswa berhasil dinonaktifkan. Data portofolio tetap tersimpan.';
}

function escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Siswa | SIPORSI Admin</title>

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

        .btn-add {
            padding: 11px 17px;
            border: none;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-add:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        /* CONTENT PANEL */

        .content-panel {
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        /* SEARCH */

        .search-box {
            position: relative;
        }

        .search-input {
            min-height: 46px;
            padding: 11px 15px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f9fbfe;
            font-size: 12px;
        }

        .search-input:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.1);
        }

        .search-button {
            min-height: 46px;
            border: none;
            border-radius: 9px;
            background: var(--blue);
            color: white;
            font-size: 12px;
            font-weight: 700;
        }

        .search-button:hover {
            background: var(--navy);
            color: white;
        }

        .reset-link {
            color: var(--muted);
            font-size: 11px;
            text-decoration: none;
        }

        .reset-link:hover {
            color: var(--blue);
        }

        /* TABLE */

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            padding: 14px 13px;
            border-bottom: 1px solid var(--border);
            background: #f1f5fa;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px 13px;
            border-color: var(--border);
            color: #45566d;
            font-size: 12px;
        }

        .table tbody tr:hover {
            background: #f8fbff;
        }

        .student-name {
            color: var(--navy);
            font-weight: 700;
        }

        .student-number {
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-active {
            background: #e5f5eb;
            color: #198754;
        }

        .status-graduated {
            background: #e8f0ff;
            color: #2864b4;
        }

        .status-inactive {
            background: #edf0f3;
            color: #657386;
        }

        /* ACTION BUTTONS */

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .btn-action {
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
            white-space: nowrap;
        }

        .btn-detail {
            border: 1px solid #8bc9df;
            background: white;
            color: #1385a8;
        }

        .btn-detail:hover {
            background: #1385a8;
            color: white;
        }

        .btn-edit {
            border: 1px solid #e9bf61;
            background: white;
            color: #a87810;
        }

        .btn-edit:hover {
            background: #e9bf61;
            color: #3d2b05;
        }

        .btn-deactivate {
            border: 1px solid #e5a0a7;
            background: white;
            color: #c23c49;
        }

        .btn-deactivate:hover {
            background: #c23c49;
            color: white;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 45px 15px !important;
            text-align: center;
            color: var(--muted) !important;
        }

        .empty-icon {
            display: block;
            margin-bottom: 12px;
            font-size: 30px;
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

            .admin-content {
                padding: 25px 20px;
            }

            .admin-topbar {
                padding: 15px 20px;
            }

        }

        @media (max-width: 576px) {

            .admin-content {
                padding: 20px 14px;
            }

            .page-title {
                font-size: 21px;
            }

            .content-panel {
                padding: 16px;
            }

            .page-header-actions {
                width: 100%;
            }

            .btn-add {
                display: block;
                width: 100%;
                text-align: center;
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
                SIPORSI / Manajemen Siswa
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/dashboard.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Dashboard
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <!-- PAGE HEADER -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

            <div>

                <h1 class="page-title">
                    Manajemen Data Siswa
                </h1>

                <p class="page-description">
                    Kelola data siswa SMK Negeri 4 Banjarmasin.
                </p>

            </div>

            <div class="page-header-actions">

                <a href="<?= BASE_URL ?>/admin/students/create.php"
                   class="btn-add">
                    + Tambah Siswa
                </a>

            </div>

        </div>


        <!-- SUCCESS NOTIFICATION -->
        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success alert-dismissible fade show rounded-3 small"
                 role="alert">

                <strong>Berhasil!</strong>
                <?= escape($successMessage) ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close">
                </button>

            </div>

        <?php endif; ?>


        <!-- STUDENT TABLE -->
        <div class="content-panel">

            <!-- PANEL HEADER -->
            <div class="mb-4">

                <h5 class="fw-bold mb-1"
                    style="color:var(--navy);font-size:16px;">
                    Daftar Siswa
                </h5>

                <p class="text-secondary mb-0"
                   style="font-size:11px;">
                    Informasi siswa yang terdaftar dalam sistem SIPORSI.
                </p>

            </div>


            <!-- SEARCH -->
            <form method="GET" class="row g-2 mb-4">

                <div class="col-md-9">

                    <input type="text"
                           name="search"
                           class="form-control search-input"
                           placeholder="Cari nama siswa, NIS, atau jurusan..."
                           value="<?= escape($keyword) ?>">

                </div>

                <div class="col-md-2 d-grid">

                    <button type="submit"
                            class="btn search-button">
                        Cari Siswa
                    </button>

                </div>

                <div class="col-md-1 d-flex align-items-center justify-content-center">

                    <?php if ($keyword !== ''): ?>

                        <a href="<?= BASE_URL ?>/admin/students/index.php"
                           class="reset-link">
                            Reset
                        </a>

                    <?php endif; ?>

                </div>

            </form>


            <!-- TABLE -->
            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th style="width:55px;">No.</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Jurusan</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th style="min-width:190px;">Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($students) > 0): ?>

                        <?php foreach ($students as $index => $student): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td class="student-number">
                                    <?= escape($student['nis'] ?? '-') ?>
                                </td>

                                <td>

                                    <span class="student-name">
                                        <?= escape($student['full_name']) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= escape($student['major_name']) ?>
                                </td>

                                <td>
                                    <?= escape($student['class_level'] ?? '-') ?>
                                </td>

                                <td>

                                    <?php if ($student['student_status'] === 'active'): ?>

                                        <span class="status-badge status-active">
                                            Aktif
                                        </span>

                                    <?php elseif ($student['student_status'] === 'graduated'): ?>

                                        <span class="status-badge status-graduated">
                                            Lulus
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge status-inactive">
                                            Tidak Aktif
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a href="<?= BASE_URL ?>/admin/students/detail.php?id=<?= (int) $student['id'] ?>"
                                           class="btn-action btn-detail">
                                            Detail
                                        </a>

                                        <a href="<?= BASE_URL ?>/admin/students/edit.php?id=<?= (int) $student['id'] ?>"
                                           class="btn-action btn-edit">
                                            Edit
                                        </a>

                                        <?php if ($student['student_status'] === 'active'): ?>

                                            <a href="<?= BASE_URL ?>/admin/students/delete.php?id=<?= (int) $student['id'] ?>"
                                               class="btn-action btn-deactivate">
                                                Nonaktifkan
                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7" class="empty-state">

                                <span class="empty-icon">📂</span>

                                <strong>
                                    Data siswa tidak ditemukan.
                                </strong>

                                <p class="small mt-2 mb-0">
                                    Coba gunakan kata kunci pencarian yang berbeda.
                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- TABLE FOOTER -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">

                <p class="text-secondary mb-0"
                   style="font-size:11px;">

                    Jumlah siswa ditemukan:
                    <strong class="text-dark">
                        <?= count($students) ?>
                    </strong>

                </p>

                <?php if ($keyword !== ''): ?>

                    <span class="badge rounded-pill text-bg-light border text-secondary">
                        Hasil pencarian: <?= escape($keyword) ?>
                    </span>

                <?php endif; ?>

            </div>

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
