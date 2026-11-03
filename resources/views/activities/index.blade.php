@extends('layouts.dashboard')
@section('title', 'Session activities')
@section('content')
<a href="{{ route('messages', ['conversation' => $conversation->id, 'activities' => 1]) }}" class="btn btn-outline-secondary mb-3">Back to session conversation</a>
<h1 class="h4 mb-3">{{ $conversation->name }} · Activities</h1>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@include('activities.panel')
@endsection
