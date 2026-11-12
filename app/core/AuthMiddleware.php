<?php
declare(strict_types=1);

final class AuthMiddleware
{
    /**
     * Memastikan pengguna sudah login
     * dan status akunnya masih aktif.
     */
    public static function requireLogin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Belum login.
        if (!isset($_SESSION['user'])) {
            self::redirectToLogin();
        }

        // Pastikan ID pengguna tersedia.
        $userId = $_SESSION['user']['id'] ?? null;

        if (!is_scalar($userId) || (string) $userId === '') {
            self::destroySession();
            self::redirectToLogin();
        }

        // Periksa status akun langsung dari database.
        require_once APP_ROOT . '/app/core/Database.php';

        try {
            $db = Database::connection();

            $stmt = $db->prepare(
                "SELECT account_status FROM users WHERE id = :id LIMIT 1"
            );

            $stmt->execute(['id' => $userId]);

            $accountStatus = $stmt->fetchColumn();

            // Akun tidak ditemukan atau sudah tidak aktif.
            if ($accountStatus !== 'active') {
                self::destroySession();
                self::redirectToLogin();
            }

        } catch (Throwable $exception) {
            error_log(
                'AuthMiddleware account status check failed: '
                . $exception->getMessage()
            );

            // Gagal memeriksa database: jangan izinkan akses.
            http_response_code(503);
            exit('Layanan autentikasi sementara tidak tersedia.');
        }
    }

    /**
     * Memastikan role pengguna sesuai izin.
     */
    public static function requireRole(array $allowedRoles): void
    {
        self::requireLogin();

        $role = $_SESSION['user']['role_name'] ?? '';

        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(403);
            exit('403 - Akses ditolak.');
        }
    }

    /**
     * Menghapus session pengguna.
     */
    private static function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Mengarahkan pengguna ke halaman login.
     */
    private static function redirectToLogin(): void
    {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}
