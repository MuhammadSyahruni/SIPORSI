
<?php
require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Permintaan tidak valid. Silakan muat ulang halaman.');
}

$db = Database::connection();

$stmt = $db->prepare("
    SELECT id FROM students
    WHERE user_id = :user_id
    LIMIT 1
");
$stmt->execute(['user_id' => $_SESSION['user']['id']]);
$student = $stmt->fetch();

if (!$student) {
    exit('Data siswa tidak ditemukan.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    exit('ID prestasi tidak valid.');
}

$delete = $db->prepare("
    DELETE FROM achievements
    WHERE id = :id AND student_id = :student_id
");

$delete->execute([
    'id' => $id,
    'student_id' => $student['id']
]);

header('Location: ' . BASE_URL . '/student/achievements/index.php?deleted=1');
exit;
