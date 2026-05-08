@extends('layouts.dashboard')

@section('title', $profileUser->first_name . ' ' . $profileUser->last_name)

@section('content')
<div class="mb-3">
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row g-4">
    {{-- Left: Profile Card --}}
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
            <div class="mb-3">
                @if($profileUser->profile_picture_url)
                    <img src="{{ $profileUser->profile_picture_url }}"
                         alt="{{ $profileUser->first_name }}"
                         class="rounded-circle object-fit-cover"
                         width="100" height="100">
                @else
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold bg-primary text-white"
                         style="width:100px;height:100px;font-size:2rem;">
                        {{ strtoupper(substr($profileUser->first_name, 0, 1)) }}{{ strtoupper(substr($profileUser->last_name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <h4 class="fw-bold mb-0">{{ $profileUser->first_name }} {{ $profileUser->middle_name }} {{ $profileUser->last_name }}</h4>
            <p class="text-muted small mb-3">{{ $profileUser->email }}</p>

            @if($profileUser->description)
                <p class="text-muted small fst-italic mb-3">{{ $profileUser->description }}</p>
            @endif

            {{-- Stats Row --}}
            <div class="d-flex justify-content-center gap-4 mb-4">
                <div class="text-center">
                    <div class="fw-bold fs-5" id="profile-like-count">{{ $likeCount }}</div>
                    <div class="text-muted" style="font-size:0.75rem;">Likes</div>
                </div>
                <div class="text-center">
                    <div class="fw-bold fs-5">{{ $swapCount }}</div>
                    <div class="text-muted" style="font-size:0.75rem;">Swaps</div>
                </div>
                <div class="text-center">
                    <div class="fw-bold fs-5">{{ $commentCount }}</div>
                    <div class="text-muted" style="font-size:0.75rem;">Comments</div>
                </div>
            </div>

            {{-- Action Buttons --}}
            @if(auth()->id() !== $profileUser->id)
            <div class="d-flex flex-column gap-2">
                <button type="button"
                    class="btn {{ $hasLiked ? 'btn-danger' : 'btn-outline-danger' }} like-profile-btn"
                    data-id="{{ $profileUser->id }}">
                    <i class="bi {{ $hasLiked ? 'bi-heart-fill' : 'bi-heart' }} me-1"></i>
                    <span class="like-label">{{ $hasLiked ? 'Unlike' : 'Like' }}</span>
                </button>

                <button type="button"
                    class="btn btn-swap rounded-3 text-uppercase fw-bold open-swap-modal"
                    data-id="{{ $profileUser->id }}">
                    <i class="bi bi-arrow-left-right me-1"></i> Swap
                </button>

                <button type="button"
                    class="btn {{ $hasFavorited ? 'btn-warning' : 'btn-outline-warning' }} fav-profile-btn"
                    data-id="{{ $profileUser->id }}">
                    <i class="bi {{ $hasFavorited ? 'bi-bookmark-fill' : 'bi-bookmark' }} me-1"></i>
                    <span class="fav-label">{{ $hasFavorited ? 'Saved' : 'Save' }}</span>
                </button>
            </div>
            @endif
        </div>
    </div>

    {{-- Right: Schedule --}}
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h5 class="fw-bold mb-3">Weekly Availability</h5>
            @php
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                $scheduleByDay = $profileUser->schedules->keyBy('day_index');
            @endphp
            <div class="row g-2">
                @foreach($days as $idx => $day)
                @php $slot = $scheduleByDay->get($idx); @endphp
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="border rounded-3 p-2 text-center {{ $slot ? 'border-primary bg-primary bg-opacity-10' : 'text-muted' }}">
                        <div class="fw-semibold small">{{ $day }}</div>
                        @if($slot)
                            <div class="text-primary" style="font-size:0.72rem;">
                                {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }} –
                                {{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                            </div>
                        @else
                            <div style="font-size:0.72rem;">Not set</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Posts --}}
        @if($profileUser->posts->count() > 0)
        <div class="card border-0 shadow-sm rounded-4 p-4 mt-4">
            <h5 class="fw-bold mb-3">Recent Posts</h5>
            @foreach($profileUser->posts->take(3) as $post)
            <div class="border-bottom pb-3 mb-3">
                <div class="fw-semibold">{{ $post->title }}</div>
                <div class="text-muted small">{{ str()->limit($post->body, 120) }}</div>
                <div class="text-muted" style="font-size:0.75rem;">{{ $post->created_at->diffForHumans() }}</div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Swap Modal --}}
<x-modal id="swapModal" title="Swap Request">
    <form id="swapRequestForm">
        <input type="hidden" id="swapUserId" name="user_id" value="">
        <div class="form-floating">
            <textarea class="form-control" placeholder="Write a message" id="swapRequestMessage" name="message" style="height:100px"></textarea>
            <label for="swapRequestMessage">Write a message</label>
        </div>
    </form>
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary" id="sendSwapRequestBtn" form="swapRequestForm">Send Request</button>
    </x-slot:footer>
</x-modal>

@endsection
