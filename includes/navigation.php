<?php

declare(strict_types=1);
?>
<header class="site-header">
    <nav class="site-nav" aria-label="Main navigation">
        <a class="brand" href="<?= escape(baseUrl()) ?>">Skadi</a>
        <div class="nav-links">
            <a href="<?= escape(baseUrl()) ?>">Home</a>
            <a href="<?= escape(baseUrl('health.php')) ?>">Health check</a>
        </div>
    </nav>
</header>
