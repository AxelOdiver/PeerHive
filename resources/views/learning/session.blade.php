@extends('layouts.dashboard')
@section('title', $session->title)
@section('content')
<x-learning-header :title="$session->title" subtitle="A community learning session."><a href="{{ route('study-sessions.index', ['filter'=>'mine']) }}" class="btn btn-outline-primary">My sessions</a><a href="{{ route('study-sessions.index') }}" class="btn btn-outline-secondary">All sessions</a></x-learning-header>
<div class="d-flex gap-2 mb-3"><button type="button" class="btn btn-outline-secondary btn-sm" data-copy-session="{{ route('study-sessions.show',$session) }}">Copy session link</button><a href="{{ route('messages') }}" class="btn btn-outline-secondary btn-sm">Open Messages</a></div>
<div class="row g-4"><div class="col-lg-8"><div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
  <div class="d-flex gap-2 flex-wrap mb-3"><span class="badge text-bg-primary">{{ $session->format==='tutor'?'Tutor-led':'Peer study group' }}</span><span class="badge text-bg-secondary">{{ ucfirst($session->status) }}</span><span class="badge bg-primary-subtle text-primary-emphasis">{{ $session->subject }}</span></div>
  <p class="text-break" style="white-space:pre-wrap">{{ $session->description }}</p>
  <dl class="row small mb-0"><dt class="col-sm-3">Community</dt><dd class="col-sm-9"><a href="{{ route('community.show',$session->community) }}">{{ $session->community->name }}</a></dd><dt class="col-sm-3">Host</dt><dd class="col-sm-9"><a href="{{ route('users.profile',$session->host) }}">{{ $session->host->first_name }} {{ $session->host->last_name }}</a>@if($session->format==='tutor')<x-teaching-badges :user="$session->host" />@endif</dd><dt class="col-sm-3">When</dt><dd class="col-sm-9">{{ $session->starts_at->copy()->setTimezone($session->timezone)->format('M j, Y · g:i A') }}–{{ $session->ends_at->copy()->setTimezone($session->timezone)->format('g:i A') }} ({{ $session->timezone }})</dd><dt class="col-sm-3">Places</dt><dd class="col-sm-9">{{ $session->participants->count() }} / {{ $session->capacity }}, including host</dd></dl>
  <hr><h2 class="h6 fw-bold">{{ $session->meeting_method==='online'?'Online meeting':'Meeting venue' }}</h2>
  @if($participation)<p class="text-break mb-0" style="white-space:pre-wrap">{{ $session->meeting_details }}</p>@else<p class="text-muted small">Meeting details are available after you book. You must belong to this community to join.</p>@endif
</div></div>
@if(auth()->id()===$session->host_id && $session->status==='scheduled' && $session->ends_at->isPast())
<div class="card shadow-sm border-0 mt-3"><div class="card-body p-4"><h2 class="h5">Record attendance</h2><p class="small text-muted">Select only the people who attended. Each selected attendee must confirm participation before points or hours are awarded. You cannot confirm your own attendance.</p><form method="POST" action="{{ route('study-sessions.complete',$session) }}">@csrf
@foreach($session->participants->where('user_id','!=',$session->host_id) as $person)<div class="form-check mb-2"><input type="checkbox" class="form-check-input" name="attendees[]" value="{{ $person->user_id }}" id="attendee{{ $person->id }}"><label class="form-check-label" for="attendee{{ $person->id }}">{{ $person->user->first_name }} {{ $person->user->last_name }}</label></div>@endforeach
<button class="btn btn-primary mt-3" type="submit">Complete session and request confirmations</button></form></div></div>
@endif
@if($participation && $session->status==='completed' && $participation->attended_at && !$participation->confirmed_at && auth()->id()!==$session->host_id)
<div class="alert alert-info mt-3"><h2 class="h6">Confirm your participation</h2><p class="small">Only confirm if you attended this session. This credits the scheduled duration to your progress and awards {{ config('learning.session_points') }} points to you and, once, to the host.</p><form method="POST" action="{{ route('study-sessions.confirm',$session) }}">@csrf<button class="btn btn-primary" type="submit">I attended this session</button></form></div>
@elseif($participation && $session->status==='completed')
<div class="alert alert-secondary mt-3">{{ $participation->confirmed_at?'Your participation is confirmed.':($participation->attended_at?'Waiting for an attendee to confirm the session.':'The host did not mark you present. Contact the host if this is incorrect.') }}</div>
@endif
</div><div class="col-lg-4"><div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4"><h2 class="h5 mb-3">Your booking</h2>
@if($session->status==='scheduled')
 @if(auth()->id()===$session->host_id)<p>You are hosting this session.</p><form action="{{ route('study-sessions.cancel',$session) }}" method="POST">@csrf<button class="btn btn-outline-danger" type="submit">Cancel session</button></form>
 @elseif($participation)<p class="text-success">Your place is booked.</p>@if($session->starts_at->isFuture())<form action="{{ route('study-sessions.leave',$session) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-outline-secondary" type="submit">Leave session</button></form>@endif
 @elseif($session->starts_at->isFuture())<form action="{{ route('study-sessions.join',$session) }}" method="POST">@csrf<button class="btn btn-primary w-100" type="submit" @disabled($session->participants->count()>=$session->capacity)>{{ $session->participants->count()>=$session->capacity?'Session full':'Book a place' }}</button></form>
 @else<p class="text-muted">This session has already started.</p>@endif
@else<p class="text-muted">This session is {{ $session->status }}.</p>@endif
<a href="{{ route('schedule') }}#bookings" class="btn btn-link px-0 mt-2">View my schedule</a>
</div></div>
@if($participation)<div class="card shadow-sm border-0 mt-3"><div class="card-body p-4"><h2 class="h6">Group members</h2>@foreach($session->participants as $person)<div class="small border-bottom py-2">{{ $person->user->first_name }} {{ $person->user->last_name }} @if($person->user_id===$session->host_id)<span class="text-muted">· Host</span>@endif @if($person->confirmed_at)<i class="bi bi-check-circle text-success" title="Participation confirmed"></i>@endif</div>@endforeach</div></div>@endif
</div></div>
@endsection
