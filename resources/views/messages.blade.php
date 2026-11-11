@extends('layouts.dashboard')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
<input type="hidden" id="chatOwnerId" value="{{ auth()->id() }}">
<x-modal id="sessionActivitiesModal" title="Session activities" size="lg" dialog-class="modal-dialog-centered modal-dialog-scrollable">
  <div id="sessionActivitiesError" class="text-danger mb-2" role="alert"></div>
  <div id="sessionActivitiesBody"></div>
</x-modal>
<div class="card shadow-sm chat-wrapper ph-messages-ui">
  <div class="messaging-columns h-100">
    
    <!-- Left Side: Conversation List -->
    <div class="border-end chat-sidebar ph-messaging-sidebar d-flex flex-column bg-body rounded-start">
      <div class="chat-column-header ph-chat-list-header p-3 border-bottom d-flex justify-content-between align-items-center">
        <h1 class="h5 fw-bold mb-0">Messages</h1>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm ph-messaging-icon-btn" data-bs-toggle="modal" data-bs-target="#newChatModal" aria-label="New direct message" title="New direct message">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-sm ph-messaging-icon-btn" data-bs-toggle="modal" data-bs-target="#newGroupModal" aria-label="New group chat" title="New group chat">
            <i class="bi bi-people" aria-hidden="true"></i>
          </button>
        </div>
      </div>
      <div class="ph-chat-list-tools p-3 border-bottom">
        <label class="visually-hidden" for="conversationSearchInput">Search conversations</label>
        <div class="ph-chat-searchbox"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="conversationSearchInput" class="form-control form-control-sm" maxlength="120" placeholder="Search conversations..."></div>
        <div class="ph-chat-category-filters mt-3" role="group" aria-label="Conversation filters">
          <button type="button" class="ph-chat-filter-chip is-active" data-chat-filter="all" aria-pressed="true">All</button>
          <button type="button" class="ph-chat-filter-chip" data-chat-filter="direct" aria-pressed="false">Direct</button>
          <button type="button" class="ph-chat-filter-chip" data-chat-filter="session" aria-pressed="false">Learn/Swap</button>
          <button type="button" class="ph-chat-filter-chip" data-chat-filter="group" aria-pressed="false">Groups</button>
          <button type="button" class="ph-chat-filter-chip" data-chat-filter="unread" aria-pressed="false">Unread</button>
        </div>
      </div>
      @if($groupInvitations->isNotEmpty())
        <div id="pendingGroupInvitations" class="ph-group-invite-tray p-2 border-bottom">
          <h2 class="small fw-semibold mb-2"><i class="bi bi-envelope-open me-1" aria-hidden="true"></i>Group invitations</h2>
          @foreach($groupInvitations as $invitation)
            <div class="ph-group-invite-item p-2 mb-2" data-invitation-id="{{ $invitation->id }}">
              <div class="small fw-semibold text-break">{{ $invitation->name }}</div>
              <p class="text-body-secondary small mb-2">Accept to join this group and see its messages.</p>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-primary group-invite-response" data-invitation-id="{{ $invitation->id }}" data-decision="accept">Accept</button>
                <button type="button" class="btn btn-sm btn-outline-secondary group-invite-response" data-invitation-id="{{ $invitation->id }}" data-decision="decline">Decline</button>
              </div>
            </div>
          @endforeach
        </div>
      @endif
      <div class="flex-grow-1 overflow-auto ph-chat-list" id="conversationsList" aria-live="polite"></div>
    </div>

    <!-- Right Side: Active Chat -->
    <div class="chat-main ph-messaging-main d-flex flex-column bg-body rounded-end">
      <div class="chat-column-header p-3 border-bottom d-flex justify-content-between align-items-center" id="chatHeader">
        <span class="text-body-secondary"><i class="bi bi-chat-dots me-2" aria-hidden="true"></i>Select a conversation to start chatting</span>
      </div>
      <button type="button" id="backToLatestBtn" class="btn btn-sm btn-outline-primary m-2" style="display:none;">Back to latest messages</button>

        <div class="flex-grow-1 overflow-auto p-3 ph-messages-scroller" id="chatMessages" aria-label="Conversation messages"><div class="ph-chat-empty"><i class="bi bi-chat-square-text" aria-hidden="true"></i><p class="mb-0">Choose a conversation to start chatting.</p></div></div>
      <div id="typingIndicator" class="px-3 py-1 text-muted small" role="status" aria-live="polite" style="display:none;"><span id="typingNames"></span><span class="typing-dots" aria-hidden="true"><span>●</span><span>●</span><span>●</span></span></div>
      <div class="p-3 border-top ph-message-composer" id="chatFooter" style="display:none;">
        <div id="sessionFinishedNotice" class="d-none text-center text-muted py-3" role="status">— Session finished —<div class="small mt-1">This conversation is read-only. You can still review messages, rate your teacher, and open Activities.</div></div>
        <div id="blockedConversationNotice" class="d-none text-center py-3" role="status"><strong>— You blocked this account —</strong><div class="small text-muted mt-1">Unblock to send and receive new messages. Messages sent while blocked will stay hidden.</div><button type="button" id="unblockConversationBtn" class="btn btn-outline-primary btn-sm mt-2 direct-block-btn" data-blocked-by-me="1">Unblock</button></div>

        <div id="replyPreview" class="px-3 py-2 small border-start border-3 border-primary mx-2 mb-2 rounded shadow-sm" style="display:none; position:relative;">
          <div class="fw-bold text-primary" id="replyPreviewName" style="font-size: 0.75rem;"></div>
          <div class="text-muted text-truncate" id="replyPreviewBody" style="max-width: 90%; font-size: 0.8rem;"></div>
          <button type="button" class="btn-close position-absolute top-0 end-0 m-2" style="font-size: 0.5rem;" id="cancelReplyBtn"></button>
        </div>

        <div id="attachmentPreview" class="px-2 pb-2 small text-muted" style="display:none;"></div>
        <form id="messageForm" class="d-flex align-items-center gap-2">
          <input type="hidden" id="activeConversationId" value="">
          <input type="hidden" id="replyToId" value="">
          <div class="dropup flex-shrink-0">
          <button type="button" id="chooseAttachmentBtn" class="btn btn-outline-secondary shadow-none rounded-circle d-inline-flex align-items-center justify-content-center p-0" style="width:38px;height:38px;font-size:1rem;" data-bs-toggle="dropdown" aria-expanded="false" title="Add photo or file" aria-label="Add photo or file">
            <i class="bi bi-plus-lg"></i>
          </button>
          <ul class="dropdown-menu shadow rounded-3 p-2" aria-labelledby="chooseAttachmentBtn">
            <li><button type="button" class="dropdown-item rounded-2 py-2" data-attachment-kind="photo"><i class="bi bi-image text-primary me-2"></i>Photo</button></li>
            <li><button type="button" class="dropdown-item rounded-2 py-2" data-attachment-kind="file"><i class="bi bi-paperclip text-primary me-2"></i>File</button></li>
          </ul>
          </div>
          <input type="file" id="attachmentInput" class="d-none">
          <input type="text" id="messageInput" maxlength="2000" class="form-control rounded-pill" placeholder="Type a message...">
          <button type="submit" class="btn btn-primary rounded-pill ph-send-button" aria-label="Send message"><i class="bi bi-send" aria-hidden="true"></i><span class="d-none d-sm-inline ms-2">Send</span></button>
        </form>
      </div>
    </div>
    <aside id="chatDetailsPanel" class="chat-details-panel border-start bg-body" style="display:none;" aria-label="Conversation details">
      <section id="chatInfoPanel" class="p-3 overflow-auto" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Conversation info</h5><button type="button" id="closeChatInfoBtn" class="btn btn-sm" aria-label="Close conversation info"><i class="bi bi-x-lg"></i></button></div>
        <div id="chatInfoContent"></div>
      </section>
      <section id="chatSearchBar" class="chat-search-panel" aria-label="Search conversation" style="display:none;">
        <div class="p-3">
          <div class="d-flex align-items-center gap-2 mb-3">
            <button type="button" id="closeChatSearchBtn" class="btn btn-sm rounded-circle" aria-label="Close search"><i class="bi bi-x-lg"></i></button>
            <h5 class="mb-0">Search</h5>
          </div>
          <div class="input-group">
            <span class="input-group-text rounded-start-pill"><i class="bi bi-search"></i></span>
            <input type="search" id="chatSearchInput" class="form-control rounded-end-pill" maxlength="200" placeholder="Search this chat" aria-label="Search this conversation">
          </div>
          <small id="chatSearchCount" class="text-muted" role="status"></small>
        </div>
        <div id="chatSearchResults" class="overflow-auto flex-grow-1" aria-live="polite"></div>
        <button type="button" id="moreSearchResultsBtn" class="btn btn-sm btn-light m-2" style="display:none;">More results</button>
      </section>
    </aside>
  </div>
