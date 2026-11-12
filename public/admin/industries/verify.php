
<?php

require_once __DIR__ . '/../../../app/config/app.php';
require_once APP_ROOT . '/app/middleware/AuthMiddleware.php';
require_once APP_ROOT . '/app/core/Database.php';
require_once APP_ROOT . '/app/core/Csrf.php';

AuthMiddleware::requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/industries/index.php');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Token keamanan tidak valid. Muat ulang halaman.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    header('Location: ' . BASE_URL . '/admin/industries/index.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];

$db = Database::connection();

try {
    $db->beginTransaction();

    $check = $db->prepare("
        SELECT id, user_id, verification_status
        FROM industries
        WHERE id = :id
        FOR UPDATE
    ");

    $check->execute(['id' => $id]);
    $industry = $check->fetch();

    if (!$industry || $industry['verification_status'] !== 'pending') {
        $db->rollBack();
        header('Location: ' . BASE_URL . '/admin/industries/index.php');
        exit;
    }

    $newStatus = $action === 'approve' ? 'verified' : 'rejected';

    $update = $db->prepare("
        UPDATE industries
        SET verification_status = :status,
            verified_by = :admin_id,
            verified_at = NOW()
        WHERE id = :id
          AND verification_status = 'pending'
    ");

    $update->execute([
        'status' => $newStatus,
        'admin_id' => $userId,
        'id' => $id
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Status verifikasi gagal diperbarui.');
    }

    $db->commit();

    header(
        'Location: ' . BASE_URL .
        '/admin/industries/detail.php?id=' . $id .
        '&verified=' . $newStatus
    );
    exit;

} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log($exception->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan saat memproses verifikasi industri.');
}
