
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();
$csrfToken = Csrf::token();

// Validasi ID industri
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: ' . BASE_URL . '/admin/industries/index.php');
    exit;
}

// Mengambil informasi perusahaan, akun, dan petugas verifikasi
$stmt = $db->prepare("
    SELECT
        i.*,
        u.username,
        u.email AS account_email,
        u.account_status,
        reviewer.username AS reviewer_username
    FROM industries i
    INNER JOIN users u ON u.id = i.user_id
    LEFT JOIN users reviewer ON reviewer.id = i.verified_by
    WHERE i.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);
$industry = $stmt->fetch();

if (!$industry) {
    http_response_code(404);
    exit('Data industri tidak ditemukan.');
}

// Fungsi keamanan output
function escape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Label status verifikasi
$verificationLabels = [
    'pending' => 'Menunggu Verifikasi',
    'verified' => 'Terverifikasi',
    'rejected' => 'Ditolak'
];

$verificationColors = [
    'pending' => 'warning',
    'verified' => 'success',
    'rejected' => 'danger'
];

$verificationStatus = $industry['verification_status'] ?? 'pending';

$verificationLabel = $verificationLabels[$verificationStatus]
    ?? 'Tidak diketahui';

$verificationColor = $verificationColors[$verificationStatus]
    ?? 'secondary';

// Label status akun
$accountLabels = [
    'active' => 'Aktif',
    'inactive' => 'Tidak Aktif',
    'suspended' => 'Ditangguhkan'
];

$accountStatus = $industry['account_status'] ?? '';

$accountLabel = $accountLabels[$accountStatus]
    ?? ucfirst($accountStatus);

// Tautan website hanya diaktifkan untuk URL HTTP/HTTPS yang valid
$website = trim((string) ($industry['website'] ?? ''));
$websiteScheme = strtolower(parse_url($website, PHP_URL_SCHEME) ?? '');

$websiteUrl = (
    filter_var($website, FILTER_VALIDATE_URL) &&
    in_array($websiteScheme, ['http', 'https'], true)
) ? $website : null;