</div>

<x-modal id="muteConversationModal" title="Mute conversation">
  <form id="muteConversationForm">
    <input type="hidden" id="muteConversationId">
    @foreach(['15' => 'For 15 minutes', '60' => 'For 1 hour', '480' => 'For 8 hours', '1440' => 'For 24 hours', 'forever' => 'Until I turn it back on'] as $duration => $label)
      <label class="d-flex align-items-center gap-3 py-3"><input class="form-check-input m-0" type="radio" name="mute_duration" value="{{ $duration }}" @checked($duration == '15')> {{ $label }}</label>
    @endforeach
    <p class="text-muted small mt-3">Messages will still arrive, but this conversation will not appear in your notification dropdown or contribute to the global and sidebar notification badges while muted. Its unread count stays visible in the chat list.</p>
  </form>
  <x-slot:footer>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" form="muteConversationForm" class="btn btn-primary" id="confirmMuteBtn">Mute</button>
  </x-slot:footer>
</x-modal>

<x-modal id="sessionDetailsModal" title="Learn/Swap session details" dialogClass="modal-dialog-centered modal-dialog-scrollable" size="lg"><div id="sessionChatPanel"></div><div class="ph-session-activities-help small text-muted mt-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Activities and ratings remain available after a session is completed, even when messaging is read-only.</div></x-modal>
<x-modal id="messageReportModal" title="Report">
  <form id="messageReportForm">
    <input type="hidden" id="messageReportTargetId">
    <input type="hidden" id="messageReportTargetType">
    <input type="hidden" id="messageReportTargetUserId">
    <div class="mb-3"><label for="messageReportReason" class="form-label">Reason</label><select id="messageReportReason" class="form-select" required><option value="" disabled selected>Select a reason…</option><option>Spam</option><option>Harassment</option><option>Inappropriate</option><option>Other</option></select></div>
    <div class="mb-3"><label for="messageReportDetails" class="form-label">Details (optional)</label><textarea id="messageReportDetails" class="form-control" rows="3" maxlength="1000"></textarea></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="messageReportBlockUser"><label class="form-check-label" for="messageReportBlockUser">Also block this account from direct messages</label></div>
  </form>
  <x-slot:footer><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger" form="messageReportForm">Submit report</button></x-slot:footer>
