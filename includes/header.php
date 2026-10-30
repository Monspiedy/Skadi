<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? appConfig('name', 'Skadi');
$activeTheme = currentTheme();
$isSignedIn = isAuthenticated();
$themeColor = match ($activeTheme) {
    'dark' => '#15171b',
    'light' => '#eef5f8',
    default => '#081522',
};
?>
<!doctype html>
<html class="no-js" lang="en" data-theme="<?= escape($activeTheme) ?>"
    data-bs-theme="<?= escape(bootstrapColorMode($activeTheme)) ?>"
    data-authenticated="<?= $isSignedIn ? 'true' : 'false' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> | <?= escape((string) appConfig('name')) ?></title>
    <meta name="theme-color" content="<?= escape($themeColor) ?>">
    <link rel="icon" href="<?= escape(baseUrl('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <?php if ($isSignedIn): ?>
        <meta name="csrf-token" content="<?= escape(csrfToken()) ?>">
    <?php endif; ?>
    <script src="<?= escape(baseUrl('assets/js/theme-bootstrap.js')) ?>"></script>
    <link rel="stylesheet" href="<?= escape(baseUrl('assets/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= escape(baseUrl('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= escape(baseUrl('assets/css/themes.css')) ?>">
    <link rel="stylesheet" href="<?= escape(baseUrl('assets/css/layout.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php require SKADI_ROOT . '/includes/navigation.php'; ?>
<main class="site-main container" id="main-content" tabindex="-1">
    <?php require SKADI_ROOT . '/includes/flash-messages.php'; ?>
