<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/app/core/Csrf.php';
require_once APP_ROOT . '/app/services/AuthService.php';

// Logout hanya boleh melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

// Verifikasi CSRF
if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Permintaan tidak valid.');
}

// Hapus sesi login
AuthService::logout();

header('Location: ' . BASE_URL . '/login.php');
exit;