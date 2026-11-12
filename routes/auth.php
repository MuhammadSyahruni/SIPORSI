<?php

require_once APP_ROOT . '/app/services/AuthService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once APP_ROOT . '/app/core/Csrf.php';

    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Permintaan tidak valid. Silakan muat ulang halaman login.');
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $_SESSION['login_error'] = 'Username dan password wajib diisi.';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    try {
        if (AuthService::login($username, $password)) {
            $role = $_SESSION['user']['role_name'];

            switch ($role) {
                case 'admin':
                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                    break;

                case 'student':
                    header('Location: ' . BASE_URL . '/student/dashboard.php');
                    break;

                case 'industry':
                    header('Location: ' . BASE_URL . '/industry/dashboard.php');
                    break;

                default:
                    AuthService::logout();
                    header('Location: ' . BASE_URL . '/login.php');
                    break;
            }

            exit;
        }

        $_SESSION['login_error'] = AuthService::getErrorMessage();
        header('Location: ' . BASE_URL . '/login.php');
        exit;

    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        $_SESSION['login_error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

header('Location: ' . BASE_URL . '/login.php');
exit;
