<?php

declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
requireAuth();

$requestedUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$pageTitle = 'Direct chat';
$pageScripts = ['assets/js/chat.js'];

require SKADI_ROOT . '/includes/header.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Friends</p>
        <h1>Direct chat</h1>
        <p>Private one-to-one messages are available only while both accounts remain unblocked friends.</p>
    </div>
</section>

<section class="chat-layout" data-chat-app
    data-current-user-id="<?= (int) currentUser()['id'] ?>"
    data-requested-user-id="<?= $requestedUserId !== false && $requestedUserId !== null ? (int) $requestedUserId : '' ?>"
    data-conversations-url="<?= escape(baseUrl('api/chat-conversations.php')) ?>"
    data-messages-url="<?= escape(baseUrl('api/chat-messages.php')) ?>"
    data-send-url="<?= escape(baseUrl('api/chat-send.php')) ?>"
    data-edit-url="<?= escape(baseUrl('api/chat-edit.php')) ?>"
    data-hide-url="<?= escape(baseUrl('api/chat-hide.php')) ?>"
    data-delete-url="<?= escape(baseUrl('api/chat-delete.php')) ?>">
    <aside class="chat-sidebar" aria-label="Conversations">
        <div class="chat-sidebar-heading">
            <h2>Friends</h2>
            <span class="badge text-bg-primary" data-unread-total hidden></span>
        </div>
        <div class="chat-conversation-list" data-conversation-list>
            <p class="text-secondary">Loading conversations…</p>
        </div>
    </aside>

    <div class="chat-panel">
        <header class="chat-panel-heading">
            <div>
                <h2 data-chat-title>Choose a friend</h2>
                <p data-chat-subtitle>Select a current friend to start or continue a conversation.</p>
            </div>
        </header>
        <div class="chat-status" data-chat-status role="status" aria-live="polite"></div>
        <div class="chat-messages" data-message-list aria-live="polite" aria-label="Messages">
            <p class="chat-empty">No conversation selected.</p>
        </div>
        <form class="chat-composer" data-chat-form enctype="multipart/form-data" hidden>
            <label for="chat-body">Message</label>
            <textarea class="form-control" id="chat-body" name="body" rows="3" maxlength="2000"
                placeholder="Write a message…"></textarea>
            <div class="chat-composer-row">
                <div>
                    <label class="form-label" for="chat-attachment">One optional attachment</label>
                    <input class="form-control" id="chat-attachment" name="attachment" type="file"
                        accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,image/jpeg,image/png,image/webp,application/pdf,text/plain">
                    <small>JPG, PNG, WebP, PDF or TXT; maximum 5 MB.</small>
                </div>
                <button class="btn btn-primary" type="submit">Send</button>
            </div>
        </form>
        <noscript><p class="alert alert-warning">Direct chat requires JavaScript for polling and message actions.</p></noscript>
    </div>
</section>
<?php require SKADI_ROOT . '/includes/footer.php'; ?>
