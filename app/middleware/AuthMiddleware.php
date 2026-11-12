<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SIPORSI AuthMiddleware Compatibility Loader
|--------------------------------------------------------------------------
| Mengarahkan pemanggilan middleware lama ke middleware utama.
*/

require_once dirname(__DIR__) . '/core/AuthMiddleware.php';