</x-modal>
<x-modal id="rateTeacherModal" title="Rate your teacher">
  <p>How was learning with <strong id="ratingTeacherName"></strong>?</p>
  <p class="small text-muted">Rate the teaching in this session. Your stars contribute to their teaching average. One rating per completed session.</p>
  <form id="rateTeacherForm">
    <input type="hidden" id="ratingConversationId">
    <div class="d-flex flex-wrap gap-3">@for($star=1;$star<=5;$star++)<label class="form-check-label"><input type="radio" class="form-check-input me-1" name="stars" value="{{ $star }}" required> {{ $star }} <i class="bi bi-star-fill text-warning" aria-hidden="true"></i></label>@endfor</div>
    <div class="mt-3">
      <label for="ratingComment" class="form-label">Write a review <span class="text-muted">(optional)</span></label>
      <textarea id="ratingComment" name="comment" class="form-control" rows="3" maxlength="500" placeholder="What helped you learn?" aria-describedby="ratingCommentHelp ratingVisibilityHelp"></textarea>
      <div id="ratingCommentHelp" class="form-text">Up to 500 characters. You can submit stars without a written review.</div>
    </div>
    <p id="ratingVisibilityHelp" class="small text-muted mt-3 mb-0">Your display name, stars, date, and any comment will be visible to other signed-in members on this teacher's profile.</p>
    <p id="ratingError" class="small text-danger mt-2" role="alert"></p>
  </form>
  <x-slot:footer><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Later</button><button type="submit" form="rateTeacherForm" class="btn btn-primary" id="submitRatingBtn">Submit rating</button></x-slot:footer>
</x-modal>
<x-modal id="createSessionModal" title="Create a session" dialogClass="modal-dialog-centered modal-dialog-scrollable" size="lg"><div id="sessionProposalModalBody"></div></x-modal>

