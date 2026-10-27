<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$pageTitle = 'Home';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="hero" aria-labelledby="welcome-heading">
    <p class="eyebrow">PHP foundation ready</p>
    <h1 id="welcome-heading">Welcome to Skadi</h1>
    <p>
        Skadi is an educational game store and player community. The shared PHP foundation is
        now in place; catalog and account features will arrive in later phases.
    </p>
    <a class="button-link" href="<?= escape(baseUrl('health.php')) ?>">View system health</a>
</section>

<section class="card-grid" aria-label="Foundation features">
    <article class="card">
        <h2>Reusable layout</h2>
        <p>Pages share one header, navigation, message area and footer.</p>
    </article>
    <article class="card">
        <h2>Safe defaults</h2>
        <p>Sessions, output encoding, CSRF tokens and guarded errors are centralized.</p>
    </article>
    <article class="card">
        <h2>Database ready</h2>
        <p>PDO connects only when a page needs it and keeps local credentials outside Git.</p>
    </article>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
