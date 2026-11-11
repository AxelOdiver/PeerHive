@php
  $c = $conversation->session_context;
  $state = $c['state'];
  $label = ucfirst($state);
  if ($state === 'scheduled') $label = $session->starts_at->isFuture() ? 'Upcoming' : ($session->ends_at->isFuture() ? 'In progress' : 'Awaiting confirmation');
  $learner = $conversation->users->firstWhere('id', $c['learner']);
  $teacher = $conversation->users->firstWhere('id', $c['teacher']);
@endphp
<div class="ph-private-session-panel" data-session-revision="{{ $c['revision'] }}">
  <div class="ph-private-session-heading d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div class="d-flex gap-2 align-items-center"><span class="ph-session-panel-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span><div><strong class="d-block">{{ $c['type'] === 'learning' ? 'Learning session' : 'Skill swap' }}</strong><small class="text-muted">Private Learn/Swap conversation</small></div></div><span class="badge ph-private-session-status">{{ $label }}</span></div>
  <details class="ph-private-session-details" open><summary class="fw-semibold">Session information and actions</summary>
  <p class="small mt-2 mb-1">{{ $teacher->first_name }} teaches {{ $c['subject'] }} to {{ $learner->first_name }}.@if($c['type'] === 'skill_swap') {{ $learner->first_name }} teaches {{ $c['offered_subject'] }} to {{ $teacher->first_name }}.@endif</p>
  @if($c['message'])<p class="small text-break">Original request: {{ $c['message'] }}</p>@endif
  @if(isset($c['proposal']))
    <p class="small mb-1">{{ \Carbon\Carbon::parse($c['proposal']['starts_at'])->setTimezone($c['proposal']['timezone'])->format('M j, Y · g:i A') }} · {{ $c['proposal']['timezone'] }} · {{ $c['proposal']['duration'] }} minutes</p>
    <div class="small mb-3"><x-meeting-details :method="$c['proposal']['meeting_method']" :details="$c['proposal']['meeting_details']" /></div>
    @if($c['type'] === 'skill_swap')<p class="small">{{ $c['proposal']['teaching_minutes'] }} minutes learning {{ $c['subject'] }}, then {{ $c['proposal']['duration'] - $c['proposal']['teaching_minutes'] }} minutes learning {{ $c['offered_subject'] }}.</p>@endif
  @endif
  @if($state === 'proposed')
    <div class="ph-session-actions-summary mt-3">
      @if(($c['proposer'] ?? null) === auth()->id())
        <p class="small text-body-secondary mb-2">Waiting for your partner to review this schedule. You can edit or withdraw your proposal.</p>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-primary btn-sm" data-session-open-proposal="edit"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit proposal</button>
          <button type="button" class="btn btn-outline-danger btn-sm" data-session-action="withdraw">Cancel proposal</button>
        </div>
      @else
        <p class="small text-body-secondary mb-2">Review this proposal before accepting. Declining a time does not cancel your learning request.</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button type="button" class="btn btn-primary btn-sm" data-session-action="accept">Accept schedule</button>
          <button type="button" class="btn btn-outline-danger btn-sm" data-session-action="decline">Decline proposal</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-session-open-proposal="counter">Suggest changes</button>
        </div>
      @endif
    </div>
  @endif
  @if(in_array($state, ['scheduling', 'proposed'], true))
    @php
      $currentProposal = $c['proposal'] ?? null;
      $proposalStart = $currentProposal ? \Carbon\Carbon::parse($currentProposal['starts_at'])->setTimezone($currentProposal['timezone'])->format('Y-m-d\TH:i') : '';
      $suggestingChanges = $state === 'proposed' && ($c['proposer'] ?? null) !== auth()->id();
      $formAction = $suggestingChanges ? 'counter' : 'propose';
    @endphp
    @if($state === 'scheduling')
      <p class="small text-muted mt-3">Chat about your goals and availability, then propose a schedule.</p>
      <button type="button" class="btn btn-outline-secondary btn-sm mb-2" data-session-starter="What would you like to focus on, and when are you available?">Help me start the conversation</button>
    @endif
    <form id="sessionProposalForm" class="ph-session-proposal-form" data-proposal-action="{{ $formAction }}" data-proposal-state="{{ $state }}">
      <input type="hidden" name="revision" value="{{ $c['revision'] }}">
      <div class="ph-session-form-section mb-3">
        <div class="ph-session-form-section-title"><i class="bi bi-calendar-week" aria-hidden="true"></i><strong>Session schedule</strong></div>
        <div class="row g-3">
          <div class="col-12 col-sm-7"><label class="form-label small w-100" for="phPrivateSessionStart">Start date and time
            <input id="phPrivateSessionStart" class="form-control" type="datetime-local" name="starts_at" value="{{ $proposalStart }}" required>
          </label></div>
          <div class="col-12 col-sm-5"><label class="form-label small w-100" for="phPrivateSessionZone">Timezone
            <select id="phPrivateSessionZone" class="form-select" name="timezone">@foreach(DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}" @selected($zone === ($currentProposal['timezone'] ?? auth()->user()->learning_timezone))>{{ $zone }}</option>@endforeach</select>
          </label></div>
          <div class="col-12 col-sm-6"><label class="form-label small w-100" for="phPrivateSessionDuration">Duration (minutes)
            <input id="phPrivateSessionDuration" class="form-control" type="number" name="duration" value="{{ $currentProposal['duration'] ?? 60 }}" min="15" max="240" required>
          </label></div>
          @if($c['type'] === 'skill_swap')
          <div class="col-12 col-sm-6"><label class="form-label small w-100" for="phPrivateTeachingMinutes">Minutes for {{ $teacher->first_name }} to teach {{ $c['subject'] }}
            <input id="phPrivateTeachingMinutes" class="form-control" type="number" name="teaching_minutes" value="{{ $currentProposal['teaching_minutes'] ?? 30 }}" min="1" max="239" required>
          </label><p class="form-text">The remaining time is for {{ $learner->first_name }} to teach {{ $c['offered_subject'] }}.</p></div>
          @endif
        </div>
      </div>
      <div class="ph-session-form-section mb-3">
        <div class="ph-session-form-section-title"><i class="bi bi-geo-alt" aria-hidden="true"></i><strong>Meeting setup</strong></div>
        <fieldset class="mb-3">
          <legend class="form-label small mb-2">Meeting method</legend>
          <div class="ph-private-meeting-switch" role="group" aria-label="Meeting method">
            <label class="ph-meeting-method-choice"><input type="radio" name="meeting_method" value="online" @checked(($currentProposal['meeting_method'] ?? 'online') === 'online')><span><i class="bi bi-camera-video" aria-hidden="true"></i> Online</span></label>
            <label class="ph-meeting-method-choice"><input type="radio" name="meeting_method" value="in_person" @checked(($currentProposal['meeting_method'] ?? 'online') === 'in_person')><span><i class="bi bi-geo-alt" aria-hidden="true"></i> In person</span></label>
          </div>
        </fieldset>
        <div><label for="phPrivateMeetingDetails" class="form-label small" data-ph-meeting-label>Meeting link</label>
          <input id="phPrivateMeetingDetails" name="meeting_details" class="form-control" maxlength="1000" value="{{ $currentProposal['meeting_details'] ?? '' }}" required>
          <p class="form-text mb-0" data-ph-meeting-help>{{ \App\Support\MeetingLink::HELP }}</p>
        </div>
      </div>
      @if($suggestingChanges)
        <div class="mb-3"><label class="form-label small w-100" for="phPrivateChangeNote">Reason or message <span class="text-muted">(optional)</span>
          <textarea id="phPrivateChangeNote" name="change_note" class="form-control" rows="2" maxlength="300" placeholder="Could we meet an hour later instead?"></textarea>
        </label></div>
      @endif
      <div class="d-flex justify-content-end gap-2 align-items-center ph-private-proposal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">{{ $suggestingChanges ? 'Send suggested changes' : ($state === 'proposed' ? 'Save updated proposal' : 'Propose schedule') }}</button>
      </div>
    </form>
  @endif
  @if($state === 'scheduled' && $session->starts_at->lte(now()))
    @if(in_array(auth()->id(), $c['confirmed'] ?? []))<p class="small text-success mt-2">You confirmed. Waiting for your partner.</p>
    @else<p class="small mt-2">Confirm only if you attended. Progress and points are credited when both of you confirm.</p><button class="btn btn-success btn-sm" data-session-action="finish">Confirm session completed</button>@endif
  @endif
  @if(isset($c['finished_at']))<p class="small">Finished at {{ \Carbon\Carbon::parse($c['finished_at'])->setTimezone($c['proposal']['timezone'])->format('g:i A') }}. Confirmed time: {{ (int) $session->starts_at->diffInMinutes($session->ends_at) }} minutes. For swaps, minutes follow the teaching order above.</p>@endif
  @if($state === 'completed')<p class="small text-success mt-2">Both participants confirmed. Your hours, session count, and points have been updated. <a href="{{ route('progress.index') }}">My Progress</a></p>@endif
  @if($state === 'problem')<p class="small text-danger mt-2">Attendance problem: {{ $c['problem'] }}. No progress or points awarded. Discuss this in the chat; cancel this session if it cannot be completed.</p>@endif
  @if(!in_array($state, ['completed', 'cancelled', 'proposed']))
    <div class="mt-3"><button class="btn btn-outline-danger btn-sm" data-session-action="cancel">Cancel session</button></div>
    @if($state === 'scheduled')<form id="sessionProblemForm" class="mt-2"><label class="form-label small w-100">Attendance problem / didn't attend<input class="form-control form-control-sm" name="reason" maxlength="1000" required></label><button class="btn btn-outline-warning btn-sm">Report attendance problem</button></form>@endif
  @endif
  </details>
</div>
