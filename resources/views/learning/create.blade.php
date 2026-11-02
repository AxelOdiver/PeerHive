@extends('layouts.dashboard')
@section('title', 'Create Study Session')
@section('content')
<x-learning-header title="Create a study session" subtitle="Choose a community, topic, and a time that works for your group." :back-url="$navigationCommunity ? route('community.show', $navigationCommunity) : null" :back-label="$navigationCommunity ? 'Back to Community' : 'Back to Dashboard'"><a href="{{ route('study-sessions.index', ['community' => $navigationCommunity?->id]) }}" class="btn btn-outline-secondary">Back to sessions</a></x-learning-header>
@if($communities->isEmpty())
<div class="alert alert-info">Join a community before hosting a session. <a href="{{ route('community') }}">Find a community</a>.</div>
@else
<div class="card shadow-sm border-0 rounded-4"><div class="card-body p-4">
<form action="{{ route('study-sessions.store', ['community' => $navigationCommunity?->id]) }}" method="POST" class="row g-3">@csrf
<div class="col-md-6"><label class="form-label" for="community_id">Community</label><select id="community_id" name="community_id" class="form-select" required>@foreach($communities as $community)<option value="{{ $community->id }}" @selected(old('community_id',request('community'))==$community->id)>{{ $community->name }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label" for="format">Session format</label><select id="format" name="format" class="form-select" required><option value="peer" @selected(old('format')==='peer')>Peer study group</option><option value="tutor" @selected(old('format')==='tutor')>Tutor-led session</option></select><div class="form-text">Peer organizers need no teaching qualification. Tutor-led hosts must be approved for the subject.</div></div>
<div class="col-md-7"><label for="title" class="form-label">Session title</label><input id="title" name="title" class="form-control" maxlength="150" value="{{ old('title') }}" required></div>
<div class="col-md-5"><label for="subject" class="form-label">Subject</label><input id="subject" name="subject" class="form-control" maxlength="255" value="{{ old('subject') }}" list="approvedSubjects" required><datalist id="approvedSubjects">@foreach(auth()->user()->approvedTeachingQualifications as $qualification)<option value="{{ $qualification->subject_name }}">@endforeach</datalist><div class="form-text">Tutor-led sessions: choose the exact subject on your <a href="{{ route('profile') }}#teaching-verification">approved qualification</a>.</div></div>
<div class="col-12"><label for="description" class="form-label">What will you work on?</label><textarea id="description" name="description" class="form-control" rows="3" maxlength="2000">{{ old('description') }}</textarea></div>
<div class="col-md-4"><label for="starts_at" class="form-label">Start date and time</label><input type="datetime-local" id="starts_at" name="starts_at" class="form-control" value="{{ old('starts_at') }}" required></div>
<div class="col-md-4"><label for="timezone" class="form-label">Timezone for this time</label><select id="timezone" name="timezone" class="form-select" required>@foreach(DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}" @selected(old('timezone',auth()->user()->learning_timezone)===$zone)>{{ $zone }}</option>@endforeach</select></div>
<div class="col-md-2"><label for="duration" class="form-label">Minutes</label><input type="number" id="duration" name="duration" min="15" max="240" class="form-control" value="{{ old('duration',60) }}" required></div>
<div class="col-md-2"><label for="capacity" class="form-label">Places</label><input type="number" id="capacity" name="capacity" min="2" max="50" class="form-control" value="{{ old('capacity',8) }}" required><div class="form-text">Including you.</div></div>
<div class="col-md-4"><label for="meeting_method" class="form-label">Meeting method</label><select id="meeting_method" name="meeting_method" class="form-select"><option value="online" @selected(old('meeting_method')==='online')>Online</option><option value="in_person" @selected(old('meeting_method')==='in_person')>In person</option></select></div>
<div class="col-md-8"><label for="meeting_details" class="form-label">Meeting link or venue</label><textarea id="meeting_details" name="meeting_details" class="form-control" maxlength="1000" rows="2" required>{{ old('meeting_details') }}</textarea><div class="form-text">Only booked participants and the host can see these details. Avoid sharing home addresses.</div></div>
<div class="col-12"><button class="btn btn-primary px-4" type="submit">Create session</button></div>
</form></div></div>
@endif
@endsection
