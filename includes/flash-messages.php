<?php

declare(strict_types=1);

$flashMessages = consumeFlashMessages();
?>
<?php if ($flashMessages !== []): ?>
    <div class="flash-list" aria-live="polite">
        <?php foreach ($flashMessages as $flashMessage): ?>
            <div class="flash flash-<?= escape($flashMessage['type']) ?>">
                <?= escape($flashMessage['message']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
