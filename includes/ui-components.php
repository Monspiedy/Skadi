<?php

declare(strict_types=1);

function renderStatePanel(string $type, string $heading, string $message): void
{
    $allowedTypes = ['empty', 'error', 'loading'];
    $safeType = in_array($type, $allowedTypes, true) ? $type : 'empty';
    $isLoading = $safeType === 'loading';
    ?>
    <section class="state-panel state-panel-<?= escape($safeType) ?>"
        <?= $isLoading ? 'aria-live="polite" aria-busy="true"' : '' ?>>
        <span class="state-symbol" aria-hidden="true"><?= $isLoading ? '' : escape($safeType === 'error' ? '!' : '◇') ?></span>
        <div>
            <h2><?= escape($heading) ?></h2>
            <p><?= escape($message) ?></p>
        </div>
    </section>
    <?php
}