<x-modal id="newChatModal" title="Start a Conversation" size="lg">
  <p class="text-body-secondary small mb-3">Find a PeerHive student and send a direct message.</p>
  <label class="form-label" for="newChatSearchInput">Search by username or email</label>
  <div class="ph-chat-searchbox mb-2"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="newChatSearchInput" class="form-control" autocomplete="off" maxlength="254" placeholder="Username prefix or full email address"></div>
  <p id="newChatSearchHelp" class="small text-body-secondary">Type at least 3 username characters or a full email address. Full-name searches are not enabled.</p>
  <p id="newChatNoResults" class="small text-body-secondary d-none" role="status"></p>
  <div id="newChatSearchResults" class="ph-contacts-results d-none" aria-live="polite"></div>
  <div id="newChatSuggestions" class="ph-chat-suggestions">
    @foreach(['Recent contacts' => $recentContacts, 'Your favorites' => $favoriteContacts] as $heading => $contacts)
      @if($contacts->isNotEmpty())
        <h3 class="h6 mt-3 mb-2">{{ $heading }}</h3>
        @foreach($contacts as $contact)
          <button type="button" class="ph-contact-row start-chat-btn" data-user-id="{{ $contact->id }}" data-name="{{ $contact->first_name }} {{ $contact->last_name }}">
            @if($contact->profile_picture_url)
              <img src="{{ $contact->profile_picture_url }}" class="ph-contact-avatar" alt="" loading="lazy">
            @else
              <span class="ph-contact-avatar ph-contact-initials">{{ strtoupper(substr($contact->first_name, 0, 1)) }}{{ strtoupper(substr($contact->last_name, 0, 1)) }}</span>
            @endif
            <span class="min-w-0 flex-grow-1 text-start"><strong class="d-block text-truncate">{{ $contact->first_name }} {{ $contact->last_name }}</strong><small class="text-body-secondary">{{ $contact->username ? '@'.$contact->username : 'PeerHive student' }}</small></span>
            <i class="bi bi-chat-dots" aria-hidden="true"></i>
          </button>
        @endforeach
      @endif
    @endforeach
    @if($recentContacts->isEmpty() && $favoriteContacts->isEmpty())
      <div class="ph-chat-list-empty"><i class="bi bi-person-plus" aria-hidden="true"></i><p class="mb-0">Find someone to chat with</p><small>Start by searching for a username or email address.</small></div>
    @endif
  </div>
</x-modal>

<x-modal id="newGroupModal" title="Create Group Chat" size="lg">
  <p class="text-body-secondary small mb-3">Start a group for your project or study circle. Selected students receive invitations and must accept before joining.</p>
  <form id="newGroupForm">
    <label for="groupNameInput" class="form-label fw-semibold">Group name</label>
    <input type="text" class="form-control mb-3" id="groupNameInput" placeholder="e.g. Web Design Study Group" maxlength="80" required>
    
    <label class="form-label" for="newGroupSearchInput">Find members by email or username</label>
    <input type="search" id="newGroupSearchInput" class="form-control mb-2" autocomplete="off" maxlength="254" placeholder="Full email address or username" aria-describedby="groupSearchHelp">
    <p class="small text-muted" id="groupSearchHelp">Search by username prefix or full email; select at least two students to invite.</p>
    <p id="groupSearchStatus" class="small text-muted" role="status" aria-live="polite">Search to find someone to add.</p>
    <div id="groupMembersList" class="d-flex flex-column gap-2 mb-3" style="max-height:200px;overflow-y:auto" aria-label="Member search results"></div>
    <h3 class="h6">Selected members (<span id="groupSelectedCount">0</span>)</h3>
    <div id="groupSelectedMembers" class="d-flex flex-column gap-2 ph-selected-group-members" aria-label="Selected members"></div>
    <template id="groupMemberResultTemplate">
      <button type="button" class="btn btn-outline-secondary text-start d-flex justify-content-between align-items-center gap-2">
        <span class="min-w-0 text-break"><strong class="member-name d-block"></strong><small class="member-username d-block"></small></span>
        <span class="member-action ph-member-add-indicator"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add</span>
      </button>
    </template>
    <template id="groupSelectedMemberTemplate">
      <div class="ph-selected-group-member p-2 d-flex justify-content-between align-items-center gap-2">
        <span class="min-w-0 text-break"><strong class="member-name d-block"></strong><small class="member-username d-block text-muted"></small></span>
        <button type="button" class="btn btn-sm btn-outline-danger" aria-label="Remove selected member"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      </div>
    </template>
  </form>
  <x-slot:footer>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="button" class="btn btn-primary" id="createGroupBtn" disabled>Send invitations &amp; create group</button>
  </x-slot:footer>
