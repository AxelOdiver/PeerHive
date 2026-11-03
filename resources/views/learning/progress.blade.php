@extends('layouts.dashboard')
@section('title', 'My Progress')
@section('content')
<div class="{{ auth()->user()->progress_theme==='journal_cover'?'learning-journal-header p-4 rounded-4 mb-4':'' }}"><x-learning-header title="My Progress" subtitle="Your confirmed learning and teaching activity, all in one place."><a href="{{ route('study-sessions.index') }}" class="btn btn-primary">Find a study session</a><button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#progressSettingsModal">Progress settings</button></x-learning-header></div>
@include('learning.progress-tabs')
<div class="row g-3 mb-4">
@foreach([['Confirmed sessions',$records->count(),$stats['learning'].' learning · '.$stats['teaching'].' teaching','bi-check-circle'],['Confirmed hours',number_format(($stats['learning_minutes']+$stats['teaching_minutes'])/60,1),'Scheduled duration of confirmed attendance','bi-clock'],['Subjects explored',$subjects,'Across confirmed sessions','bi-book'],['Available points',$balance,'Earned points minus unlocked rewards','bi-gift']] as [$label,$value,$caption,$icon])
<div class="col-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><div class="d-flex justify-content-between gap-2 text-muted small"><span>{{ $label }}</span><i class="bi {{ $icon }} text-primary"></i></div><div class="fs-2 fw-bold my-2">{{ $value }}</div><div class="small text-muted">{{ $caption }}</div></div></div></div>
@endforeach
</div>
<div class="row g-4"><div class="col-lg-7"><article class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4"><h2 class="h5">This week’s activity</h2><p class="small text-muted">Hours by session start date · {{ $timezone }}</p><div class="d-flex gap-3 small mb-3"><span><i class="bi bi-square-fill text-primary me-1"></i>Learning</span><span><i class="bi bi-square-fill text-secondary me-1"></i>Teaching</span></div>
@php
 $maxMinutes=max(60,collect($days)->flatMap(fn($day)=>array_values($day))->max());
@endphp
<div class="learning-chart" aria-label="Weekly learning and teaching hours">
@foreach($days as $index=>$day)<div class="learning-chart-day"><div class="learning-chart-bars"><div class="bg-primary rounded-top" style="height:{{ $day['learning']/$maxMinutes*140 }}px" title="Learning: {{ number_format($day['learning']/60,1) }} hours"></div><div class="bg-secondary rounded-top" style="height:{{ $day['teaching']/$maxMinutes*140 }}px" title="Teaching: {{ number_format($day['teaching']/60,1) }} hours"></div></div><span class="small text-muted">{{ ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$index] }}</span><span class="small">{{ number_format(($day['learning']+$day['teaching'])/60,1) }}h</span></div>@endforeach
</div><p class="small text-muted mt-3 mb-0">{{ $weekly['learning']+$weekly['teaching']===0?'No confirmed activity this week yet. Book a session to get started.':'Only confirmed participation contributes to these totals.' }}</p></div></article></div>
<div class="col-lg-5"><article class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
<h2 class="h5">My Skill Tree</h2><p class="small text-muted">{{ $completedTopics }} completed topics · Based on confirmed learning and peer-reviewed outcomes.</p>
@forelse($learningTopics as $node)
  <div class="border-top py-3">
    <div class="d-flex justify-content-between align-items-start gap-2"><a class="fw-semibold text-body text-decoration-none" href="{{ route('progress.skill-tree', ['subject'=>$node['topic']->parent_id,'topic'=>$node['topic']->id]) }}">{{ $node['topic']->name }}</a><span class="badge text-bg-secondary">{{ $node['status'] }}</span></div>
    <p class="small text-muted mb-1">{{ $node['sessions'] }} learning sessions · {{ $node['minutes'] }} minutes</p>
    @if($node['requirements']->isNotEmpty())
      <p class="small mb-0">{{ $node['requirements']->pluck('key')->diff($node['covered'])->count() }} of {{ $node['requirements']->count() }} completion outcomes pending</p>
    @else<p class="small text-muted mb-0">Completion requirements not yet defined.</p>@endif
  </div>
@empty
  <p class="text-muted">No learning topics tracked yet. Choose your <a href="{{ route('profile') }}#learning-interests">learning interests</a> or complete a learning session to get started.</p>
@endforelse
<a class="btn btn-outline-primary mt-2" href="{{ route('progress.skill-tree') }}">Open Skill Tree</a>
</div></article></div></div>
<article class="card border-0 shadow-sm rounded-4 mt-4"><div class="card-body p-4"><div class="d-flex justify-content-between gap-2 mb-3"><h2 class="h5">Recent sessions</h2><a href="{{ route('history') }}" class="small">View history</a></div>
@forelse($recent as $record)<div class="d-flex justify-content-between gap-3 border-bottom py-3"><div><a href="{{ route('study-sessions.show',$record->session) }}" class="text-body fw-semibold text-decoration-none">{{ $record->session->title }}</a><div class="text-muted small">{{ $record->session->subject }} · {{ $record->session->starts_at->copy()->setTimezone($timezone)->format('M j, Y') }}</div></div><span class="small {{ $record->confirmed_at?'text-success':'text-muted' }}">{{ $record->confirmed_at?'Confirmed':($record->attended_at?'Awaiting confirmation':'Not marked present') }}</span></div>@empty<p class="text-muted small mb-0">Your completed sessions will appear here. Attendance needs confirmation before it counts toward progress.</p>@endforelse
</div></article>
<x-modal id="progressSettingsModal" title="Progress settings"><form id="progressSettingsForm" action="{{ route('progress.settings') }}" method="POST">@csrf @method('PUT')
<label class="form-label" for="learningTimezone">Timezone</label><select id="learningTimezone" name="timezone" class="form-select">@foreach(DateTimeZone::listIdentifiers() as $zone)<option value="{{ $zone }}" @selected(old('timezone',$timezone)===$zone)>{{ $zone }}</option>@endforeach</select></form><x-slot:footer><button type="submit" form="progressSettingsForm" class="btn btn-primary">Save settings</button></x-slot:footer></x-modal>
@endsection
