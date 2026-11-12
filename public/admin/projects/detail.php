
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();

// Validasi ID proyek
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: ' . BASE_URL . '/admin/projects/index.php');
    exit;
}

// Mengambil proyek beserta informasi siswa dan jurusan
$sql = "
    SELECT
        p.*,
        s.full_name,
        s.public_slug,
        m.major_name
    FROM projects p
    INNER JOIN students s ON s.id = p.student_id
    INNER JOIN majors m ON m.id = s.major_id
    WHERE p.id = :id
    LIMIT 1
";

$stmt = $db->prepare($sql);
$stmt->execute(['id' => $id]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    exit('Proyek tidak ditemukan.');
}

$csrfToken = Csrf::token();

// Label status proyek
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

$projectStatus = $project['publication_status'] ?? 'draft';

$statusLabel = $statusLabels[$projectStatus] ?? 'Tidak diketahui';
$statusColor = $statusColors[$projectStatus] ?? 'secondary';

// Validasi tautan demo dan repository
function safeUrl($value): ?string
{
    $url = trim((string) ($value ?? ''));

    if (
        $url === '' ||
        !filter_var($url, FILTER_VALIDATE_URL)
    ) {
        return null;
    }

    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

    return in_array($scheme, ['http', 'https'], true)
        ? $url
        : null;
}

$demoUrl = safeUrl($project['demo_url'] ?? '');
$repositoryUrl = safeUrl($project['repository_url'] ?? '');

