@php
  $c = $conversation->session_context;
  $state = $c['state'];
  $label = ucfirst($state);
  if ($state === 'scheduled') $label = $session->starts_at->isFuture() ? 'Upcoming' : ($session->ends_at->isFuture() ? 'In progress' : 'Awaiting confirmation');
  $learner = $conversation->users->firstWhere('id', $c['learner']);
  $teacher = $conversation->users->firstWhere('id', $c['teacher']);
@endphp
<div class="p-3 bg-body-tertiary border-bottom" data-session-revision="{{ $c['revision'] }}">
  <div class="d-flex justify-content-between gap-2"><strong>PeerHive · {{ $c['type'] === 'learning' ? 'Learning session' : 'Skill swap' }}</strong><span class="badge text-bg-primary">{{ $label }}</span></div>
  <details class="mt-2" @if(!$session) open @endif><summary>Session details and actions</summary>
  <p class="small mt-2 mb-1">{{ $teacher->first_name }} teaches {{ $c['subject'] }} to {{ $learner->first_name }}.@if($c['type'] === 'skill_swap') {{ $learner->first_name }} teaches {{ $c['offered_subject'] }} to {{ $teacher->first_name }}.@endif</p>
  @if($c['message'])<p class="small text-break">Original request: {{ $c['message'] }}</p>@endif
  @if(isset($c['proposal']))
    <p class="small mb-1">{{ \Carbon\Carbon::parse($c['proposal']['starts_at'])->setTimezone($c['proposal']['timezone'])->format('M j, Y · g:i A') }} · {{ $c['proposal']['timezone'] }} · {{ $c['proposal']['duration'] }} minutes</p>
    <p class="small text-break">{{ $c['proposal']['meeting_method'] === 'online' ? 'Online' : 'In person' }}: {{ $c['proposal']['meeting_details'] }}</p>
    @if($c['type'] === 'skill_swap')<p class="small">{{ $c['proposal']['teaching_minutes'] }} minutes learning {{ $c['subject'] }}, then {{ $c['proposal']['duration'] - $c['proposal']['teaching_minutes'] }} minutes learning {{ $c['offered_subject'] }}.</p>@endif
  @endif
  @if($state === 'proposed')
    @if($c['proposer'] !== auth()->id())<button type="button" class="btn btn-primary btn-sm" data-session-action="accept">Accept schedule</button>@else<p class="small">Waiting for your partner to accept the schedule.</p>@endif
    <button type="button" class="btn btn-outline-secondary btn-sm" data-session-action="changes">Suggest changes</button>
  @endif
  @if($state === 'scheduling')
    <p class="small text-muted">Chat about your goals and availability, then use the calendar button beside the attachment icon to create a session.</p>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-2" data-session-starter="What would you like to focus on, and when are you available?">Help me start the conversation</button>
    <form id="sessionProposalForm" class="row g-2 mt-1">
      <div class="col-sm-6"><label class="form-label small">Start date and time<input class="form-control form-control-sm" type="datetime-local" name="starts_at" required></label></div>
      <div class="col-sm-6"><label class="form-label small">Timezone<select class="form-select form-select-sm" name="timezone">@foreach(DateTimeZone::listIdentifiers() as $zone)<option @selected($zone === auth()->user()->learning_timezone)>{{ $zone }}</option>@endforeach</select></label></div>
      <div class="col-sm-6"><label class="form-label small">Duration (minutes)<input class="form-control form-control-sm" type="number" name="duration" value="60" min="15" max="240" required></label></div>
      @if($c['type'] === 'skill_swap')<div class="col-sm-6"><label class="form-label small">Minutes for {{ $teacher->first_name }} to teach {{ $c['subject'] }}<input class="form-control form-control-sm" type="number" name="teaching_minutes" value="30" min="1" max="239" required></label></div>@endif
      <div class="col-sm-6"><label class="form-label small">Meeting method<select class="form-select form-select-sm" name="meeting_method"><option value="online">Online</option><option value="in_person">In person</option></select></label></div>
      <div class="col-12"><label class="form-label small w-100">Meeting link or venue<input name="meeting_details" class="form-control form-control-sm" maxlength="1000" required></label></div>
      <div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Propose a session</button></div>
    </form>
  @endif
  @if($state === 'scheduled' && $session->starts_at->lte(now()))
    @if(in_array(auth()->id(), $c['confirmed'] ?? []))<p class="small text-success mt-2">You confirmed. Waiting for your partner.</p>
    @else<p class="small mt-2">Confirm only if you attended. Progress and points are credited when both of you confirm.</p><button class="btn btn-success btn-sm" data-session-action="finish">Confirm session completed</button>@endif
  @endif
  @if(isset($c['finished_at']))<p class="small">Finished at {{ \Carbon\Carbon::parse($c['finished_at'])->setTimezone($c['proposal']['timezone'])->format('g:i A') }}. Confirmed time: {{ (int) $session->starts_at->diffInMinutes($session->ends_at) }} minutes. For swaps, minutes follow the teaching order above.</p>@endif
  @if($state === 'completed')<p class="small text-success mt-2">Both participants confirmed. Your hours, session count, and points have been updated. <a href="{{ route('progress.index') }}">My Progress</a></p>@endif
  @if($state === 'problem')<p class="small text-danger mt-2">Attendance problem: {{ $c['problem'] }}. No progress or points awarded. Discuss this in the chat; cancel this session if it cannot be completed.</p>@endif
  @if(!in_array($state, ['completed', 'cancelled']))
    <div class="mt-3"><button class="btn btn-outline-danger btn-sm" data-session-action="cancel">Cancel session</button></div>
    @if($state === 'scheduled')<form id="sessionProblemForm" class="mt-2"><label class="form-label small w-100">Attendance problem / didn't attend<input class="form-control form-control-sm" name="reason" maxlength="1000" required></label><button class="btn btn-outline-warning btn-sm">Report attendance problem</button></form>@endif
  @endif
  </details>
</div>
