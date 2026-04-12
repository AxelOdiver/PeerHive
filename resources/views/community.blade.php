@extends('layouts.dashboard')

@section('title', 'Community')
@section('page-title', 'Community')

@push('styles')
<style>
  .card-hover:hover {
    transform: translateY(-5px);
    transition: transform 0.2s ease;
  }
  
  .btn-join {
    background-color: var(--bs-emphasis-color) !important;
    color: var(--bs-body-bg) !important;
    border: none;
    transition: all 0.3s ease;
  }
  
  .btn-join:hover {
    opacity: 0.5;
  }
</style>
@endpush

@section('content')
<h2 class="mb-3 fw-bold">Community</h2>
<div class="row">
  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
      <h3 class="fw-bold mb-4">Graphic Era</h3>
      <p class="text-muted mb-4">Unleash your creativity and join our vibrant community of graphic designers, where ideas ignite and talents flourish.</p>
      <button type="button" class="btn btn-join w-100 rounded-3 text-uppercase fw-bold py-2">Join Now</button>    
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
      <h3 class="fw-bold mb-4">Dev Era</h3>
      <p class="text-muted mb-4">Debug your logic and join our vibrant community of developers, where syntax connects and clean code flourishes.</p>
      <button type="button" class="btn btn-join w-100 rounded-3 text-uppercase fw-bold py-2">Join Now</button>    
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
      <h3 class="fw-bold mb-4">Fluent Era</h3>
      <p class="text-muted mb-4">Master the global tongue and join our vibrant community of language learners, where voices ignite and expression flourishes.</p>
      <button type="button" class="btn btn-join w-100 rounded-3 text-uppercase fw-bold py-2">Join Now</button>    
    </div>
  </div>
</div>
@endsection
