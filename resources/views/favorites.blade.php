@extends('layouts.dashboard')

@section('title', 'My Favorites')
@section('page-title', 'My Favorites')

@push('styles')
<style>
  
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

@section('content')
<div class="container-fluid">
  <h2 class="mb-3 fw-bold">My Favorites</h2>
  <div class="row" id="favoritesList">
    @foreach($favorites as $student)
    <div class="col-12 col-md-6 col-xl-4 mb-4 favorite-card" id="card-{{ $student->id }}">
      <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
        <div class="d-flex align-items-start gap-2 gap-sm-3">                      
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0" 
          style="width: 60px; height: 60px; font-size: 1.5rem;">
          {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
        </div>      
        
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <h5 class="fw-bold mb-0 text-truncate">{{ $student->first_name }} {{ $student->last_name }}</h5>                
            <button class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0 fav-btn" data-id="{{ $student->id }}">
              <i class="bi bi-bookmark-fill text-warning"></i>
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

<div class="col-12" id="empty-favorites-alert" style="display: {{ $favorites->isEmpty() ? 'block' : 'none' }};">
  <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
    <i class="bi bi-bookmark-x fs-1 text-muted mb-3 d-block"></i>
    <h5 class="text-muted">You haven't bookmarked any students yet.</h5>
    <p class="text-muted mb-0">Go to your dashboard to find peers to collaborate with!</p>
  </div>
</div>
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