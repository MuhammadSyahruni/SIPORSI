
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$csrfToken = Csrf::token();
$db = Database::connection();

$errors = [];
$success = false;

// Ambil daftar jurusan aktif
$stmt = $db->query(
    "SELECT id, major_name FROM majors WHERE is_active = 1 ORDER BY major_name"
);

$majors = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $majorId = (int) ($_POST['major_id'] ?? 0);
    $classLevel = trim($_POST['class_level'] ?? '');
    $enrollmentYear = trim($_POST['enrollment_year'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi dasar
    if (
        $nis === '' ||
        $fullName === '' ||
        $email === '' ||
        $username === '' ||
        $password === '' ||
        $majorId <= 0
    ) {
        $errors[] = 'Semua kolom wajib harus diisi.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($password !== '' && strlen($password) < 12) {
        $errors[] = 'Password minimal 12 karakter.';
    }

    if ($gender !== '' && !in_array($gender, ['L', 'P'], true)) {
        $errors[] = 'Jenis kelamin tidak valid.';
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

    // Pastikan jurusan tersedia
    $majorCheck = $db->prepare(
        "SELECT id FROM majors WHERE id = :id AND is_active = 1"
    );

    $majorCheck->execute(['id' => $majorId]);

    if (!$majorCheck->fetch()) {
        $errors[] = 'Jurusan tidak ditemukan atau tidak aktif.';
    }

    // Cek username, email, NIS dan NISN
    if (!$errors) {

        $check = $db->prepare(
            "SELECT
                EXISTS(SELECT 1 FROM users WHERE username = :username) AS username_exists,
                EXISTS(SELECT 1 FROM users WHERE email = :email) AS email_exists,
                EXISTS(SELECT 1 FROM students WHERE nis = :nis) AS nis_exists,
                EXISTS(SELECT 1 FROM students WHERE nisn = :nisn) AS nisn_exists"
        );

        $check->execute([
            'username' => $username,
            'email' => $email,
            'nis' => $nis,
            'nisn' => $nisn
        ]);

        $existing = $check->fetch();

        if ($existing['username_exists']) {
            $errors[] = 'Username sudah digunakan.';
        }

        if ($existing['email_exists']) {
            $errors[] = 'Email sudah digunakan.';
        }

        if ($existing['nis_exists']) {
            $errors[] = 'NIS sudah terdaftar.';
        }

        if ($nisn !== '' && $existing['nisn_exists']) {
            $errors[] = 'NISN sudah terdaftar.';
        }
    }

    // Simpan akun dan profil secara bersamaan
    if (!$errors) {

        try {

            $db->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $userStmt = $db->prepare(
                "INSERT INTO users
                (role_id, username, email, password_hash, account_status)
                VALUES (2, :username, :email, :password_hash, 'active')"
            );

            $userStmt->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $hash
            ]);

            $userId = (int) $db->lastInsertId();

            $slug = bin2hex(random_bytes(16));

            $studentStmt = $db->prepare(
                "INSERT INTO students
                (user_id, major_id, nis, nisn, full_name, gender,
                 class_level, enrollment_year, public_slug)
                VALUES
                (:user_id, :major_id, :nis, :nisn, :full_name, :gender,
                 :class_level, :enrollment_year, :public_slug)"
            );

            $studentStmt->execute([
                'user_id' => $userId,
                'major_id' => $majorId,
                'nis' => $nis,
                'nisn' => $nisn !== '' ? $nisn : null,
                'full_name' => $fullName,
                'gender' => $gender !== '' ? $gender : null,
                'class_level' => $classLevel !== '' ? $classLevel : null,
                'enrollment_year' => $enrollmentYear !== '' ? $enrollmentYear : null,
                'public_slug' => $slug
            ]);

            $db->commit();

            header('Location: ' . BASE_URL . '/admin/students/index.php?created=1');
            exit;

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = 'Data siswa gagal disimpan. Periksa kembali data yang dimasukkan.';
        }
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

    <title>Tambah Siswa | SIPORSI Admin</title>

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
            max-width: 1200px;
            margin: 0 auto;
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

        .form-help {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .form-divider {
            margin: 27px 0;
            border: 0;
            border-top: 1px solid var(--border);
        }

        /* ACCOUNT NOTICE */

        .account-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .notice-icon {
            color: var(--blue);
            font-size: 19px;
        }

        .account-notice strong {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 11px;
        }

        .account-notice p {
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
                SIPORSI / Data Siswa / Tambah Siswa
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/students/index.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Data Siswa
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h1 class="page-title">
                Tambah Data Siswa
            </h1>

            <p class="page-description">
                Masukkan identitas dan informasi akun siswa SMK Negeri 4 Banjarmasin.
            </p>

        </div>


        <!-- ERROR NOTIFICATION -->
        <?php if ($errors): ?>

            <div class="alert alert-danger alert-dismissible fade show rounded-3 small"
                 role="alert">

                <strong class="d-block mb-2">
                    Data belum berhasil disimpan.
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
                                Masukkan informasi dasar siswa.
                                Kolom bertanda * wajib diisi.
                            </p>

                        </div>

                    </div>


                    <div class="row g-3">

                        <!-- NIS -->
                        <div class="col-md-6">

                            <label class="form-label">
                                NIS <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                name="nis"
                                class="form-control"
                                maxlength="30"
                                placeholder="Masukkan Nomor Induk Siswa"
                                value="<?= escape($_POST['nis'] ?? '') ?>"
                                required
                            >

                        </div>


                        <!-- NISN -->
                        <div class="col-md-6">

                            <label class="form-label">
                                NISN
                            </label>

                            <input
                                type="text"
                                name="nisn"
                                class="form-control"
                                maxlength="30"
                                placeholder="Masukkan Nomor Induk Siswa Nasional"
                                value="<?= escape($_POST['nisn'] ?? '') ?>"
                            >

                        </div>


                        <!-- NAMA -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Nama Lengkap <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                maxlength="150"
                                placeholder="Masukkan nama lengkap siswa"
                                value="<?= escape($_POST['full_name'] ?? '') ?>"
                                required
                            >

                        </div>


                        <!-- GENDER -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Jenis Kelamin
                            </label>

                            <select name="gender" class="form-select">

                                <option value="">
                                    Pilih jenis kelamin
                                </option>

                                <option value="L"
                                    <?= ($_POST['gender'] ?? '') === 'L' ? 'selected' : '' ?>>
                                    Laki-laki
                                </option>

                                <option value="P"
                                    <?= ($_POST['gender'] ?? '') === 'P' ? 'selected' : '' ?>>
                                    Perempuan
                                </option>

                            </select>

                        </div>


                        <!-- JURUSAN -->
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
                                        <?= (string) ($_POST['major_id'] ?? '') === (string) $major['id'] ? 'selected' : '' ?>
                                    >

                                        <?= escape($major['major_name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <small class="form-help">
                                Hanya jurusan yang berstatus aktif dapat dipilih.
                            </small>

                        </div>


                        <!-- KELAS -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Kelas
                            </label>

                            <input
                                type="text"
                                name="class_level"
                                class="form-control"
                                placeholder="Contoh: XII RPL 1"
                                value="<?= escape($_POST['class_level'] ?? '') ?>"
                            >

                        </div>


                        <!-- TAHUN MASUK -->
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
                                value="<?= escape($_POST['enrollment_year'] ?? '') ?>"
                            >

                        </div>

                    </div>

                </div>


                <hr class="form-divider">


                <!-- INFORMASI AKUN -->
                <div class="form-section">

                    <div class="section-heading">

                        <span class="section-icon">🔐</span>

                        <div>

                            <h5>Informasi Akun Siswa</h5>

                            <p>
                                Informasi yang digunakan siswa untuk masuk ke SIPORSI.
                            </p>

                        </div>

                    </div>


                    <div class="row g-3">

                        <!-- EMAIL -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Email <span class="required-mark">*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                maxlength="100"
                                placeholder="nama@email.com"
                                value="<?= escape($_POST['email'] ?? '') ?>"
                                autocomplete="off"
                                required
                            >

                        </div>


                        <!-- USERNAME -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Username <span class="required-mark">*</span>
                            </label>

                            <input
                                type="text"
                                name="username"
                                class="form-control"
                                placeholder="Buat username siswa"
                                value="<?= escape($_POST['username'] ?? '') ?>"
                                autocomplete="off"
                                required
                            >

                        </div>


                        <!-- PASSWORD -->
                        <div class="col-md-6">

                            <label class="form-label">
                                Password Awal <span class="required-mark">*</span>
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                minlength="12"
                                placeholder="Masukkan password awal"
                                autocomplete="new-password"
                                required
                            >

                            <small class="form-help">
                                Minimal 12 karakter. Berikan password kepada siswa melalui cara yang aman.
                            </small>

                        </div>

                    </div>


                    <!-- INFORMATION -->
                    <div class="account-notice">

                        <div class="notice-icon">ⓘ</div>

                        <div>

                            <strong>Informasi Pembuatan Akun</strong>

                            <p>
                                Sistem akan membuat akun siswa dan profil portofolio
                                secara bersamaan. Pastikan informasi yang dimasukkan
                                sudah benar sebelum menyimpan data.
                            </p>

                        </div>

                    </div>

                </div>


                <hr class="form-divider">


                <!-- BUTTONS -->
                <div class="form-actions d-flex flex-wrap gap-2">

                    <button type="submit"
                            class="btn-save">
                        Simpan Data Siswa →
                    </button>

                    <a href="<?= BASE_URL ?>/admin/students/index.php"
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
