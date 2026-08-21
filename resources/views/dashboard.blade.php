@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
<div class="row d-flex align-items-stretch">
  <div class="col-12 col-md-6 mb-4 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Communities</h2>
    @php
    $featuredCommunity = \App\Models\Community::with('user')->latest()->first();
    @endphp
    @if($featuredCommunity)
    <div class="card card-hover border-0 shadow-sm rounded-2 overflow-hidden h-100 position-relative">
      <div style="overflow: hidden;">
        <img src="https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg" class="card-img-top rounded-2" style="object-fit: cover; height: 150px;" />
      </div>
      <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start mb-2">        
          
          <!-- Left Side: Limit Badge -->
          <small class="badge bg-secondary p-2">
            <i class="bi bi-people-fill me-1"></i> Limit: {{ $featuredCommunity->member_limit }} members
          </small>
          
          <!-- Right Side: Subject and Visibility grouped together -->
          <div class="d-flex gap-2">
            <small class="badge bg-secondary px-3 py-2 rounded-pill">
              <i class="bi bi-book-half me-1"></i> {{ $featuredCommunity->subject }}
            </small>
            
            @if($featuredCommunity->visibility === 'private')
            <small class="badge bg-danger text-white px-3 py-2 rounded-pill">
              <i class="bi bi-lock-fill me-1"></i> Private
            </small>
            @else
            <small class="badge bg-success text-white px-3 py-2 rounded-pill">
              <i class="bi bi-globe me-1"></i> Public
            </small>
            @endif
          </div>
        </div>
        
        <h4 class="mb-1 mt-1 fw-bold">
          <a href="{{ route('community.show', $featuredCommunity->id) }}" class="text-decoration-none text-body-emphasis stretched-link">
            {{ $featuredCommunity->name }}
          </a>
        </h4>
        <small class="text-muted mb-2 mt-2 d-block"><i class="bi bi-person-circle me-1"></i>Created by: {{ $featuredCommunity->user->first_name ?? 'Unknown' }}</small>
        <p class="text-muted small mb-0 mt-auto">{{ str()->limit($featuredCommunity->description, 150) }}</p>
      </div>
    </div>
    @else
    <div class="card border-0 shadow-sm rounded-2 p-4 text-center h-100 d-flex justify-content-center align-items-center">
      <p class="text-muted mb-0">No communities available yet. Be the first to create one!</p>
    </div>
    @endif
  </div>
  
  <div class="col-12 col-md-6 mb-4 mt-2 mt-md-0 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Swap, learn, grow</h2>
    <div class="card card-hover border-0 shadow-sm rounded-2 p-4 card-border-dark overflow-hidden h-100 d-flex flex-column">
      <div class="flex-grow-1">
        <p><a href="#" class="fw-bold btn btn-link link-body-emphasis text-decoration-none pb-1 p-0">Most Collaborated</a></p>
        <p class="text-muted" style="font-size: 1.1rem;">
          Discover the most accomplished and influential professionals
        </p>
      </div>
      <div class="d-flex align-items-center gap-0 mt-auto">
        <button class="btn btn-sm shadow-none fs-4"><i class="bi bi-heart"></i></button>
        <small class="text-muted fw-semibold p-2 me-2">20k+</small>
        <button class="btn btn-sm shadow-none fs-4"><i class="bi bi-arrow-left-right"></i></button>
        <small class="text-muted fw-semibold p-2 me-2">500+</small>
        <button class="btn btn-sm shadow-none fs-4"><i class="bi bi-chat-dots"></i></button>
        <small class="text-muted fw-semibold p-2 me-2">1k+</small>
      </div>
    </div>
  </div>
  
  {{-- TOP STUDENTS --}}
  <h2 class="mb-3 fw-bold">Top Students</h2>
  <div class="row">
    @foreach($topstudents as $topstudent)
    @php
    $isLiked = in_array($topstudent->id, $likedIds);
    $isFaved = in_array($topstudent->id, $favoritedIds);
    @endphp
    <div class="col-12 col-md-6 col-xl-4 mb-4">
      <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
        <div class="d-flex align-items-start gap-2 gap-sm-3">
          <a href="{{ route('users.profile', $topstudent->id) }}" class="text-decoration-none flex-shrink-0">
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white"
            style="width: 60px; height: 60px; font-size: 1.5rem;">
            {{ strtoupper(substr($topstudent->first_name, 0, 1)) }}{{ strtoupper(substr($topstudent->last_name, 0, 1)) }}
          </div>
        </a>
        
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <a href="{{ route('users.profile', $topstudent->id) }}" class="text-decoration-none text-body-emphasis">
              <h5 class="fw-bold mb-0 text-truncate">{{ $topstudent->first_name }} {{ $topstudent->last_name }}</h5>
            </a>
            <button type="button" class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0 fav-btn" data-id="{{ $topstudent->id }}">
              @if($isFaved)
              <i class="bi bi-bookmark-fill text-warning"></i>
              @else
              <i class="bi bi-bookmark"></i>
              @endif
            </button>
          </div>
          
          <p class="text-muted small mb-1 text-truncate">Peer Student
            <span class="text-warning text-nowrap ms-1">
              @for($i = 0; $i < 4; $i++) <i class="bi bi-star-fill"></i> @endfor
              <i class="bi bi-star"></i>
            </span>
          </p>
          
          <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
            {{-- Like button --}}
            <div class="d-flex align-items-center">
              <button type="button"
              class="btn btn-sm p-0 shadow-none fs-5 like-btn {{ $isLiked ? 'text-danger' : '' }}"
              data-id="{{ $topstudent->id }}"
              title="{{ $isLiked ? 'Unlike' : 'Like' }}">
              <i class="bi {{ $isLiked ? 'bi-heart-fill' : 'bi-heart' }}"></i>
            </button>
            <small class="text-muted fw-semibold ms-1 like-count" data-id="{{ $topstudent->id }}">{{ $topstudent->liked_by_count }}</small>
          </div>
          
          {{-- Swap count (display only) --}}
          <div class="d-flex align-items-center">
            <button type="button" class="btn btn-sm p-0 shadow-none fs-5 open-swap-modal" data-id="{{ $topstudent->id }}" title="Send swap request">
              <i class="bi bi-arrow-left-right"></i>
            </button>
            <small class="text-muted fw-semibold ms-1">{{ $topstudent->swaps_count }}</small>
          </div>
          
          {{-- View profile / comments --}}
          <div class="d-flex align-items-center">
            <a href="{{ route('users.profile', $topstudent->id) }}" class="btn btn-sm p-0 shadow-none fs-5 text-body" title="View profile">
              <i class="bi bi-chat-dots"></i>
            </a>
            <small class="text-muted fw-semibold ms-1">{{ $topstudent->comments()->count() }}</small>
          </div>
        </div>
        
        <button type="button" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 open-swap-modal" data-id="{{ $topstudent->id }}">
          Swap
        </button>
      </div>
    </div>
  </div>
