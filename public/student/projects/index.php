
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

$db = Database::connection();
$csrfToken = Csrf::token();
$userId = (int) $_SESSION['user']['id'];

// Mengambil identitas siswa berdasarkan akun yang sedang login.
$stmt = $db->prepare("
    SELECT id, full_name
    FROM students
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute(['user_id' => $userId]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

// Mengambil seluruh proyek milik siswa,
// termasuk catatan pemeriksaan Admin terakhir.
$stmt = $db->prepare("
    SELECT
        id,
        title,
        description,
        category,
        thumbnail,
        publication_status,
        created_at,

        (
            SELECT pr.review_notes
            FROM project_reviews pr
            WHERE pr.project_id = projects.id
            ORDER BY pr.reviewed_at DESC, pr.id DESC
            LIMIT 1
        ) AS review_notes,

        (
            SELECT pr.reviewed_at
            FROM project_reviews pr
            WHERE pr.project_id = projects.id
            ORDER BY pr.reviewed_at DESC, pr.id DESC
            LIMIT 1
        ) AS reviewed_at

    FROM projects
    WHERE student_id = :student_id
    ORDER BY created_at DESC
");

$stmt->execute([
    'student_id' => $student['id']
]);

$projects = $stmt->fetchAll();

// Label status proyek.
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

// Fungsi keamanan output.
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Fungsi format tanggal.
function formatProjectDate($value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d-m-Y', $timestamp)
        : '-';
}

// Menghitung statistik dari data proyek yang sudah diambil.
$totalProjects = count($projects);
$totalPending = 0;
$totalApproved = 0;
$totalActionNeeded = 0;

foreach ($projects as $project) {

    $status = $project['publication_status'];

    if ($status === 'pending') {
        $totalPending++;
    }

    if ($status === 'approved') {
        $totalApproved++;
    }

    // Hanya draft dan revision yang dapat ditindaklanjuti siswa.
    // Rejected merupakan keputusan akhir Admin.
    if (in_array($status, ['draft', 'revision'], true)) {
        $totalActionNeeded++;
    }
}

// Notifikasi setelah proses.
$successMessage = '';

if (isset($_GET['created'])) {
    $successMessage = 'Proyek berhasil ditambahkan.';
} elseif (isset($_GET['updated'])) {
    $successMessage = 'Proyek berhasil diperbarui.';
} elseif (isset($_GET['submitted'])) {
    $successMessage = 'Proyek berhasil diajukan dan menunggu pemeriksaan Admin.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Proyek Saya | SIPORSI Siswa</title>

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
            max-width: 1250px;
            margin: 0 auto;
            padding: 35px;
        }

        /* WELCOME */

        .welcome-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            padding: 22px 25px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .welcome-label {
            margin-bottom: 6px;
            color: var(--blue);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .welcome-name {
            margin-bottom: 6px;
            color: var(--navy);
            font-size: 19px;
            font-weight: 800;
        }

        .welcome-description {
            margin: 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .welcome-icon {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 53px;
            height: 53px;
            border-radius: 14px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 24px;
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
            line-height: 1.8;
        }

        /* ADD BUTTON */

        .btn-add-project {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 17px;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-add-project:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-bottom: 23px;
        }

        .stat-card {
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            box-shadow: 0 5px 18px rgba(16,46,89,.035);
        }

        .stat-number {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 23px;
            font-weight: 800;
        }

        .stat-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
        }

        /* PANEL */

        .content-panel {
            margin-bottom: 23px;
            padding: 27px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .panel-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 21px;
        }

        .panel-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 19px;
        }

        .panel-heading h2 {
            margin: 0;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .panel-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 10px;
        }

        /* PROJECT GRID */

        .project-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px;
        }

        .project-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            transition: .2s;
        }

        .project-card:hover {
            border-color: #c5d8ed;
            box-shadow: 0 9px 22px rgba(16,46,89,.07);
            transform: translateY(-2px);
        }

        .project-image-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 205px;
            overflow: hidden;
            background: #f1f5fa;
        }

        .project-image {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .3s;
        }

        .project-card:hover .project-image {
            transform: scale(1.025);
        }

        .project-placeholder {
            padding: 20px;
            color: #91a3b9;
            text-align: center;
        }

        .project-placeholder-icon {
            display: block;
            margin-bottom: 9px;
            font-size: 34px;
        }

        .project-placeholder span {
            display: block;
            font-size: 10px;
        }

        .project-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            padding: 19px;
        }

        .project-title {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .project-category {
            display: inline-flex;
            align-self: flex-start;
            margin-bottom: 11px;
            padding: 5px 9px;
            border-radius: 6px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 9px;
            font-weight: 700;
        }

        .project-description {
            flex: 1;
            margin: 0 0 14px;
            color: #66778c;
            font-size: 11px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        /* STATUS */

        .status-badge {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
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
            background: #e4f3fb;
            color: #1769aa;
        }

        .status-rejected {
            background: #fde8e9;
            color: #c23c49;
        }

        .project-date {
            margin-top: 12px;
            padding-top: 11px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 10px;
        }

        .project-date strong {
            color: #465a72;
        }

        /* CARD FOOTER */

        .project-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 14px 19px;
            border-top: 1px solid var(--border);
            background: #fbfcfe;
        }

        .btn-edit-project,
        .btn-submit-project {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 35px;
            padding: 8px 12px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-edit-project {
            border: 1px solid var(--border);
            background: white;
            color: var(--navy);
        }

        .btn-edit-project:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .btn-submit-project {
            border: none;
            background: var(--navy);
            color: white;
        }

        .btn-submit-project:hover {
            background: var(--blue);
            color: white;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 40px 20px;
            border: 1px dashed #cbd7e5;
            border-radius: 11px;
            background: #fafcff;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
            line-height: 1.8;
        }

        .empty-state-icon {
            display: block;
            margin-bottom: 10px;
            color: #a4b4c8;
            font-size: 35px;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 5px;
            color: var(--navy);
            font-size: 13px;
        }

        /* INFORMATION NOTICE */

        .info-notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-top: 21px;
            padding: 14px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .notice-icon {
            color: var(--blue);
            font-size: 17px;
        }

        .info-notice strong {
            display: block;
            margin-bottom: 3px;
            color: var(--navy);
            font-size: 10px;
        }

        .info-notice span {
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
        }

        /* MODAL */

        .confirmation-modal .modal-content {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: 0 20px 55px rgba(16,46,89,.18);
        }

        .confirmation-modal .modal-header {
            padding: 19px 23px;
            border-bottom: 1px solid var(--border);
            background: #f9fbfe;
        }

        .confirmation-modal .modal-title {
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .confirmation-modal .modal-body {
            padding: 24px;
        }

        .modal-warning-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 47px;
            height: 47px;
            margin-bottom: 15px;
            border-radius: 12px;
            background: #fff4d9;
            color: #956b0a;
            font-size: 22px;
            font-weight: 800;
        }

        .modal-project-info {
            margin-top: 16px;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f9fbfe;
        }

        .modal-project-info small {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 9px;
        }

        .modal-project-info strong {
            color: var(--navy);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .confirmation-modal .modal-footer {
            padding: 14px 23px;
            border-top: 1px solid var(--border);
        }

        .btn-modal-cancel,
        .btn-modal-submit {
            min-height: 38px;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 700;
        }

        .btn-modal-cancel {
            border: 1px solid var(--border);
            background: white;
            color: var(--muted);
        }

        .btn-modal-submit {
            border: none;
            background: var(--navy);
            color: white;
        }

        .btn-modal-submit:hover {
            background: var(--blue);
            color: white;
        }

        /* FOOTER */

        .page-footer {
            margin-top: 25px;
            color: #8996a8;
            font-size: 10px;
            text-align: center;
        }

        /* RESPONSIVE */

        .mobile-toggle {
            display: none;
        }

        @media (max-width: 1100px) {

            .project-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

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

            .student-topbar {
                padding: 15px 20px;
            }

            .student-content {
                padding: 25px 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 650px) {

            .project-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 576px) {

            .student-content {
                padding: 20px 14px;
            }

            .page-title {
                font-size: 21px;
            }

            .content-panel {
                padding: 20px 16px;
            }

            .welcome-panel {
                padding: 17px;
            }

            .stats-grid {
                gap: 9px;
            }

            .stat-card {
                padding: 14px;
            }

            .stat-number {
                font-size: 20px;
            }

            .project-image-wrap {
                height: 190px;
            }

            .project-body {
                padding: 16px;
            }

            .project-footer {
                padding: 12px 16px;
            }

        }

    </style>

</head>

<body>


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
       class="sidebar-menu">

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
       class="sidebar-menu active">

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
                SIPORSI / Portofolio Saya / Proyek dan Karya
            </span>

        </div>


        <a href="<?= BASE_URL ?>/student/dashboard.php"
           class="btn btn-sm btn-outline-primary">

            Dashboard

        </a>

    </header>


    <!-- CONTENT -->
    <main class="student-content">


        <!-- WELCOME -->
        <div class="welcome-panel">

            <div>

                <div class="welcome-label">
                    Karya dan Kompetensi
                </div>

                <h1 class="welcome-name">
                    Halo, <?= e($student['full_name']) ?>!
                </h1>

                <p class="welcome-description">
                    Dokumentasikan karya, proyek, dan pengalaman
                    yang menjadi bagian dari perjalanan kompetensi Anda.
                </p>

            </div>


            <div class="welcome-icon">
                ▤
            </div>

        </div>


        <!-- PAGE HEADER -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

            <div>

                <h2 class="page-title">
                    Proyek Saya
                </h2>

                <p class="page-description">
                    Kelola hasil karya dan pengalaman proyek Anda.
                </p>

            </div>


            <a href="<?= BASE_URL ?>/student/projects/create.php"
               class="btn-add-project">

                + Tambah Proyek

            </a>

        </div>


        <!-- SUCCESS NOTIFICATION -->
        <?php if ($successMessage !== ''): ?>

            <div class="alert alert-success alert-dismissible fade show rounded-3 small"
                 role="alert">

                <strong>Berhasil!</strong>
                <?= e($successMessage) ?>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close">
                </button>

            </div>

        <?php endif; ?>


        <!-- PROJECT STATISTICS -->
        <div class="stats-grid">

            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalProjects ?>
                </span>

                <span class="stat-label">
                    Total Proyek
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalPending ?>
                </span>

                <span class="stat-label">
                    Menunggu Pemeriksaan
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalApproved ?>
                </span>

                <span class="stat-label">
                    Proyek Disetujui
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalActionNeeded ?>
                </span>

                <span class="stat-label">
                    Draft / Perlu Revisi
                </span>

            </div>

        </div>


        <!-- PROJECT LIST -->
        <section class="content-panel">


            <div class="panel-heading">

                <span class="panel-icon">▤</span>

                <div>

                    <h2>Daftar Proyek dan Karya</h2>

                    <p>
                        Semua karya yang pernah Anda tambahkan ke SIPORSI.
                    </p>

                </div>

            </div>


            <?php if (count($projects) > 0): ?>


                <div class="project-grid">


                    <?php foreach ($projects as $project): ?>

                        <?php
                        $projectStatus = $project['publication_status'];

                        $statusLabel = $statusLabels[$projectStatus]
                            ?? 'Tidak diketahui';

                        $statusColor = $statusColors[$projectStatus]
                            ?? 'secondary';

                        $description = (string) ($project['description'] ?? '');

                        $descriptionShort = mb_strimwidth(
                            $description,
                            0,
                            150,
                            '...'
                        );
                        ?>


                        <article class="project-card">


                            <!-- PROJECT IMAGE -->
                            <div class="project-image-wrap">

                                <?php if (!empty($project['thumbnail'])): ?>

                                    <img
                                        src="<?= BASE_URL . '/' . e($project['thumbnail']) ?>"
                                        class="project-image"
                                        alt="Gambar proyek <?= e($project['title']) ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="project-placeholder">

                                        <span class="project-placeholder-icon">
                                            ▧
                                        </span>

                                        <span>
                                            Belum ada gambar proyek
                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- PROJECT BODY -->
                            <div class="project-body">


                                <h3 class="project-title">
                                    <?= e($project['title']) ?>
                                </h3>


                                <span class="project-category">

                                    <?= e($project['category'] ?? 'Tanpa kategori') ?>

                                </span>


                                <p class="project-description">

                                    <?= e($descriptionShort) ?>

                                </p>


                                <span class="status-badge status-<?= e($projectStatus) ?>">

                                    <?= e($statusLabel) ?>

                                </span>


                                <div class="project-date">

                                    <strong>Tanggal ditambahkan:</strong>

                                    <?= e(formatProjectDate($project['created_at'])) ?>

                                </div>

                                <?php if (
                                    in_array($projectStatus, ['revision', 'rejected'], true) &&
                                    !empty($project['review_notes'])
                                ): ?>

                                    <div class="alert <?= $projectStatus === 'revision' ? 'alert-info' : 'alert-danger' ?> py-2 px-3 mt-3 mb-0 small">

                                        <strong>Catatan Admin</strong>

                                        <div class="mt-1">
                                            <?= nl2br(e($project['review_notes'])) ?>
                                        </div>

                                        <?php if (!empty($project['reviewed_at'])): ?>

                                            <small class="d-block mt-2">
                                                Diperiksa:
                                                <?= e(formatProjectDate($project['reviewed_at'])) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                            <!-- PROJECT FOOTER -->
                            <div class="project-footer">

                                <?php if (in_array($projectStatus, ['draft', 'revision', 'approved'], true)): ?>

                                    <a
                                        href="<?= BASE_URL ?>/student/projects/edit.php?id=<?= (int) $project['id'] ?>"
                                        class="btn-edit-project"
                                    >
                                        Edit Proyek
                                    </a>

                                <?php elseif ($projectStatus === 'pending'): ?>

                                    <span class="small text-muted">
                                        Proyek sedang diperiksa Admin.
                                        Perubahan sementara dikunci.
                                    </span>

                                <?php elseif ($projectStatus === 'rejected'): ?>

                                    <span class="small text-danger">
                                        Pengajuan ditolak.
                                        Anda dapat mengajukan proyek baru melalui tombol Tambah Proyek.
                                    </span>

                                <?php endif; ?>


                                <?php if (in_array($projectStatus, ['draft', 'revision'], true)): ?>

                                    <form
                                        method="POST"
                                        action="<?= BASE_URL ?>/student/projects/submit.php"
                                        class="submit-project-form"
                                        data-project-title="<?= e($project['title']) ?>"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($csrfToken) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $project['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-submit-project"
                                        >
                                            Ajukan Proyek →
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-state">

                    <span class="empty-state-icon">
                        ▤
                    </span>

                    <strong>
                        Belum Ada Proyek
                    </strong>

                    Anda belum memiliki proyek dalam portofolio.

                    <div class="mt-3">

                        <a href="<?= BASE_URL ?>/student/projects/create.php"
                           class="btn-add-project">

                            + Tambahkan Proyek Pertama

                        </a>

                    </div>

                </div>


            <?php endif; ?>


        </section>


        <!-- INFORMATION NOTICE -->
        <div class="info-notice">

            <div class="notice-icon">ⓘ</div>

            <div>

                <strong>Informasi Pemeriksaan Proyek</strong>

                <span>
                    Proyek yang Anda ajukan akan diperiksa oleh Admin sekolah.
                    Hanya proyek dengan status Disetujui yang dapat ditampilkan
                    pada portofolio publik Anda.
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


<!-- SUBMIT CONFIRMATION MODAL -->
<div class="modal fade confirmation-modal"
     id="submitProjectModal"
     tabindex="-1"
     aria-labelledby="submitProjectModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title"
                    id="submitProjectModalLabel">

                    Konfirmasi Pengajuan Proyek

                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup">
                </button>

            </div>


            <div class="modal-body">

                <div class="modal-warning-icon">
                    ↑
                </div>


                <h6 class="fw-bold mb-2"
                    style="color:var(--navy);">

                    Ajukan proyek untuk diperiksa?

                </h6>


                <p class="text-secondary small mb-0">

                    Proyek akan dikirim kepada Admin sekolah untuk diperiksa.
                    Setelah diajukan, status proyek berubah menjadi Menunggu Pemeriksaan.

                </p>


                <div class="modal-project-info">

                    <small>PROYEK YANG AKAN DIAJUKAN</small>

                    <strong id="submitProjectTitle">
                        -
                    </strong>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button"
                        class="btn-modal-cancel"
                        data-bs-dismiss="modal">

                    Batal

                </button>


                <button type="button"
                        class="btn-modal-submit"
                        id="confirmSubmitProject">

                    Ya, Ajukan Proyek

                </button>

            </div>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

// Sidebar untuk tampilan mobile.
function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
}


// Konfirmasi pengajuan proyek menggunakan modal Bootstrap.
let pendingProjectForm = null;

const submitProjectModal = document.getElementById('submitProjectModal');

const bootstrapSubmitModal = new bootstrap.Modal(submitProjectModal);

document.querySelectorAll('.submit-project-form').forEach(function(form) {

    form.addEventListener('submit', function(event) {

        event.preventDefault();

        pendingProjectForm = this;

        const projectTitle = this.getAttribute('data-project-title');

        document.getElementById('submitProjectTitle').textContent = projectTitle;

        bootstrapSubmitModal.show();

    });

});


// Proses form hanya setelah siswa menekan tombol konfirmasi.
document.getElementById('confirmSubmitProject').addEventListener('click', function() {

    if (pendingProjectForm) {

        pendingProjectForm.submit();

        pendingProjectForm = null;

    }

});

</script>

</body>
</html>
