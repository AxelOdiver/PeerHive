@extends('layouts.dashboard')

@section('title', 'My Schedule')

@section('content')
<div class="ph-schedule-page">
    <header class="mb-3">
        <h1 class="h2 fw-bold mb-1">My Schedule</h1>
        <p class="text-body-secondary mb-0">Set your weekly availability and review your booked study sessions.</p>
    </header>

    <nav class="ph-tabs-nav mb-4" data-ph-tabs data-ph-tabs-key="schedule" aria-label="My Schedule sections">
        <div class="ph-tabs-track" role="tablist">
            <button class="ph-tabs-item is-active" id="availabilityTab" type="button" role="tab"
                data-bs-toggle="pill" data-bs-target="#availabilityPanel" aria-controls="availabilityPanel" aria-selected="true">
                My Availability
            </button>
            <button class="ph-tabs-item" id="bookingsTab" type="button" role="tab"
                data-bs-toggle="pill" data-bs-target="#bookingsPanel" aria-controls="bookingsPanel" aria-selected="false">
                Booked Sessions
            </button>
            <span class="ph-tabs-indicator" aria-hidden="true"></span>
        </div>
    </nav>

    <div class="tab-content">
        <section class="tab-pane fade show active ph-tabs-page-panel" id="availabilityPanel" role="tabpanel" aria-labelledby="availabilityTab">
            <div class="card ph-schedule-card">
                <div class="card-header ph-schedule-availability-header">
                    <div class="ph-schedule-availability-heading">
                        <h2 class="h5 fw-bold mb-1">Your Weekly Availability</h2>
                        <p class="text-body-secondary small mb-0">You can save multiple time slots for each day.</p>
                    </div>
                    <p class="ph-schedule-timezone-note small mb-0">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <span>Times are entered in your learning timezone: <strong>{{ $timezone }}</strong></span>
                    </p>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4" id="weekTiles" aria-live="polite"></div>
                </div>
            </div>
        </section>

        <section class="tab-pane fade" id="bookingsPanel" role="tabpanel" aria-labelledby="bookingsTab">
            <div class="card ph-schedule-card mb-3">
                <div class="card-header ph-calendar-header">
                    <div class="ph-calendar-month-nav" role="group" aria-label="Browse booking months">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="calendarPrevious" aria-label="Previous month"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <h2 class="h5 fw-bold mb-0" id="bookingCalendarTitle" aria-live="polite"></h2>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="calendarNext" aria-label="Next month"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="small text-body-secondary mb-3">Your confirmed bookings · {{ $timezone }}</p>
                    <div id="bookingCalendar"></div>
                </div>
            </div>
            <script type="application/json" id="bookingCalendarEvents">@json($bookingEvents)</script>
            <x-session-bookings :bookings="$bookings" :confirmations="$confirmations" />
        </section>
    </div>

    <x-modal id="availabilityModal" title="Set Availability">
        <form id="availabilityForm" autocomplete="off">
            <input type="hidden" id="selectedDayIndex">
            <p class="text-body-secondary small mb-3">Add as many free periods as you need, up to eight per day.</p>
            <div class="mb-3">
                <label for="selectedDayLabel" class="form-label fw-semibold">Day</label>
                <input type="text" id="selectedDayLabel" class="form-control" readonly>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-2 gap-2">
                <strong>Available time slots</strong>
                <span class="small text-body-secondary" id="phAvailabilitySlotCount">0 slots</span>
            </div>
            <div id="phAvailabilitySlots" class="d-grid gap-2"></div>
            <button type="button" class="btn btn-outline-secondary w-100 mt-3 ph-add-slot" id="phAddAvailabilitySlot">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add another time slot
            </button>
            <p class="form-text mt-3" id="phAvailabilityTimezone">Times are based on your selected learning timezone: {{ $timezone }}. Slots cannot overlap or cross midnight.</p>
            <p class="alert alert-danger small mt-3 mb-0" role="alert" id="phAvailabilityErrors" hidden></p>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-outline-danger me-auto" id="clearTimeBtn">Clear day</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="phSaveAvailability" form="availabilityForm">Save availability</button>
        </x-slot:footer>
    </x-modal>
</div>
@endsection
