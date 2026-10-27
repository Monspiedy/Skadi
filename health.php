<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$databaseRequested = ($_GET['database'] ?? '') === '1';
$checks = [
    [
        'label' => 'PHP 8.2 or newer',
        'passed' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'detail' => 'Running PHP ' . PHP_VERSION,
    ],
    [
        'label' => 'PDO extension',
        'passed' => extension_loaded('pdo'),
        'detail' => extension_loaded('pdo') ? 'Available' : 'Not available',
    ],
    [
        'label' => 'PDO MySQL driver',
        'passed' => extension_loaded('pdo_mysql'),
        'detail' => extension_loaded('pdo_mysql') ? 'Available' : 'Not available',
    ],
    [
        'label' => 'Session',
        'passed' => session_status() === PHP_SESSION_ACTIVE,
        'detail' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not active',
    ],
    [
        'label' => 'Application timezone',
        'passed' => date_default_timezone_get() === 'Europe/Helsinki',
        'detail' => date_default_timezone_get(),
    ],
];

$databaseStatus = hasDatabaseConfig()
    ? 'Local configuration found. Connectivity was not tested.'
    : 'Not configured. Copy config/database.example.php to config/database.local.php.';
$databasePassed = null;

if ($databaseRequested) {
    try {
        db()->query('SELECT 1')->fetchColumn();
        $databaseStatus = 'Connection successful.';
        $databasePassed = true;
    } catch (Throwable $exception) {
        error_log('Health-check database failure: ' . $exception->getMessage());
        $databaseStatus = hasDatabaseConfig()
            ? 'Connection failed. Check the local database service and credentials.'
            : 'Configuration is missing. Copy the example file and enter local credentials.';
        $databasePassed = false;
    }
}

$pageTitle = 'System health';
require SKADI_ROOT . '/includes/header.php';
?>
<section aria-labelledby="health-heading">
    <p class="eyebrow">Development check</p>
    <h1 id="health-heading">System health</h1>
    <p>This page reports only non-sensitive application requirements.</p>

    <div class="check-list">
        <?php foreach ($checks as $check): ?>
            <article class="check-row">
                <span class="status-badge <?= $check['passed'] ? 'status-ok' : 'status-error' ?>">
                    <?= $check['passed'] ? 'Ready' : 'Needs attention' ?>
                </span>
                <div>
                    <h2><?= escape($check['label']) ?></h2>
                    <p><?= escape($check['detail']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>

        <article class="check-row">
            <span class="status-badge <?= $databasePassed === true ? 'status-ok' : 'status-neutral' ?>">
                <?= $databasePassed === true ? 'Ready' : ($databasePassed === false ? 'Unavailable' : 'Optional') ?>
            </span>
            <div>
                <h2>Database</h2>
                <p><?= escape($databaseStatus) ?></p>
                <?php if (!$databaseRequested): ?>
                    <a href="<?= escape(baseUrl('health.php?database=1')) ?>">Test database connectivity</a>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
