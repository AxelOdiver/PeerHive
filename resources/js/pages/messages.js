$(document).ready(function () {
  const $conversationsList = $('#conversationsList');
  const $chatMessages = $('#chatMessages');
  const $chatHeader = $('#chatHeader');
  const $chatFooter = $('#chatFooter');
  const $messageForm = $('#messageForm');
  const $activeConversationId = $('#activeConversationId');
  const $messageInput = $('#messageInput');
  const $attachmentInput = $('#attachmentInput');
  const $attachmentPreview = $('#attachmentPreview');

  let chatPollTimer = null;
  let currentConversationId = null;
  let currentIsGroup = false;
  let selectedFile = null;
  let oldestLoadedId = null;
  let hasMoreMessages = false;
  let isLoadingOlder = false;
  const outgoing = [];
  const hiddenMessageIds = new Set();
  let searchTerm = '';
  let searchTimer = null;
  let viewVersion = 0;
  let searchVersion = 0;
  let searchCursor = null;
  let viewingHistory = false;
  let hasNewerMessages = false;
  let loadingNewer = false;
  let navigatingHistory = false;
  let activeConversation = null;
  let infoVersion = 0;
  let activityPending = false;
  let activityMembers = [];
  let activityReceivedAt = 0;
  let typingTimer = null;
  let typingSentAt = 0;
  let typingConversation = null;
  let readTimer = null;
  let readPending = false;
  const acknowledgedReads = new Map();
  let messageRequestPending = false;

  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
  const requestError = xhr => window.toast?.('error', xhr.responseJSON?.message || 'Something went wrong. Please try again.');
  function reactionsHtml(reactions = []) {
    return reactions.map(r => `<button type="button" class="btn btn-sm ${r.is_mine ? 'btn-light' : 'btn-outline-secondary'} reaction-choice" data-emoji="${escapeHtml(r.emoji)}" aria-pressed="${r.is_mine}" title="Toggle ${escapeHtml(r.emoji)} reaction">${escapeHtml(r.emoji)} ${r.count}</button>`).join('');
  }

  function scrollToBottom() {
    $chatMessages.scrollTop($chatMessages[0].scrollHeight);
  }

  function renderAttachment(m) {
    if (!m.attachment_url) return '';
    const isImage = m.attachment_type && m.attachment_type.startsWith('image/');
    if (isImage) {
      return `<a href="${escapeHtml(m.attachment_url)}" target="_blank"><img src="${escapeHtml(m.attachment_url)}" class="img-fluid rounded-3 mt-1" style="max-width:220px;"></a>`;
    }
    return `<a href="${escapeHtml(m.attachment_url)}" target="_blank" class="d-flex align-items-center gap-2 mt-1 text-decoration-none ${m.is_mine ? 'text-white' : 'text-body'}">
      <i class="bi bi-file-earmark-arrow-down fs-5"></i> <span>${escapeHtml(m.attachment_name)}</span>
    </a>`;
  }

  function messageHtml(m, isGroup) {
    if (hiddenMessageIds.has(Number(m.id))) return '';
    
        if (m.is_unsent) {
        return `
            <div class="message-row d-flex ${m.is_mine ? 'justify-content-end' : 'justify-content-start'} align-items-center mb-2"
                 data-message-id="${m.id}">
                <div class="deleted-message-bubble">
                    This message was unsent
                </div>
            </div>
        `;
    }

    const alignClass = m.is_mine
        ? 'bg-primary text-white'
        : 'bg-body-tertiary';

    const senderLabel = (isGroup && !m.is_mine)
        ? `<div class="small fw-semibold mb-1">${escapeHtml(m.sender_name)}</div>`
        : '';

    const bodyHtml = m.body ? $('<div>').text(m.body).html() : '';
    const editedTag = m.is_edited ? '<span class="opacity-75 edited-tag" style="font-size:0.7rem;"> (edited)</span>' : '';

    const safeDataBody = escapeHtml(m.body || (m.attachment_name ? 'Attachment' : ''));
    const replyPreviewHtml = m.reply_to ? `
      <div class="mb-2 p-2 rounded bg-white bg-opacity-25 border-start border-3 border-${m.is_mine ? 'light' : 'primary'} shadow-sm">
        <div class="d-flex align-items-center gap-1 fw-bold mb-1" style="font-size: 0.75rem;">
          <i class="bi bi-reply-fill"></i> Replying to ${escapeHtml(m.reply_to.sender_name)}
        </div>
        <div class="text-truncate opacity-100 fst-italic" style="font-size: 0.75rem; max-width: 250px;">
          "${escapeHtml(m.reply_to.body)}"
        </div>
      </div>
    ` : '';

    const kebabHtml = `
      <div class="dropdown">
        <button type="button" class="toolbar-btn" data-bs-toggle="dropdown" aria-expanded="false" title="More">
          <i class="bi bi-three-dots-vertical" style="font-size:0.85rem;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          ${m.is_mine ? `<li><button type="button" class="dropdown-item edit-message-btn" data-message-id="${m.id}"><i class="bi bi-pencil-fill me-2"></i>Edit</button></li>
          <li><button type="button" class="dropdown-item text-danger unsend-message-btn" data-message-id="${m.id}"><i class="bi bi-trash-fill me-2"></i>Unsend for everyone</button></li>` : ''}
          <li><button type="button" class="dropdown-item text-danger delete-message-for-me" data-message-id="${m.id}"><i class="bi bi-trash me-2"></i>Delete for me</button></li>
        </ul>
      </div>
    `;

  const rowAlign = m.is_mine ? 'justify-content-end' : 'justify-content-start';

  return `
    <div class="message-row d-flex ${rowAlign} align-items-center mb-2" data-message-id="${m.id}" data-client-message-id="${escapeHtml(m.client_message_id || '')}" data-is-mine="${m.is_mine}" data-sender="${escapeHtml(m.sender_name)}" data-body="${safeDataBody}">

      ${m.is_mine ? `
        <div class="message-hover-toolbar me-2">
          ${toolbarButtonsHtml(kebabHtml)}
        </div>
      ` : ''}

      <div class="p-2 rounded-3 ${alignClass} message-bubble" style="max-width:70%; width:fit-content;">
        ${senderLabel}
        ${replyPreviewHtml} 
        <div class="message-body">${bodyHtml}</div>
        ${renderAttachment(m)}
        <div class="message-reactions d-flex flex-wrap gap-1 mt-1">${reactionsHtml(m.reactions)}</div>
        <small class="d-block opacity-75 mt-1" style="font-size:0.7rem;">
          ${m.created_at}${editedTag}
        </small>
        ${m.is_mine ? '<div class="message-read-receipt small mt-1" aria-label="Read receipt"></div>' : ''}
      </div>

      ${!m.is_mine ? `
        <div class="message-hover-toolbar ms-2">
          ${toolbarButtonsHtml(kebabHtml)}
        </div>
      ` : ''}
    </div>
  `;
  }

  function toolbarButtonsHtml(kebabHtml) {
    return `
      <button type="button" class="toolbar-btn reply-message-btn" title="Reply"><i class="bi bi-reply-fill" style="font-size:0.8rem;"></i></button>
      <div class="dropdown">
        <button type="button" class="toolbar-btn" data-bs-toggle="dropdown" aria-expanded="false" title="React" aria-label="React to message"><i class="bi bi-emoji-smile-fill"></i></button>
        <div class="dropdown-menu reaction-picker shadow rounded-pill p-1" role="group" aria-label="Choose a reaction">
          <div class="d-flex">${['👍', '❤️', '😂', '🎉', '😮', '😢'].map(emoji => `<button type="button" class="btn btn-sm reaction-choice rounded-circle" data-emoji="${emoji}" aria-label="React with ${emoji}">${emoji}</button>`).join('')}</div>
        </div>
      </div>
      ${kebabHtml}
    `;
  }

  function loadConversation(conversationId, aroundId = null, refresh = false) {
    if (refresh && messageRequestPending) return;
    messageRequestPending = true;
    const version = viewVersion;
    navigatingHistory = Boolean(aroundId);
    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'GET',
      data: aroundId ? { around_id: aroundId } : {},
      success: function (response) {
        if (version !== viewVersion || String(conversationId) !== String(currentConversationId)) return;
        if (!aroundId && $chatMessages.find('.edit-message-input, .dropdown-menu.show').length) return;
        currentIsGroup = response.is_group;
        const wasAtBottom = atBottom();
        if (!refresh) $chatMessages.empty();
        else $chatMessages.children('p').remove();
        response.messages.forEach(function (m) {
          const $existing = $chatMessages.find(`[data-message-id="${m.id}"]`);
          if ($existing.length) $existing.replaceWith(messageHtml(m, response.is_group));
          else $chatMessages.append(messageHtml(m, response.is_group));
        });
        if (!response.messages.length) $chatMessages.html('<p class="text-muted text-center p-3">' + (searchTerm ? 'No matching messages.' : 'No messages yet. Say hello!') + '</p>');
        hasNewerMessages = response.has_newer;
        if (!refresh) {
          oldestLoadedId = response.messages.length ? response.messages[0].id : null;
          hasMoreMessages = response.has_more;
        }
        if (aroundId) {
          const target = $chatMessages.find(`[data-message-id="${aroundId}"]`)[0];
          if (target) {
            $(target).addClass('message-search-highlight').attr('tabindex', '-1');
            target.focus({ preventScroll: true });
            const panel = $chatMessages[0];
            panel.scrollTop += target.getBoundingClientRect().top - panel.getBoundingClientRect().top - (panel.clientHeight - target.offsetHeight) / 2;
          }
        } else if (!refresh || wasAtBottom) scrollToBottom();
        requestAnimationFrame(() => { if (version === viewVersion) navigatingHistory = false; });
        renderActivity();
        scheduleRead();
        refreshConversationsList();
      },
      error: requestError,
      complete: () => { messageRequestPending = false; }
    });
  }

  function loadOlderMessages(conversationId) {
    if (isLoadingOlder || !hasMoreMessages || !oldestLoadedId) return;
    isLoadingOlder = true;
    const version = viewVersion;

    const prevScrollHeight = $chatMessages[0].scrollHeight;

    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'GET',
      data: { before_id: oldestLoadedId },
      success: function (response) {
        if (version !== viewVersion || String(conversationId) !== String(currentConversationId)) return;
        if (response.messages.length) {
          const html = response.messages.map(m => messageHtml(m, response.is_group)).join('');
          $chatMessages.prepend(html);
          oldestLoadedId = response.messages[0].id;
          $chatMessages.scrollTop($chatMessages[0].scrollHeight - prevScrollHeight);
        }
        hasMoreMessages = response.has_more;
        isLoadingOlder = false;
        renderActivity(); scheduleRead();
      },
      error: function () {
        isLoadingOlder = false;
      }
    });
  }

  $chatMessages.on('scroll', function () {
    scheduleRead();
    if (navigatingHistory) return;
    if (viewingHistory && $chatMessages.scrollTop() + $chatMessages.innerHeight() >= $chatMessages[0].scrollHeight - 50) loadNewerMessages();
    if ($chatMessages.scrollTop() < 50) {
      loadOlderMessages(currentConversationId);
    }
  });

  function renderChatHeader(conv) {
    const buttons = '<button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" id="chatInfoBtn" aria-label="Conversation information" title="Conversation information"><i class="bi bi-info-circle-fill"></i></button>';
    $chatHeader.html(`
      <div class="d-flex justify-content-between align-items-center w-100">
        <div><span class="fw-semibold">${escapeHtml(conv.name)}</span><small id="chatPresenceStatus" class="d-block text-muted"></small></div>
        <div class="d-flex flex-wrap gap-1 justify-content-end">${buttons}</div>
      </div>
    `);
  }

  function openConversation(conv) {
    stopTyping();
    activityMembers = [];
    $('#typingIndicator').hide();
    viewVersion++;
    searchTerm = '';
    clearTimeout(searchTimer);
    closeDetails();
    viewingHistory = false; hasNewerMessages = false; loadingNewer = false;
    $('#backToLatestBtn').hide();
    $('#cancelReplyBtn').trigger('click');
    $messageInput.val('');
    selectedFile = null;
    $attachmentInput.val('');
    $attachmentPreview.hide().empty();
    oldestLoadedId = null;
    hasMoreMessages = false;
    isLoadingOlder = false;
    $chatMessages.empty();
    currentConversationId = conv.id;
    activeConversation = conv;
    $activeConversationId.val(conv.id);
    renderChatHeader(conv);
    $chatFooter.show();
    renderOutgoing();

    $('.conversation-item').removeClass('bg-body-tertiary');
    $(`.conversation-item[data-conversation-id="${conv.id}"]`).addClass('bg-body-tertiary');

    loadConversation(conv.id);
    refreshActivity();

    if (chatPollTimer) clearInterval(chatPollTimer);
    chatPollTimer = setInterval(function () {
      if (document.hidden) return;
      refreshActivity();
      scheduleRead();
      if (viewingHistory || $chatMessages.find('.edit-message-input, .dropdown-menu.show').length) return;
      if ($chatMessages.scrollTop() + $chatMessages.innerHeight() >= $chatMessages[0].scrollHeight - 100) {
        loadConversation(conv.id, null, true);
      } else {
        refreshConversationsList();
      }
    }, 4000);
  }

  function renderConversations(conversations) {
    if ($conversationsList.find('.dropdown-menu.show').length) return;
    $conversationsList.empty();

    if (!conversations.length) {
      $conversationsList.append('<p class="text-muted text-center p-4">No conversations yet.</p>');
      return;
    }

    conversations.forEach(function (c) {
      const isSelected = currentConversationId && String(currentConversationId) === String(c.id);
      const nameClass = c.unread_count > 0 ? 'fw-bold' : 'fw-semibold';
      const badge = c.unread_count > 0 ? `<span class="badge bg-danger rounded-pill ms-2">${c.unread_count}</span>` : '';
      const lastMsg = c.last_message ? c.last_message.slice(0, 30) : 'No messages yet';
      const groupIcon = c.is_group ? '<i class="bi bi-people-fill me-1"></i>' : '';

      let avatarHtml = '';
      if (c.profile_picture) {
        avatarHtml = `<img src="/storage/${c.profile_picture}" alt="${escapeHtml(c.name)}" class="rounded-circle shadow-sm flex-shrink-0" style="width:40px; height:40px; object-fit:cover;">`;
      } else {
        avatarHtml = `<div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white shadow-sm flex-shrink-0" style="width:40px; height:40px;">
                        ${escapeHtml(c.initials || '?')}
                      </div>`;
      }

      $conversationsList.append(`
        <div class="conversation-list-row d-flex align-items-center border-bottom">
        <a href="#" class="d-flex flex-grow-1 min-w-0 align-items-center gap-2 p-3 text-decoration-none text-body conversation-item ${isSelected ? 'bg-body-tertiary' : ''}"
           data-conversation-id="${c.id}" data-is-group="${c.is_group}" data-name="${escapeHtml(c.name)}">
          <span class="chat-avatar-wrap flex-shrink-0">${avatarHtml}${c.presence?.is_online ? '<span class="online-dot" role="img" aria-label="Online"></span>' : ''}</span>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-center">
              <span class="${nameClass} text-truncate">${groupIcon}${escapeHtml(c.name)}${c.is_muted ? ' <i class="bi bi-bell-slash" title="Muted"></i>' : ''}</span>
              ${badge}
            </div>
            <small class="text-muted text-truncate d-block">${escapeHtml(lastMsg)}</small>
          </div>
        </a>
        <div class="dropdown pe-2">
          <button type="button" class="btn btn-sm rounded-circle conversation-options" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-label="Options for ${escapeHtml(c.name)}"><i class="bi bi-three-dots"></i></button>
          <ul class="dropdown-menu dropdown-menu-end shadow rounded-4">
            <li><button type="button" class="dropdown-item mute-group-btn" data-conversation-id="${c.id}" data-muted="${c.is_muted}"><i class="bi bi-bell-slash me-2"></i>${c.is_muted ? 'Unmute notifications' : 'Mute notifications'}</button></li>
            ${c.is_group ? `<li><button type="button" class="dropdown-item text-danger leave-group-btn" data-conversation-id="${c.id}"><i class="bi bi-box-arrow-right me-2"></i>Leave group</button></li>` : ''}
            <li><button type="button" class="dropdown-item text-danger delete-chat-btn" data-conversation-id="${c.id}" data-name="${escapeHtml(c.name)}"><i class="bi bi-trash me-2"></i>Delete for me</button></li>
          </ul>
        </div>
        </div>
      `);
    });
  }

  function refreshConversationsList() {
    $.ajax({
      url: '/messages/conversations',
      method: 'GET',
      success: function (response) {
        renderConversations(response.conversations);
        const active = response.conversations.find(c => String(c.id) === String(currentConversationId));
        if (active && !active.is_group) $('#chatPresenceStatus').text(presenceLabel(active.presence));
      }
    });
  }

  function refreshUnreadBadge() {
    if (window.refreshSidebarMessagesBadge) window.refreshSidebarMessagesBadge();
  }

  $conversationsList.on('click', '.conversation-item', function (e) {
    e.preventDefault();
    openConversation({
      id: $(this).data('conversation-id'),
      is_group: $(this).data('is-group') === true || $(this).data('is-group') === 'true',
      name: $(this).data('name'),
    });
  });

  $attachmentInput.on('change', function () {
    selectedFile = this.files[0] || null;
    if (selectedFile) {
      $attachmentPreview.show().html(`<i class="bi bi-paperclip me-1"></i>${escapeHtml(selectedFile.name)} <a href="#" id="clearAttachmentBtn" class="ms-2 text-danger">Remove</a>`);
    } else {
      $attachmentPreview.hide().empty();
    }
  });

  $(document).on('click', '#clearAttachmentBtn', function (e) {
    e.preventDefault();
    selectedFile = null;
    $attachmentInput.val('');
    $attachmentPreview.hide().empty();
  });

  $messageInput.on('keydown', function (event) {
    if (event.key === 'Enter' && (event.originalEvent?.repeat || event.originalEvent?.isComposing)) event.preventDefault();
  });
  function newSendId() {
    if (globalThis.crypto.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    return hex.slice(0,8)+'-'+hex.slice(8,12)+'-'+hex.slice(12,16)+'-'+hex.slice(16,20)+'-'+hex.slice(20);
  }
  function renderOutgoing() {
    $chatMessages.find('.pending-message-row').remove();
    if (viewingHistory) return;
    const pending = outgoing.filter(item => String(item.conversationId) === String(currentConversationId)
      && !$chatMessages.find('[data-client-message-id="' + item.id + '"]').length);
    if (pending.length) $chatMessages.children('p').remove();
    pending.forEach(item => {
      const status = item.failed
        ? '<button type="button" class="btn btn-link text-danger p-0 small retry-outgoing" data-id="' + item.id + '"><i class="bi bi-exclamation-circle me-1"></i>Not sent · Retry</button>'
        : '<span class="text-body-secondary" role="status" aria-label="Sending">Sending…</span>';
      $chatMessages.append('<div class="pending-message-row d-flex flex-column align-items-end mb-2" data-pending-id="' + item.id + '"><div class="p-2 rounded-3 bg-primary text-white message-bubble" style="max-width:70%;width:fit-content">'
        + (item.replyText ? '<div class="small border-start border-3 ps-2 mb-2 opacity-75">' + escapeHtml(item.replyText) + '</div>' : '')
        + '<div class="message-body">' + escapeHtml(item.body) + '</div>'
        + (item.file ? '<div class="small mt-1"><i class="bi bi-paperclip"></i> ' + escapeHtml(item.file.name) + '</div>' : '')
        + '</div><div class="mt-1" style="font-size:0.7rem">' + status + '</div></div>');
    });
  }
  function sendNext(conversationId) {
    const item = outgoing.find(item => String(item.conversationId) === String(conversationId));
    if (!item || item.sending || item.failed) return;
    item.sending = true; renderOutgoing();
    const formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('client_message_id', item.id);
    if (item.body) formData.append('body', item.body);
    if (item.file) formData.append('attachment', item.file);
    if (item.replyToId) formData.append('reply_to_id', item.replyToId);
    $.ajax({
      url: '/messages/conversations/' + conversationId, method: 'POST', data: formData,
      contentType: false, processData: false,
      success: response => {
        outgoing.splice(outgoing.indexOf(item), 1);
        $chatMessages.find('[data-pending-id="' + item.id + '"]').remove();
        if (String(conversationId) === String(currentConversationId)) {
          if (viewingHistory) $('#backToLatestBtn').trigger('click');
          else {
            $chatMessages.children('p').remove();
            const $existing = $chatMessages.find('[data-message-id="' + response.message.id + '"]');
            if ($existing.length) $existing.replaceWith(messageHtml(response.message, currentIsGroup));
            else $chatMessages.append(messageHtml(response.message, currentIsGroup));
            renderOutgoing(); scrollToBottom();
          }
          renderActivity(); scheduleRead();
        }
        refreshConversationsList();
      },
      error: xhr => {
        item.failed = true;
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Message could not be confirmed. Use Retry beside it to try again.');
      },
      complete: () => { item.sending = false; renderOutgoing(); sendNext(conversationId); }
    });
  }
  $(document).on('click', '.retry-outgoing', function () {
    const item = outgoing.find(item => item.id === $(this).attr('data-id'));
    if (!item || item.sending) return;
    item.failed = false; sendNext(item.conversationId);
  });
  $messageForm.on('submit', function (event) {
    event.preventDefault();
    const conversationId = $activeConversationId.val(), body = $messageInput.val().trim();
    if (!conversationId || (!body && !selectedFile)) return;
    if (selectedFile && selectedFile.size > 10240 * 1024) { window.toast?.('error', 'Attachments must be 10 MB or smaller.'); return; }
    const item = { id: newSendId(), conversationId, body, file: selectedFile, replyToId: $('#replyToId').val(), replyText: $('#replyToId').val() ? $('#replyPreviewBody').text() : '' };
    // Consume this draft synchronously: extra Enter presses see an empty composer.
    $messageInput.val(''); selectedFile = null; $attachmentInput.val(''); $attachmentPreview.hide().empty();
    $('#replyToId').val(''); $('#replyPreview').hide();
    stopTyping(); outgoing.push(item);
    if (viewingHistory) $('#backToLatestBtn').trigger('click');
    renderOutgoing(); scrollToBottom(); sendNext(conversationId);
    $messageInput.trigger('focus');
  });

  $(document).on('click', '.start-chat-btn', function () {
    $(this).blur();
    const userId = $(this).data('user-id');
    const name = $(this).data('name');

    $.ajax({
      url: '/messages/conversations',
      method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        user_id: userId,
      },
      success: function (response) {
        window.hideModal('newChatModal');
        refreshConversationsList();
        setTimeout(function () {
          openConversation({ id: response.conversation_id, is_group: false, name: name });
        }, 300);
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to start chat.');
      }
    });
  });

  $('#createGroupBtn').on('click', function () {
    const name = $('#groupNameInput').val().trim();
    const memberIds = $('.group-member-checkbox:checked').map(function () { return $(this).val(); }).get();

    if (memberIds.length < 2) {
      if (window.toast) window.toast('error', 'Select at least 2 members.');
      return;
    }

    $.ajax({
      url: '/messages/conversations',
      method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        is_group: 1,
        name: name,
        member_ids: memberIds,
      },
      success: function (response) {
        window.hideModal('newGroupModal');
        $('#groupNameInput').val('');
        $('.group-member-checkbox').prop('checked', false);
        refreshConversationsList();
        if (window.toast) window.toast('success', 'Group created!');
        setTimeout(function () {
          openConversation({ id: response.conversation_id, is_group: true, name: name || 'Group' });
        }, 300);
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to create group.');
      }
    });
  });

  $(document).on('click', '#viewMembersBtn', function () {
    const conversationId = $(this).data('conversation-id');
    $('#addMemberBtn').data('conversation-id', conversationId);

    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'GET',
      success: function (response) {
        let html = '';
        response.members.forEach(function (m) {
          html += `
            <div class="d-flex justify-content-between align-items-center py-1">
              <span>${escapeHtml(m.name)}</span>
              <button type="button" class="btn btn-sm btn-outline-danger remove-member-btn" data-user-id="${m.id}" data-conversation-id="${conversationId}">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          `;
        });
        $('#groupMembersModalList').html(html);
        window.showModal('groupMembersModal');
      }
    });
  });

  $(document).on('click', '.remove-member-btn', function () {
    const userId = $(this).data('user-id');
    const conversationId = $(this).data('conversation-id');

    $.ajax({
      url: `/messages/conversations/${conversationId}/members/${userId}`,
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      success: function () {
        $(`.remove-member-btn[data-user-id="${userId}"]`).closest('div').remove();
        if (window.toast) window.toast('success', 'Member removed.');
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to remove member.');
      }
    });
  });

  $('#addMemberBtn').on('click', function () {
    const conversationId = $(this).data('conversation-id');
    const userId = $('#addMemberSelect').val();

    if (!userId) return;

    $.ajax({
      url: `/messages/conversations/${conversationId}/members`,
      method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        user_id: userId,
      },
      success: function () {
        $('#addMemberSelect').val('');
        if (window.toast) window.toast('success', 'Member added.');
        $(`#viewMembersBtn[data-conversation-id="${conversationId}"]`).trigger('click');
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to add member.');
      }
    });
  });

  $chatMessages.on('click', '.reply-message-btn', function () {
    const $row = $(this).closest('.message-row');
    const messageId = $row.data('message-id');
    const senderName = $row.data('sender') || 'User';
    const bodyText = $row.data('body') || 'Attachment';

    $('#replyToId').val(messageId);
    $('#replyPreviewName').text('Replying to ' + senderName);
    $('#replyPreviewBody').text(bodyText);
    $('#replyPreview').slideDown(150);
    $('#messageInput').trigger('focus');
  });

  $('#cancelReplyBtn').on('click', function () {
    $('#replyToId').val('');
    $('#replyPreview').slideUp(150);
  });

  $chatMessages.on('click', '.reaction-choice', function () {
    const $row = $(this).closest('.message-row');
    $row.find('.reaction-choice').prop('disabled', true);
    $.ajax({
      url: `/messages/${$row.data('message-id')}/reactions`, method: 'POST',
      data: { emoji: $(this).data('emoji'), _token: $('meta[name="csrf-token"]').attr('content') },
      success: response => { $row.find('.message-reactions').html(reactionsHtml(response.reactions)); },
      error: requestError,
      complete: () => $row.find('.reaction-choice').prop('disabled', false)
    });
  });

  // Edit message — now updates the bubble directly, no full reload
  $chatMessages.on('click', '.edit-message-btn', function () {
    const messageId = $(this).data('message-id');
    const $bubble = $(this).closest('.message-row');
    const $bodyDiv = $bubble.find('.message-body');
    const currentText = $bodyDiv.text().trim();
    const safeText = escapeHtml(currentText);

    $bodyDiv.addClass('is-editing').html(`
      <div class="message-edit-controls d-flex gap-1 align-items-center">
        <input type="text" class="form-control form-control-sm edit-message-input" value="${safeText}">
        <button type="button" class="btn btn-sm btn-light save-edit-btn" data-message-id="${messageId}"><i class="bi bi-check-lg"></i></button>
        <button type="button" class="btn btn-sm btn-light cancel-edit-btn" data-original-text="${safeText}"><i class="bi bi-x-lg"></i></button>
      </div>
    `);
    $bodyDiv.find('.edit-message-input').trigger('focus');
  });

  $chatMessages.on('click', '.cancel-edit-btn', function () {
    const $bodyDiv = $(this).closest('.message-body');
    const originalText = $(this).data('original-text');
    $bodyDiv.removeClass('is-editing').text(originalText);
  });

  $chatMessages.on('keypress', '.edit-message-input', function (e) {
    if (e.which === 13) {
      e.preventDefault();
      $(this).closest('.message-body').find('.save-edit-btn').trigger('click');
    }
  });

  $chatMessages.on('click', '.save-edit-btn', function () {
    const messageId = $(this).data('message-id');
    const $bodyDiv = $(this).closest('.message-body');
    const newBody = $bodyDiv.find('.edit-message-input').val().trim();

    if (!newBody) return;

    $.ajax({
      url: `/messages/${messageId}`,
      method: 'PUT',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        body: newBody,
      },
      success: function () {
        $bodyDiv.removeClass('is-editing').text(newBody);
        const $row = $bodyDiv.closest('.message-row');
        $row.attr('data-body', newBody).data('body', newBody);
        if (!$row.find('.edited-tag').length) $row.find('small').append('<span class="edited-tag"> (edited)</span>');
        refreshConversationsList();
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to edit message.');
      }
    });
  });

  $chatMessages.on('click', '.delete-message-for-me', async function () {
    const id = Number($(this).data('message-id'));
    const conversationId = currentConversationId;
    const result = window.confirmAction
      ? await window.confirmAction('This message will disappear only for you. Other people will still see it.', 'Delete for me')
      : { isConfirmed: confirm('Delete this message only for you?') };
    if (!result.isConfirmed) return;
    $.ajax({
      url: '/messages/' + id + '/for-me', method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      success: () => {
        hiddenMessageIds.add(id);
        $chatMessages.find('[data-message-id="' + id + '"]').remove();
        if (String(conversationId) === String(currentConversationId)) loadConversation(conversationId);
        refreshConversationsList(); refreshUnreadBadge();
      }, error: requestError
    });
  });

  // Unsend message
  $chatMessages.on('click', '.unsend-message-btn', async function () {
    const messageId = $(this).data('message-id');
    const $bubble = $(this).closest('.message-row');

    const result = window.confirmAction
      ? await window.confirmAction('This message will be unsent for everyone.', 'Unsend Message')
      : { isConfirmed: confirm('Unsend this message?') };

    if (!result.isConfirmed) return;

    $.ajax({
      url: `/messages/${messageId}`,
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      success: function () {
          const $row = $bubble.closest('.message-row');

          $row.replaceWith(`
              <div class="message-row d-flex justify-content-end align-items-center mb-2"
                  data-message-id="${messageId}">
                  <div class="deleted-message-bubble">
                      You deleted a message
                  </div>
              </div>
          `);

          if (String($('#replyToId').val()) === String(messageId)) $('#cancelReplyBtn').trigger('click');
          refreshConversationsList();
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to unsend message.');
      }
    });
  });

  // --- Delete Conversation ---
  $(document).on('click', '.delete-chat-btn', async function (e) {
    e.preventDefault();
    const conversationId = $(this).data('conversation-id');
    const name = $(this).data('name');

    const result = window.confirmAction
      ? await window.confirmAction(`Delete your chat history with ${name} for you? Other members keep their messages. New messages will bring the chat back.`, 'Delete for me?')
      : { isConfirmed: confirm(`Are you sure you want to delete your chat with ${name}?`) };

    if (!result.isConfirmed) return;

    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      success: function () {
        refreshConversationsList(); refreshUnreadBadge();
        
        if (String(currentConversationId) === String(conversationId)) {
          stopTyping(); activityMembers = []; $('#typingIndicator').hide();
          viewVersion++; closeDetails(); activeConversation = null; $('#backToLatestBtn').hide();
          $chatMessages.empty();
          $chatHeader.empty();$chatFooter.hide();
          currentConversationId = null;
          $activeConversationId.val('');
          
          if (typeof chatPollTimer !== 'undefined') {
             clearInterval(chatPollTimer);
          }
        }
        
        if (window.toast) window.toast('success', 'Conversation deleted for you.');
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to delete conversation.');
      }
    });
  });

  // --- Search Filter for Single Chat Modal ---
  $('#newChatSearchInput').on('input', function () {
    const searchTerm = $(this).val().trim().toLowerCase();

    $('#newChatUserList .start-chat-btn').each(function () {
      const userName = String($(this).data('name')).toLowerCase();
      
      if (userName.includes(searchTerm)) {
        $(this).removeClass('d-none').addClass('d-flex');
      } else {
        $(this).removeClass('d-flex').addClass('d-none');
      }
    });
  });

  $('#newChatSearchInput').on('input', function () {
    $('#newChatNoResults').toggleClass('d-none', $('#newChatUserList .start-chat-btn').not('.d-none').length > 0);
  });
  $('#newChatModal').on('shown.bs.modal', () => $('#newChatSearchInput').trigger('input').trigger('focus'));

  // --- Search Filter for Group Chat Modal ---
  $('#newGroupSearchInput').on('input', function () {
    const searchTerm = $(this).val().toLowerCase();

    $('#groupMembersList .group-member-item').each(function () {
      const userName = $(this).data('name').toLowerCase();
      
      if (userName.includes(searchTerm)) {
        $(this).removeClass('d-none').addClass('d-flex');
      } else {
        $(this).removeClass('d-flex').addClass('d-none');
      }
    });
  });

  // Clear the search bars when the modals are closed
  $('#newChatModal, #newGroupModal').on('hidden.bs.modal', function () {
    $('#newChatSearchInput, #newGroupSearchInput').val('').trigger('input');
  });


  function closeSearch() {
    clearTimeout(searchTimer); searchVersion++; searchTerm = ''; searchCursor = null;
    $('#chatSearchInput').val(''); $('#chatSearchBar').hide();
    $('#chatSearchResults').empty(); $('#chatSearchCount').text(''); $('#moreSearchResultsBtn').hide();
  }
  function searchMessages(append = false) {
    const id = currentConversationId, version = searchVersion, term = searchTerm;
    if (!term || !id) return;
    $('#moreSearchResultsBtn').prop('disabled', true);
    $.ajax({
      url: `/messages/conversations/${id}`, data: { q: term, before_id: append ? searchCursor : undefined },
      success: response => {
        if (version !== searchVersion || String(id) !== String(currentConversationId)) return;
        const $results = $('#chatSearchResults');
        if (!append) $results.empty();
        $('#chatSearchCount').text(`${response.total_results} ${response.total_results === 1 ? 'result' : 'results'}`);
        response.messages.slice().reverse().forEach(m => {
          $results.append(`<button type="button" class="chat-search-result btn w-100 text-start d-flex gap-2 p-3 rounded-0" data-message-id="${m.id}"><span class="search-avatar bg-secondary-subtle rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"><i class="bi bi-person-fill"></i></span><span class="min-w-0"><strong class="d-block">${escapeHtml(m.sender_name)}</strong><span class="d-block text-truncate">${escapeHtml(m.body || m.attachment_name || 'Attachment')}</span><small class="text-muted">${escapeHtml(m.created_at)}</small></span></button>`);
        });
        if (!response.total_results) $results.html('<p class="text-muted p-3">No matching messages.</p>');
        searchCursor = response.messages[0]?.id;
        $('#moreSearchResultsBtn').toggle(response.has_more).prop('disabled', false);
      }, error: xhr => { if (version === searchVersion) { $('#chatSearchCount').text('Search failed. Try again.'); $('#moreSearchResultsBtn').prop('disabled', false); requestError(xhr); } }
    });
  }
  $(document).on('click', '#searchChatBtn', function () {
    infoVersion++; $('#chatInfoPanel').hide(); $('#chatDetailsPanel').show(); $('#chatSearchBar').css('display', 'flex');
    $('#chatSearchInput').trigger('focus');
  });
  $('#chatSearchInput').on('input', function () {
    clearTimeout(searchTimer); searchVersion++; searchTerm = $(this).val().trim(); searchCursor = null;
    $('#chatSearchResults').empty(); $('#moreSearchResultsBtn').hide();
    $('#chatSearchCount').text(searchTerm ? 'Searching…' : 'Search messages and attachments');
    searchTimer = setTimeout(() => searchMessages(), 300);
  });
  $('#moreSearchResultsBtn').on('click', () => searchMessages(true));
  $('#closeChatSearchBtn').on('click', showConversationInfo);
  $('#chatSearchResults').on('click', '.chat-search-result', function () {
    viewVersion++; isLoadingOlder = false; loadingNewer = false;
    viewingHistory = true; $('#backToLatestBtn').show();
    loadConversation(currentConversationId, $(this).data('message-id'));
  });
  $('#backToLatestBtn').on('click', function () {
    viewVersion++; viewingHistory = false; isLoadingOlder = false; loadingNewer = false;
    $(this).hide(); loadConversation(currentConversationId);
  });
  function loadNewerMessages() {
    if (!hasNewerMessages || loadingNewer) return;
    const id = currentConversationId, version = viewVersion;
    const after = $chatMessages.find('.message-row').last().data('message-id');
    if (!after) return;
    loadingNewer = true;
    $.ajax({
      url: `/messages/conversations/${id}`, data: { after_id: after },
      success: response => {
        if (version !== viewVersion || String(id) !== String(currentConversationId)) return;
        response.messages.forEach(m => $chatMessages.append(messageHtml(m, response.is_group)));
        hasNewerMessages = response.has_newer;
        renderActivity(); scheduleRead();
      }, error: requestError,
      complete: () => { if (version === viewVersion) loadingNewer = false; }
    });
  }
  function closeDetails() {
    infoVersion++; closeSearch(); $('#chatInfoPanel, #chatDetailsPanel').hide();
  }
  function showConversationInfo() {
    if (!activeConversation || !currentConversationId) return;
    closeSearch();
    const id = currentConversationId, version = ++infoVersion, conv = activeConversation;
    $('#chatDetailsPanel, #chatInfoPanel').show();
    $('#chatInfoContent').html('<p class="text-muted">Loading conversation…</p>');
    $.ajax({
      url: `/messages/conversations/${id}/info`,
      success: response => {
        if (version !== infoVersion || String(id) !== String(currentConversationId)) return;
        const members = response.members.map(m => `<a class="d-flex align-items-center gap-2 py-2 text-body text-decoration-none" href="${escapeHtml(m.profile_url)}">${m.profile_picture ? `<img class="info-member-avatar rounded-circle" src="${escapeHtml(m.profile_picture)}" alt="">` : '<i class="bi bi-person-circle fs-4"></i>'}<span>${escapeHtml(m.name)}</span></a>`).join('');
        $('#chatInfoContent').html(`
          <div class="text-center mb-4"><div class="info-conversation-avatar rounded-circle bg-primary-subtle text-primary mx-auto mb-3"><i class="bi ${response.is_group ? 'bi-people-fill' : 'bi-person-fill'}"></i></div><h5>${escapeHtml(conv.name)}</h5><p class="text-muted small">${response.is_group ? response.members.length + ' members' : 'Direct conversation'}</p></div>
          <div class="d-flex justify-content-center gap-4 mb-4">
            <button type="button" class="btn mute-group-btn" data-conversation-id="${id}" data-muted="${response.is_muted}"><i class="bi ${response.is_muted ? 'bi-bell-slash-fill' : 'bi-bell'} info-action-icon"></i>${response.is_muted ? 'Unmute' : 'Mute'}</button>
            <button type="button" class="btn" id="searchChatBtn"><i class="bi bi-search info-action-icon"></i>Search</button>
          </div>
          <details class="border-top py-3" open><summary class="fw-semibold">Chat info</summary><p class="small text-muted mt-2">${response.is_muted ? (response.muted_until ? 'Notifications muted until ' + escapeHtml(new Date(response.muted_until).toLocaleString()) + '.' : 'Notifications muted until you turn them back on.') : 'Notifications are on.'}</p></details>
          <details class="border-top py-3"><summary class="fw-semibold">${response.is_group ? 'Chat members' : 'People'}</summary>${members}${response.is_group ? `<button class="btn btn-sm btn-outline-secondary mt-2" id="viewMembersBtn" data-conversation-id="${id}">Manage members</button>` : ''}</details>
          ${response.is_group ? `<details class="border-top py-3"><summary class="fw-semibold">Conversation settings</summary><button type="button" class="btn text-danger leave-group-btn mt-2" data-conversation-id="${id}">Leave group</button></details>` : ''}
        `);
      }, error: xhr => { if (version === infoVersion) $('#chatInfoContent').text('Could not load conversation information. Please reopen the panel to retry.'); requestError(xhr); }
    });
  }
  $(document).on('click', '#chatInfoBtn', function () {
    if ($('#chatInfoPanel').is(':visible')) closeDetails(); else showConversationInfo();
  });
  $('#closeChatInfoBtn').on('click', closeDetails);
  function saveMute(id, isMuted, duration, $button, done) {
    $button.prop('disabled', true);
    $.ajax({
      url: `/messages/conversations/${id}/mute`, method: 'PUT',
      data: { is_muted: isMuted ? 1 : 0, duration, _token: $('meta[name="csrf-token"]').attr('content') },
      success: () => {
        done?.(); refreshConversationsList(); refreshUnreadBadge();
        if (String(id) === String(currentConversationId) && $('#chatInfoPanel').is(':visible')) showConversationInfo();
      }, error: requestError, complete: () => $button.prop('disabled', false)
    });
  }
  $(document).on('click', '.mute-group-btn', function () {
    const $button = $(this), id = $button.data('conversation-id');
    if ($button.data('muted')) { saveMute(id, false, 'forever', $button); return; }
    $('#muteConversationId').val(id);
    $('#muteConversationForm input[value="15"]').prop('checked', true);
    window.showModal('muteConversationModal');
  });
  $('#muteConversationForm').on('submit', function (event) {
    event.preventDefault();
    saveMute($('#muteConversationId').val(), true, $('input[name="mute_duration"]:checked').val(), $('#confirmMuteBtn'), () => window.hideModal('muteConversationModal'));
  });
  $(document).on('click', '.leave-group-btn', async function () {
    const id = $(this).data('conversation-id');
    const result = window.confirmAction ? await window.confirmAction('You will no longer receive messages from this group. A member can add you again.', 'Leave group?') : { isConfirmed: confirm('Leave this group?') };
    if (!result.isConfirmed) return;
    $.ajax({
      url: `/messages/conversations/${id}/leave`, method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      success: () => {
        if (String(id) === String(currentConversationId)) {
          stopTyping(); activityMembers = []; $('#typingIndicator').hide();
          viewVersion++; clearInterval(chatPollTimer); clearTimeout(searchTimer);
          currentConversationId = null; $activeConversationId.val('');
          hasMoreMessages = false; oldestLoadedId = null;
          $chatMessages.empty(); $chatFooter.hide(); closeDetails(); activeConversation = null; $('#backToLatestBtn').hide();
          $('#cancelReplyBtn').trigger('click');
          $chatHeader.text('Select a conversation to start chatting');
        }
        refreshConversationsList(); refreshUnreadBadge();
      }, error: requestError
    });
  });


  function atBottom() {
    return $chatMessages.scrollTop() + $chatMessages.innerHeight() >= $chatMessages[0].scrollHeight - 80;
  }
  function presenceLabel(presence) {
    if (presence?.is_online) return 'Online';
    if (!presence?.last_seen_at) return 'Offline';
    const seconds = Math.max(1, Math.floor((Date.now() - new Date(presence.last_seen_at).getTime()) / 1000));
    if (!Number.isFinite(seconds)) return 'Offline';
    const [unit, size] = seconds >= 604800 ? ['week', 604800] : seconds >= 86400 ? ['day', 86400] : seconds >= 3600 ? ['hour', 3600] : seconds >= 60 ? ['minute', 60] : ['second', 1];
    const count = Math.floor(seconds / size);
    return 'Last seen ' + count + ' ' + unit + (count === 1 ? '' : 's') + ' ago';
  }
  function renderActivity() {
    if (!currentConversationId) return;
    if (!currentIsGroup && activityMembers.length) $('#chatPresenceStatus').text(presenceLabel(activityMembers[0]));
    if (currentIsGroup) {
      const online = activityMembers.filter(m => m.is_online).length;
      $('#chatPresenceStatus').text(online ? online + ' other ' + (online === 1 ? 'member online' : 'members online') : 'Group conversation');
    }
    const typing = Date.now() - activityReceivedAt < 8000 ? activityMembers.filter(m => m.is_typing).map(m => m.name) : [];
    $('#typingIndicator').toggle(typing.length > 0);
    $('#typingNames').text(typing.length ? typing.join(', ') + (typing.length === 1 ? ' is typing' : ' are typing') : '');
    $chatMessages.find('.message-read-receipt').empty();
    const grouped = new Map();
    activityMembers.forEach(member => {
      if (!member.last_read_message_id) return;
      const key = String(member.last_read_message_id);
      grouped.set(key, [...(grouped.get(key) || []), member.name]);
    });
    grouped.forEach((names, id) => {
      $chatMessages.find(`[data-message-id="${id}"] .message-read-receipt`).text('✓ Seen by ' + names.join(', '));
    });
  }
  function refreshActivity() {
    if (!currentConversationId || document.hidden || activityPending) return;
    const id = currentConversationId;
    activityPending = true;
    $.ajax({
      url: `/messages/conversations/${id}/state`,
      success: response => {
        if (String(id) !== String(currentConversationId) || document.hidden) return;
        activityMembers = response.members; activityReceivedAt = Date.now(); renderActivity();
      },
      error: () => { if (String(id) === String(currentConversationId)) $('#typingIndicator').hide(); },
      complete: () => { activityPending = false; }
    });
  }
  function sendTyping(id, typing) {
    if (!id) return;
    $.ajax({ url: `/messages/conversations/${id}/typing`, method: 'POST',
      data: { is_typing: typing ? 1 : 0, _token: $('meta[name="csrf-token"]').attr('content') } });
  }
  function stopTyping() {
    clearTimeout(typingTimer);
    if (typingConversation) sendTyping(typingConversation, false);
    typingConversation = null; typingSentAt = 0;
  }
  $messageInput.on('input', function () {
    if (!currentConversationId || document.hidden || !document.hasFocus() || !$(this).val().trim()) { stopTyping(); return; }
    if (!typingConversation || Date.now() - typingSentAt >= 3000) {
      typingConversation = currentConversationId; typingSentAt = Date.now();
      sendTyping(currentConversationId, true);
    }
    clearTimeout(typingTimer); typingTimer = setTimeout(stopTyping, 2500);
  });
  $messageInput.on('blur', stopTyping);
  function scheduleRead() {
    clearTimeout(readTimer); readTimer = setTimeout(acknowledgeVisible, 300);
  }
  function acknowledgeVisible() {
    if (!currentConversationId || readPending || document.hidden || !document.hasFocus() || $('.modal.show').length || navigatingHistory) return;
    const panel = $chatMessages[0].getBoundingClientRect();
    const visibleTop = Math.max(panel.top, 0), visibleBottom = Math.min(panel.bottom, window.innerHeight);
    if (visibleBottom <= visibleTop) return;
    let lastVisible = 0;
    $chatMessages.find('.message-row').each(function () {
      const rect = this.getBoundingClientRect();
      if (rect.bottom > visibleTop && rect.top < visibleBottom) lastVisible = Math.max(lastVisible, Number(this.dataset.messageId));
    });
    const id = currentConversationId;
    if (!lastVisible || lastVisible <= (acknowledgedReads.get(String(id)) || 0)) return;
    readPending = true;
    $.ajax({ url: `/messages/conversations/${id}/read`, method: 'POST',
      data: { message_id: lastVisible, _token: $('meta[name="csrf-token"]').attr('content') },
      success: () => { acknowledgedReads.set(String(id), lastVisible); refreshConversationsList(); refreshUnreadBadge(); },
      complete: () => { readPending = false; }
    });
  }
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { stopTyping(); $('#typingIndicator').hide(); }
    else { refreshActivity(); scheduleRead(); refreshConversationsList(); }
  });
  window.addEventListener('blur', stopTyping);
  window.addEventListener('focus', () => { refreshActivity(); scheduleRead(); });
  window.addEventListener('pagehide', stopTyping);

  refreshConversationsList();
  setInterval(() => { if (!document.hidden) refreshConversationsList(); }, 10000);
});