<?php

require_once __DIR__ . '/../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';

// Pastikan sesi pengguna tersedia.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$db = Database::connection();

// Ambil ID sertifikat.
$certificateId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$certificateId || $certificateId <= 0) {
    http_response_code(400);
    exit('ID sertifikat tidak valid.');
}

// Identifikasi pengguna.
$userId = (int) ($_SESSION['user']['id'] ?? 0);
$roleId = 0;
$accountActive = false;

// Periksa role dan status akun terbaru.
if ($userId > 0) {
    $roleStmt = $db->prepare("
        SELECT role_id, account_status
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $roleStmt->execute([
        'user_id' => $userId
    ]);

    $userAccount = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if ($userAccount) {
        $roleId = (int) $userAccount['role_id'];
        $accountActive = (
            $userAccount['account_status'] === 'active'
        );
    }
}

// Ambil data sertifikat dan pemiliknya.
$stmt = $db->prepare("
    SELECT
        c.id,
        c.student_id,
        c.certificate_file,
        c.verification_status,
        s.public_slug,
        s.is_public,
        s.student_status
    FROM certificates c
    INNER JOIN students s
        ON s.id = c.student_id
    WHERE c.id = :certificate_id
    LIMIT 1
");

$stmt->execute([
    'certificate_id' => $certificateId
]);

$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    http_response_code(404);
    exit('Sertifikat tidak ditemukan.');
}

// Tentukan hak akses.
$authorized = false;

// ADMIN aktif.
if (
    $userId > 0 &&
    $accountActive &&
    $roleId === 1
) {
    $authorized = true;
}

// SISWA aktif: hanya sertifikat miliknya sendiri.
elseif (
    $userId > 0 &&
    $accountActive &&
    $roleId === 2
) {
    $ownerStmt = $db->prepare("
        SELECT id
        FROM students
        WHERE user_id = :user_id
          AND id = :student_id
        LIMIT 1
    ");

    $ownerStmt->execute([
        'user_id' => $userId,
        'student_id' => $certificate['student_id']
    ]);

    $authorized = (bool) $ownerStmt->fetchColumn();
}

// INDUSTRI aktif dan pengunjung publik.
elseif (
    $userId === 0 ||
    (
        $userId > 0 &&
        $accountActive &&
        $roleId === 3
    )
) {
    $slug = trim($_GET['slug'] ?? '');

    $authorized =
        $slug !== '' &&
        !empty($certificate['public_slug']) &&
        hash_equals(
            (string) $certificate['public_slug'],
            $slug
        ) &&
        $certificate['verification_status'] === 'verified' &&
        (int) $certificate['is_public'] === 1 &&
        in_array(
            $certificate['student_status'],
            ['active', 'graduated'],
            true
        );
}

// Tolak akses yang tidak memiliki izin.
if (!$authorized) {
    http_response_code(403);
    exit('Anda tidak memiliki izin untuk membuka file ini.');
}

// Validasi lokasi file dari database.
$storedPath = (string) $certificate['certificate_file'];

if (!preg_match(
    '~\Auploads/certificates/[a-f0-9]{32}\.(pdf|jpg|png)\z~i',
    $storedPath
)) {
    http_response_code(404);
    exit('Lokasi file tidak valid.');
}

// Pastikan file berada di folder sertifikat yang ditentukan.
$uploadDir = realpath(
    APP_ROOT . '/public/uploads/certificates'
);

$filePath = realpath(
    APP_ROOT . '/public/' . $storedPath
);

if (
    $uploadDir === false ||
    $filePath === false ||
    dirname($filePath) !== $uploadDir ||
    !is_file($filePath) ||
    !is_readable($filePath)
) {
    http_response_code(404);
    exit('File sertifikat tidak ditemukan.');
}

// Periksa MIME type file sebenarnya.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

if ($mimeType === false) {
    http_response_code(500);
    exit('Gagal membaca MIME type file.');
}

$allowedMimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'png' => 'image/png'
];

$extension = strtolower(
    pathinfo($filePath, PATHINFO_EXTENSION)
);

if (
    !isset($allowedMimeTypes[$extension]) ||
    $mimeType !== $allowedMimeTypes[$extension]
) {
    http_response_code(415);
    exit('Format file tidak sesuai.');
}

// Bersihkan output buffer.
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Kirim file melalui PHP.
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header(
    'Content-Disposition: inline; filename="sertifikat.'
    . $extension . '"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);
exit;
