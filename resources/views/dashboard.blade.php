@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')

<!-- Communities and Swap Section -->
<div class="row g-4 align-items-stretch mb-4">
  
  <!-- Left Column: Communities -->
  <div class="col-12 col-md-6 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Communities</h2>
    @php
    $featuredCommunity = \App\Models\Community::with('user')->latest()->first();
    @endphp
    @if($featuredCommunity)
    <div class="card card-hover border-0 shadow-sm rounded-2 overflow-hidden h-100 d-flex flex-column">
      <div style="overflow: hidden; height: 200px; flex-shrink: 0;">
        <img src="https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg" class="w-100 h-100" style="object-fit: cover; object-position: center;" />
      </div>
      <div class="card-body d-flex flex-column p-4">
        
        <!-- Description at the top -->
        <p class="text-muted small mb-3">{{ str()->limit($featuredCommunity->description, 120) }}</p>
        
        <!-- Name, Creator, and Badges pushed to the bottom -->
        <div class="mt-auto">
          <h4 class="mb-1 fw-bold">
            <a href="{{ route('community.show', $featuredCommunity->id) }}" class="text-decoration-none text-body-emphasis stretched-link">
              {{ $featuredCommunity->name }}
            </a>
          </h4>
          
          <small class="text-muted d-block mb-3">
            <i class="bi bi-person-circle me-1"></i>Created by: {{ $featuredCommunity->user->first_name ?? 'Unknown' }}
          </small>
          
          <div class="d-flex flex-wrap align-items-center gap-2">
            @if($featuredCommunity->visibility === 'private')
            <span class="badge bg-danger px-3 py-2 rounded-pill">
              <i class="bi bi-lock-fill me-1"></i> Private
            </span>
            @else
            <span class="badge bg-success px-3 py-2 rounded-pill">
              <i class="bi bi-globe me-1"></i> Public
            </span>
            @endif
            
            <span class="badge bg-secondary px-3 py-2 rounded-pill">
              <i class="bi bi-book-half me-1"></i> {{ $featuredCommunity->subject }}
            </span>

            <span class="badge border text-secondary px-3 py-2 rounded-pill">
              <i class="bi bi-people-fill me-1"></i> Limit: {{ $featuredCommunity->member_limit }}
            </span>
          </div>
        </div>
      </div>
    </div>
    @else
    <div class="card border-0 shadow-sm rounded-2 p-4 text-center h-100 d-flex justify-content-center align-items-center">
      <p class="text-muted mb-0">No communities available yet. Be the first to create one!</p>
    </div>
    @endif
  </div>

  <!-- Right Column: Walkthrough -->
  <div class="col-12 col-md-6 d-flex flex-column">
    <h2 class="mb-3 fw-bold">How PeerHive works</h2>
    @php
      $walkthrough = [
        ['icon' => 'bi-search', 'title' => 'Find your learning partner', 'text' => 'Browse the students below or use search to find a peer. Open their profile to explore their skills and interests.', 'tip' => 'Start with a skill you want to learn.'],
        ['icon' => 'bi-arrow-left-right', 'title' => 'Send a swap request', 'text' => 'Tap Swap on a student card. Introduce yourself and explain what you would like to learn and what you can share.', 'tip' => 'Verify your email before sending a request.'],
        ['icon' => 'bi-chat-dots', 'title' => 'Start a conversation', 'text' => 'Open Messages to connect with your peer. Discuss your learning goals and decide how you will work together.', 'tip' => 'On your phone, tap a chat to open it.'],
        ['icon' => 'bi-calendar-check', 'title' => 'Make time to learn', 'text' => 'Set your weekly availability in Schedule. Use Messages to agree with your peer on a time that suits you both.', 'tip' => 'Choose a day and set your available hours.'],
        ['icon' => 'bi-people', 'title' => 'Learn with a community', 'text' => 'Explore Community to find groups around your interests. Join a group to connect, ask questions, and share what you know.', 'tip' => 'Private communities may require an invitation.'],
      ];
    @endphp
    <section id="peerHiveWalkthrough" class="card border-0 shadow-sm rounded-2 h-100 d-flex flex-column p-4 carousel slide tutorial-card" aria-label="How PeerHive works" aria-roledescription="carousel" tabindex="0">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="small fw-semibold text-primary">Your quick start guide</span>
      </div>
      <div class="carousel-inner flex-grow-1" aria-live="polite">
        @foreach($walkthrough as $step)
          <article class="carousel-item {{ $loop->first ? 'active' : '' }}" role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ count($walkthrough) }}" @if(!$loop->first) aria-hidden="true" @endif>
            <div class="tutorial-icon mb-3"><i class="bi {{ $step['icon'] }}" aria-hidden="true"></i></div>
            <h3 class="h5 fw-bold">{{ $step['title'] }}</h3>
            <p class="text-muted mb-3">{{ $step['text'] }}</p>
            <p class="small mb-0"><i class="bi bi-lightbulb text-warning me-1" aria-hidden="true"></i>{{ $step['tip'] }}</p>
          </article>
        @endforeach
      </div>
      <div class="tutorial-controls mt-auto d-flex align-items-center justify-content-between gap-2 mt-4">
        <button id="tutorialPrevious" type="button" class="btn btn-outline-secondary btn-sm" aria-label="Previous tutorial step" disabled><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="ms-1">Previous</span></button>
        <div class="d-flex align-items-center tutorial-dots" aria-label="Choose a tutorial step">
          @foreach($walkthrough as $step)
            <button type="button" class="tutorial-dot {{ $loop->first ? 'is-active' : '' }}" data-tutorial-slide="{{ $loop->index }}" aria-label="Step {{ $loop->iteration }}: {{ $step['title'] }}" aria-current="{{ $loop->first ? 'step' : 'false' }}"><span></span></button>
          @endforeach
        </div>
        <button id="tutorialNext" type="button" class="btn btn-primary btn-sm" aria-label="Next tutorial step"><span class="me-1">Next</span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
      </div>
    </section>
  </div>
  
