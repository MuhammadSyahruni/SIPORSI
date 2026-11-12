
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();
$csrfToken = Csrf::token();

// Validasi ID siswa
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('ID siswa tidak valid.');
}

// Ambil data siswa
$stmt = $db->prepare("
    SELECT s.*, m.major_name
    FROM students s
    INNER JOIN majors m ON m.id = s.major_id
    WHERE s.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

// Ambil daftar jurusan aktif
$majors = $db->query("
    SELECT id, major_name
    FROM majors
    WHERE is_active = 1
    ORDER BY major_name
")->fetchAll();

$errors = [];

function escape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Proses perubahan data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Muat ulang halaman.');
    }

    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $majorId = (int) ($_POST['major_id'] ?? 0);
    $classLevel = trim($_POST['class_level'] ?? '');
    $enrollmentYear = trim($_POST['enrollment_year'] ?? '');
    $graduationYear = trim($_POST['graduation_year'] ?? '');
    $studentStatus = $_POST['student_status'] ?? '';

    $isPublicRaw = $_POST['is_public'] ?? '0';

    $isPublic = in_array($isPublicRaw, ['0', '1'], true)
        ? (int) $isPublicRaw
        : -1;

    $shortBio = trim($_POST['short_bio'] ?? '');
    $biography = trim($_POST['biography'] ?? '');

    // Validasi
    if ($nis === '' || $fullName === '') {
        $errors[] = 'NIS dan nama lengkap wajib diisi.';
    }

    if ($gender !== '' && !in_array($gender, ['L', 'P'], true)) {
        $errors[] = 'Jenis kelamin tidak valid.';
    }

    if (!in_array($studentStatus, ['active', 'graduated', 'inactive'], true)) {
        $errors[] = 'Status siswa tidak valid.';
    }
    // Validasi status publikasi portofolio.
    if (!in_array($isPublic, [0, 1], true)) {
        $errors[] = 'Status publikasi portofolio tidak valid.';
    }

    // Siswa tidak aktif tidak boleh memiliki portofolio publik.
    if ($studentStatus === 'inactive' && $isPublic === 1) {
        $errors[] = 'Siswa berstatus tidak aktif tidak dapat memiliki portofolio publik.';
    }

    if (
        $enrollmentYear !== '' &&
        (
            !ctype_digit($enrollmentYear) ||
            (int) $enrollmentYear < 2000 ||
            (int) $enrollmentYear > (int) date('Y') + 1
        )
    ) {
        $errors[] = 'Tahun masuk tidak valid.';
    }

    if (
        $graduationYear !== '' &&
        (
            !ctype_digit($graduationYear) ||
            (int) $graduationYear < 2000 ||
            (int) $graduationYear > (int) date('Y') + 10
        )
    ) {
        $errors[] = 'Tahun kelulusan tidak valid.';
    }

    // Periksa jurusan aktif
    $majorCheck = $db->prepare("
        SELECT id FROM majors
        WHERE id = :id AND is_active = 1
    ");

    $majorCheck->execute(['id' => $majorId]);

    if (!$majorCheck->fetch()) {
        $errors[] = 'Jurusan tidak valid.';
    }

    // Periksa NIS dan NISN agar tidak digunakan siswa lain
    $duplicate = $db->prepare("
        SELECT id FROM students
        WHERE (nis = :nis AND id != :id)
        OR (nisn = :nisn AND :nisn_check != '' AND id != :id_check)
        LIMIT 1
    ");

    $duplicate->execute([
        'nis' => $nis,
        'id' => $id,
        'nisn' => $nisn,
        'nisn_check' => $nisn,
        'id_check' => $id
    ]);

    if ($duplicate->fetch()) {
        $errors[] = 'NIS atau NISN sudah digunakan siswa lain.';
    }

    // Simpan perubahan
    if (!$errors) {

        try {

            $db->beginTransaction();

            $update = $db->prepare("
                UPDATE students SET
                    nis = :nis,
                    nisn = :nisn,
                    full_name = :full_name,
                    gender = :gender,
                    major_id = :major_id,
                    class_level = :class_level,
                    enrollment_year = :enrollment_year,
                    graduation_year = :graduation_year,
                    student_status = :student_status,
                    is_public = :is_public,
                    short_bio = :short_bio,
                    biography = :biography
                WHERE id = :id
            ");

            $update->execute([
                'nis' => $nis,
                'nisn' => $nisn !== '' ? $nisn : null,
                'full_name' => $fullName,
                'gender' => $gender !== '' ? $gender : null,
                'major_id' => $majorId,
                'class_level' => $classLevel !== '' ? $classLevel : null,
                'enrollment_year' => $enrollmentYear !== '' ? $enrollmentYear : null,
                'graduation_year' => $graduationYear !== '' ? $graduationYear : null,
                'student_status' => $studentStatus,
                'is_public' => $isPublic,
                'short_bio' => $shortBio !== '' ? $shortBio : null,
                'biography' => $biography !== '' ? $biography : null,
                'id' => $id
            ]);
            // ========================================
            // SINKRONISASI STATUS AKUN SISWA
            // ========================================

            // Ambil dan kunci data akun siswa selama transaksi.
            $accountCheck = $db->prepare("
                SELECT id, account_status
                FROM users
                WHERE id = :user_id
                AND role_id = 2
                FOR UPDATE
            ");

            $accountCheck->execute([
                'user_id' => $student['user_id']
            ]);

            $studentAccount = $accountCheck->fetch(PDO::FETCH_ASSOC);

            if (!$studentAccount) {
                throw new RuntimeException('Akun pengguna siswa tidak ditemukan.');
            }


            // Jika status siswa menjadi tidak aktif,
            // akun login juga dinonaktifkan.
            // Akun yang berstatus suspended tetap dipertahankan.
            if ($studentStatus === 'inactive') {

                $updateUserStatus = $db->prepare("
                    UPDATE users
                    SET account_status = 'inactive'
                    WHERE id = :user_id
                    AND role_id = 2
                    AND account_status <> 'suspended'
                ");

                $updateUserStatus->execute([
                    'user_id' => $student['user_id']
                ]);

            }


            // Jika siswa sebelumnya tidak aktif kemudian diaktifkan
            // atau dinyatakan lulus, akun inactive dapat diaktifkan kembali.
            elseif (
                $student['student_status'] === 'inactive' &&
                in_array($studentStatus, ['active', 'graduated'], true) &&
                $studentAccount['account_status'] === 'inactive'
            ) {

                $updateUserStatus = $db->prepare("
                    UPDATE users
                    SET account_status = 'active'
                    WHERE id = :user_id
                    AND role_id = 2
                    AND account_status = 'inactive'
                ");

                $updateUserStatus->execute([
                    'user_id' => $student['user_id']
                ]);

            }

            $db->commit();

            header('Location: ' . BASE_URL . '/admin/students/detail.php?id=' . $id . '&updated=1');
            exit;

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = 'Gagal memperbarui data siswa.';
        }
    }

    // Tampilkan kembali input jika validasi gagal
    $student = array_merge($student, [
        'nis' => $nis,
        'nisn' => $nisn,
        'full_name' => $fullName,
        'gender' => $gender,
        'major_id' => $majorId,
        'class_level' => $classLevel,
        'enrollment_year' => $enrollmentYear,
        'graduation_year' => $graduationYear,
        'student_status' => $studentStatus,
        'is_public' => $isPublic,
        'short_bio' => $shortBio,
        'biography' => $biography
    ]);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Siswa | SIPORSI Admin</title>

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

        /* FORM PANEL */

        .form-panel {
            padding: 30px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            box-shadow: 0 5px 20px rgba(16,46,89,.035);
        }

        .form-section {
            margin-bottom: 24px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 23px;
        }

        .section-icon {
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

        .section-heading h5 {
            margin: 0;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .section-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

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
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: #f9fbfe;
            color: var(--text);
            font-size: 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--blue);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.1);
        }

        textarea.form-control {
            min-height: 115px;
            resize: vertical;
            line-height: 1.7;
        }

        .form-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 10px;
        }

        .form-divider {
            margin: 27px 0;
            border: 0;
            border-top: 1px solid var(--border);
        }

        /* ACCOUNT NOTICE */

        .edit-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .notice-icon {
            color: var(--blue);
            font-size: 19px;
        }

        .edit-notice strong {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 11px;
        }

        .edit-notice p {
            margin: 0;
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
        }

        /* BUTTONS */

        .btn-save {
            min-height: 44px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: var(--navy);
            color: white;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-save:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-2px);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 19px;
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

            .form-panel {
                padding: 20px 16px;
            }

            .form-actions {
                display: flex;
                flex-direction: column;
            }

            .btn-save,
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
                SIPORSI / Data Siswa / Edit Siswa
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/students/detail.php?id=<?= (int) $id ?>"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Detail Siswa
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h1 class="page-title">
                Edit Data Siswa
            </h1>

            <p class="page-description">
                Perbarui informasi identitas, akademik, dan profil siswa SIPORSI.
            </p>

        </div>


        <!-- ERROR NOTIFICATION -->
        <?php if ($errors): ?>

            <div class="alert alert-danger alert-dismissible fade show rounded-3 small"
                 role="alert">

                <strong class="d-block mb-2">
                    Perubahan belum berhasil disimpan.
                </strong>

                <ul class="mb-0 ps-3">

                    <?php foreach ($errors as $error): ?>

                        <li><?= escape($error) ?></li>

                    <?php endforeach; ?>

                </ul>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close">
                </button>

            </div>

        <?php endif; ?>


        <!-- FORM PANEL -->
        <div class="form-panel">

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= escape($csrfToken) ?>">


                <!-- IDENTITAS SISWA -->
                <div class="form-section">

                    <div class="section-heading">

                        <span class="section-icon">♙</span>

                        <div>

                            <h5>Identitas Siswa</h5>

                            <p>
                                Perbarui informasi identitas dasar siswa.
                            </p>

                        </div>

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                NIS <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                name="nis"
                                class="form-control"
                                value="<?= escape($student['nis']) ?>"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                NISN
                            </label>

                            <input
                                type="text"
                                name="nisn"
                                class="form-control"
                                value="<?= escape($student['nisn']) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Nama Lengkap <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                maxlength="150"
                                value="<?= escape($student['full_name']) ?>"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Jenis Kelamin
                            </label>

                            <select name="gender" class="form-select">

                                <option value="">
                                    Pilih jenis kelamin
                                </option>

                                <option value="L"
                                    <?= $student['gender'] === 'L' ? 'selected' : '' ?>>
                                    Laki-laki
                                </option>

                                <option value="P"
                                    <?= $student['gender'] === 'P' ? 'selected' : '' ?>>
                                    Perempuan
                                </option>

                            </select>

                        </div>

                    </div>

                </div>


                <hr class="form-divider">


                <!-- INFORMASI AKADEMIK -->
                <div class="form-section">

                    <div class="section-heading">

                        <span class="section-icon">🎓</span>

                        <div>

                            <h5>Informasi Akademik</h5>

                            <p>
                                Perbarui jurusan, kelas, dan tahun akademik siswa.
                            </p>

                        </div>

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Jurusan <span class="required-mark">*</span>
                            </label>

                            <select name="major_id"
                                    class="form-select"
                                    required>

                                <option value="">
                                    Pilih jurusan
                                </option>

                                <?php foreach ($majors as $major): ?>

                                    <option
                                        value="<?= (int) $major['id'] ?>"
                                        <?= (int) $student['major_id'] === (int) $major['id'] ? 'selected' : '' ?>
                                    >

                                        <?= escape($major['major_name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Kelas
                            </label>

                            <input
                                type="text"
                                name="class_level"
                                class="form-control"
                                placeholder="Contoh: XII RPL 1"
                                value="<?= escape($student['class_level']) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Tahun Masuk
                            </label>

                            <input
                                type="number"
                                name="enrollment_year"
                                class="form-control"
                                min="2000"
                                max="<?= (int) date('Y') + 1 ?>"
                                placeholder="Contoh: 2024"
                                value="<?= escape($student['enrollment_year']) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Tahun Lulus
                            </label>

                            <input
                                type="number"
                                name="graduation_year"
                                class="form-control"
                                min="2000"
                                max="<?= (int) date('Y') + 10 ?>"
                                placeholder="Contoh: 2027"
                                value="<?= escape($student['graduation_year']) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Status Siswa
                            </label>

                            <select name="student_status"
                                    class="form-select">

                                <option value="active"
                                    <?= $student['student_status'] === 'active' ? 'selected' : '' ?>>
                                    Aktif
                                </option>

                                <option value="graduated"
                                    <?= $student['student_status'] === 'graduated' ? 'selected' : '' ?>>
                                    Lulus
                                </option>

                                <option value="inactive"
                                    <?= $student['student_status'] === 'inactive' ? 'selected' : '' ?>>
                                    Tidak Aktif
                                </option>

                            </select>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Publikasi Portofolio
                            </label>

                            <select name="is_public" class="form-select">

                                <option value="0"
                                    <?= (int) $student['is_public'] === 0 ? 'selected' : '' ?>>
                                    Privat - Tidak ditampilkan untuk publik
                                </option>

                                <option value="1"
                                    <?= (int) $student['is_public'] === 1 ? 'selected' : '' ?>>
                                    Publik - Dapat dilihat pengunjung
                                </option>

                            </select>

                            <small class="form-text">
                                Hanya siswa berstatus Aktif atau Lulus yang dapat dipublikasikan.
                            </small>

                        </div>

                    </div>

                </div>


                <hr class="form-divider">


                <!-- BIOGRAFI -->
                <div class="form-section">

                    <div class="section-heading">

                        <span class="section-icon">✎</span>

                        <div>

                            <h5>Informasi Profil dan Biografi</h5>

                            <p>
                                Perbarui deskripsi singkat dan biografi siswa.
                            </p>

                        </div>

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Biografi Singkat
                        </label>

                        <input
                            type="text"
                            name="short_bio"
                            class="form-control"
                            maxlength="255"
                            placeholder="Tuliskan deskripsi singkat tentang siswa"
                            value="<?= escape($student['short_bio']) ?>"
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Biografi Lengkap
                        </label>

                        <textarea
                            name="biography"
                            class="form-control"
                            rows="5"
                            placeholder="Tuliskan informasi lengkap mengenai siswa..."
                        ><?= escape($student['biography']) ?></textarea>

                        <small class="form-help">
                            Biografi dapat menjadi bagian dari informasi profil portofolio siswa.
                        </small>

                    </div>

                </div>


                <hr class="form-divider">


                <!-- NOTICE -->
                <div class="edit-notice mb-4">

                    <div class="notice-icon">ⓘ</div>

                    <div>

                        <strong>Informasi Pembaruan Data</strong>

                        <p>
                            Pastikan data identitas, jurusan, dan status siswa
                            sudah benar sebelum menyimpan perubahan.
                            Data portofolio yang sudah dimiliki siswa tetap tersimpan.
                        </p>

                    </div>

                </div>


                <!-- BUTTONS -->
                <div class="form-actions d-flex flex-wrap gap-2">

                    <button type="submit"
                            class="btn-save">
                        Simpan Perubahan →
                    </button>

                    <a href="<?= BASE_URL ?>/admin/students/detail.php?id=<?= (int) $id ?>"
                       class="btn-cancel">
                        Batal
                    </a>

                </div>

            </form>

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
