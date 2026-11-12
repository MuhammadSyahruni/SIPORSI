
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

$db = Database::connection();
$csrfToken = Csrf::token();

// Mengambil identitas siswa berdasarkan akun yang sedang login.
$stmt = $db->prepare("
    SELECT id FROM students
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $_SESSION['user']['id']
]);

$student = $stmt->fetch();

if (!$student) {
    exit('Data siswa tidak ditemukan.');
}

// Mengambil seluruh pengalaman milik siswa.
$experienceStmt = $db->prepare("
    SELECT *
    FROM experiences
    WHERE student_id = :student_id
    ORDER BY start_date DESC, created_at DESC
");

$experienceStmt->execute([
    'student_id' => $student['id']
]);

$experiences = $experienceStmt->fetchAll();

// Fungsi keamanan output.
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Format tanggal pengalaman.
function experienceDate($value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('d-m-Y', $timestamp)
        : '-';
}

// Menghitung jumlah pengalaman.
$totalExperiences = count($experiences);

// Notifikasi.
$successMessage = '';

if (isset($_GET['created'])) {
    $successMessage = 'Pengalaman berhasil ditambahkan.';
} elseif (isset($_GET['updated'])) {
    $successMessage = 'Pengalaman berhasil diperbarui.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Pengalaman berhasil dihapus.';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengalaman Saya | SIPORSI Siswa</title>

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

        /* BUTTON ADD */

        .btn-add-experience {
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

        .btn-add-experience:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-bottom: 23px;
        }

        .stat-card {
            padding: 18px 20px;
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

        /* CONTENT PANEL */

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

        /* EXPERIENCE GRID */

        .experience-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .experience-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: white;
            transition: .2s;
        }

        .experience-card:hover {
            border-color: #c5d8ed;
            box-shadow: 0 9px 22px rgba(16,46,89,.07);
            transform: translateY(-2px);
        }

        .experience-card-header {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 15px;
        }

        .experience-icon {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 18px;
        }

        .experience-company {
            margin: 0 0 5px;
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .experience-position {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 6px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 9px;
            font-weight: 700;
        }

        .experience-description {
            flex: 1;
            margin: 0 0 17px;
            color: #66778c;
            font-size: 11px;
            line-height: 1.8;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        /* EXPERIENCE DETAILS */

        .experience-details {
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f9fbfe;
        }

        .experience-detail-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 7px 0;
            border-bottom: 1px solid var(--border);
        }

        .experience-detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
        }

        .detail-value {
            color: var(--navy);
            font-size: 10px;
            font-weight: 600;
            text-align: right;
            overflow-wrap: anywhere;
        }

        /* ACTIONS */

        .experience-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .btn-edit-experience,
        .btn-delete-experience {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 13px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-edit-experience {
            border: 1px solid var(--border);
            background: white;
            color: var(--navy);
        }

        .btn-edit-experience:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .btn-delete-experience {
            border: 1px solid #edc4c8;
            background: white;
            color: #c23c49;
        }

        .btn-delete-experience:hover {
            background: #c23c49;
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

        /* NOTICE */

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

        /* DELETE MODAL */

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
            background: #fdebed;
            color: #c23c49;
            font-size: 22px;
            font-weight: 800;
        }

        .modal-experience-info {
            margin-top: 16px;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f9fbfe;
        }

        .modal-experience-info small {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 9px;
        }

        .modal-experience-info strong {
            color: var(--navy);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .confirmation-modal .modal-footer {
            padding: 14px 23px;
            border-top: 1px solid var(--border);
        }

        .btn-modal-cancel,
        .btn-modal-delete {
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

        .btn-modal-delete {
            border: none;
            background: #c23c49;
            color: white;
        }

        .btn-modal-delete:hover {
            background: #a82e3a;
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

            .experience-grid {
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

        }

        @media (max-width: 650px) {

            .experience-grid {
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

            .experience-card {
                padding: 16px;
            }

            .experience-detail-row {
                gap: 8px;
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
       class="sidebar-menu">

        <span class="sidebar-icon">▤</span>
        Proyek dan Karya

    </a>


    <a href="<?= BASE_URL ?>/student/experiences/index.php"
       class="sidebar-menu active">

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
                SIPORSI / Portofolio Saya / Pengalaman PKL
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
                    Pengalaman dan Pengembangan Diri
                </div>

                <h1 class="welcome-name">
                    Pengalaman Saya
                </h1>

                <p class="welcome-description">
                    Dokumentasikan pengalaman PKL, magang, dan kegiatan
                    yang mendukung perkembangan kompetensi Anda.
                </p>

            </div>


            <div class="welcome-icon">
                ▣
            </div>

        </div>


        <!-- PAGE HEADER -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

            <div>

                <h2 class="page-title">
                    Riwayat Pengalaman
                </h2>

                <p class="page-description">
                    Kelola pengalaman PKL, magang, dan kegiatan Anda.
                </p>

            </div>


            <a href="<?= BASE_URL ?>/student/experiences/create.php"
               class="btn-add-experience">

                + Tambah Pengalaman

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


        <!-- STATISTICS -->
        <div class="stats-grid">

            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalExperiences ?>
                </span>

                <span class="stat-label">
                    Total Pengalaman
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= count(array_filter($experiences, function ($item) {
                        return !empty($item['proof_file']);
                    })) ?>
                </span>

                <span class="stat-label">
                    Pengalaman dengan Bukti
                </span>

            </div>

        </div>


        <!-- EXPERIENCE LIST -->
        <section class="content-panel">


            <div class="panel-heading">

                <span class="panel-icon">▣</span>

                <div>

                    <h2>Daftar Pengalaman Saya</h2>

                    <p>
                        Seluruh pengalaman PKL, magang, dan kegiatan yang telah ditambahkan.
                    </p>

                </div>

            </div>


            <?php if (!empty($experiences)): ?>


                <div class="experience-grid">


                    <?php foreach ($experiences as $index => $experience): ?>


                        <article class="experience-card">


                            <!-- EXPERIENCE HEADER -->
                            <div class="experience-card-header">

                                <div class="experience-icon">
                                    ▣
                                </div>

                                <div>

                                    <h3 class="experience-company">
                                        <?= e($experience['company_name']) ?>
                                    </h3>

                                    <span class="experience-position">

                                        <?= e($experience['position'] ?: 'Pengalaman') ?>

                                    </span>

                                </div>

                            </div>


                            <!-- DESCRIPTION -->
                            <p class="experience-description">

                                <?= e($experience['description'] ?: 'Belum ada deskripsi pengalaman.') ?>

                            </p>


                            <!-- EXPERIENCE DETAILS -->
                            <div class="experience-details">


                                <div class="experience-detail-row">

                                    <span class="detail-label">
                                        Periode
                                    </span>

                                    <span class="detail-value">

                                        <?= e(experienceDate($experience['start_date'])) ?>

                                        s.d.

                                        <?= !empty($experience['end_date'])
                                            ? e(experienceDate($experience['end_date']))
                                            : 'Sekarang' ?>

                                    </span>

                                </div>


                                <div class="experience-detail-row">

                                    <span class="detail-label">
                                        Pembimbing
                                    </span>

                                    <span class="detail-value">
                                        <?= e($experience['supervisor_name'] ?: '-') ?>
                                    </span>

                                </div>


                                <div class="experience-detail-row">

                                    <span class="detail-label">
                                        Bukti Pengalaman
                                    </span>

                                    <span class="detail-value">

                                        <?= !empty($experience['proof_file'])
                                            ? 'Tersedia'
                                            : 'Belum diunggah' ?>

                                    </span>

                                </div>


                            </div>


                            <!-- ACTIONS -->
                            <div class="experience-actions">


                                <a
                                    href="<?= BASE_URL ?>/student/experiences/edit.php?id=<?= (int) $experience['id'] ?>"
                                    class="btn-edit-experience"
                                >

                                    Edit Pengalaman

                                </a>


                                <button
                                    type="button"
                                    class="btn-delete-experience"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteExperienceModal"
                                    data-experience-id="<?= (int) $experience['id'] ?>"
                                    data-experience-name="<?= e($experience['company_name']) ?>"
                                >

                                    Hapus

                                </button>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-state">

                    <span class="empty-state-icon">
                        ▣
                    </span>

                    <strong>
                        Belum Ada Pengalaman
                    </strong>

                    Anda belum menambahkan pengalaman PKL, magang,
                    atau kegiatan lainnya.

                    <div class="mt-3">

                        <a href="<?= BASE_URL ?>/student/experiences/create.php"
                           class="btn-add-experience">

                            + Tambahkan Pengalaman Pertama

                        </a>

                    </div>

                </div>


            <?php endif; ?>


        </section>


        <!-- INFORMATION NOTICE -->
        <div class="info-notice">

            <div class="notice-icon">ⓘ</div>

            <div>

                <strong>Informasi Portofolio Pengalaman</strong>

                <span>
                    Pengalaman yang Anda simpan akan menjadi bagian dari
                    portofolio digital. Pastikan informasi tempat kegiatan,
                    posisi, periode, dan deskripsi ditulis dengan benar.
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


<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade confirmation-modal"
     id="deleteExperienceModal"
     tabindex="-1"
     aria-labelledby="deleteExperienceModalLabel"
     aria-hidden="true">


    <div class="modal-dialog modal-dialog-centered">


        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title"
                    id="deleteExperienceModalLabel">

                    Konfirmasi Hapus Pengalaman

                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup">
                </button>

            </div>


            <form method="POST"
                  action="<?= BASE_URL ?>/student/experiences/delete.php">


                <div class="modal-body">

                    <div class="modal-warning-icon">
                        !
                    </div>


                    <h6 class="fw-bold mb-2"
                        style="color:var(--navy);">

                        Hapus pengalaman ini?

                    </h6>


                    <p class="text-secondary small mb-0">

                        Data pengalaman ini akan dihapus dari portofolio Anda.
                        Pastikan Anda memang ingin melanjutkan tindakan ini.

                    </p>


                    <div class="modal-experience-info">

                        <small>PENGALAMAN YANG AKAN DIHAPUS</small>

                        <strong id="deleteExperienceName">
                            -
                        </strong>

                    </div>

                </div>


                <div class="modal-footer">


                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($csrfToken) ?>">


                    <input type="hidden"
                           name="id"
                           id="deleteExperienceId"
                           value="">


                    <button type="button"
                            class="btn-modal-cancel"
                            data-bs-dismiss="modal">

                        Batal

                    </button>


                    <button type="submit"
                            class="btn-modal-delete">

                        Ya, Hapus Pengalaman

                    </button>


                </div>


            </form>


        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script>

// Sidebar untuk tampilan mobile.
function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
}


// Mengisi data pengalaman pada modal konfirmasi hapus.
const deleteExperienceModal = document.getElementById('deleteExperienceModal');

deleteExperienceModal.addEventListener('show.bs.modal', function (event) {

    const button = event.relatedTarget;

    const experienceId = button.getAttribute('data-experience-id');
    const experienceName = button.getAttribute('data-experience-name');

    document.getElementById('deleteExperienceId').value = experienceId;

    document.getElementById('deleteExperienceName').textContent = experienceName;

});

</script>


</body>
</html>
