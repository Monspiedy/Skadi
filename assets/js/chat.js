(() => {
  'use strict';

  const app = document.querySelector('[data-chat-app]');
  if (!app) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const requestedUserId = Number(app.dataset.requestedUserId) || null;
  const conversationList = app.querySelector('[data-conversation-list]');
  const messageList = app.querySelector('[data-message-list]');
  const form = app.querySelector('[data-chat-form]');
  const title = app.querySelector('[data-chat-title]');
  const subtitle = app.querySelector('[data-chat-subtitle]');
  const status = app.querySelector('[data-chat-status]');
  const unreadTotal = app.querySelector('[data-unread-total]');
  let activeUserId = null;
  let conversations = [];
  let polling = false;
  let lastRenderedMessageId = null;

  const jsonRequest = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const payload = await response.json().catch(() => ({
      ok: false,
      error: { message: 'The server returned an unreadable response.' }
    }));
    if (!response.ok || !payload.ok) {
      throw new Error(payload.error?.message || 'The request failed.');
    }
    return payload;
  };

  const setStatus = (message, isError = false) => {
    status.textContent = message;
    status.classList.toggle('text-danger', isError);
  };

  const formatTime = (databaseTime) => {
    const parsed = new Date(databaseTime.replace(' ', 'T'));
    return Number.isNaN(parsed.getTime()) ? databaseTime : parsed.toLocaleString();
  };

  const renderConversations = () => {
    conversationList.replaceChildren();
    if (conversations.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'chat-empty';
      empty.textContent = 'No available friends. Accept a friend request to use chat.';
      conversationList.append(empty);
      return;
    }

    let total = 0;
    conversations.forEach((conversation) => {
      total += conversation.unread_count;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'chat-conversation';
      button.classList.toggle('active', conversation.user_id === activeUserId);

      const name = document.createElement('strong');
      name.textContent = conversation.display_name;
      const meta = document.createElement('span');
      meta.textContent = conversation.last_message || conversation.skadi_id;
      button.append(name, meta);
      if (conversation.unread_count > 0) {
        const badge = document.createElement('span');
        badge.className = 'badge rounded-pill text-bg-primary';
        badge.textContent = String(conversation.unread_count);
        badge.setAttribute('aria-label', `${conversation.unread_count} unread messages`);
        button.append(badge);
      }
      button.addEventListener('click', () => selectConversation(conversation.user_id));
      conversationList.append(button);
    });
    unreadTotal.hidden = total === 0;
    unreadTotal.textContent = total === 0 ? '' : `${total} unread`;
  };

  const actionButton = (label, action, messageId, className = 'btn btn-sm btn-outline-secondary') => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    button.addEventListener('click', () => handleMessageAction(action, messageId));
    return button;
  };

  const renderMessages = (messages) => {
    const newestMessageId = messages.length > 0 ? messages[messages.length - 1].id : null;
    const previousScrollTop = messageList.scrollTop;
    const shouldFollowLatest = messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight < 80
      || newestMessageId !== lastRenderedMessageId;
    messageList.replaceChildren();
    if (messages.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'chat-empty';
      empty.textContent = 'No messages yet. Say hello.';
      messageList.append(empty);
      return;
    }

    const unique = new Map(messages.map((message) => [message.id, message]));
    [...unique.values()].sort((first, second) => first.id - second.id).forEach((message) => {
      const article = document.createElement('article');
      article.className = `chat-message ${message.is_own ? 'chat-message-own' : ''}`;
      article.dataset.messageId = String(message.id);

      if (message.body) {
        const body = document.createElement('p');
        body.className = 'chat-message-body';
        body.textContent = message.body;
        article.append(body);
      }
      if (message.attachment) {
        const attachment = document.createElement('a');
        attachment.className = 'chat-attachment';
        attachment.href = message.attachment.url;
        if (message.attachment.is_image) {
          const image = document.createElement('img');
          image.src = message.attachment.url;
          image.alt = message.attachment.name;
          image.loading = 'lazy';
          attachment.append(image);
        }
        const attachmentName = document.createElement('span');
        attachmentName.textContent = `${message.attachment.name} (${Math.ceil(message.attachment.size_bytes / 1024)} KB)`;
        attachment.append(attachmentName);
        article.append(attachment);
      }

      const meta = document.createElement('div');
      meta.className = 'chat-message-meta';
      const time = document.createElement('time');
      time.dateTime = message.created_at;
      time.textContent = formatTime(message.created_at);
      meta.append(time);
      if (message.edited) {
        const edited = document.createElement('span');
        edited.textContent = 'edited';
        meta.append(edited);
      }
      article.append(meta);

      const actions = document.createElement('div');
      actions.className = 'chat-message-actions';
      if (message.is_own) {
        actions.append(actionButton('Edit', 'edit', message.id));
        actions.append(actionButton('Delete for everyone', 'delete', message.id, 'btn btn-sm btn-outline-danger'));
      }
      actions.append(actionButton('Hide for me', 'hide', message.id));
      article.append(actions);
      messageList.append(article);
    });
    messageList.scrollTop = shouldFollowLatest ? messageList.scrollHeight : previousScrollTop;
    lastRenderedMessageId = newestMessageId;
  };

  const loadConversations = async () => {
    const payload = await jsonRequest(app.dataset.conversationsUrl);
    conversations = payload.conversations;
    if (activeUserId && !conversations.some((item) => item.user_id === activeUserId)) {
      activeUserId = null;
      form.hidden = true;
      title.textContent = 'Conversation unavailable';
      subtitle.textContent = 'The friendship or block state changed.';
      renderMessages([]);
    }
    renderConversations();
  };

  const loadMessages = async () => {
    if (!activeUserId) return;
    const payload = await jsonRequest(app.dataset.messagesUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ user_id: activeUserId })
    });
    title.textContent = payload.friend.display_name;
    subtitle.textContent = payload.friend.skadi_id;
    renderMessages(payload.messages);
    setStatus('');
  };

  const selectConversation = async (userId) => {
    activeUserId = userId;
    form.hidden = false;
    renderConversations();
    setStatus('Loading messages…');
    try {
      await loadMessages();
      await loadConversations();
    } catch (error) {
      setStatus(error.message, true);
      form.hidden = true;
    }
  };

  const postJson = (url, data) => jsonRequest(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(data)
  });

  const handleMessageAction = async (action, messageId) => {
    try {
      if (action === 'edit') {
        const current = messageList.querySelector(`[data-message-id="${messageId}"] .chat-message-body`)?.textContent || '';
        const body = window.prompt('Edit your message:', current);
        if (body === null || body === current) return;
        await postJson(app.dataset.editUrl, { message_id: messageId, body });
      } else if (action === 'delete') {
        if (!window.confirm('Delete this message for everyone? It will disappear completely and its attachment will be removed.')) return;
        await postJson(app.dataset.deleteUrl, { message_id: messageId });
      } else {
        if (!window.confirm('Hide this message only from your view?')) return;
        await postJson(app.dataset.hideUrl, { message_id: messageId });
      }
      await loadMessages();
      await loadConversations();
    } catch (error) {
      setStatus(error.message, true);
    }
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!activeUserId) return;
    const data = new FormData(form);
    data.set('user_id', String(activeUserId));
    data.set('csrf_token', csrfToken);
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    setStatus('Sending…');
    try {
      await jsonRequest(app.dataset.sendUrl, { method: 'POST', body: data });
      form.reset();
      await loadMessages();
      await loadConversations();
      setStatus('Message sent.');
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      submit.disabled = false;
    }
  });

  const poll = async () => {
    if (polling || document.hidden) return;
    polling = true;
    try {
      await loadConversations();
      if (activeUserId) await loadMessages();
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      polling = false;
    }
  };

  loadConversations().then(() => {
    if (requestedUserId && conversations.some((item) => item.user_id === requestedUserId)) {
      selectConversation(requestedUserId);
    }
  }).catch((error) => setStatus(error.message, true));
  window.setInterval(poll, 3000);
})();
