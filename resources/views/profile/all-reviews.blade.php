<div class="mb-3">
    <x-teaching-rating :user="$user" />
    <p class="small text-muted mb-0">Based on all {{ $user->teaching_ratings_count }} reviews.</p>
</div>
<div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter reviews by stars">
    <button type="button" class="btn btn-sm {{ $stars === null ? 'btn-primary' : 'btn-outline-primary' }}" data-review-stars="" aria-pressed="{{ $stars === null ? 'true' : 'false' }}">All ({{ $user->teaching_ratings_count }})</button>
    @for($star = 5; $star >= 1; $star--)
        <button type="button" class="btn btn-sm {{ (int) $stars === $star ? 'btn-primary' : 'btn-outline-primary' }}" data-review-stars="{{ $star }}" aria-pressed="{{ (int) $stars === $star ? 'true' : 'false' }}">{{ $star }}★ ({{ $counts[$star] ?? 0 }})</button>
    @endfor
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <p class="small text-muted mb-0" role="status">
        @if($learnerReviews->count())
            Showing {{ $learnerReviews->firstItem() }}–{{ $learnerReviews->lastItem() }} of {{ $learnerReviews->total() }} reviews
        @else
            0 matching reviews
        @endif
    </p>
    <div class="d-flex align-items-center gap-2"><label for="allReviewSort" class="small text-nowrap">Sort</label><select id="allReviewSort" class="form-select form-select-sm"><option value="newest" @selected($sort === 'newest')>Newest first</option><option value="highest" @selected($sort === 'highest')>Highest rated</option><option value="lowest" @selected($sort === 'lowest')>Lowest rated</option></select></div>
</div>
@include('profile.review-items', ['reviewActions' => true, 'emptyReviewsMessage' => $stars !== null ? 'No reviews with this rating yet.' : 'No learner reviews yet.'])
@if($learnerReviews->hasPages())<div class="mt-3" data-review-pagination>{{ $learnerReviews->links('pagination::simple-bootstrap-5') }}</div>@endif
