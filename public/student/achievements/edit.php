<?php
require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

$db = Database::connection();
$csrfToken = Csrf::token();

$stmt = $db->prepare("SELECT id FROM students WHERE user_id = :user_id LIMIT 1");
$stmt->execute(['user_id' => $_SESSION['user']['id']]);
$student = $stmt->fetch();

if (!$student) exit('Data siswa tidak ditemukan.');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) exit('ID prestasi tidak valid.');

$stmt = $db->prepare("
    SELECT * FROM achievements
    WHERE id = :id AND student_id = :student_id
    LIMIT 1
");
$stmt->execute(['id' => $id, 'student_id' => $student['id']]);
$achievement = $stmt->fetch();

if (!$achievement) {
    http_response_code(404);
    exit('Prestasi tidak ditemukan.');
}

$levels = [
    'school' => 'Sekolah',
    'city' => 'Kota/Kabupaten',
    'province' => 'Provinsi',
    'national' => 'Nasional',
    'international' => 'Internasional'
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        exit('Permintaan tidak valid.');
    }

    $name = trim($_POST['achievement_name'] ?? '');
    $level = $_POST['achievement_level'] ?? '';
    $organizer = trim($_POST['organizer'] ?? '');
    $date = $_POST['achievement_date'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Nama prestasi wajib diisi.';
    }

    if ($level !== '' && !array_key_exists($level, $levels)) {
        $errors[] = 'Tingkat prestasi tidak valid.';
    }

    $proofFile = $achievement['proof_file'];

    if (!empty($_FILES['proof_file']['name'])) {
        $file = $_FILES['proof_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Gagal mengunggah bukti.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Ukuran file maksimal 5 MB.';
        } else {
            $mime = mime_content_type($file['tmp_name']);
            $allowed = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            if (!isset($allowed[$mime])) {
                $errors[] = 'Bukti harus PDF, JPG, atau PNG.';
            } else {
                $uploadDir = APP_ROOT . '/public/uploads/achievements/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                    $proofFile = 'uploads/achievements/' . $newName;
                } else {
                    $errors[] = 'Gagal menyimpan file.';
                }
            }
        }
    }

    if (empty($errors)) {
        $update = $db->prepare("
            UPDATE achievements SET
                achievement_name = :name,
                achievement_level = :level,
                organizer = :organizer,
                achievement_date = :date,
                description = :description,
                proof_file = :proof_file
            WHERE id = :id AND student_id = :student_id
        ");

        $update->execute([
            'name' => $name,
            'level' => $level ?: null,
            'organizer' => $organizer ?: null,
            'date' => $date ?: null,
            'description' => $description ?: null,
            'proof_file' => $proofFile,
            'id' => $id,
            'student_id' => $student['id']
        ]);

        header('Location: ' . BASE_URL . '/student/achievements/index.php?updated=1');
        exit;
    }

    $achievement = array_merge($achievement, [
        'achievement_name' => $name,
        'achievement_level' => $level,
        'organizer' => $organizer,
        'achievement_date' => $date,
        'description' => $description
    ]);
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Prestasi - SIPORSI</title>

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

        /* TOPBAR */

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

        /* PAGE */

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

        /* FORM CARD */

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

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            margin-bottom: 8px;
            color: #35465e;
            font-size: 11px;
            font-weight: 700;
        }

        .required-mark {
            color: #dc3545;
        }

        .form-control,
        .form-select {
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

        .form-control:focus,
        .form-select:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(23,105,170,.10);
        }

        textarea.form-control {
            min-height: 105px;
            resize: vertical;
            line-height: 1.7;
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
            line-height: 1.7;
        }

        /* CURRENT PROOF */

        .current-proof-box {
            margin: 10px 0 13px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f9fbfe;
        }

        .current-proof-label {
            margin-bottom: 8px;
            color: #52647a;
            font-size: 10px;
            font-weight: 700;
        }

        .proof-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--blue);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .proof-link:hover {
            color: var(--navy);
            text-decoration: underline;
        }

        /* ERROR */

        .error-alert {
            margin-bottom: 21px;
            padding: 13px 16px;
            border: 1px solid #f1c6ca;
            border-radius: 9px;
            background: #fff5f5;
            color: #a72f3b;
            font-size: 11px;
        }

        /* NOTICE */

        .info-notice {
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

        /* BUTTONS */

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

        /* RESPONSIVE */

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

        <a class="topbar-link" href="<?= BASE_URL ?>/student/achievements/index.php">
            Kembali ke Prestasi
        </a>

    </div>
</header>


<main class="page-container">

    <div class="page-heading">

        <div class="breadcrumb-text">
            SIPORSI / Prestasi / <span>Edit Prestasi</span>
        </div>

        <h1 class="page-title">Edit Prestasi</h1>

        <p class="page-description">
            Perbarui informasi pencapaian, penghargaan, atau prestasi yang pernah kamu raih.
        </p>

    </div>


    <section class="form-card">

        <h2 class="form-section-title">Informasi Prestasi</h2>

        <p class="form-section-description">
            Pastikan informasi prestasi dan bukti pendukung yang kamu masukkan sudah benar.
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

                <label class="form-label">
                    Nama Prestasi
                    <span class="required-mark">*</span>
                </label>

                <input
                    type="text"
                    name="achievement_name"
                    class="form-control"
                    value="<?= e($achievement['achievement_name']) ?>"
                    placeholder="Contoh: Juara 1 Lomba Kompetensi Siswa"
                    required
                >

            </div>


            <div class="form-group">

                <label class="form-label">Tingkat Prestasi</label>

                <select name="achievement_level" class="form-select">

                    <option value="">Pilih Tingkat</option>

                    <?php foreach ($levels as $value => $label): ?>

                        <option
                            value="<?= e($value) ?>"
                            <?= $achievement['achievement_level'] === $value ? 'selected' : '' ?>
                        >
                            <?= e($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label class="form-label">Penyelenggara</label>

                <input
                    type="text"
                    name="organizer"
                    class="form-control"
                    value="<?= e($achievement['organizer']) ?>"
                    placeholder="Nama instansi atau penyelenggara"
                >

            </div>


            <div class="form-group">

                <label class="form-label">Tanggal Prestasi</label>

                <input
                    type="date"
                    name="achievement_date"
                    class="form-control"
                    value="<?= e($achievement['achievement_date']) ?>"
                >

            </div>


            <div class="form-group">

                <label class="form-label">Deskripsi</label>

                <textarea
                    name="description"
                    class="form-control"
                    rows="4"
                    placeholder="Jelaskan pencapaian atau pengalaman dalam memperoleh prestasi ini..."
                ><?= e($achievement['description']) ?></textarea>

            </div>


            <div class="form-group">

                <label class="form-label">Bukti Sertifikat</label>


                <?php if (!empty($achievement['proof_file'])): ?>

                    <div class="current-proof-box">

                        <div class="current-proof-label">
                            Sertifikat atau bukti prestasi saat ini
                        </div>

                        <a
                            href="<?= BASE_URL . '/' . e($achievement['proof_file']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="proof-link"
                        >
                            ↗ Lihat bukti saat ini
                        </a>

                    </div>

                <?php else: ?>

                    <div class="form-text mb-2">
                        Belum ada sertifikat atau bukti prestasi yang diunggah.
                    </div>

                <?php endif; ?>


                <input
                    type="file"
                    name="proof_file"
                    class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png"
                >

                <div class="form-text">
                    Pilih file baru apabila ingin mengganti sertifikat.
                    Format PDF, JPG, atau PNG. Maksimal 5 MB.
                </div>

            </div>


            <div class="info-notice">

                <span class="notice-icon">ⓘ</span>

                <span>
                    Perubahan informasi prestasi akan disimpan setelah kamu menekan
                    tombol <strong>Simpan Perubahan</strong>.
                    Bukti lama tetap digunakan apabila kamu tidak mengunggah file baru.
                </span>

            </div>


            <div class="form-actions">

                <button type="submit" class="btn-save">
                    Simpan Perubahan
                </button>

                <a
                    href="<?= BASE_URL ?>/student/achievements/index.php"
                    class="btn-back"
                >
                    Kembali
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>