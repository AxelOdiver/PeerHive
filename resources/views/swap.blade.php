@extends('layouts.dashboard')

@section('title', 'Swap')
@section('page-title', 'Swap')
@push('styles')
<style>
  
  .btn-swap {
    background-color: var(--bs-emphasis-color) !important;
    color: var(--bs-body-bg) !important;
    border: none;
    transition: all 0.5s ease;
  }
  
  .btn-swap:hover {
    opacity: 0.5;
  }
  
</style>
@endpush
@section('content')
<div class=" d-flex flex-column align-items-start gap-3 mb-4">
  <div class="d-inline-flex bg-light rounded-0 shadow-sm p-0 overflow-hidden w-100" style="max-width: 500px;">
    <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked>
    <label class="btn btn-outline-dark flex-fill border-0 rounded-0 py-2 fw-bold shadow-none" for="btnradio1">Send
    </label>
    <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off">
    <label class="btn btn-outline-dark flex-fill border-0 rounded-0 py-2 fw-bold shadow-none" for="btnradio2">Received
    </label>    
  </div>
  <!-- Dropdown Filter -->
  <div class="border border-secondary rounded-1 p-0 overflow-hidden">
    <a class="nav-link dropdown-toggle show rounded-1 px-3 py-1 d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="bi bi-funnel-fill me-1"></i>
      <span class="fw-semibold text-uppercase" style="font-size: 0.75rem;">Filter</span>
    </a>
    <ul class="dropdown-menu show text-center" data-bs-popper="static">
      <li>
        <a class="dropdown-item" href="#">Accepted</a>
      </li>
      <li>
        <hr class="dropdown-divider">
      </li>
      <li>
        <a class="dropdown-item" href="#">Declined</a>
      </li>
    </ul>
  </div>
</div>  
<!-- Swap List -->
<div class="container-fluid" id="swapPage">
  @if($swaps->count() > 0)
  
  <h2 class="fw-bold mb-4" id="swapHeading">Start a Swap with <span id="swapCount">{{ $swaps->count() }}</span> Peers</h2>
  
  <div class="row" id="swapList">
    @foreach($swaps as $swap)
    <div class="col-md-6 mb-3 swap-card" data-swap-id="{{ $swap->id }}">
      <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
        <div class="d-flex flex-column h-100">
          <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white" 
            style="width: 50px; height: 50px; font-size: 1.2rem;">
            {{ strtoupper(substr($swap->requestedUser->first_name, 0, 1)) }}{{ strtoupper(substr($swap->requestedUser->last_name, 0, 1)) }}
          </div> 
          <div>
            <h5 class="fw-bold mb-0">{{ $swap->requestedUser->first_name }} {{ $swap->requestedUser->last_name }}</h5>
            <p class="text-muted mb-0 small">{{ $swap->requestedUser->email }}</p>
          </div>
        </div>
        <div class="d-flex justify-content-end mt-3">
          @if($swap->status === 'pending')
          <form method="POST" action="{{ route('swap.destroy', $swap) }}" class="me-2 cancel-swap-form w-50 flex-grow-1">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 open-swap-modal">Unswap</button>
          </form>
          @endif
          <div class="text-end">
            <small class="text-muted">Status</small>
            <span class="badge text-bg-{{ $swap->status === 'accepted' ? 'success' : ($swap->status === 'declined' ? 'danger' : 'warning') }}">
              {{ ucfirst($swap->status) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>
</div>

@else
<div id="swapEmptyState">
  <h2 class="fw-bold mb-4">Swap Dashboard</h2>
  <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
    <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
    <h5 class="text-muted">You haven't selected any peers to swap with.</h5>
    <p class="text-muted mb-0">Go back to the dashboard, check the boxes on the student cards, and click "Swap"!</p>
    <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">Find Peers</a>
  </div>
</div>
@endif
</div>
@endsection