</x-modal>

<x-modal id="editGroupNameModal" title="Rename group">
  <form id="editGroupNameForm">
    <label for="editGroupNameInput" class="form-label">Group name</label>
    <input id="editGroupNameInput" class="form-control" required maxlength="80" minlength="2" autocomplete="off">
    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-primary">Save name</button>
    </div>
  </form>
</x-modal>
<input type="file" id="groupPhotoUpload" accept="image/jpeg,image/png,image/webp" class="d-none" aria-label="Upload group photo">

<x-modal id="groupMembersModal" title="Manage Group Members" size="lg">
  <div id="groupMembersModalList" class="ph-group-manage-list"></div>
  <div class="border-top pt-3 mt-3">
    <label for="addMemberSearchInput" class="form-label fw-semibold">Invite a student</label>
    <input type="search" id="addMemberSearchInput" class="form-control mb-2" autocomplete="off" placeholder="Username prefix or full email address" maxlength="254">
    <p class="small text-body-secondary mb-2">Members can request an invitation; owners and admins send invitations directly. Recipients must accept.</p>
    <div id="addMemberSearchResults" class="ph-group-member-search-results"></div>
  </div>
</x-modal>

{{-- Client-rendered markup for messages.js. Slots receive escaped text or child markup. --}}
@verbatim
<script type="text/x-template" id="message-template-reactions-html-1"><button type="button" class="btn btn-sm [[slot0]] reaction-choice" data-emoji="[[slot1]]" aria-pressed="[[slot2]]" title="Toggle [[slot3]] reaction">[[slot4]] [[slot5]]</button></script>

<script type="text/x-template" id="message-template-render-attachment-1"><a href="[[slot0]]" target="_blank" rel="noopener noreferrer" class="ph-chat-image-attachment"><img src="[[slot1]]" alt="Image attachment" class="img-fluid rounded-3 mt-1" style="max-width:260px;"></a></script>

<script type="text/x-template" id="message-template-render-attachment-2"><a href="[[slot0]]" target="_blank" class="d-flex align-items-center gap-2 mt-1 text-decoration-none [[slot1]]">
      <i class="bi bi-file-earmark-arrow-down fs-5"></i> <span>[[slot2]]</span>
    </a></script>

<script type="text/x-template" id="message-template-message-html-1">
            <div class="message-row d-flex [[slot0]] align-items-center mb-2"
                 data-message-id="[[slot1]]">
                <div class="deleted-message-bubble">
                    This message was unsent
                </div>
            </div>
        </script>

<script type="text/x-template" id="message-template-sender-label-1"><div class="small fw-semibold mb-1">[[slot0]]</div></script>

<script type="text/x-template" id="message-template-body-html-1"><div></script>

<script type="text/x-template" id="message-template-edited-tag-1"><span class="opacity-75 edited-tag" style="font-size:0.7rem;"> (edited)</span></script>

<script type="text/x-template" id="message-template-reply-preview-html-1">
      <div class="mb-2 p-2 rounded bg-white bg-opacity-25 border-start border-3 border-[[slot0]] shadow-sm">
        <div class="d-flex align-items-center gap-1 fw-bold mb-1" style="font-size: 0.75rem;">
          <i class="bi bi-reply-fill"></i> Replying to [[slot1]]
        </div>
        <div class="text-truncate opacity-100 fst-italic" style="font-size: 0.75rem; max-width: 250px;">
          "[[slot2]]"
        </div>
      </div>
    </script>

<script type="text/x-template" id="message-template-kebab-html-2"><li><button type="button" class="dropdown-item edit-message-btn" data-message-id="[[slot0]]"><i class="bi bi-pencil-fill me-2"></i>Edit</button></li>
          <li><button type="button" class="dropdown-item text-danger unsend-message-btn" data-message-id="[[slot1]]"><i class="bi bi-trash-fill me-2"></i>Unsend for everyone</button></li></script>

<script type="text/x-template" id="message-template-kebab-html-1">
      <div class="dropdown">
        <button type="button" class="toolbar-btn" data-bs-toggle="dropdown" aria-expanded="false" title="More">
          <i class="bi bi-three-dots-vertical" style="font-size:0.85rem;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          [[slot0]]
          <li><button type="button" class="dropdown-item text-danger delete-message-for-me" data-message-id="[[slot1]]"><i class="bi bi-trash me-2"></i>Delete for me</button></li>
          [[slot2]]
        </ul>
      </div>
    </script>

