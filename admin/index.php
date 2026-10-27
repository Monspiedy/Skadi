<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

requireAdmin();

$pageTitle = 'Administration';
require SKADI_ROOT . '/includes/header.php';
?>
<section aria-labelledby="admin-heading">
    <p class="eyebrow">Admin-only page</p>
    <h1 id="admin-heading">Administration access confirmed</h1>
    <p>This protected placeholder demonstrates the admin guard. Administrative CRUD begins in Phase 14.</p>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
