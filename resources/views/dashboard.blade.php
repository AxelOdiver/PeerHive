@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')

@endpush

@section('content')
<div class="row d-flex align-items-stretch">
  <div class="col-12 col-md-6 mb-4 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Free Learning</h2>
    <div class="card border-0 shadow-sm rounded-2 overflow-hidden h-100">
      <div style="overflow: hidden;"> 
        <img src="https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg" class="card-img-top rounded-2" style="object-fit: cover; height: 150px;" />
      </div>
      <hr class="m-0"/>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <small class="text-muted">1,234 Students</small>
            <small class="text-muted">10h 26m</small>
            <button class="text-muted btn btn-sm p-0 shadow-none fs-6"><i class="bi bi-bookmark"></i></button>
          </div>
          <h5 class="mb-1 fw-bold">Learn Python: The Complete Python Programming Course</h5>
          <small class="text-muted">Axel Odiver</small>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6 mb-4 mt-2 mt-md-0 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Swap, learn, grow</h2>
    <div class="card border-0 shadow-sm rounded-2 p-4 card-border-dark overflow-hidden h-100 d-flex flex-column">
       <div class="flex-grow-1">
          <p><a href="#" class="fw-bold btn btn-link link-body-emphasis text-decoration-none pb-1 p-0">Most Collaborated</a></p>
          <p class="text-muted" style="font-size: 1.1rem;">
            Discover the most accomplished and influential professionals
          </p>
       </div>

      <div class="d-flex align-items-center gap-0 mt-auto">
        <button class="btn btn-sm p-0 shadow-none fs-4"><i class="bi bi-heart"></i></button>
        <small class="text-muted fw-semibold p-2 me-2">20k+</small>
        <button class="btn btn-sm p-0 shadow-none fs-4"><i class="bi bi-arrow-left-right"></i></button>
        <small class="text-muted fw-semibold p-2 me-2">500+</small>
        <button class="btn btn-sm p-0 shadow-none fs-4"><i class="bi bi-chat-dots"></i></button>
        <small class="text-muted fw-semibold p-2 me-2">1k+</small>
      </div>
    </div>
  </div>
</div>
@endsection


