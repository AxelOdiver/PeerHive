@extends('layouts.dashboard')

@section('title', 'History')

@section('content')
<x-learning-header title="Learning History" subtitle="Your completed and cancelled study sessions."><a href="{{ route('study-sessions.index') }}" class="btn btn-primary">Find a session</a></x-learning-header>
<div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
@forelse($participations as $record)<div class="d-flex justify-content-between flex-wrap gap-2 border-bottom py-3"><div><a href="{{ route('study-sessions.show',$record->session) }}" class="fw-semibold text-body text-decoration-none">{{ $record->session->title }}</a><div class="small text-muted">{{ ($record->session->community?->name ?? 'Private session') }} · {{ $record->session->subject }} · {{ $record->session->starts_at->copy()->setTimezone(auth()->user()->learning_timezone)->format('M j, Y · g:i A') }}</div></div><div><span class="badge text-bg-secondary">{{ ucfirst($record->session->status) }}</span><span class="small ms-2">{{ $record->confirmed_at?'Participation confirmed':($record->attended_at?'Awaiting confirmation':'No confirmed attendance') }}</span></div></div>@empty<p class="text-muted mb-0">No completed or cancelled sessions yet. Your history will appear here after your first session.</p>@endforelse
<div class="mt-3">{{ $participations->links() }}</div></div></div>
@endsection
