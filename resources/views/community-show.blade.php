@extends('layouts.dashboard')

@section('title', 'Community')

@section('content')
@php
  $isCommunityManager = auth()->check() && (auth()->id() === $community->user_id || auth()->user()->role === 'admin');
@endphp
<div class="container-fluid py-4">
  <a href="{{ route('community') }}" class="btn btn-primary mb-4">
    <i class="bi bi-arrow-left"></i> Back to Communities
  </a>
  
  <!-- Main content area with community details -->
  <div class="row align-items-stretch g-3 mb-4">
    <div class="col-12 col-xl-8">
      <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <h3 class="fw-bold mb-2">{{ $community->name }}</h3>
          @if($isCommunityManager)
          <button type="button" id="editDescriptionBtn" data-id="{{ $community->id }}" class="btn btn-sm btn-primary justify-content-end d-flex align-items-center ms-auto">Edit</button>
          @endif
        </div>
        
        <!-- Description section with view and edit modes -->
        <div id="descriptionViewMode">
          <p class="lead mb-0" id="descriptionText">{{ $community->description }}</p>
        </div>
        @if($isCommunityManager)
        <div id="descriptionEditMode" style="display:none;">
          <textarea name="description" 
          id="descriptionInput"
          class="form-control form-control-lg" 
          rows="8" 
          placeholder="Enter your description">{{ old('description', $community->description) }}</textarea>
          <div class="d-flex justify-content-end gap-2">
            <button type="button" id="cancelEditBtn" class="btn btn-sm btn-secondary mt-2 px-4">Cancel</button>
            <button type="button" id="saveEditBtn" class="btn btn-sm btn-primary mt-2 px-4">Save</button>
          </div>
        </div>
        @endif  
        
        <!-- Tags section -->
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
            <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2 rounded-pill">
              {{ $tag }}
            </span>
            @endforeach
            @else
            <span class="text-muted fst-italic small">No tags selected yet.</span>
            @endif
          </div>
        </div>
      </div>
    </div>
    
    <!-- Right column with community details -->
    <div class="col-12 col-xl-4">
      <div class="card border-0 shadow-sm rounded-4 p-4">
        <h3 class="fw-bold">{{ $community->name }}</h3>
        <div class="text-muted mb-3">
          <i class="bi bi-person-circle me-1"></i> Created by {{ $community->user?->first_name }} {{ $community->user?->last_name ?? 'Unknown' }}
          <span class="mx-2">|</span>
          <i class="bi bi-people-fill me-1"></i> Member Limit: {{ $community->member_limit }}
        </div>
      </div>
    </div>
  </div>
  
  <!-- Full-width section for posts, files, or chat features -->
  <div class="card border-0 shadow-sm rounded-4 mb-4 mt-4">
    <div class="card-body p-4 border-bottom border-secondary-subtle">
      <div class="d-flex gap-3 align-items-center">
        <input type="text" class="form-control rounded-pill bg-body-tertiary border-0 px-4 py-2" placeholder="Create a new post..." data-bs-toggle="modal" data-bs-target="#createPostModal" readonly style="cursor: pointer;">
        <button class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#createPostModal">Post</button>
      </div>
    </div>
    
    <!-- Posts feed -->
    <div id="postsFeed">
      @forelse($community->posts as $post)
      <div class="p-4 border-bottom border-secondary-subtle">
        <div class="d-flex align-items-center mb-2">
          <div class="bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
            <span class="text-primary fw-bold small">{{ substr($post->user->first_name, 0, 1) }}</span>
          </div>
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
              <span class="input-group-text bg-primary text-white fw-bold border-0 px-3">
                {{ substr(auth()->user()->first_name, 0, 1) }}
              </span>
              <input type="text" name="body" class="form-control bg-body-tertiary border-0 px-3" placeholder="Write a comment..." required>
              <button type="submit" class="btn btn-primary fw-bold px-4">Reply</button>
            </div>
          </form>
          
          <!-- Comments List -->
          <div class="d-flex flex-column gap-2">
            @forelse($post->comments as $comment)
            <div class="d-flex gap-2 align-items-start">
              <span class="badge bg-secondary rounded-circle p-2 mt-1">
                {{ substr($comment->user->first_name, 0, 1) }}
              </span>
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
  <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" id="savePostBtn" form="createPostForm">Post</button>
</x-slot:footer>
</x-modal>

@endsection
