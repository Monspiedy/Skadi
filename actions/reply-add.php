<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { renderErrorPage(405, 'Method not allowed', 'Replies require POST.'); }
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$topicId = communityPositiveInt($_POST['topic_id'] ?? null) ?? 0;
try { addDiscussionReply(db(), (int) currentUser()['id'], $topicId, is_string($_POST['body'] ?? null) ? $_POST['body'] : ''); addFlashMessage('success', 'Reply posted.'); }
catch (CommunityException $exception) { addFlashMessage('error', $exception->getMessage()); }
redirectTo('discussion.php?id=' . $topicId);
