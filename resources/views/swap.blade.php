@extends('layouts.dashboard')

@section('title', 'Swap')

@section('content')
<div class="d-flex flex-column align-items-start gap-3 mb-4">
  <div class="swap-tab-toggle d-inline-flex rounded-0 shadow-sm p-0 overflow-hidden w-100">
    <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked>
    <label class="btn swap-tab-label flex-fill border-0 rounded-0 py-2 fw-bold shadow-none" for="btnradio1">
      Sent
      @if($sent->count() > 0)
        <span class="badge bg-secondary ms-1">{{ $sent->count() }}</span>
      @endif
    </label>
    <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off">
    <label class="btn swap-tab-label flex-fill border-0 rounded-0 py-2 fw-bold shadow-none" for="btnradio2">
      Received
      @php $pendingReceived = $received->where('status', 'pending')->count(); @endphp
      @if($pendingReceived > 0)
        <span class="badge bg-danger ms-1">{{ $pendingReceived }}</span>
      @endif
    </label>
  </div>
</div>

<!-- SENT TAB -->
<div id="sentPanel">
  @if($sent->count() > 0)
    <h2 class="fw-bold mb-4">
      Swap requests you sent
      <span class="fs-5 text-muted fw-normal ms-1">({{ $sent->count() }})</span>
    </h2>
    <div class="row" id="swapList">
      @foreach($sent as $swap)
        <div class="col-md-6 mb-3 swap-card" data-swap-id="{{ $swap->id }}">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            @if($swap->status === 'accepted')
              <div class="position-absolute top-0 end-0 m-3 z-3">
                <button class="btn btn-sm btn-danger text-light rounded-circle shadow-sm remove-swap-btn" data-id="{{ $swap->id }}" title="Remove Swap">
                  <i class="bi bi-trash3-fill"></i>
                </button>
              </div>
            @endif
            <div class="d-flex flex-column h-100">
              <div class="d-flex align-items-center gap-3">
                @if($swap->requestedUser->profile_picture)
                  <img src="{{ asset('storage/' . $swap->requestedUser->profile_picture) }}" 
                    alt="{{ $swap->requestedUser->first_name }}" 
                    class="swap-avatar rounded-circle shadow-sm flex-shrink-0">
                @else
                  <div class="swap-avatar-fallback rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white flex-shrink-0 shadow-sm">
                    {{ strtoupper(substr($swap->requestedUser->first_name, 0, 1)) }}{{ strtoupper(substr($swap->requestedUser->last_name, 0, 1)) }}
                  </div>
                @endif
                <div>
                  <h5 class="fw-bold mb-0">{{ $swap->requestedUser->first_name }} {{ $swap->requestedUser->last_name }}</h5>
                  <p class="text-muted mb-0 small">{{ $swap->requestedUser->email }}</p>
                </div>
              </div>
              @if($swap->message)
                <p class="text-muted small mt-2 mb-0 fst-italic">"{{ $swap->message }}"</p>
              @endif
              <div class="d-flex align-items-end gap-3 mt-auto pt-2 w-100">
                <!-- Left Action Area (Unswap OR Go to Messages) -->
                <div class="flex-grow-1 mb-0">
                  @if($swap->status === 'pending')
                    <form method="POST" action="{{ route('swap.destroy', $swap) }}" class="cancel-swap-form m-0">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 cancel-swap-btn">Unswap</button>
                    </form>
                  @elseif($swap->status === 'accepted')
                    <a href="{{ route('messages') }}" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2">
                      <i class="bi bi-chat-dots me-2"></i> Go to Messages
                    </a>
                  @endif
                </div>

                <!-- Right Status Area -->
                <div class="d-inline-flex flex-column align-items-center flex-shrink-0">
                  <small class="text-muted mb-1">Status</small>
                  <span class="badge fs-8 px-3 py-2
                    @if($swap->status === 'accepted') text-bg-primary
                    @elseif($swap->status === 'declined') text-bg-danger
                    @else text-bg-warning
                    @endif">
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
      <h2 class="fw-bold mb-4">Swap Dashboard</h2>
      <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
        <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
        <h5 class="text-muted">You haven't selected any peers to swap with.</h5>
        <p class="text-muted mb-0">Go back to the dashboard and click "Swap" on a student card!</p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">Find Peers</a>
      </div>
    </div>
  @endif
