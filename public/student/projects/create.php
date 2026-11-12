<?php
require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

$db = Database::connection();
$csrfToken = Csrf::token();

$userId = (int) $_SESSION['user']['id'];

$stmt = $db->prepare("
    SELECT id
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

$title = '';
$description = '';
$category = '';
$studentRole = '';
$toolsUsed = '';
$demoUrl = '';
$repositoryUrl = '';
$startDate = '';
$completionDate = '';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $studentRole = trim($_POST['student_role'] ?? '');
    $toolsUsed = trim($_POST['tools_used'] ?? '');
    $demoUrl = trim($_POST['demo_url'] ?? '');
    $repositoryUrl = trim($_POST['repository_url'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $completionDate = trim($_POST['completion_date'] ?? '');

    if ($title === '' || mb_strlen($title) > 180) {
        $errors[] = 'Judul proyek wajib diisi dan maksimal 180 karakter.';
    }

    if ($description === '') {
        $errors[] = 'Deskripsi proyek wajib diisi.';
    }

    if ($demoUrl !== '' && (!filter_var($demoUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($demoUrl, PHP_URL_SCHEME), ['http', 'https'], true))) {
        $errors[] = 'Link demo harus berupa alamat HTTP atau HTTPS yang valid.';
    }

    if ($repositoryUrl !== '' && (!filter_var($repositoryUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($repositoryUrl, PHP_URL_SCHEME), ['http', 'https'], true))) {
        $errors[] = 'Link repository harus berupa alamat HTTP atau HTTPS yang valid.';
    }

    if ($startDate !== '' && $completionDate !== '' && $completionDate < $startDate) {
        $errors[] = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.';
    }

    $thumbnailPath = null;

    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['thumbnail'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Gambar gagal diunggah. Silakan coba kembali.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran gambar maksimal 2 MB.';
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $mimeType = mime_content_type($file['tmp_name']);

            if (!isset($allowedTypes[$mimeType])) {
                $errors[] = 'Format gambar harus JPG, PNG, atau WEBP.';
            } else {
                $uploadDirectory = APP_ROOT . '/public/uploads/projects/';

                if (!is_dir($uploadDirectory) &&
                    !mkdir($uploadDirectory, 0755, true) &&
                    !is_dir($uploadDirectory)) {
                    $errors[] = 'Folder penyimpanan gambar tidak dapat dibuat.';
                } else {
                    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
                    $targetPath = $uploadDirectory . $fileName;
                    $thumbnailPath = 'uploads/projects/' . $fileName;

                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $errors[] = 'Gambar gagal disimpan.';
                        $thumbnailPath = null;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $slug = bin2hex(random_bytes(16));

            $stmt = $db->prepare("
                INSERT INTO projects (
                    student_id,
                    title,
                    slug,
                    description,
                    category,
                    student_role,
                    tools_used,
                    thumbnail,
                    demo_url,
                    repository_url,
                    start_date,
                    completion_date,
                    publication_status
                ) VALUES (
                    :student_id,
                    :title,
                    :slug,
                    :description,
                    :category,
                    :student_role,
                    :tools_used,
                    :thumbnail,
                    :demo_url,
                    :repository_url,
                    :start_date,
                    :completion_date,
                    'draft'
                )
            ");

            $stmt->execute([
                'student_id' => $studentId,
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'category' => $category !== '' ? $category : null,
                'student_role' => $studentRole !== '' ? $studentRole : null,
                'tools_used' => $toolsUsed !== '' ? $toolsUsed : null,
                'thumbnail' => $thumbnailPath,
                'demo_url' => $demoUrl !== '' ? $demoUrl : null,
                'repository_url' => $repositoryUrl !== '' ? $repositoryUrl : null,
                'start_date' => $startDate !== '' ? $startDate : null,
                'completion_date' => $completionDate !== '' ? $completionDate : null
            ]);

            header('Location: ' . BASE_URL . '/student/projects/index.php?created=1');
            exit;

        } catch (PDOException $exception) {
            error_log($exception->getMessage());

            if ($thumbnailPath !== null) {
                $savedFile = APP_ROOT . '/public/' . $thumbnailPath;

                if (is_file($savedFile)) {
                    unlink($savedFile);
                }
            }

            $errors[] = 'Terjadi kesalahan saat menyimpan proyek.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Proyek - SIPORSI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        .page-topbar {
            min-height: 75px;
            background: var(--navy);
            box-shadow: 0 3px 14px rgba(16, 46, 89, .10);
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            width: min(1180px, calc(100% - 40px));
            min-height: 75px;
            margin: 0 auto;
        }

        .brand-link {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            color: white;
            text-decoration: none;
        }

        .brand-logo {
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

        .brand-name {
            display: block;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: .5px;
            line-height: 1.2;
        }

        .brand-caption {
            display: block;
            margin-top: 4px;
            color: rgba(255,255,255,.65);
            font-size: 9px;
            letter-spacing: 1px;
        }

        .topbar-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 37px;
            padding: 8px 13px;
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 8px;
            color: white;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            transition: .2s;
        }

        .topbar-link:hover {
            border-color: var(--gold);
            background: rgba(255,255,255,.08);
            color: white;
        }

        .page-container {
            width: min(920px, calc(100% - 36px));
            margin: 0 auto;
            padding: 38px 0 55px;
        }

        .page-heading {
            margin-bottom: 21px;
        }

        .breadcrumb-text {
            margin-bottom: 9px;
            color: var(--muted);
            font-size: 11px;
        }

        .breadcrumb-text span {
            color: var(--blue);
            font-weight: 700;
        }

        .page-title {
            margin: 0 0 7px;
            color: var(--navy);
            font-size: 25px;
            font-weight: 800;
        }

        .page-description {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
        }

        .form-card {
            padding: 29px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: white;
            box-shadow: 0 7px 24px rgba(16,46,89,.045);
        }

        .form-section-title {
            margin: 0 0 5px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 800;
        }

        .form-section-description {
            margin: 0 0 23px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .form-label {
            margin-bottom: 8px;
            color: #35465e;
            font-size: 11px;
            font-weight: 700;
        }

        .form-control {
            min-height: 43px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background-color: #f9fbfe;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 11px;
        }

        .form-control::placeholder {
            color: #98a6b8;
        }

        .form-control:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.10);
        }

        textarea.form-control {
            min-height: 105px;
            resize: vertical;
            line-height: 1.7;
        }

        textarea.tools-field {
            min-height: 78px;
        }

        input[type="file"].form-control {
            padding: 7px 9px;
            background: #f9fbfe;
        }

        input[type="file"]::file-selector-button {
            margin: -2px 10px -2px -4px;
            padding: 7px 11px;
            border: 0;
            border-radius: 6px;
            background: #eaf2fc;
            color: var(--navy);
            font-family: 'Inter', sans-serif;
            font-size: 10px;
            font-weight: 700;
        }

        .form-text {
            margin-top: 7px;
            color: var(--muted);
            font-size: 10px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .required-mark {
            color: #dc3545;
        }

        .error-alert {
            margin-bottom: 21px;
            padding: 13px 16px;
            border: 1px solid #f1c6ca;
            border-radius: 9px;
            background: #fff5f5;
            color: #a72f3b;
            font-size: 11px;
        }

        .error-alert ul {
            padding-left: 19px;
        }

        .draft-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 6px 0 22px;
            padding: 14px 15px;
            border: 1px solid #cfe4f8;
            border-radius: 9px;
            background: #f0f7ff;
            color: #526b86;
            font-size: 10px;
            line-height: 1.7;
        }

        .notice-icon {
            flex: 0 0 auto;
            color: var(--blue);
            font-size: 15px;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            padding-top: 2px;
        }

        .btn-save,
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 41px;
            padding: 10px 17px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-save {
            border: 0;
            background: var(--navy);
            color: white;
        }

        .btn-save:hover {
            background: var(--blue);
            color: white;
            transform: translateY(-1px);
        }

        .btn-back {
            border: 1px solid var(--border);
            background: white;
            color: #52647a;
        }

        .btn-back:hover {
            border-color: #c5d8ed;
            background: #f8fbff;
            color: var(--navy);
        }

        @media (max-width: 576px) {
            .topbar-inner {
                width: calc(100% - 28px);
            }

            .topbar-link {
                padding: 7px 9px;
                font-size: 10px;
            }

            .page-container {
                width: calc(100% - 24px);
                padding: 25px 0 35px;
            }

            .page-title {
                font-size: 22px;
            }

            .form-card {
                padding: 19px 16px;
            }
        }
    </style>
</head>

<body>

<header class="page-topbar">
    <div class="topbar-inner">
        <a class="brand-link" href="<?= BASE_URL ?>/student/dashboard.php">
            <span class="brand-logo">S</span>
            <span>
                <span class="brand-name">SIPORSI</span>
                <span class="brand-caption">STUDENT PANEL</span>
            </span>
        </a>

        <a class="topbar-link" href="<?= BASE_URL ?>/student/dashboard.php">
            Kembali ke Dashboard
        </a>
    </div>
</header>

<main class="page-container">
    <div class="page-heading">
        <div class="breadcrumb-text">SIPORSI / Proyek dan Karya / <span>Tambah Proyek</span></div>
        <h1 class="page-title">Tambah Proyek</h1>
        <p class="page-description">
            Masukkan informasi hasil karya atau proyek Anda ke dalam portofolio digital.
        </p>
    </div>

    <section class="form-card">
        <h2 class="form-section-title">Informasi Proyek</h2>
        <p class="form-section-description">
            Lengkapi informasi berikut. Data proyek akan tersimpan sebagai draft terlebih dahulu.
        </p>

        <?php if (!empty($errors)): ?>
            <div class="error-alert" role="alert">
                <strong>Periksa kembali data yang dimasukkan:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-group">
                <label class="form-label">Judul Proyek <span class="required-mark">*</span></label>
                <input type="text" name="title" class="form-control"
                    maxlength="180" value="<?= e($title) ?>" required
                    placeholder="Contoh: Website Portofolio Pribadi">
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi Proyek <span class="required-mark">*</span></label>
                <textarea name="description" class="form-control" rows="4"
                    required placeholder="Jelaskan tujuan, proses, dan hasil proyek..."><?= e($description) ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Kategori Proyek</label>
                        <input type="text" name="category" class="form-control"
                            value="<?= e($category) ?>"
                            placeholder="Contoh: Website, Desain, Kuliner">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Peran Saya dalam Proyek</label>
                        <input type="text" name="student_role" class="form-control"
                            value="<?= e($studentRole) ?>"
                            placeholder="Contoh: Programmer, Desainer, Ketua Tim">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Teknologi atau Alat yang Digunakan</label>
                <textarea name="tools_used" class="form-control tools-field" rows="2"
                    placeholder="Contoh: HTML, CSS, PHP, MySQL"><?= e($toolsUsed) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Gambar Proyek</label>
                <input type="file" name="thumbnail" class="form-control"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <div class="form-text">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Link Demo</label>
                        <input type="url" name="demo_url" class="form-control"
                            value="<?= e($demoUrl) ?>" placeholder="https://contoh.com">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Link Repository</label>
                        <input type="url" name="repository_url" class="form-control"
                            value="<?= e($repositoryUrl) ?>" placeholder="https://github.com/username/proyek">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control"
                            value="<?= e($startDate) ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="completion_date" class="form-control"
                            value="<?= e($completionDate) ?>">
                    </div>
                </div>
            </div>

            <div class="draft-notice">
                <span class="notice-icon">ⓘ</span>
                <span>Proyek yang disimpan akan berstatus <strong>Draft</strong> dan belum ditampilkan ke publik.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">Simpan Proyek</button>
                <a href="<?= BASE_URL ?>/student/projects/index.php" class="btn-back">Kembali</a>
            </div>
        </form>
    </section>
</main>

</body>
</html>