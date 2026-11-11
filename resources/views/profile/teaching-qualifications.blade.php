<section id="teaching-verification" class="ph-teaching-verification" aria-label="Teaching verification applications">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><span class="badge text-bg-secondary">{{ $qualifications->where('status', 'pending')->count() }} pending</span><x-teaching-badges :user="auth()->user()" /></div>
  <p class="small text-muted">Apply for each topic you can teach. Each application is reviewed separately. You can learn without teaching verification. Existing broad qualifications remain valid only for their original subject.</p>
  @if($qualifications->isEmpty())<p class="small">No applications yet.</p>@else
  <ul class="list-group mb-3">@foreach($qualifications as $qualification)<li class="list-group-item"><strong>{{ $qualification->subject_name }}</strong> — {{ ucfirst($qualification->status) }} @if($qualification->rejection_reason)<div class="small text-muted">{{ $qualification->rejection_reason }}</div>@endif</li>@endforeach</ul>
  @endif
  <form id="qualificationForm" action="{{ route('qualifications.store') }}" method="POST" enctype="multipart/form-data" class="card card-body gap-3">
    @csrf
    <label class="form-label">Topic you want to teach<select name="subject" class="form-select" required><option value="">Select a topic</option>@foreach($catalogTopics->groupBy(fn ($t) => $t->parent?->name ?? 'Topics') as $subject => $topics)<optgroup label="{{ $subject }}">@foreach($topics as $topic)@php($past = $qualifications->where('subject_name', $topic->name))<option value="{{ $topic->name }}" @disabled($past->contains(fn ($q) => in_array($q->status, ['approved', 'pending'])) || $past->count() >= 3)>{{ $topic->name }}{{ $past->contains('status', 'approved') ? ' — Approved' : ($past->contains('status', 'pending') ? ' — Pending' : '') }}</option>@endforeach</optgroup>@endforeach</select></label>
    <label class="form-label">Proof for this topic<input type="file" name="proof_document" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required></label>
    <p class="small text-muted mb-0">JPEG, PNG, or PDF up to 10MB. Three attempts per topic. You may apply for other topics while one is pending.</p>
    <div><button type="submit" class="btn btn-primary">Submit for approval</button></div>
    <button type="button" class="btn btn-outline-secondary btn-sm align-self-start ph-suggestion-trigger" data-bs-toggle="modal" data-bs-target="#catalogSuggestionModal" data-catalog-suggest-kind="topic"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Topic not listed? Suggest it</button>
  </form>
</section>