// Inisial nama perusahaan
$companyInitial = strtoupper(
    substr($industry['company_name'] ?? 'I', 0, 1)
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Industri | SIPORSI Admin</title>

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

        /* COMPANY PROFILE */

        .company-banner {
            position: relative;
            overflow: hidden;
            padding: 27px;
            border-radius: 16px;
            background: linear-gradient(120deg, #102e59, #1769aa);
            color: white;
        }

        .company-banner::after {
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

        .company-banner-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 19px;
        }

        .company-avatar {
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

        .company-banner h4 {
            margin-bottom: 5px;
            font-size: 21px;
            font-weight: 800;
        }

        .company-banner p {
            margin: 0;
            color: rgba(255,255,255,.75);
            font-size: 12px;
        }

        .banner-status {
            display: inline-block;
            margin-top: 11px;
            padding: 6px 11px;
            border-radius: 20px;
            background: rgba(255,255,255,.16);
            color: white;
            font-size: 10px;
            font-weight: 700;
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
            grid-template-columns: minmax(155px, 30%) 1fr;
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
            line-height: 1.7;
        }

        .detail-value.regular {
            font-weight: 400;
            white-space: pre-wrap;
        }

        .detail-link {
            color: var(--blue);
            text-decoration: none;
            overflow-wrap: anywhere;
        }

        .detail-link:hover {
            text-decoration: underline;
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

        .status-suspended {
            background: #fde8e9;
            color: #c23c49;
        }

        /* VERIFICATION PANEL */

        .verification-panel {
            padding: 25px;
            border: 1px solid #cfe4f8;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .verification-panel h5 {
            margin-bottom: 8px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .verification-panel p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .verification-note {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
            padding: 14px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .verification-note-icon {
            color: var(--blue);
            font-size: 18px;
        }

        .verification-note strong {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 11px;
        }

        .verification-note span {
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
        }

        /* BUTTONS */

        .btn-approve {
            min-height: 42px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: #198754;
            color: white;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-approve:hover {
            background: #146c43;
            color: white;
            transform: translateY(-2px);
        }

        .btn-reject {
            min-height: 42px;
            padding: 10px 18px;
            border: 1px solid #e5a0a7;
            border-radius: 8px;
            background: white;
            color: #c23c49;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-reject:hover {
            background: #c23c49;
            color: white;
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

        /* MODAL */

        .confirmation-modal .modal-content {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 20px 55px rgba(16,46,89,.18);
        }

        .confirmation-modal .modal-header {
            padding: 21px 24px;
            border-bottom: 1px solid var(--border);
            background: #f9fbfe;
        }

        .confirmation-modal .modal-title {
            color: var(--navy);
            font-size: 16px;
            font-weight: 800;
        }

        .confirmation-modal .modal-body {
            padding: 24px;
        }

        .modal-action-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            margin-bottom: 15px;
            border-radius: 13px;
            font-size: 22px;
            font-weight: 800;
        }

        .modal-action-icon.approve {
            background: #e5f5eb;
            color: #198754;
        }

        .modal-action-icon.reject {
            background: #fde8e9;
            color: #c23c49;
        }

        .modal-company-info {
            margin-top: 17px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f9fbfe;
        }

        .modal-company-info small {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .modal-company-info strong {
            color: var(--navy);
            font-size: 13px;
        }

        .modal-footer {
            padding: 15px 24px;
            border-top: 1px solid var(--border);
        }

        .btn-modal-cancel {
            min-height: 39px;
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .btn-modal-approve,
        .btn-modal-reject {
            min-height: 39px;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 11px;
            font-weight: 700;
        }

        .btn-modal-approve {
            background: #198754;
        }

        .btn-modal-approve:hover {
            background: #146c43;
            color: white;
        }

        .btn-modal-reject {
            background: #c23c49;
        }

        .btn-modal-reject:hover {
            background: #a82e3a;
            color: white;
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

            .detail-panel,
            .verification-panel {
                padding: 18px;
            }

            .company-banner {
                padding: 20px;
            }

            .company-banner-content {
                align-items: flex-start;
                gap: 13px;
            }

            .company-avatar {
                width: 52px;
                height: 52px;
                font-size: 22px;
            }

            .company-banner h4 {
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

            .verification-actions {
                display: flex;
                flex-direction: column;
            }

            .verification-actions button,
            .verification-actions a {
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
                SIPORSI / Data Industri / Detail
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/industries/index.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Data Industri
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h1 class="page-title">
                Detail Industri
            </h1>

            <p class="page-description">
                Informasi lengkap perusahaan yang terdaftar di SIPORSI.
            </p>

        </div>


        <!-- COMPANY BANNER -->
        <div class="company-banner mb-4">

            <div class="company-banner-content">

                <div class="company-avatar">
                    <?= escape($companyInitial) ?>
                </div>

                <div>

                    <h4>
                        <?= escape($industry['company_name']) ?>
                    </h4>

                    <p>
                        <?= escape($industry['industry_type'] ?: 'Jenis industri belum diisi') ?>
                    </p>

                    <span class="banner-status">
                        <?= escape($verificationLabel) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- COMPANY INFORMATION -->
        <div class="detail-panel mb-4">

            <div class="panel-heading">

                <span class="panel-icon">▣</span>

                Informasi Perusahaan

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Nama Perusahaan
                </div>

                <div class="detail-value">
                    <?= escape($industry['company_name']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Jenis Industri
                </div>

                <div class="detail-value">
                    <?= escape($industry['industry_type']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Deskripsi
                </div>

                <div class="detail-value regular">
                    <?= escape($industry['company_description']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Website
                </div>

                <div class="detail-value">

                    <?php if ($websiteUrl !== null): ?>

                        <a href="<?= escape($websiteUrl) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="detail-link">

                            <?= escape($website) ?>
                            ↗

                        </a>

                    <?php else: ?>

                        <?= escape($industry['website']) ?>

                    <?php endif; ?>

                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Email Perusahaan
                </div>

                <div class="detail-value">
                    <?= escape($industry['email_contact']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Nomor Telepon
                </div>

                <div class="detail-value">
                    <?= escape($industry['phone_contact']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Alamat
                </div>

                <div class="detail-value regular">
                    <?= escape($industry['address']) ?>
                </div>

            </div>

        </div>


        <!-- ACCOUNT AND VERIFICATION INFORMATION -->
        <div class="detail-panel mb-4">

            <div class="panel-heading">

                <span class="panel-icon">🔐</span>

                Informasi Akun dan Verifikasi

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Username
                </div>

                <div class="detail-value">
                    <?= escape($industry['username']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Email Login
                </div>

                <div class="detail-value">
                    <?= escape($industry['account_email']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Status Akun
                </div>

                <div class="detail-value">

                    <span class="status-badge status-<?= escape($accountStatus) ?>">
                        <?= escape($accountLabel) ?>
                    </span>

                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Status Verifikasi
                </div>

                <div class="detail-value">

                    <?php if ($verificationStatus === 'verified'): ?>

                        <span class="status-badge status-verified">
                            Terverifikasi
                        </span>

                    <?php elseif ($verificationStatus === 'rejected'): ?>

                        <span class="status-badge status-rejected">
                            Ditolak
                        </span>

                    <?php else: ?>

                        <span class="status-badge status-pending">
                            Menunggu Verifikasi
                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Waktu Pendaftaran
                </div>

                <div class="detail-value">
                    <?= escape($industry['created_at']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Diverifikasi Oleh
                </div>

                <div class="detail-value">
                    <?= escape($industry['reviewer_username']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Waktu Verifikasi
                </div>

                <div class="detail-value">
                    <?= escape($industry['verified_at']) ?>
                </div>

            </div>

        </div>


        <!-- VERIFICATION DECISION -->
        <?php if ($verificationStatus === 'pending'): ?>

            <div class="verification-panel mb-4">

                <div class="panel-heading">

                    <span class="panel-icon">✓</span>

                    Keputusan Verifikasi

                </div>

                <p>
                    Periksa seluruh informasi perusahaan sebelum memberikan
                    keputusan. Pastikan data perusahaan sudah sesuai.
                </p>


                <div class="verification-note">

                    <div class="verification-note-icon">ⓘ</div>

                    <div>

                        <strong>Informasi Verifikasi</strong>

                        <span>
                            Jika disetujui, mitra industri dapat menggunakan akun
                            untuk mengakses fasilitas SIPORSI. Jika ditolak,
                            akun tidak dapat digunakan untuk login sebagai industri.
                        </span>

                    </div>

                </div>


                <div class="verification-actions d-flex flex-wrap gap-2">

                    <button type="button"
                            class="btn-approve"
                            data-bs-toggle="modal"
                            data-bs-target="#approveModal">

                        Setujui Industri

                    </button>

                    <button type="button"
                            class="btn-reject"
                            data-bs-toggle="modal"
                            data-bs-target="#rejectModal">

                        Tolak Industri

                    </button>

                </div>

            </div>

        <?php endif; ?>


        <!-- BACK BUTTON -->
        <div class="d-flex flex-wrap gap-2">

            <a href="<?= BASE_URL ?>/admin/industries/index.php"
               class="btn-back">

                ← Kembali ke Daftar Industri

            </a>

        </div>


        <!-- FOOTER -->
        <div class="page-footer">

            SIPORSI — Sistem Informasi Portofolio Digital Siswa
            SMK Negeri 4 Banjarmasin

        </div>

    </main>

</div>


<!-- APPROVE CONFIRMATION MODAL -->
<?php if ($verificationStatus === 'pending'): ?>

<div class="modal fade confirmation-modal"
     id="approveModal"
     tabindex="-1"
     aria-labelledby="approveModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="approveModalLabel">
                    Konfirmasi Persetujuan Industri
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup">
                </button>

            </div>


            <form method="POST"
                  action="<?= BASE_URL ?>/admin/industries/verify.php">

                <div class="modal-body">

                    <div class="modal-action-icon approve">
                        ✓
                    </div>

                    <h6 class="fw-bold mb-2" style="color:var(--navy);">
                        Setujui perusahaan ini?
                    </h6>

                    <p class="text-secondary small mb-0">
                        Anda akan menyetujui perusahaan sebagai mitra industri
                        SIPORSI. Perusahaan dapat menggunakan akun setelah
                        verifikasi disetujui.
                    </p>

                    <div class="modal-company-info">

                        <small>PERUSAHAAN YANG AKAN DISETUJUI</small>

                        <strong>
                            <?= escape($industry['company_name']) ?>
                        </strong>

                    </div>

                </div>


                <div class="modal-footer">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= escape($csrfToken) ?>">

                    <input type="hidden"
                           name="id"
                           value="<?= (int) $industry['id'] ?>">

                    <input type="hidden"
                           name="action"
                           value="approve">

                    <button type="button"
                            class="btn-modal-cancel"
                            data-bs-dismiss="modal">
                        Batal
                    </button>

                    <button type="submit"
                            class="btn-modal-approve">
                        Ya, Setujui Industri
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- REJECT CONFIRMATION MODAL -->
<div class="modal fade confirmation-modal"
     id="rejectModal"
     tabindex="-1"
     aria-labelledby="rejectModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="rejectModalLabel">
                    Konfirmasi Penolakan Industri
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup">
                </button>

            </div>


            <form method="POST"
                  action="<?= BASE_URL ?>/admin/industries/verify.php">

                <div class="modal-body">

                    <div class="modal-action-icon reject">
                        !
                    </div>

                    <h6 class="fw-bold mb-2" style="color:var(--navy);">
                        Tolak pendaftaran perusahaan ini?
                    </h6>

                    <p class="text-secondary small mb-0">
                        Anda akan menolak pendaftaran perusahaan sebagai mitra
                        industri SIPORSI. Akun tersebut tidak dapat digunakan
                        untuk login sebagai industri.
                    </p>

                    <div class="modal-company-info">

                        <small>PERUSAHAAN YANG AKAN DITOLAK</small>

                        <strong>
                            <?= escape($industry['company_name']) ?>
                        </strong>

                    </div>

                </div>


                <div class="modal-footer">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= escape($csrfToken) ?>">

                    <input type="hidden"
                           name="id"
                           value="<?= (int) $industry['id'] ?>">

                    <input type="hidden"
                           name="action"
                           value="reject">

                    <button type="button"
                            class="btn-modal-cancel"
                            data-bs-dismiss="modal">
                        Batal
                    </button>

                    <button type="submit"
                            class="btn-modal-reject">
                        Ya, Tolak Industri
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
}
</script>

</body>
</html>
