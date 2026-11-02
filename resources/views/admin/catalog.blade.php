@extends('layouts.dashboard')
@section('title', 'Learning catalog approvals')
@section('content')
<a href="{{ route('admin.qualifications') }}" class="btn btn-outline-secondary mb-3">Back to teaching qualifications</a>
<h2 class="h4">Programs, subjects and topics</h2>
<p class="text-muted">Catalog approval makes an entry selectable by everyone. Teaching qualification approval is separate.</p>
@if(session('catalog_status'))<div class="alert alert-success">{{ session('catalog_status') }}</div>@endif
<div class="list-group">@foreach($entries as $entry)<div class="list-group-item">
  <div><strong>{{ $entry->name }}</strong> · {{ ucfirst($entry->kind) }} · {{ ucfirst($entry->status) }}</div>
  @if($entry->parent)<div class="small">Subject: {{ $entry->parent->name }} ({{ $entry->parent->status }})</div>@endif
  @if($entry->submitter)<div class="small text-muted">Suggested by {{ $entry->submitter->first_name }} {{ $entry->submitter->last_name }}</div>@endif
  @if($entry->status === 'pending')<form action="{{ route('admin.catalog.review', $entry) }}" method="POST" class="d-flex flex-wrap gap-2 mt-2">@csrf<input class="form-control" name="review_note" maxlength="1000" aria-label="Review note" placeholder="Review note (optional)"><button class="btn btn-success btn-sm" name="status" value="approved" @disabled($entry->kind === 'topic' && $entry->parent?->status !== 'approved')>Approve</button><button class="btn btn-outline-danger btn-sm" name="status" value="rejected">Reject</button></form>@endif
  @if($entry->review_note)<div class="small">{{ $entry->review_note }}</div>@endif
</div>@endforeach</div>
@endsection
