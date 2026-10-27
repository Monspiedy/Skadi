<?php

declare(strict_types=1);

define('SKADI_ROOT', dirname(__DIR__));

$appConfig = require SKADI_ROOT . '/config/app.php';
$GLOBALS['appConfig'] = $appConfig;

date_default_timezone_set($appConfig['timezone']);

require_once SKADI_ROOT . '/includes/error-handler.php';
require_once SKADI_ROOT . '/includes/url.php';
require_once SKADI_ROOT . '/includes/html.php';
require_once SKADI_ROOT . '/includes/flash.php';
require_once SKADI_ROOT . '/includes/csrf.php';
require_once SKADI_ROOT . '/includes/validation.php';
require_once SKADI_ROOT . '/config/database.php';
require_once SKADI_ROOT . '/includes/auth.php';
require_once SKADI_ROOT . '/includes/auth-service.php';
require_once SKADI_ROOT . '/includes/theme.php';
require_once SKADI_ROOT . '/includes/ui-components.php';
require_once SKADI_ROOT . '/includes/catalog.php';

initializeErrorHandling($appConfig);

if (session_status() === PHP_SESSION_NONE) {
    session_name($appConfig['session_name']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $appConfig['base_path'] . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

synchronizeAuthenticatedSession();
