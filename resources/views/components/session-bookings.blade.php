@props(['bookings', 'confirmations'])
@if($confirmations->isNotEmpty())
    <div class="alert alert-info">
        <h2 class="h6 fw-bold">Confirm your attendance</h2>
        @foreach($confirmations as $record)
            <a class="d-block" href="{{ route('study-sessions.show', $record->session) }}">{{ $record->session->title }}</a>
        @endforeach
    </div>
@endif
<section class="card ph-schedule-card ph-bookings-card">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Booked sessions</h2>
                <p class="small text-body-secondary mb-0">Your upcoming and ongoing study sessions.</p>
            </div>
            <a href="{{ route('study-sessions.index') }}" class="btn btn-outline-secondary btn-sm">Find sessions <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        </div>
        @forelse($bookings as $booking)
            @php($session = $booking->session)
            <article class="ph-booked-session">
                <div class="d-flex justify-content-between gap-3 align-items-start flex-wrap">
                    <div class="flex-grow-1 min-w-0">
                        <div class="mb-2"><span class="ph-booked-subject">{{ $session->subject }}</span></div>
                        <h3 class="h6 fw-bold mb-2"><a class="text-body text-decoration-none" href="{{ route('study-sessions.show', $session) }}">{{ $session->title }}</a></h3>
                        <div class="d-flex flex-column gap-2 small text-body-secondary">
                            <span><i class="bi bi-calendar-event me-2" aria-hidden="true"></i>{{ $session->starts_at->copy()->setTimezone(auth()->user()->learning_timezone)->format('M j, Y · g:i A') }} · {{ auth()->user()->learning_timezone }}</span>
                            <span><i class="bi bi-person me-2" aria-hidden="true"></i>{{ $session->host_id === auth()->id() ? 'You are hosting' : ('Hosted by '.($session->host?->first_name ?? 'Student').' '.($session->host?->last_name ?? '')) }}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-start align-items-sm-end gap-2">
                        <span class="ph-booking-status">{{ $session->host_id === auth()->id() ? 'Hosting' : 'Booked' }}{{ $session->ends_at->isPast() ? ' · Awaiting completion' : '' }}</span>
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('study-sessions.show', $session) }}">View session <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                    </div>
                </div>
            </article>
        @empty
            <div class="ph-bookings-empty text-center p-4">
                <i class="bi bi-calendar2-check fs-2" aria-hidden="true"></i>
                <p class="fw-semibold mt-2 mb-1">No booked sessions yet</p>
                <p class="small text-body-secondary mb-0">Your weekly availability is separate from a session booking.</p>
            </div>
        @endforelse
    </div>
</section>
