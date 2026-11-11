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
    refresh();
  });
</script>
@endsection
