@extends('layouts.dashboard')

@section('title', 'Schedule')
@section('page-title', 'Schedule')

@push('styles')
<style>
  .availability-tile {
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-radius: 0.75rem;
    min-height: 140px;
    padding: 1rem;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, background-color .15s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .availability-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08);
  }

  .availability-tile.active {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .15);
  }

  .availability-day {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: .5rem;
  }

  .availability-status {
    font-size: .875rem;
    opacity: .8;
    margin-bottom: .5rem;
  }

  .availability-time {
    font-size: 1rem;
    font-weight: 700;
    color: var(--bs-primary);
    word-break: break-word;
  }

  .availability-empty {
    color: var(--bs-secondary-color);
    font-weight: 500;
  }

  @media (max-width: 575.98px) {
    .availability-tile {
      min-height: 120px;
      padding: .875rem;
    }

    .availability-day {
      font-size: .95rem;
    }

    .availability-time {
      font-size: .95rem;
    }
  }
</style>
@endpush

@section('content')
<h2 class="mb-3 fw-bold">Schedule</h2>
<!-- Week Availability  -->
<div class="card shadow-sm">
  <div class="card-header">
    <h3 class="card-title mb-0">Weekly Availability</h3>
  </div>

  <div class="card-body">
    <div class="row g-3 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-7" id="weekTiles">
    </div>
  </div>
</div>

<!-- Time Range -->
<div class="modal fade" id="availabilityModal" tabindex="-1" aria-labelledby="availabilityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="availabilityForm">
        <div class="modal-header">
          <h5 class="modal-title" id="availabilityModalLabel">Set Availability</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="selectedDayIndex">

          <div class="mb-3">
            <label for="selectedDayLabel" class="form-label">Day</label>
            <input type="text" class="form-control" id="selectedDayLabel" readonly>
          </div>

          <div class="row g-3">
            <div class="col-6">
              <label for="startTime" class="form-label">Start time</label>
              <input type="time" class="form-control" id="startTime" required>
            </div>
            <div class="col-6">
              <label for="endTime" class="form-label">End time</label>
              <input type="time" class="form-control" id="endTime" required>
            </div>
          </div>

          <div class="form-text mt-3">
            Pick your available time range for this day.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary me-auto" id="clearTimeBtn">Clear</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save availability</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  window.addEventListener('load', function () {
    var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    var storageKey = 'weekly-availability';
    var $weekTiles = $('#weekTiles');
    var modalEl = document.getElementById('availabilityModal');
    var modal = new bootstrap.Modal(modalEl);

    var $selectedDayIndex = $('#selectedDayIndex');
    var $selectedDayLabel = $('#selectedDayLabel');
    var $startTimeInput = $('#startTime');
    var $endTimeInput = $('#endTime');
    var $form = $('#availabilityForm');
    var $clearTimeBtn = $('#clearTimeBtn');

    var availability = loadAvailability();

    function loadAvailability() {
      try {
        var saved = localStorage.getItem(storageKey);
        return saved ? JSON.parse(saved) : {};
      } catch (e) {
        return {};
      }
    }

    function saveAvailability() {
      localStorage.setItem(storageKey, JSON.stringify(availability));
    }

    function formatTime(time24) {
      if (!time24) return '';

      var parts = time24.split(':');
      var hour = parseInt(parts[0], 10);
      var minute = parts[1];
      var ampm = hour >= 12 ? 'PM' : 'AM';

      hour = hour % 12 || 12;

      return hour + ':' + minute + ' ' + ampm;
    }

    function getDisplayRange(dayIndex) {
      var data = availability[dayIndex];

      if (!data || !data.start || !data.end) {
        return '';
      }

      return formatTime(data.start) + ' - ' + formatTime(data.end);
    }

    function renderTiles() {
      $weekTiles.empty();

      $.each(days, function (index, day) {
        var range = getDisplayRange(index);
        var hasAvailability = !!range;

        var tileHtml = `
          <div class="col">
            <div class="availability-tile ${hasAvailability ? 'active' : ''}" data-day-index="${index}">
              <div>
                <div class="availability-day">${day}</div>
                <div class="availability-status">${hasAvailability ? 'Available' : 'No time selected'}</div>
              </div>
              <div class="availability-time ${hasAvailability ? '' : 'availability-empty'}">
                ${hasAvailability ? range : 'Tap to set time'}
              </div>
            </div>
          </div>
        `;

        $weekTiles.append(tileHtml);
      });
    }

    function openModal(dayIndex) {
      var existing = availability[dayIndex] || {};

      $selectedDayIndex.val(dayIndex);
      $selectedDayLabel.val(days[dayIndex]);
      $startTimeInput.val(existing.start || '');
      $endTimeInput.val(existing.end || '');

      modal.show();
    }

    $weekTiles.on('click', '.availability-tile', function () {
      var dayIndex = $(this).data('day-index');
      openModal(dayIndex);
    });

    $form.on('submit', function (e) {
      e.preventDefault();

      var dayIndex = $selectedDayIndex.val();
      var start = $startTimeInput.val();
      var end = $endTimeInput.val();

      if (!start || !end) {
        return;
      }

      if (start >= end) {
        toast('error', 'End time must be later than start time');
        return;
      }

      availability[dayIndex] = {
        start: start,
        end: end
      };

      saveAvailability();
      renderTiles();
      modal.hide();
      toast('success', 'Availability saved');
    });

    $clearTimeBtn.on('click', function () {
      var dayIndex = $selectedDayIndex.val();

      delete availability[dayIndex];
      saveAvailability();
      renderTiles();
      modal.hide();
    });

    renderTiles();
  });
</script>
@endpush