@extends('layouts.dashboard')
@section('title', 'Create Study Session')
@section('content')
<x-learning-header title="Create a study session" subtitle="Plan what you will learn, when you will meet, and how everyone will join." :back-url="$navigationCommunity ? route('community.show', $navigationCommunity) : null" :back-label="$navigationCommunity ? 'Back to Community' : 'Back to Dashboard'">
  <a href="{{ route('study-sessions.index', ['community' => $navigationCommunity?->id]) }}" class="btn btn-outline-secondary">Back to sessions</a>
</x-learning-header>
@if($communities->isEmpty())
  <div class="alert alert-info">Join a community before hosting a session. <a href="{{ route('community') }}">Find a community</a>.</div>
@else
@php
  $errorField = $errors->keys()[0] ?? '';
  $wizardErrorStep = in_array($errorField, ['starts_at','timezone','duration','capacity'], true) ? 2 : (in_array($errorField, ['meeting_method','meeting_details'], true) ? 3 : 1);
@endphp
<div class="ph-session-create-shell">
  <div class="card ph-session-create-card shadow-sm rounded-4">
    <div class="card-body p-3 p-md-4 p-lg-5">
      <div class="ph-session-create-intro mb-4">
        <div class="d-flex align-items-center flex-wrap justify-content-between gap-2 mb-2">
          <h2 class="h5 fw-bold mb-0">Session information</h2>
          <span class="small text-body-secondary" data-ph-wizard-counter>Step 1 of 3</span>
        </div>
        <p class="small text-body-secondary mb-0">All three steps are part of one session. Nothing is submitted until you select Create session.</p>
      </div>
      <ol class="ph-session-wizard-progress list-unstyled" aria-label="Create session steps">
        <li data-ph-wizard-indicator="1"><span class="ph-session-step-number">1</span><span>Details</span></li>
        <li data-ph-wizard-indicator="2"><span class="ph-session-step-number">2</span><span>Schedule</span></li>
        <li data-ph-wizard-indicator="3"><span class="ph-session-step-number">3</span><span>Meeting</span></li>
      </ol>

      <form id="phSessionWizardForm" action="{{ route('study-sessions.store', ['community' => $navigationCommunity?->id]) }}" method="POST" data-ph-initial-step="{{ $wizardErrorStep }}">
        @csrf
        <section class="ph-session-wizard-panel" data-ph-wizard-panel="1" aria-labelledby="phSessionDetailsHeading">
          <div class="ph-session-step-header mb-4">
            <span class="ph-session-step-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
            <div><h3 class="h5 fw-bold mb-1" id="phSessionDetailsHeading">What will you work on?</h3><p class="small text-body-secondary mb-0">Tell other students what this session is about.</p></div>
          </div>
          <div class="row g-3">
            <div class="col-12"><label class="form-label fw-semibold" for="community_id">Community</label>
              <select id="community_id" name="community_id" class="form-select @error('community_id') is-invalid @enderror" required>
                @foreach($communities as $community)
                  <option value="{{ $community->id }}" @selected(old('community_id', request('community')) == $community->id)>{{ $community->name }}</option>
                @endforeach
              </select>
              @error('community_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><fieldset class="ph-session-format-fieldset">
              <legend class="form-label fw-semibold fs-6 mb-2">Session format</legend>
              <div class="ph-session-format-options">
                <label class="ph-session-format-option">
                  <input type="radio" name="format" value="peer" @checked(old('format', 'peer') === 'peer') required>
                  <span class="ph-session-format-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                  <span><strong>Peer study group</strong><small>Learn and practise together. No teaching qualification needed.</small></span>
                  <i class="bi bi-check-circle-fill ph-session-format-check" aria-hidden="true"></i>
                </label>
                <label class="ph-session-format-option">
                  <input type="radio" name="format" value="tutor" @checked(old('format') === 'tutor')>
                  <span class="ph-session-format-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
                  <span><strong>Tutor-led session</strong><small>Teach a subject you are approved to teach.</small></span>
                  <i class="bi bi-check-circle-fill ph-session-format-check" aria-hidden="true"></i>
                </label>
              </div>
            </fieldset></div>
            <div class="col-12 col-md-7"><label class="form-label fw-semibold" for="title">Session title</label>
              <input id="title" name="title" class="form-control @error('title') is-invalid @enderror" maxlength="150" value="{{ old('title') }}" placeholder="e.g. Introduction to Python" required>
              @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-5"><label class="form-label fw-semibold" for="subject">Subject</label>
              <select id="subject" name="subject" class="form-select @error('subject') is-invalid @enderror" data-ph-session-subject data-ph-old-subject="{{ old('subject') }}" required aria-describedby="phSubjectHint @error('subject') phSubjectError @enderror">
                <option value="">Choose a subject</option>
                <optgroup label="Approved subjects" data-ph-subject-group="peer">
                  @foreach($catalogSubjects as $entry)<option value="{{ $entry->name }}" data-ph-subject-role="peer" @selected(old('subject') === $entry->name)>{{ $entry->name }}</option>@endforeach
                </optgroup>
                <optgroup label="Approved learning topics" data-ph-subject-group="peer">
                  @foreach($catalogTopics as $entry)<option value="{{ $entry->name }}" data-ph-subject-role="peer" @selected(old('subject') === $entry->name)>{{ $entry->parent?->name ? $entry->parent->name.' · ' : '' }}{{ $entry->name }}</option>@endforeach
                </optgroup>
                @if($communitySubjects->isNotEmpty())<optgroup label="Your community subjects" data-ph-subject-group="peer">
                  @foreach($communitySubjects as $name)<option value="{{ $name }}" data-ph-subject-role="peer" @selected(old('subject') === $name)>{{ $name }}</option>@endforeach
                </optgroup>@endif
                <optgroup label="Your approved teaching subjects" data-ph-subject-group="tutor">
                  @foreach($verifiedSubjects as $name)<option value="{{ $name }}" data-ph-subject-role="tutor" @selected(old('subject') === $name)>{{ $name }}</option>@endforeach
                </optgroup>
              </select>
              <div class="form-text" id="phSubjectHint" data-ph-subject-hint>Choose a subject from the approved catalog or your communities.</div>
              @error('subject')<div class="invalid-feedback d-block" id="phSubjectError">{{ $message }}</div>@enderror
              <p class="form-text mb-0" data-ph-subject-tutor-help hidden>Tutor-led sessions require an approved teaching subject. <a href="{{ route('profile') }}#teaching-verification">Manage teaching verification</a>.</p>
            </div>
            <div class="col-12"><label class="form-label fw-semibold" for="description">Description <span class="fw-normal text-body-secondary">(optional)</span></label>
              <textarea id="description" name="description" class="form-control" rows="4" maxlength="2000" placeholder="What topics or activities will your group cover?">{{ old('description') }}</textarea>
            </div>
          </div>
        </section>

        <section class="ph-session-wizard-panel" data-ph-wizard-panel="2" aria-labelledby="phSessionScheduleHeading">
          <div class="ph-session-step-header mb-4">
            <span class="ph-session-step-icon"><i class="bi bi-calendar-week" aria-hidden="true"></i></span>
            <div><h3 class="h5 fw-bold mb-1" id="phSessionScheduleHeading">When will you meet?</h3><p class="small text-body-secondary mb-0">Choose a convenient time and the number of participants.</p></div>
          </div>
          <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="starts_at">Start date and time</label>
              <input type="datetime-local" id="starts_at" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at') }}" required aria-describedby="@error('starts_at') phStartsAtError @enderror">
              @error('starts_at')<div class="invalid-feedback d-block" id="phStartsAtError" role="alert">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="timezone">Timezone for this time</label>
              <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                @foreach(DateTimeZone::listIdentifiers() as $zone)
                  <option value="{{ $zone }}" @selected(old('timezone', auth()->user()->learning_timezone) === $zone)>{{ $zone }}</option>
                @endforeach
              </select>
              @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="duration">Duration in minutes</label>
              <input type="number" id="duration" name="duration" min="15" max="240" class="form-control @error('duration') is-invalid @enderror" value="{{ old('duration', 60) }}" required>
              @error('duration')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              <div class="form-text">Between 15 and 240 minutes.</div>
            </div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="capacity">Participant limit</label>
              <input type="number" id="capacity" name="capacity" min="2" max="50" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', 8) }}" required>
              @error('capacity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              <div class="form-text">2 to 50 participants, including the host.</div>
            </div>
          </div>
          <div class="ph-session-step-tip mt-4"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Your actual session time will be saved using the timezone you select.</span></div>
        </section>

        <section class="ph-session-wizard-panel" data-ph-wizard-panel="3" aria-labelledby="phSessionMeetingHeading">
          <div class="ph-session-step-header mb-4">
            <span class="ph-session-step-icon"><i class="bi bi-camera-video" aria-hidden="true"></i></span>
            <div><h3 class="h5 fw-bold mb-1" id="phSessionMeetingHeading">How will you meet?</h3><p class="small text-body-secondary mb-0">Share the meeting information privately with booked participants.</p></div>
          </div>
          <div class="row g-3">
            <div class="col-12 col-md-5"><label class="form-label fw-semibold" for="meeting_method">Meeting method</label>
              <select id="meeting_method" name="meeting_method" class="form-select @error('meeting_method') is-invalid @enderror" required>
                <option value="online" @selected(old('meeting_method', 'online') === 'online')>Online</option>
                <option value="in_person" @selected(old('meeting_method') === 'in_person')>In person</option>
              </select>
              @error('meeting_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-7"><label class="form-label fw-semibold" for="meeting_details" data-ph-meeting-label>Meeting link or venue</label>
              <textarea id="meeting_details" name="meeting_details" class="form-control @error('meeting_details') is-invalid @enderror" maxlength="1000" rows="3" required>{{ old('meeting_details') }}</textarea>
              @error('meeting_details')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              <div class="form-text">{{ \App\Support\MeetingLink::HELP }} Only booked participants and the host can see these details. Avoid sharing home addresses.</div>
            </div>
          </div>
          <div class="ph-session-step-tip mt-4"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Meeting information stays private until someone books the session.</span></div>
          <div class="ph-session-review mt-4" aria-label="Session summary">
            <h4 class="h6 fw-bold mb-3">Review your session</h4>
            <dl class="mb-0">
              <div><dt>Session</dt><dd data-ph-review="title">Not entered</dd></div>
              <div><dt>Community</dt><dd data-ph-review="community">Not selected</dd></div>
              <div><dt>Format / subject</dt><dd data-ph-review="subject">Not entered</dd></div>
              <div><dt>Starts</dt><dd data-ph-review="schedule">Not selected</dd></div>
              <div><dt>Duration / participants</dt><dd data-ph-review="capacity">Not entered</dd></div>
              <div><dt>Meeting</dt><dd data-ph-review="method">Not selected</dd></div>
            </dl>
          </div>
        </section>

        <div class="ph-session-wizard-footer mt-4 pt-3">
          <button type="button" class="btn btn-outline-secondary" data-ph-wizard-back hidden><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</button>
          <span class="small text-body-secondary" data-ph-wizard-status aria-live="polite">Complete the details above to continue.</span>
          <button type="button" class="btn btn-primary px-4" data-ph-wizard-next hidden>Continue <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
          <button type="submit" class="btn btn-primary px-4" data-ph-wizard-submit>Create session <i class="bi bi-check2 ms-1" aria-hidden="true"></i></button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection
