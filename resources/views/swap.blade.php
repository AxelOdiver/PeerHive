@extends('layouts.dashboard')
@section('title', 'Learning Requests')
@section('content')
@php
    $requestTab = in_array(request('tab'), ['sent', 'received', 'history'], true) ? request('tab') : 'sent';
    $pendingReceived = $received->where('status', 'pending')->count();
@endphp
<div class="ph-requests-page">
    <header class="mb-4">
        <h1 class="h3 fw-bold mb-2">Learning Requests</h1>
        <p class="text-body-secondary mb-0">Manage learning help, skill swaps, and past conversations.</p>
    </header>

    <nav class="ph-tabs-nav mb-4" aria-label="Learning request sections" data-ph-tabs data-ph-tabs-key="requests">
        <div class="ph-tabs-track">
            <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" @checked($requestTab === 'sent')>
            <label for="btnradio1" class="ph-tabs-item swap-tab-label" data-request-tab="sent" aria-controls="sentPanel">
                Sent @if($sent->count() > 0)<span class="ph-tabs-count">{{ $sent->count() }}</span>@endif
            </label>
            <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off" @checked($requestTab === 'received')>
            <label for="btnradio2" class="ph-tabs-item swap-tab-label" data-request-tab="received" aria-controls="receivedPanel">
                Received @if($pendingReceived > 0)<span class="ph-tabs-count ph-tabs-count--alert">{{ $pendingReceived }}</span>@endif
            </label>
            <input type="radio" class="btn-check" name="btnradio" id="btnradio3" autocomplete="off" @checked($requestTab === 'history')>
            <label for="btnradio3" class="ph-tabs-item swap-tab-label" data-request-tab="history" aria-controls="historyPanel">
                History @if($history->count() > 0)<span class="ph-tabs-count">{{ $history->count() }}</span>@endif
            </label>
            <span class="ph-tabs-indicator" aria-hidden="true"></span>
        </div>
    </nav>

    <section id="sentPanel" class="ph-request-panel" aria-label="Sent requests" @if($requestTab !== 'sent') hidden @endif>
        <div class="ph-request-section-heading d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div><h2 class="h5 fw-bold mb-1">Sent requests</h2><p class="small text-body-secondary mb-0">Track requests you've sent to other students.</p></div>
            <span class="small text-body-secondary">{{ $sent->count() }} {{ Str::plural('request', $sent->count()) }}</span>
        </div>
        @if($sent->isNotEmpty())
            <div class="row g-0" id="swapList">
                @foreach($sent as $swap)
                    @include('components.peerhive-request-card', ['swap' => $swap, 'variant' => 'sent'])
                @endforeach
            </div>
        @else
            <div id="swapEmptyState" class="ph-request-empty text-center">
                <i class="bi bi-send fs-2" aria-hidden="true"></i>
                <h3 class="h6 fw-bold mt-3">No sent requests yet</h3>
                <p class="small text-body-secondary">Explore students and ask a verified tutor for help.</p>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Find learning partners</a>
            </div>
        @endif
    </section>

    <section id="receivedPanel" class="ph-request-panel" aria-label="Received requests" @if($requestTab !== 'received') hidden @endif>
        <div class="ph-request-section-heading d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div><h2 class="h5 fw-bold mb-1">Received requests</h2><p class="small text-body-secondary mb-0">Respond to students who want to learn with you.</p></div>
            <span class="small text-body-secondary">{{ $received->count() }} {{ Str::plural('request', $received->count()) }}</span>
        </div>
        @if($received->isNotEmpty())
            <div class="row g-0">
                @foreach($received as $swap)
                    @include('components.peerhive-request-card', ['swap' => $swap, 'variant' => 'received'])
                @endforeach
            </div>
        @else
            <div class="ph-request-empty text-center"><i class="bi bi-inbox fs-2" aria-hidden="true"></i><h3 class="h6 fw-bold mt-3">No received requests yet</h3><p class="small text-body-secondary mb-0">Requests from fellow students will appear here.</p></div>
        @endif
    </section>

    <section id="historyPanel" class="ph-request-panel" aria-label="Request history" @if($requestTab !== 'history') hidden @endif>
        <div class="ph-request-section-heading d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div><h2 class="h5 fw-bold mb-1">Request history</h2><p class="small text-body-secondary mb-0">Review finished learning and skill-swap sessions.</p></div>
            <span class="small text-body-secondary">{{ $history->count() }} {{ Str::plural('session', $history->count()) }}</span>
        </div>
        @if($history->isNotEmpty())
            <div class="row g-0">
                @foreach($history as $swap)
                    @include('components.peerhive-request-card', ['swap' => $swap, 'variant' => 'history'])
                @endforeach
            </div>
        @else
            <div class="ph-request-empty text-center"><i class="bi bi-archive fs-2" aria-hidden="true"></i><h3 class="h6 fw-bold mt-3">No finished sessions yet</h3><p class="small text-body-secondary mb-0">Completed learning requests will appear here.</p></div>
        @endif
    </section>
</div>
@endsection
