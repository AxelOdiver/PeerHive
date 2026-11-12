@extends('layouts.dashboard')
@section('title', 'Get Started · PeerHive')
@section('content')
<div class="ph-get-started mx-auto">
  <div class="mb-4">
    <span class="ph-onboarding-kicker">GET STARTED · STEP {{ $step === 'skills' ? '1' : '2' }} OF 2</span>
    <div class="ph-onboarding-progress mt-2" aria-hidden="true"><span style="width:{{ $step === 'skills' ? 50 : 100 }}%"></span></div>
    <h1 class="mt-4 fw-bold">{{ $step === 'skills' ? 'What skills do you have?' : 'What would you like to learn?' }}</h1>
    <p class="text-body-secondary">{{ $step === 'skills' ? 'Tell other students what you know. These are self-described skills, not verified teaching qualifications.' : 'Pick topics you want help learning. Your choices will appear in your profile and improve recommendations.' }}</p>
  </div>
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
      <form id="onboardingTopicsForm" action="{{ $step === 'skills' ? route('onboarding.skills') : route('onboarding.learn') }}" method="POST">
        @csrf
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
          <label for="onboardingTopicSearch" class="form-label fw-semibold mb-0">Choose up to 10 topics</label>
          <span class="small text-body-secondary" id="onboardingCount">{{ count($selected) }} / 10 selected</span>
        </div>
        <input id="onboardingTopicSearch" type="search" class="form-control mb-4" placeholder="Search skills, subjects, or topics…" autocomplete="off">
        @forelse($topics->groupBy(fn ($topic) => $topic->parent?->name ?? 'Topics') as $subject => $subjectTopics)
          <section class="ph-topic-category mb-4" data-topic-group>
            <h2 class="fs-6 fw-bold mb-3">{{ $subject }}</h2>
            <div class="ph-onboarding-topics">
              @foreach($subjectTopics as $topic)
                <label class="ph-onboarding-topic" data-topic-entry data-search="{{ mb_strtolower($subject.' '.$topic->name) }}">
                  <input type="checkbox" name="topics[]" value="{{ $topic->name }}" @checked(in_array($topic->name, $selected, true))>
                  <span>{{ $topic->name }}</span><i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                </label>
              @endforeach
            </div>
          </section>
        @empty
          <p class="text-body-secondary">There are no approved topics available yet. You can skip and add them later in your profile.</p>
        @endforelse
        <p id="onboardingNoMatches" class="text-body-secondary" hidden>No matching topics found.</p>
        @error('topics')<p class="text-danger small" role="alert">{{ $message }}</p>@enderror
        @error('topics.*')<p class="text-danger small" role="alert">{{ $message }}</p>@enderror
        <div class="border rounded-3 bg-body-tertiary p-3 mt-4" id="ph-onboarding-missing-topic">
          <div class="d-flex align-items-start gap-3">
            <i class="bi bi-lightbulb text-warning fs-4" aria-hidden="true"></i>
            <div class="flex-grow-1">
              <h2 class="h6 fw-bold mb-1">Can't find your subject?</h2>
              <p class="text-body-secondary small mb-2">Suggest a missing subject or skill for admin review. You don't need to wait for approval to finish setting up your account.</p>
              <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#phOnboardingSuggestModal"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Suggest a subject or skill</button>
            </div>
          </div>
        </div>
        <div id="phOnboardingSuggestionFeedback" class="alert alert-success mt-3" role="status" aria-live="polite" hidden></div>
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mt-4 border-top pt-4">
          <div>@if($step === 'learn')<a class="btn btn-outline-secondary" href="{{ route('onboarding.show', ['step' => 'skills']) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back</a>@endif</div>
          <div class="d-flex align-items-center justify-content-end gap-2 ms-auto flex-wrap ph-onboarding-actions">
            <button type="submit" form="onboardingSkipForm" class="btn ph-quiet-action ph-onboarding-skip">Skip for now</button>
            <button type="submit" class="btn btn-primary px-4">{{ $step === 'skills' ? 'Next' : 'Finish' }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
          </div>
        </div>
      </form>
      <form id="onboardingSkipForm" method="POST" action="{{ route('onboarding.skip') }}">
        @csrf
        <input type="hidden" name="step" value="{{ $step }}">
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="phOnboardingSuggestModal" tabindex="-1" aria-labelledby="phOnboardingSuggestTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title h5" id="phOnboardingSuggestTitle">Suggest a subject or skill</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form id="phOnboardingSuggestionForm" action="{{ route('catalog.suggest') }}" method="POST">
        @csrf
        <input type="hidden" name="source" value="{{ $step === 'skills' ? 'onboarding_skills' : 'onboarding_learn' }}">
        <input type="hidden" name="purpose" value="{{ $step === 'skills' ? 'share' : 'learn' }}">
        <div class="modal-body d-grid gap-3">
          <p class="small text-body-secondary mb-0">Suggestions are reviewed by administrators. You can continue with Next or Finish while your suggestion is pending. Selecting a skill does not verify you to teach it.</p>
          <div><label class="form-label" for="phOnboardKind">What are you suggesting?</label>
            <select class="form-select" id="phOnboardKind" name="kind"><option value="topic">Skill or learning topic</option><option value="subject">Subject or category</option></select>
          </div>
          <div><label class="form-label" for="phOnboardName">Suggested name</label><input class="form-control" id="phOnboardName" name="name" minlength="3" maxlength="100" required placeholder="e.g., Mobile App Development"></div>
          <div id="phOnboardParentWrap"><label class="form-label" for="phOnboardSubject">Under which subject?</label>
            <select class="form-select" id="phOnboardSubject" name="subject_id">
              <option value="">Other / Subject not listed</option>
              @foreach($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach
            </select>
            <div class="mt-2" id="phOnboardOtherSubject"><label class="form-label" for="phOnboardSubjectName">Missing subject name</label><input class="form-control" id="phOnboardSubjectName" name="subject_name" minlength="3" maxlength="100" placeholder="e.g., Mobile Development"></div>
          </div>
          <div><label class="form-label" for="phOnboardDescription">Additional details (optional)</label><textarea class="form-control" id="phOnboardDescription" name="description" rows="3" maxlength="500" placeholder="Tell us what you'd like to learn or share..."></textarea></div>
          <div class="small text-body-secondary">A new subject is a category. To make a specific skill selectable in PeerHive, suggest a topic under that subject.</div>
          <div id="phOnboardSubmitError" class="alert alert-danger small mb-0" role="alert" hidden></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit suggestion</button></div>
      </form>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.ph-get-started');
    if (!root) return;
    const items = [...root.querySelectorAll('[data-topic-entry]')];
    const inputs = items.map(item => item.querySelector('input'));
    const count = document.getElementById('onboardingCount');
    const search = document.getElementById('onboardingTopicSearch');
    const noMatches = document.getElementById('onboardingNoMatches');
    const refresh = () => {
      const selected = inputs.filter(input => input.checked).length;
      count.textContent = `${selected} / 10 selected`;
      inputs.forEach(input => { input.disabled = !input.checked && selected >= 10; });
    };
    inputs.forEach(input => input.addEventListener('change', refresh));
    search.addEventListener('input', () => {
      const q = search.value.trim().toLocaleLowerCase();
      let visible = 0;
      items.forEach(item => {
        const show = item.dataset.search.includes(q);
        item.hidden = !show;
        if (show) visible++;
      });
      root.querySelectorAll('[data-topic-group]').forEach(group => {
        group.hidden = !group.querySelector('[data-topic-entry]:not([hidden])');
      });
      noMatches.hidden = !!visible || !items.length;
    });
    const suggestionForm = document.getElementById('phOnboardingSuggestionForm');
    const kind = document.getElementById('phOnboardKind');
    const parent = document.getElementById('phOnboardSubject');
    const parentWrap = document.getElementById('phOnboardParentWrap');
    const otherSubject = document.getElementById('phOnboardOtherSubject');
    const subjectName = document.getElementById('phOnboardSubjectName');
    const submitError = document.getElementById('phOnboardSubmitError');
    const suggestionFeedback = document.getElementById('phOnboardingSuggestionFeedback');
    const suggestionModal = document.getElementById('phOnboardingSuggestModal');
    const adjustSuggestion = () => {
      const isTopic = kind.value === 'topic';
      parentWrap.hidden = !isTopic;
      otherSubject.hidden = !isTopic || Boolean(parent.value);
      subjectName.required = isTopic && !parent.value;
    };
    kind.addEventListener('change', adjustSuggestion);
    parent.addEventListener('change', adjustSuggestion);
    suggestionModal.addEventListener('show.bs.modal', () => {
      const name = document.getElementById('phOnboardName');
      if (search.value.trim() && !name.value.trim()) name.value = search.value.trim().slice(0, 100);
      adjustSuggestion();
    });
    suggestionForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (!suggestionForm.reportValidity()) return;
      const submitButton = suggestionForm.querySelector('button[type="submit"]');
      submitButton.disabled = true;
      submitError.hidden = true;
      try {
        const response = await fetch(suggestionForm.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': suggestionForm.querySelector('[name="_token"]').value },
          body: new FormData(suggestionForm),
        });
        const data = await response.json();
        if (!response.ok) {
          const first = Object.values(data.errors || {}).flat()[0];
          throw new Error(first || data.message || 'Unable to submit your suggestion.');
        }
        suggestionFeedback.textContent = data.message || 'Your suggestion was submitted for review.';
        suggestionFeedback.hidden = false;
        suggestionForm.reset();
        adjustSuggestion();
        window.bootstrap.Modal.getOrCreateInstance(suggestionModal).hide();
      } catch (error) {
        submitError.textContent = error.message || 'Please try again.';
        submitError.hidden = false;
      } finally { submitButton.disabled = false; }
    });
    adjustSuggestion();
    refresh();
  });
</script>
@endsection