// Mengambil riwayat pemeriksaan proyek
$reviewStmt = $db->prepare("
    SELECT
        pr.review_status,
        pr.review_notes,
        pr.reviewed_at,
        u.username AS reviewer_username
    FROM project_reviews pr
    LEFT JOIN users u ON u.id = pr.reviewer_id
    WHERE pr.project_id = :project_id
    ORDER BY pr.reviewed_at DESC, pr.id DESC
");

$reviewStmt->execute(['project_id' => $id]);
$reviewHistory = $reviewStmt->fetchAll();

// Label riwayat pemeriksaan
$reviewLabels = [
    'approved' => 'Disetujui',
    'revision' => 'Perlu Revisi',
    'rejected' => 'Ditolak'
];

$reviewColors = [
    'approved' => 'success',
    'revision' => 'info',
    'rejected' => 'danger'
];

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Tanggal agar lebih mudah dibaca
function formatDate($value, $withTime = false): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        $withTime ? 'd-m-Y H:i' : 'd-m-Y',
        $timestamp
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Proyek | SIPORSI Admin</title>

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
            max-width: 1250px;
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

        /* PROJECT HEADER */

        .project-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .project-title {
            margin: 0;
            color: var(--navy);
            font-size: 22px;
            font-weight: 800;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .project-category {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 11px;
            border-radius: 7px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 10px;
            font-weight: 700;
        }

        /* STATUS */

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* PANELS */

        .content-panel {
            margin-bottom: 22px;
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

        .panel-description {
            margin-top: -12px;
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        /* PROJECT IMAGE */

        .project-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 180px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #f7f9fc;
        }

        .project-preview img {
            display: block;
            width: 100%;
            max-height: 440px;
            object-fit: contain;
        }

        .empty-image {
            padding: 40px 20px;
            color: var(--muted);
            text-align: center;
        }

        .empty-image-icon {
            margin-bottom: 12px;
            color: #a7b5c7;
            font-size: 35px;
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
            line-height: 1.8;
        }

        .detail-value.regular {
            font-weight: 400;
            white-space: pre-wrap;
        }

        .detail-link {
            color: var(--blue);
            text-decoration: none;
        }

        .detail-link:hover {
            text-decoration: underline;
        }

        /* REVIEW PANEL */

        .review-panel {
            padding: 25px;
            border: 1px solid #cfe4f8;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .review-panel h5 {
            margin-bottom: 7px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .review-panel-description {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .review-note-label {
            margin-bottom: 8px;
            color: #35465e;
            font-size: 12px;
            font-weight: 600;
        }

        .review-note {
            min-height: 115px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f9fbfe;
            color: var(--text);
            font-size: 12px;
            line-height: 1.7;
            resize: vertical;
        }

        .review-note:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.1);
            outline: none;
        }

        .review-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        /* REVIEW BUTTONS */

        .btn-approve {
            min-height: 42px;
            padding: 10px 17px;
            border: none;
            border-radius: 8px;
            background: #198754;
            color: white;
            font-size: 11px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-approve:hover {
            background: #146c43;
            color: white;
            transform: translateY(-2px);
        }

        .btn-revision {
            min-height: 42px;
            padding: 10px 17px;
            border: none;
            border-radius: 8px;
            background: #e5a928;
            color: #493506;
            font-size: 11px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-revision:hover {
            background: #d49a17;
            color: #382804;
            transform: translateY(-2px);
        }

        .btn-reject {
            min-height: 42px;
            padding: 10px 17px;
            border: 1px solid #e5a0a7;
            border-radius: 8px;
            background: white;
            color: #c23c49;
            font-size: 11px;
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

        /* REVIEW HISTORY */

        .history-item {
            position: relative;
            padding: 0 0 22px 24px;
            border-left: 2px solid var(--border);
        }

        .history-item:last-child {
            padding-bottom: 0;
            border-left-color: transparent;
        }

        .history-dot {
            position: absolute;
            top: 2px;
            left: -7px;
            width: 12px;
            height: 12px;
            border: 2px solid white;
            border-radius: 50%;
            background: var(--blue);
            box-shadow: 0 0 0 1px var(--blue);
        }

        .history-title {
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 12px;
            font-weight: 700;
        }

        .history-meta {
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 10px;
        }

        .history-note {
            padding: 11px 13px;
            border-radius: 8px;
            background: #f7f9fc;
            color: #52657c;
            font-size: 11px;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        /* INFO NOTICE */

        .review-info {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .review-info-icon {
            color: var(--blue);
            font-size: 18px;
        }

        .review-info strong {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 11px;
        }

        .review-info span {
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
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

        .modal-action-icon.revision {
            background: #fff4d9;
            color: #956b0a;
        }

        .modal-action-icon.reject {
            background: #fde8e9;
            color: #c23c49;
        }

        .modal-project-info {
            margin-top: 17px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f9fbfe;
        }

        .modal-project-info small {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .modal-project-info strong {
            color: var(--navy);
            font-size: 13px;
            overflow-wrap: anywhere;
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
        .btn-modal-revision,
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

        .btn-modal-revision {
            background: #d49a17;
            color: #352700;
        }

        .btn-modal-revision:hover {
            background: #bd8710;
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

            .content-panel,
            .review-panel {
                padding: 18px;
            }

            .project-title {
                font-size: 18px;
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

            .review-actions {
                display: flex;
                flex-direction: column;
            }

            .review-actions button {
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
                SIPORSI / Manajemen Proyek / Detail Proyek
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/projects/index.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Daftar Proyek
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h1 class="page-title">
                Detail Proyek
            </h1>

            <p class="page-description">
                Periksa informasi dan hasil karya siswa sebelum menentukan status publikasi.
            </p>

        </div>


        <!-- PROJECT TITLE PANEL -->
        <div class="content-panel">

            <div class="project-heading">

                <div>

                    <h2 class="project-title">
                        <?= e($project['title']) ?>
                    </h2>

                    <span class="project-category">
                        <?= e($project['category'] ?? '-') ?>
                    </span>

                </div>

                <div>

                    <span class="status-badge bg-<?= e($statusColor) ?> text-<?= $statusColor === 'warning' ? 'dark' : 'white' ?>">
                        <?= e($statusLabel) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- PROJECT IMAGE -->
        <div class="content-panel">

            <div class="panel-heading">

                <span class="panel-icon">▤</span>

                Tampilan Hasil Proyek

            </div>

            <?php if (!empty($project['thumbnail'])): ?>

                <div class="project-preview">

                    <img
                        src="<?= BASE_URL . '/' . e($project['thumbnail']) ?>"
                        alt="Gambar proyek <?= e($project['title']) ?>"
                    >

                </div>

            <?php else: ?>

                <div class="empty-image">

                    <div class="empty-image-icon">▧</div>

                    <strong>
                        Belum ada gambar proyek.
                    </strong>

                    <p class="small mt-2 mb-0">
                        Siswa belum mengunggah gambar untuk proyek ini.
                    </p>

                </div>

            <?php endif; ?>

        </div>


        <!-- STUDENT INFORMATION -->
        <div class="content-panel">

            <div class="panel-heading">

                <span class="panel-icon">♙</span>

                Informasi Siswa

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Nama Siswa
                </div>

                <div class="detail-value">
                    <?= e($project['full_name']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Jurusan
                </div>

                <div class="detail-value">
                    <?= e($project['major_name']) ?>
                </div>

            </div>


            <?php if (!empty($project['public_slug'])): ?>

                <div class="detail-row">

                    <div class="detail-label">
                        Portofolio Publik
                    </div>

                    <div class="detail-value">

                        <a href="<?= BASE_URL ?>/portfolio.php?slug=<?= rawurlencode($project['public_slug']) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="detail-link">

                            Lihat Portofolio Siswa ↗

                        </a>

                    </div>

                </div>

            <?php endif; ?>

        </div>


        <!-- PROJECT INFORMATION -->
        <div class="content-panel">

            <div class="panel-heading">

                <span class="panel-icon">▦</span>

                Informasi Proyek

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Kategori
                </div>

                <div class="detail-value">
                    <?= e($project['category']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Peran Siswa
                </div>

                <div class="detail-value">
                    <?= e($project['student_role']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Teknologi / Alat
                </div>

                <div class="detail-value regular">
                    <?= e($project['tools_used']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Tanggal Mulai
                </div>

                <div class="detail-value">
                    <?= e(formatDate($project['start_date'])) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Tanggal Selesai
                </div>

                <div class="detail-value">
                    <?= e(formatDate($project['completion_date'])) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Deskripsi
                </div>

                <div class="detail-value regular">
                    <?= e($project['description']) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Link Demo
                </div>

                <div class="detail-value">

                    <?php if ($demoUrl !== null): ?>

                        <a href="<?= e($demoUrl) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="detail-link">

                            Buka Demo Proyek ↗

                        </a>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Repository
                </div>

                <div class="detail-value">

                    <?php if ($repositoryUrl !== null): ?>

                        <a href="<?= e($repositoryUrl) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="detail-link">

                            Buka Repository ↗

                        </a>

                    <?php else: ?>

                        -

                    <?php endif; ?>

                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Tanggal Ditambahkan
                </div>

                <div class="detail-value">
                    <?= e(formatDate($project['created_at'], true)) ?>
                </div>

            </div>


            <div class="detail-row">

                <div class="detail-label">
                    Tanggal Dipublikasikan
                </div>

                <div class="detail-value">
                    <?= e(formatDate($project['published_at'], true)) ?>
                </div>

            </div>

        </div>


        <!-- REVIEW FORM -->
        <?php if (in_array($projectStatus, ['draft', 'pending'], true)): ?>

            <div class="review-panel mb-4">

                <div class="panel-heading">

                    <span class="panel-icon">✓</span>

                    Pemeriksaan Proyek

                </div>

                <p class="review-panel-description">
                    Periksa hasil karya siswa. Pilih keputusan yang sesuai
                    dengan hasil pemeriksaan Admin.
                </p>


                <div class="review-info mb-4">

                    <div class="review-info-icon">ⓘ</div>

                    <div>

                        <strong>Petunjuk Pemeriksaan</strong>

                        <span>
                            Catatan Admin bersifat opsional untuk persetujuan.
                            Catatan wajib diisi apabila meminta revisi atau menolak proyek.
                        </span>

                    </div>

                </div>


                <form id="reviewForm"
                      method="POST"
                      action="<?= BASE_URL ?>/admin/projects/review.php">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($csrfToken) ?>">

                    <input type="hidden"
                           name="id"
                           value="<?= (int) $project['id'] ?>">

                    <input type="hidden"
                           name="action"
                           id="reviewAction"
                           value="">

                    <div class="mb-4">

                        <label for="reviewNote"
                               class="review-note-label">

                            Catatan Admin

                        </label>

                        <textarea
                            name="review_note"
                            id="reviewNote"
                            class="form-control review-note"
                            rows="4"
                            placeholder="Tuliskan catatan pemeriksaan, saran perbaikan, atau alasan keputusan..."
                        ></textarea>

                        <small class="review-help">
                            Wajib diisi untuk keputusan Minta Revisi dan Tolak Proyek.
                        </small>

                    </div>

                </form>


                <!-- ACTION BUTTONS -->
                <div class="review-actions d-flex flex-wrap gap-2">

                    <button
                        type="button"
                        class="btn-approve"
                        onclick="prepareReview('approve')"
                        data-bs-toggle="modal"
                        data-bs-target="#reviewConfirmModal"
                    >
                        Setujui Proyek
                    </button>

                    <button
                        type="button"
                        class="btn-revision"
                        onclick="prepareReview('revision')"
                        data-bs-toggle="modal"
                        data-bs-target="#reviewConfirmModal"
                    >
                        Minta Revisi
                    </button>

                    <button
                        type="button"
                        class="btn-reject"
                        onclick="prepareReview('reject')"
                        data-bs-toggle="modal"
                        data-bs-target="#reviewConfirmModal"
                    >
                        Tolak Proyek
                    </button>

                </div>

            </div>

        <?php else: ?>

            <div class="content-panel">

                <div class="review-info">

                    <div class="review-info-icon">ⓘ</div>

                    <div>

                        <strong>Pemeriksaan Sudah Dilakukan</strong>

                        <span>
                            Proyek ini sudah memiliki status
                            <strong><?= e($statusLabel) ?></strong>.
                            Proyek tidak menampilkan formulir keputusan baru pada halaman ini.
                        </span>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- REVIEW HISTORY -->
        <div class="content-panel">

            <div class="panel-heading">

                <span class="panel-icon">◷</span>

                Riwayat Pemeriksaan

            </div>

            <?php if (count($reviewHistory) > 0): ?>

                <div class="mt-2">

                    <?php foreach ($reviewHistory as $review): ?>

                        <?php
                        $reviewStatus = $review['review_status'];

                        $reviewLabel = $reviewLabels[$reviewStatus]
                            ?? ucfirst($reviewStatus);

                        $reviewColor = $reviewColors[$reviewStatus]
                            ?? 'secondary';
                        ?>

                        <div class="history-item">

                            <span class="history-dot"></span>

                            <div class="history-title">

                                <?= e($reviewLabel) ?>

                                <span class="status-badge bg-<?= e($reviewColor) ?> text-<?= $reviewColor === 'warning' ? 'dark' : 'white' ?> ms-1">
                                    <?= e($reviewLabel) ?>
                                </span>

                            </div>

                            <div class="history-meta">

                                Pemeriksa:
                                <strong>
                                    <?= e($review['reviewer_username'] ?? 'Tidak diketahui') ?>
                                </strong>

                                &nbsp;|&nbsp;

                                <?= e(formatDate($review['reviewed_at'], true)) ?>

                            </div>

                            <?php if (!empty($review['review_notes'])): ?>

                                <div class="history-note">
                                    <?= e($review['review_notes']) ?>
                                </div>

                            <?php else: ?>

                                <div class="text-secondary small">
                                    Tidak ada catatan pemeriksaan.
                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="text-center py-4">

                    <div class="fs-2 mb-2" style="color:#a7b5c7;">
                        ◷
                    </div>

                    <strong class="d-block mb-1" style="color:var(--navy);font-size:12px;">
                        Belum Ada Riwayat Pemeriksaan
                    </strong>

                    <span class="text-secondary small">
                        Riwayat keputusan Admin akan tampil di bagian ini.
                    </span>

                </div>

            <?php endif; ?>

        </div>


        <!-- BACK BUTTON -->
        <div class="d-flex flex-wrap gap-2">

            <a href="<?= BASE_URL ?>/admin/projects/index.php"
               class="btn-back">

                ← Kembali ke Daftar Proyek

            </a>

        </div>


        <!-- FOOTER -->
        <div class="page-footer">

            SIPORSI — Sistem Informasi Portofolio Digital Siswa
            SMK Negeri 4 Banjarmasin

        </div>

    </main>

</div>


<!-- REVIEW CONFIRMATION MODAL -->
<?php if (in_array($projectStatus, ['draft', 'pending'], true)): ?>

<div class="modal fade confirmation-modal"
     id="reviewConfirmModal"
     tabindex="-1"
     aria-labelledby="reviewConfirmModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="reviewConfirmModalLabel">
                    Konfirmasi Pemeriksaan Proyek
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup">
                </button>

            </div>


            <div class="modal-body">

                <div id="reviewModalIcon"
                     class="modal-action-icon approve">
                    ✓
                </div>

                <h6 id="reviewModalTitle"
                    class="fw-bold mb-2"
                    style="color:var(--navy);">

                    Setujui proyek ini?

                </h6>

                <p id="reviewModalDescription"
                   class="text-secondary small mb-0">

                    Proyek akan disetujui dan dapat ditampilkan pada portofolio publik siswa.

                </p>


                <div class="modal-project-info">

                    <small>PROYEK YANG DIPERIKSA</small>

                    <strong>
                        <?= e($project['title']) ?>
                    </strong>

                    <div class="small text-secondary mt-2">
                        Siswa: <?= e($project['full_name']) ?>
                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button"
                        class="btn-modal-cancel"
                        data-bs-dismiss="modal">
                    Batal
                </button>

                <button type="submit"
                        form="reviewForm"
                        id="confirmReviewButton"
                        class="btn-modal-approve">

                    Ya, Setujui Proyek

                </button>

            </div>

        </div>

    </div>

</div>

<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
}

function prepareReview(action) {

    const actionInput = document.getElementById('reviewAction');
    const reviewNote = document.getElementById('reviewNote');

    const modalTitle = document.getElementById('reviewModalTitle');
    const modalDescription = document.getElementById('reviewModalDescription');
    const modalIcon = document.getElementById('reviewModalIcon');
    const confirmButton = document.getElementById('confirmReviewButton');

    actionInput.value = action;

    reviewNote.required = action === 'revision' || action === 'reject';

    if (action === 'approve') {

        modalTitle.textContent = 'Setujui proyek ini?';

        modalDescription.textContent =
            'Proyek akan disetujui dan dapat ditampilkan pada portofolio publik siswa.';

        modalIcon.className = 'modal-action-icon approve';
        modalIcon.textContent = '✓';

        confirmButton.className = 'btn-modal-approve';
        confirmButton.textContent = 'Ya, Setujui Proyek';

    } else if (action === 'revision') {

        modalTitle.textContent = 'Minta revisi proyek ini?';

        modalDescription.textContent =
            'Proyek akan dikembalikan kepada siswa untuk diperbaiki. Catatan Admin wajib diisi.';

        modalIcon.className = 'modal-action-icon revision';
        modalIcon.textContent = '↻';

        confirmButton.className = 'btn-modal-revision';
        confirmButton.textContent = 'Ya, Minta Revisi';

    } else if (action === 'reject') {

        modalTitle.textContent = 'Tolak proyek ini?';

        modalDescription.textContent =
            'Proyek akan ditolak dan tidak ditampilkan pada portofolio publik. Catatan Admin wajib diisi.';

        modalIcon.className = 'modal-action-icon reject';
        modalIcon.textContent = '!';

        confirmButton.className = 'btn-modal-reject';
        confirmButton.textContent = 'Ya, Tolak Proyek';

    }
}
</script>

</body>
</html>
