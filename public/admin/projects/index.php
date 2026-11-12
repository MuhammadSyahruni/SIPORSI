
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();

$keyword = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

// Mengambil data proyek beserta informasi siswa dan jurusan
$sql = "
    SELECT
        p.id,
        p.title,
        p.category,
        p.thumbnail,
        p.publication_status,
        p.created_at,
        s.full_name,
        m.major_name
    FROM projects p
    INNER JOIN students s ON s.id = p.student_id
    INNER JOIN majors m ON m.id = s.major_id
    WHERE 1 = 1
";

$params = [];

if ($keyword !== '') {
    $sql .= "
        AND (
            p.title LIKE :title_keyword
            OR s.full_name LIKE :student_keyword
            OR m.major_name LIKE :major_keyword
        )
    ";

    $searchValue = '%' . $keyword . '%';

    $params = [
        'title_keyword' => $searchValue,
        'student_keyword' => $searchValue,
        'major_keyword' => $searchValue
    ];
}

// Status yang diperbolehkan
$allowedStatuses = [
    'draft',
    'pending',
    'approved',
    'revision',
    'rejected'
];

if (in_array($status, $allowedStatuses, true)) {
    $sql .= " AND p.publication_status = :status";
    $params['status'] = $status;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Statistik proyek
$totalProjects = (int) $db->query(
    "SELECT COUNT(*) FROM projects"
)->fetchColumn();

$totalDraft = (int) $db->query(
    "SELECT COUNT(*) FROM projects WHERE publication_status = 'draft'"
)->fetchColumn();

$totalPending = (int) $db->query(
    "SELECT COUNT(*) FROM projects WHERE publication_status = 'pending'"
)->fetchColumn();

$totalApproved = (int) $db->query(
    "SELECT COUNT(*) FROM projects WHERE publication_status = 'approved'"
)->fetchColumn();

$totalRevision = (int) $db->query(
    "SELECT COUNT(*) FROM projects WHERE publication_status = 'revision'"
)->fetchColumn();

$totalRejected = (int) $db->query(
    "SELECT COUNT(*) FROM projects WHERE publication_status = 'rejected'"
)->fetchColumn();

// Label status
$statusLabels = [
    'draft' => 'Draft',
    'pending' => 'Menunggu Pemeriksaan',
    'approved' => 'Disetujui',
    'revision' => 'Perlu Revisi',
    'rejected' => 'Ditolak'
];

$statusColors = [
    'draft' => 'secondary',
    'pending' => 'warning',
    'approved' => 'success',
    'revision' => 'info',
    'rejected' => 'danger'
];

// Notifikasi hasil pemeriksaan
$reviewMessage = isset($_GET['reviewed'])
    && $_GET['reviewed'] === '1';

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Proyek | SIPORSI Admin</title>

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

        /* STATISTICS */

        .summary-card {
            height: 100%;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
            transition: .2s;
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(16,46,89,.07);
        }

        .summary-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            margin-bottom: 14px;
            border-radius: 11px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 18px;
        }

        .summary-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .summary-value {
            margin-top: 6px;
            color: var(--navy);
            font-size: 27px;
            font-weight: 800;
        }

        .summary-pending .summary-value {
            color: #d99a00;
        }

        .summary-approved .summary-value {
            color: #198754;
        }

        .summary-revision .summary-value {
            color: #0aa2c0;
        }

        .summary-rejected .summary-value {
            color: #dc3545;
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

        /* SEARCH */

        .search-input,
        .filter-select {
            min-height: 44px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #f9fbfe;
            font-size: 12px;
        }

        .search-input:focus,
        .filter-select:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.1);
        }

        .btn-search {
            min-height: 44px;
            padding: 10px 17px;
            border: none;
            border-radius: 8px;
            background: var(--blue);
            color: white;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-search:hover {
            background: var(--navy);
            color: white;
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-reset:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        /* TABLE */

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            background: #f1f5fa;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 13px 12px;
            border-color: var(--border);
            color: #45566d;
            font-size: 12px;
        }

        .table tbody tr:hover {
            background: #f8fbff;
        }

        .project-thumbnail {
            width: 75px;
            height: 55px;
            border: 1px solid var(--border);
            border-radius: 7px;
            object-fit: cover;
        }

        .no-thumbnail {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 75px;
            height: 55px;
            border-radius: 7px;
            background: #edf3fa;
            color: var(--muted);
            font-size: 20px;
        }

        .project-name {
            color: var(--navy);
            font-weight: 700;
        }

        .project-category {
            display: inline-block;
            margin-top: 5px;
            padding: 4px 8px;
            border-radius: 6px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 10px;
            font-weight: 600;
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

        .status-draft {
            background: #edf0f3;
            color: #657386;
        }

        .status-pending {
            background: #fff4d9;
            color: #956b0a;
        }

        .status-approved {
            background: #e5f5eb;
            color: #198754;
        }

        .status-revision {
            background: #e1f6fa;
            color: #087f96;
        }

        .status-rejected {
            background: #fde8e9;
            color: #c23c49;
        }

        /* DETAIL BUTTON */

        .btn-detail {
            display: inline-block;
            padding: 7px 13px;
            border: 1px solid #8bc9df;
            border-radius: 7px;
            background: white;
            color: #1385a8;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
            white-space: nowrap;
        }

        .btn-detail:hover {
            background: #1385a8;
            color: white;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 45px 15px !important;
            color: var(--muted) !important;
            text-align: center;
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

        @media (max-width: 1200px) {

            .admin-content {
                padding: 25px 20px;
            }

            .content-panel {
                padding: 18px;
            }

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

        }

        @media (max-width: 576px) {

            .admin-content {
                padding: 20px 14px;
            }

            .page-title {
                font-size: 21px;
            }

            .summary-value {
                font-size: 24px;
            }

            .content-panel {
                padding: 15px;
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
       class="sidebar-menu active">

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
                SIPORSI / Manajemen Proyek
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
        <div class="mb-4">

            <h1 class="page-title">
                Manajemen Proyek Siswa
            </h1>

            <p class="page-description">
                Periksa dan kelola hasil karya siswa SIPORSI.
            </p>

        </div>


        <!-- STATISTICS -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-4">

                <div class="summary-card">

                    <div class="summary-icon">▤</div>

                    <div class="summary-label">
                        Total Proyek
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalProjects, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-4">

                <div class="summary-card summary-pending">

                    <div class="summary-icon">◷</div>

                    <div class="summary-label">
                        Menunggu Pemeriksaan
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalPending, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-4">

                <div class="summary-card summary-approved">

                    <div class="summary-icon">✓</div>

                    <div class="summary-label">
                        Disetujui
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalApproved, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-4">

                <div class="summary-card summary-revision">

                    <div class="summary-icon">↻</div>

                    <div class="summary-label">
                        Perlu Revisi
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalRevision, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-4">

                <div class="summary-card summary-rejected">

                    <div class="summary-icon">✕</div>

                    <div class="summary-label">
                        Ditolak
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalRejected, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-4">

                <div class="summary-card">

                    <div class="summary-icon">◷</div>

                    <div class="summary-label">
                        Draft
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalDraft, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- REVIEW NOTIFICATION -->
        <?php if ($reviewMessage): ?>

            <div class="alert alert-success alert-dismissible fade show rounded-3 small"
                 role="alert">

                <strong>Berhasil!</strong>
                Status pemeriksaan proyek berhasil diperbarui.

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close">
                </button>

            </div>

        <?php endif; ?>


        <!-- PROJECT TABLE -->
        <div class="content-panel">

            <div class="mb-4">

                <h5 class="panel-heading">
                    Daftar Proyek Siswa
                </h5>

                <p class="panel-description">
                    Pantau hasil karya siswa dan status pemeriksaannya.
                </p>

            </div>


            <!-- SEARCH AND FILTER -->
            <form method="GET" class="row g-2 mb-4">

                <div class="col-lg-5 col-md-12">

                    <input
                        type="text"
                        name="search"
                        class="form-control search-input"
                        placeholder="Cari judul proyek, nama siswa, atau jurusan..."
                        value="<?= e($keyword) ?>"
                    >

                </div>

                <div class="col-lg-3 col-md-5">

                    <select name="status"
                            class="form-select filter-select">

                        <option value="">Semua Status</option>

                        <?php foreach ($statusLabels as $value => $label): ?>

                            <option value="<?= e($value) ?>"
                                <?= $status === $value ? 'selected' : '' ?>>

                                <?= e($label) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-lg-2 col-md-3 d-grid">

                    <button type="submit"
                            class="btn btn-search">
                        Cari
                    </button>

                </div>

                <div class="col-lg-2 col-md-4 d-grid">

                    <a href="<?= BASE_URL ?>/admin/projects/index.php"
                       class="btn-reset">
                        Reset
                    </a>

                </div>

            </form>


            <!-- TABLE -->
            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th>No.</th>
                            <th>Gambar</th>
                            <th>Judul Proyek</th>
                            <th>Nama Siswa</th>
                            <th>Jurusan</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($projects) > 0): ?>

                        <?php foreach ($projects as $index => $project): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>

                                    <?php if (!empty($project['thumbnail'])): ?>

                                        <img
                                            src="<?= BASE_URL . '/' . e($project['thumbnail']) ?>"
                                            alt="Gambar proyek"
                                            class="project-thumbnail"
                                        >

                                    <?php else: ?>

                                        <div class="no-thumbnail">
                                            ▤
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <div class="project-name">
                                        <?= e($project['title']) ?>
                                    </div>

                                    <span class="project-category">
                                        <?= e($project['category'] ?? '-') ?>
                                    </span>

                                </td>

                                <td>
                                    <?= e($project['full_name']) ?>
                                </td>

                                <td>
                                    <?= e($project['major_name']) ?>
                                </td>

                                <td>

                                    <?php
                                    $projectStatus = $project['publication_status'];
                                    $statusClass = $projectStatus === 'pending'
                                        ? 'status-pending'
                                        : 'status-' . $projectStatus;
                                    ?>

                                    <span class="status-badge <?= e($statusClass) ?>">

                                        <?= e(
                                            $statusLabels[$projectStatus]
                                            ?? 'Tidak diketahui'
                                        ) ?>

                                    </span>

                                </td>

                                <td style="white-space:nowrap;">

                                    <?= !empty($project['created_at'])
                                        ? date('d-m-Y', strtotime($project['created_at']))
                                        : '-' ?>

                                </td>

                                <td>

                                    <a
                                        href="<?= BASE_URL ?>/admin/projects/detail.php?id=<?= (int) $project['id'] ?>"
                                        class="btn-detail"
                                    >
                                        Detail →
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="8" class="empty-state">

                                <div class="fs-2 mb-2">📂</div>

                                <strong>
                                    Proyek tidak ditemukan.
                                </strong>

                                <p class="small mt-2 mb-0">
                                    Silakan periksa kata kunci atau filter status.
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

                    Menampilkan:
                    <strong class="text-dark">
                        <?= number_format(count($projects), 0, ',', '.') ?>
                    </strong>
                    proyek.

                </p>

                <?php if ($keyword !== '' || $status !== ''): ?>

                    <span class="badge rounded-pill text-bg-light border text-secondary">
                        Filter sedang digunakan
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
