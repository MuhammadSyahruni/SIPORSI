
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();

// Mengambil data industri dan akun pengguna
$stmt = $db->query("
    SELECT
        i.id,
        i.company_name,
        i.industry_type,
        i.email_contact,
        i.phone_contact,
        i.verification_status,
        i.created_at,
        u.username,
        u.email,
        u.account_status
    FROM industries i
    INNER JOIN users u ON u.id = i.user_id
    ORDER BY i.created_at DESC
");

$industries = $stmt->fetchAll();

// Menghitung statistik dari data yang sudah diambil
$totalIndustries = count($industries);
$totalVerified = 0;
$totalPending = 0;
$totalRejected = 0;
$totalActiveAccounts = 0;

foreach ($industries as $industry) {

    if ($industry['verification_status'] === 'verified') {
        $totalVerified++;
    } elseif ($industry['verification_status'] === 'pending') {
        $totalPending++;
    } elseif ($industry['verification_status'] === 'rejected') {
        $totalRejected++;
    }

    if ($industry['account_status'] === 'active') {
        $totalActiveAccounts++;
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

    <title>Manajemen Industri | SIPORSI Admin</title>

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
            width: 41px;
            height: 41px;
            margin-bottom: 15px;
            border-radius: 11px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 19px;
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

        /* TABLE PANEL */

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
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            background: #f1f5fa;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 14px 12px;
            border-color: var(--border);
            color: #45566d;
            font-size: 11px;
        }

        .table tbody tr:hover {
            background: #f8fbff;
        }

        .company-name {
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
        }

        .company-type {
            color: var(--muted);
        }

        .account-username {
            color: #45566d;
            font-weight: 600;
        }

        /* BADGES */

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-verified {
            background: #e5f5eb;
            color: #198754;
        }

        .status-pending {
            background: #fff4d9;
            color: #956b0a;
        }

        .status-rejected {
            background: #fde8e9;
            color: #c23c49;
        }

        .status-active {
            background: #e5f5eb;
            color: #198754;
        }

        .status-inactive {
            background: #edf0f3;
            color: #657386;
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
       class="sidebar-menu active">

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
                SIPORSI / Manajemen Industri
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
                Manajemen Data Industri
            </h1>

            <p class="page-description">
                Kelola akun perusahaan dan mitra industri SIPORSI.
            </p>

        </div>


        <!-- STATISTICS -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">

                <div class="summary-card">

                    <div class="summary-icon">▣</div>

                    <div class="summary-label">
                        Total Industri
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalIndustries, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="summary-card">

                    <div class="summary-icon">✓</div>

                    <div class="summary-label">
                        Terverifikasi
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalVerified, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="summary-card">

                    <div class="summary-icon">◷</div>

                    <div class="summary-label">
                        Menunggu Verifikasi
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalPending, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="summary-card">

                    <div class="summary-icon">✕</div>

                    <div class="summary-label">
                        Ditolak
                    </div>

                    <div class="summary-value">
                        <?= number_format($totalRejected, 0, ',', '.') ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- INDUSTRY TABLE -->
        <div class="content-panel">

            <div class="mb-4">

                <h5 class="panel-heading">
                    Daftar Mitra Industri
                </h5>

                <p class="panel-description">
                    Informasi perusahaan, akun pengguna, dan status verifikasi.
                </p>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th>No.</th>
                            <th>Nama Perusahaan</th>
                            <th>Jenis Industri</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Verifikasi</th>
                            <th>Status Akun</th>
                            <th>Tanggal Daftar</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($industries) > 0): ?>

                        <?php foreach ($industries as $index => $industry): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>

                                    <span class="company-name">
                                        <?= escape($industry['company_name']) ?>
                                    </span>

                                </td>

                                <td>

                                    <span class="company-type">
                                        <?= escape($industry['industry_type'] ?: '-') ?>
                                    </span>

                                </td>

                                <td>
                                    <?= escape($industry['email_contact'] ?: $industry['email']) ?>
                                </td>

                                <td>

                                    <span class="account-username">
                                        <?= escape($industry['username']) ?>
                                    </span>

                                </td>

                                <td>

                                    <?php if ($industry['verification_status'] === 'verified'): ?>

                                        <span class="status-badge status-verified">
                                            Terverifikasi
                                        </span>

                                    <?php elseif ($industry['verification_status'] === 'rejected'): ?>

                                        <span class="status-badge status-rejected">
                                            Ditolak
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge status-pending">
                                            Menunggu
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if ($industry['account_status'] === 'active'): ?>

                                        <span class="status-badge status-active">
                                            Aktif
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge status-inactive">
                                            <?= escape(ucfirst($industry['account_status'])) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td style="white-space:nowrap;">

                                    <?= !empty($industry['created_at'])
                                        ? date('d-m-Y H:i', strtotime($industry['created_at']))
                                        : '-' ?>

                                </td>

                                <td>

                                    <a href="<?= BASE_URL ?>/admin/industries/detail.php?id=<?= (int) $industry['id'] ?>"
                                       class="btn-detail">
                                        Detail →
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="9" class="empty-state">

                                <div class="fs-2 mb-2">🏢</div>

                                <strong>
                                    Belum ada data industri.
                                </strong>

                                <p class="small mt-2 mb-0">
                                    Data perusahaan yang mendaftar akan tampil di sini.
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

                    Jumlah industri:
                    <strong class="text-dark">
                        <?= number_format($totalIndustries, 0, ',', '.') ?>
                    </strong>

                </p>

                <span class="badge rounded-pill text-bg-light border text-secondary">
                    Akun aktif: <?= number_format($totalActiveAccounts, 0, ',', '.') ?>
                </span>

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
