@extends('layouts.dashboard')

@section('title', 'Profile')

@section('content')
<h2 class="mb-3 fw-bold">Profile</h2>
<div class="row align-items-stretch row-cols-1 row-cols-lg-2 g-3">
  <!-- Profile Information Card -->
  <div class="d-flex mb-lg-0"> 
    <div class="card w-100">     
      <div class="card-header d-flex justify-content-center">
        <div class="position-relative d-inline-block my-2" style="width: 150px; height: 150px;" id="profileAvatarContainer"> 
        
          <!-- The Profile Picture or the Default Placeholder Image -->
          <img src="{{ auth()->user()->profile_picture_url ?? asset('images/profile-placeholder.jpeg') }}"
            alt="Profile" 
            id="profileImage" 
            class="rounded-circle shadow-sm w-100 h-100 {{ auth()->user()->profile_frame_class }}"
            style="object-fit: cover;">
    
          <!-- Remove Picture Button -->
          <form action="{{ route('profile.picture.remove') }}" method="POST" id="removePictureForm" class="position-absolute top-0 end-0 m-0 {{ auth()->user()->profile_picture ? '' : 'd-none' }}" style="z-index: 10;">
            @csrf
            @method('DELETE')
            <button type="button" id="removePictureBtn" class="btn btn-danger btn-sm rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;" title="Remove Picture">
              <i class="bi bi-trash3-fill fs-6 text-white"></i>
            </button>
          </form>
        
          <!-- Camera Upload Button -->
          <label for="profilePictureUpload" class="btn btn-light btn-sm rounded-circle position-absolute bottom-0 end-0 d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; cursor: pointer; z-index: 10;">
            <i class="bi bi-camera-fill fs-5 text-secondary"></i> 
            <input id="profilePictureUpload" type="file" class="d-none" accept="image/*" name="profile_picture">
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
            <label for="username" class="form-label">Username (optional)</label>
            <input id="username" name="username" class="form-control" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" value="{{ auth()->user()->username }}" aria-describedby="username-help">
            <div class="invalid-feedback" data-error-for="username"></div>
            <div class="form-text" id="username-help">3–30 letters, numbers or underscores. Your username is searchable by other members.</div>
          </div>
          <fieldset class="border rounded p-3 mb-3">
            <legend class="float-none w-auto px-2 fs-6">Degree program, skills and school</legend>
            <p class="small text-muted">Optional. Visible fields are shared with other signed-in members. Keep city/campus general; do not enter a home address.</p>
            <div id="learning-interests" class="mb-3">
              <label class="form-label" for="learning_interests_picker">I want to learn</label>
              @include('profile.topic-picker', ['field' => 'learning_interests', 'pickerLabel' => 'Add a topic to learn', 'selected' => auth()->user()->learning_interests ?? []])
              <div class="form-text">Choose up to 10 topics. Recommendations match these to peers’ approved teaching topics, across degree programs.</div>
              <div class="invalid-feedback d-block" data-error-for="learning_interests"></div>
            </div>
            @foreach(['course' => ['Degree program', 120, ''], 'expertise' => ['Skills & interests', 100, ''], 'school' => ['School', 160, 'School or university name'], 'school_location' => ['School location', 160, 'City or campus']] as $field => [$label, $limit, $placeholder])
              <div class="mb-3">
                <label for="{{ $field === 'expertise' ? 'expertise_picker' : $field }}" class="form-label">{{ $label }}</label>
                @if($field === 'course')
                  <select id="course" name="course" class="form-select"><option value="">Select a degree program</option>
                    @foreach($catalogPrograms as $program)<option value="{{ $program->name }}" @selected(auth()->user()->course === $program->name)>{{ $program->name }}</option>@endforeach
                    @if(auth()->user()->course && !$catalogPrograms->contains('name', auth()->user()->course))<option value="{{ auth()->user()->course }}" selected>{{ auth()->user()->course }} (not in shared list)</option>@endif
                    <option value="__other">Other / Not listed</option>
                  </select>
                  <input type="hidden" name="course_unlisted" id="courseUnlisted" value="0">
                  <div id="courseOtherWrap" class="mt-2" hidden><label for="course_other" class="form-label">Your degree program</label><input id="course_other" name="course_other" maxlength="120" class="form-control"><div class="form-text">Saved on your profile immediately. Admin approval adds it to everyone’s program list.</div><div class="invalid-feedback" data-error-for="course_other"></div></div>
                @elseif($field === 'expertise')
                  @include('profile.topic-picker', ['field' => 'expertise', 'pickerLabel' => 'Add a skill or interest', 'selected' => auth()->user()->expertise ?? []])
                @else
                  <input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="{{ $limit }}" placeholder="{{ $placeholder }}" value="{{ auth()->user()->{$field} }}">
                @endif
                <div class="invalid-feedback {{ $field === 'expertise' ? 'd-block' : '' }}" data-error-for="{{ $field }}"></div>
                @if($field === 'expertise')
                  <div class="form-text">Choose up to 10 topics. These are self-described skills, not approved teaching qualifications.</div>
                @endif
                <div class="form-check form-switch mt-2">
                  <input type="hidden" name="show_{{ $field }}" value="0">
                  <input type="checkbox" class="form-check-input" id="show_{{ $field }}" name="show_{{ $field }}" value="1" @checked(auth()->user()->{'show_'.$field})>
                  <label class="form-check-label small" for="show_{{ $field }}">Show {{ strtolower($label) }} to members</label>
                </div>
              </div>
            @endforeach
          </fieldset>

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
  <div class="d-flex flex-column gap-1 align-self-start">
    <!-- Description Card -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Description</h3>
        <button type="button" id="editDescriptionBtn" class="btn btn-sm btn-primary ms-auto">Edit</button>
      </div>
      <div class="card-body">
        <div id="descriptionViewMode"><p id="descriptionText" class="form-text fs-5 mb-0">{{ auth()->user()->description ?? 'No description yet.' }}</p></div>
        <div id="descriptionEditMode" style="display: none;">
          <textarea name="description" class="form-control form-control-lg" rows="4" maxlength="200" placeholder="Enter your description">{{ old('description', auth()->user()->description) }}</textarea>
          <div class="text-end mt-2"><button type="button" id="cancelEditBtn" class="btn btn-sm btn-secondary">Cancel</button></div>
        </div>
      </div>
    </div>
    <!-- Weekly Availability Card -->
    <div class="card mb-3">
      <div class="card shadow-sm">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Your Weekly Availability</h3>
            <small class="text-muted">Click a day to set your schedule</small>
          </div>
        </div>
        
        <div class="card-body">
          <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-4" id="weekTiles">
          </div>
        </div>
      </div>
    </div>
    @include('profile.catalog-suggestions')
    @include('profile.teaching-qualifications')
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

<div id="server-messages" 
  data-errors="{{ json_encode($errors->all()) }}" 
  data-success="{{ session('success') }}" 
  class="d-none">
</div>

@endsection
