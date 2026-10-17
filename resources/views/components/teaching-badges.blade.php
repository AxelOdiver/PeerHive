@props(['user'])
@if($user->approvedTeachingQualifications->isNotEmpty())
  <div class="d-flex flex-wrap gap-1 my-2" aria-label="Approved teaching qualifications">
    @foreach($user->approvedTeachingQualifications->pluck('subject_name')->unique() as $subject)
      <span class="badge rounded-pill text-bg-success text-wrap" title="An administrator approved a teaching qualification for this subject.">
        <i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Teaching verified: {{ $subject }}
      </span>
    @endforeach
  </div>
@endif
