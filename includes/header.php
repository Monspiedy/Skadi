<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? appConfig('name', 'Skadi');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> | <?= escape((string) appConfig('name')) ?></title>
    <link rel="stylesheet" href="<?= escape(baseUrl('assets/css/app.css')) ?>">
</head>
<body>
<?php require SKADI_ROOT . '/includes/navigation.php'; ?>
<main class="site-main">
    <?php require SKADI_ROOT . '/includes/flash-messages.php'; ?>
