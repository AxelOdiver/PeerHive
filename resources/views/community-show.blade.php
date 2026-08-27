@extends('layouts.dashboard')

@section('title', 'Community')

@section('content')
@php
$isCommunityManager = auth()->check() && (auth()->id() === $community->user_id || auth()->user()->role === 'admin');
$isMember = auth()->check() && $community->members->contains('id', auth()->id());
@endphp
<div class="container-fluid py-1">
  <a href="{{ route('community') }}" class="btn btn-primary mb-4">
    <i class="bi bi-arrow-left"></i> Back to Communities
  </a>
  
  <!-- Main content area with community details -->
  <div class="row align-items-stretch g-3 mb-4">
    
    <!-- TOP LEFT: Community Description -->
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <h3 class="fw-bold mb-2">{{ $community->name }}</h3>
          @if($isCommunityManager)
          <button type="button" id="editDescriptionBtn" data-id="{{ $community->id }}" class="btn btn-sm btn-primary justify-content-end d-flex align-items-center ms-auto">Edit</button>
          @endif
        </div>
        
        <div id="descriptionViewMode">
          <p class="lead mb-0" id="descriptionText">{{ $community->description }}</p>
        </div>
        
        @if($isCommunityManager)
        <div id="descriptionEditMode" style="display:none;">
          <textarea name="description" id="descriptionInput" class="form-control form-control-lg" rows="8" placeholder="Enter your description">{{ old('description', $community->description) }}</textarea>
          <div class="d-flex justify-content-end gap-2">
            <button type="button" id="cancelEditBtn" class="btn btn-sm btn-secondary mt-2 px-4">Cancel</button>
            <button type="button" id="saveEditBtn" class="btn btn-sm btn-primary mt-2 px-4">Save</button>
          </div>
        </div>
        @endif  
        
        <div class="d-flex align-items-center mt-2 pt-2 flex-wrap gap-2">
          <span class="text-muted small me-1">Tags:</span>
          @if($isCommunityManager)
          <button class="btn btn-sm btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#editTagsModal">
            <i class="bi bi-pencil-fill me-1"></i> Edit Tags
          </button>
          @endif
          <div class="d-flex flex-wrap gap-2">
            @if($community->tags && count($community->tags) > 0)
            @foreach($community->tags as $tag)
            <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2 rounded-pill">{{ $tag }}</span>
            @endforeach
            @else
            <span class="text-muted fst-italic small">No tags selected yet.</span>
            @endif
          </div>
        </div>
      </div>
    </div>
    
    <!-- TOP RIGHT: Community Info -->
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h3 class="fw-bold mb-0">{{ $community->name }}</h3>
          
          <!-- Only show to members who are NOT the creator -->
          @if($isMember && auth()->id() !== $community->user_id)
          <button class="btn btn-sm btn-danger leave-community-btn" 
          data-url="{{ route('community.leave', $community->id) }}">
          <i class="bi bi-box-arrow-right"></i> Leave
        </button>
        @endif
      </div>
      
      <div class="text-muted mb-3">
        <i class="bi bi-person-circle me-1"></i> Created by {{ $community->user?->first_name }} {{ $community->user?->last_name ?? 'Unknown' }}
        <span class="mx-2">|</span>
        <i class="bi bi-people-fill me-1"></i> Member Limit: {{ $community->member_limit }}
      </div>
      <div class="mt-auto pt-2">
        <div class="d-flex flex-wrap gap-2 align-items-start mb-1">
          <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2 rounded-pill">
            <i class="bi bi-book-half me-1"></i> {{ $community->subject }}
          </span>
          @if($community->visibility === 'private')
          <span class="badge bg-danger text-white rounded-pill px-3 py-2">
            <i class="bi bi-lock-fill me-1"></i> Private
          </span>
          @else
          <span class="badge bg-success text-white rounded-pill px-3 py-2">
            <i class="bi bi-globe me-1"></i> Public
          </span>
          @endif
        </div>
      </div>
    </div>
  </div>
  
  <!-- BOTTOM LEFT: Members List -->
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-people me-2"></i> Members ({{ $community->members->count() ?? 0 }})</h5>
      
      <div class="d-flex flex-column gap-2 pe-1" style="max-height: 120px; overflow-y: auto;">
        @forelse($community->members->sortByDesc(fn($m) => $m->id === $community->user_id) as $member)
        <div class="d-flex justify-content-between align-items-center p-2 border border-secondary-subtle rounded-3">
          <div class="d-flex align-items-center">
            @if($member->profile_picture)
            <img src="{{ asset('storage/' . $member->profile_picture) }}" 
            alt="{{ $member->first_name }}" 
            class="rounded-circle me-2 object-fit-cover shadow-sm" 
            style="width: 32px; height: 32px;">
            @else
            <!-- Fallback to the first letter -->
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
              <span class="text-light fw-bold small">{{ substr($member->first_name, 0, 1) }}</span>
            </div>
            @endif
            <span class="fw-medium small text-truncate" style="max-width: 120px;">
              {{ $member->first_name }} {{ $member->last_name }}
            </span>
            @if($member->id === $community->user_id)
            <small class="text-muted mb-2 mt-2 ps-2 d-block">
              <i class="bi bi-person-circle me-1"></i>Creator
            </small>
            @endif
          </div>
          @if($isCommunityManager && $member->id !== $community->user_id)
          <button class="btn btn-sm btn-danger remove-member-btn px-2 py-1" 
          data-url="{{ route('community.removeMember', ['community' => $community->id, 'user' => $member->id]) }}">
          <i class="bi bi-person-x"></i>
        </button>
        @endif
      </div>
      @empty
      <p class="text-muted small fst-italic mb-0 text-center py-2">No members have joined yet.</p>
      @endforelse
    </div>
  </div>
