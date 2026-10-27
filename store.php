<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$pageTitle = 'Store';
require SKADI_ROOT . '/includes/header.php';
?>
<section aria-labelledby="store-heading">
    <p class="eyebrow">Module preview</p>
    <h1 id="store-heading">Store</h1>
    <p>The searchable game catalog is scheduled for Phase 05.</p>
    <?php renderStatePanel('empty', 'Catalog coming next', 'No catalog query runs on this placeholder page.'); ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
