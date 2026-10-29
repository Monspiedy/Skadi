<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderErrorPage(405, 'Method not allowed', 'Featured games must be saved from the administration form.');
}
requireValidCsrfToken($_POST['csrf_token'] ?? null);

$gameIds = normalizeFeaturedSelection($_POST['game_ids'] ?? []);
$error = featuredSelectionError(db(), $gameIds);
if ($error !== null) {
    addFlashMessage('error', $error);
    redirectTo('admin/featured.php');
}

saveFeaturedSelection(db(), $gameIds, (int) currentUser()['id']);
recordAdminAudit(db(), (int) currentUser()['id'], 'featured.updated', 'featured_games', 1, 'Saved ' . count($gameIds) . ' featured game(s).');
addFlashMessage('success', count($gameIds) . ' featured game(s) saved in storefront order.');
redirectTo('admin/featured.php');
