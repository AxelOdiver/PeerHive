<p class="small text-muted">Activities are optional. Session hours and points are separate. Submit after both participants confirm completion; ordinary chat stays read-only.</p>
@forelse($activities as $activity)
<article class="card mb-3" id="activity-{{ $activity->id }}"><div class="card-body">
  <div class="d-flex justify-content-between gap-2 flex-wrap"><h2 class="h5">{{ $activity->title }}</h2><span class="badge text-bg-secondary align-self-start">{{ ['assigned'=>'Awaiting submission','submitted'=>'Awaiting review','revision'=>'Needs revision','passed'=>'Activity passed'][$activity->status] }}</span></div>
  <p class="small text-muted">{{ $activity->topic_name }} · {{ $activity->teacher->first_name }} teaches {{ $activity->learner->first_name }}</p>
  <p class="text-break" style="white-space:pre-wrap">{{ $activity->instructions }}</p>
  <h3 class="h6">Passing criteria</h3><p class="text-break" style="white-space:pre-wrap">{{ $activity->passing_criteria }}</p>
  @if($activity->reference_url)<a href="{{ $activity->reference_url }}" target="_blank" rel="noopener noreferrer">Open reference</a>@endif
  @if($activity->covered_outcomes)<h3 class="h6 mt-3">Topic outcomes assessed</h3><ul>@foreach($activity->covered_outcomes as $outcome)<li>{{ $outcome['text'] }}</li>@endforeach</ul>@else<p class="small text-muted">Practice activity. No topic completion outcomes are attached.</p>@endif
  @foreach($activity->submissions as $submission)
    <details class="border-top py-3" @if($loop->first) open @endif><summary>Submission {{ $submission->attempt }} · {{ $submission->created_at->format('M j, Y') }} @if($submission->decision) · {{ $submission->decision === 'passed' ? 'Passed' : 'Needs revision' }} @endif</summary>
      <pre class="bg-body-tertiary p-3 mt-2 text-break" style="white-space:pre-wrap">{{ $submission->answer }}</pre>
      @if($submission->project_url)<a href="{{ $submission->project_url }}" target="_blank" rel="noopener noreferrer">Open project link</a>@endif
      @if($submission->feedback)<h4 class="h6 mt-3">Teacher feedback</h4><p class="text-break" style="white-space:pre-wrap">{{ $submission->feedback }}</p>@endif
    </details>
  @endforeach
  @if(!$ready[$activity->id])<p class="small text-muted">Submission and review require confirmed learning time for this topic and completion by both participants.</p>
  @elseif(auth()->id() === $activity->learner_id && in_array($activity->status, ['assigned','revision']))
    <form method="POST" action="{{ route('session-activities.submit', [$conversation, $activity]) }}" data-activity-form>@csrf<input type="hidden" name="revision" value="{{ $activity->revision }}">
      <label class="form-label" for="answer{{ $activity->id }}">Your answer</label><textarea id="answer{{ $activity->id }}" name="answer" rows="5" class="form-control mb-3" maxlength="10000" required>{{ old('answer', $activity->submissions->first()?->answer) }}</textarea>
      <label class="form-label" for="project{{ $activity->id }}">Project link (optional)</label><input id="project{{ $activity->id }}" type="url" name="project_url" class="form-control mb-3" maxlength="1000" placeholder="https://…" value="{{ old('project_url', $activity->submissions->first()?->project_url) }}">
      <button type="submit" class="btn btn-primary">{{ $activity->status === 'revision' ? 'Resubmit for review' : 'Submit for review' }}</button>
    </form>
  @elseif(auth()->id() === $activity->teacher_id && $activity->status === 'submitted')
    <form method="POST" action="{{ route('session-activities.review', [$conversation, $activity]) }}" data-activity-form>@csrf<input type="hidden" name="revision" value="{{ $activity->revision }}">
      <label class="form-label" for="feedback{{ $activity->id }}">Feedback against the passing criteria</label><textarea id="feedback{{ $activity->id }}" name="feedback" class="form-control mb-3" rows="3" maxlength="3000" required>{{ old('feedback') }}</textarea>
      <p class="small text-muted">Passing confirms the learner met the activity criteria and every selected topic outcome.</p>
      <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-outline-secondary" name="decision" value="revision">Needs revision</button><button type="submit" class="btn btn-primary" name="decision" value="passed">Activity passed</button></div>
    </form>
  @endif
  @if($activity->status === 'passed')<p class="small text-muted mt-3 mb-0">Passed — reviewed by your peer. <a href="{{ route('progress.skill-tree', ['topic'=>$activity->topic_id, 'subject'=>$activity->topic?->parent_id]) }}">View topic progress</a></p>@endif
</div></article>
@empty<p class="text-muted">No activities assigned for this session.</p>@endforelse
@if($canAssign)
<details class="card" @if($activities->isEmpty()) open @endif><summary class="card-header">Assign an activity · {{ $direction['name'] }}</summary><div class="card-body">
<form method="POST" action="{{ route('session-activities.store', $conversation) }}" data-activity-form>@csrf
  <label class="form-label" for="activityTitle">Title</label><input id="activityTitle" name="title" class="form-control mb-3" maxlength="150" value="{{ old('title') }}" required>
  <label class="form-label" for="activityInstructions">Instructions</label><textarea id="activityInstructions" name="instructions" class="form-control mb-3" maxlength="4000" rows="3" required>{{ old('instructions') }}</textarea>
  <label class="form-label" for="activityCriteria">Passing criteria</label><textarea id="activityCriteria" name="passing_criteria" class="form-control mb-3" maxlength="2000" rows="3" required>{{ old('passing_criteria') }}</textarea>
  <label class="form-label" for="activityReference">Reference link (optional)</label><input id="activityReference" type="url" name="reference_url" class="form-control mb-3" maxlength="1000" placeholder="https://…" value="{{ old('reference_url') }}">
  @if($plan?->outcomes)<fieldset class="mb-3"><legend class="fs-6">Which topic outcomes will this activity assess?</legend><p class="small text-muted">Select only outcomes covered by your instructions and criteria. Leave unchecked for practice only.</p>
  @foreach($plan->outcomes as $outcome)<div class="form-check"><input class="form-check-input" type="checkbox" name="outcomes[]" value="{{ $outcome['key'] }}" id="outcome{{ $loop->index }}"><label class="form-check-label" for="outcome{{ $loop->index }}">{{ $outcome['text'] }}</label></div>@endforeach</fieldset>
  @else<p class="small text-muted">No completion requirements have been defined for this topic. This activity will record peer-reviewed practice without automatically completing the topic.</p>@endif
  <p class="small text-muted">One activity per teaching direction. Instructions and criteria are fixed once assigned.</p><button class="btn btn-primary" type="submit">Assign activity</button>
</form></div></details>
@elseif($activities->isEmpty())<p class="small text-muted">The teacher can assign an activity once a session schedule is accepted.</p>@endif