<script type="text/x-template" id="message-template-message-html-3">
        <div class="message-hover-toolbar me-2">
          [[slot0]]
        </div>
      </script>

<script type="text/x-template" id="message-template-message-html-4"><div class="message-read-receipt small mt-1" aria-label="Read receipt"></div></script>

<script type="text/x-template" id="message-template-message-html-5">
        <div class="message-hover-toolbar ms-2">
          [[slot0]]
        </div>
      </script>

<script type="text/x-template" id="message-template-message-html-2">
    <div class="message-row d-flex [[slot0]] align-items-center mb-2" data-message-id="[[slot1]]" data-client-message-id="[[slot2]]" data-is-mine="[[slot3]]" data-sender="[[slot4]]" data-body="[[slot5]]">

      [[slot6]]

      <div class="p-2 rounded-3 [[slot7]] message-bubble ph-message-bubble" style="max-width:75%; width:fit-content;">
        [[slot8]]
        [[slot9]]
        <div class="message-body">[[slot10]]</div>
        [[slot11]]
        <div class="message-reactions d-flex flex-wrap gap-1 mt-1">[[slot12]]</div>
        <small class="d-block opacity-75 mt-1 ph-message-time" style="font-size:0.7rem;">
          [[slot13]][[slot14]]
        </small>
        [[slot15]]
      </div>

      [[slot16]]
    </div>
  </script>

<script type="text/x-template" id="message-template-toolbar-buttons-html-2"><button type="button" class="btn btn-sm reaction-choice rounded-circle" data-emoji="[[slot0]]" aria-label="React with [[slot1]]">[[slot2]]</button></script>

<script type="text/x-template" id="message-template-toolbar-buttons-html-1">
      <button type="button" class="toolbar-btn reply-message-btn" title="Reply"><i class="bi bi-reply-fill" style="font-size:0.8rem;"></i></button>
      <div class="dropdown">
        <button type="button" class="toolbar-btn" data-bs-toggle="dropdown" aria-expanded="false" title="React" aria-label="React to message"><i class="bi bi-emoji-smile-fill"></i></button>
        <div class="dropdown-menu reaction-picker shadow rounded-pill p-1" role="group" aria-label="Choose a reaction">
          <div class="d-flex">[[slot0]]</div>
        </div>
      </div>
      [[slot1]]
    </script>

<script type="text/x-template" id="message-template-load-conversation-1"><p class="text-muted text-center p-3">[[slot0]]</p></script>

<script type="text/x-template" id="message-template-buttons-1"><button type="button" class="btn border-0 bg-transparent text-body-secondary shadow-none d-inline-flex align-items-center justify-content-center p-0" style="width:32px;height:32px;" id="chatInfoBtn" aria-label="Conversation info" title="Conversation info"><i class="bi bi-info-circle-fill" style="font-size:1.5rem;line-height:1;" aria-hidden="true"></i></button></script>

<script type="text/x-template" id="message-template-render-chat-header-1">
      <div class="d-flex justify-content-between align-items-center w-100 gap-2">
        <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
          <button type="button" id="mobileChatBack" class="btn btn-link text-body text-decoration-none d-md-none flex-shrink-0 p-2" aria-label="Back to conversations"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
          <span class="ph-chat-header-avatar" aria-hidden="true">[[slot2]]</span>
          <div class="flex-grow-1 ph-chat-header-identity" style="min-width:0;"><span class="fw-semibold">[[slot0]]</span><small id="chatPresenceStatus" class="d-block text-muted">[[slot3]]</small></div>
        </div>
        <div class="d-flex gap-2 align-items-center justify-content-end ph-chat-header-actions">
          <button type="button" id="createSessionBtn" class="btn btn-sm btn-primary ph-session-header-btn d-none" title="Propose a private session schedule" aria-label="Propose session schedule"><i class="bi bi-calendar-plus" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Propose schedule</span></button>
          <button type="button" id="sessionDetailsBtn" class="btn btn-sm btn-outline-primary ph-session-header-btn d-none" title="Session details and actions" aria-label="Open session details and actions"><i class="bi bi-calendar-event" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Session details</span></button>
          [[slot1]]
        </div>
      </div>
    </script>

