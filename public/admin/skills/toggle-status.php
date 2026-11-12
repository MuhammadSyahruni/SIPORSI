
<?php
require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

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
    header('Location: ' . BASE_URL . '/admin/skills/index.php');
    exit;
}

$db = Database::connection();

$stmt = $db->prepare("
    SELECT is_active
    FROM skills
    WHERE id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);
$skill = $stmt->fetch();

if (!$skill) {
    header('Location: ' . BASE_URL . '/admin/skills/index.php');
    exit;
}

$newStatus = (int) $skill['is_active'] === 1 ? 0 : 1;

$update = $db->prepare("
    UPDATE skills
    SET is_active = :status
    WHERE id = :id
");

$update->execute([
    'status' => $newStatus,
    'id' => $id,
]);

header('Location: ' . BASE_URL . '/admin/skills/index.php?status_changed=1');
exit;
