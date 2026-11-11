@extends('layouts.dashboard')
@section('title', 'Study Sessions')
@section('content')
<x-learning-header title="Study Sessions" subtitle="Learn with your community, practise together, or join a verified tutor." :back-url="$navigationCommunity ? route('community.show', $navigationCommunity) : null" :back-label="$navigationCommunity ? 'Back to Community' : 'Back to Dashboard'">
  <a href="{{ route('schedule') }}#bookings" class="btn btn-outline-secondary">My schedule</a>
  <a href="{{ route('study-sessions.create', ['community' => request('community')]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create a session</a>
</x-learning-header>
<nav class="ph-tabs-nav mb-4" data-ph-tabs data-ph-tabs-key="study-sessions" aria-label="Filter sessions">
  <div class="ph-tabs-track">
    @foreach(['all'=>'All sessions','peer'=>'Peer study groups','tutor'=>'Tutor-led','mine'=>'My sessions','history'=>'History'] as $value=>$label)
      <a href="{{ route('study-sessions.index', ['filter'=>$value,'community'=>request('community')]) }}" class="ph-tabs-item {{ $filter === $value ? 'is-active' : '' }}" @if($filter===$value) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <span class="ph-tabs-indicator" aria-hidden="true"></span>
  </div>
</nav>
@if(request('community'))
  <div class="mb-3"><a href="{{ route('study-sessions.index') }}" class="btn btn-link btn-sm">Clear community filter</a></div>
@endif
<div class="ph-tabs-page-panel">
@if($filter === 'mine')<p class="text-muted small">Sessions you host or have joined that are still upcoming or in progress.</p>@elseif($filter === 'history')<p class="text-muted small">Completed and cancelled sessions you hosted or joined.</p>@endif
<div class="row g-3 ph-session-card-grid">
@forelse($sessions as $session)
@php
  $spotsLeft = max(0, $session->capacity - $session->participants_count);
  $occupancyPercent = min(100, round(($session->participants_count / max(1, $session->capacity)) * 100));
  $statusLabel = $session->status !== 'scheduled' ? ucfirst($session->status) : ($session->starts_at->isFuture() ? 'Upcoming' : ($session->ends_at->isFuture() ? 'In progress' : 'Awaiting attendance'));
  $isOpen = $session->status === 'scheduled' && $session->starts_at->isFuture();
@endphp
<div class="col-12 col-md-6 col-xl-4">
  <article class="card h-100 ph-session-list-card rounded-4">
    <div class="card-body p-3 p-lg-4 d-flex flex-column">
      <div class="ph-session-card-top d-flex align-items-center justify-content-between gap-2 mb-3">
        <span class="ph-session-format-chip"><i class="bi {{ $session->format === 'tutor' ? 'bi-mortarboard' : 'bi-people' }}" aria-hidden="true"></i>{{ $session->format === 'swap' ? 'Skill swap' : ($session->format === 'tutor' ? 'Tutor-led session' : 'Peer study group') }}</span>
        @if($session->host_id === auth()->id())<span class="ph-session-member-chip">Hosting</span>
        @elseif(in_array($session->id, $joined))<span class="ph-session-member-chip">Joined</span>@endif
      </div>
      <div class="mb-3">
        <span class="ph-session-status-label {{ $session->status === 'cancelled' ? 'is-cancelled' : ($session->status === 'completed' ? 'is-complete' : '') }}"><i class="bi bi-circle-fill" aria-hidden="true"></i>{{ $statusLabel }}</span>
        <p class="ph-session-card-subject mb-1 mt-2">{{ $session->subject }}</p>
        <h2 class="h5 fw-bold mb-2 text-break">{{ $session->title }}</h2>
        @if($session->description)<p class="small text-body-secondary mb-0 ph-session-description">{{ Str::limit($session->description, 145) }}</p>@endif
      </div>
      <div class="ph-session-card-meta mb-3">
        <div><i class="bi bi-calendar-event" aria-hidden="true"></i><span>{{ $session->starts_at->copy()->setTimezone(auth()->user()->learning_timezone)->format('M j, Y · g:i A') }}</span></div>
        <div><i class="bi {{ $session->meeting_method === 'online' ? 'bi-camera-video' : 'bi-geo-alt' }}" aria-hidden="true"></i><span>{{ $session->meeting_method === 'online' ? 'Online' : 'In person' }} · {{ auth()->user()->learning_timezone }}</span></div>
        <div><i class="bi bi-person" aria-hidden="true"></i><span>{{ ($session->community?->name ?? 'Private session') }} · {{ $session->host->first_name }} {{ $session->host->last_name }}</span></div>
      </div>
      <div class="ph-session-capacity mt-auto mb-3">
        <div class="d-flex justify-content-between align-items-center gap-2 small mb-2">
          <span class="fw-semibold">{{ $session->participants_count }} / {{ $session->capacity }} participants <span class="fw-normal text-body-secondary">(including host)</span></span>
          <span class="text-body-secondary text-nowrap">{{ $isOpen ? ($spotsLeft ? $spotsLeft.' spots available' : 'Full') : 'Closed' }}</span>
        </div>
        <div class="ph-session-capacity-track" role="progressbar" aria-label="Participants joined" aria-valuemin="0" aria-valuemax="{{ $session->capacity }}" aria-valuenow="{{ min($session->capacity, $session->participants_count) }}">
          <span style="width: {{ $occupancyPercent }}%"></span>
        </div>
      </div>
      <a href="{{ route('study-sessions.show', ['session' => $session, 'community' => $navigationCommunity?->id]) }}" class="btn ph-session-card-action w-100">View session <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
    </div>
  </article>
</div>
@empty
<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><i class="bi bi-people fs-1 text-muted"></i><h2 class="h5 mt-3">{{ $filter === 'history' ? 'No session history yet' : ($filter === 'mine' ? 'You have no active sessions here yet' : 'No upcoming sessions here yet') }}</h2><p class="text-muted">{{ $filter === 'history' ? 'Finished sessions will appear here.' : 'Create a session in a community you belong to, or try another filter.' }}</p>@if($filter !== 'history')<a href="{{ route('community') }}" class="btn btn-outline-primary">Explore communities</a>@endif</div></div></div>
@endforelse
</div><div class="mt-4">{{ $sessions->links() }}</div>
</div>
@endsection
