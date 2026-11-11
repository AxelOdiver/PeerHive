@extends('layouts.dashboard')

@section('title', 'Learning History')

@section('content')
<x-learning-header title="Learning History" subtitle="Your completed and cancelled study sessions.">
    <a href="{{ route('study-sessions.index') }}" class="btn ph-history-discover">
        <i class="bi bi-search me-1" aria-hidden="true"></i>Find a session
    </a>
</x-learning-header>

<section class="ph-history-shell card" aria-labelledby="phHistoryListTitle">
    <header class="ph-history-header">
        <div>
            <h2 id="phHistoryListTitle" class="h5 fw-bold mb-1">Past sessions</h2>
            <p class="small text-body-secondary mb-0">Your learning and teaching activity, from newest to oldest.</p>
        </div>
        <span class="ph-history-count">{{ $participations->total() }} {{ \Illuminate\Support\Str::plural('session', $participations->total()) }}</span>
    </header>

    <div class="ph-history-list">
        @forelse($participations as $record)
            @php
                $session = $record->session;
                $completed = $session->status === 'completed';
                $attendanceText = !$completed
                    ? 'Session cancelled'
                    : ($record->confirmed_at
                        ? 'Participation confirmed'
                        : ($record->attended_at ? 'Awaiting confirmation' : 'Attendance not confirmed'));
                $attendanceKind = !$completed ? 'neutral' : ($record->confirmed_at ? 'confirmed' : ($record->attended_at ? 'pending' : 'neutral'));
                $displayTime = $session->starts_at->copy()->setTimezone(auth()->user()->learning_timezone)->format('M j, Y · g:i A');
            @endphp
            <article class="ph-history-row" aria-label="{{ $session->title }}: {{ ucfirst($session->status) }}">
                <div class="ph-history-row-main">
                    <span class="ph-history-row-icon {{ $completed ? 'is-completed' : 'is-cancelled' }}" aria-hidden="true">
                        <i class="bi {{ $completed ? 'bi-check2-circle' : 'bi-calendar-x' }}"></i>
                    </span>
                    <div class="ph-history-row-copy">
                        <a class="ph-history-title" href="{{ route('study-sessions.show', $session) }}">{{ $session->title }}</a>
                        <div class="ph-history-meta">
                            <span>{{ $session->community?->name ?? 'Private session' }}</span>
                            <span>{{ $session->subject }}</span>
                            <span><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ $displayTime }}</span>
                        </div>
                    </div>
                </div>
                <div class="ph-history-row-aside">
                    <span class="ph-history-status {{ $completed ? 'is-completed' : 'is-cancelled' }}">
                        <i class="bi {{ $completed ? 'bi-check-circle' : 'bi-x-circle' }}" aria-hidden="true"></i>{{ ucfirst($session->status) }}
                    </span>
                    <span class="ph-history-attendance ph-history-attendance--{{ $attendanceKind }}">{{ $attendanceText }}</span>
                    <a class="ph-history-action" href="{{ route('study-sessions.show', $session) }}">
                        View details <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </article>
        @empty
            <div class="ph-history-empty">
                <span class="ph-history-empty-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
                <h3 class="h6 fw-bold">No session history yet</h3>
                <p class="text-body-secondary mb-3">No completed or cancelled sessions yet. Your history will appear here after your first session.</p>
                <a href="{{ route('study-sessions.index') }}" class="btn ph-history-discover">Explore study sessions</a>
            </div>
        @endforelse
    </div>

    @if($participations->hasPages())
        <footer class="ph-history-pagination">{{ $participations->links() }}</footer>
    @endif
</section>
@endsection
