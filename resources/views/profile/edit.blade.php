@extends('layouts.dashboard')

@section('title', 'Profile Settings')

@section('content')
<div class="ph-settings-shell">
  <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <p class="small text-body-secondary fw-semibold text-uppercase mb-1">My account</p>
      <h1 class="h3 fw-bold mb-1">Profile settings</h1>
      <p class="text-body-secondary mb-0">Your personal details, learning preferences, and teaching applications, organized in one place.</p>
    </div>
    <a href="{{ route('users.profile', auth()->user()) }}" class="btn btn-outline-primary ph-settings-view-profile"><i class="bi bi-person me-2" aria-hidden="true"></i>View my profile</a>
  </header>

  @if(session('catalog_status'))<div class="alert alert-success" role="status">{{ session('catalog_status') }}</div>@endif
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

  <nav class="ph-settings-tabs-nav mb-3" aria-label="Profile settings categories">
    <div class="nav nav-tabs ph-settings-tabs flex-nowrap" id="profileSettingsTabs" role="tablist">
      @foreach([
        ['general', 'General', 'bi-person'],
        ['academic', 'Academic', 'bi-mortarboard'],
        ['skills', 'Skills & Interests', 'bi-lightbulb'],
        ['verification', 'Teaching Verification', 'bi-patch-check'],
        ['privacy', 'Privacy', 'bi-shield-check'],
        ['account', 'Account', 'bi-gear'],
      ] as [$key, $label, $icon])
        <button id="ph-settings-tab-{{ $key }}" class="nav-link {{ $key === 'general' ? 'active' : '' }}" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#ph-settings-{{ $key }}" data-ph-settings-tab="{{ $key }}" aria-controls="ph-settings-{{ $key }}" aria-selected="{{ $key === 'general' ? 'true' : 'false' }}">
          <i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
        </button>
      @endforeach
    </div>
  </nav>

  <div class="ph-settings-content" id="profileSettingsContent">
    <form id="form" action="{{ route('profile.update') }}" data-user-id="{{ auth()->id() }}" method="POST" enctype="multipart/form-data" novalidate>
      @csrf
      <input type="hidden" name="email" id="profileUnchangedEmail" value="{{ auth()->user()->email }}">

      <section class="tab-pane fade show active ph-settings-pane" id="ph-settings-general" role="tabpanel" aria-labelledby="ph-settings-tab-general" tabindex="0">
        <div class="ph-settings-panel">
          <div class="ph-settings-heading"><h2 class="h5 fw-bold mb-1">General information</h2><p class="text-body-secondary small mb-0">Update the details students see on your profile.</p></div>
          <div class="ph-settings-avatar-row mb-4">
            <div class="position-relative flex-shrink-0" id="profileAvatarContainer" style="width:112px;height:112px">
              <img src="{{ auth()->user()->profile_picture_url ?? asset('images/profile-placeholder.jpeg') }}" alt="Your profile picture" id="profileImage" class="rounded-circle w-100 h-100 {{ auth()->user()->profile_frame_class }}" style="object-fit:cover">
              <button type="button" id="removePictureBtn" class="btn btn-danger btn-sm rounded-circle position-absolute top-0 end-0 {{ auth()->user()->profile_picture ? '' : 'd-none' }}" title="Remove picture" aria-label="Remove profile picture"><i class="bi bi-trash3" aria-hidden="true"></i></button>
              <label for="profilePictureUpload" class="btn btn-light border rounded-circle position-absolute bottom-0 end-0" title="Upload profile picture"><i class="bi bi-camera" aria-hidden="true"></i></label>
            </div>
            <div><p class="fw-semibold mb-1" id="profileSummaryName">{{ trim(auth()->user()->first_name.' '.auth()->user()->last_name) }}</p><input id="profilePictureUpload" type="file" class="form-control form-control-sm" accept="image/*" name="profile_picture" aria-label="Choose a profile photo"></div>
          </div>
          <p class="form-text mb-3">Names can use letters, spaces, apostrophes, or hyphens. Numbers and other symbols are not allowed.</p>
          <div class="row g-3">
            <div class="col-12 col-md-6"><label for="first_name" class="form-label">First name</label><input id="first_name" type="text" name="first_name" class="form-control" data-ph-person-name="true" maxlength="255" required><div class="invalid-feedback" data-error-for="first_name"></div></div>
            <div class="col-12 col-md-6"><label for="last_name" class="form-label">Last name</label><input id="last_name" type="text" name="last_name" class="form-control" data-ph-person-name="true" maxlength="255" required><div class="invalid-feedback" data-error-for="last_name"></div></div>
            <div class="col-12 col-md-6"><label for="middle_name" class="form-label">Middle name</label><input id="middle_name" type="text" name="middle_name" class="form-control" data-ph-person-name="true" maxlength="255"><div class="invalid-feedback" data-error-for="middle_name"></div></div>
            <div class="col-12 col-md-6"><label for="username" class="form-label">Username</label><input id="username" type="text" name="username" class="form-control" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" value="{{ auth()->user()->username }}" aria-describedby="username-help"><div class="invalid-feedback" data-error-for="username"></div><div class="form-text" id="username-help">3–30 letters, numbers, or underscores. Your username can be searched by other students.</div></div>
          </div>
          <div class="mt-4"><div class="d-flex align-items-center justify-content-between mb-2"><label for="description" class="form-label mb-0">About me</label><button type="button" id="editDescriptionBtn" class="btn btn-sm btn-outline-secondary">Edit bio</button></div><div id="descriptionViewMode" class="ph-settings-bio-read"><p id="descriptionText" class="mb-0 text-body-secondary">{{ auth()->user()->description ?? 'No description yet.' }}</p></div><div id="descriptionEditMode" style="display:none"><textarea id="description" name="description" class="form-control" rows="4" maxlength="200" placeholder="Introduce yourself to your peers">{{ old('description', auth()->user()->description) }}</textarea><button type="button" id="cancelEditBtn" class="btn btn-sm btn-outline-secondary mt-2">Cancel editing</button></div></div>
          <div class="ph-settings-actions"><button type="button" class="btn btn-primary ph-settings-save" data-ph-settings-save="general"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Save general</button></div>
        </div>
      </section>

      <section class="tab-pane fade ph-settings-pane" id="ph-settings-academic" role="tabpanel" aria-labelledby="ph-settings-tab-academic" tabindex="0">
        <div class="ph-settings-panel"><div class="ph-settings-heading"><h2 class="h5 fw-bold mb-1">Academic information</h2><p class="small text-body-secondary mb-0">Your program and campus details. Choose what to share in the Privacy tab.</p></div>
          <div class="row g-3">
            <div class="col-12"><label for="course" class="form-label">Degree program</label><select id="course" name="course" class="form-select"><option value="">Select a degree program</option>@foreach($catalogPrograms as $program)<option value="{{ $program->name }}" @selected(auth()->user()->course === $program->name)>{{ $program->name }}</option>@endforeach @if(auth()->user()->course && !$catalogPrograms->contains('name', auth()->user()->course))<option value="{{ auth()->user()->course }}" selected>{{ auth()->user()->course }} (not in shared list)</option>@endif<option value="__other">Other / Not listed</option></select><input type="hidden" id="courseUnlisted" name="course_unlisted" value="0"><div id="courseOtherWrap" class="mt-2" hidden><label for="course_other" class="form-label">Your degree program</label><input id="course_other" name="course_other" class="form-control" maxlength="120"><div class="form-text">Saved to your profile now; added to the shared list after approval.</div><div class="invalid-feedback" data-error-for="course_other"></div></div><div class="invalid-feedback" data-error-for="course"></div></div>
            <div class="col-12 col-md-6"><label for="school" class="form-label">School / University</label><input id="school" name="school" class="form-control" maxlength="160" placeholder="School or university name" value="{{ auth()->user()->school }}"><div class="invalid-feedback" data-error-for="school"></div></div>
            <div class="col-12 col-md-6"><label for="school_location" class="form-label">School location</label><input id="school_location" name="school_location" class="form-control" maxlength="160" placeholder="City or campus" value="{{ auth()->user()->school_location }}"><div class="invalid-feedback" data-error-for="school_location"></div></div>
          </div>
          <p class="small text-body-secondary mt-3 mb-0">Keep your campus location general. Do not enter your home address.</p>
          <button type="button" class="btn btn-outline-secondary btn-sm mt-3 ph-suggestion-trigger" data-bs-toggle="modal" data-bs-target="#catalogSuggestionModal" data-catalog-suggest-kind="program"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Suggest a program</button>
          <div class="ph-settings-actions"><button type="button" class="btn btn-primary ph-settings-save" data-ph-settings-save="academic"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Save academic</button></div>
        </div>
      </section>

      <section class="tab-pane fade ph-settings-pane" id="ph-settings-skills" role="tabpanel" aria-labelledby="ph-settings-tab-skills" tabindex="0">
        <div class="ph-settings-panel"><div class="ph-settings-heading"><h2 class="h5 fw-bold mb-1">Skills &amp; interests</h2><p class="small text-body-secondary mb-0">Choose what you know and what you want to learn. These are different from verified teaching topics.</p></div>
          <div class="row g-4">
            <div class="col-12 col-lg-6"><div class="ph-settings-subcard h-100"><label class="form-label fw-semibold" for="expertise_picker">My skills</label>@include('profile.topic-picker', ['field' => 'expertise', 'pickerLabel' => 'Add a skill or interest', 'selected' => auth()->user()->expertise ?? []])<div class="invalid-feedback d-block" data-error-for="expertise"></div><p class="small text-body-secondary mt-2 mb-0">Up to 10 topics. These are self-described, not teaching qualifications.</p></div></div>
            <div class="col-12 col-lg-6"><div class="ph-settings-subcard h-100" id="learning-interests"><label class="form-label fw-semibold" for="learning_interests_picker">I want to learn</label>@include('profile.topic-picker', ['field' => 'learning_interests', 'pickerLabel' => 'Add a topic to learn', 'selected' => auth()->user()->learning_interests ?? []])<div class="invalid-feedback d-block" data-error-for="learning_interests"></div><p class="small text-body-secondary mt-2 mb-0">Up to 10 topics. Used to recommend peers who can teach them.</p></div></div>
          </div>
          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3"><button type="button" class="btn btn-outline-secondary btn-sm ph-suggestion-trigger" data-bs-toggle="modal" data-bs-target="#catalogSuggestionModal" data-catalog-suggest-kind="topic"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Suggest a topic</button><a href="{{ route('schedule') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Manage availability</a></div>
          <div class="ph-settings-actions"><button type="button" class="btn btn-primary ph-settings-save" data-ph-settings-save="skills"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Save skills &amp; interests</button></div>
        </div>
      </section>

      <section class="tab-pane fade ph-settings-pane" id="ph-settings-privacy" role="tabpanel" aria-labelledby="ph-settings-tab-privacy" tabindex="0">
        <div class="ph-settings-panel"><div class="ph-settings-heading"><h2 class="h5 fw-bold mb-1">Profile visibility</h2><p class="small text-body-secondary mb-0">Decide which profile details other signed-in members can see.</p></div>
          @foreach(['course' => 'Degree program', 'expertise' => 'Skills & interests', 'school' => 'School', 'school_location' => 'School location'] as $field => $label)
            <div class="ph-settings-privacy-row"><div><label class="form-check-label fw-semibold" for="show_{{ $field }}">Show {{ strtolower($label) }}</label><p class="small text-body-secondary mb-0">Allow other members to view your {{ strtolower($label) }}.</p></div><div class="form-check form-switch m-0"><input type="hidden" name="show_{{ $field }}" value="0"><input type="checkbox" class="form-check-input" role="switch" id="show_{{ $field }}" name="show_{{ $field }}" value="1" @checked(auth()->user()->{'show_'.$field})></div></div><div class="invalid-feedback d-block" data-error-for="show_{{ $field }}"></div>
          @endforeach
          <div class="ph-settings-actions"><button type="button" class="btn btn-primary ph-settings-save" data-ph-settings-save="privacy"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Save privacy</button></div>
        </div>
      </section>

    </form>

    <section class="tab-pane fade ph-settings-pane" id="ph-settings-account" role="tabpanel" aria-labelledby="ph-settings-tab-account" tabindex="0">
      <div class="ph-settings-panel">
        <div class="ph-settings-heading"><h2 class="h5 fw-bold mb-1">Account &amp; security</h2><p class="small text-body-secondary mb-0">Verify an email change or update your password securely. These changes are not automatic.</p></div>
        @if(request('email') === 'verified')<div class="alert alert-success" role="status">Your new email has been verified and updated.</div>@endif
        <div class="ph-settings-security-block">
          <h3 class="h6 fw-bold mb-2"><i class="bi bi-envelope-check me-2" aria-hidden="true"></i>Change email address</h3>
          <p class="small text-body-secondary mb-3">Current email: <strong id="profileSummaryEmail">{{ auth()->user()->email }}</strong>. Confirm your current password, then open the verification link sent to your new address. Your current email stays active until you verify.</p>
          <form id="phEmailChangeForm" action="{{ route('profile.email.request') }}" method="POST" class="row g-3" novalidate>
            @csrf
            <div class="col-12 col-md-6"><label for="ph-new-email" class="form-label">New email address</label><input id="ph-new-email" name="new_email" type="email" class="form-control" autocomplete="email" required maxlength="255"><div class="invalid-feedback" data-security-error="new_email"></div></div>
            <div class="col-12 col-md-6"><label for="ph-email-current-password" class="form-label">Current password</label><div class="input-group has-validation ph-password-visibility-group"><input id="ph-email-current-password" name="current_password" type="password" class="form-control" autocomplete="current-password" required><button type="button" class="btn ph-settings-password-toggle" data-ph-password-toggle="ph-email-current-password" data-ph-password-label="current password for email change" aria-controls="ph-email-current-password" aria-label="Show current password for email change" aria-pressed="false" title="Show current password for email change"><i class="bi bi-eye" aria-hidden="true"></i></button><div class="invalid-feedback" data-security-error="current_password"></div></div></div>
            <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary ph-security-submit"><i class="bi bi-send me-2" aria-hidden="true"></i>Send verification email</button></div>
            <div class="col-12"><p id="ph-email-change-message" class="small mb-0" role="status" aria-live="polite"></p></div>
          </form>
        </div>
        <div class="ph-settings-security-block">
          <h3 class="h6 fw-bold mb-2"><i class="bi bi-key me-2" aria-hidden="true"></i>Change password</h3>
          <p class="small text-body-secondary mb-3">Enter your current password and choose a new password using the same requirements as registration.</p>
          <form id="phPasswordChangeForm" action="{{ route('profile.password.update') }}" method="POST" class="row g-3" novalidate>
            @csrf
            <div class="col-12"><label for="ph-current-password" class="form-label">Current password</label><div class="input-group has-validation ph-password-visibility-group"><input id="ph-current-password" type="password" name="current_password" class="form-control" autocomplete="current-password" required><button type="button" class="btn ph-settings-password-toggle" data-ph-password-toggle="ph-current-password" data-ph-password-label="current password" aria-controls="ph-current-password" aria-label="Show current password" aria-pressed="false" title="Show current password"><i class="bi bi-eye" aria-hidden="true"></i></button><div class="invalid-feedback" data-security-error="current_password"></div></div></div>
            <div class="col-12 col-md-6"><label for="ph-new-password" class="form-label">New password</label><div class="input-group has-validation ph-password-visibility-group"><input id="ph-new-password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" required aria-describedby="ph-password-requirements"><button type="button" class="btn ph-settings-password-toggle" data-ph-password-toggle="ph-new-password" data-ph-password-label="new password" aria-controls="ph-new-password" aria-label="Show new password" aria-pressed="false" title="Show new password"><i class="bi bi-eye" aria-hidden="true"></i></button><div class="invalid-feedback" data-security-error="password"></div></div></div>
            <div class="col-12 col-md-6"><label for="ph-confirm-password" class="form-label">Confirm new password</label><div class="input-group has-validation ph-password-visibility-group"><input id="ph-confirm-password" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required><button type="button" class="btn ph-settings-password-toggle" data-ph-password-toggle="ph-confirm-password" data-ph-password-label="confirmation password" aria-controls="ph-confirm-password" aria-label="Show confirmation password" aria-pressed="false" title="Show confirmation password"><i class="bi bi-eye" aria-hidden="true"></i></button><div class="invalid-feedback" data-security-error="password_confirmation"></div></div></div>
            <div class="col-12"><div id="ph-password-requirements" class="ph-password-requirements small" aria-live="polite"><span data-ph-rule="length"><i class="bi bi-circle me-1"></i>8+ characters</span><span data-ph-rule="uppercase"><i class="bi bi-circle me-1"></i>Uppercase letter</span><span data-ph-rule="number"><i class="bi bi-circle me-1"></i>Number</span><span data-ph-rule="special"><i class="bi bi-circle me-1"></i>Special character</span></div></div>
            <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary ph-security-submit"><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Update password</button></div>
            <div class="col-12"><p id="ph-password-change-message" class="small mb-0" role="status" aria-live="polite"></p></div>
          </form>
        </div>
      </div>
    </section>

    <section class="tab-pane fade ph-settings-pane" id="ph-settings-verification" role="tabpanel" aria-labelledby="ph-settings-tab-verification" tabindex="0">
      <div class="ph-settings-panel"><div class="ph-settings-heading"><h2 class="h5 fw-bold mb-1">Teaching Verification</h2><p class="small text-body-secondary mb-0">Submit evidence for topics you can teach and track each application's approval status.</p></div>
        @include('profile.teaching-qualifications')
      </div>
    </section>
  </div>
</div>

<div class="modal fade" id="phUnsavedChangesModal" tabindex="-1" aria-labelledby="phUnsavedChangesTitle" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="phUnsavedChangesTitle"><i class="bi bi-exclamation-triangle text-warning me-2" aria-hidden="true"></i>Unsaved changes</h2></div>
    <div class="modal-body"><p class="mb-0">You have changes that haven't been saved. Leave without saving them?</p></div>
    <div class="modal-footer"><button type="button" class="btn btn-primary" id="phKeepEditingBtn" data-bs-dismiss="modal">Stay and continue editing</button><button type="button" class="btn btn-outline-danger" id="phDiscardChangesBtn">Discard changes</button></div>
  </div></div>
</div>

<form id="removePictureForm" action="{{ route('profile.picture.remove') }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
@include('profile.catalog-suggestions')
<div id="server-messages" data-errors="{{ json_encode($errors->all()) }}" data-success="{{ session('success') }}" class="d-none"></div>
@endsection