<script type="text/x-template" id="message-template-render-conversations-1"><div class="ph-chat-list-empty"><i class="bi bi-chat-dots" aria-hidden="true"></i><p class="mb-0">No conversations yet.</p><small>Start by messaging another student.</small></div></script>

<script type="text/x-template" id="message-template-badge-1"><span class="badge rounded-pill ph-chat-unread-badge ms-2" aria-label="[[slot0]] unread messages">[[slot0]]</span></script>

<script type="text/x-template" id="message-template-group-icon-1"><i class="bi bi-people-fill me-1"></i></script>

<script type="text/x-template" id="message-template-render-conversations-2"><img src="/storage/[[slot0]]" alt="[[slot1]]" class="rounded-circle shadow-sm flex-shrink-0" style="width:40px; height:40px; object-fit:cover;"></script>

<script type="text/x-template" id="message-template-render-conversations-3"><div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white shadow-sm flex-shrink-0" style="width:40px; height:40px;">
                        [[slot0]]
                      </div></script>

<script type="text/x-template" id="message-template-render-conversations-5"><span class="online-dot" role="img" aria-label="Online"></span></script>

<script type="text/x-template" id="message-template-render-conversations-6"> <i class="bi bi-bell-slash" title="Muted"></i></script>

<script type="text/x-template" id="message-template-render-conversations-7"><li><button type="button" class="dropdown-item text-danger leave-group-btn" data-conversation-id="[[slot0]]"><i class="bi bi-box-arrow-right me-2"></i>Leave group</button></li></script>

<script type="text/x-template" id="message-template-render-conversations-4">
        <div class="conversation-list-row d-flex align-items-center">
        <a href="#" class="d-flex flex-grow-1 min-w-0 align-items-center gap-2 p-3 text-decoration-none text-body conversation-item [[slot0]]"
           data-conversation-id="[[slot1]]" data-is-group="[[slot2]]" data-name="[[slot3]]">
          <span class="chat-avatar-wrap flex-shrink-0">[[slot4]][[slot5]]</span>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-center gap-1">
              <span class="[[slot6]] text-truncate ph-conversation-name">[[slot7]][[slot8]][[slot9]]</span>
              <time class="ph-conversation-time">[[slot20]]</time>
            </div>
            <div class="d-flex align-items-center gap-1 mt-1"><span class="ph-conversation-kind">[[slot19]]</span>[[slot10]]</div>
            <small class="text-muted text-truncate d-block mt-1">[[slot11]]</small>
          </div>
        </a>
        <div class="dropdown pe-2">
          <button type="button" class="btn btn-sm rounded-circle conversation-options" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-label="Options for [[slot12]]"><i class="bi bi-three-dots"></i></button>
          <ul class="dropdown-menu dropdown-menu-end shadow rounded-4">
            <li><button type="button" class="dropdown-item mute-group-btn" data-conversation-id="[[slot13]]" data-muted="[[slot14]]"><i class="bi bi-bell-slash me-2"></i>[[slot15]]</button></li>
            [[slot16]]
            <li><button type="button" class="dropdown-item text-danger delete-chat-btn" data-conversation-id="[[slot17]]" data-name="[[slot18]]"><i class="bi bi-trash me-2"></i>Delete for me</button></li>
          </ul>
        </div>
        </div>
      </script>

<script type="text/x-template" id="message-template-fragment-1"><i class="bi bi-paperclip me-1"></i>[[slot0]] <a href="#" id="clearAttachmentBtn" class="ms-2 text-danger">Remove</a></script>

<script type="text/x-template" id="message-template-status-1"><button type="button" class="btn btn-link text-danger p-0 small retry-outgoing" data-id="[[slot0]]"><i class="bi bi-exclamation-circle me-1"></i>Not sent · Retry</button></script>

<script type="text/x-template" id="message-template-status-2"><span class="text-body-secondary" role="status" aria-label="Sending">Sending…</span></script>

<script type="text/x-template" id="message-template-render-outgoing-2"><div class="small border-start border-3 ps-2 mb-2 opacity-75">[[slot0]]</div></script>

<script type="text/x-template" id="message-template-render-outgoing-3"><div class="small mt-1"><i class="bi bi-paperclip"></i> [[slot0]]</div></script>

