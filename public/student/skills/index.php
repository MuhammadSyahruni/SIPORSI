
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

$studentId = (int) $student['id'];
$errors = [];

// Proses tambah, edit, dan hapus keterampilan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $action = $_POST['action'] ?? '';

    try {

        // TAMBAH KETERAMPILAN
        if ($action === 'add') {

            $skillId = filter_var(
                $_POST['skill_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $proficiency = $_POST['proficiency'] ?? 'beginner';
            $description = trim($_POST['description'] ?? '');

            $allowedLevels = [
                'beginner',
                'intermediate',
                'advanced',
                'expert'
            ];

            if (!$skillId || $skillId < 1) {
                $errors[] = 'Silakan pilih keterampilan.';
            }

            if (!in_array($proficiency, $allowedLevels, true)) {
                $errors[] = 'Tingkat kemampuan tidak valid.';
            }

            if (empty($errors)) {

                $check = $db->prepare("
                    SELECT id FROM skills
                    WHERE id = :id AND is_active = 1
                    LIMIT 1
                ");

                $check->execute(['id' => $skillId]);

                if (!$check->fetch()) {

                    $errors[] = 'Keterampilan tidak tersedia.';

                } else {

                    $duplicate = $db->prepare("
                        SELECT id FROM student_skills
                        WHERE student_id = :student_id
                        AND skill_id = :skill_id
                        LIMIT 1
                    ");

                    $duplicate->execute([
                        'student_id' => $studentId,
                        'skill_id' => $skillId
                    ]);

                    if ($duplicate->fetch()) {

                        $errors[] = 'Keterampilan tersebut sudah ada di profil Anda.';

                    } else {

                        $insert = $db->prepare("
                            INSERT INTO student_skills
                            (student_id, skill_id, proficiency, description)
                            VALUES
                            (:student_id, :skill_id, :proficiency, :description)
                        ");

                        $insert->execute([
                            'student_id' => $studentId,
                            'skill_id' => $skillId,
                            'proficiency' => $proficiency,
                            'description' => $description !== '' ? $description : null
                        ]);

                        header('Location: ' . BASE_URL . '/student/skills/index.php?added=1');
                        exit;
                    }
                }
            }

        // EDIT KETERAMPILAN
        } elseif ($action === 'update') {

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $proficiency = $_POST['proficiency'] ?? '';
            $description = trim($_POST['description'] ?? '');

            $allowedLevels = [
                'beginner',
                'intermediate',
                'advanced',
                'expert'
            ];

            if (!$id || $id < 1) {
                $errors[] = 'Data keterampilan tidak valid.';
            }

            if (!in_array($proficiency, $allowedLevels, true)) {
                $errors[] = 'Tingkat kemampuan tidak valid.';
            }

            if (empty($errors)) {

                $update = $db->prepare("
                    UPDATE student_skills
                    SET proficiency = :proficiency,
                        description = :description
                    WHERE id = :id
                    AND student_id = :student_id
                ");

                $update->execute([
                    'proficiency' => $proficiency,
                    'description' => $description !== '' ? $description : null,
                    'id' => $id,
                    'student_id' => $studentId
                ]);

                header('Location: ' . BASE_URL . '/student/skills/index.php?updated=1');
                exit;
            }

        // HAPUS KETERAMPILAN DARI PROFIL
        } elseif ($action === 'delete') {

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {

                $errors[] = 'Data keterampilan tidak valid.';

            } else {

                $delete = $db->prepare("
                    DELETE FROM student_skills
                    WHERE id = :id AND student_id = :student_id
                ");

                $delete->execute([
                    'id' => $id,
                    'student_id' => $studentId
                ]);

                header('Location: ' . BASE_URL . '/student/skills/index.php?deleted=1');
                exit;
            }
        }

    } catch (PDOException $exception) {

        error_log($exception->getMessage());
        $errors[] = 'Terjadi kesalahan saat memproses keterampilan.';

    }
}

// Mengambil daftar keterampilan aktif yang dapat dipilih.
$availableSkills = $db->query("
    SELECT id, skill_name, skill_category
    FROM skills
    WHERE is_active = 1
    ORDER BY skill_category, skill_name
")->fetchAll();

// Mengambil keterampilan yang sudah dimiliki siswa.
$stmt = $db->prepare("
    SELECT
        ss.id,
        ss.skill_id,
        ss.proficiency,
        ss.description,
        sk.skill_name,
        sk.skill_category,
        sk.is_active
    FROM student_skills ss
    INNER JOIN skills sk ON sk.id = ss.skill_id
    WHERE ss.student_id = :student_id
    ORDER BY sk.skill_category, sk.skill_name
");

$stmt->execute(['student_id' => $studentId]);
$mySkills = $stmt->fetchAll();

// Fungsi keamanan output.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$levelLabels = [
    'beginner' => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced' => 'Mahir',
    'expert' => 'Ahli'
];

// Pesan notifikasi.
$successMessage = '';

if (isset($_GET['added'])) {
    $successMessage = 'Keterampilan berhasil ditambahkan ke profil.';
} elseif (isset($_GET['updated'])) {
    $successMessage = 'Keterampilan berhasil diperbarui.';
} elseif (isset($_GET['deleted'])) {
    $successMessage = 'Keterampilan berhasil dihapus dari profil.';
}

$availableCount = count($availableSkills);
$mySkillsCount = count($mySkills);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Keterampilan Saya | SIPORSI Siswa</title>

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
            line-height: 1.8;
        }

        /* PROFILE WELCOME */

        .welcome-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 23px;
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

        /* PANELS */

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
            margin-bottom: 20px;
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

        /* FORM */

        .form-label {
            margin-bottom: 8px;
            color: #35465e;
            font-size: 12px;
            font-weight: 600;
        }

        .required-mark {
            color: #dc3545;
        }

        .form-control,
        .form-select {
            min-height: 44px;
            padding: 11px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: #f9fbfe;
            color: var(--text);
            font-size: 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.1);
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
            line-height: 1.7;
        }

        .form-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .btn-submit {
            min-height: 42px;
            padding: 10px 17px;
            border: none;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 11px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-submit:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        /* FORM NOTICE */

        .form-notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-top: 20px;
            padding: 14px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .notice-icon {
            color: var(--blue);
            font-size: 17px;
        }

        .form-notice strong {
            display: block;
            margin-bottom: 3px;
            color: var(--navy);
            font-size: 10px;
        }

        .form-notice span {
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
        }

        /* SKILL LIST */

        .skills-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .skill-card {
            height: 100%;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: white;
            transition: .2s;
        }

        .skill-card:hover {
            border-color: #c5d8ed;
            box-shadow: 0 8px 20px rgba(16,46,89,.06);
            transform: translateY(-2px);
        }

        .skill-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .skill-name {
            margin: 0 0 5px;
            color: var(--navy);
            font-size: 14px;
            font-weight: 800;
            line-height: 1.5;
        }

        .skill-category {
            color: var(--muted);
            font-size: 10px;
        }

        .skill-symbol {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            border-radius: 9px;
            background: #eaf2fc;
            color: var(--blue);
            font-size: 15px;
            font-weight: 700;
        }

        .skill-unavailable {
            display: inline-block;
            margin-top: 9px;
            padding: 5px 8px;
            border-radius: 6px;
            background: #edf0f3;
            color: #657386;
            font-size: 9px;
            font-weight: 700;
        }

        .skill-divider {
            margin: 15px 0;
            border: 0;
            border-top: 1px solid var(--border);
        }

        .skill-form .form-label {
            font-size: 11px;
        }

        .skill-form .form-control,
        .skill-form .form-select {
            min-height: 40px;
            font-size: 11px;
        }

        .skill-form textarea.form-control {
            min-height: 78px;
        }

        .skill-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 13px;
        }

        .btn-update {
            min-height: 36px;
            padding: 8px 13px;
            border: none;
            border-radius: 7px;
            background: var(--navy);
            color: white;
            font-size: 10px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-update:hover {
            background: var(--blue);
            color: white;
        }

        .btn-delete {
            min-height: 36px;
            padding: 8px 12px;
            border: 1px solid #edc4c8;
            border-radius: 7px;
            background: white;
            color: #c23c49;
            font-size: 10px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-delete:hover {
            background: #c23c49;
            color: white;
        }

        /* EMPTY STATE */

        .empty-state {
            padding: 32px 20px;
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
            font-size: 30px;
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
            background: #fdebed;
            color: #c23c49;
            font-size: 22px;
            font-weight: 800;
        }

        .modal-skill-info {
            margin-top: 16px;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: #f9fbfe;
        }

        .modal-skill-info small {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 9px;
        }

        .modal-skill-info strong {
            color: var(--navy);
            font-size: 12px;
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

            .skills-list {
                grid-template-columns: 1fr;
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

            .skill-card {
                padding: 16px;
            }

            .skill-actions {
                align-items: stretch;
            }

            .skill-actions form {
                flex: 1;
            }

            .btn-update,
            .btn-delete {
                width: 100%;
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
       class="sidebar-menu active">

        <span class="sidebar-icon">✦</span>
        Keterampilan

    </a>


    <a href="<?= BASE_URL ?>/student/projects/index.php"
       class="sidebar-menu">

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
                SIPORSI / Portofolio Saya / Keterampilan
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
                    Pengembangan Kompetensi
                </div>

                <h1 class="welcome-name">
                    Halo, <?= e($student['full_name']) ?>!
                </h1>

                <p class="welcome-description">
                    Kelola keahlian dan kemampuan yang ingin ditampilkan
                    dalam portofolio digital Anda.
                </p>

            </div>


            <div class="welcome-icon">
                ✦
            </div>

        </div>


        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h2 class="page-title">
                Keterampilan Saya
            </h2>

            <p class="page-description">
                Tambahkan dan perbarui kemampuan yang mencerminkan kompetensi Anda.
            </p>

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


        <!-- ERROR NOTIFICATION -->
        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger alert-dismissible fade show rounded-3 small"
                 role="alert">

                <strong class="d-block mb-2">
                    Permintaan belum berhasil diproses.
                </strong>

                <ul class="mb-0 ps-3">

                    <?php foreach ($errors as $error): ?>

                        <li><?= e($error) ?></li>

                    <?php endforeach; ?>

                </ul>

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
                    <?= $mySkillsCount ?>
                </span>

                <span class="stat-label">
                    Keterampilan di Profil Saya
                </span>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $availableCount ?>
                </span>

                <span class="stat-label">
                    Keterampilan Tersedia
                </span>

            </div>

        </div>


        <!-- ADD SKILL PANEL -->
        <section class="content-panel">

            <div class="panel-heading">

                <span class="panel-icon">＋</span>

                <div>

                    <h2>Tambah Keterampilan</h2>

                    <p>
                        Pilih keterampilan yang sesuai dengan kemampuan Anda.
                    </p>

                </div>

            </div>


            <?php if (!empty($availableSkills)): ?>

                <form method="POST">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($csrfToken) ?>">

                    <input type="hidden"
                           name="action"
                           value="add">


                    <!-- SKILL SELECT -->
                    <div class="mb-3">

                        <label class="form-label">
                            Pilih Keterampilan
                            <span class="required-mark">*</span>
                        </label>

                        <select name="skill_id"
                                class="form-select"
                                required>

                            <option value="">
                                -- Pilih Keterampilan --
                            </option>

                            <?php foreach ($availableSkills as $item): ?>

                                <option value="<?= (int) $item['id'] ?>">

                                    <?= e($item['skill_name']) ?>
                                    — <?= e($item['skill_category']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <small class="form-help">
                            Pilih salah satu keterampilan yang tersedia dalam daftar SIPORSI.
                        </small>

                    </div>


                    <!-- PROFICIENCY -->
                    <div class="mb-3">

                        <label class="form-label">
                            Tingkat Kemampuan
                            <span class="required-mark">*</span>
                        </label>

                        <select name="proficiency"
                                class="form-select"
                                required>

                            <option value="beginner">Pemula</option>

                            <option value="intermediate">Menengah</option>

                            <option value="advanced">Mahir</option>

                            <option value="expert">Ahli</option>

                        </select>

                        <small class="form-help">
                            Pilih tingkat kemampuan yang paling sesuai dengan
                            pengalaman dan penguasaan Anda.
                        </small>

                    </div>


                    <!-- DESCRIPTION -->
                    <div class="mb-3">

                        <label class="form-label">
                            Deskripsi Kemampuan
                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="4"
                            placeholder="Jelaskan kemampuan atau pengalaman Anda..."
                        ></textarea>

                        <small class="form-help">
                            Berikan penjelasan singkat mengenai kemampuan
                            atau pengalaman Anda dalam bidang tersebut.
                        </small>

                    </div>


                    <!-- NOTICE -->
                    <div class="form-notice mb-4">

                        <div class="notice-icon">ⓘ</div>

                        <div>

                            <strong>Informasi Portofolio</strong>

                            <span>
                                Keterampilan yang ditambahkan akan menjadi bagian
                                dari profil kompetensi dan dapat ditampilkan pada
                                portofolio publik Anda.
                            </span>

                        </div>

                    </div>


                    <button type="submit"
                            class="btn-submit">

                        Tambahkan ke Profil →

                    </button>

                </form>

            <?php else: ?>

                <div class="empty-state">

                    <span class="empty-state-icon">✦</span>

                    Belum ada pilihan keterampilan yang tersedia.
                    Silakan hubungi Admin sekolah.

                </div>

            <?php endif; ?>

        </section>


        <!-- MY SKILLS PANEL -->
        <section class="content-panel">


            <div class="panel-heading">

                <span class="panel-icon">◇</span>

                <div>

                    <h2>Daftar Keterampilan Saya</h2>

                    <p>
                        Perbarui tingkat kemampuan dan deskripsi keterampilan Anda.
                    </p>

                </div>

            </div>


            <?php if (count($mySkills) > 0): ?>

                <div class="skills-list">

                    <?php foreach ($mySkills as $item): ?>


                        <article class="skill-card">


                            <!-- CARD HEADER -->
                            <div class="skill-card-top">

                                <div>

                                    <h3 class="skill-name">
                                        <?= e($item['skill_name']) ?>
                                    </h3>

                                    <span class="skill-category">
                                        <?= e($item['skill_category'] ?? '-') ?>
                                    </span>

                                    <?php if ((int) $item['is_active'] === 0): ?>

                                        <div>

                                            <span class="skill-unavailable">
                                                Keterampilan Tidak Tersedia
                                            </span>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <span class="skill-symbol">
                                    ✦
                                </span>

                            </div>


                            <hr class="skill-divider">


                            <!-- UPDATE FORM -->
                            <form method="POST"
                                  class="skill-form">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= e($csrfToken) ?>">

                                <input type="hidden"
                                       name="action"
                                       value="update">

                                <input type="hidden"
                                       name="id"
                                       value="<?= (int) $item['id'] ?>">


                                <div class="mb-3">

                                    <label class="form-label">
                                        Tingkat Kemampuan
                                    </label>

                                    <select name="proficiency"
                                            class="form-select"
                                            required>

                                        <?php foreach ($levelLabels as $value => $label): ?>

                                            <option value="<?= e($value) ?>"
                                                <?= $item['proficiency'] === $value ? 'selected' : '' ?>>

                                                <?= e($label) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="mb-2">

                                    <label class="form-label">
                                        Deskripsi Kemampuan
                                    </label>

                                    <textarea
                                        name="description"
                                        class="form-control"
                                        rows="3"
                                    ><?= e($item['description'] ?? '') ?></textarea>

                                </div>


                                <div class="skill-actions">

                                    <button type="submit"
                                            class="btn-update">

                                        Simpan Perubahan

                                    </button>


                                    <!-- DELETE MODAL TRIGGER -->
                                    <button type="button"
                                            class="btn-delete"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteSkillModal"
                                            data-skill-id="<?= (int) $item['id'] ?>"
                                            data-skill-name="<?= e($item['skill_name']) ?>">

                                        Hapus dari Profil

                                    </button>

                                </div>

                            </form>

                        </article>


                    <?php endforeach; ?>

                </div>

            <?php else: ?>


                <div class="empty-state">

                    <span class="empty-state-icon">◇</span>

                    <strong class="d-block mb-1" style="color:var(--navy);">
                        Belum Ada Keterampilan
                    </strong>

                    Anda belum menambahkan keterampilan ke profil.
                    Gunakan formulir di atas untuk mulai membangun portofolio kompetensi.

                </div>


            <?php endif; ?>


        </section>


        <!-- PUBLIC PORTFOLIO INFORMATION -->
        <div class="form-notice">

            <div class="notice-icon">ⓘ</div>

            <div>

                <strong>Keterampilan pada Portofolio Publik</strong>

                <span>
                    Keterampilan yang tersimpan pada profil dapat ditampilkan
                    pada portofolio publik Anda sehingga perusahaan dapat
                    mengenali kemampuan yang dimiliki.
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
     id="deleteSkillModal"
     tabindex="-1"
     aria-labelledby="deleteSkillModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title"
                    id="deleteSkillModalLabel">

                    Konfirmasi Hapus Keterampilan

                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Tutup">
                </button>

            </div>


            <form method="POST">

                <div class="modal-body">

                    <div class="modal-warning-icon">
                        !
                    </div>

                    <h6 class="fw-bold mb-2"
                        style="color:var(--navy);">

                        Hapus keterampilan dari profil?

                    </h6>

                    <p class="text-secondary small mb-0">

                        Keterampilan ini akan dihapus dari profil kompetensi Anda.
                        Data master keterampilan SIPORSI tetap tersimpan.

                    </p>


                    <div class="modal-skill-info">

                        <small>KETERAMPILAN YANG AKAN DIHAPUS</small>

                        <strong id="deleteSkillName">
                            -
                        </strong>

                    </div>

                </div>


                <div class="modal-footer">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($csrfToken) ?>">

                    <input type="hidden"
                           name="action"
                           value="delete">

                    <input type="hidden"
                           name="id"
                           id="deleteSkillId"
                           value="">


                    <button type="button"
                            class="btn-modal-cancel"
                            data-bs-dismiss="modal">

                        Batal

                    </button>


                    <button type="submit"
                            class="btn-modal-delete">

                        Ya, Hapus Keterampilan

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

// Mengisi data keterampilan pada modal konfirmasi hapus.
const deleteSkillModal = document.getElementById('deleteSkillModal');

deleteSkillModal.addEventListener('show.bs.modal', function (event) {

    const button = event.relatedTarget;

    const skillId = button.getAttribute('data-skill-id');
    const skillName = button.getAttribute('data-skill-name');

    document.getElementById('deleteSkillId').value = skillId;
    document.getElementById('deleteSkillName').textContent = skillName;

});

</script>

</body>
</html>
