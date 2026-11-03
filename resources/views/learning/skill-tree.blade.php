@extends('layouts.dashboard')
@section('title', 'My Skill Tree')
@section('content')
<x-learning-header title="My Progress" subtitle="Explore topics, practise with peers, and track reviewed learning activities." />
@include('learning.progress-tabs')
<form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-4"><div><label for="treeSubject" class="form-label">Subject</label><select id="treeSubject" name="subject" class="form-select" aria-describedby="treeSubjectHint">@foreach($subjects as $option)<option value="{{ $option->id }}" @selected($subject?->id === $option->id)>{{ $option->name }}</option>@endforeach</select><span id="treeSubjectHint" class="form-text">Topics update when you choose a subject.</span></div><noscript><button class="btn btn-outline-primary" type="submit">Show topics</button></noscript></form>
<div class="row g-3"><div class="col-xl-7"><section class="card border-0 shadow-sm rounded-4 p-3">
  <h2 class="h5">{{ $subject?->name ?? 'No topics available yet' }}</h2><p class="small text-muted">Lines show suggested prerequisites. You can learn any topic without completing another first.</p>
  @if($nodes->isEmpty())<p class="text-muted mb-0">No approved topics in this subject yet. Newly approved topics will appear here automatically.</p>@else<div class="skill-tree-map"><ul>@include('learning.topic-branch', ['branch'=>$nodes->filter(fn ($node) => !$node['plan']?->prerequisite_id || !$nodes->contains('topic.id', $node['plan']->prerequisite_id))])</ul></div>@endif
</section></div><div class="col-xl-5">
@if($selected)
<section class="card border-0 shadow-sm rounded-4 p-4"><h2 class="h4">{{ $selected['topic']->name }}</h2><span class="badge text-bg-secondary align-self-start mb-3">{{ $selected['status'] }}</span>
  <p class="text-break" style="white-space:pre-wrap">{{ $selected['plan']?->description ?: 'Explore this topic with a learning partner.' }}</p>
  @if($selected['plan']?->prerequisite)<p class="small">Suggested prerequisite: <a href="{{ route('progress.skill-tree', ['subject'=>$subject->id,'topic'=>$selected['plan']->prerequisite_id]) }}">{{ $selected['plan']->prerequisite->name }}</a></p>@endif
  <p class="small">{{ $selected['sessions'] }} confirmed learning sessions · {{ $selected['minutes'] }} minutes</p>
  <h3 class="h6">Topic completion requirements</h3>
  @forelse($selected['requirements'] as $outcome)<p class="small mb-2"><i class="bi {{ $selected['covered']->contains($outcome['key']) ? 'bi-check-circle-fill text-success' : 'bi-circle' }} me-1" aria-hidden="true"></i>{{ $outcome['text'] }}<span class="visually-hidden"> — {{ $selected['covered']->contains($outcome['key']) ? 'Met' : 'Pending' }}</span></p>@empty<p class="small text-muted">Not defined yet. Confirmed sessions and passed activities track practice; they do not mark this topic completed.</p>@endforelse
  <h3 class="h6 mt-3">Passed activities</h3>
  @forelse($selected['passed'] as $activity)<a class="small mb-2" href="{{ route('session-activities.index', $activity->conversation_id) }}#activity-{{ $activity->id }}">{{ $activity->title }} · reviewed by {{ $activity->teacher->first_name }}</a>@empty<p class="small text-muted">No passed activities yet.</p>@endforelse
  <a href="{{ route('dashboard', ['learn_topic'=>$selected['topic']->id]) }}#recommendedPeersHeading" class="btn btn-primary mt-3">Find a learning partner</a>
  <p class="small text-muted mt-3 mb-0">Completion is based on peer-reviewed outcomes, not a standardized exam, certificate, or teaching verification. Requirements may be updated by an admin; your previous activity history stays available.</p>
</section>
@endif
</div></div>
@endsection
