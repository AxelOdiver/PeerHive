@extends('layouts.dashboard')

@section('title', 'My Favorites')

@section('content')
<h2 class="mb-3 fw-bold">My Favorites</h2>
<div class="row" id="favoritesList">
  @foreach($favorites as $student)
    @include('components.dashboard-peer-card', [
      'student' => $student,
      'showMatch' => false,
      'columnClass' => 'col-12 col-md-6 col-xl-4',
      'cardId' => 'card-'.$student->id,
      'cardExtraClass' => 'favorite-card',
    ])
  @endforeach
</div>

<div class="col-12" id="empty-favorites-alert" style="display: {{ $favorites->isEmpty() ? 'block' : 'none' }};">
  <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
    <i class="bi bi-bookmark-x fs-1 text-muted mb-3 d-block"></i>
    <h5 class="text-muted">You haven't bookmarked any students yet.</h5>
    <p class="text-muted mb-0">Go to your dashboard to find peers to collaborate with!</p>
  </div>
</div>

<x-learning-request-modal />
@endsection
