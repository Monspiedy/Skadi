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
        Skadi is an educational game store and player community. Create an account to receive
        an auditable EUR 100.00 demonstration balance, or sign in to continue.
    </p>
    <?php if (isAuthenticated()): ?>
        <a class="button-link" href="<?= escape(baseUrl(authenticationDestination())) ?>">Open account</a>
    <?php else: ?>
        <a class="button-link" href="<?= escape(baseUrl('register.php')) ?>">Create account</a>
    <?php endif; ?>
</section>

<section class="card-grid" aria-label="Account features">
    <article class="card">
        <h2>Private sign-in</h2>
        <p>Use your permanent private login name or your email address.</p>
    </article>
    <article class="card">
        <h2>Secure passwords</h2>
        <p>Passwords are hashed and temporary seed credentials must be replaced on first login.</p>
    </article>
    <article class="card">
        <h2>Demo wallet</h2>
        <p>New accounts begin with EUR 100.00 and a matching wallet ledger entry.</p>
    </article>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
