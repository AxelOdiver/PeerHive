@extends('layouts.dashboard')
@section('title', 'Learning catalog approvals')
@push('styles')
<style>
.ph-catalog-review{--ph-accent:#ffa500}
.ph-catalog-review .ph-catalog-shell{display:grid;grid-template-columns:minmax(260px,340px) minmax(0,1fr);gap:1.25rem;align-items:start}
.ph-catalog-review .ph-catalog-left,.ph-catalog-review .ph-catalog-detail{background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:1rem;min-width:0}
.ph-catalog-review .ph-catalog-item{display:block;width:100%;text-align:left;background:transparent;color:var(--bs-body-color);border:1px solid var(--bs-border-color);border-radius:.75rem;padding:.9rem 1rem;margin-bottom:.65rem}
.ph-catalog-review .ph-catalog-item:hover{background:var(--bs-tertiary-bg)}
.ph-catalog-review .ph-catalog-item[aria-pressed="true"]{border-color:var(--ph-accent);background:var(--bs-tertiary-bg);box-shadow:inset 3px 0 0 var(--ph-accent)}
.ph-catalog-review .ph-catalog-item:focus-visible{outline:2px solid var(--ph-accent);outline-offset:2px}
.ph-catalog-review .ph-catalog-detail{padding:1.5rem}
.ph-catalog-review .ph-catalog-detail[hidden],.ph-catalog-review .ph-catalog-item[hidden]{display:none!important}
.ph-catalog-review .ph-review-box{padding:1rem;border:1px solid var(--bs-border-color);border-radius:.8rem;margin-top:1rem}
.ph-catalog-review .ph-review-meta{font-size:.75rem;letter-spacing:.025em;text-transform:uppercase;color:var(--bs-secondary-color)}
.ph-catalog-review .ph-review-tabs .btn.active{background:#102440;border-color:#102440;color:white}
@media(max-width:991.98px){.ph-catalog-review .ph-catalog-shell{grid-template-columns:1fr}.ph-catalog-review .ph-catalog-detail{padding:1.1rem}}
</style>
@endpush
@section('content')
@include('admin.approval-tabs')
@if(session('catalog_status'))<div class="alert alert-success" role="status">{{ session('catalog_status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="ph-catalog-review" id="phCatalogReview">
  <header class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
    <div>
      <h1 class="h3 fw-bold mb-1">Catalog suggestions</h1>
      <p class="text-body-secondary mb-0">Review student suggestions before making them available to everyone. Approved topics do not grant teaching verification.</p>
    </div>
    <span class="badge rounded-pill text-bg-warning fs-6">{{ $entries->where('status','pending')->count() }} pending</span>
  </header>
  <div class="d-flex gap-2 flex-wrap mb-3" aria-label="Catalog entry type">
    @foreach(['program'=>'Programs', 'subject'=>'Subjects', 'topic'=>'Topics'] as $tabKind=>$tabLabel)
      <a class="btn {{ $kind === $tabKind ? 'btn-primary' : 'btn-outline-secondary' }} btn-sm" href="{{ route('admin.catalog', ['kind'=>$tabKind]) }}" @if($kind === $tabKind)aria-current="page"@endif>{{ $tabLabel }}</a>
    @endforeach
  </div>
  <div class="ph-catalog-shell">
    <section class="ph-catalog-left p-3" aria-labelledby="phCatalogQueueTitle">
      <h2 class="h5 fw-semibold mb-3" id="phCatalogQueueTitle">{{ ['program'=>'Programs','subject'=>'Subjects','topic'=>'Topics'][$kind] }} to review</h2>
      <label class="visually-hidden" for="phCatalogSearch">Search suggestions</label>
      <input type="search" id="phCatalogSearch" class="form-control mb-3" placeholder="Search suggestions..." autocomplete="off">
      <div class="d-flex gap-2 flex-wrap mb-3 ph-review-tabs" role="group" aria-label="Filter suggestions">
        <button type="button" class="btn btn-sm btn-outline-secondary active" data-catalog-filter="pending" aria-pressed="true">Pending</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-catalog-filter="reviewed" aria-pressed="false">Reviewed</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-catalog-filter="all" aria-pressed="false">All</button>
      </div>
      <div id="phCatalogItems">
        @foreach($entries as $entry)
          <button type="button" class="ph-catalog-item" data-catalog-entry="{{ $entry->id }}" data-catalog-status="{{ $entry->status }}" data-catalog-name="{{ $entry->name_key }}" aria-pressed="false" aria-controls="ph-catalog-detail-{{ $entry->id }}">
            <span class="d-flex justify-content-between gap-2 align-items-start">
              <strong class="text-break">{{ $entry->name }}</strong>
              <span class="badge {{ $entry->status === 'pending' ? 'text-bg-warning' : ($entry->status === 'approved' ? 'text-bg-success' : 'text-bg-secondary') }}">{{ ucfirst($entry->status) }}</span>
            </span>
            <span class="d-block small text-body-secondary mt-2"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ $entry->requesters_count }} {{ $entry->requesters_count === 1 ? 'student' : 'students' }} interested</span>
            @if($entry->kind === 'topic' && $entry->parent)<span class="d-block small text-body-secondary mt-1">Under {{ $entry->parent->name }}</span>@endif
          </button>
        @endforeach
      </div>
      <p class="text-body-secondary small text-center py-3 mb-0" id="phCatalogEmpty" hidden>No matching suggestions.</p>
    </section>
    <section aria-label="Selected catalog suggestion">
      <div id="phCatalogSelectPrompt" class="ph-catalog-detail text-body-secondary">Select a suggestion to review its details.</div>
      @foreach($entries as $entry)
        <article class="ph-catalog-detail" id="ph-catalog-detail-{{ $entry->id }}" data-catalog-panel="{{ $entry->id }}" hidden>
          <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
              <p class="ph-review-meta mb-1">{{ ucfirst($kind) }} suggestion #{{ $entry->id }}</p>
              <h2 class="h4 fw-bold mb-1 text-break">{{ $entry->name }}</h2>
              <p class="text-body-secondary mb-0">{{ $entry->requesters_count }} {{ $entry->requesters_count === 1 ? 'student has' : 'students have' }} requested this entry.</p>
            </div>
            <span class="badge {{ $entry->status === 'pending' ? 'text-bg-warning' : ($entry->status === 'approved' ? 'text-bg-success' : 'text-bg-secondary') }}">{{ ucfirst($entry->status) }}</span>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6"><div class="ph-review-meta mb-1">Original submitter</div><strong class="small">{{ $entry->submitter ? trim($entry->submitter->first_name.' '.$entry->submitter->last_name) : 'Unknown' }}</strong></div>
            <div class="col-6"><div class="ph-review-meta mb-1">First suggested</div><strong class="small">{{ $entry->created_at?->format('M d, Y') ?? 'Unknown' }}</strong></div>
            @if($entry->kind === 'topic')
              <div class="col-12"><div class="ph-review-meta mb-1">Parent subject</div><strong>{{ $entry->parent?->name ?? 'Not specified' }}</strong> @if($entry->parent?->status !== 'approved')<span class="text-warning-emphasis small">({{ $entry->parent?->status ?? 'missing' }})</span>@endif</div>
            @endif
          </div>
          @if($entry->requesters->isNotEmpty())
            <div class="ph-review-box">
              <h3 class="h6 fw-bold mb-2">Student requests</h3>
              <div class="d-grid gap-2">
                @foreach($entry->requesters as $requester)
                  <div class="small border-bottom pb-2">
                    <span class="fw-semibold">{{ trim($requester->first_name.' '.$requester->last_name) }}</span>
                    @if($requester->pivot->purpose)<span class="badge text-bg-secondary ms-1">{{ ucfirst($requester->pivot->purpose) }}</span>@endif
                    <span class="text-body-secondary ms-1">{{ str_replace('_', ' ', $requester->pivot->source) }}</span>
                    @if($requester->pivot->description)<p class="text-body-secondary mb-0 mt-1 text-break">{{ $requester->pivot->description }}</p>@endif
                  </div>
                @endforeach
              </div>
            </div>
          @endif
          @if($entry->status === 'pending')
            <div class="ph-review-box">
              <h3 class="h6 fw-bold mb-3">Review decision</h3>
              @if($entry->kind === 'topic' && $entry->parent?->status !== 'approved')
                <p class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Approve or merge the parent subject before approving this topic.</p>
              @endif
              <form method="POST" action="{{ route('admin.catalog.review', $entry) }}" class="mb-3" data-catalog-confirm="Approve {{ $entry->name }}? It will appear in the shared catalog.">
                @csrf<input type="hidden" name="status" value="approved">
                <button type="submit" class="btn btn-success w-100" @disabled($entry->kind === 'topic' && $entry->parent?->status !== 'approved')><i class="bi bi-check-circle me-2" aria-hidden="true"></i>Approve</button>
              </form>
              <form method="POST" action="{{ route('admin.catalog.review', $entry) }}" class="mb-3" data-catalog-confirm="Merge {{ $entry->name }} with the selected approved entry? Requesters will keep their history.">
                @csrf<input type="hidden" name="status" value="merged">
                <label class="form-label fw-semibold" for="catalogMerge{{ $entry->id }}">Merge into an approved {{ $kind }}</label>
                <select id="catalogMerge{{ $entry->id }}" name="merge_into_id" class="form-select mb-2" required>
                  <option value="">Choose approved {{ $kind }}</option>
                  @foreach($mergeTargets as $target)
                    @if($target->id !== $entry->id)<option value="{{ $target->id }}">{{ $target->name }}</option>@endif
                  @endforeach
                </select>
                <p class="small text-body-secondary">Merge keeps this request in the review history. For subjects, pending child topics are moved under the approved subject.</p>
                <button type="submit" class="btn btn-outline-primary w-100" @disabled($mergeTargets->isEmpty())><i class="bi bi-intersect me-2" aria-hidden="true"></i>Merge</button>
              </form>
              <form method="POST" action="{{ route('admin.catalog.review', $entry) }}" data-catalog-confirm="Reject {{ $entry->name }}? This decision will be recorded.">
                @csrf<input type="hidden" name="status" value="rejected">
                <label class="form-label fw-semibold" for="catalogReject{{ $entry->id }}">Reason for rejection</label>
                <textarea class="form-control mb-2" id="catalogReject{{ $entry->id }}" name="review_note" rows="2" minlength="10" maxlength="1000" placeholder="Explain why this suggestion was rejected..." required></textarea>
                @if($kind === 'subject')<p class="small text-body-secondary">Rejecting a subject also rejects its pending child topics.</p>@endif
                <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-2" aria-hidden="true"></i>Reject</button>
              </form>
            </div>
          @else
            <div class="ph-review-box">
              <h3 class="h6 fw-bold mb-2">Review history</h3>
              <p class="small mb-1">Decision: <strong>{{ ucfirst($entry->status) }}</strong></p>
              @if($entry->mergedInto)<p class="small mb-1">Merged into: <strong>{{ $entry->mergedInto->name }}</strong></p>@endif
              @if($entry->reviewed_at)<p class="small mb-1">Reviewed {{ $entry->reviewed_at->format('M d, Y g:i A') }}</p>@endif
              @if($entry->review_note)<p class="small text-body-secondary text-break mb-0">{{ $entry->review_note }}</p>@endif
            </div>
          @endif
        </article>
      @endforeach
    </section>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('phCatalogReview');
  if (!root) return;
  const items = [...root.querySelectorAll('[data-catalog-entry]')];
  const panels = [...root.querySelectorAll('[data-catalog-panel]')];
  const search = document.getElementById('phCatalogSearch');
  const empty = document.getElementById('phCatalogEmpty');
  const prompt = document.getElementById('phCatalogSelectPrompt');
  const filters = [...root.querySelectorAll('[data-catalog-filter]')];
  let activeFilter = 'pending';
  let selected = null;
  function select(id) {
    selected = id;
    items.forEach(item => item.setAttribute('aria-pressed', item.dataset.catalogEntry === id ? 'true' : 'false'));
    panels.forEach(panel => { panel.hidden = panel.dataset.catalogPanel !== id; });
    prompt.hidden = id !== null;
  }
  function applyFilter() {
    const term = search.value.trim().toLocaleLowerCase();
    let visible = 0;
    items.forEach(item => {
      const status = item.dataset.catalogStatus;
      const match = (activeFilter === 'all' || (activeFilter === 'pending' ? status === 'pending' : status !== 'pending'))
        && item.dataset.catalogName.includes(term);
      item.hidden = !match;
      if (match) visible++;
    });
    empty.hidden = visible > 0;
    if (!items.some(item => !item.hidden && item.dataset.catalogEntry === selected)) {
      select(items.find(item => !item.hidden)?.dataset.catalogEntry ?? null);
    }
  }
  items.forEach(item => item.addEventListener('click', () => select(item.dataset.catalogEntry)));
  search.addEventListener('input', applyFilter);
  filters.forEach(button => button.addEventListener('click', () => {
    activeFilter = button.dataset.catalogFilter;
    filters.forEach(filter => { const on = filter === button; filter.classList.toggle('active', on); filter.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    applyFilter();
  }));
  root.querySelectorAll('[data-catalog-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!form.checkValidity()) return;
    if (!window.confirm(form.dataset.catalogConfirm)) event.preventDefault();
  }));
  applyFilter();
});
</script>
@endsection
