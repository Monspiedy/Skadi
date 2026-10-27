<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$pageTitle = 'Home';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="hero" aria-labelledby="welcome-heading">
    <p class="eyebrow">Store and community</p>
    <h1 id="welcome-heading">Welcome to Skadi</h1>
    <p>
        Cross the frostline into an original educational game store and player community,
        designed to remain clear, responsive and fully available during an offline presentation.
    </p>
    <?php if (isAuthenticated()): ?>
        <a class="button-link" href="<?= escape(baseUrl(authenticationDestination())) ?>">Open account</a>
    <?php else: ?>
        <a class="button-link" href="<?= escape(baseUrl('register.php')) ?>">Create account</a>
    <?php endif; ?>
</section>

<section class="card-grid" aria-label="Interface features">
    <article class="card">
        <h2>Three clear themes</h2>
        <p>Choose Skadi, neutral Dark or accessible Light from the navigation on every page.</p>
    </article>
    <article class="card">
        <h2>Built for every screen</h2>
        <p>The shared navigation, content rhythm and footer adapt from wide displays to mobile.</p>
    </article>
    <article class="card">
        <h2>Offline-ready shell</h2>
        <p>Bootstrap and Skadi's own visual assets are stored locally for a dependable demo.</p>
    </article>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
