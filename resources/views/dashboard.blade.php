@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
<div class="row g-4 align-items-start">
  <div class="col-12 col-xl-8 dashboard-main order-2 order-xl-1">
    <section class="mb-4" aria-labelledby="communitiesHeading">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 id="communitiesHeading" class="h4 fw-bold mb-0">Communities</h2>
        <a href="{{ route('community') }}" class="small">View all communities <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
      </div>
      @php($community = $communitySuggestion['community'])
      @if($community)
        <article class="card border-0 shadow-sm rounded-3 overflow-hidden position-relative">
          <x-community-banner :community="$community" size="dashboard" />
          <div class="card-body">
            <p class="small text-muted mb-2">{{ $communitySuggestion['matches_interests'] ? 'Matched to your learning interests.' : 'From a community you belong to.' }}</p>
            <h3 class="h5 fw-bold mb-1 text-break"><a href="{{ route('community.show', $community) }}" class="stretched-link text-body-emphasis text-decoration-none">{{ $community->name }}</a></h3>
            <p class="small text-muted mb-2">Created by {{ $community->user?->first_name ?? 'Unknown' }}</p>
            @if($community->description)<p class="small text-muted mb-3 text-break">{{ Str::limit($community->description, 150) }}</p>@endif
            <div class="d-flex flex-wrap gap-2">
              <span class="badge {{ $community->visibility === 'private' ? 'text-bg-danger' : 'text-bg-success' }} rounded-pill"><i class="bi {{ $community->visibility === 'private' ? 'bi-lock-fill' : 'bi-globe' }} me-1" aria-hidden="true"></i>{{ ucfirst($community->visibility) }}</span>
              <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill text-wrap">{{ $community->subject }}</span>
            </div>
          </div>
        </article>
      @else
        <div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-0">No matching or joined communities yet. <a href="{{ route('community') }}">Explore communities</a> to find a group.</p></div></div>
      @endif
    </section>

    <section class="mb-4" aria-labelledby="recommendedPeersHeading">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><h2 id="recommendedPeersHeading" class="h4 fw-bold">Recommended learning partners</h2><a href="{{ route('profile') }}#learning-interests">Edit learning interests</a></div>
      <p class="text-muted small">Up to six peers, matched to your learning subjects. Shared availability helps rank equally relevant matches.</p>
      @if($focusTopic)<p class="small">Teachers for: <strong>{{ $focusTopic->name }}</strong> · <a href="{{ route('dashboard') }}#recommendedPeersHeading">Show my usual recommendations</a></p>@elseif(auth()->user()->learning_interests)<p class="small">You want to learn: <strong>{{ implode(', ', auth()->user()->learning_interests) }}</strong></p>@endif
      <div class="row g-3">
        @forelse($students as $student)
          @include('components.dashboard-peer-card', ['student' => $student, 'showMatch' => true, 'columnClass' => 'col-12 col-md-6'])
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
        <div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-0">No posts from your communities yet. <a href="{{ route('community') }}">Explore communities</a> to join a discussion.</p></div></div>
      @endforelse
    </section>
  </div>

  <aside class="col-12 col-xl-4 dashboard-rail order-1 order-xl-2" aria-label="Progress, sessions, guide, and top students">
    <div class="d-flex flex-column gap-3 mb-4">
      <a class="card shadow-sm border-0 text-decoration-none text-body mb-0" href="{{ route('progress.index') }}">
        <div class="card-body py-2 px-3 dashboard-shortcut">
          <div><span class="d-block fw-semibold">My Progress</span><span class="small text-muted">Confirmed sessions</span></div>
          <span class="fs-4 fw-bold text-end">{{ $completedSessions }}</span>
          <i class="bi bi-arrow-right dashboard-shortcut-arrow" aria-hidden="true"></i>
        </div>
      </a>
      <a class="card shadow-sm border-0 text-decoration-none text-body mb-0" href="{{ route('rewards.index') }}">
        <div class="card-body py-2 px-3 dashboard-shortcut">
          <div><span class="d-block fw-semibold">Rewards Hub</span><span class="small text-muted">Available points</span></div>
          <span class="fs-4 fw-bold text-end">{{ $learningPoints }}</span>
          <i class="bi bi-arrow-right dashboard-shortcut-arrow" aria-hidden="true"></i>
        </div>
      </a>
      <a class="card shadow-sm border-0 text-decoration-none text-body mb-0" href="{{ route('study-sessions.index') }}">
        <div class="card-body py-2 px-3 dashboard-shortcut">
          <div><span class="fw-semibold">Study Sessions</span>
          <span class="small d-block mt-1 text-break">{{ $nextSession?->title ?? 'Find your next study circle' }}</span>
          @if($nextSession)<span class="small text-muted">{{ $nextSession->starts_at->copy()->setTimezone(auth()->user()->learning_timezone)->format('M j · g:i A') }} · {{ auth()->user()->learning_timezone }}</span>@endif
          </div>
          <i class="bi bi-arrow-right dashboard-shortcut-arrow" aria-hidden="true"></i>
        </div>
      </a>
    </div>

    @include('dashboard.quick-start')

    <section class="mb-4" aria-labelledby="topStudentsHeading">
      <h2 id="topStudentsHeading" class="h4 fw-bold mb-3">Top Students</h2>
      <div class="row g-3">
        @forelse($topstudents as $topstudent)
          @include('components.dashboard-peer-card', ['student' => $topstudent, 'showMatch' => false, 'columnClass' => 'col-12'])
        @empty
          <div class="col-12"><p class="text-muted small mb-0">No other students yet.</p></div>
        @endforelse
      </div>
    </section>
  </aside>
</div>

<x-learning-request-modal />
@endsection
