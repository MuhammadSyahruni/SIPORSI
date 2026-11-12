<?php
require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

$db = Database::connection();
$csrfToken = Csrf::token();
$userId = (int) ($_SESSION['user']['id'] ?? 0);

// Pastikan akun yang login terhubung dengan data siswa.
$studentStmt = $db->prepare("
    SELECT id
    FROM students
    WHERE user_id = :user_id
    LIMIT 1
");
$studentStmt->execute(['user_id' => $userId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

$studentId = (int) $student['id'];
$certificateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$certificateId || $certificateId <= 0) {
    http_response_code(400);
    exit('ID sertifikat tidak valid.');
}

// Siswa hanya boleh mengubah sertifikat miliknya.
$certificateStmt = $db->prepare("
    SELECT
        c.*,
        (
            SELECT cr.review_notes
            FROM certificate_reviews cr
            WHERE cr.certificate_id = c.id
            ORDER BY cr.reviewed_at DESC, cr.id DESC
            LIMIT 1
        ) AS latest_review_notes
    FROM certificates c
    WHERE c.id = :id
      AND c.student_id = :student_id
    LIMIT 1
");

$certificateStmt->execute([
    'id' => $certificateId,
    'student_id' => $studentId
]);

$certificate = $certificateStmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    http_response_code(404);
    exit('Sertifikat tidak ditemukan atau bukan milik akun Anda.');
}

// Hanya sertifikat yang ditolak yang dapat diperbaiki.
if ($certificate['verification_status'] !== 'rejected') {
    header('Location: ' . BASE_URL . '/student/certificates/index.php?notice=not_editable');
    exit;
}

$errors = [];

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

    // Gunakan file lama jika siswa tidak mengunggah file baru.
    $certificateFile = $certificate['certificate_file'];

    // Validasi nama sertifikat.
    if ($certificateName === '') {
        $errors[] = 'Nama sertifikat wajib diisi.';
    } elseif (mb_strlen($certificateName) > 180) {
        $errors[] = 'Nama sertifikat maksimal 180 karakter.';
    }

    // Validasi penerbit.
    if (mb_strlen($organization) > 180) {
        $errors[] = 'Nama penerbit maksimal 180 karakter.';
    }

    // Validasi nomor sertifikat.
    if (mb_strlen($certificateNumber) > 100) {
        $errors[] = 'Nomor sertifikat maksimal 100 karakter.';
    }

    // Validasi tanggal.
    $validDate = static function ($value): bool {
        if ($value === '') {
            return true;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value;
    };

    if (!$validDate($issueDate)) {
        $errors[] = 'Tanggal terbit tidak valid.';
    }

    if (!$validDate($expiryDate)) {
        $errors[] = 'Tanggal kedaluwarsa tidak valid.';
    }

    if (
        $issueDate !== '' &&
        $expiryDate !== '' &&
        $expiryDate < $issueDate
    ) {
        $errors[] = 'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.';
    }

    $newUploadedPath = null;

    // Proses unggahan file baru jika dipilih.
    if (
        !$errors &&
        isset($_FILES['certificate_file']) &&
        $_FILES['certificate_file']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES['certificate_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Unggahan sertifikat gagal. Silakan pilih file kembali.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Ukuran file maksimal 5 MB.';
        } elseif (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'File yang diunggah tidak valid.';
        } else {

            $mime = mime_content_type($file['tmp_name']);

            $allowedTypes = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            if (!isset($allowedTypes[$mime])) {
                $errors[] = 'File sertifikat harus berformat PDF, JPG, atau PNG.';
            } else {

                $uploadDir = APP_ROOT . '/public/uploads/certificates/';

                if (
                    !is_dir($uploadDir) &&
                    !mkdir($uploadDir, 0755, true) &&
                    !is_dir($uploadDir)
                ) {
                    $errors[] = 'Folder penyimpanan sertifikat tidak dapat dibuat.';
                } else {
                    try {
                        $newName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];

                        if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                            $newUploadedPath = 'uploads/certificates/' . $newName;
                            $certificateFile = $newUploadedPath;
                        } else {
                            $errors[] = 'File sertifikat gagal disimpan.';
                        }

                    } catch (Throwable $e) {
                        $errors[] = 'Terjadi kesalahan saat menyiapkan file sertifikat.';
                    }
                }
            }
        }
    }

    // Pastikan sertifikat mempunyai file bukti.
    if (!$errors && empty($certificateFile)) {
        $errors[] = 'Sertifikat wajib memiliki file bukti. Silakan unggah file sertifikat.';
    }

    // Simpan perubahan.
    if (!$errors) {
        try {

            // Setelah diperbaiki, status kembali menjadi pending.
            $update = $db->prepare("
                UPDATE certificates SET
                    certificate_name = :certificate_name,
                    issuing_organization = :issuing_organization,
                    certificate_number = :certificate_number,
                    issue_date = :issue_date,
                    expiry_date = :expiry_date,
                    certificate_file = :certificate_file,
                    verification_status = 'pending'
                WHERE id = :id
                  AND student_id = :student_id
                  AND verification_status = 'rejected'
            ");

            $update->execute([
                'certificate_name' => $certificateName,
                'issuing_organization' => $organization !== '' ? $organization : null,
                'certificate_number' => $certificateNumber !== '' ? $certificateNumber : null,
                'issue_date' => $issueDate !== '' ? $issueDate : null,
                'expiry_date' => $expiryDate !== '' ? $expiryDate : null,
                'certificate_file' => $certificateFile,
                'id' => $certificateId,
                'student_id' => $studentId
            ]);

            if ($update->rowCount() < 1) {

                // Hapus file baru jika perubahan database gagal.
                if ($newUploadedPath !== null) {
                    $newFileFullPath = APP_ROOT . '/public/' . $newUploadedPath;

                    if (is_file($newFileFullPath)) {
                        unlink($newFileFullPath);
                    }
                }

                $errors[] = 'Sertifikat tidak dapat dikirim ulang. Muat ulang halaman dan periksa statusnya.';

            } else {
                header('Location: ' . BASE_URL . '/student/certificates/index.php?resubmitted=1');
                exit;
            }

        } catch (Throwable $e) {

            if ($newUploadedPath !== null) {
                $newFileFullPath = APP_ROOT . '/public/' . $newUploadedPath;

                if (is_file($newFileFullPath)) {
                    unlink($newFileFullPath);
                }
            }

            $errors[] = 'Gagal menyimpan perubahan sertifikat. Silakan coba kembali.';
        }
    }

    // Pertahankan isian jika validasi gagal.
    $certificate = array_merge($certificate, [
        'certificate_name' => $certificateName,
        'issuing_organization' => $organization,
        'certificate_number' => $certificateNumber,
        'issue_date' => $issueDate,
        'expiry_date' => $expiryDate,
        'certificate_file' => $certificateFile
    ]);
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$currentFileUrl = !empty($certificate['certificate_file'])
    ? BASE_URL . '/' . ltrim($certificate['certificate_file'], '/')
    : null;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Perbaiki Sertifikat - SIPORSI</title>

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
            box-shadow: 0 3px 14px rgba(16,46,89,.10);
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

        .current-file-box,
        .review-box {
            margin: 10px 0 16px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #f9fbfe;
        }

        .current-file-label,
        .review-label {
            margin-bottom: 8px;
            color: #52647a;
            font-size: 10px;
            font-weight: 700;
        }

        .file-link {
            color: var(--blue);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .file-link:hover {
            color: var(--navy);
            text-decoration: underline;
        }

        .review-box {
            border-color: #f1d59a;
            background: #fffaf0;
        }

        .review-label {
            color: #8a6418;
        }

        .review-notes {
            margin: 0;
            color: #6f5728;
            font-size: 11px;
            line-height: 1.8;
            white-space: pre-wrap;
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

        <a class="topbar-link" href="<?= BASE_URL ?>/student/certificates/index.php">
            Kembali ke Sertifikat
        </a>

    </div>
</header>

<main class="page-container">

    <div class="page-heading">

        <div class="breadcrumb-text">
            SIPORSI / Sertifikat / <span>Perbaiki Sertifikat</span>
        </div>

        <h1 class="page-title">Perbaiki Sertifikat</h1>

        <p class="page-description">
            Perbarui informasi atau ganti file sertifikat sesuai catatan
            pemeriksaan Admin, kemudian kirim ulang untuk diverifikasi.
        </p>

    </div>

    <section class="form-card">

        <h2 class="form-section-title">Informasi Sertifikat</h2>

        <p class="form-section-description">
            Periksa catatan Admin dan pastikan data serta file sertifikat
            sudah benar sebelum dikirim ulang.
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

        <?php if (!empty($certificate['latest_review_notes'])): ?>

            <div class="review-box">

                <div class="review-label">
                    Catatan perbaikan dari Admin
                </div>

                <p class="review-notes">
                    <?= e($certificate['latest_review_notes']) ?>
                </p>

            </div>

        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <div class="form-group">

                <label class="form-label" for="certificate_name">
                    Nama Sertifikat <span class="required-mark">*</span>
                </label>

                <input
                    type="text"
                    id="certificate_name"
                    name="certificate_name"
                    class="form-control"
                    maxlength="180"
                    value="<?= e($certificate['certificate_name']) ?>"
                    placeholder="Contoh: Sertifikat Kompetensi Keahlian"
                    required
                >

            </div>

            <div class="form-group">

                <label class="form-label" for="issuing_organization">
                    Penerbit / Penyelenggara
                </label>

                <input
                    type="text"
                    id="issuing_organization"
                    name="issuing_organization"
                    class="form-control"
                    maxlength="180"
                    value="<?= e($certificate['issuing_organization']) ?>"
                    placeholder="Nama lembaga atau penyelenggara"
                >

            </div>

            <div class="form-group">

                <label class="form-label" for="certificate_number">
                    Nomor Sertifikat
                </label>

                <input
                    type="text"
                    id="certificate_number"
                    name="certificate_number"
                    class="form-control"
                    maxlength="100"
                    value="<?= e($certificate['certificate_number']) ?>"
                    placeholder="Nomor yang tertera pada sertifikat"
                >

            </div>

            <div class="row">

                <div class="col-md-6 form-group">

                    <label class="form-label" for="issue_date">
                        Tanggal Terbit
                    </label>

                    <input
                        type="date"
                        id="issue_date"
                        name="issue_date"
                        class="form-control"
                        value="<?= e($certificate['issue_date']) ?>"
                    >

                </div>

                <div class="col-md-6 form-group">

                    <label class="form-label" for="expiry_date">
                        Tanggal Kedaluwarsa
                    </label>

                    <input
                        type="date"
                        id="expiry_date"
                        name="expiry_date"
                        class="form-control"
                        value="<?= e($certificate['expiry_date']) ?>"
                    >

                </div>

            </div>

            <div class="form-group">

                <label class="form-label">
                    File Sertifikat Saat Ini
                </label>

                <?php if ($currentFileUrl): ?>

                    <div class="current-file-box">

                        <div class="current-file-label">
                            File yang saat ini tersimpan
                        </div>

                        <a
                            class="file-link"
                            href="<?= e($currentFileUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            ↗ Lihat sertifikat saat ini
                        </a>

                    </div>

                <?php else: ?>

                    <div class="form-text mb-2">
                        Belum ada file sertifikat yang tersimpan.
                    </div>

                <?php endif; ?>

                <label class="form-label" for="certificate_file">
                    Ganti File Sertifikat (opsional)
                </label>

                <input
                    type="file"
                    id="certificate_file"
                    name="certificate_file"
                    class="form-control"
                    accept=".pdf,.jpg,.jpeg,.png"
                >

                <div class="form-text">
                    Unggah file baru apabila diminta oleh Admin atau file
                    sebelumnya perlu diperbaiki. Format PDF, JPG, atau PNG.
                    Maksimal 5 MB. File lama tetap digunakan jika tidak
                    memilih file baru.
                </div>

            </div>

            <div class="info-notice">

                <span aria-hidden="true">ⓘ</span>

                <span>
                    Setelah dikirim ulang, status sertifikat akan kembali
                    menjadi <strong>Menunggu Verifikasi</strong>.
                    Admin akan memeriksa kembali perubahan yang kamu kirimkan.
                </span>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn-save">
                    Simpan dan Kirim Ulang
                </button>

                <a
                    href="<?= BASE_URL ?>/student/certificates/index.php"
                    class="btn-back"
                >
                    Batal
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>