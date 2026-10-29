<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { renderErrorPage(405, 'Method not allowed', 'Reply edits require POST.'); }
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$replyId = communityPositiveInt($_POST['reply_id'] ?? null) ?? 0;
try { $topicId = editDiscussionReply(db(), (int) currentUser()['id'], $replyId, is_string($_POST['body'] ?? null) ? $_POST['body'] : ''); addFlashMessage('success', 'Reply updated.'); redirectTo('discussion.php?id=' . $topicId . '#reply-' . $replyId); }
catch (CommunityException $exception) { addFlashMessage('error', $exception->getMessage()); redirectTo('discussions.php'); }
