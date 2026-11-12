
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

$db = Database::connection();

$errors = [];
$skillName = '';
$skillCategory = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $skillName = trim($_POST['skill_name'] ?? '');
    $skillCategory = trim($_POST['skill_category'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($skillName === '') {
        $errors[] = 'Nama keterampilan wajib diisi.';
    } elseif (mb_strlen($skillName) > 100) {
        $errors[] = 'Nama keterampilan maksimal 100 karakter.';
    }

    if (mb_strlen($skillCategory) > 100) {
        $errors[] = 'Kategori maksimal 100 karakter.';
    }

    if ($skillCategory === '') {
        $errors[] = 'Kategori keterampilan wajib diisi.';
    }

    if (empty($errors)) {

        try {

            $stmt = $db->prepare("
                INSERT INTO skills (skill_name, skill_category, description)
                VALUES (:skill_name, :skill_category, :description)
            ");

            $stmt->execute([
                'skill_name' => $skillName,
                'skill_category' => $skillCategory,
                'description' => $description !== '' ? $description : null,
            ]);

            header('Location: ' . BASE_URL . '/admin/skills/index.php?created=1');
            exit;

        } catch (PDOException $exception) {

            if ($exception->getCode() === '23000') {
                $errors[] = 'Nama keterampilan tersebut sudah terdaftar.';
            } else {
                error_log($exception->getMessage());
                $errors[] = 'Terjadi kesalahan saat menyimpan keterampilan.';
            }
        }
    }
}

$csrfToken = Csrf::token();

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Keterampilan | SIPORSI Admin</title>

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
            max-width: 1100px;
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

        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 43px;
            height: 43px;
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

        .form-control {
            min-height: 45px;
            padding: 11px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #f9fbfe;
            color: var(--text);
            font-size: 12px;
        }

        .form-control:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.1);
        }

        textarea.form-control {
            min-height: 135px;
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

        /* FORM NOTICE */

        .form-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 22px;
            padding: 15px;
            border: 1px solid #cfe4f8;
            border-radius: 10px;
            background: #f0f7ff;
        }

        .notice-icon {
            color: var(--blue);
            font-size: 19px;
        }

        .form-notice strong {
            display: block;
            margin-bottom: 4px;
            color: var(--navy);
            font-size: 11px;
        }

        .form-notice p {
            margin: 0;
            color: #63758a;
            font-size: 10px;
            line-height: 1.7;
        }

        .form-divider {
            margin: 27px 0;
            border: 0;
            border-top: 1px solid var(--border);
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
                padding: 21px 17px;
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
       class="sidebar-menu active">

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
                SIPORSI / Data Keterampilan / Tambah Keterampilan
            </span>

        </div>

        <a href="<?= BASE_URL ?>/admin/skills/index.php"
           class="btn btn-sm btn-outline-primary">
            Kembali ke Data Keterampilan
        </a>

    </header>


    <!-- CONTENT -->
    <main class="admin-content">

        <!-- PAGE HEADER -->
        <div class="mb-4">

            <h1 class="page-title">
                Tambah Keterampilan
            </h1>

            <p class="page-description">
                Masukkan keterampilan baru ke dalam daftar kompetensi SIPORSI.
            </p>

        </div>


        <!-- ERROR NOTIFICATION -->
        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger alert-dismissible fade show rounded-3 small"
                 role="alert">

                <strong class="d-block mb-2">
                    Keterampilan belum berhasil disimpan.
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


        <!-- FORM PANEL -->
        <div class="form-panel">

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($csrfToken) ?>">


                <!-- SKILL INFORMATION -->
                <div class="section-heading">

                    <span class="section-icon">✦</span>

                    <div>

                        <h5>Informasi Keterampilan</h5>

                        <p>
                            Lengkapi nama, kategori, dan deskripsi keterampilan.
                        </p>

                    </div>

                </div>


                <div class="row g-3">

                    <!-- SKILL NAME -->
                    <div class="col-md-6">

                        <label class="form-label">
                            Nama Keterampilan
                            <span class="required-mark">*</span>
                        </label>

                        <input
                            type="text"
                            name="skill_name"
                            class="form-control"
                            maxlength="100"
                            value="<?= e($skillName) ?>"
                            placeholder="Contoh: Desain UI/UX"
                            required
                        >

                        <small class="form-help">
                            Maksimal 100 karakter.
                        </small>

                    </div>


                    <!-- CATEGORY -->
                    <div class="col-md-6">

                        <label class="form-label">
                            Kategori
                            <span class="required-mark">*</span>
                        </label>

                        <input
                            type="text"
                            name="skill_category"
                            class="form-control"
                            maxlength="100"
                            value="<?= e($skillCategory) ?>"
                            placeholder="Contoh: Teknologi"
                            required
                        >

                        <small class="form-help">
                            Kelompok bidang keterampilan, misalnya Teknologi, Kuliner,
                            Desain, atau Perhotelan.
                        </small>

                    </div>


                    <!-- DESCRIPTION -->
                    <div class="col-12">

                        <label class="form-label">
                            Deskripsi
                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="5"
                            placeholder="Jelaskan kemampuan atau kompetensi yang dimiliki..."
                        ><?= e($description) ?></textarea>

                        <small class="form-help">
                            Tuliskan penjelasan singkat agar keterampilan mudah dipahami
                            oleh siswa dan pengguna SIPORSI lainnya.
                        </small>

                    </div>

                </div>


                <!-- INFORMATION NOTICE -->
                <div class="form-notice">

                    <div class="notice-icon">ⓘ</div>

                    <div>

                        <strong>Informasi Penambahan Keterampilan</strong>

                        <p>
                            Keterampilan yang berhasil disimpan akan otomatis
                            berstatus aktif dan dapat dipilih oleh siswa untuk
                            melengkapi profil kompetensi mereka.
                        </p>

                    </div>

                </div>


                <hr class="form-divider">


                <!-- BUTTONS -->
                <div class="form-actions d-flex flex-wrap gap-2">

                    <button type="submit"
                            class="btn-save">
                        Simpan Keterampilan →
                    </button>

                    <a href="<?= BASE_URL ?>/admin/skills/index.php"
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
