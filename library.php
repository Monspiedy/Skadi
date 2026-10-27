<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

requireAuth();

$pageTitle = 'Library';
require SKADI_ROOT . '/includes/header.php';
?>
<section aria-labelledby="library-heading">
    <p class="eyebrow">Module preview</p>
    <h1 id="library-heading">Library</h1>
    <p>Ownership and library tools are scheduled for Phase 08.</p>
    <?php renderStatePanel('empty', 'No library view yet', 'This placeholder makes the destination clear without adding business behavior.'); ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
