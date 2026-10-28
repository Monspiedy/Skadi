<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$pageTitle = 'Community';
require SKADI_ROOT . '/includes/header.php';
?>
<section aria-labelledby="community-heading">
    <p class="eyebrow">Community</p>
    <h1 id="community-heading">Community</h1>
    <p>Share game knowledge and connect with other Skadi players.</p>
    <div class="community-links">
        <a class="card" href="<?= escape(baseUrl('guides.php')) ?>"><h2>Player guides</h2><p>Read safe, formatted walkthroughs, strategies and builds.</p></a>
        <a class="card" href="<?= escape(baseUrl('people.php')) ?>"><h2>Find people</h2><p>Search public profiles and manage friendships.</p></a>
    </div>
    <p class="form-note">Discussions and broader Community Center features remain reserved for Phase 13.</p>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
