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
    <div class="d-flex justify-content-between align-items-center">
      <h3 class="card-title mb-0">Your Weekly Availability</h3>
      <small class="text-muted">Click a day to set your schedule</small>
    </div>
  </div>

  <div class="card-body">
    <div class="row g-3 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-7" id="weekTiles">
    </div>
  </div>
</div>

<!-- Time Range -->
<x-modal id="availabilityModal" title="Set Availability">
  <form id="availabilityForm">
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
  </form>

  <x-slot:footer>
    <button type="button" class="btn btn-outline-secondary me-auto" id="clearTimeBtn">Clear</button>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-primary" form="availabilityForm">Save availability</button>
  </x-slot:footer>
</x-modal>
@endsection