</div>

<!-- BOTTOM RIGHT: Invite Peers -->
@if($isCommunityManager)
<div class="col-12 col-lg-4">
  <div class="card border-0 shadow-sm rounded-4 p-4 h-100" id="inviteSearchWrapper">
    <h5 class="fw-bold mb-3"><i class="bi bi-person-plus-fill me-2"></i>Invite Peers</h5>
    
    <div class="d-flex flex-column h-100">
      <form action="{{ route('invite.send') }}" method="POST" class="mt-auto">
        @csrf
        <input type="hidden" name="community_id" value="{{ $community->id }}">
        <input type="hidden" name="user_id" id="inviteUserId" required>
        
        <div class="mb-3 position-relative">
          <label for="inviteSearchInput" class="form-label small text-muted">Search Student</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control border-start-0 ps-2" id="inviteSearchInput" placeholder="Search students..." autocomplete="off">
          </div>
          <div id="inviteDropdown" class="dropdown-menu w-100 shadow-sm" style="display: none; position: absolute; top: 100%; z-index: 1050;"></div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill">Send Invite</button>
      </form>
    </div>
  </div>
</div>
@endif
</div>

<!-- Full-width section for posts, files, or chat features -->
<div class="card border-0 shadow-sm rounded-4 mb-4 mt-4">
  <div class="card-body p-4 border-bottom border-secondary-subtle">
    <div class="d-flex gap-3 align-items-center">
      <input type="text" class="form-control rounded-pill bg-body-tertiary border-0 px-4 py-2" placeholder="Create a new post..." data-bs-toggle="modal" data-bs-target="#createPostModal" readonly style="cursor: pointer;">
      <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createPostModal">Post</button>
    </div>
  </div>
  
  <!-- Posts feed -->
  <div id="postsFeed">
    @forelse($community->posts as $post)
    <div class="p-4 border-bottom border-secondary-subtle">
      <div class="d-flex align-items-center mb-2">
        @if($post->user->profile_picture)
          <img src="{{ asset('storage/' . $post->user->profile_picture) }}" 
            alt="{{ $post->user->first_name }}" 
            class="rounded-circle me-2 object-fit-cover shadow-sm" 
            style="width: 28px; height: 28px;">
        @else
        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
          <span class="text-light fw-bold small">{{ substr($post->user->first_name, 0, 1) }}</span>
        </div>
        @endif
        <small class="text-muted fw-medium me-2">{{ $post->user->first_name }} {{ $post->user->last_name }}</small>
        <small class="text-muted">• {{ $post->created_at->diffForHumans() }}</small>
      </div>
      
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h5 class="fw-bold mb-0 text-body-emphasis">{{ $post->title }}</h5>
        
        <!-- Post Delete Button (post owner or admin) -->
        @if(auth()->check() && (auth()->id() === $post->user_id || auth()->user()->role === 'admin'))
        <button type="button" class="btn btn-sm btn-danger top-0 end-0 text-decoration-none delete-post-btn" title="Delete Post" data-url="{{ route('posts.destroy', $post->id) }}">
          <i class="bi bi-trash"></i>
        </button>
        @endif
      </div>
      
      <p class="text-muted mb-3">{{ str()->limit($post->body, 250) }}</p>
      <div class="mt-auto">
        <button class="btn btn-swap btn-sm rounded-pill fw-medium text-muted px-3" data-bs-toggle="collapse" data-bs-target="#commentsSection{{ $post->id }}">
          <i class="bi bi-chat-square-text me-1"></i> 
          {{ $post->comments->count() }} Comments
        </button>
      </div>
      <div class="collapse mt-3 pt-3 border-top" id="commentsSection{{ $post->id }}">
        <form action="{{ route('comments.store', $post->id) }}" method="POST" class="mb-3">
          @csrf
          <div class="input-group input-group-sm shadow-sm rounded-pill overflow-hidden">
            <input type="text" name="body" class="form-control bg-body-tertiary border-0 px-3" placeholder="Write a comment..." required>
            <button type="submit" class="btn btn-primary fw-bold px-4">Reply</button>
          </div>
        </form>
        
        <!-- Comments List -->
        <div class="d-flex flex-column gap-2">
          @forelse($post->comments as $comment)
          <div class="d-flex gap-2 align-items-start">
            @if($comment->user->profile_picture)
              <img src="{{ asset('storage/' . $comment->user->profile_picture) }}" 
                alt="{{ $comment->user->first_name }}" 
                class="rounded-circle mt-1 object-fit-cover shadow-sm" 
                style="width: 28px; height: 28px;">
            @else
            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mt-1 text-white fw-bold small" style="width: 28px; height: 28px;">
              {{ substr($comment->user->first_name, 0, 1) }}
            </div>
            @endif
            <div class="bg-body-tertiary px-3 py-2 rounded-4 w-100 shadow-sm">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <div>
                  <span class="fw-bold small">{{ $comment->user->first_name }}</span>
                  <small class="text-muted ms-2">{{ $comment->created_at->diffForHumans() }}</small>
                </div>
                
                <!-- Comment Delete Button (only for comment owner) -->
                @if(auth()->check() && auth()->id() === $comment->user_id)
                <button type="button" class="btn btn-sm btn-danger top-0 end-0 text-decoration-none delete-comment-btn" title="Delete Comment" data-url="{{ route('comments.destroy', $comment->id) }}">
                  <i class="bi bi-trash"></i>
                </button>
                @endif
              </div>
              <p class="mb-0 small">{{ $comment->body }}</p>
            </div>
          </div>
          @empty
          <p class="text-muted small fst-italic mb-0 text-center">No comments yet.</p>
          @endforelse
        </div>
      </div> 
    </div> 
    @empty
    <div class="p-5 text-center text-muted">
      <i class="bi bi-chat-square-dots fs-1 mb-3 text-secondary opacity-50"></i>
      <h5 class="fw-bold">No posts yet</h5>
      <p>Be the first to start a discussion in this community!</p>
    </div>
    @endforelse
  </div> 
