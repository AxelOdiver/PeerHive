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
@endsection