</div>
  
  <!-- TOP STUDENTS -->
  <h2 class="mb-3 fw-bold">Top Students</h2>
  <div class="row">
    @foreach($topstudents as $topstudent)
    @php
    $isLiked = in_array($topstudent->id, $likedIds);
    $isFaved = in_array($topstudent->id, $favoritedIds);
    @endphp
    <div class="col-12 col-md-6 col-xl-4 mb-4 student-card-column">
      <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100 student-card">
        <div class="d-flex align-items-start gap-2 gap-sm-3">
          <a href="{{ route('users.profile', $topstudent->id) }}" class="text-decoration-none flex-shrink-0">
          @if($topstudent->profile_picture)
            <img src="{{ asset('storage/' . $topstudent->profile_picture) }}" 
              alt="{{ $topstudent->first_name }}" 
              class="rounded-circle shadow-sm" 
              style="width: 60px; height: 60px; object-fit: cover;">
          @else
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white shadow-sm"
                style="width: 60px; height: 60px; font-size: 1.5rem;">
              {{ strtoupper(substr($topstudent->first_name, 0, 1)) }}{{ strtoupper(substr($topstudent->last_name, 0, 1)) }}
            </div>
          @endif
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
            <!-- Like button -->
            <div class="d-flex align-items-center">
              <button type="button"
              class="btn btn-sm p-0 shadow-none fs-5 like-btn {{ $isLiked ? 'text-danger' : '' }}"
              data-id="{{ $topstudent->id }}"
              title="{{ $isLiked ? 'Unlike' : 'Like' }}">
              <i class="bi {{ $isLiked ? 'bi-heart-fill' : 'bi-heart' }}"></i>
            </button>
            <small class="text-muted fw-semibold ms-1 like-count" data-id="{{ $topstudent->id }}">{{ $topstudent->liked_by_count }}</small>
          </div>
          
          <!-- Swap count (display only) -->
          <div class="d-flex align-items-center">
            <button type="button" class="btn btn-sm p-0 shadow-none fs-5 open-swap-modal" data-id="{{ $topstudent->id }}" title="Send swap request">
              <i class="bi bi-arrow-left-right"></i>
            </button>
            <small class="text-muted fw-semibold ms-1">{{ $topstudent->swaps_count }}</small>
          </div>
          
          <!-- View profile / comments -->
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

<!-- PEERHIVE STUDENTS -->
<h2 class="mb-3 fw-bold">PeerHive Students</h2>
<div class="row">
  @foreach($students as $student)
  @php
  $isLiked = in_array($student->id, $likedIds);
  $isFaved = in_array($student->id, $favoritedIds);
  @endphp
  <div class="col-12 col-md-6 col-xl-4 mb-4 student-card-column">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100 student-card">
      <div class="d-flex align-items-start gap-2 gap-sm-3">
        <a href="{{ route('users.profile', $student->id) }}" class="text-decoration-none flex-shrink-0">
        @if($student->profile_picture)
          <img src="{{ asset('storage/' . $student->profile_picture) }}" 
            alt="{{ $student->first_name }}" 
            class="rounded-circle shadow-sm" 
            style="width: 60px; height: 60px; object-fit: cover;">
        @else
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white shadow-sm"
              style="width: 60px; height: 60px; font-size: 1.5rem;">
            {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
          </div>
        @endif
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
          <!-- Like button -->
          <div class="d-flex align-items-center">
            <button type="button"
              class="btn btn-sm p-0 shadow-none fs-5 like-btn {{ $isLiked ? 'text-danger' : '' }}"
              data-id="{{ $student->id }}"
              title="{{ $isLiked ? 'Unlike' : 'Like' }}">
            <i class="bi {{ $isLiked ? 'bi-heart-fill' : 'bi-heart' }}"></i>
          </button>
          <small class="text-muted fw-semibold ms-1 like-count" data-id="{{ $student->id }}">{{ $student->liked_by_count }}</small>
        </div>
        
        <!-- Swap count (show how many swaps they've done) -->
        <div class="d-flex align-items-center">
          <button type="button" class="btn btn-sm p-0 shadow-none fs-5 open-swap-modal" data-id="{{ $student->id }}" title="Send swap request">
            <i class="bi bi-arrow-left-right"></i>
          </button>
          <small class="text-muted fw-semibold ms-1">{{ $student->swaps_count }}</small>
        </div>
        
        <!-- Comments / profile link -->
        <div class="d-flex align-items-center">
          <a href="{{ route('users.profile', $student->id) }}" class="btn btn-sm p-0 shadow-none fs-5 text-body" title="View profile">
            <i class="bi bi-chat-dots"></i>
          </a>
          <small class="text-muted fw-semibold ms-1">{{ $student->comments()->count() }}</small>
        </div>
      </div>
      
      @if($topstudent->hasVerifiedEmail())
        <button type="button" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 open-swap-modal" data-id="{{ $topstudent->id }}">
          Swap
        </button>
      @else
        <button type="button" class="btn btn-secondary opacity-50 w-100 rounded-3 text-uppercase fw-bold py-2" disabled title="This user is not verified yet.">
          <i class="bi bi-shield-lock me-1"></i> Unverified
        </button>
      @endif
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