<script type="text/x-template" id="message-template-render-outgoing-1"><div class="pending-message-row d-flex flex-column align-items-end mb-2" data-pending-id="[[slot0]]"><div class="p-2 rounded-3 bg-primary text-white message-bubble ph-message-bubble" style="max-width:75%;width:fit-content">[[slot1]]<div class="message-body">[[slot2]]</div>[[slot3]]</div><div class="mt-1" style="font-size:0.7rem">[[slot4]]</div></div></script>

<script type="text/x-template" id="message-template-fragment-2">
            <div class="d-flex justify-content-between align-items-center py-1">
              <span>[[slot0]]</span>
              <button type="button" class="btn btn-sm btn-outline-danger remove-member-btn" data-user-id="[[slot1]]" data-conversation-id="[[slot2]]">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </script>

<script type="text/x-template" id="message-template-fragment-3">
      <div class="message-edit-controls d-flex gap-1 align-items-center">
        <input type="text" class="form-control form-control-sm edit-message-input" value="[[slot0]]">
        <button type="button" class="btn btn-sm btn-light save-edit-btn" data-message-id="[[slot1]]"><i class="bi bi-check-lg"></i></button>
        <button type="button" class="btn btn-sm btn-light cancel-edit-btn" data-original-text="[[slot2]]"><i class="bi bi-x-lg"></i></button>
      </div>
    </script>

<script type="text/x-template" id="message-template-fragment-4"><span class="edited-tag"> (edited)</span></script>

<script type="text/x-template" id="message-template-fragment-5">
              <div class="message-row d-flex justify-content-end align-items-center mb-2"
                  data-message-id="[[slot0]]">
                  <div class="deleted-message-bubble">
                      You deleted a message
                  </div>
              </div>
          </script>

<script type="text/x-template" id="message-template-search-messages-1"><button type="button" class="chat-search-result btn w-100 text-start d-flex gap-2 p-3 rounded-0" data-message-id="[[slot0]]"><span class="search-avatar bg-secondary-subtle rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"><i class="bi bi-person-fill"></i></span><span class="min-w-0"><strong class="d-block">[[slot1]]</strong><span class="d-block text-truncate">[[slot2]]</span><small class="text-muted">[[slot3]]</small></span></button></script>

<script type="text/x-template" id="message-template-search-messages-2"><p class="text-muted p-3">No matching messages.</p></script>

<script type="text/x-template" id="message-template-show-conversation-info-1"><p class="text-muted">Loading conversation…</p></script>

<script type="text/x-template" id="message-template-members-2"><img class="info-member-avatar rounded-circle" src="[[slot0]]" alt=""></script>

<script type="text/x-template" id="message-template-members-3"><i class="bi bi-person-circle fs-4"></i></script>

<script type="text/x-template" id="message-template-members-1"><a class="d-flex align-items-center gap-2 py-2 text-body text-decoration-none" href="[[slot0]]">[[slot1]]<span>[[slot2]]</span></a></script>

<script type="text/x-template" id="message-template-show-conversation-info-3"><button class="btn btn-sm btn-outline-secondary mt-2" id="viewMembersBtn" data-conversation-id="[[slot0]]">Manage members</button></script>

<script type="text/x-template" id="message-template-show-conversation-info-4"><details class="border-top py-3"><summary class="fw-semibold">Conversation settings</summary><button type="button" class="btn text-danger leave-group-btn mt-2" data-conversation-id="[[slot0]]">Leave group</button></details></script>

<script type="text/x-template" id="message-template-show-conversation-info-2">
          <div class="text-center mb-4"><div class="info-conversation-avatar rounded-circle bg-primary-subtle text-primary mx-auto mb-3"><i class="bi [[slot0]]"></i></div><h5>[[slot1]]</h5><p class="text-muted small">[[slot2]]</p></div>
          <div class="d-flex justify-content-center gap-4 mb-4">
            <button type="button" class="btn shadow-none mute-group-btn" data-conversation-id="[[slot3]]" data-muted="[[slot4]]"><i class="bi [[slot5]] info-action-icon"></i>[[slot6]]</button>
            <button type="button" class="btn shadow-none" id="searchChatBtn"><i class="bi bi-search info-action-icon"></i>Search</button>
          </div>
          <details class="border-top py-3" open><summary class="fw-semibold">Chat info</summary><p class="small text-muted mt-2">[[slot7]]</p></details>
          <details class="border-top py-3"><summary class="fw-semibold">[[slot8]]</summary>[[slot9]][[slot10]]</details>
          [[slot11]]
        </script>
@endverbatim
@endsection
