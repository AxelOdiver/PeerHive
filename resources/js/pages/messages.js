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
  let isSending = false;

  function scrollToBottom() {
    $chatMessages.scrollTop($chatMessages[0].scrollHeight);
  }

  function renderAttachment(m) {
    if (!m.attachment_url) return '';
    const isImage = m.attachment_type && m.attachment_type.startsWith('image/');
    if (isImage) {
      return `<a href="${m.attachment_url}" target="_blank"><img src="${m.attachment_url}" class="img-fluid rounded-3 mt-1" style="max-width:220px;"></a>`;
    }
    return `<a href="${m.attachment_url}" target="_blank" class="d-flex align-items-center gap-2 mt-1 text-decoration-none ${m.is_mine ? 'text-white' : 'text-body'}">
      <i class="bi bi-file-earmark-arrow-down fs-5"></i> <span>${m.attachment_name}</span>
    </a>`;
  }

  function messageHtml(m, isGroup) {
    
        if (m.is_unsent) {
        return `
            <div class="message-row d-flex ${m.is_mine ? 'justify-content-end' : 'justify-content-start'} align-items-center mb-2"
                 data-message-id="${m.id}">
                <div class="deleted-message-bubble">
                    You deleted a message
                </div>
            </div>
        `;
    }

    const alignClass = m.is_mine
        ? 'bg-primary text-white'
        : 'bg-body-tertiary';

    const senderLabel = (isGroup && !m.is_mine)
        ? `<div class="small fw-semibold mb-1">${m.sender_name}</div>`
        : '';

    const bodyHtml = m.body ? $('<div>').text(m.body).html() : '';
    const editedTag = m.is_edited ? '<span class="opacity-75 edited-tag" style="font-size:0.7rem;"> (edited)</span>' : '';

    const kebabHtml = m.is_mine ? `
      <div class="dropdown">
        <button type="button" class="toolbar-btn" data-bs-toggle="dropdown" aria-expanded="false" title="More">
          <i class="bi bi-three-dots-vertical" style="font-size:0.85rem;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li><button type="button" class="dropdown-item edit-message-btn" data-message-id="${m.id}"><i class="bi bi-pencil-fill me-2"></i>Edit</button></li>
          <li><button type="button" class="dropdown-item text-danger unsend-message-btn" data-message-id="${m.id}"><i class="bi bi-trash-fill me-2"></i>Unsend</button></li>
        </ul>
      </div>
    ` : '';

    const toolbarHtml = `
      <div class="message-hover-toolbar d-flex gap-1">
        <button type="button" class="toolbar-btn reply-message-btn" title="Reply"><i class="bi bi-reply-fill" style="font-size:0.8rem;"></i></button>
        <button type="button" class="toolbar-btn react-message-btn" title="React"><i class="bi bi-emoji-smile-fill" style="font-size:0.8rem;"></i></button>
        ${kebabHtml}
      </div>
    `;

  const rowAlign = m.is_mine ? 'justify-content-end' : 'justify-content-start';

  return `
    <div class="message-row d-flex ${rowAlign} align-items-center mb-2" data-message-id="${m.id}">

      ${m.is_mine ? `
        <div class="message-hover-toolbar me-2">
          ${toolbarButtonsHtml(kebabHtml)}
        </div>
      ` : ''}

      <div class="p-2 rounded-3 ${alignClass} message-bubble" style="max-width:70%; width:fit-content;">
        ${senderLabel}
        <div class="message-body">${bodyHtml}</div>
        ${renderAttachment(m)}
        <small class="d-block opacity-75 mt-1" style="font-size:0.7rem;">
          ${m.created_at}${editedTag}
        </small>
      </div>

      ${!m.is_mine ? `
        <div class="message-hover-toolbar ms-2">
          ${toolbarButtonsHtml('')}
        </div>
      ` : ''}

    </div>
  `;
  }

  function toolbarButtonsHtml(kebabHtml) {
    return `
      <button type="button" class="toolbar-btn reply-message-btn" title="Reply"><i class="bi bi-reply-fill" style="font-size:0.8rem;"></i></button>
      <button type="button" class="toolbar-btn react-message-btn" title="React"><i class="bi bi-emoji-smile-fill" style="font-size:0.8rem;"></i></button>
      ${kebabHtml}
    `;
  }

  function loadConversation(conversationId) {
    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'GET',
      success: function (response) {
        currentIsGroup = response.is_group;
        $chatMessages.empty();
        response.messages.forEach(function (m) {
          $chatMessages.append(messageHtml(m, response.is_group));
        });
        oldestLoadedId = response.messages.length ? response.messages[0].id : null;
        hasMoreMessages = response.has_more;
        scrollToBottom();
        refreshConversationsList();
        refreshUnreadBadge();
      }
    });
  }

  function loadOlderMessages(conversationId) {
    if (isLoadingOlder || !hasMoreMessages || !oldestLoadedId) return;
    isLoadingOlder = true;

    const prevScrollHeight = $chatMessages[0].scrollHeight;

    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'GET',
      data: { before_id: oldestLoadedId },
      success: function (response) {
        if (response.messages.length) {
          const html = response.messages.map(m => messageHtml(m, response.is_group)).join('');
          $chatMessages.prepend(html);
          oldestLoadedId = response.messages[0].id;
          $chatMessages.scrollTop($chatMessages[0].scrollHeight - prevScrollHeight);
        }
        hasMoreMessages = response.has_more;
        isLoadingOlder = false;
      },
      error: function () {
        isLoadingOlder = false;
      }
    });
  }

  $chatMessages.on('scroll', function () {
    if ($chatMessages.scrollTop() < 50) {
      loadOlderMessages(currentConversationId);
    }
  });

  function renderChatHeader(conv) {
    let membersBtn = '';
    if (conv.is_group) {
      membersBtn = `<button type="button" class="btn btn-sm btn-outline-secondary" id="viewMembersBtn" data-conversation-id="${conv.id}"><i class="bi bi-people-fill"></i> Members</button>`;
    }
    $chatHeader.html(`<span class="fw-semibold">${conv.name}</span>${membersBtn}`);
  }

  function openConversation(conv) {
    currentConversationId = conv.id;
    $activeConversationId.val(conv.id);
    renderChatHeader(conv);
    $chatFooter.show();

    $('.conversation-item').removeClass('bg-body-tertiary');
    $(`.conversation-item[data-conversation-id="${conv.id}"]`).addClass('bg-body-tertiary');

    loadConversation(conv.id);

    if (chatPollTimer) clearInterval(chatPollTimer);
    chatPollTimer = setInterval(function () {
      if ($chatMessages.scrollTop() + $chatMessages.innerHeight() >= $chatMessages[0].scrollHeight - 100) {
        loadConversation(conv.id);
      } else {
        refreshConversationsList();
      }
    }, 4000);
  }

  function renderConversations(conversations) {
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
        avatarHtml = `<img src="/storage/${c.profile_picture}" alt="${c.name}" class="rounded-circle shadow-sm flex-shrink-0" style="width:40px; height:40px; object-fit:cover;">`;
      } else {
        avatarHtml = `<div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white shadow-sm flex-shrink-0" style="width:40px; height:40px;">
                        ${c.initials || '?'}
                      </div>`;
      }

      $conversationsList.append(`
        <a href="#" class="d-flex align-items-center gap-2 p-3 border-bottom text-decoration-none text-body conversation-item ${isSelected ? 'bg-body-tertiary' : ''}"
           data-conversation-id="${c.id}" data-is-group="${c.is_group}" data-name="${c.name}">
          ${avatarHtml}
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-center">
              <span class="${nameClass} text-truncate">${groupIcon}${c.name}</span>
              ${badge}
            </div>
            <small class="text-muted text-truncate d-block">${lastMsg}</small>
          </div>
        </a>
      `);
    });
  }

  function refreshConversationsList() {
    $.ajax({
      url: '/messages/conversations',
      method: 'GET',
      success: function (response) {
        renderConversations(response.conversations);
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
      $attachmentPreview.show().html(`<i class="bi bi-paperclip me-1"></i>${selectedFile.name} <a href="#" id="clearAttachmentBtn" class="ms-2 text-danger">Remove</a>`);
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

  $messageForm.on('submit', function (e) {
    e.preventDefault();

    if (isSending) return;

    const conversationId = $activeConversationId.val();
    const body = $messageInput.val().trim();

    if (!conversationId || (!body && !selectedFile)) return;

    isSending = true;
    $messageForm.find('button[type="submit"]').prop('disabled', true);

    const formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    if (body) formData.append('body', body);
    if (selectedFile) formData.append('attachment', selectedFile);

    $messageInput.val('');
    const clearedFile = selectedFile;
    selectedFile = null;
    $attachmentInput.val('');
    $attachmentPreview.hide().empty();

    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function (response) {
        $chatMessages.append(messageHtml(response.message, currentIsGroup));
        scrollToBottom();
        refreshConversationsList();
      },
      error: function (xhr) {
        $messageInput.val(body);
        selectedFile = clearedFile;
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to send message.');
      },
      complete: function () {
        isSending = false;
        $messageForm.find('button[type="submit"]').prop('disabled', false);
        $messageInput.trigger('focus');
      }
    });
  });

  $(document).on('click', '.start-chat-btn', function () {
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
              <span>${m.name}</span>
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

  // Placeholder actions — wire these up later if you want them functional
  $(document).on('click', '.reply-message-btn, .react-message-btn', function () {
    if (window.toast) window.toast('success', 'Coming soon!');
  });

  // Edit message — now updates the bubble directly, no full reload
  $chatMessages.on('click', '.edit-message-btn', function () {
    const messageId = $(this).data('message-id');
    const $bubble = $(this).closest('.message-row');
    const $bodyDiv = $bubble.find('.message-body');
    const currentText = $bodyDiv.text().trim();
    const safeText = $('<div>').text(currentText).html();

    $bodyDiv.html(`
      <div class="d-flex gap-1 align-items-center">
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
    $bodyDiv.text(originalText);
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
        const isMine = $bubble.find('.bg-primary').length > 0;
        const alignClass = isMine ? 'ms-auto bg-primary text-white' : 'me-auto bg-body-tertiary';
        const rowAlign = isMine ? 'justify-content-end' : 'justify-content-start';
        const timestamp = $bubble.find('small').first().clone().find('.edited-tag').remove().end().text().trim();

        $bubble.replaceWith(`
          <div class="message-row d-flex ${rowAlign} mb-2" data-message-id="${messageId}">
            <div class="p-2 rounded-3 ${alignClass} fst-italic opacity-75" style="max-width:70%; width:fit-content;">
              <div><i class="bi bi-slash-circle me-1"></i>This message was unsent</div>
              <small class="d-block opacity-75 mt-1" style="font-size:0.7rem;">${timestamp}</small>
            </div>
          </div>
        `);
        refreshConversationsList();
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to edit message.');
      }
    });
  });

  // Unsend message — now replaces the bubble directly, no full reload
  $chatMessages.on('click', '.unsend-message-btn', async function () {
    const messageId = $(this).data('message-id');
    const $bubble = $(this).closest('.message-bubble');

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

          refreshConversationsList();
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to unsend message.');
      }
    });
  });

  refreshConversationsList();
  setInterval(refreshConversationsList, 5000);
});