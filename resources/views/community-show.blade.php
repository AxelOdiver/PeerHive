@extends('layouts.dashboard')

@section('title', 'Community')
@section('page-title', 'Community')

@section('content')
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
          @if(auth()->check() && auth()->id() === $community->user_id)
          <button type="button" id="editDescriptionBtn" data-id="{{ $community->id }}" class="btn btn-sm btn-primary justify-content-end d-flex align-items-center ms-auto">Edit</button>
          @endif
        </div>
        
        <div id="descriptionViewMode">
          <p class="lead mb-0" id="descriptionText">{{ $community->description }}</p>
        </div>
        @if(auth()->check() && auth()->id() === $community->user_id)
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
          @if(auth()->check() && auth()->id() === $community->user_id)
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
  <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
    <h3 class="text-muted">Welcome to the {{ $community->name }} community!</h3>
    <p class="text-muted mb-0">This is where you will add posts, files, or chat features later.</p>
  </div>
</div>

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
@endsection
