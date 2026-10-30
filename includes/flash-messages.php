<?php

declare(strict_types=1);

$flashMessages = consumeFlashMessages();
$successMessages = array_values(array_filter(
    $flashMessages,
    static fn (array $message): bool => ($message['type'] ?? null) === 'success'
));
$fallbackMessages = array_values(array_filter(
    $flashMessages,
    static fn (array $message): bool => ($message['type'] ?? null) !== 'success'
));
?>
<?php if ($fallbackMessages !== []): ?>
    <div class="flash-list" aria-live="assertive">
        <?php foreach ($fallbackMessages as $flashMessage): ?>
            <div class="flash flash-<?= escape($flashMessage['type']) ?>">
                <?= escape($flashMessage['message']) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($successMessages !== []): ?>
    <div class="flash-list toast-region" aria-live="polite" aria-label="Success messages" data-toast-region>
        <?php foreach ($successMessages as $flashMessage): ?>
            <div class="flash flash-success success-toast" data-success-toast>
                <span><?= escape($flashMessage['message']) ?></span>
                <button class="toast-dismiss" type="button" aria-label="Dismiss notification"
                    data-toast-dismiss hidden>&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
