<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/majors/index.php');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Token keamanan tidak valid.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: ' . BASE_URL . '/admin/majors/index.php');
    exit;
}

$db = Database::connection();

$stmt = $db->prepare("
    UPDATE majors
    SET is_active = CASE
        WHEN is_active = 1 THEN 0
        ELSE 1
    END
    WHERE id = :id
");

$stmt->execute(['id' => $id]);

header('Location: ' . BASE_URL . '/admin/majors/index.php?status_changed=1');
exit;
