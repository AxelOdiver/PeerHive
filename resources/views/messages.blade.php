@extends('layouts.dashboard')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
<div class="card shadow-sm" style="height: 75vh;">
  <div class="row g-0 h-100">
    <div class="col-4 border-end h-100 d-flex flex-column">
      <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
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

    <div class="col-8 h-100 d-flex flex-column">
      <div class="p-3 border-bottom d-flex justify-content-between align-items-center" id="chatHeader">
        <span class="text-muted">Select a conversation to start chatting</span>
      </div>
      <div class="flex-grow-1 overflow-auto p-3" id="chatMessages"></div>
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
          <input type="text" id="messageInput" class="form-control rounded-pill" placeholder="Type a message...">
          <button type="submit" class="btn btn-primary rounded-pill px-4">Send</button>
        </form>
      </div>
    </div>
  </div>
</div>

<x-modal id="newChatModal" title="Start a Conversation">
  
  <!-- NEW: Search Input -->
  <div class="mb-3 px-1">
    <div class="input-group">
      <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
      <input type="text" id="newChatSearchInput" class="form-control border-start-0 ps-0" placeholder="Search peers...">
    </div>
  </div>

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
    <div class="form-floating mb-3">s
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
@endsection