
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

$db = Database::connection();
$csrfToken = Csrf::token();

$userId = (int) $_SESSION['user']['id'];

// Mengambil identitas siswa.
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

// Nilai awal form agar data tetap terisi setelah validasi gagal.
$certificateName = '';
$organization = '';
$certificateNumber = '';
$issueDate = '';
$expiryDate = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validasi CSRF.
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }

    $certificateName = trim($_POST['certificate_name'] ?? '');
    $organization = trim($_POST['issuing_organization'] ?? '');
    $certificateNumber = trim($_POST['certificate_number'] ?? '');
    $issueDate = trim($_POST['issue_date'] ?? '');
    $expiryDate = trim($_POST['expiry_date'] ?? '');

    // Validasi nama sertifikat.
    if ($certificateName === '') {
        $errors[] = 'Nama sertifikat wajib diisi.';
    } elseif (mb_strlen($certificateName) > 180) {
        $errors[] = 'Nama sertifikat maksimal 180 karakter.';
    }

    if (mb_strlen($organization) > 180) {
        $errors[] = 'Nama penerbit maksimal 180 karakter.';
    }

    if (mb_strlen($certificateNumber) > 100) {
        $errors[] = 'Nomor sertifikat maksimal 100 karakter.';
    }

    // Validasi format tanggal.
    foreach ([
        'Tanggal terbit' => $issueDate,
        'Tanggal kedaluwarsa' => $expiryDate
    ] as $label => $dateValue) {

        if ($dateValue !== '') {
            $dateObject = DateTime::createFromFormat('!Y-m-d', $dateValue);

            if (!$dateObject || $dateObject->format('Y-m-d') !== $dateValue) {
                $errors[] = $label . ' tidak valid.';
            }
        }
    }

    // Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.
    if (
        $issueDate !== '' &&
        $expiryDate !== '' &&
        $expiryDate < $issueDate
    ) {
        $errors[] = 'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.';
    }

    $certificateFile = null;

    // File sertifikat wajib diunggah.
    if (
        !isset($_FILES['certificate_file']) ||
        $_FILES['certificate_file']['error'] === UPLOAD_ERR_NO_FILE
    ) {

        $errors[] = 'File sertifikat wajib diunggah.';

    } else {

        $file = $_FILES['certificate_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {

            $errors[] = 'Gagal mengunggah file sertifikat.';

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

                $errors[] = 'Sertifikat harus berupa PDF, JPG, atau PNG.';

            } else {

                $uploadDir = APP_ROOT . '/public/uploads/certificates/';

                if (
                    !is_dir($uploadDir) &&
                    !mkdir($uploadDir, 0755, true) &&
                    !is_dir($uploadDir)
                ) {

                    $errors[] = 'Folder penyimpanan sertifikat gagal dibuat.';

                } else {

                    $newName = bin2hex(random_bytes(16))
                        . '.' . $allowed[$mime];

                    if (
                        move_uploaded_file(
                            $file['tmp_name'],
                            $uploadDir . $newName
                        )
                    ) {

                        $certificateFile = 'uploads/certificates/' . $newName;

                    } else {

                        $errors[] = 'Gagal menyimpan file sertifikat.';

                    }
                }
            }
        }
    }

    // Simpan sertifikat apabila seluruh validasi berhasil.
    if (empty($errors)) {

        try {

            $insert = $db->prepare("
                INSERT INTO certificates (
                    student_id,
                    certificate_name,
                    issuing_organization,
                    certificate_number,
                    issue_date,
                    expiry_date,
                    certificate_file,
                    verification_status
                ) VALUES (
                    :student_id,
                    :certificate_name,
                    :issuing_organization,
                    :certificate_number,
                    :issue_date,
                    :expiry_date,
                    :certificate_file,
                    'pending'
                )
            ");

            $insert->execute([
                'student_id' => $studentId,
                'certificate_name' => $certificateName,
                'issuing_organization' => $organization ?: null,
                'certificate_number' => $certificateNumber ?: null,
                'issue_date' => $issueDate ?: null,
                'expiry_date' => $expiryDate ?: null,
                'certificate_file' => $certificateFile
            ]);

            header(
                'Location: ' .
                BASE_URL .
                '/student/certificates/index.php?created=1'
            );

            exit;

        } catch (PDOException $exception) {

            error_log($exception->getMessage());

            if ($certificateFile !== null) {

                $savedFile = APP_ROOT . '/public/' . $certificateFile;

                if (is_file($savedFile)) {
                    unlink($savedFile);
                }
            }

            $errors[] = 'Terjadi kesalahan saat menyimpan sertifikat.';
        }
    }
}

