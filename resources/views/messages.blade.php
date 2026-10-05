@extends('layouts.dashboard')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
<div class="card shadow-sm chat-wrapper">
  <div class="messaging-columns h-100">
    
    <!-- Left Side: Conversation List -->
    <div class="border-end chat-sidebar d-flex flex-column bg-body rounded-start">
      <div class="chat-column-header p-3 border-bottom d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Messages</h5>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#newChatModal">
            <i class="bi bi-chat-left-text"></i>
          </button>
          <button type="button" class="btn btn-sm btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#newGroupModal">
            <i class="bi bi-people-fill"></i>
          </button>
        </div>
      </div>
      <div class="flex-grow-1 overflow-auto" id="conversationsList"></div>
    </div>

    <!-- Right Side: Active Chat -->
    <div class="chat-main d-flex flex-column bg-body rounded-end">
      <div class="chat-column-header p-3 border-bottom d-flex justify-content-between align-items-center" id="chatHeader">
        <span class="text-muted">Select a conversation to start chatting</span>
      </div>
      <button type="button" id="backToLatestBtn" class="btn btn-sm btn-outline-primary m-2" style="display:none;">Back to latest messages</button>
      <div class="flex-grow-1 overflow-auto p-3" id="chatMessages"></div>
      <div id="typingIndicator" class="px-3 py-1 text-muted small" role="status" aria-live="polite" style="display:none;"><span id="typingNames"></span><span class="typing-dots" aria-hidden="true"><span>●</span><span>●</span><span>●</span></span></div>
      <div class="p-2 border-top" id="chatFooter" style="display:none;">

        <div id="replyPreview" class="px-3 py-2 small border-start border-3 border-primary mx-2 mb-2 rounded shadow-sm" style="display:none; position:relative;">
          <div class="fw-bold text-primary" id="replyPreviewName" style="font-size: 0.75rem;"></div>
          <div class="text-muted text-truncate" id="replyPreviewBody" style="max-width: 90%; font-size: 0.8rem;"></div>
          <button type="button" class="btn-close position-absolute top-0 end-0 m-2" style="font-size: 0.5rem;" id="cancelReplyBtn"></button>
        </div>

        <div id="attachmentPreview" class="px-2 pb-2 small text-muted" style="display:none;"></div>
        <form id="messageForm" class="d-flex align-items-center gap-2">
          <input type="hidden" id="activeConversationId" value="">
          <input type="hidden" id="replyToId" value="">
          <label class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center p-0 mb-0 flex-shrink-0" style="width:40px;height:40px;min-width:40px;min-height:40px;cursor:pointer;overflow:hidden;" title="Attach a file">
            <i class="bi bi-plus-lg"></i>
            <input type="file" id="attachmentInput" class="d-none">
          </label>
          <input type="text" id="messageInput" maxlength="2000" class="form-control rounded-pill" placeholder="Type a message...">
          <button type="submit" class="btn btn-primary rounded-pill px-4">Send</button>
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