</div>

<!-- Modal for editing community tags -->
<x-modal id="editTagsModal" title="Edit Community Tags">
  <form id="editTagsForm">
    @csrf
    @method('PUT')
    <input type="hidden" id="editTagsCommunityId" value="{{ $community->id }}">
    <p class="text-muted small mb-3">Select the tags that best describe your community.</p>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <input type="checkbox" class="btn-check" id="editTagBeginner" name="tags[]" value="Beginner Friendly" {{ collect($community->tags)->contains('Beginner Friendly') ? 'checked' : '' }}>
      <label class="btn btn-outline-secondary rounded-pill btn-sm" for="editTagBeginner">Beginner Friendly</label>
      
      <input type="checkbox" class="btn-check" id="editTagStudy" name="tags[]" value="Study Group" {{ collect($community->tags)->contains('Study Group') ? 'checked' : '' }}>
      <label class="btn btn-outline-secondary rounded-pill btn-sm" for="editTagStudy">Study Group</label>
      
      <input type="checkbox" class="btn-check" id="editTagProject" name="tags[]" value="Project Collab" {{ collect($community->tags)->contains('Project Collab') ? 'checked' : '' }}>
      <label class="btn btn-outline-secondary rounded-pill btn-sm" for="editTagProject">Project Collab</label>
      
      <input type="checkbox" class="btn-check" id="editTagFast" name="tags[]" value="Fast Paced" {{ collect($community->tags)->contains('Fast Paced') ? 'checked' : '' }}>
      <label class="btn btn-outline-secondary rounded-pill btn-sm" for="editTagFast">Fast Paced</label>
    </div>
  </form>
  
  <x-slot:footer>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button type="submit" class="btn btn-primary" id="saveTagsBtn" form="editTagsForm">Save Tags</button>
</x-slot:footer>
</x-modal>

<!-- Modals for creating posts -->
<x-modal id="createPostModal" title="Create a Post">
  <form id="createPostForm">
    @csrf
    <input type="hidden" id="communityIdForPost" value="{{ $community->id }}">
    
    <div class="form-floating mb-3 mt-3">
      <input type="text" class="form-control" id="postTitle" name="title" placeholder="Post Title" required>
      <label for="postTitle">Title <span class="text-danger">*</span></label>
      <div class="invalid-feedback" data-error-for="title"></div>
    </div>
    
    <div class="form-floating mb-3">
      <textarea class="form-control" id="postBody" name="body" placeholder="What are your thoughts?" style="height: 150px" required></textarea>
      <label for="postBody">Body <span class="text-danger">*</span></label>
      <div class="invalid-feedback" data-error-for="body"></div>
    </div>
  </form>
  <x-slot:footer>
  <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
  <button type="submit" class="btn btn-primary rounded-pill px-4" id="savePostBtn" form="createPostForm">Post</button>
</x-slot:footer>
</x-modal>

@endsection
