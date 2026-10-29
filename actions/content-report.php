<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php'; requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { renderErrorPage(405, 'Method not allowed', 'Reports require POST.'); }
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$type = is_string($_POST['target_type'] ?? null) ? $_POST['target_type'] : '';
$targetId = communityPositiveInt($_POST['target_id'] ?? null) ?? 0;
$reason = is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '';
$details = is_string($_POST['details'] ?? null) ? $_POST['details'] : '';
$returnTo = is_string($_POST['return_to'] ?? null) ? $_POST['return_to'] : 'community.php';
if (preg_match('/\A[a-z][a-z0-9-]*\.php(?:\?[^\r\n]*)?(?:#[^\r\n]*)?\z/', $returnTo) !== 1) { $returnTo = 'community.php'; }
try { submitContentReport(db(), (int) currentUser()['id'], $type, $targetId, $reason, $details); addFlashMessage('success', 'Report submitted for administrator review.'); } catch (CommunityException $exception) { addFlashMessage('error', $exception->getMessage()); }
redirectTo($returnTo);