</div>

<!-- RECEIVED TAB -->
<div id="receivedPanel" class="panel-hidden">
  @if($received->count() > 0)
    <h2 class="fw-bold mb-4">
      Swap requests you received
      <span class="fs-5 text-muted fw-normal ms-1">({{ $received->count() }})</span>
    </h2>
    <div class="row">
      @foreach($received as $swap)
        <div class="col-md-6 mb-3 received-swap-card" data-swap-id="{{ $swap->id }}">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            @if($swap->status === 'accepted')
              <div class="position-absolute top-0 end-0 m-3 z-3">
                <button class="btn btn-sm btn-danger text-light rounded-circle shadow-sm remove-swap-btn" data-id="{{ $swap->id }}" title="Remove Swap">
                  <i class="bi bi-trash3-fill"></i>
                </button>
              </div>
            @endif
            <div class="d-flex flex-column h-100">
              <div class="d-flex align-items-center gap-3 mb-2">
                @if($swap->requester->profile_picture)
                  <img src="{{ asset('storage/' . $swap->requester->profile_picture) }}" 
                    alt="{{ $swap->requester->first_name }}" 
                    class="swap-avatar rounded-circle shadow-sm flex-shrink-0">
                @else
                  <div class="swap-avatar-fallback rounded-circle d-flex align-items-center justify-content-center fw-bold bg-secondary text-white flex-shrink-0 shadow-sm">
                    {{ strtoupper(substr($swap->requester->first_name, 0, 1)) }}{{ strtoupper(substr($swap->requester->last_name, 0, 1)) }}
                  </div>
                @endif
                <div>
                  <h5 class="fw-bold mb-0">{{ $swap->requester->first_name }} {{ $swap->requester->last_name }}</h5>
                  <p class="text-muted mb-0 small">{{ $swap->requester->email }}</p>
                </div>
              </div>
              @if($swap->message)
                <p class="text-muted small fst-italic mb-2">"{{ $swap->message }}"</p>
              @endif
              <div class="d-flex align-items-end gap-3 mt-auto pt-2 w-100">
                <!-- Accept/Decline OR Go to Messages -->
                <div class="flex-grow-1 mb-0">
                  @if($swap->status === 'pending')
                    <div class="d-flex gap-2">
                      <button
                        class="btn btn-primary flex-fill rounded-3 respond-swap-btn"
                        data-swap-id="{{ $swap->id }}"
                        data-action="accepted">
                        <i class="bi bi-check-lg me-1"></i> Accept
                      </button>
                      <button
                        class="btn btn-danger flex-fill rounded-3 respond-swap-btn"
                        data-swap-id="{{ $swap->id }}"
                        data-action="declined">
                        <i class="bi bi-x-lg me-1"></i> Decline
                      </button>
                    </div>
                  @elseif($swap->status === 'accepted')
                    <a href="{{ route('messages') }}" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2">
                      <i class="bi bi-chat-dots me-2"></i> Go to Messages
                    </a>
                  @endif
                </div>
                
                <!-- Right Status Area -->
                <div class="d-inline-flex flex-column align-items-center flex-shrink-0">
                  <small class="text-muted mb-1">Status</small>
                  <span class="badge fs-8 px-3 py-2
                    @if($swap->status === 'accepted') text-bg-primary
                    @elseif($swap->status === 'declined') text-bg-danger
                    @else text-bg-warning
                    @endif">
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
      <h5 class="text-muted">No swap requests received yet.</h5>
      <p class="text-muted mb-0">When other students send you a swap request, it'll appear here.</p>
    </div>
  @endif
</div>
@endsection