</div>
@endforeach
</div>

{{-- PEERHIVE STUDENTS --}}
<h2 class="mb-3 fw-bold">PeerHive Students</h2>
<div class="row">
  @foreach($students as $student)
  @php
  $isLiked = in_array($student->id, $likedIds);
  $isFaved = in_array($student->id, $favoritedIds);
  @endphp
  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
      <div class="d-flex align-items-start gap-2 gap-sm-3">
        <a href="{{ route('users.profile', $student->id) }}" class="text-decoration-none flex-shrink-0">
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white"
          style="width: 60px; height: 60px; font-size: 1.5rem;">
          {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
        </div>
      </a>
      
      <div class="flex-grow-1 min-w-0">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <a href="{{ route('users.profile', $student->id) }}" class="text-decoration-none text-body-emphasis">
            <h5 class="fw-bold mb-0 text-truncate">{{ $student->first_name }} {{ $student->last_name }}</h5>
          </a>
          <button type="button" class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0 fav-btn" data-id="{{ $student->id }}">
            @if($isFaved)
            <i class="bi bi-bookmark-fill text-warning"></i>
            @else
            <i class="bi bi-bookmark"></i>
            @endif
          </button>
        </div>
        
        <p class="text-muted small mb-1 text-truncate">Peer Student
          <span class="text-warning text-nowrap ms-1">
            @for($i = 0; $i < 4; $i++) <i class="bi bi-star-fill"></i> @endfor
            <i class="bi bi-star"></i>
          </span>
        </p>
        
        <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
          {{-- Like button --}}
          <div class="d-flex align-items-center">
            <button type="button"
            class="btn btn-sm p-0 shadow-none fs-5 like-btn {{ $isLiked ? 'text-danger' : '' }}"
            data-id="{{ $student->id }}"
            title="{{ $isLiked ? 'Unlike' : 'Like' }}">
            <i class="bi {{ $isLiked ? 'bi-heart-fill' : 'bi-heart' }}"></i>
          </button>
          <small class="text-muted fw-semibold ms-1 like-count" data-id="{{ $student->id }}">{{ $student->liked_by_count }}</small>
        </div>
        
        {{-- Swap count (show how many swaps they've done) --}}
        <div class="d-flex align-items-center">
          <button type="button" class="btn btn-sm p-0 shadow-none fs-5 open-swap-modal" data-id="{{ $student->id }}" title="Send swap request">
            <i class="bi bi-arrow-left-right"></i>
          </button>
          <small class="text-muted fw-semibold ms-1">{{ $student->swaps_count }}</small>
        </div>
        
        {{-- Comments / profile link --}}
        <div class="d-flex align-items-center">
          <a href="{{ route('users.profile', $student->id) }}" class="btn btn-sm p-0 shadow-none fs-5 text-body" title="View profile">
            <i class="bi bi-chat-dots"></i>
          </a>
          <small class="text-muted fw-semibold ms-1">{{ $student->comments()->count() }}</small>
        </div>
      </div>
      
      <button type="button" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 open-swap-modal" data-id="{{ $student->id }}">
        Swap
      </button>
    </div>
  </div>
</div>
</div>
@endforeach
</div>
</div>

<x-modal id="swapModal" title="Swap Request">
  <form id="swapRequestForm">
    <input type="hidden" id="swapUserId" name="user_id" value="">
    <div class="form-floating">
      <textarea class="form-control" placeholder="Write a message" id="swapRequestMessage" name="message" style="height: 100px"></textarea>
      <label for="swapRequestMessage">Write a message</label>
    </div>
  </form>
  <x-slot:footer>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button type="submit" class="btn btn-primary" id="sendSwapRequestBtn" form="swapRequestForm">Send Request</button>
</x-slot:footer>
</x-modal>
@endsection
