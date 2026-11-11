@extends('layouts.dashboard')
@section('title', $session->title)
@section('content')
@php
    $isHost = auth()->id() === $session->host_id;
    $participantCount = $session->participants->count();
    $spotsAvailable = max(0, $session->capacity - $participantCount);
    $fillPercent = $session->capacity > 0 ? min(100, round($participantCount / $session->capacity * 100)) : 0;
    $startLocal = $session->starts_at->copy()->setTimezone($session->timezone);
    $endLocal = $session->ends_at->copy()->setTimezone($session->timezone);
    $statusLabel = $session->status === 'scheduled'
        ? ($session->starts_at->isFuture() ? 'Scheduled' : ($session->ends_at->isFuture() ? 'In progress' : 'Awaiting attendance'))
        : ucfirst($session->status);
@endphp

<div class="ph-session-detail-page">
    <x-learning-header
        :title="$session->title"
        subtitle="A community learning session."
        :back-url="$navigationCommunity ? route('community.show', $navigationCommunity) : null"
        :back-label="$navigationCommunity ? 'Back to Community' : 'Back to Dashboard'"
    >
        <a href="{{ route('study-sessions.index', ['filter' => 'mine', 'community' => $navigationCommunity?->id]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>My sessions
        </a>
    </x-learning-header>

    <div class="ph-session-detail-toolbar d-flex justify-content-between align-items-center gap-2 flex-wrap mb-4">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="ph-session-detail-format">
                <i class="bi {{ $session->format === 'tutor' ? 'bi-mortarboard' : 'bi-people' }} me-1" aria-hidden="true"></i>
                {{ $session->format === 'tutor' ? 'Tutor-led' : 'Peer study group' }}
            </span>
            <span class="ph-session-detail-status ph-session-detail-status--{{ $session->status }}">{{ $statusLabel }}</span>
            <span class="ph-session-detail-topic">{{ $session->subject }}</span>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm ph-session-detail-copy" data-copy-session="{{ route('study-sessions.show', $session) }}">
            <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Copy session link
        </button>
    </div>

    <div class="row g-3 g-lg-4 align-items-start">
        <div class="col-12 col-lg-8">
            <section class="card ph-session-detail-card mb-3" aria-labelledby="phSessionInformationTitle">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 fw-bold mb-3" id="phSessionInformationTitle">Session information</h2>
                    @if(filled($session->description))
                        <p class="ph-session-detail-description mb-4">{{ $session->description }}</p>
                    @endif
                    <div class="ph-session-detail-facts">
                        <div class="ph-session-detail-fact">
                            <span class="ph-session-detail-fact-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                            <div>
                                <strong>Date and time</strong>
                                <p class="mb-0">{{ $startLocal->format('M j, Y · g:i A') }} – {{ $endLocal->format('g:i A') }}</p>
                                <small>{{ $session->timezone }}</small>
                            </div>
                        </div>
                        <div class="ph-session-detail-fact">
                            <span class="ph-session-detail-fact-icon">
                                <i class="bi {{ $session->meeting_method === 'online' ? 'bi-camera-video' : 'bi-geo-alt' }}" aria-hidden="true"></i>
                            </span>
                            <div>
                                <strong>{{ $session->meeting_method === 'online' ? 'Online meeting' : 'Meeting venue' }}</strong>
                                @if($participation)
                                    <div class="mt-1">
                                        <x-meeting-details :method="$session->meeting_method" :details="$session->meeting_details" />
                                    </div>
                                @else
                                    <p class="mb-0 text-body-secondary small">Meeting details are available after booking to eligible community members.</p>
                                @endif
                            </div>
                        </div>
                        <div class="ph-session-detail-fact">
                            <span class="ph-session-detail-fact-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                            <div>
                                <strong>Community</strong>
                                <p class="mb-0">
                                    @if($session->community)
                                        <a href="{{ route('community.show', $session->community) }}">{{ $session->community->name }}</a>
                                    @else
                                        Private session
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="ph-session-detail-fact">
                            <span class="ph-session-detail-fact-icon"><i class="bi bi-person-circle" aria-hidden="true"></i></span>
                            <div>
                                <strong>Host</strong>
                                <p class="mb-0">
                                    <a href="{{ route('users.profile', $session->host) }}">{{ $session->host->first_name }} {{ $session->host->last_name }}</a>
                                </p>
                                @if($session->format === 'tutor')
                                    <div class="mt-1"><x-teaching-badges :user="$session->host" /></div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="ph-session-detail-capacity mt-4 pt-3">
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                            <h3 class="h6 fw-bold mb-0">Participants</h3>
                            <span class="small text-body-secondary">{{ $participantCount }} of {{ $session->capacity }} joined</span>
                        </div>
                        <div class="ph-session-detail-capacity-track" role="progressbar" aria-label="Participants joined" aria-valuenow="{{ $participantCount }}" aria-valuemin="0" aria-valuemax="{{ $session->capacity }}">
                            <span style="width:{{ $fillPercent }}%"></span>
                        </div>
                        <p class="small text-body-secondary mb-0 mt-2">{{ $spotsAvailable }} {{ $spotsAvailable === 1 ? 'spot' : 'spots' }} available. Participant limit includes the host.</p>
                    </div>
                </div>
            </section>

            @if($isHost && $session->status === 'scheduled' && $session->ends_at->isPast())
                <section class="card ph-session-detail-card mb-3">
                    <div class="card-body p-3 p-md-4">
                        <h2 class="h5 fw-bold mb-2">Record attendance</h2>
                        <p class="small text-body-secondary">Select only students who attended. Each attendee must confirm their participation before points or learning hours are credited. Hosts cannot confirm their own attendance.</p>
                        <form method="POST" action="{{ route('study-sessions.complete', $session) }}">
                            @csrf
                            @foreach($session->participants->where('user_id', '!=', $session->host_id) as $person)
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" name="attendees[]" value="{{ $person->user_id }}" id="attendee{{ $person->id }}">
                                    <label class="form-check-label" for="attendee{{ $person->id }}">{{ $person->user->first_name }} {{ $person->user->last_name }}</label>
                                </div>
                            @endforeach
                            <button class="btn btn-primary mt-3" type="submit">Complete session and request confirmations</button>
                        </form>
                    </div>
                </section>
            @endif

            @if($participation && $session->status === 'completed' && $participation->attended_at && !$participation->confirmed_at && !$isHost)
                <section class="alert alert-info mt-3">
                    <h2 class="h6 fw-bold">Confirm your participation</h2>
                    <p class="small">Only confirm if you attended. This credits the scheduled duration to your progress and awards {{ config('learning.session_points') }} points to you and, once, to the host.</p>
                    <form method="POST" action="{{ route('study-sessions.confirm', $session) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">I attended this session</button>
                    </form>
                </section>
            @elseif($participation && $session->status === 'completed')
                <div class="alert alert-secondary mt-3">
                    {{ $participation->confirmed_at ? 'Your participation is confirmed.' : ($participation->attended_at ? 'Waiting for an attendee to confirm the session.' : 'The host did not mark you present. Contact the host if this is incorrect.') }}
                </div>
            @endif
        </div>

        <aside class="col-12 col-lg-4">
            <section class="card ph-session-detail-card mb-3">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 fw-bold mb-3">Your booking</h2>
                    @if($session->status === 'scheduled')
                        @if($isHost)
                            <p class="ph-session-booking-note"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>You're hosting this session.</p>
                            <a href="{{ route('schedule') }}#bookings" class="btn btn-primary w-100 mb-2">View My Schedule</a>
                            <form method="POST" action="{{ route('study-sessions.cancel', $session) }}" onsubmit="return confirm('Cancel this session? All participants will lose their booking.');">
                                @csrf
                                <button class="btn btn-outline-danger w-100" type="submit">Cancel session</button>
                            </form>
                        @elseif($participation)
                            <p class="ph-session-booking-note"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>Your participation is booked.</p>
                            <a href="{{ route('schedule') }}#bookings" class="btn btn-primary w-100 mb-2">View My Schedule</a>
                            @if($session->starts_at->isFuture())
                                <form method="POST" action="{{ route('study-sessions.leave', $session) }}" onsubmit="return confirm('Leave this session? Your spot will become available to others.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-secondary w-100" type="submit">Leave session</button>
                                </form>
                            @endif
                        @elseif($session->starts_at->isFuture())
                            <p class="text-body-secondary small">Join this session to access the meeting information and participant list.</p>
                            <form method="POST" action="{{ route('study-sessions.join', $session) }}">
                                @csrf
                                <button class="btn btn-primary w-100" type="submit" @disabled($participantCount >= $session->capacity)>{{ $participantCount >= $session->capacity ? 'Session full' : 'Join session' }}</button>
                            </form>
                        @else
                            <p class="text-body-secondary">This session has already started.</p>
                        @endif
                    @else
                        <p class="text-body-secondary small mb-3">This session is {{ $session->status }}.</p>
                        @if($participation)
                            <a href="{{ route('schedule') }}#bookings" class="btn btn-outline-secondary w-100">View My Schedule</a>
                        @endif
                    @endif
                </div>
            </section>

            @if($participation)
                <section class="card ph-session-detail-card" aria-labelledby="phSessionMembersTitle">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <h2 class="h5 fw-bold mb-0" id="phSessionMembersTitle">Participants</h2>
                            <span class="small text-body-secondary">{{ $participantCount }} / {{ $session->capacity }}</span>
                        </div>
                        <div class="ph-session-member-list">
                            @foreach($session->participants as $person)
                                <div class="ph-session-member-row">
                                    <div class="ph-session-member-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->user->first_name ?? '?', 0, 1)) }}</div>
                                    <div class="flex-grow-1 min-w-0">
                                        <p class="small fw-semibold mb-0 text-truncate">{{ $person->user->first_name }} {{ $person->user->last_name }}</p>
                                        <span class="small text-body-secondary">{{ $person->user_id === $session->host_id ? 'Session organizer' : 'Participant' }}</span>
                                    </div>
                                    @if($person->user_id === $session->host_id)
                                        <span class="ph-session-member-role">Host</span>
                                    @endif
                                    @if($person->confirmed_at)
                                        <i class="bi bi-check-circle-fill text-success" title="Participation confirmed" aria-label="Participation confirmed"></i>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
