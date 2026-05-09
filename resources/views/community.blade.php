@extends('layouts.dashboard')

@section('title', 'Community')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
  <h2 class="fw-bold">Community</h2>
  <button class="btn btn-primary create-community-btn">
    <i class="bi bi-plus-lg me-1"></i>
    Create Community
  </button>
</div>

<div class="row">
  @forelse($communities as $community)
  @php
    $isCreator = auth()->check() && auth()->id() === $community->user_id;
    $isAdmin = auth()->check() && auth()->user()->role === 'admin';
    $isMember = $isCreator || $community->members->contains('id', auth()->id());
    $canDeleteCommunity = $isCreator || $isAdmin;
  @endphp
  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100 position-relative">
      @if($canDeleteCommunity)
      <div class="position-absolute top-0 end-0 m-3">
        <button class="btn btn-sm btn-danger rounded-2 delete-community-btn" data-id="{{ $community->id }}" title="Delete Community">
          <i class="bi bi-trash"></i>
        </button>
      </div>
      @endif
      <h3 class="fw-bold mb-2 pe-5">{{ $community->name }}</h3>
      <div class="text-muted small mb-2 fw-medium">
        <i class="bi bi-person-circle me-1"></i> 
        Created by: {{ $community->user->first_name }} {{ $community->user->last_name ?? 'unknown' }}
      </div>
      <p class="text-muted mb-2">{{ str()->limit($community->description ?? 'No description available.', 150) }}</p>
      
      <div class="mt-auto pt-2">
        <div class="d-flex flex-wrap gap-2 align-items-start mb-3">
          <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2 rounded-pill">
            <i class="bi bi-book-half me-1"></i> {{ $community->subject }}
          </span>
        </div>
        
        <span class="badge bg-secondary mb-2 p-2"><i class="bi bi-people-fill me-1"></i>Limit: {{ $community->member_limit }} members</span>
        @if($isMember || $isAdmin)
          <a href="{{ route('community.show', $community->id) }}" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 d-block text-center text-decoration-none">
            View Community
          </a>
        @else
          <form action="{{ route('community.join', $community) }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2">
              Join Community
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>
  @empty  
  <div class="col-12 text-center text-muted p-5">
    <p> No Communities found. Click the button above to create one! </p>
  </div>
  @endforelse
</div>

<x-modal id="communityModal" title="">
  <form id="communityForm">
    @csrf
    <input type="hidden" id="editCommunityId" name="community_id" value="">
    
    <div class="form-floating">
      <input type="text" class="form-control mt-3" id="editCommunityName" name="name" placeholder="Community Name" required>
      <label for="editCommunityName">Community Name</label>
    </div>
    
    <select name="subject" id="subjectInput" class="form-select mt-3 p-3" required>
      <option value="" selected disabled>Choose a Subject...</option>
      <option value="Coding">Coding</option>
      <option value="Foreign Language">Foreign Language</option>
      <option value="Graphic Designing">Graphic Designing</option>
    </select> 
    
    <div class="form-floating">
      <input type="number" class="form-control mt-3" id="editCommunityLimit" name="member_limit" placeholder="Member Limit" min="3" max="25" value="25" required>
      <label for="editCommunityLimit">Max Members (3 - 25)</label>
    </div>
  </form>
  
  <x-slot:footer>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button type="submit" class="btn btn-primary" id="saveCommunityBtn" form="communityForm">Save Changes</button>
</x-slot:footer>
</x-modal>
@endsection
