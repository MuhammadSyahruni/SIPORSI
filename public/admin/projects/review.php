
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
$action = $_POST['action'] ?? '';
$reviewNotes = trim($_POST['review_note'] ?? '');

$allowedActions = ['approve', 'revision', 'reject'];

if (!$id || $id < 1 || !in_array($action, $allowedActions, true)) {
    http_response_code(400);
    exit('Data pemeriksaan tidak valid.');
}

$reviewStatus = [
    'approve' => 'approved',
    'revision' => 'revision',
    'reject' => 'rejected'
][$action];

if ($action !== 'approve' && $reviewNotes === '') {
    http_response_code(422);
    exit('Catatan admin wajib diisi untuk revisi atau penolakan.');
}

if (mb_strlen($reviewNotes) > 10000) {
    http_response_code(422);
    exit('Catatan admin terlalu panjang.');
}

$db = Database::connection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT id, publication_status
        FROM projects
        WHERE id = :id
        FOR UPDATE
    ");

    $stmt->execute(['id' => $id]);
    $project = $stmt->fetch();

    if (!$project) {
        $db->rollBack();
        http_response_code(404);
        exit('Proyek tidak ditemukan.');
    }

    if (!in_array($project['publication_status'], ['draft', 'pending'], true)) {
        $db->rollBack();
        exit('Proyek ini sudah diperiksa dan tidak dapat diproses kembali.');
    }

    $adminId = (int) $_SESSION['user']['id'];

    $reviewStmt = $db->prepare("
        INSERT INTO project_reviews (
            project_id,
            reviewer_id,
            review_status,
            review_notes
        ) VALUES (
            :project_id,
            :reviewer_id,
            :review_status,
            :review_notes
        )
    ");

    $reviewStmt->execute([
        'project_id' => $id,
        'reviewer_id' => $adminId,
        'review_status' => $reviewStatus,
        'review_notes' => $reviewNotes !== '' ? $reviewNotes : null
    ]);

    $updateStmt = $db->prepare("
        UPDATE projects
        SET publication_status = :status,
            published_at = CASE
                WHEN :approved = 1 THEN NOW()
                ELSE NULL
            END
        WHERE id = :id
    ");

    $updateStmt->execute([
        'status' => $reviewStatus,
        'approved' => $reviewStatus === 'approved' ? 1 : 0,
        'id' => $id
    ]);

    $db->commit();

    header('Location: ' . BASE_URL . '/admin/projects/index.php?reviewed=1');
    exit;

} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log($exception->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan saat memproses pemeriksaan proyek.');
}
