
<?php
require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Permintaan tidak valid. Silakan muat ulang halaman.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Data proyek tidak valid.');
}

$db = Database::connection();
$userId = (int) $_SESSION['user']['id'];

$stmt = $db->prepare("
    SELECT p.id, p.publication_status
    FROM projects p
    INNER JOIN students s ON s.id = p.student_id
    WHERE p.id = :id
      AND s.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'id' => $id,
    'user_id' => $userId
]);

$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    exit('Proyek tidak ditemukan.');
}

if (!in_array($project['publication_status'], ['draft', 'revision'], true)) {
    exit('Proyek tidak dapat diajukan dengan status saat ini.');
}

$update = $db->prepare("
    UPDATE projects
    SET publication_status = 'pending',
        published_at = NULL
    WHERE id = :id
");

$update->execute(['id' => $id]);

header('Location: ' . BASE_URL . '/student/projects/index.php?submitted=1');
exit;
