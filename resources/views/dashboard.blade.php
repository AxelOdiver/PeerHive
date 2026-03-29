@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<style>
  
  .card-hover:hover {
    transform: translateY(-5px);
    transition: transform 0.2s ease;
  }
  
  .btn-swap {
    background-color: var(--bs-emphasis-color) !important;
    color: var(--bs-body-bg) !important;
    border: none;
    transition: all 0.3s ease;
  }
  
  .btn-swap:hover {
    opacity: 0.5;
  }
  
</style>
@endpush
<!-- FREE LEARNING -->
@section('content')
<div class="row d-flex align-items-stretch">
  <div class="col-12 col-md-6 mb-4 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Free Learning</h2>
    <div class="card card-hover border-0 shadow-sm rounded-2 overflow-hidden h-100">
      <div style="overflow: hidden;"> 
        <img src="https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg" class="card-img-top rounded-2" style="object-fit: cover; height: 150px;" />
      </div>
      <hr class="m-0"/>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <small class="text-muted">1,234 Students</small>
          <small class="text-muted">10h 26m</small>
          <button class="text-muted btn btn-sm p-0 shadow-none fs-6 fav-btn" data-id="2"><i class="bi bi-bookmark"></i></button>
        </div>
        <h5 class="mb-1 fw-bold">Learn Python: The Complete Python Programming Course</h5>
        <small class="text-muted">Axel Odiver</small>
      </div>
    </div>
  </div>
  <!-- MOST COLLABORATED -->
  <div class="col-12 col-md-6 mb-4 mt-2 mt-md-0 d-flex flex-column">
    <h2 class="mb-3 fw-bold">Swap, learn, grow</h2>
    <div class="card card-hover border-0 shadow-sm rounded-2 p-4 card-border-dark overflow-hidden h-100 d-flex flex-column">
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
  <!-- TOP STUDENTS -->
  <h2 class="mb-3 fw-bold">Top Students</h2>
  <div class="row">
    @foreach($topstudents as $topstudent)
    <div class="col-12 col-md-6 col-xl-4 mb-4">
      <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
        <div class="d-flex align-items-start gap-2 gap-sm-3">        
          
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0" 
          style="width: 60px; height: 60px; font-size: 1.5rem;">
          {{ strtoupper(substr($topstudent->first_name, 0, 1)) }}{{ strtoupper(substr($topstudent->last_name, 0, 1)) }}
        </div>      
        
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <h5 class="fw-bold mb-0 text-truncate">{{ $topstudent->first_name }} {{ $topstudent->last_name }}</h5>
            
            <button type="button" class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0 fav-btn" data-id="{{ $topstudent->id }}">
              @if(in_array($topstudent->id, $favoritedIds))
              <i class="bi bi-bookmark-fill text-warning"></i>
              @else
              <i class="bi bi-bookmark"></i>
              @endif
            </button>
          </div>        
          
          <p class="text-muted small mb-1 text-truncate">Peer Student
            <span class="text-warning text-nowrap ms-1">
              @for($i = 0; $i < 4; $i++) <i class="bi bi-star-fill"></i> @endfor
              <i class="bi bi-star"></i>
            </span>  
          </p>       
          
          <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
            <div class="d-flex align-items-center"><button type="button" class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-heart"></i></button><small class="text-muted fw-semibold ms-1">0</small></div>
            <div class="d-flex align-items-center"><button type="button" class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-arrow-left-right"></i></button><small class="text-muted fw-semibold ms-1">0</small></div>
            <div class="d-flex align-items-center"><button type="button" class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-chat-dots"></i></button><small class="text-muted fw-semibold ms-1">0</small></div>
          </div>
          
          <button type="button" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 open-swap-modal" data-id="{{ $topstudent->id }}">
            Swap
          </button>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>
  <!-- PeerHive Students -->
  <h2 class="mb-3 fw-bold">PeerHive Students</h2>
  <div class="row">
    @foreach($students as $student)
    <div class="col-12 col-md-6 col-xl-4 mb-4">
      <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
        <div class="d-flex align-items-start gap-2 gap-sm-3">        
          
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0" 
          style="width: 60px; height: 60px; font-size: 1.5rem;">
          {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
        </div>      
        
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <h5 class="fw-bold mb-0 text-truncate">{{ $student->first_name }} {{ $student->last_name }}</h5>
            
            <button type="button" class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0 fav-btn" data-id="{{ $student->id }}">
              @if(in_array($student->id, $favoritedIds))
              <i class="bi bi-bookmark-fill text-warning"></i>
              @else
              <i class="bi bi-bookmark"></i>
              @endif
            </button>
          </div>        
          
          <p class="text-muted small mb-1 text-truncate">Peer Student
            <span class="text-warning text-nowrap ms-1">
              @for($i = 0; $i < 4; $i++) <i class="bi bi-star-fill"></i> @endfor
              <i class="bi bi-star"></i>
            </span>  
          </p>       
          
          <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
            <div class="d-flex align-items-center"><button type="button" class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-heart"></i></button><small class="text-muted fw-semibold ms-1">0</small></div>
            <div class="d-flex align-items-center"><button type="button" class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-arrow-left-right"></i></button><small class="text-muted fw-semibold ms-1">0</small></div>
            <div class="d-flex align-items-center"><button type="button" class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-chat-dots"></i></button><small class="text-muted fw-semibold ms-1">0</small></div>
          </div>
          
          <button type="button" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 open-swap-modal" data-id="{{ $student->id }}">
            Swap
          </button>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

<x-modal id="swapModal" title="Swap Request">
  <form id="swapRequestForm">
    <input type="hidden" id="swapUserId" name="user_id" value="">
    <div class="form-floating">
      <textarea class="form-control" placeholder="Write a message" id="swapRequestMessage" name="message" style="height: 100px"></textarea>
      <label for="swapRequestMessage">Write a message</label>
    </div>
  </form>

  <x-slot:footer>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-primary" id="sendSwapRequestBtn" form="swapRequestForm">Send Request</button>
  </x-slot:footer>
</x-modal>
@endsection
