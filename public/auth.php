<?php

require_once __DIR__ . '/../app/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/routes/auth.php';