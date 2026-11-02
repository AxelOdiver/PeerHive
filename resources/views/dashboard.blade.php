@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')

<!-- Communities and Swap Section -->
<div class="row g-3 mb-4"><div class="col-md-4"><a class="card shadow-sm border-0 h-100 text-decoration-none text-body" href="{{ route('progress.index') }}"><div class="card-body"><div class="small text-muted">My Progress</div><div class="fs-3 fw-bold">{{ $completedSessions }}</div><span class="small">Confirmed sessions <i class="bi bi-arrow-right ms-2"></i></span></div></a></div><div class="col-md-4"><a class="card shadow-sm border-0 h-100 text-decoration-none text-body" href="{{ route('rewards.index') }}"><div class="card-body"><div class="small text-muted">Rewards Hub</div><div class="fs-3 fw-bold">{{ $learningPoints }}</div><span class="small">Available points <i class="bi bi-arrow-right ms-2"></i></span></div></a></div><div class="col-md-4"><a class="card shadow-sm border-0 h-100 text-decoration-none text-body" href="{{ route('study-sessions.index') }}"><div class="card-body"><div class="small text-muted">Study Sessions</div><div class="fw-bold mt-2 text-break">{{ $nextSession?->title ?? 'Find your next study circle' }}</div><span class="small text-muted">{{ $nextSession?$nextSession->starts_at->copy()->setTimezone(auth()->user()->learning_timezone)->format('M j · g:i A').' · '.auth()->user()->learning_timezone:'Learn with your community' }}</span></div></a></div></div>
<div class="row g-4 align-items-stretch mb-4">
  
  <!-- Left Column: Communities -->
  <div class="col-12 col-md-6 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Communities</h2>
    @php
    $featuredCommunity = \App\Models\Community::with('user')->where(fn($q) => $q->where('user_id', auth()->id())->orWhereHas('members', fn($m) => $m->where('users.id', auth()->id())))->latest()->first();
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
    @include('components.dashboard-peer-card', ['student' => $topstudent, 'showMatch' => false])
@endforeach
</div>

<section class="mb-4" aria-labelledby="recommendedPeersHeading">
  <div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><h2 id="recommendedPeersHeading" class="h4 fw-bold">Recommended learning partners</h2><a href="{{ route('profile') }}#learning-interests">Edit learning interests</a></div>
  <p class="text-muted small">Up to six peers, matched to your learning subjects. Shared availability helps rank equally relevant matches.</p>
  @if(auth()->user()->learning_interests)<p class="small">You want to learn: <strong>{{ implode(', ', auth()->user()->learning_interests) }}</strong></p>@endif
  <div class="row g-3">
  @forelse($students as $student)
    @include('components.dashboard-peer-card', ['student' => $student, 'showMatch' => true])
  @empty
    <div class="col-12"><div class="card border-0 bg-body-tertiary p-4"><p class="mb-2">{{ empty(auth()->user()->learning_interests) ? 'Tell us what you want to learn to see relevant peers.' : 'No approved teachers match your subjects yet. Try a subject from the suggestions in Edit Profile, or check back later.' }}</p><a href="{{ route('profile') }}#learning-interests">Choose learning interests</a></div></div>
  @endforelse
  </div>
</section>
<section aria-labelledby="communityFeedHeading">
  <h2 id="communityFeedHeading" class="h4 fw-bold mb-3">From your communities</h2>
  @forelse($communityPosts as $post)
    <article class="card border-0 shadow-sm mb-3"><div class="card-body">
      <div class="small text-muted mb-2">{{ $post->community->name }} · {{ $post->user->first_name }} {{ $post->user->last_name }} · {{ $post->created_at->diffForHumans() }}</div>
      <h3 class="h5">{{ $post->title }}</h3><p class="text-break">{{ Str::limit($post->body, 250) }}</p>
      <a href="{{ route('community.show', $post->community) }}#post-{{ $post->id }}" class="btn btn-outline-primary btn-sm">Open discussion</a>
    </div></article>
  @empty
    <p class="text-muted">No posts from your communities yet. <a href="{{ route('community') }}">Explore communities</a> to join a discussion.</p>
  @endforelse
</section>
<x-learning-request-modal />
@endsection
