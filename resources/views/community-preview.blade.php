@extends('layouts.dashboard')
@section('title', 'Community')
@section('content-class', 'community-content')
@section('content')
<div class="community-hero"><x-community-banner :community="$community" size="header" :viewable="true" /></div>
<a href="{{ route('community') }}" class="btn btn-primary mb-4"><i class="bi bi-arrow-left"></i> Back to Communities</a>
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><h2 class="fw-bold mb-0">{{ $community->name }}</h2><span class="badge bg-danger rounded-pill"><i class="bi bi-lock-fill me-1"></i>Private</span></div>
  <p class="text-muted">Created by {{ $community->user?->first_name }} {{ $community->user?->last_name }}</p>
  <p>{{ $community->description ?: 'No description available.' }}</p>
  <div class="d-flex flex-wrap gap-2 mb-3"><span class="badge bg-secondary">{{ $community->subject }}</span><span class="badge bg-secondary">{{ $memberCount }} / {{ $community->member_limit }} members</span></div>
  <p class="text-muted">Posts, comments, member details, and study sessions are visible to members only. Request to join for owner approval, or accept an invitation.</p>
  @include('components.community-join-actions')
</div>
@endsection
