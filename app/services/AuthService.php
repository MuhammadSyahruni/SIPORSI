
<?php

require_once APP_ROOT . '/app/core/Database.php';

final class AuthService
{
    private static string $errorMessage = 'Username atau password salah.';

    public static function getErrorMessage(): string
    {
        return self::$errorMessage;
    }

    public static function login(string $username, string $password): bool
    {
        self::$errorMessage = 'Username atau password salah.';

        $db = Database::connection();

        $sql = "
            SELECT
                u.id,
                u.username,
                u.email,
                u.password_hash,
                u.account_status,
                r.role_name,
                i.verification_status
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN industries i ON i.user_id = u.id
            WHERE u.username = :username
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['username' => $username]);

        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        if ($user['account_status'] !== 'active') {
            self::$errorMessage = 'Akun Anda tidak aktif. Hubungi administrator.';
            return false;
        }

        // Pemeriksaan verifikasi khusus akun industri
        if ($user['role_name'] === 'industry') {

            if ($user['verification_status'] === null) {
                self::$errorMessage = 'Profil industri tidak ditemukan.';
                return false;
            }

            if ($user['verification_status'] === 'pending') {
                self::$errorMessage = 'Pendaftaran berhasil, tetapi akun industri Anda masih menunggu verifikasi admin.';
                return false;
            }

            if ($user['verification_status'] === 'rejected') {
                self::$errorMessage = 'Pendaftaran industri Anda ditolak. Silakan hubungi administrator.';
                return false;
            }

            if ($user['verification_status'] !== 'verified') {
                self::$errorMessage = 'Status verifikasi industri tidak valid.';
                return false;
            }
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);

        unset($user['password_hash']);
        unset($user['verification_status']);

        $_SESSION['user'] = $user;

        $update = $db->prepare(
            "UPDATE users SET last_login = NOW() WHERE id = :id"
        );

        $update->execute(['id' => $user['id']]);

        return true;
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

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
}
