<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
requireAdmin();

$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'open';
$reports = loadAdminReports(db(), $status, adminPositivePage($_GET['page'] ?? 1));
$pageTitle = 'Content reports';
require SKADI_ROOT . '/includes/header.php';
?>
<section class="admin-shell" aria-labelledby="reports-heading">
    <p class="eyebrow">Administration</p>
    <h1 id="reports-heading">Content reports</h1>
    <p>This queue contains public-content metadata only. Private conversations are outside this panel.</p>
    <?php renderAdminNav('reports'); ?>
    <nav class="admin-subnav" aria-label="Report status">
        <?php foreach (['open', 'reviewing', 'resolved', 'dismissed', 'all'] as $filter): ?>
            <a <?= $reports['status'] === $filter ? 'aria-current="page"' : '' ?> href="<?= escape(baseUrl('admin/reports.php?status=' . $filter)) ?>"><?= escape(ucfirst($filter)) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($reports['items'] === []): ?>
        <?php renderStatePanel('empty', 'No reports', 'No reports match this status.'); ?>
    <?php else: ?>
        <?php foreach ($reports['items'] as $report): ?>
            <article class="admin-panel">
                <h2>Report #<?= (int) $report['id'] ?> · <?= escape(adminReportTargetLabel((int) $report['target_type_code'])) ?> #<?= (int) $report['target_record_id'] ?></h2>
                <p><span class="status-badge"><?= escape(ucfirst($report['status'])) ?></span> · <?= escape(ucfirst($report['reason'])) ?> · <?= escape($report['reporter_display_name']) ?> (<?= escape($report['reporter_skadi_id']) ?>)</p>
                <?php if ($report['details']): ?><p><?= escape($report['details']) ?></p><?php endif; ?>
                <?php if (in_array($report['status'], ['open', 'reviewing'], true)): ?>
                    <form class="admin-form" method="post" action="<?= escape(baseUrl('actions/admin/report-resolve.php')) ?>">
                        <?= csrfInput() ?><input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                        <label>Resolution note<textarea name="resolution_note" minlength="3" maxlength="1000" rows="2" required></textarea></label>
                        <button class="btn btn-sm btn-primary" name="status" value="resolved" type="submit">Resolve</button>
                        <button class="btn btn-sm btn-outline-secondary" name="status" value="dismissed" type="submit">Dismiss</button>
                    </form>
                <?php else: ?>
                    <p><strong>Resolution:</strong> <?= escape($report['resolution_note'] ?? '') ?> · <?= escape($report['handler_skadi_id'] ?? 'unknown administrator') ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php renderAdminPagination('admin/reports.php', $reports, ['status' => $reports['status']]); ?>
    <?php endif; ?>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
