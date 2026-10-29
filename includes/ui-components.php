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

/**
 * Render one consistent game-cover thumbnail beside an existing visible game title.
 * The image is decorative because the adjacent title carries the accessible label.
 */
function renderGameThumbnail(array $game, string $size = 'small'): void
{
    $allowedSizes = ['small', 'medium', 'large'];
    $safeSize = in_array($size, $allowedSizes, true) ? $size : 'small';
    $title = trim((string) ($game['game_title'] ?? $game['title'] ?? 'Game'));
    $coverPath = is_string($game['cover_path'] ?? null) ? $game['cover_path'] : null;
    $coverUrl = safeGameMediaUrl($coverPath);
    $initials = mb_strtoupper(mb_substr($title, 0, 2));
    ?>
    <span class="game-thumbnail game-thumbnail-<?= escape($safeSize) ?>" aria-hidden="true">
        <?php if ($coverUrl !== null): ?>
            <img src="<?= escape($coverUrl) ?>" alt="">
        <?php else: ?>
            <span class="game-thumbnail-placeholder"><?= escape($initials) ?></span>
        <?php endif; ?>
    </span>
    <?php
}
