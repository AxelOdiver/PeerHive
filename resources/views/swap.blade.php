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

  .swap-filter {
    align-self: flex-start;
  }

  .swap-filter-toggle {
    font-size: 0.75rem;
    line-height: 1;
    min-height: 32px;
  }

  .swap-filter-menu {
    min-width: 9rem;
    font-size: 0.8rem;
  }

  .swap-section-toggle {
    max-width: 320px;
  }

  .swap-section-toggle .btn {
    border: 1px solid var(--bs-border-color);
    background-color: var(--bs-body-bg);
    color: var(--bs-emphasis-color);
  }

  .swap-section-toggle .btn:hover {
    background-color: var(--bs-tertiary-bg);
    color: var(--bs-emphasis-color);
  }

  .swap-section-toggle .btn-check:checked + .btn {
    background-color: var(--bs-emphasis-color);
    border-color: var(--bs-emphasis-color);
    color: var(--bs-body-bg);
  }
</style>
@endpush
@section('content')
<div class="container-fluid px-0">
  <div class="d-inline-flex swap-section-toggle rounded-0 shadow-sm p-0 overflow-hidden w-100 mb-4">
    <input type="radio" class="btn-check" name="swapSection" id="swapSectionSend" autocomplete="off" checked>
    <label class="btn flex-fill rounded-0 py-2 fw-bold shadow-none text-uppercase" for="swapSectionSend">Send</label>

    <input type="radio" class="btn-check" name="swapSection" id="swapSectionReceive" autocomplete="off">
    <label class="btn flex-fill rounded-0 py-2 fw-bold shadow-none text-uppercase" for="swapSectionReceive">Receive</label>
  </div>

  <section class="mb-5" data-swap-section="send">
    <div class="d-flex flex-column align-items-start gap-3 mb-4">
      <h2 class="fw-bold mb-0">Send</h2>

      <div class="dropdown swap-filter">
        <a class="btn btn-outline-secondary btn-sm dropdown-toggle swap-filter-toggle d-inline-flex align-items-center gap-1 px-2 py-1" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="bi bi-funnel-fill me-1"></i>
          <span class="fw-semibold text-uppercase">Filter</span>
        </a>
        <ul class="dropdown-menu swap-filter-menu text-center">
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

    <div id="swapPage">
      @if($sentSwaps->count() > 0)
      <h3 class="fw-bold mb-4" id="swapHeading">Start a Swap with <span id="swapCount">{{ $sentSwaps->count() }}</span> Peers</h3>

      <div class="row" id="swapList">
        @foreach($sentSwaps as $swap)
        <div class="col-md-6 mb-3 swap-card" data-swap-id="{{ $swap->id }}">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex flex-column h-100">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white" style="width: 50px; height: 50px; font-size: 1.2rem;">
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
      @else
      <div id="swapEmptyState">
        <h3 class="fw-bold mb-4">Swap Dashboard</h3>
        <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
          <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
          <h5 class="text-muted">You haven't selected any peers to swap with.</h5>
          <p class="text-muted mb-0">Go back to the dashboard, check the boxes on the student cards, and click "Swap"!</p>
          <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">Find Peers</a>
        </div>
      </div>
      @endif
    </div>
  </section>

  <section class="d-none" data-swap-section="receive">
    <h2 class="fw-bold mb-4">Receive</h2>

    <div id="receiveSection">
      @if($receivedSwaps->count() > 0)
      <h3 class="fw-bold mb-4">Students who requested to swap with you</h3>

      <div class="row">
        @foreach($receivedSwaps as $swap)
        <div class="col-md-6 mb-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex flex-column h-100">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white" style="width: 50px; height: 50px; font-size: 1.2rem;">
                  {{ strtoupper(substr($swap->requester->first_name, 0, 1)) }}{{ strtoupper(substr($swap->requester->last_name, 0, 1)) }}
                </div>
                <div>
                  <h5 class="fw-bold mb-0">{{ $swap->requester->first_name }} {{ $swap->requester->last_name }}</h5>
                  <p class="text-muted mb-0 small">{{ $swap->requester->email }}</p>
                </div>
              </div>

              @if($swap->message)
              <div class="mt-3">
                <small class="text-muted d-block mb-1">Message</small>
                <p class="mb-0">{{ $swap->message }}</p>
              </div>
              @endif

              <div class="d-flex justify-content-end mt-3">
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
      @else
      <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
        <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
        <h5 class="text-muted">No incoming swap requests yet.</h5>
        <p class="text-muted mb-0">When other students request to swap with you, they will appear here.</p>
      </div>
      @endif
    </div>
  </section>
</div>
@endsection