// Fungsi keamanan output.
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Sertifikat - SIPORSI</title>

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
        }

        .topbar-link:hover {
            border-color: var(--gold);
            background: rgba(255,255,255,.08);
            color: white;
        }

        /* MAIN */

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

        /* FORM */

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

        .form-control {
            min-height: 43px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #f9fbfe;
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

        input[type="file"].form-control {
            padding: 7px 9px;
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

        /* BUTTONS */

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
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

        <a class="brand-link"
           href="<?= BASE_URL ?>/student/dashboard.php">

            <span class="brand-logo">S</span>

            <span>
                <span class="brand-name">SIPORSI</span>
                <span class="brand-caption">STUDENT PANEL</span>
            </span>

        </a>

        <a class="topbar-link"
           href="<?= BASE_URL ?>/student/dashboard.php">
            Kembali ke Dashboard
        </a>

    </div>

</header>


<main class="page-container">

    <div class="page-heading">

        <div class="breadcrumb-text">
            SIPORSI / Sertifikat / <span>Tambah Sertifikat</span>
        </div>

        <h1 class="page-title">
            Tambah Sertifikat
        </h1>

        <p class="page-description">
            Dokumentasikan sertifikat sebagai bukti kompetensi dan pencapaianmu.
        </p>

    </div>


    <section class="form-card">

        <h2 class="form-section-title">
            Informasi Sertifikat
        </h2>

        <p class="form-section-description">
            Lengkapi informasi sertifikat dan unggah dokumen pendukung untuk diperiksa Admin.
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

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($csrfToken) ?>">


            <div class="form-group">

                <label class="form-label">
                    Nama Sertifikat <span class="required-mark">*</span>
                </label>

                <input type="text"
                       name="certificate_name"
                       class="form-control"
                       maxlength="180"
                       value="<?= e($certificateName) ?>"
                       placeholder="Contoh: Sertifikat Kompetensi Pemrograman Web"
                       required>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Penerbit / Lembaga
                </label>

                <input type="text"
                       name="issuing_organization"
                       class="form-control"
                       maxlength="180"
                       value="<?= e($organization) ?>"
                       placeholder="Contoh: Lembaga Sertifikasi Profesi">

            </div>


            <div class="form-group">

                <label class="form-label">
                    Nomor Sertifikat
                </label>

                <input type="text"
                       name="certificate_number"
                       class="form-control"
                       maxlength="100"
                       value="<?= e($certificateNumber) ?>"
                       placeholder="Masukkan nomor sertifikat jika tersedia">

            </div>


            <div class="row">

                <div class="col-md-6">

                    <div class="form-group">

                        <label class="form-label">
                            Tanggal Terbit
                        </label>

                        <input type="date"
                               name="issue_date"
                               class="form-control"
                               value="<?= e($issueDate) ?>">

                    </div>

                </div>


                <div class="col-md-6">

                    <div class="form-group">

                        <label class="form-label">
                            Tanggal Kedaluwarsa
                        </label>

                        <input type="date"
                               name="expiry_date"
                               class="form-control"
                               value="<?= e($expiryDate) ?>">

                    </div>

                </div>

            </div>


            <div class="form-group">

                <label class="form-label">
                    File Sertifikat <span class="required-mark">*</span>
                </label>

                <input type="file"
                       name="certificate_file"
                       class="form-control"
                       accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                       required>

                <div class="form-text">
                    Format PDF, JPG, atau PNG. Maksimal 5 MB.
                </div>

            </div>


            <div class="info-notice">

                <span class="notice-icon">ⓘ</span>

                <span>
                    Setelah disimpan, sertifikat akan berstatus
                    <strong>Menunggu Verifikasi (Pending)</strong>.
                    Sertifikat belum ditampilkan pada portofolio publik sampai disetujui Admin.
                </span>

            </div>


            <div class="form-actions">

                <button type="submit" class="btn-save">
                    Simpan Sertifikat
                </button>

                <a href="<?= BASE_URL ?>/student/dashboard.php"
                   class="btn-back">
                    Kembali
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>
