<?php

declare(strict_types=1);
?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="footer-brand" href="<?= escape(baseUrl()) ?>">Skadi</a>
            <p>An original educational game store and community. Demo currency only.</p>
        </div>
        <nav aria-label="Footer navigation">
            <a href="<?= escape(baseUrl('store.php')) ?>">Store</a>
            <a href="<?= escape(baseUrl('community.php')) ?>">Community</a>
            <a href="<?= escape(baseUrl('guides.php')) ?>">Guides</a>
            <a href="<?= escape(baseUrl('health.php')) ?>">System health</a>
        </nav>
        <p class="footer-meta">&copy; <?= escape(date('Y')) ?> Skadi. Built for offline demonstration.</p>
    </div>
</footer>
<script src="<?= escape(baseUrl('assets/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= escape(baseUrl('assets/js/theme-selector.js')) ?>"></script>
<?php foreach (($pageScripts ?? []) as $scriptPath): ?>
    <script src="<?= escape(baseUrl((string) $scriptPath)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
