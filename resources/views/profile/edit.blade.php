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
    <!-- Description Card -->
    <div class="card flex-fill">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Description</h3>
        <button type="button" id="editDescriptionBtn" class="btn btn-sm btn-primary justify-content-end d-flex align-items-center ms-auto">Edit</button>
      </div>
      <div class="card-body">
        <div id="descriptionViewMode">
          <p id="descriptionText" class="form-text m-2 fs-5">
            {{ auth()->user()->description ?? 'No description yet.' }}
          </p>
        </div>
        <div id="descriptionEditMode" style="display: none;">
          <textarea name="description" 
          class="form-control form-control-lg" 
          rows="4" 
          maxlength="200"
          placeholder="Enter your description">{{ old('description', auth()->user()->description) }}</textarea>
          <div class="text-end mt-2">
            <button type="button" id="cancelEditBtn" class="btn btn-sm btn-secondary">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Qualification Request Form -->
@php
$latestQualification = \App\Models\UserSubjectQualification::where('user_id', auth()->id())->latest()->first();
$attempts = \App\Models\UserSubjectQualification::where('user_id', auth()->id())->count();
@endphp

@if($latestQualification && ($latestQualification->status === 'pending' || $latestQualification->status === 'approved' || $attempts >= 3))
<div class="card flex fill mt-4 p-4 text-center shadow-sm border-0 rounded-4">
  <h5 class="fw-bold mt-2"><i class="bi bi-award text-primary me-2"></i> {{ $latestQualification->subject_name }}</h5>
  <p class="text-muted mb-4">Application Status</p>
  
  @if($latestQualification->status === 'pending')
  <div class="alert alert-warning rounded-4 d-inline-block px-5 mb-0">
    <i class="bi bi-hourglass-split me-2"></i> Your application is <strong>Pending Review</strong> by an Admin.
  </div>
  @elseif($latestQualification->status === 'approved')
  <div class="alert alert-success rounded-4 d-inline-block px-5 mb-0">
    <i class="bi bi-check-circle-fill me-2"></i> You are officially <strong>Approved</strong> to teach this subject!
  </div>
  @else
  <div class="alert alert-danger rounded-4 d-inline-block px-5 mb-0">
    <i class="bi bi-x-circle-fill me-2"></i> Your applications were <strong>Rejected</strong>.<br><br>
    <small>You have reached the maximum number of attempts (3/3). Please kindly contact admin to appeal your submission.</small>
  </div>
  @endif
</div>

@else
<form id="qualificationForm" action="{{ route('qualifications.store') }}" method="POST" enctype="multipart/form-data">
  @csrf
  <div class="card flex fill mt-4 p-4 shadow-sm border-0 rounded-4">
    <h5 class="mb-4"><i class="bi bi-file-earmark-check me-2"></i> Submit Teaching Qualification</h5>
    
    @if($latestQualification && $latestQualification->status === 'rejected')
    <div class="alert alert-danger rounded-3 mb-4 border-0">
      <i class="bi bi-exclamation-triangle-fill me-2"></i> Your previous application was rejected. You have <strong>{{ 3 - $attempts }}</strong> attempts remaining.
    </div>
    @endif
    
    <div class="mb-3">
      <label class="form-label">Subject You Want to Teach</label>
      <select name="subject" class="form-select" required>
        <option value="" disabled selected>Select a subject...</option>
        <option value="Coding">Coding</option>
        <option value="Foreign Language">Foreign Language</option>
        <option value="Graphic Design">Graphic Design</option>
      </select>
    </div>
    
    <div class="mb-2">
      <label class="form-label">Upload Proof (Grade or Certificate)</label>
      <input type="file" name="proof_document" class="form-control" accept=".jpg,.png,.pdf" required>
      <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i> Max file size: 10MB.</small>
    </div>
  </div>
  
  <div class="text-start">
    <button type="submit" class="btn btn-primary mt-3 px-4 rounded-pill">
      Submit for Approval
    </button>
  </div>
</form>
@endif

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

<div id="server-messages" 
data-errors="{{ json_encode($errors->all()) }}" 
data-success="{{ session('success') }}" 
class="d-none">
</div>
@endsection
