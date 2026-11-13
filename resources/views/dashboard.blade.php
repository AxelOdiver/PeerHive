@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
<div class="ph-v44-dashboard">
  <header class="ph-v44-welcome mb-3">
    <h1 class="h4 fw-bold mb-1">{{ session('peerhive_first_dashboard_visit') ? 'Welcome to PeerHive' : 'Welcome back' }}, {{ auth()->user()->first_name }}!</h1>
    <p class="small text-body-secondary mb-0">Learn with your peers, share your skills, and keep growing.</p>
  </header>

  <div class="ph-v44-overview mb-3">
    <section class="ph-v44-card ph-v44-progress" aria-labelledby="phV44ProgressHeading">
      <div class="d-flex align-items-start gap-2 mb-3">
        <span class="ph-v44-icon" aria-hidden="true"><i class="bi bi-bar-chart-line"></i></span>
        <div class="flex-grow-1 min-w-0">
          <h2 class="h5 fw-bold mb-1" id="phV44ProgressHeading">My Progress</h2>
          <p class="small text-body-secondary mb-0">Your learning and collaboration activity</p>
        </div>
        <a class="btn btn-sm ph-v45-action ph-v45-action--outline flex-shrink-0" href="{{ route('progress.index') }}">Details <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
      </div>
      <div class="ph-v44-progress-body">
        <div class="ph-v44-activity">
          <p class="fw-semibold small mb-2">Completed sessions <span class="text-body-secondary fw-normal">· last 4 weeks</span></p>
          @if($activityTotal > 0)
            <div class="ph-v44-chart" role="img" aria-label="Completed sessions by week: {{ collect($weeklyActivity)->map(fn ($week) => $week['label'].': '.$week['count'])->implode('; ') }}">
              @foreach($weeklyActivity as $week)
                <div class="ph-v44-chart-week">
                  <div class="ph-v44-chart-bars" aria-hidden="true">
                    <span class="ph-v44-chart-value">{{ $week['count'] }}</span>
                    <span class="ph-v44-chart-bar" style="height: {{ ($week['count'] > 0 ? max(8, round(($week['count'] / $activityPeak) * 100)) : 0) }}%"></span>
                  </div>
                  <span class="ph-v44-chart-label">{{ $week['label'] }}</span>
                </div>
              @endforeach
            </div>
          @else
            <div class="ph-v44-chart-empty">
              <i class="bi bi-graph-up-arrow fs-4" aria-hidden="true"></i>
              <span>Your activity chart will appear after your first completed study session.</span>
            </div>
          @endif
        </div>
        <div class="ph-v44-metrics" aria-label="Your learning statistics">
          <div><i class="bi bi-check2-circle" aria-hidden="true"></i><span><strong>{{ number_format($completedSessions) }}</strong><small>Completed sessions</small></span></div>
          <div><i class="bi bi-arrow-left-right" aria-hidden="true"></i><span><strong>{{ number_format($sentRequests) }}</strong><small>Requests sent</small></span></div>
          <div><i class="bi bi-people" aria-hidden="true"></i><span><strong>{{ number_format($joinedCommunityCount) }}</strong><small>Communities joined</small></span></div>
        </div>
      </div>
    </section>

    <div class="ph-v44-stat-stack">
      <section class="ph-v44-card ph-v44-side-card" aria-labelledby="phV44RewardsHeading">
        <div class="d-flex align-items-center gap-2">
          <span class="ph-v44-icon" aria-hidden="true"><i class="bi bi-gift"></i></span>
          <div class="flex-grow-1 min-w-0">
            <h2 class="h6 fw-bold mb-0" id="phV44RewardsHeading">Rewards Hub</h2>
            <p class="small text-body-secondary mb-0">Points earned</p>
          </div>
        </div>
        <div class="ph-v44-side-footer">
          <div class="ph-v44-side-value">{{ number_format($learningPoints) }} <small>pts</small></div>
          <a class="btn btn-sm ph-v45-action ph-v45-action--primary" href="{{ route('rewards.index') }}">View rewards <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
        </div>
      </section>
      <section class="ph-v44-card ph-v44-side-card" aria-labelledby="phV44SessionsHeading">
        <div class="d-flex align-items-center gap-2">
          <span class="ph-v44-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
          <div class="flex-grow-1 min-w-0">
            <h2 class="h6 fw-bold mb-0" id="phV44SessionsHeading">Study Sessions</h2>
            <p class="small text-body-secondary mb-0 text-truncate" title="{{ $nextSession?->title ?? 'Find your next session' }}">{{ $nextSession?->title ?? 'Find your next session' }}</p>
          </div>
        </div>
        <div class="ph-v44-side-footer">
          <div class="ph-v44-side-value">{{ number_format($upcomingSessionCount) }} <small>upcoming</small></div>
          <a class="btn btn-sm ph-v45-action ph-v45-action--outline" href="{{ route('study-sessions.index') }}">View sessions <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
        </div>
      </section>
    </div>
  </div>

  @if($showDashboardGuide)
    <div class="ph-v44-guide-wrap ph-v45-guide-wrap">
      @include('dashboard.quick-start')
    </div>
  @endif

  <div class="ph-v44-content">
    <section class="mb-4" aria-labelledby="communitiesHeading">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 id="communitiesHeading" class="h4 fw-bold mb-0">Communities</h2>
        <a href="{{ route('community') }}" class="btn btn-sm ph-v45-action ph-v45-action--outline">View all communities <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
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
        <div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-0">No matching or joined communities yet. Find a group that matches your interests.</p><a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('community') }}"><i class="bi bi-people me-1" aria-hidden="true"></i>Explore communities</a></div></div>
      @endif
    </section>

    <section class="mb-4" aria-labelledby="recommendedPeersHeading">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><h2 id="recommendedPeersHeading" class="h4 fw-bold">Recommended learning partners</h2><a class="btn btn-sm ph-v45-action ph-v45-action--outline" href="{{ route('profile') }}#learning-interests">Edit interests</a></div>
      <p class="text-muted small">Up to six peers, matched to your learning subjects. Shared availability helps rank equally relevant matches.</p>
      @if($focusTopic)<p class="small">Teachers for: <strong>{{ $focusTopic->name }}</strong> · <a class="btn btn-sm btn-link text-decoration-none" href="{{ route('dashboard') }}#recommendedPeersHeading">Reset filter</a></p>@elseif(auth()->user()->learning_interests)<p class="small">You want to learn: <strong>{{ implode(', ', auth()->user()->learning_interests) }}</strong></p>@endif
      <div class="row g-3">
        @forelse($students as $student)
          @include('components.dashboard-peer-card', ['student' => $student, 'showMatch' => true, 'columnClass' => 'col-12 col-md-6'])
        @empty
          <div class="col-12"><div class="card border-0 bg-body-tertiary p-4"><p class="mb-2">{{ empty(auth()->user()->learning_interests) ? 'Tell us what you want to learn to see relevant peers.' : 'No approved teachers match your subjects yet. Try a subject from the suggestions in Edit Profile, or check back later.' }}</p><a class="btn btn-sm ph-v45-action ph-v45-action--outline" href="{{ route('profile') }}#learning-interests">Choose learning interests</a></div></div>
        @endforelse
      </div>
    </section>

    <section aria-labelledby="communityFeedHeading">
      <h2 id="communityFeedHeading" class="h4 fw-bold mb-3">From your communities</h2>
      @forelse($communityPosts as $post)
        <article class="card border-0 shadow-sm mb-3"><div class="card-body">
          <div class="small text-muted mb-2">{{ $post->community->name }} · {{ $post->user->first_name }} {{ $post->user->last_name }} · {{ $post->created_at->diffForHumans() }}</div>
          <h3 class="h5">{{ $post->title }}</h3><p class="text-break">{{ Str::limit($post->body, 250) }}</p>
          <a href="{{ route('community.show', $post->community) }}#post-{{ $post->id }}" class="btn btn-sm ph-v45-action ph-v45-action--outline">Open discussion</a>
        </div></article>
      @empty
        <div class="card border-0 shadow-sm"><div class="card-body"><p class="text-muted mb-0">No posts from your communities yet. Join a community to start discussing topics with other students.</p><a href="{{ route('community') }}" class="btn btn-sm btn-outline-primary mt-2">Explore communities</a></div></div>
      @endforelse
    </section>

    <section class="mb-4" aria-labelledby="topStudentsHeading">
      <h2 id="topStudentsHeading" class="h4 fw-bold mb-3">Top Students</h2>
      <div class="row g-3">
        @forelse($topstudents as $topstudent)
          @include('components.dashboard-peer-card', ['student' => $topstudent, 'showMatch' => false, 'columnClass' => 'col-12 col-md-6 col-xl-4'])
        @empty
          <div class="col-12"><p class="text-body-secondary small mb-0">No other students yet.</p></div>
        @endforelse
      </div>
    </section>
  </div>
</div>

<x-learning-request-modal />
@endsection
