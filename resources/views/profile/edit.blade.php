@extends('layouts.dashboard')

@section('title', 'Profile')
@section('page-title', 'Profile')

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
<h2 class="mb-3 fw-bold">Profile</h2>
<div class="row align-items-stretch row-cols-1 row-cols-xxl-2 g-3">
  <!-- Profile Information Card -->
  <div class="d-flex mb-lg-0"> 
    <div class="card w-100">     
      <div class="card-header d-flex justify-content-center">
        <div class="position-relative d-inline-block">          
          <img alt="Profile" id="profileImage" class="rounded-circle shadow-sm" width="150" height="150" style="object-fit: cover;">
          <label for="profilePictureUpload" class="btn btn-light btn-sm rounded-circle position-absolute bottom-0 end-0 d-flex align-items-center justify-content-center shadow-sm m-0" style="width: 40px; height: 40px; transform: translate(-10%, -10%); cursor: pointer;">
            <i class="bi bi-camera-fill fs-5 text-secondary"></i> 
            <input id="profilePictureUpload" type="file" class="d-none" accept="image/*">
          </label>
        </div>
      </div>
      <div class="card-body">
        <form id="form" action="{{ route('profile.update') }}" data-user-id="{{ auth()->user()->id }}" enctype="multipart/form-data">
          <div class="mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input
            id="first_name"
            type="text"
            name="first_name"
            class="form-control"
            required
            >
            <div class="invalid-feedback" data-error-for="first_name"></div>
          </div>
          <div class="mb-3">
            <label for="middle_name" class="form-label">Middle Name</label>
            <input
            id="middle_name"
            type="text"
            name="middle_name"
            class="form-control"
            >
            <div class="invalid-feedback" data-error-for="middle_name"></div>
          </div>
          <div class="mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input
            id="last_name"
            type="text"
            name="last_name"
            class="form-control"
            required
            >
            <div class="invalid-feedback" data-error-for="last_name"></div>
          </div>
          
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input
            id="email"
            type="email"
            name="email"
            class="form-control"
            required
            >
            <div class="invalid-feedback" data-error-for="email"></div>
          </div>
          
          <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <input
            id="password"
            type="password"
            name="password"
            class="form-control"
            autocomplete="new-password"
            >
            <div class="form-text">Leave blank to keep your current password.</div>
            <div class="invalid-feedback" data-error-for="password"></div>
          </div>
          <div id="confirmPasswordWrap" class="fade-field">
            <div class="mb-3">
              <label for="password_confirmation" class="form-label">Confirm New Password</label>
              <input
              id="password_confirmation"
              type="password"
              name="password_confirmation"
              class="form-control"
              >
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- Right Column: Availability & Account Status -->
  <div class="d-flex flex-column gap-1">
    <!-- Weekly Availability Card -->
    <div class="card flex-fill mb-3">
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
    </div>
    <!-- Account Status Card -->
    <div class="card flex-fill">
      <div class="card-body">
        <h3 class="h5 mb-3">Account Status</h3>
        <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2 mb-3">
          <span class="text-body-secondary">Password</span>
          <span class="badge text-bg-success" id="profilePasswordStatus">Unchanged</span>
        </div>
        <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2">
          <span class="text-body-secondary">Profile Photo</span>
          <span class="badge text-bg-secondary" id="profilePhotoStatus">No photo</span>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Form Submit Button -->
<div class="mt-3">
  <button type="submit" id="saveChangesBtn" class="btn btn-primary w-100">Save Changes</button>
</div>

<!-- Availability Modal (Time Range Selector) -->
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
