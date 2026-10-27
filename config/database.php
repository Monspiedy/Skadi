<?php

declare(strict_types=1);

function hasDatabaseConfig(): bool
{
    return is_file(SKADI_ROOT . '/config/database.local.php');
}

function loadDatabaseConfig(): array
{
    $configPath = SKADI_ROOT . '/config/database.local.php';

    if (!is_file($configPath)) {
        throw new RuntimeException(
            'Database configuration is missing. Copy config/database.example.php to '
            . 'config/database.local.php and enter your local credentials.'
        );
    }

    $config = require $configPath;
    $requiredKeys = ['host', 'port', 'database', 'charset', 'username', 'password'];

    if (!is_array($config) || array_diff($requiredKeys, array_keys($config)) !== []) {
        throw new RuntimeException(
            'Database configuration is incomplete. Compare config/database.local.php with the example file.'
        );
    }

    return $config;
}

function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = loadDatabaseConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        (int) $config['port'],
        $config['database'],
        $config['charset']
    );

    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
