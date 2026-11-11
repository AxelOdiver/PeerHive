import { initGroupMemberSearch } from './group-member-search.js';
import { mergeConversationMetadata } from '../support/conversation-metadata.js';

import { initSessionActivities, openSessionActivities } from './session-activities.js';

$(document).ready(function () {
  initSessionActivities();
  let activityAutoOpened = false;
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
  function resetConversationHeader() {
    $chatHeader.html('<span class="text-body-secondary">Select a conversation to start chatting</span>');
  }
  const collapsedChatSections = new Set();
  let selectedConversationFilter = 'all';
  $(document).on('click', '.ph-chat-filter-chip', function () {
    selectedConversationFilter = this.dataset.chatFilter || 'all';
    $('.ph-chat-filter-chip').removeClass('is-active').attr('aria-pressed', 'false');
    $(this).addClass('is-active').attr('aria-pressed', 'true');
    renderConversations(conversationSnapshot);
  });
  $(document).on('click', '.ph-chat-section-toggle', function () {
    const key = this.dataset.section;
    if (collapsedChatSections.has(key)) collapsedChatSections.delete(key); else collapsedChatSections.add(key);
    renderConversations(conversationSnapshot);
  });
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
    updateComposerState();
    if (sessionFinished) {
      stopTyping();
      $('#replyPreview').hide();
      $attachmentPreview.hide();
    }
  }
  function updateComposerState() {
    const blocked = Boolean(activeConversation?.blocked_by_me);
    const readOnly = sessionFinished || blocked;
    $('#blockedConversationNotice').toggleClass('d-none', !blocked || sessionFinished);
    $('#unblockConversationBtn').attr('data-user-id', activeConversation?.other_user_id || '');
    $messageForm.toggleClass('d-none', readOnly);
    $messageForm.find('input, button').prop('disabled', readOnly);
    if (readOnly) {
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
  let conversationSnapshot = [];
  const conversationIndex = new Map();

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
    const isImage = ['image/jpeg', 'image/png', 'image/webp'].includes(m.attachment_type);
    if (isImage) {
      const preview = m.attachment_url.includes('/private/') ? m.attachment_url + '?preview=1' : m.attachment_url;
      return renderTemplate('message-template-render-attachment-1', [escapeHtml(preview), escapeHtml(preview)]);
    }
    return renderTemplate('message-template-render-attachment-2', [escapeHtml(m.attachment_url), m.is_mine ? 'text-white' : 'text-body', escapeHtml(m.attachment_name)]);
  }

  const hiddenSessionCards = new Set();
  const expandedPastSessionCards = new Set();
  let sessionBeforeStart = false;
  function messageHtml(m, isGroup) {
    if (m.session_event) {
      const c = m.session_event.context;
      const p = c.proposal;
      const hidden = hiddenSessionCards.has(String(m.id));
      const meetingDetails = m.session_event.meeting_url ? `<a href="${escapeHtml(m.session_event.meeting_url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(m.session_event.meeting_url)} (opens in a new tab)</a>` : escapeHtml(p?.meeting_details || '');
      const schedule = p ? `<p class="small mb-1">${escapeHtml(new Date(p.starts_at).toLocaleString([], {timeZone:p.timezone}))} · ${escapeHtml(p.timezone)} · ${escapeHtml(p.duration)} minutes</p><p class="small text-break">${meetingDetails}</p>` : '';
      const latest = Boolean(m.session_event.current);
      const pending = latest && c.state === 'proposed';
      const ownProposal = pending && m.is_mine;
      const proposalActions = !pending ? '' : ownProposal
        ? `<div class="ph-session-card-actions"><button type="button" class="btn btn-outline-primary btn-sm" data-session-open-proposal="edit" data-action-revision="${c.revision}"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit proposal</button><button type="button" class="btn btn-outline-danger btn-sm" data-session-action="withdraw" data-action-revision="${c.revision}">Cancel proposal</button></div>`
        : `<div class="ph-session-card-actions"><button type="button" class="btn btn-primary btn-sm" data-session-action="accept" data-action-revision="${c.revision}">Accept schedule</button><button type="button" class="btn btn-outline-danger btn-sm" data-session-action="decline" data-action-revision="${c.revision}">Decline</button><button type="button" class="btn btn-link btn-sm" data-session-open-proposal="counter" data-action-revision="${c.revision}">Suggest changes</button></div>`;
      const note = c.change_note && m.session_event.action === 'counter' ? `<p class="ph-session-change-note"><i class="bi bi-chat-left-text" aria-hidden="true"></i> ${escapeHtml(c.change_note)}</p>` : '';
      const swapTime = p && c.type === 'skill_swap' && p.teaching_minutes ? `<p class="small text-body-secondary">${escapeHtml(p.teaching_minutes)} minutes: ${escapeHtml(c.subject)}, ${escapeHtml(Number(p.duration) - Number(p.teaching_minutes))} minutes: ${escapeHtml(c.offered_subject)}</p>` : '';
      const heading = m.session_event.action === 'counter' ? 'Suggested a new schedule' : escapeHtml(m.body);
      const compact = hidden || (!pending && !expandedPastSessionCards.has(String(m.id)));
      const label = latest && pending ? 'Pending proposal' : c.state === 'completed' ? 'Completed' : c.state === 'cancelled' ? 'Cancelled' : latest && c.state === 'scheduled' ? 'Scheduled' : 'Earlier update';
      const time = p ? escapeHtml(new Date(p.starts_at).toLocaleString([], {timeZone:p.timezone, dateStyle:'medium',timeStyle:'short'})) : '';
      const meetingLabel = p?.meeting_method === 'online' ? 'Online meeting' : 'In-person venue';
      const details = p ? `<div class="ph-session-card-detail-grid"><span><i class="bi bi-calendar3" aria-hidden="true"></i>${time}</span><span><i class="bi bi-clock" aria-hidden="true"></i>${escapeHtml(p.duration)} minutes</span><span><i class="bi bi-globe2" aria-hidden="true"></i>${escapeHtml(p.timezone)}</span><span><i class="bi bi-${p.meeting_method === 'online' ? 'camera-video' : 'geo-alt'}" aria-hidden="true"></i>${meetingLabel}</span></div><div class="ph-session-card-meeting">${meetingDetails}</div>` : '';
      return `<div class="ph-session-event-row mb-3" data-message-id="${m.id}"><article class="card ph-session-event-card p-3" role="group" aria-label="Session event"><div class="d-flex justify-content-between align-items-start gap-2"><div class="d-flex align-items-center gap-2"><span class="ph-session-event-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span><div><strong class="d-block">${heading}</strong><small class="text-body-secondary">By ${escapeHtml(m.sender_name)}</small></div></div><span class="ph-session-state-badge">${label}</span></div><div class="session-card-details ${compact ? 'd-none' : ''}"><h3 class="h6 fw-bold mt-3 mb-2 ph-session-subject">${escapeHtml(c.subject)}${c.offered_subject ? ' ↔ '+escapeHtml(c.offered_subject) : ''}</h3>${details}${swapTime}${note}${c.message ? `<p class="small ph-session-card-note">${escapeHtml(c.message)}</p>` : ''}${c.finished_at ? `<p class="small">Finished: ${escapeHtml(new Date(c.finished_at).toLocaleString())}</p>` : ''}${proposalActions}</div><div class="ph-session-card-footer mt-2"><small class="text-body-secondary">${escapeHtml(m.created_at)} · Session record</small><button type="button" class="btn btn-link btn-sm p-0" data-hide-session-card="${m.id}" data-older="${pending ? '0' : '1'}">${compact ? 'Show details' : 'Hide details'}</button></div></article></div>`;
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

    const reportItem = !m.is_mine ? `<li><button type="button" class="dropdown-item text-warning message-report-btn" data-message-id="${m.id}" data-user-id="${m.sender_id}"><i class="bi bi-flag me-2"></i>Report message</button></li>` : '';
    const kebabHtml = renderTemplate('message-template-kebab-html-1', [m.is_mine ? renderTemplate('message-template-kebab-html-2', [m.id, m.id]) : '', m.id, reportItem]);

  const rowAlign = m.is_mine ? 'justify-content-end' : 'justify-content-start';

  return renderTemplate('message-template-message-html-2', [rowAlign, m.id, escapeHtml(m.client_message_id || ''), m.is_mine, escapeHtml(m.sender_name), safeDataBody, m.is_mine ? renderTemplate('message-template-message-html-3', [toolbarButtonsHtml(kebabHtml)]) : '', alignClass, senderLabel, replyPreviewHtml, bodyHtml, renderAttachment(m), reactionsHtml(m.reactions), m.created_at, editedTag, m.is_mine ? renderTemplate('message-template-message-html-4') : '', !m.is_mine ? renderTemplate('message-template-message-html-5', [toolbarButtonsHtml(kebabHtml)]) : '']);
  }

  function toolbarButtonsHtml(kebabHtml) {
    if (sessionFinished || activeConversation?.blocked_by_me) return kebabHtml;
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
        if (activeConversation) { activeConversation.blocked_by_me = Boolean(response.blocked_by_me); activeConversation.other_user_id = response.other_user_id; }
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
    const formData = new FormData(this);
    $('#submitRatingBtn').prop('disabled', true); $('#ratingError').text('');
    try {
      const response = await fetch(`/messages/conversations/${id}/rating`, {method:'POST', headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')}, body:JSON.stringify({stars: Number(formData.get('stars')), comment: formData.get('comment')})});
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
    $('#sessionDetailsBtn').toggleClass('d-none', !response.html);
    $('#sessionActivitiesBtn').remove();
    if (response.activities_url) {
      $('<a>', {id: 'sessionActivitiesBtn', href: response.activities_url, class: 'btn btn-outline-primary btn-sm ph-session-header-btn', title: 'View session activities', 'aria-label': 'View session activities', 'data-session-activities': ''}).html('<i class="bi bi-clipboard-check" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Activities</span>').insertBefore('#chatInfoBtn');
      if (!activityAutoOpened && new URLSearchParams(window.location.search).get('activities') === '1') {
        activityAutoOpened = true;
        openSessionActivities(response.activities_url);
      }
    }
    pendingRating = response.rating || null;
    $('#rateTeacherBtn').remove();
    if (pendingRating) {
      $('<button>', {id:'rateTeacherBtn', type:'button', class:'btn btn-outline-warning btn-sm ph-session-header-btn', title:'Rate your teacher', 'aria-label':'Rate your teacher'}).html('<i class="bi bi-star" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Rate teacher</span>').insertBefore('#chatInfoBtn');
      if (!sessionStorage.getItem(`rating-prompt:${pendingRating.learner_id}:${pendingRating.conversation_id}`) && !document.querySelector('.modal.show') && !window.Swal?.isVisible()) showTeacherRating(pendingRating);
    }
    const panel = $('#sessionChatPanel');
    const detailsOpen = panel.find('details').prop('open');
    sessionBeforeStart = Boolean(response.before_start);
    panel.html(response.html);
    if (detailsOpen !== undefined) panel.find('details').prop('open', detailsOpen);
    const proposal = panel.find('#sessionProposalForm').detach();
    $('#sessionProposalModalBody').empty().append(proposal);
    $('#createSessionBtn').toggleClass('d-none', !response.html || !proposal.length).prop('disabled', !proposal.length);
    const label = proposal.attr('data-proposal-action') === 'counter' ? 'Suggest changes' : proposal.attr('data-proposal-state') === 'proposed' ? 'Edit proposal' : 'Propose schedule';
    $('#createSessionBtn span').text(label);
    $('#finishSessionBtn').remove();
    if (response.html && response.finish) $('<button>', {id: 'finishSessionBtn', type: 'button', class: 'btn btn-success btn-sm ph-session-header-btn', title: 'Finish now and request your partner’s confirmation', 'aria-label': 'Finish session', 'data-session-action': 'finish'}).html('<i class="bi bi-check2-circle" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Finish session</span>').insertBefore('#chatInfoBtn');
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
  function openProposalEditor() {
    const form = document.getElementById('sessionProposalForm');
    if (!form) return;
    const title = form.dataset.proposalAction === 'counter' ? 'Suggest changes' : form.dataset.proposalState === 'proposed' ? 'Edit proposed session' : 'Create a session';
    $('#createSessionModalLabel').text(title);
    updateMeetingMethodFields(form);
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('createSessionModal')).show();
  }
  function updateMeetingMethodFields(form) {
    if (!form) return;
    const selected = form.querySelector('input[name="meeting_method"]:checked')?.value || 'online';
    const isOnline = selected === 'online';
    const label = form.querySelector('[data-ph-meeting-label]');
    const helper = form.querySelector('[data-ph-meeting-help]');
    const input = form.querySelector('[name="meeting_details"]');
    if (label) label.textContent = isOnline ? 'Meeting link' : 'Meeting venue';
    if (helper) helper.textContent = isOnline ? 'Use an HTTPS Google Meet, Zoom, or Microsoft Teams link.' : 'Enter a clear, safe meeting place so both participants know where to meet.';
    if (input) { input.placeholder = isOnline ? 'https://meet.google.com/...' : 'Campus building, room, or public venue'; input.setAttribute('autocomplete', 'off'); }
  }
  $(document).on('change', '#sessionProposalForm input[name="meeting_method"]', function () { updateMeetingMethodFields(this.form); });
  $(document).on('click', '#createSessionBtn, [data-session-open-proposal]', openProposalEditor);
  $('#createSessionModal').on('shown.bs.modal', function () { $(this).find('input[name="starts_at"]').trigger('focus'); });
  $(document).on('click', '#sessionDetailsBtn', function () { window.bootstrap.Modal.getOrCreateInstance(document.getElementById('sessionDetailsModal')).show(); });
  $(document).on('submit', '#sessionProposalForm', function (e) { e.preventDefault(); sessionAction(this.dataset.proposalAction || 'propose', this); });
  $(document).on('submit', '#sessionProblemForm', function (e) { e.preventDefault(); sessionAction('problem', this); });
  $(document).on('click', '[data-hide-session-card]', function () {
    const id = String(this.dataset.hideSessionCard);
    const older = this.dataset.older === '1';
    const $details = $(this).closest('article').find('.session-card-details');
    const nowHidden = !$details.hasClass('d-none');
    $details.toggleClass('d-none', nowHidden);
    // Preserve expanded/collapsed state during the four-second chat refresh.
    if (older) { if (nowHidden) expandedPastSessionCards.delete(id); else expandedPastSessionCards.add(id); }
    else { if (nowHidden) hiddenSessionCards.add(id); else hiddenSessionCards.delete(id); }
    $(this).text(nowHidden ? 'Show details' : 'Hide details');
  });
  $(document).on('click', '[data-session-action]', async function () {
    const action = this.dataset.sessionAction;
    let minutes = null;
    if (action === 'finish' && sessionBeforeStart) {
      const answer = await window.Swal.fire({
        title: 'Finish ahead of schedule',
        text: 'How many minutes did you actually study? Your partner must confirm this time too.',
        input: 'number', inputAttributes: {min: '1', max: '240', step: '1'},
        showCancelButton: true, confirmButtonText: 'Confirm my attendance',
        background: 'var(--bs-body-bg)', color: 'var(--bs-body-color)', buttonsStyling: false,
        customClass: {popup: 'session-finish-dialog rounded-4', input: 'form-control', actions: 'gap-2', confirmButton: 'btn btn-primary', cancelButton: 'btn btn-outline-secondary'},
        inputValidator: value => !Number.isInteger(Number(value)) || Number(value) < 1 ? 'Enter the actual number of minutes.' : undefined,
      });
      if (!answer.isConfirmed) return;
      minutes = answer.value;
    } else if (['finish', 'cancel', 'withdraw', 'decline'].includes(action)) {
      const choices = {
        finish: ['Confirm the recorded session time and your attendance? Your partner must also confirm.', 'Finish session'],
        cancel: ['Cancel the entire learning session? No points will be awarded.', 'Cancel session'],
        withdraw: ['Withdraw this proposed time? Your learning request and conversation will remain open.', 'Cancel proposal'],
        decline: ['Decline this proposed time? You can continue discussing another schedule.', 'Decline proposal'],
      };
      const answer = await window.confirmAction(...choices[action]);
      if (!answer.isConfirmed) return;
    }
    sessionAction(action, null, minutes, this.dataset.actionRevision ?? null);
  });
  $(document).on('click', '[data-session-starter]', function () { $messageInput.val(this.dataset.sessionStarter).trigger('focus'); });

  function renderChatHeader(conv) {
    const accountActions = conv.other_user_id
      ? `<li><button type="button" class="dropdown-item account-report-btn" data-user-id="${conv.other_user_id}"><i class="bi bi-flag me-2"></i>Report account</button></li><li><button type="button" class="dropdown-item ${conv.blocked_by_me ? '' : 'text-danger'} direct-block-btn" data-user-id="${conv.other_user_id}" data-blocked-by-me="${conv.blocked_by_me ? 1 : 0}"><i class="bi bi-slash-circle me-2"></i>${conv.blocked_by_me ? 'Unblock account' : 'Block account'}</button></li>`
      : '';
    const buttons = renderTemplate('message-template-buttons-1', [accountActions]);
    const initialText = escapeHtml((conv.name || '?').split(/\s+/).slice(0, 2).map(word => word[0] || '').join('').toUpperCase());
    const avatar = conv.group_photo_url && conv.is_group
      ? `<img src="${escapeHtml(conv.group_photo_url)}" alt="" class="ph-chat-header-photo">`
      : conv.profile_picture
        ? `<img src="/storage/${escapeHtml(conv.profile_picture)}" alt="" class="ph-chat-header-photo">`
        : `<span class="ph-chat-header-initials">${initialText}</span>`;
    const typeLabel = conv.is_session ? 'Private Learn/Swap conversation' : conv.is_group ? 'Group conversation' : 'Direct message';
    $chatHeader.html(renderTemplate('message-template-render-chat-header-1', [escapeHtml(conv.name), buttons, avatar, typeLabel]));
    updateComposerState();
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
    $('#createSessionBtn, #sessionDetailsBtn').addClass('d-none');
    $('#sessionActivitiesBtn, #finishSessionBtn, #rateTeacherBtn').remove();
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

  function chatListTime(timestamp) {
    const date = new Date(Number(timestamp) * 1000);
    if (!Number.isFinite(date.getTime())) return '';
    const today = new Date();
    return date.toDateString() === today.toDateString()
      ? new Intl.DateTimeFormat(undefined, {hour: 'numeric', minute: '2-digit'}).format(date)
      : new Intl.DateTimeFormat(undefined, {month: 'short', day: 'numeric'}).format(date);
  }

  function renderConversations(conversations) {
    if ($conversationsList.find('.dropdown-menu.show').length) return;
    conversationSnapshot = conversations;
    conversationIndex.clear();
    conversations.forEach(c => conversationIndex.set(String(c.id), c));
    const term = String($('#conversationSearchInput').val() || '').trim().toLocaleLowerCase();
    const unreadOnly = selectedConversationFilter === 'unread';
    const visible = conversations.filter(c => (!term || String(c.name || '').toLocaleLowerCase().includes(term) || String(c.last_message || '').toLocaleLowerCase().includes(term)) && (!unreadOnly || c.unread_count > 0) && (selectedConversationFilter === 'all' || selectedConversationFilter === 'unread' || (selectedConversationFilter === 'direct' && !c.is_group && !c.is_session) || (selectedConversationFilter === 'session' && c.is_session) || (selectedConversationFilter === 'group' && c.is_group && !c.is_session)));
    $conversationsList.empty();
    if (!visible.length) {
      $conversationsList.append(term || unreadOnly ? '<div class="ph-chat-list-empty"><i class="bi bi-search" aria-hidden="true"></i><p class="mb-0">No matching conversations.</p><small>Try a different search or filter.</small></div>' : renderTemplate('message-template-render-conversations-1'));
      return;
    }
    const sections = [
      {key: 'direct', name: 'Direct Messages', icon: 'bi-chat-dots', items: visible.filter(c => !c.is_group && !c.is_session)},
      {key: 'session', name: 'Learn/Swap Sessions', icon: 'bi-mortarboard', items: visible.filter(c => c.is_session)},
      {key: 'group', name: 'Group Chats', icon: 'bi-people', items: visible.filter(c => c.is_group && !c.is_session)},
    ];
    sections.forEach(section => {
      if (!section.items.length) return;
      const collapsed = collapsedChatSections.has(section.key);
      const $heading = $(`<button type="button" class="ph-chat-section-heading ph-chat-section-toggle" data-section="${section.key}" aria-expanded="${!collapsed}"><i class="bi ${section.icon}" aria-hidden="true"></i><span>${section.name}</span><span class="ph-chat-section-count">${section.items.length}</span><i class="bi ${collapsed ? 'bi-chevron-right' : 'bi-chevron-down'} ph-chat-section-chevron" aria-hidden="true"></i></button>`);
      $conversationsList.append($heading);
      const $body = $('<div class="ph-chat-section-body"></div>').toggleClass('d-none', collapsed).appendTo($conversationsList);
      section.items.forEach(function (c) {
        const isSelected = currentConversationId && String(currentConversationId) === String(c.id);
        const nameClass = c.unread_count > 0 ? 'fw-bold' : 'fw-semibold';
        const badge = c.unread_count > 0 ? renderTemplate('message-template-badge-1', [c.unread_count]) : '';
        const lastMsg = c.last_message ? c.last_message.slice(0, 55) : 'No messages yet';
        let avatarHtml = '';
        if (c.group_photo_url && c.is_group && !c.is_session) {
          avatarHtml = `<img src="${escapeHtml(c.group_photo_url)}" alt="" class="ph-chat-header-photo ph-group-list-photo">`;
        } else if (c.profile_picture && !c.is_session && !c.is_group) {
          avatarHtml = renderTemplate('message-template-render-conversations-2', [escapeHtml(c.profile_picture), escapeHtml(c.name)]);
        } else if (c.is_session || c.is_group) {
          avatarHtml = `<span class="ph-chat-conversation-type-avatar"><i class="bi ${c.is_session ? 'bi-mortarboard-fill' : 'bi-people-fill'}" aria-hidden="true"></i></span>`;
        } else {
          avatarHtml = renderTemplate('message-template-render-conversations-3', [escapeHtml(c.initials || '?')]);
        }
        const label = c.is_session ? 'Learn/Swap session' : c.is_group ? 'Group chat' : 'Direct message';
        const chip = `<span class="${c.is_session ? 'ph-kind-session' : ''}">${label}</span>`;
        $body.append(renderTemplate('message-template-render-conversations-4', [isSelected ? 'bg-body-tertiary' : '', c.id, c.is_group, escapeHtml(c.name), avatarHtml, c.presence?.is_online ? renderTemplate('message-template-render-conversations-5') : '', nameClass, '', escapeHtml(c.name), c.is_muted ? renderTemplate('message-template-render-conversations-6') : '', badge, escapeHtml(lastMsg), escapeHtml(c.name), c.id, c.is_muted, c.is_muted ? 'Unmute notifications' : 'Mute notifications', c.is_group ? renderTemplate('message-template-render-conversations-7', [c.id]) : '', c.id, escapeHtml(c.name), chip, escapeHtml(chatListTime(c.last_message_at))]));
      });
    });
  }
  $('#conversationSearchInput').on('input', () => renderConversations(conversationSnapshot));

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
        if (active && activeConversation) {
          const merged = mergeConversationMetadata(activeConversation, active);
          activeConversation = merged.value;
          if (merged.headerChanged) {
            renderChatHeader(activeConversation);
            // Refresh only details when visible; do not reopen chat or discard a draft.
            if ($('#chatInfoPanel').is(':visible')) showConversationInfo();
          }
        }
        if (active && !active.is_group) $('#chatPresenceStatus').text(presenceLabel(active.presence));
      }
    });
  }

  function refreshUnreadBadge() {
    if (window.refreshSidebarMessagesBadge) window.refreshSidebarMessagesBadge();
  }

  $conversationsList.on('click', '.conversation-item', function (e) {
    e.preventDefault();
    const id = String($(this).data('conversation-id'));
    openConversation(conversationIndex.get(id) || {
      id, is_group: $(this).data('is-group') === true || $(this).data('is-group') === 'true', name: $(this).data('name'),
    });
  });

  $('[data-attachment-kind]').on('click', function () {
    if (sessionFinished || activeConversation?.blocked_by_me) return;
    $attachmentInput.attr('accept', this.dataset.attachmentKind === 'photo' ? '.jpg,.jpeg,.png,.webp' : '.pdf,.txt,.docx,.pptx,.xlsx');
    window.bootstrap.Dropdown.getInstance(document.getElementById('chooseAttachmentBtn'))?.hide();
    $attachmentInput.trigger('click');
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
        if (window.toast) window.toast('success', response.message || 'Group created. Invitations sent.');
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

  // Group management keeps role checks on the server. The client only presents permitted actions.
  let memberSearchTimer = null;
  let managingGroupId = null;
  function groupRequest(path, method, data = {}, after = null) {
    return $.ajax({url:path, method, data, headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
      success: result => { window.toast?.('success', result.message || 'Saved.'); after?.(result); refreshConversationsList(); }, error: requestError});
  }
  function renderGroupMembersModal(info, groupId) {
    managingGroupId = groupId;
    const selfRole = info.group_role;
    const isManager = selfRole === 'owner' || selfRole === 'admin';
    const list = $('#groupMembersModalList').empty();
    list.append(`<p class="small text-body-secondary">${info.members.length} current members. Only accepted invitations grant access.</p>`);
    info.members.forEach(member => {
      const role = member.role || 'member';
      const canRemove = isManager && role !== 'owner' && (selfRole === 'owner' || role === 'member');
      const avatar = member.profile_picture ? `<img src="${escapeHtml(member.profile_picture)}" class="ph-contact-avatar" alt="">` : '<span class="ph-contact-avatar ph-contact-initials"><i class="bi bi-person"></i></span>';
      const row = $(`<div class="ph-group-member-row">${avatar}<div class="flex-grow-1 min-w-0"><a href="${escapeHtml(member.profile_url)}" class="fw-semibold text-body text-decoration-none text-break">${escapeHtml(member.name)}</a><small class="text-body-secondary d-block">${escapeHtml(role.charAt(0).toUpperCase()+role.slice(1))}</small></div></div>`);
      if (selfRole === 'owner' && role !== 'owner') {
        const $role = $('<select class="form-select form-select-sm ph-member-role-picker" aria-label="Change group role"><option value="member">Member</option><option value="admin">Admin</option><option value="owner">Transfer ownership</option></select>').val(role);
        $role.on('change', () => {
          if ($role.val() === 'owner' && !confirm('Transfer group ownership to ' + member.name + '?')) { $role.val(role); return; }
          groupRequest(`/messages/groups/${groupId}/members/${member.id}/role`, 'PATCH', {role:$role.val()}, () => {loadGroupManager(groupId); showConversationInfo();});
        });
        row.append($role);
      }
      if (canRemove) {
        const $remove = $('<button class="btn btn-sm btn-outline-danger" aria-label="Remove group member"><i class="bi bi-person-dash"></i></button>');
        $remove.on('click', async () => { const ok = window.confirmAction ? (await window.confirmAction('Remove '+member.name+' from this group?','Remove member')).isConfirmed : confirm('Remove '+member.name+'?'); if (ok) groupRequest(`/messages/conversations/${groupId}/members/${member.id}`, 'DELETE', {}, () => {loadGroupManager(groupId); showConversationInfo();}); });
        row.append($remove);
      }
      list.append(row);
    });
    if (info.pending_invitations?.length) {
      list.append('<h3 class="h6 mt-3">Pending invitations and approvals</h3>');
      info.pending_invitations.forEach(item => {
        if (!isManager && item.requested_by !== Number($('#chatOwnerId').val())) return;
        const row = $(`<div class="ph-group-member-row"><div class="flex-grow-1"><strong>${escapeHtml(item.first_name+' '+item.last_name)}</strong><small class="d-block text-body-secondary">${item.status === 'approval_requested' ? 'Awaiting admin approval' : 'Awaiting acceptance'}</small></div></div>`);
        if (isManager && item.status === 'approval_requested') {
          for (const [decision,label] of [['approve','Approve'],['reject','Reject']]) {
            const $btn = $(`<button type="button" class="btn btn-sm ${decision === 'approve' ? 'btn-primary':'btn-outline-secondary'}">${label}</button>`);
            $btn.on('click',()=>groupRequest(`/messages/group-invitations/${item.id}/decision`, 'POST', {decision},()=>loadGroupManager(groupId)));
            row.append($btn);
          }
        } else if (isManager || item.requested_by === Number($('#chatOwnerId').val())) {
          const $withdraw = $('<button type="button" class="btn btn-sm btn-outline-secondary">Withdraw</button>');
          $withdraw.on('click',()=>groupRequest(`/messages/group-invitations/${item.id}/decision`, 'POST', {decision:'withdraw'},()=>loadGroupManager(groupId)));
          row.append($withdraw);
        }
        list.append(row);
      });
    }
    $('#addMemberSearchInput').val(''); $('#addMemberSearchResults').empty();
  }
  function loadGroupManager(id) {
    $.getJSON(`/messages/conversations/${id}/info`).done(info => { renderGroupMembersModal(info, id); window.showModal('groupMembersModal'); }).fail(requestError);
  }
  $(document).on('click','#viewMembersBtn', function () { loadGroupManager($(this).data('conversation-id')); });
  $('#addMemberSearchInput').on('input',function () {
    clearTimeout(memberSearchTimer); const q=this.value.trim(), groupId=managingGroupId;
    $('#addMemberSearchResults').empty();
    if (!groupId || q.length < 3) return;
    memberSearchTimer=setTimeout(()=>{$.getJSON('/messages/member-search',{q}).done(result=>{
      if (groupId!==managingGroupId || $('#addMemberSearchInput').val().trim()!==q) return;
      (result.users||[]).forEach(u=>{
        const $row=$(`<button type="button" class="ph-contact-row"><span class="ph-contact-avatar ph-contact-initials"><i class="bi bi-person"></i></span><span class="flex-grow-1 text-start"><strong>${escapeHtml(u.name)}</strong><small class="d-block text-body-secondary">@${escapeHtml(u.username || '')}</small></span><span class="small">Invite</span></button>`);
        $row.on('click',()=>groupRequest(`/messages/conversations/${groupId}/members`,'POST',{user_id:u.id},()=>{loadGroupManager(groupId);$('#addMemberSearchInput').val('');}));
        $('#addMemberSearchResults').append($row);
      });
    }).fail(requestError);},300);
  });
  $(document).on('click','.group-invite-response',function () {
    const $btn=$(this),id=$btn.data('invitation-id');
    $btn.prop('disabled',true);
    groupRequest(`/messages/group-invitations/${id}/decision`,'POST',{decision:$btn.data('decision')},()=>{
      $(`[data-invitation-id="${id}"]`).remove(); window.location.reload();
    }).always(()=>$btn.prop('disabled',false));
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

  // Search first: never fetch or render the complete user directory.
  let directSearchTimer = null, directSearchSeq = 0;
  $('#newChatSearchInput').on('input', function () {
    clearTimeout(directSearchTimer);
    const q = this.value.trim();
    const sequence = ++directSearchSeq;
    $('#newChatSuggestions').toggleClass('d-none', q.length > 0);
    $('#newChatSearchResults').toggleClass('d-none', q.length < 3).empty();
    $('#newChatNoResults').addClass('d-none').text('');
    if (q.length < 3) { $('#newChatSearchHelp').text('Type at least 3 username characters or a full email address.'); return; }
    $('#newChatSearchHelp').text('Searching for students…');
    directSearchTimer = setTimeout(() => {
      $.getJSON('/messages/member-search', {q}).done(response => {
        if (sequence !== directSearchSeq) return;
        const people = response.users || [];
        $('#newChatSearchHelp').text(people.length ? 'Select a student to start a direct message.' : 'No matching students. Try their username or full email.');
        $('#newChatSearchResults').removeClass('d-none').empty();
        people.forEach(person => {
          const photo = person.photo_url ? `<img class="ph-contact-avatar" src="${escapeHtml(person.photo_url)}" alt="">` : '<span class="ph-contact-avatar ph-contact-initials"><i class="bi bi-person"></i></span>';
          $('#newChatSearchResults').append(`<button type="button" class="ph-contact-row start-chat-btn" data-user-id="${person.id}" data-name="${escapeHtml(person.name)}">${photo}<span class="min-w-0 flex-grow-1 text-start"><strong class="d-block text-truncate">${escapeHtml(person.name)}</strong><small class="text-body-secondary">@${escapeHtml(person.username || '')}</small></span><i class="bi bi-chat-dots" aria-hidden="true"></i></button>`);
        });
      }).fail(xhr => { if (sequence === directSearchSeq) $('#newChatSearchHelp').text('Unable to search students. Please retry.'); });
    }, 280);
  });
  $('#newChatModal').on('shown.bs.modal', () => $('#newChatSearchInput').trigger('focus'));
  $('#newChatModal, #newGroupModal').on('hidden.bs.modal', function () {
    clearTimeout(directSearchTimer); directSearchSeq++;
    $('#newChatSearchInput').val('').trigger('input');
    $('#newGroupSearchInput').val('');
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
        const container = $('#chatInfoContent').empty();
        const idSafe = Number(id);
        const makeAvatar = (src, label, isGroup) => src
          ? `<img src="${escapeHtml(src)}" alt="" class="ph-info-profile-photo">`
          : `<span class="ph-info-profile-photo ph-info-profile-fallback"><i class="bi ${isGroup ? 'bi-people-fill':'bi-person-fill'}" aria-hidden="true"></i></span>`;
        const profileName = response.group_name || conv.name;
        const src = response.is_group ? response.group_photo_url : response.other_profile_photo;
        const header = `<div class="ph-info-profile">${makeAvatar(src, profileName, response.is_group)}<h2 class="h5 mt-2 mb-1">${escapeHtml(profileName)}</h2><small class="text-body-secondary">${response.is_group ? response.members.length+' members · '+escapeHtml(response.group_role||'Member') : response.is_session ? 'Private Learn/Swap session' : (response.other_username ? '@'+escapeHtml(response.other_username) : 'Direct conversation')}</small></div>`;
        container.append(header);
        const $actions=$('<div class="ph-info-quick-actions"></div>');
        const mute= $(`<button type="button" class="ph-info-quick-action mute-group-btn" data-conversation-id="${idSafe}" data-muted="${response.is_muted}"><i class="bi ${response.is_muted ? 'bi-bell-slash':'bi-bell'}"></i><span>${response.is_muted?'Unmute':'Mute'}</span></button>`);
        $actions.append(mute, '<button type="button" class="ph-info-quick-action" id="searchChatBtn"><i class="bi bi-search"></i><span>Search</span></button>');
        if (!response.is_group && !response.is_session && response.other_user_id) {
          $actions.append(`<button type="button" class="ph-info-quick-action" id="favoriteConversationPeerBtn" data-user-id="${response.other_user_id}" aria-pressed="${response.is_favorite}"><i class="bi ${response.is_favorite ? 'bi-star-fill':'bi-star'}"></i><span>${response.is_favorite ? 'Favorited' : 'Favorite'}</span></button>`);
        }
        container.append($actions);
        if (response.is_group && !response.is_session) {
          const isManager = ['owner','admin'].includes(response.group_role);
          const members = response.members.map(user => `<div class="ph-info-person"><span class="ph-info-person-avatar">${user.profile_picture ? `<img src="${escapeHtml(user.profile_picture)}" alt="">` : '<i class="bi bi-person"></i>'}</span><span class="flex-grow-1 min-w-0"><a href="${escapeHtml(user.profile_url)}" class="text-body text-decoration-none text-break">${escapeHtml(user.name)}</a><small class="d-block text-body-secondary">${escapeHtml(user.role||'Member')}</small></span></div>`).join('');
          container.append(`<div class="ph-info-section"><div class="d-flex justify-content-between align-items-center"><h3 class="h6">Group settings</h3>${response.group_role === 'owner' ? '<span class="ph-info-role-tag">Owner</span>' : ''}</div>${isManager ? `<button type="button" class="ph-info-line-action" id="editGroupNameBtn" data-conversation-id="${idSafe}"><i class="bi bi-pencil"></i><span>Edit group name</span><i class="bi bi-chevron-right ms-auto"></i></button><button type="button" class="ph-info-line-action" id="changeGroupPhotoBtn" data-conversation-id="${idSafe}"><i class="bi bi-image"></i><span>Change group photo</span><i class="bi bi-chevron-right ms-auto"></i></button>${response.group_photo_url ? `<button type="button" class="ph-info-line-action" id="removeGroupPhotoBtn" data-conversation-id="${idSafe}"><i class="bi bi-image-alt"></i><span>Remove group photo</span></button>` : ''}` : '<p class="small text-body-secondary">Owner and admins manage group identity and memberships.</p>'}</div>`);
          container.append(`<div class="ph-info-section"><h3 class="h6">Group members <span class="text-body-secondary">(${response.members.length})</span></h3>${members}<button type="button" class="btn btn-outline-primary btn-sm mt-2 w-100" id="viewMembersBtn" data-conversation-id="${idSafe}"><i class="bi bi-person-plus me-1"></i>${isManager?'Manage members and invitations':'Request to invite a member'}</button></div>`);
          container.append(`<div class="ph-info-section"><h3 class="h6">Conversation settings</h3>${response.group_role === 'owner' ? '<p class="small text-body-secondary">Transfer ownership before leaving this group.</p>' : `<button type="button" class="ph-info-line-action text-danger leave-group-btn" data-conversation-id="${idSafe}"><i class="bi bi-box-arrow-right"></i>Leave group</button>`}</div>`);
        } else if (response.is_session) {
          container.append('<div class="ph-info-section"><h3 class="h6">Private Learn/Swap session</h3><p class="small text-body-secondary">Propose or review schedules, confirm completion, and open Activities through your session controls.</p><button type="button" class="btn btn-outline-primary btn-sm w-100" id="openInfoSessionDetails"><i class="bi bi-calendar-event me-2"></i>Open session details</button></div>');
        } else {
          if (response.other_profile_url) container.append(`<a href="${escapeHtml(response.other_profile_url)}" class="btn btn-outline-primary w-100 mb-3"><i class="bi bi-person me-2"></i>View student profile</a>`);
          container.append('<div class="ph-info-section"><h3 class="h6">Conversation settings</h3><p class="small text-body-secondary mb-0">Find older messages, manage notifications, or save this student to Favorites.</p></div>');
          const $safety=$('<div class="ph-info-section"><h3 class="h6">Safety and privacy</h3></div>');
          $safety.append(`<button type="button" class="ph-info-line-action delete-chat-btn" data-conversation-id="${idSafe}" data-name="${escapeHtml(conv.name)}"><i class="bi bi-trash"></i>Delete conversation for me</button>`);
          $safety.append(`<button type="button" class="ph-info-line-action account-report-btn" data-user-id="${response.other_user_id}"><i class="bi bi-flag"></i>Report account</button>`);
          $safety.append(`<button type="button" class="ph-info-line-action text-danger direct-block-btn" data-user-id="${response.other_user_id}" data-blocked-by-me="${response.blocked_by_me ? 1:0}"><i class="bi bi-shield-slash"></i>${response.blocked_by_me?'Unblock account':'Block account'}</button>`);
          container.append($safety);
        }
      }, error: xhr => { if (version === infoVersion) $('#chatInfoContent').text('Could not load conversation information. Please reopen the panel to retry.'); requestError(xhr); }
    });
  }
  $(document).on('click','#openInfoSessionDetails',function () { window.showModal('sessionDetailsModal'); });
  $(document).on('click','#favoriteConversationPeerBtn',function () {
    const $button=$(this);
    groupRequest('/favorite/toggle','POST',{item_id:$button.data('user-id')},()=>showConversationInfo());
  });
  $(document).on('click','#editGroupNameBtn',function () {
    $('#editGroupNameInput').val(activeConversation?.name||'');
    $('#editGroupNameForm').data('conversation-id',$(this).data('conversation-id'));
    window.showModal('editGroupNameModal');
  });
  $('#editGroupNameForm').on('submit',function (event) {
    event.preventDefault();
    const id=$(this).data('conversation-id');
    groupRequest(`/messages/groups/${id}/name`,'PATCH',{name:$('#editGroupNameInput').val()},result=>{
      window.hideModal('editGroupNameModal');
      if (activeConversation && String(activeConversation.id)===String(id)) {activeConversation.name=result.name;renderChatHeader(activeConversation);}
      showConversationInfo();
    });
  });
  $(document).on('click','#changeGroupPhotoBtn',function () { $('#groupPhotoUpload').data('conversation-id',$(this).data('conversation-id')).trigger('click'); });
  $('#groupPhotoUpload').on('change',function () {
    if (!this.files?.length) return;
    const id=$(this).data('conversation-id'),data=new FormData();
    data.append('photo',this.files[0]);
    $.ajax({url:`/messages/groups/${id}/photo`,type:'POST',data,contentType:false,processData:false,
      headers:{'X-CSRF-TOKEN':$('meta[name="csrf-token"]').attr('content')},
      success:result=>{
        window.toast?.('success',result.message);
        if (activeConversation && String(activeConversation.id) === String(id)) {
          activeConversation.group_photo_url = result.photo_url + '?v=' + Date.now();
          renderChatHeader(activeConversation);
        }
        refreshConversationsList();showConversationInfo();
      },error:requestError,
      complete:()=>{$('#groupPhotoUpload').val('');}});
  });
  $(document).on('click','#removeGroupPhotoBtn',async function () {
    const id=$(this).data('conversation-id');
    const confirmed=window.confirmAction ? (await window.confirmAction('Remove the group photo?','Remove photo')).isConfirmed : confirm('Remove group photo?');
    if (confirmed) groupRequest(`/messages/groups/${id}/photo`,'DELETE',{},()=>{
      if (activeConversation && String(activeConversation.id) === String(id)) {
        activeConversation.group_photo_url = null;
        renderChatHeader(activeConversation);
      }
      showConversationInfo();
    });
  });
  $(document).on('click', '#chatInfoBtn', function () {
    if ($('#chatInfoPanel').is(':visible')) closeDetails(); else showConversationInfo();
  });
  $('#closeChatInfoBtn').on('click', closeDetails);
  function openMessageReport(id, type, userId = id) {
    $('#messageReportForm')[0].reset();
    $('#messageReportTargetId').val(id);
    $('#messageReportTargetType').val(type);
    $('#messageReportTargetUserId').val(userId);
    window.showModal('messageReportModal');
  }
  $(document).on('click', '.message-report-btn', function () {
    openMessageReport($(this).data('message-id'), 'App\\Models\\Message', $(this).data('user-id'));
  });
  $(document).on('click', '.account-report-btn', function () {
    openMessageReport($(this).data('user-id'), 'App\\Models\\User');
  });
  $('#messageReportForm').on('submit', function (event) {
    event.preventDefault();
    const $submit = $(this).find('[type="submit"]');
    $submit.prop('disabled', true);
    $.ajax({
      url: '/reports', method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        reportable_id: $('#messageReportTargetId').val(),
        reportable_type: $('#messageReportTargetType').val(),
        reason: $('#messageReportReason').val(),
        details: $('#messageReportDetails').val(),
        block_user: $('#messageReportBlockUser').is(':checked') ? 1 : 0,
      },
      success: response => {
        const shouldBlock = $('#messageReportBlockUser').is(':checked');
        const targetUserId = Number($('#messageReportTargetUserId').val());
        window.hideModal('messageReportModal');
        window.toast?.('success', response.message);
        if (shouldBlock && activeConversation?.other_user_id === targetUserId) {
          activeConversation.blocked_by_me = true;
          activeConversation.blocked_between_us = true;
          renderChatHeader(activeConversation);
          if ($('#chatInfoPanel').is(':visible')) showConversationInfo();
        }
      },
      error: requestError,
      complete: () => $submit.prop('disabled', false),
    });
  });
  $(document).on('click', '.direct-block-btn', async function () {
    const $button = $(this);
    const userId = Number($button.attr('data-user-id'));
    const blockedByMe = String($button.attr('data-blocked-by-me')) === '1';
    const verb = blockedByMe ? 'unblock' : 'block';
    const promptText = blockedByMe ? 'Unblock this account? New direct messages will be visible. Messages sent while blocked will stay hidden.' : 'Block this account? You cannot send to them, and their new direct messages will stay hidden from you. Shared group chats are not affected.';
    const confirmed = window.confirmAction
      ? await window.confirmAction(promptText, `${blockedByMe ? 'Unblock' : 'Block'} account`)
      : {isConfirmed: window.confirm(promptText)};
    if (!confirmed.isConfirmed) return;
    $button.prop('disabled', true);
    $.ajax({
      url: `/users/${userId}/block`, method: blockedByMe ? 'DELETE' : 'POST',
      headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
      success: response => {
        window.toast?.('success', response.message);
        if (activeConversation?.other_user_id === Number(userId)) {
          activeConversation.blocked_by_me = !blockedByMe;
          activeConversation.blocked_between_us = !blockedByMe;
          renderChatHeader(activeConversation);
          if ($('#chatInfoPanel').is(':visible')) showConversationInfo();
        }
        refreshConversationsList();
      },
      error: requestError,
      complete: () => $button.prop('disabled', false),
    });
  });
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
        if (activeConversation && activeConversation.blocked_by_me !== Boolean(response.blocked_by_me)) {
          activeConversation.blocked_by_me = Boolean(response.blocked_by_me);
          renderChatHeader(activeConversation);
          if ($('#chatInfoPanel').is(':visible')) showConversationInfo();
        }
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
    $chatMessages.find('[data-message-id]').each(function () {
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
