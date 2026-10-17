@extends('layouts.auth')
@section('title', 'Privacy Policy | PeerHive')
@section('body-class', 'bg-body-tertiary')
@section('content')
<main class="container py-5" style="max-width:850px">
  <a href="{{ url('/') }}" class="d-inline-block mb-3">Back to PeerHive</a>
  <article class="card shadow-sm"><div class="card-body p-4 p-md-5">
    @include('legal.partials.privacy')
  </div></article>
</main>
@endsection
