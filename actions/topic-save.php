<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { renderErrorPage(405, 'Method not allowed', 'Topics must be saved from the discussion form.'); }
requireValidCsrfToken($_POST['csrf_token'] ?? null);
$topicId = communityPositiveInt($_POST['topic_id'] ?? null);
try { $savedId = saveDiscussionTopic(db(), (int) currentUser()['id'], $topicId, $_POST); addFlashMessage('success', $topicId ? 'Topic updated.' : 'Topic published.'); redirectTo('discussion.php?id=' . $savedId); }
catch (CommunityException $exception) { addFlashMessage('error', $exception->getMessage()); redirectTo('topic-edit.php' . ($topicId ? '?id=' . $topicId : '')); }
