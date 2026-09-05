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
  let selectedFile = null;

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

  function renderMessages(data) {
    $chatMessages.empty();
    data.messages.forEach(function (m) {
      const alignClass = m.is_mine ? 'ms-auto bg-primary text-white' : 'me-auto bg-body-tertiary';
      const senderLabel = (data.is_group && !m.is_mine) ? `<div class="small fw-semibold mb-1">${m.sender_name}</div>` : '';
      const bodyHtml = m.body ? $('<div>').text(m.body).html() : '';
      $chatMessages.append(`
        <div class="mb-2 p-2 rounded-3 ${alignClass}" style="max-width:70%; width:fit-content;">
          ${senderLabel}
          <div>${bodyHtml}</div>
          ${renderAttachment(m)}
          <small class="d-block opacity-75 mt-1" style="font-size:0.7rem;">${m.created_at}</small>
        </div>
      `);
    });
    scrollToBottom();
  }

  function loadConversation(conversationId) {
    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'GET',
      success: function (response) {
        renderMessages(response);
        refreshConversationsList();
        refreshUnreadBadge();
      }
    });
  }

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
      loadConversation(conv.id);
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

      $conversationsList.append(`
        <a href="#" class="d-flex align-items-center gap-2 p-3 border-bottom text-decoration-none text-body conversation-item ${isSelected ? 'bg-body-tertiary' : ''}"
           data-conversation-id="${c.id}" data-is-group="${c.is_group}" data-name="${c.name}">
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0" style="width:40px;height:40px;">
            ${c.initials}
          </div>
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
    const conversationId = $activeConversationId.val();
    const body = $messageInput.val().trim();

    if (!conversationId || (!body && !selectedFile)) return;

    const formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    if (body) formData.append('body', body);
    if (selectedFile) formData.append('attachment', selectedFile);

    $.ajax({
      url: `/messages/conversations/${conversationId}`,
      method: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function () {
        $messageInput.val('');
        selectedFile = null;
        $attachmentInput.val('');
        $attachmentPreview.hide().empty();
        loadConversation(conversationId);
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to send message.');
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

  refreshConversationsList();
  setInterval(refreshConversationsList, 5000);
});