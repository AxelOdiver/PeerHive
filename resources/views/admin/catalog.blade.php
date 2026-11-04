@extends('layouts.dashboard')
@section('title', 'Learning catalog approvals')
@section('content')
@include('admin.approval-tabs')
@if(session('catalog_status'))<div class="alert alert-success">{{ session('catalog_status') }}</div>@endif
<div class="card shadow-sm border-0 rounded-4">
  <div class="card-header bg-dark text-white rounded-top-4">
    <h2 class="h5 mb-0"><i class="bi bi-journal-check me-2" aria-hidden="true"></i>{{ ['program' => 'Programs', 'subject' => 'Subjects', 'topic' => 'Topics'][$kind] }}</h2>
  </div>
  <div class="card-body p-0 table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th class="ps-4" scope="col">Name</th>@if($kind === 'topic')<th scope="col">Parent subject</th>@endif<th scope="col">Suggested by</th><th scope="col">Status</th><th scope="col">Review note</th><th class="text-end pe-4" scope="col">Actions</th></tr></thead>
      <tbody>
        @forelse($entries as $entry)
          <tr>
            <td class="ps-4 text-break"><strong>{{ $entry->name }}</strong></td>
            @if($kind === 'topic')
            <td>
              @if($entry->parent)
                {{ $entry->parent->name }}
                @if($entry->parent->status !== 'approved')<span class="d-block small text-muted">{{ ucfirst($entry->parent->status) }} subject</span>@endif
              @else<span class="text-muted">—</span>@endif
            </td>
            @endif
            <td>@if($entry->submitter){{ $entry->submitter->first_name }} {{ $entry->submitter->last_name }}@else<span class="text-muted">—</span>@endif</td>
            <td><span class="badge rounded-pill {{ $entry->status === 'pending' ? 'bg-warning text-dark' : ($entry->status === 'approved' ? 'bg-success' : 'bg-danger') }}">{{ ucfirst($entry->status) }}</span></td>
            <td style="min-width:220px">
              @if($entry->status === 'pending')
                <input class="form-control form-control-sm" form="catalogReview{{ $entry->id }}" name="review_note" maxlength="1000" aria-label="Review note for {{ $entry->name }}" placeholder="Review note (optional)" value="{{ $entry->review_note }}">
              @else<span class="small text-muted text-break">{{ $entry->review_note ?: '—' }}</span>@endif
            </td>
            <td class="text-end pe-4">
              @if($entry->status === 'pending')
                <form id="catalogReview{{ $entry->id }}" action="{{ route('admin.catalog.review', $entry) }}" method="POST" class="d-flex justify-content-end gap-2">
                  @csrf
                  <button class="btn btn-success btn-sm rounded-2 px-3" name="status" value="approved" @disabled($entry->kind === 'topic' && $entry->parent?->status !== 'approved')>Approve</button>
                  <button class="btn btn-danger btn-sm rounded-2 px-3" name="status" value="rejected">Reject</button>
                </form>
              @else<span class="text-muted small">Reviewed</span>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ $kind === 'topic' ? 6 : 5 }}" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3" aria-hidden="true"></i>No {{ $kind === 'program' ? 'programs' : ($kind === 'subject' ? 'subjects' : 'topics') }} found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
