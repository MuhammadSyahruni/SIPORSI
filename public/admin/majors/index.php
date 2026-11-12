
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();

// Mengambil data jurusan
$stmt = $db->query("
    SELECT id, major_code, major_name, description, is_active
    FROM majors
    ORDER BY major_name ASC
");

$majors = $stmt->fetchAll();

// Token keamanan
$csrfToken = Csrf::token();

// Statistik tampilan
$totalMajors = count($majors);
$activeMajors = 0;
$inactiveMajors = 0;

foreach ($majors as $major) {
    if ((int) $major['is_active'] === 1) {
        $activeMajors++;
    } else {
        $inactiveMajors++;
    }
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

    <title>Manajemen Jurusan | SIPORSI Admin</title>

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
            display: inline-block;
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

        /* STATISTICS */

        .summary-card {
            height: 100%;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .summary-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .summary-value {
            margin-top: 7px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .summary-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 18px;
        }

        /* CONTENT PANEL */

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
            font-size: 16px;
            font-weight: 800;
        }

        .panel-description {
            color: var(--muted);
            font-size: 11px;
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

        .major-code {
            display: inline-block;
            min-width: 48px;
            padding: 6px 9px;
            border-radius: 7px;
            background: #eaf2fc;
            color: var(--blue);
            text-align: center;
            font-size: 11px;
            font-weight: 800;
        }

        .major-name {
            color: var(--navy);
            font-weight: 700;
        }

        .major-description {
            max-width: 250px;
            color: var(--muted);
        }

        /* STATUS */

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

        .status-inactive {
            background: #edf0f3;
            color: #657386;
        }

        /* ACTION BUTTONS */

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .btn-action {
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
            transition: .2s;
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

        .btn-activate {
            border: 1px solid #8bc9a7;
            background: white;
            color: #198754;
        }

        .btn-activate:hover {
            background: #198754;
            color: white;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 45px 15px !important;
            text-align: center;
            color: var(--muted) !important;
        }

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
       class="sidebar-menu">

        <span class="sidebar-icon">♙</span>
        Data Siswa

    </a>

    <a href="<?= BASE_URL ?>/admin/majors/index.php"
       class="sidebar-menu active">

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
                SIPORSI / Manajemen Jurusan
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
                    Manajemen Data Jurusan
                </h1>

                <p class="page-description">
                    Kelola daftar program keahlian SMK Negeri 4 Banjarmasin.
                </p>

            </div>

            <a href="<?= BASE_URL ?>/admin/majors/create.php"
               class="btn-add">
                + Tambah Jurusan
            </a>

        </div>


        <!-- SUMMARY -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-md-4">

                <div class="summary-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="summary-label">
                                Total Jurusan
                            </div>

                            <div class="summary-value">
                                <?= number_format($totalMajors, 0, ',', '.') ?>
                            </div>
                        </div>

                        <div class="summary-icon">▦</div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-md-4">

                <div class="summary-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="summary-label">
                                Jurusan Aktif
                            </div>

                            <div class="summary-value">
                                <?= number_format($activeMajors, 0, ',', '.') ?>
                            </div>
                        </div>

                        <div class="summary-icon">✓</div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-md-4">

                <div class="summary-card">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>
                            <div class="summary-label">
                                Jurusan Tidak Aktif
                            </div>

                            <div class="summary-value">
                                <?= number_format($inactiveMajors, 0, ',', '.') ?>
                            </div>
                        </div>

                        <div class="summary-icon">◷</div>

                    </div>

                </div>

            </div>

        </div>


        <!-- TABLE PANEL -->
        <div class="content-panel">

            <div class="mb-4">

                <h5 class="panel-heading">
                    Daftar Program Keahlian
                </h5>

                <p class="panel-description">
                    Seluruh jurusan yang terdaftar dalam sistem SIPORSI.
                </p>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th style="width:55px;">No.</th>
                            <th>Kode</th>
                            <th>Nama Jurusan</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th style="min-width:155px;">Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($majors) > 0): ?>

                        <?php foreach ($majors as $index => $major): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>

                                    <span class="major-code">
                                        <?= escape($major['major_code']) ?>
                                    </span>

                                </td>

                                <td>

                                    <span class="major-name">
                                        <?= escape($major['major_name']) ?>
                                    </span>

                                </td>

                                <td>

                                    <span class="major-description">
                                        <?= escape($major['description'] ?: '-') ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ((int) $major['is_active'] === 1): ?>

                                        <span class="status-badge status-active">
                                            Aktif
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge status-inactive">
                                            Tidak Aktif
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a href="<?= BASE_URL ?>/admin/majors/edit.php?id=<?= (int) $major['id'] ?>"
                                           class="btn btn-action btn-edit">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              action="<?= BASE_URL ?>/admin/majors/toggle-status.php"
                                              class="d-inline"
                                              onsubmit="return confirm('Apakah Anda yakin ingin mengubah status jurusan ini?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= escape($csrfToken) ?>">

                                            <input type="hidden"
                                                   name="id"
                                                   value="<?= (int) $major['id'] ?>">

                                            <?php if ((int) $major['is_active'] === 1): ?>

                                                <button type="submit"
                                                        class="btn btn-action btn-deactivate">
                                                    Nonaktifkan
                                                </button>

                                            <?php else: ?>

                                                <button type="submit"
                                                        class="btn btn-action btn-activate">
                                                    Aktifkan
                                                </button>

                                            <?php endif; ?>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6" class="empty-state">

                                <div class="fs-2 mb-2">📂</div>

                                <strong>
                                    Belum ada data jurusan.
                                </strong>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- TABLE FOOTER -->
            <div class="mt-4">

                <p class="text-secondary mb-0"
                   style="font-size:11px;">

                    Jumlah jurusan:
                    <strong class="text-dark">
                        <?= number_format($totalMajors, 0, ',', '.') ?>
                    </strong>

                </p>

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
