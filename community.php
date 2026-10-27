<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$pageTitle = 'Community';
require SKADI_ROOT . '/includes/header.php';
?>
<section aria-labelledby="community-heading">
    <p class="eyebrow">Module preview</p>
    <h1 id="community-heading">Community</h1>
    <p>Guides, discussions and community posts arrive in later approved phases.</p>
    <?php renderStatePanel('empty', 'The camp is quiet', 'Community features are clearly reserved for later phases.'); ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
