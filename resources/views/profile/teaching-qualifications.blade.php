<section class="mt-4" aria-labelledby="teachingVerificationHeading">
  <details id="teaching-verification" class="card" @if($errors->has('subject') || $errors->has('proof_document')) open @endif>
  <summary class="card-header p-3">
    <span class="h5" id="teachingVerificationHeading">Teaching Verification</span>
    <span class="badge text-bg-secondary ms-2">{{ $qualifications->where('status', 'pending')->count() }} pending</span>
    <x-teaching-badges :user="auth()->user()" />
  </summary>
  <div class="card-body">
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
    <a href="#catalog-suggestions">Topic not listed? Suggest it for review.</a>
  </form>
  </div>
  </details>
</section>
