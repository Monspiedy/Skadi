<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'review';
$queue = loadAdminModerationQueue(db(), $type, adminPositivePage($_GET['page'] ?? 1));
$types = adminModerationTypes();
$pageTitle = 'Content moderation';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="moderation-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="moderation-heading">Content moderation</h1>
    <p>Public visibility follows the stored status immediately. Deleted content remains retained for audit and cannot be restored.</p>
    <?php renderAdminNav('moderation'); ?>
    <nav class="admin-subnav" aria-label="Moderation content types">
        <?php foreach ($types as $key => $definition): ?>
            <a <?= $queue['type'] === $key ? 'aria-current="page"' : '' ?> href="<?= escape(baseUrl('admin/moderation.php?type=' . $key)) ?>"><?= escape($definition['label']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($queue['items'] === []): ?>
        <?php renderStatePanel('empty', 'No content', 'This moderation queue is empty.'); ?>
    <?php else: ?>
        <div class="admin-user-grid">
            <?php foreach ($queue['items'] as $item): ?>
                <article class="admin-panel">
                    <h2><?= escape($item['title']) ?></h2>
                    <p><span class="status-badge"><?= escape(ucfirst($item['status'])) ?></span> · <?= escape($item['game_title']) ?> · <?= escape($item['display_name']) ?> (<?= escape($item['skadi_id']) ?>)</p>
                    <p><?= escape(mb_strimwidth((string) $item['body'], 0, 260, '…')) ?></p>
                    <form class="admin-form" method="post" action="<?= escape(baseUrl('actions/admin/content-moderate.php')) ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="content_type" value="<?= escape($queue['type']) ?>">
                        <input type="hidden" name="content_id" value="<?= (int) $item['id'] ?>">
                        <div class="admin-form-grid">
                            <label>Status
                                <select name="status" required>
                                    <?php foreach ([$queue['definition']['public'], 'hidden', 'deleted'] as $status): ?>
                                        <option value="<?= escape($status) ?>" <?= $item['status'] === $status ? 'selected' : '' ?>><?= escape(ucfirst($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Reason
                                <input name="reason" minlength="3" maxlength="500" required>
                            </label>
                        </div>
                        <?php if ($queue['type'] === 'topic'): ?>
                            <label><input type="checkbox" name="is_pinned" value="1" <?= $item['is_pinned'] ? 'checked' : '' ?>> Pinned</label>
                            <label><input type="checkbox" name="is_locked" value="1" <?= $item['is_locked'] ? 'checked' : '' ?>> Locked</label>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-warning" type="submit">Apply moderation</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <?php renderAdminPagination('admin/moderation.php', $queue, ['type' => $queue['type']]); ?>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
