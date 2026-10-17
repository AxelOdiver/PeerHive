import { initGroupMemberSearch } from './group-member-search.js';

$(document).ready(function () {
  const groupMembers = initGroupMemberSearch(document);
  let creatingGroup = false;
  // Markup lives in messages.blade.php. Values are already escaped by callers;
  // some slots intentionally contain rendered child templates.
  const templateCache = new Map();
  function renderTemplate(id, values = []) {
    if (!templateCache.has(id)) {
      const template = document.getElementById(id);
      if (!template) throw new Error('Missing message template: ' + id);
      templateCache.set(id, template.textContent);
    }
    return templateCache.get(id).replace(/\[\[slot(\d+)\]\]/g, (_, index) => String(values[Number(index)]));
  }

  const $conversationsList = $('#conversationsList');
  const $chatMessages = $('#chatMessages');
  const $chatHeader = $('#chatHeader');
  const $chatFooter = $('#chatFooter');
  const $messageForm = $('#messageForm');
  const $activeConversationId = $('#activeConversationId');
  const $messageInput = $('#messageInput');
  const $attachmentInput = $('#attachmentInput');
  const $attachmentPreview = $('#attachmentPreview');

  // Phones show one pane at a time; desktop keeps the conversation list beside the chat.
  const messagingColumns = document.querySelector('.messaging-columns');
  $chatHeader.on('click', '#mobileChatBack', function () {
    stopTyping();
    closeDetails();
    messagingColumns.classList.remove('mobile-chat-open');
    refreshConversationsList();
    document.querySelector('.conversation-item')?.focus();
  });

  let chatPollTimer = null;
  let currentConversationId = null;
  let currentIsGroup = false;
  let sessionFinished = false;
  function setSessionFinished(finished) {
    sessionFinished = Boolean(finished);
    $('#sessionFinishedNotice').toggleClass('d-none', !sessionFinished);
    $messageForm.toggleClass('d-none', sessionFinished);
    $messageForm.find('input, button').prop('disabled', sessionFinished);
    if (sessionFinished) {
      stopTyping();
      $('#replyPreview').hide();
      $attachmentPreview.hide();
    }
  }
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
    return reactions.map(r => renderTemplate('message-template-reactions-html-1', [r.is_mine ? 'btn-light' : 'btn-outline-secondary', escapeHtml(r.emoji), r.is_mine, escapeHtml(r.emoji), escapeHtml(r.emoji), r.count])).join('');
  }

  function scrollToBottom() {
    $chatMessages.scrollTop($chatMessages[0].scrollHeight);
  }

  function renderAttachment(m) {
    if (!m.attachment_url) return '';
    const isImage = m.attachment_type && m.attachment_type.startsWith('image/');
    if (isImage) {
      return renderTemplate('message-template-render-attachment-1', [escapeHtml(m.attachment_url), escapeHtml(m.attachment_url)]);
    }
    return renderTemplate('message-template-render-attachment-2', [escapeHtml(m.attachment_url), m.is_mine ? 'text-white' : 'text-body', escapeHtml(m.attachment_name)]);
  }

  const hiddenSessionCards = new Set();
  let sessionBeforeStart = false;
  function messageHtml(m, isGroup) {
    if (m.session_event) {
      const c = m.session_event.context;
      const p = c.proposal;
      const hidden = hiddenSessionCards.has(String(m.id));
      const schedule = p ? `<p class="small mb-1">${escapeHtml(new Date(p.starts_at).toLocaleString([], {timeZone:p.timezone}))} · ${escapeHtml(p.timezone)} · ${escapeHtml(p.duration)} minutes</p><p class="small text-break">${escapeHtml(p.meeting_details)}</p>` : '';
      const actions = m.session_event.current && c.state === 'proposed' && !m.is_mine ? `<button class="btn btn-primary btn-sm" data-session-action="accept" data-action-revision="${c.revision}">Accept schedule</button> <button class="btn btn-outline-secondary btn-sm" data-session-action="changes" data-action-revision="${c.revision}">Suggest changes</button>` : '';
      return `<div class="d-flex ${m.is_mine ? 'justify-content-end' : 'justify-content-start'} mb-3" data-message-id="${m.id}"><article class="card border shadow-sm p-3" style="max-width:90%;"><div class="d-flex justify-content-between gap-3"><strong class="small">${escapeHtml(m.sender_name)} · ${escapeHtml(m.body)}</strong><button type="button" class="btn btn-link btn-sm p-0" data-hide-session-card="${m.id}">${hidden ? 'Show' : 'Hide'}</button></div><div class="session-card-details ${hidden ? 'd-none' : ''}"><p class="small mt-2">${escapeHtml(c.subject)}${c.offered_subject ? ' ↔ '+escapeHtml(c.offered_subject) : ''}</p>${schedule}${c.message ? `<p class="small text-break">${escapeHtml(c.message)}</p>` : ''}${c.finished_at ? `<p class="small">Finished: ${escapeHtml(new Date(c.finished_at).toLocaleString())} · ${escapeHtml(c.finished_minutes ?? '')} minutes</p>` : ''}${actions}</div><small class="text-muted mt-2">${escapeHtml(m.created_at)} · Session record</small></article></div>`;
    }

    if (hiddenMessageIds.has(Number(m.id))) return '';
    
        if (m.is_unsent) {
        return renderTemplate('message-template-message-html-1', [m.is_mine ? 'justify-content-end' : 'justify-content-start', m.id]);
    }

    const alignClass = m.is_mine
        ? 'bg-primary text-white'
        : 'bg-body-tertiary';

    const senderLabel = (isGroup && !m.is_mine)
        ? renderTemplate('message-template-sender-label-1', [escapeHtml(m.sender_name)])
        : '';

    const bodyHtml = m.body ? $(renderTemplate('message-template-body-html-1')).text(m.body).html() : '';
    const editedTag = m.is_edited ? renderTemplate('message-template-edited-tag-1') : '';

    const safeDataBody = escapeHtml(m.body || (m.attachment_name ? 'Attachment' : ''));
    const replyPreviewHtml = m.reply_to ? renderTemplate('message-template-reply-preview-html-1', [m.is_mine ? 'light' : 'primary', escapeHtml(m.reply_to.sender_name), escapeHtml(m.reply_to.body)]) : '';

    const kebabHtml = renderTemplate('message-template-kebab-html-1', [m.is_mine ? renderTemplate('message-template-kebab-html-2', [m.id, m.id]) : '', m.id]);

  const rowAlign = m.is_mine ? 'justify-content-end' : 'justify-content-start';

  return renderTemplate('message-template-message-html-2', [rowAlign, m.id, escapeHtml(m.client_message_id || ''), m.is_mine, escapeHtml(m.sender_name), safeDataBody, m.is_mine ? renderTemplate('message-template-message-html-3', [toolbarButtonsHtml(kebabHtml)]) : '', alignClass, senderLabel, replyPreviewHtml, bodyHtml, renderAttachment(m), reactionsHtml(m.reactions), m.created_at, editedTag, m.is_mine ? renderTemplate('message-template-message-html-4') : '', !m.is_mine ? renderTemplate('message-template-message-html-5', [toolbarButtonsHtml(kebabHtml)]) : '']);
  }

  function toolbarButtonsHtml(kebabHtml) {
    if (sessionFinished) return '';
    return renderTemplate('message-template-toolbar-buttons-html-1', [['👍', '❤️', '😂', '🎉', '😮', '😢'].map(emoji => renderTemplate('message-template-toolbar-buttons-html-2', [emoji, emoji, emoji])).join(''), kebabHtml]);
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
        setSessionFinished(response.session_finished);
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
        if (!response.messages.length) $chatMessages.html(renderTemplate('message-template-load-conversation-1', [searchTerm ? 'No matching messages.' : 'No messages yet. Say hello!']));
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


  let sessionPanelBusy = false;
  let pendingRating = null;
  function showTeacherRating(rating) {
    $('#ratingTeacherName').text(rating.teacher_name);
    $('#rateTeacherForm')[0].reset();
    $('#ratingConversationId').val(rating.conversation_id); $('#ratingError').text('');
    sessionStorage.setItem(`rating-prompt:${rating.learner_id}:${rating.conversation_id}`, 'shown');
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('rateTeacherModal')).show();
  }
  $(document).on('click', '#rateTeacherBtn', () => { if (pendingRating) showTeacherRating(pendingRating); });
  $(document).on('submit', '#rateTeacherForm', async function (e) {
    e.preventDefault(); const id = $('#ratingConversationId').val();
    $('#submitRatingBtn').prop('disabled', true); $('#ratingError').text('');
    try {
      const response = await fetch(`/messages/conversations/${id}/rating`, {method:'POST', headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}, body:JSON.stringify({stars: Number(new FormData(this).get('stars'))})});
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message);
      window.bootstrap.Modal.getInstance(document.getElementById('rateTeacherModal')).hide();
      pendingRating = null; $('#rateTeacherBtn').remove(); refreshSessionPanel();
      window.toast?.('success', result.message);
    } catch (error) { $('#ratingError').text(error.message); }
    finally { $('#submitRatingBtn').prop('disabled', false); }
  });
  function applySessionPanel(response) {
    setSessionFinished(response.session_finished);
    pendingRating = response.rating || null;
    $('#rateTeacherBtn').remove();
    if (pendingRating) {
      $('<button>', {id:'rateTeacherBtn', type:'button', class:'btn btn-outline-warning btn-sm', text:'Rate teacher'}).insertBefore('#chatInfoBtn');
      if (!sessionStorage.getItem(`rating-prompt:${pendingRating.learner_id}:${pendingRating.conversation_id}`) && !document.querySelector('.modal.show') && !window.Swal?.isVisible()) showTeacherRating(pendingRating);
    }
    const panel = $('#sessionChatPanel');
    const detailsOpen = panel.find('details').prop('open');
    sessionBeforeStart = Boolean(response.before_start);
    panel.html(response.html);
    if (detailsOpen !== undefined) panel.find('details').prop('open', detailsOpen);
    const proposal = panel.find('#sessionProposalForm').detach();
    $('#sessionProposalModalBody').empty().append(proposal);
    $('#createSessionBtn').toggleClass('d-none', !response.html).prop('disabled', !proposal.length);
    $('#finishSessionBtn').remove();
    if (response.html) $('<button>', {id: 'finishSessionBtn', type: 'button', class: 'btn btn-success btn-sm', text: 'Finish session', disabled: !response.finish, title: response.finish ? 'Finish now and request your partner’s confirmation' : 'Accept a schedule first; unavailable after confirmation or cancellation', 'data-session-action': 'finish'}).insertBefore('#chatInfoBtn');
  }
  function refreshSessionPanel() {
    if (!currentConversationId || sessionPanelBusy || $('#createSessionModal').hasClass('show') || $('#sessionDetailsModal').hasClass('show') || $('#sessionChatPanel').find('input:focus, select:focus, textarea:focus').length || $('#sessionProposalForm').data('dirty') || $('#sessionProblemForm').data('dirty')) return;
    const id = currentConversationId;
    $.getJSON(`/messages/conversations/${id}/session`).done(response => { if (String(id) === String(currentConversationId)) applySessionPanel(response); });
  }
  async function sessionAction(action, form = null, actualMinutes = null, revision = null) {
    if (sessionPanelBusy) return;
    const id = currentConversationId;
    const data = form ? new FormData(form) : new FormData();
    data.set('action', action);
    if (actualMinutes !== null) data.set('actual_minutes', actualMinutes);
    data.set('revision', revision ?? $('#sessionChatPanel [data-session-revision]').attr('data-session-revision'));
    data.set('_token', $('meta[name="csrf-token"]').attr('content'));
    sessionPanelBusy = true;
    try {
      const response = await fetch(`/messages/conversations/${id}/session`, {method: 'POST', headers: {Accept: 'application/json'}, body: data});
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Unable to update session.');
      if (String(id) === String(currentConversationId)) {
        window.bootstrap.Modal.getInstance(document.getElementById('createSessionModal'))?.hide();
        applySessionPanel(result);
        loadConversation(id, null, true);
      }
      window.refreshSidebarMessagesBadge?.();
    } catch (error) { window.toast?.('error', error.message); }
    finally { sessionPanelBusy = false; }
  }
  $(document).on('input change', '#sessionProposalForm, #sessionProblemForm', function () { $(this).data('dirty', true); });
  $(document).on('click', '#createSessionBtn', function () { window.bootstrap.Modal.getOrCreateInstance(document.getElementById('createSessionModal')).show(); });
  $(document).on('submit', '#sessionProposalForm', function (e) { e.preventDefault(); sessionAction('propose', this); });
  $(document).on('submit', '#sessionProblemForm', function (e) { e.preventDefault(); sessionAction('problem', this); });
  $(document).on('click', '[data-hide-session-card]', function () {
    const id = String(this.dataset.hideSessionCard);
    if (hiddenSessionCards.has(id)) hiddenSessionCards.delete(id); else hiddenSessionCards.add(id);
    $(this).text(hiddenSessionCards.has(id) ? 'Show' : 'Hide').closest('article').find('.session-card-details').toggleClass('d-none', hiddenSessionCards.has(id));
  });
  $(document).on('click', '[data-session-action]', async function () {
    const action = this.dataset.sessionAction;
    let minutes = null;
    if (action === 'finish' && sessionBeforeStart) {
      const answer = await window.Swal.fire({title: 'Finish ahead of schedule', text: 'How many minutes did you actually study? Your partner must confirm this time too.', input: 'number', inputAttributes: {min: '1', max: '240', step: '1'}, showCancelButton: true, confirmButtonText: 'Confirm my attendance', inputValidator: value => !Number.isInteger(Number(value)) || Number(value) < 1 ? 'Enter the actual number of minutes.' : undefined});
      if (!answer.isConfirmed) return;
      minutes = answer.value;
    } else if (['finish', 'cancel'].includes(action)) {
      const answer = await window.confirmAction(action === 'finish' ? 'Confirm the recorded session time and your attendance? Your partner must also confirm.' : 'Cancel this session? No points will be awarded.', action === 'finish' ? 'Finish session' : 'Cancel session');
      if (!answer.isConfirmed) return;
    }
    sessionAction(action, null, minutes, this.dataset.actionRevision ?? null);
  });
  $(document).on('click', '[data-session-starter]', function () { $messageInput.val(this.dataset.sessionStarter).trigger('focus'); });

  function renderChatHeader(conv) {
    const buttons = renderTemplate('message-template-buttons-1');
    $chatHeader.html(renderTemplate('message-template-render-chat-header-1', [escapeHtml(conv.name), buttons]));
  }

  function openConversation(conv) {
    messagingColumns.classList.add('mobile-chat-open');
    stopTyping();
    activityMembers = [];
    $('#typingIndicator').hide();
    viewVersion++;
    searchTerm = '';
    clearTimeout(searchTimer);
    closeDetails();
    viewingHistory = false; hasNewerMessages = false; loadingNewer = false;
    $('#backToLatestBtn').hide();
    // Returning from the mobile conversation list must preserve the active draft.
    if (String(currentConversationId) !== String(conv.id)) {
      $('#cancelReplyBtn').trigger('click');
      $messageInput.val('');
      selectedFile = null;
      $attachmentInput.val('');
      $attachmentPreview.hide().empty();
    }
    oldestLoadedId = null;
    hasMoreMessages = false;
    isLoadingOlder = false;
    $chatMessages.empty();
    currentConversationId = conv.id;
    setSessionFinished(false);
    activeConversation = conv;
    $activeConversationId.val(conv.id);
    renderChatHeader(conv);
    $('#sessionChatPanel').empty();
    $('#sessionProposalModalBody').empty();
    $('#createSessionBtn').addClass('d-none');
    refreshSessionPanel();
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
      refreshSessionPanel();
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
      $conversationsList.append(renderTemplate('message-template-render-conversations-1'));
      return;
    }

    conversations.forEach(function (c) {
      const isSelected = currentConversationId && String(currentConversationId) === String(c.id);
      const nameClass = c.unread_count > 0 ? 'fw-bold' : 'fw-semibold';
      const badge = c.unread_count > 0 ? renderTemplate('message-template-badge-1', [c.unread_count]) : '';
      const lastMsg = c.last_message ? c.last_message.slice(0, 30) : 'No messages yet';
      const groupIcon = c.is_group ? renderTemplate('message-template-group-icon-1') : '';

      let avatarHtml = '';
      if (c.profile_picture) {
        avatarHtml = renderTemplate('message-template-render-conversations-2', [c.profile_picture, escapeHtml(c.name)]);
      } else {
        avatarHtml = renderTemplate('message-template-render-conversations-3', [escapeHtml(c.initials || '?')]);
      }

      $conversationsList.append(renderTemplate('message-template-render-conversations-4', [isSelected ? 'bg-body-tertiary' : '', c.id, c.is_group, escapeHtml(c.name), avatarHtml, c.presence?.is_online ? renderTemplate('message-template-render-conversations-5') : '', nameClass, groupIcon, escapeHtml(c.name), c.is_muted ? renderTemplate('message-template-render-conversations-6') : '', badge, escapeHtml(lastMsg), escapeHtml(c.name), c.id, c.is_muted, c.is_muted ? 'Unmute notifications' : 'Mute notifications', c.is_group ? renderTemplate('message-template-render-conversations-7', [c.id]) : '', c.id, escapeHtml(c.name)]));
    });
  }

  let requestedConversation = new URLSearchParams(window.location.search).get('conversation');
  function refreshConversationsList() {
    $.ajax({
      url: '/messages/conversations',
      method: 'GET',
      success: function (response) {
        renderConversations(response.conversations);
        if (requestedConversation) {
          const target = response.conversations.find(c => String(c.id) === requestedConversation);
          requestedConversation = null;
          if (target) openConversation(target);
        }
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
      $attachmentPreview.show().html(renderTemplate('message-template-fragment-1', [escapeHtml(selectedFile.name)]));
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
        ? renderTemplate('message-template-status-1', [item.id])
        : renderTemplate('message-template-status-2');
      $chatMessages.append(renderTemplate('message-template-render-outgoing-1', [item.id, item.replyText ? renderTemplate('message-template-render-outgoing-2', [escapeHtml(item.replyText)]) : '', escapeHtml(item.body), item.file ? renderTemplate('message-template-render-outgoing-3', [escapeHtml(item.file.name)]) : '', status]));
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
    if (sessionFinished) return;
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
    if (creatingGroup) return;
    const name = $('#groupNameInput').val().trim();
    const memberIds = groupMembers.ids();

    if (memberIds.length < 2) {
      if (window.toast) window.toast('error', 'Select at least 2 members.');
      return;
    }

    creatingGroup = true;
    $(this).prop('disabled', true);
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
        groupMembers.clear();
        refreshConversationsList();
        if (window.toast) window.toast('success', 'Group created!');
        setTimeout(function () {
          openConversation({ id: response.conversation_id, is_group: true, name: name || 'Group' });
        }, 300);
      },
      error: function (xhr) {
        if (window.toast) window.toast('error', xhr.responseJSON?.message || 'Failed to create group.');
      },
      complete: function () {
        creatingGroup = false;
        $('#createGroupBtn').prop('disabled', groupMembers.ids().length < 2);
      }
    });
  });
  $('#newGroupForm').on('submit', function (event) {
    event.preventDefault();
    $('#createGroupBtn').trigger('click');
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
          html += renderTemplate('message-template-fragment-2', [escapeHtml(m.name), m.id, conversationId]);
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
    if (sessionFinished) return;
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

    $bodyDiv.addClass('is-editing').html(renderTemplate('message-template-fragment-3', [safeText, messageId, safeText]));
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
        if (!$row.find('.edited-tag').length) $row.find('small').append(renderTemplate('message-template-fragment-4'));
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

          $row.replaceWith(renderTemplate('message-template-fragment-5', [messageId]));

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
          messagingColumns.classList.remove('mobile-chat-open');
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
          $results.append(renderTemplate('message-template-search-messages-1', [m.id, escapeHtml(m.sender_name), escapeHtml(m.body || m.attachment_name || 'Attachment'), escapeHtml(m.created_at)]));
        });
        if (!response.total_results) $results.html(renderTemplate('message-template-search-messages-2'));
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
    $('#chatInfoContent').html(renderTemplate('message-template-show-conversation-info-1'));
    $.ajax({
      url: `/messages/conversations/${id}/info`,
      success: response => {
        if (version !== infoVersion || String(id) !== String(currentConversationId)) return;
        const members = response.members.map(m => renderTemplate('message-template-members-1', [escapeHtml(m.profile_url), m.profile_picture ? renderTemplate('message-template-members-2', [escapeHtml(m.profile_picture)]) : renderTemplate('message-template-members-3'), escapeHtml(m.name)])).join('');
        $('#chatInfoContent').html(renderTemplate('message-template-show-conversation-info-2', [response.is_group ? 'bi-people-fill' : 'bi-person-fill', escapeHtml(conv.name), response.is_group ? response.members.length + ' members' : 'Direct conversation', id, response.is_muted, response.is_muted ? 'bi-bell-slash-fill' : 'bi-bell', response.is_muted ? 'Unmute' : 'Mute', response.is_muted ? (response.muted_until ? 'Notifications muted until ' + escapeHtml(new Date(response.muted_until).toLocaleString()) + '.' : 'Notifications muted until you turn them back on.') : 'Notifications are on.', response.is_group ? 'Chat members' : 'People', members, response.is_group ? renderTemplate('message-template-show-conversation-info-3', [id]) : '', response.is_group ? renderTemplate('message-template-show-conversation-info-4', [id]) : '']));
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
          messagingColumns.classList.remove('mobile-chat-open');
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
    return 'Active ' + count + ' ' + unit + (count === 1 ? '' : 's') + ' ago';
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
  const requestedPeer = new URLSearchParams(window.location.search).get('peer');
  if (requestedPeer && /^\d+$/.test(requestedPeer)) {
    $.ajax({
      url: '/messages/conversations', method: 'POST',
      data: { user_id: requestedPeer, _token: $('meta[name="csrf-token"]').attr('content') },
      success: response => {
        $.getJSON('/messages/conversations').done(list => {
          renderConversations(list.conversations);
          const conversation = list.conversations.find(c => String(c.id) === String(response.conversation_id));
          if (conversation) openConversation(conversation);
        }).fail(requestError);
      },
      error: requestError,
    });
  }
});
