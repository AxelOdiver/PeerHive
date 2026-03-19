@extends('layouts.dashboard')

@section('title', 'Swap')
@section('page-title', 'Swap')
@push('styles')
<style>
  
  .card:hover {
    transform: translateY(-5px);
    transition: transform 0.5s ease;
  }

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
<div class="container-fluid px-3 d-flex flex-column align-items-start gap-3 mb-4">
  <div class="d-inline-flex bg-light rounded-0 shadow-lg p-0 overflow-hidden w-100" style="max-width: 500px;">
    <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked>
    <label class="btn btn-outline-dark flex-fill border-0 rounded-0 py-2 fw-bold shadow-none" for="btnradio1">Send
    </label>
    <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off">
    <label class="btn btn-outline-dark flex-fill border-0 rounded-0 py-2 fw-bold shadow-none" for="btnradio2">Received
    </label>    
  </div>
  <div class="d-inline-flex bg-dark rounded-1 shadow-lg p-0 overflow-hidden">
    <button class="btn btn-sm btn-dark rounded-1 px-3 py-1 border-0 d-flex align-items-center shadow-none">
      <i class="bi bi-funnel-fill me-1 text-white"></i>
      <span class="fw-semibold text-uppercase text-white" style="font-size: 0.75rem;">Filter</span>
    </button>
  </div>
</div>
<!-- Swap List -->
<h2 class="mb-3 mt-2 fw-bold">Students</h2>
<div class="row">
  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
      <div class="d-flex align-items-start gap-2 gap-sm-3">         
        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0" 
          style="width: 60px; height: 60px; font-size: 1.5rem;">AO
        </div>       
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <h5 class="fw-bold mb-0 text-truncate">Axel Odiver</h5>
              <button class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0"><i class="bi bi-bookmark"></i></button>
            </div>        
              <p class="text-muted small mb-1 text-truncate">Web Developer
                <span class="text-warning text-nowrap ms-1">
                  @for($i = 0; $i < 4; $i++) <i class="bi bi-star-fill"></i> @endfor
                  <i class="bi bi-star"></i>
                </span>  
              </p>       
                <p class="text-success small fw-bold mb-2 text-nowrap">Experience: 5 years +</p>   
                  <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-heart"></i></button>
                      <small class="text-muted fw-semibold ms-1">5k+</small>
                    </div>
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-arrow-left-right"></i></button>
                      <small class="text-muted fw-semibold ms-1">60</small>
                    </div>
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-chat-dots"></i></button>
                      <small class="text-muted fw-semibold ms-1">128</small>
                    </div>
                  </div>
                    <div class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2">
                      Unswap
                    </div>
              </div>
          </div>
      </div>
  </div>

  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
      <div class="d-flex align-items-start gap-2 gap-sm-3">         
        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0" 
          style="width: 60px; height: 60px; font-size: 1.5rem;">DB
        </div>       
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <h5 class="fw-bold mb-0 text-truncate">Dominic Belen</h5>
              <button class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0"><i class="bi bi-bookmark"></i></button>
            </div>        
              <p class="text-muted small mb-1 text-truncate">Back-End Developer
                <span class="text-warning text-nowrap ms-1">
                  @for($i = 0; $i < 4; $i++) <i class="bi bi-star-fill"></i> @endfor
                  <i class="bi bi-star"></i>
                </span>  
              </p>       
                <p class="text-success small fw-bold mb-2 text-nowrap">Experience: 8 years +</p>   
                  <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-heart"></i></button>
                      <small class="text-muted fw-semibold ms-1">8k+</small>
                    </div>
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-arrow-left-right"></i></button>
                      <small class="text-muted fw-semibold ms-1">65</small>
                    </div>
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-chat-dots"></i></button>
                      <small class="text-muted fw-semibold ms-1">146</small>
                    </div>
                  </div>
                    <div class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2">
                      Unswap
                    </div>
              </div>
          </div>
      </div>
  </div>

  <div class="col-12 col-md-6 col-xl-4 mb-4">
    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100">
      <div class="d-flex align-items-start gap-2 gap-sm-3">         
        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0" 
          style="width: 60px; height: 60px; font-size: 1.5rem;">JP
        </div>       
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <h5 class="fw-bold mb-0 text-truncate">John Paul Castro</h5>
              <button class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0"><i class="bi bi-bookmark"></i></button>
            </div>        
              <p class="text-muted small mb-1 text-truncate">Data Analyst
                <span class="text-warning text-nowrap ms-1">
                  @for($i = 0; $i < 3; $i++) <i class="bi bi-star-fill"></i> @endfor
                  <i class="bi bi-star"></i>
                </span>  
              </p>       
                <p class="text-success small fw-bold mb-2 text-nowrap">Experience: 10 years +</p>   
                  <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-heart"></i></button>
                      <small class="text-muted fw-semibold ms-1">3k+</small>
                    </div>
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-arrow-left-right"></i></button>
                      <small class="text-muted fw-semibold ms-1">65</small>
                    </div>
                    <div class="d-flex align-items-center">
                      <button class="btn btn-sm p-0 shadow-none fs-5"><i class="bi bi-chat-dots"></i></button>
                      <small class="text-muted fw-semibold ms-1">1000</small>
                    </div>
                  </div>
                    <div class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2">
                      Unswap
                    </div>
              </div>
          </div>
      </div>
  </div>
</div>
@endsection
