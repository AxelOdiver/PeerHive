@extends('layouts.dashboard')
@section('title', 'Study Sessions')
@section('content')
<x-learning-header title="Study Sessions" subtitle="Learn with your community, practise together, or join a verified tutor.">
  <a href="{{ route('schedule') }}#bookings" class="btn btn-outline-secondary">My schedule</a>
  <a href="{{ route('study-sessions.create', ['community' => request('community')]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create a session</a>
</x-learning-header>
<nav class="d-flex gap-2 flex-wrap mb-4" aria-label="Filter sessions">
@foreach(['all'=>'All sessions','peer'=>'Peer study groups','tutor'=>'Tutor-led','mine'=>'My sessions','history'=>'History'] as $value=>$label)
  <a href="{{ route('study-sessions.index', ['filter'=>$value,'community'=>request('community')]) }}" class="btn {{ $filter === $value ? 'btn-primary' : 'btn-outline-secondary' }}" @if($filter===$value) aria-current="page" @endif>{{ $label }}</a>
@endforeach
@if(request('community'))<a href="{{ route('study-sessions.index') }}" class="btn btn-link">Clear community filter</a>@endif
</nav>
@if($filter === 'mine')<p class="text-muted small">Sessions you host or have joined that are still upcoming or in progress.</p>@elseif($filter === 'history')<p class="text-muted small">Completed and cancelled sessions you hosted or joined.</p>@endif
<div class="row g-3">
@forelse($sessions as $session)
<div class="col-12 col-md-6 col-xl-4"><article class="card h-100 shadow-sm border-0 rounded-4">
  <div class="card-body p-4 d-flex flex-column">
    <div class="d-flex justify-content-between gap-2 mb-3"><span class="badge {{ $session->format==='tutor'?'text-bg-primary':'text-bg-secondary' }}">{{ $session->format==='swap'?'Skill swap':($session->format==='tutor'?'Tutor-led':'Peer study group') }}</span>@if($session->host_id === auth()->id())<span class="badge text-bg-success">Hosting</span>@elseif(in_array($session->id,$joined))<span class="badge text-bg-success">Joined</span>@endif</div>
    <p class="small fw-semibold">{{ $session->status !== 'scheduled' ? ucfirst($session->status) : ($session->starts_at->isFuture() ? 'Upcoming' : ($session->ends_at->isFuture() ? 'In progress' : 'Awaiting attendance')) }}</p>
    <p class="small text-primary fw-semibold mb-1">{{ $session->subject }}</p><h2 class="h5 fw-bold text-break">{{ $session->title }}</h2>
    <p class="text-muted small flex-grow-1">{{ Str::limit($session->description,150) }}</p>
    <div class="small text-muted mb-3"><div><i class="bi bi-calendar-event me-1"></i>{{ $session->starts_at->copy()->setTimezone(auth()->user()->learning_timezone)->format('M j, Y · g:i A') }}</div><div>{{ auth()->user()->learning_timezone }} · {{ $session->meeting_method==='online'?'Online':'In person' }}</div><div class="mt-1">{{ ($session->community?->name ?? 'Private session') }} · {{ $session->host->first_name }} {{ $session->host->last_name }}</div></div>
    <div class="d-flex justify-content-between small mb-2"><span>{{ $session->participants_count }} / {{ $session->capacity }} places, including host</span><span>{{ max(0,$session->capacity-$session->participants_count) }} left</span></div>
    <a href="{{ route('study-sessions.show',$session) }}" class="btn btn-outline-primary w-100">View session</a>
  </div>
</article></div>
@empty
<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><i class="bi bi-people fs-1 text-muted"></i><h2 class="h5 mt-3">{{ $filter === 'history' ? 'No session history yet' : ($filter === 'mine' ? 'You have no active sessions here yet' : 'No upcoming sessions here yet') }}</h2><p class="text-muted">{{ $filter === 'history' ? 'Finished sessions will appear here.' : 'Create a session in a community you belong to, or try another filter.' }}</p>@if($filter !== 'history')<a href="{{ route('community') }}" class="btn btn-outline-primary">Explore communities</a>@endif</div></div></div>
@endforelse
</div><div class="mt-4">{{ $sessions->links() }}</div>
@endsection
