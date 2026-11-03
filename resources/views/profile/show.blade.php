@extends('layouts.dashboard')

@section('title', $profileUser->first_name . ' ' . $profileUser->last_name)

@section('content')
<div class="mb-3">
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-primary">
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
                         class="rounded-circle object-fit-cover {{ $profileUser->profile_frame_class }}"
                         width="100" height="100">
                @else
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold bg-primary text-white {{ $profileUser->profile_frame_class }}"
                         style="width:100px;height:100px;font-size:2rem;">
                        {{ strtoupper(substr($profileUser->first_name, 0, 1)) }}{{ strtoupper(substr($profileUser->last_name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <h4 class="fw-bold mb-0">{{ $profileUser->first_name }} {{ $profileUser->middle_name }} {{ $profileUser->last_name }}</h4>
            @if(auth()->id() === $profileUser->id)<p class="text-muted small mb-3">{{ $profileUser->email }}</p>@endif
            @if($profileUser->username)
                <p class="text-muted small">{{ '@'.$profileUser->username }}</p>
            @endif
            <x-teaching-badges :user="$profileUser" />
            <div class="my-3">
                <x-teaching-rating :user="$profileUser" />
                @if($profileUser->teaching_ratings_count)
                    <a href="#learner-reviews" class="small">View learner reviews</a>
                @endif
            </div>
            @if(count($learningBadges))<div class="my-3"><p class="small text-muted mb-1">Participation achievements</p><div class="d-flex gap-1 flex-wrap justify-content-center">@foreach($learningBadges as $badge)<span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-trophy me-1" aria-hidden="true"></i>{{ $badge }}</span>@endforeach</div></div>@endif

            @if($profileUser->description)
                <p class="text-muted small fst-italic mb-3">{{ $profileUser->description }}</p>
            @endif

            @php
                $isOwnProfile = auth()->id() === $profileUser->id;
            @endphp
            <div class="mb-3">
                @foreach(['course' => 'Course', 'school' => 'School', 'school_location' => 'City / campus'] as $field => $label)
                    @if(($isOwnProfile || $profileUser->{'show_'.$field}) && $profileUser->{$field})
                        <p class="small mb-1"><strong>{{ $label }}:</strong> {{ $profileUser->{$field} }}</p>
                    @endif
                @endforeach
                @if(($isOwnProfile || $profileUser->show_expertise) && count($profileUser->expertise ?? []))
                    <p class="small text-muted mt-2 mb-1">Self-described expertise</p>
                    <div class="d-flex flex-wrap justify-content-center gap-1">
                        @foreach($profileUser->expertise as $label)
                            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

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
                    <i class="bi bi-arrow-left-right me-1"></i> Learn / Swap
                </button>

                <button type="button"
                    class="btn {{ $hasFavorited ? 'btn-warning' : 'btn-outline-warning' }} fav-profile-btn"
                    data-id="{{ $profileUser->id }}">
                    <i class="bi {{ $hasFavorited ? 'bi-bookmark-fill' : 'bi-bookmark' }} me-1"></i>
                    <span class="fav-label">{{ $hasFavorited ? 'Favorited' : 'Add to favorites' }}</span>
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

        <section id="learner-reviews" class="card border-0 shadow-sm rounded-4 p-4 mt-4" aria-labelledby="learner-reviews-title">
            <h5 id="learner-reviews-title" class="fw-bold mb-2">Learner reviews</h5>
            <p class="small text-muted mb-0">Feedback from learners after completed sessions.</p>
            @forelse($learnerReviews as $review)
                <article class="border-top pt-3 mt-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong>{{ trim(($review->learner?->first_name ?? '').' '.($review->learner?->last_name ?? '')) ?: 'PeerHive member' }}</strong>
                        <time class="small text-muted" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->setTimezone(auth()->user()->learning_timezone)->format('M j, Y') }}</time>
                    </div>
                    <div class="small mt-1" aria-label="{{ $review->stars }} out of 5 stars">
                        <span class="text-warning" aria-hidden="true">@for($star = 1; $star <= 5; $star++)<i class="bi {{ $review->stars >= $star ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</span>
                        <span>{{ $review->stars }} / 5</span>
                    </div>
                    @if(filled($review->comment))
                        <p class="text-break mt-2 mb-0" style="white-space:pre-wrap">{{ $review->comment }}</p>
                    @else
                        <p class="small text-muted mt-2 mb-0">Rated without a written review.</p>
                    @endif
                </article>
            @empty
                <p class="text-muted mt-3 mb-0">No learner reviews yet.</p>
            @endforelse
            @if($learnerReviews->hasPages())
                <div class="mt-3">{{ $learnerReviews->links('pagination::simple-bootstrap-5') }}</div>
            @endif
        </section>

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
<x-learning-request-modal />

@endsection
