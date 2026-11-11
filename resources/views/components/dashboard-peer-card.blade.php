@php
$isLiked = in_array($student->id, $likedIds);
$isFaved = in_array($student->id, $favoritedIds);
@endphp
    <div @if(!empty($cardId)) id="{{ $cardId }}" @endif class="{{ $columnClass ?? 'col-12 col-md-6 col-xl-4' }} mb-4 student-card-column {{ $cardExtraClass ?? '' }}">
      <div class="card border-0 shadow-sm rounded-4 p-3 h-100 w-100 student-card">
        <div class="d-flex align-items-start gap-2 gap-sm-3">
          <a href="{{ route('users.profile', $student->id) }}" class="text-decoration-none flex-shrink-0">
          @if($student->profile_picture_url)
            <img src="{{ $student->profile_picture_url }}"
              alt="{{ $student->first_name }}"
              class="rounded-circle shadow-sm"
              style="width: 60px; height: 60px; object-fit: cover;">
          @else
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white shadow-sm"
                style="width: 60px; height: 60px; font-size: 1.5rem;">
              {{ strtoupper(substr($student->first_name, 0, 1)) }}{{ strtoupper(substr($student->last_name, 0, 1)) }}
            </div>
          @endif
        </a>

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <a href="{{ route('users.profile', $student->id) }}" class="text-decoration-none text-body-emphasis">
              <h5 class="fw-bold mb-0 text-truncate">{{ $student->first_name }} {{ $student->last_name }}</h5>
            </a>
            <button type="button" class="text-muted btn btn-sm p-0 shadow-none line-height-1 ms-2 flex-shrink-0 fav-btn" data-id="{{ $student->id }}">
              @if($isFaved)
              <i class="bi bi-bookmark-fill text-warning"></i>
              @else
              <i class="bi bi-bookmark"></i>
              @endif
            </button>
          </div>

          <x-teaching-badges :user="$student" />
          <p class="text-muted small mb-1 text-truncate">Peer Student
            <span class="text-warning text-nowrap ms-1" title="{{ $student->teaching_ratings_count ? number_format($student->teaching_ratings_avg_stars,1).' / 5 · '.$student->teaching_ratings_count.' learner ratings' : 'No learner ratings yet' }}">
              @for($i = 1; $i <= 5; $i++)<i class="bi {{ ($student->teaching_ratings_avg_stars ?? 0) >= $i ? 'bi-star-fill' : (($student->teaching_ratings_avg_stars ?? 0) >= $i-0.5 ? 'bi-star-half' : 'bi-star') }}" aria-hidden="true"></i>@endfor
              <span class="visually-hidden">{{ $student->teaching_ratings_count ? number_format($student->teaching_ratings_avg_stars,1).' out of 5' : 'No learner ratings yet' }}</span>
            </span>
          </p>

          <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mb-3">
            <!-- Like button -->
            <div class="d-flex align-items-center">
              <button type="button"
              class="btn btn-sm p-0 shadow-none fs-5 like-btn {{ $isLiked ? 'text-danger' : '' }}"
              data-id="{{ $student->id }}"
              title="{{ $isLiked ? 'Unlike' : 'Like' }}">
              <i class="bi {{ $isLiked ? 'bi-heart-fill' : 'bi-heart' }}"></i>
            </button>
            <small class="text-muted fw-semibold ms-1 like-count" data-id="{{ $student->id }}">{{ $student->liked_by_count }}</small>
          </div>

          <!-- Swap count (display only) -->
          <div class="d-flex align-items-center">
            <button type="button" class="btn btn-sm p-0 shadow-none fs-5 open-swap-modal" data-id="{{ $student->id }}" title="Send learn/swap request">
              <i class="bi bi-arrow-left-right"></i>
            </button>
            <small class="text-muted fw-semibold ms-1">{{ $student->swaps_count }}</small>
          </div>

          <!-- View profile / comments -->
          <div class="d-flex align-items-center">
            <a href="{{ route('users.profile', $student->id) }}" class="btn btn-sm p-0 shadow-none fs-5 text-body" title="View profile">
              <i class="bi bi-chat-dots"></i>
            </a>
            <small class="text-muted fw-semibold ms-1">{{ $student->comments_count }}</small>
          </div>
        </div>

        @if($showMatch ?? false)
          <p class="small mb-1">Matches your interests: <strong>{{ $student->matched_subjects->implode(', ') }}</strong></p>
          @if($student->shared_availability)<p class="small text-muted mb-3">Your weekly availability overlaps.</p>@endif
        @endif
        <button type="button" class="btn btn-swap w-100 rounded-3 text-uppercase fw-bold py-2 open-swap-modal" data-id="{{ $student->id }}">
          Learn / Swap
        </button>
      </div>
    </div>
  </div>
</div>