<x-modal id="newChatModal" title="Start a Conversation">
  <!-- NEW: Search Input -->
  <div class="mb-3 px-1">
    <div class="input-group">
      <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
      <input type="text" id="newChatSearchInput" class="form-control border-start-0 ps-0" placeholder="Search peers...">
    </div>
  </div>

  <p id="newChatNoResults" class="text-muted text-center d-none" role="status">No peers found. Try another name.</p>
  <div id="newChatUserList" class="d-flex flex-column gap-2" style="max-height: 300px; overflow-y:auto;">
    @foreach($users as $user)
    <button type="button" class="btn btn-outline-secondary text-start start-chat-btn d-flex align-items-center gap-3 border-0 py-2 px-3" data-user-id="{{ $user->id }}" data-name="{{ $user->first_name }} {{ $user->last_name }}">
      
      @if($user->profile_picture)
        <img src="{{ asset('storage/' . $user->profile_picture) }}" 
          alt="{{ $user->first_name }}" 
          class="rounded-circle shadow-sm flex-shrink-0" style="width: 35px; height: 35px; object-fit: cover;">
      @else
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold shadow-sm flex-shrink-0" style="width: 35px; height: 35px; font-size: 0.9rem;">
          {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
        </div>
      @endif

      <span class="fw-medium text-body">{{ $user->first_name }} {{ $user->last_name }}</span>
    </button>
    @endforeach
  </div>
</x-modal>

<x-modal id="newGroupModal" title="Create Group Chat">
  <form id="newGroupForm">
    <div class="form-floating mb-3">
      <input type="text" class="form-control" id="groupNameInput" placeholder="Group name">
      <label for="groupNameInput">Group Name</label>
    </div>
    
    <!-- Search Input -->
    <div class="mb-2">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
        <input type="text" id="newGroupSearchInput" class="form-control border-start-0 ps-0" placeholder="Search members...">
      </div>
    </div>

    <p class="text-muted small mb-2">Select members (at least 2):</p>
    <div id="groupMembersList" class="d-flex flex-column gap-1" style="max-height: 250px; overflow-y: auto;">
      
      @foreach($users as $user)
      <div class="form-check d-flex align-items-center gap-2 p-2 rounded hover-bg-light group-member-item" data-name="{{ $user->first_name }} {{ $user->last_name }}">
        <input class="form-check-input group-member-checkbox m-0" type="checkbox" value="{{ $user->id }}" id="member{{ $user->id }}">
        <label class="form-check-label d-flex align-items-center gap-3 w-100 m-0" style="cursor: pointer;" for="member{{ $user->id }}">
          
          @if($user->profile_picture)
            <img src="{{ asset('storage/' . $user->profile_picture) }}" 
              alt="{{ $user->first_name }}" 
              class="rounded-circle shadow-sm flex-shrink-0" style="width: 30px; height: 30px; object-fit: cover;">
          @else
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white fw-bold shadow-sm flex-shrink-0" style="width: 30px; height: 30px; font-size: 0.8rem;">
              {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
            </div>
          @endif
          
          <span class="fw-medium">{{ $user->first_name }} {{ $user->last_name }}</span>
        </label>
      </div>
      @endforeach

    </div>
  </form>
  <x-slot:footer>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="button" class="btn btn-primary" id="createGroupBtn">Create Group</button>
  </x-slot:footer>
</x-modal>

<x-modal id="groupMembersModal" title="Group Members">
  <div id="groupMembersModalList"></div>
  <div class="input-group mt-3">
    <select id="addMemberSelect" class="form-select" aria-label="Add a group member">
      <option value="">Choose a peer…</option>
      @foreach($users as $user)
        <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
      @endforeach
    </select>
    <button type="button" class="btn btn-primary" id="addMemberBtn">Add member</button>
  </div>
</x-modal>

{{-- Client-rendered markup for messages.js. Slots receive escaped text or child markup. --}}
@verbatim
<script type="text/x-template" id="message-template-reactions-html-1"><button type="button" class="btn btn-sm [[slot0]] reaction-choice" data-emoji="[[slot1]]" aria-pressed="[[slot2]]" title="Toggle [[slot3]] reaction">[[slot4]] [[slot5]]</button></script>

<script type="text/x-template" id="message-template-render-attachment-1"><a href="[[slot0]]" target="_blank"><img src="[[slot1]]" class="img-fluid rounded-3 mt-1" style="max-width:220px;"></a></script>

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

      <div class="p-2 rounded-3 [[slot7]] message-bubble" style="max-width:70%; width:fit-content;">
        [[slot8]]
        [[slot9]]
        <div class="message-body">[[slot10]]</div>
        [[slot11]]
        <div class="message-reactions d-flex flex-wrap gap-1 mt-1">[[slot12]]</div>
        <small class="d-block opacity-75 mt-1" style="font-size:0.7rem;">
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

<script type="text/x-template" id="message-template-buttons-1"><button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" id="chatInfoBtn" aria-label="Conversation information" title="Conversation information"><i class="bi bi-info-circle-fill"></i></button></script>

<script type="text/x-template" id="message-template-render-chat-header-1">
      <div class="d-flex justify-content-between align-items-center w-100">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
          <button type="button" id="mobileChatBack" class="btn btn-link text-body text-decoration-none d-md-none flex-shrink-0 p-2" aria-label="Back to conversations"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
          <div class="flex-grow-1" style="min-width:0;"><span class="fw-semibold">[[slot0]]</span><small id="chatPresenceStatus" class="d-block text-muted"></small></div>
        </div>
        <div class="d-flex flex-wrap gap-1 justify-content-end">[[slot1]]</div>
      </div>
    </script>

<script type="text/x-template" id="message-template-render-conversations-1"><p class="text-muted text-center p-4">No conversations yet.</p></script>

<script type="text/x-template" id="message-template-badge-1"><span class="badge bg-danger rounded-pill ms-2">[[slot0]]</span></script>

<script type="text/x-template" id="message-template-group-icon-1"><i class="bi bi-people-fill me-1"></i></script>

<script type="text/x-template" id="message-template-render-conversations-2"><img src="/storage/[[slot0]]" alt="[[slot1]]" class="rounded-circle shadow-sm flex-shrink-0" style="width:40px; height:40px; object-fit:cover;"></script>

<script type="text/x-template" id="message-template-render-conversations-3"><div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white shadow-sm flex-shrink-0" style="width:40px; height:40px;">
                        [[slot0]]
                      </div></script>

<script type="text/x-template" id="message-template-render-conversations-5"><span class="online-dot" role="img" aria-label="Online"></span></script>

<script type="text/x-template" id="message-template-render-conversations-6"> <i class="bi bi-bell-slash" title="Muted"></i></script>

<script type="text/x-template" id="message-template-render-conversations-7"><li><button type="button" class="dropdown-item text-danger leave-group-btn" data-conversation-id="[[slot0]]"><i class="bi bi-box-arrow-right me-2"></i>Leave group</button></li></script>

<script type="text/x-template" id="message-template-render-conversations-4">
        <div class="conversation-list-row d-flex align-items-center border-bottom">
        <a href="#" class="d-flex flex-grow-1 min-w-0 align-items-center gap-2 p-3 text-decoration-none text-body conversation-item [[slot0]]"
           data-conversation-id="[[slot1]]" data-is-group="[[slot2]]" data-name="[[slot3]]">
          <span class="chat-avatar-wrap flex-shrink-0">[[slot4]][[slot5]]</span>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-center">
              <span class="[[slot6]] text-truncate">[[slot7]][[slot8]][[slot9]]</span>
              [[slot10]]
            </div>
            <small class="text-muted text-truncate d-block">[[slot11]]</small>
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

<script type="text/x-template" id="message-template-render-outgoing-1"><div class="pending-message-row d-flex flex-column align-items-end mb-2" data-pending-id="[[slot0]]"><div class="p-2 rounded-3 bg-primary text-white message-bubble" style="max-width:70%;width:fit-content">[[slot1]]<div class="message-body">[[slot2]]</div>[[slot3]]</div><div class="mt-1" style="font-size:0.7rem">[[slot4]]</div></div></script>

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
            <button type="button" class="btn mute-group-btn" data-conversation-id="[[slot3]]" data-muted="[[slot4]]"><i class="bi [[slot5]] info-action-icon"></i>[[slot6]]</button>
            <button type="button" class="btn" id="searchChatBtn"><i class="bi bi-search info-action-icon"></i>Search</button>
          </div>
          <details class="border-top py-3" open><summary class="fw-semibold">Chat info</summary><p class="small text-muted mt-2">[[slot7]]</p></details>
          <details class="border-top py-3"><summary class="fw-semibold">[[slot8]]</summary>[[slot9]][[slot10]]</details>
          [[slot11]]
        </script>
@endverbatim
@endsection
