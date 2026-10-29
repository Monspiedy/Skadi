<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Reports must be handled from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$reportId = communityPositiveInt($_POST['report_id'] ?? null);
if ($reportId === null) {
    renderErrorPage(404, 'Report not found', 'The submitted report identifier is invalid.');
}
try {
    resolveAdminReport(db(), (int) currentUser()['id'], $reportId, (string) ($_POST['status'] ?? ''), (string) ($_POST['resolution_note'] ?? ''));
    addFlashMessage('success', 'Report handled and audited.');
} catch (AdminException $exception) {
    addFlashMessage('warning', $exception->getMessage());
}
redirectTo('admin/reports.php');
