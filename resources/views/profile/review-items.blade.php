@forelse($learnerReviews as $review)
    <article class="border-top pt-3 mt-3">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <strong>{{ trim(($review->learner?->first_name ?? '').' '.($review->learner?->last_name ?? '')) ?: 'PeerHive member' }}</strong>
            <time class="small text-muted" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->setTimezone(auth()->user()->learning_timezone)->format('M j, Y') }}</time>
        </div>
        @if($review->updated_at?->gt($review->created_at))<span class="small text-muted">Edited</span>@endif
        <div class="small mt-1" aria-label="{{ $review->stars }} out of 5 stars">
            <span class="text-warning" aria-hidden="true">@for($star = 1; $star <= 5; $star++)<i class="bi {{ $review->stars >= $star ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</span>
            <span>{{ $review->stars }} / 5</span>
        </div>
        @if(filled($review->comment))
            <p class="text-break mt-2 mb-0" style="white-space:pre-wrap">{{ $review->comment }}</p>
        @else
            <p class="small text-muted mt-2 mb-0">Rated without a written review.</p>
        @endif
        @if($reviewActions ?? false)
            @if($review->canEditBy(auth()->user()))
                <details class="mt-2">
                    <summary class="small text-primary">Edit your review</summary>
                    <form action="{{ route('reviews.update', $review) }}" method="POST" data-review-edit class="mt-2 border rounded-3 p-3">
                        @csrf @method('PATCH')
                        <input type="hidden" name="version" value="{{ $review->reviewVersion() }}">
                        <p class="small text-muted">You can edit your review within 24 hours of posting.</p>
                        <label class="form-label small" for="editReviewStars{{ $review->id }}">Stars</label>
                        <select id="editReviewStars{{ $review->id }}" name="stars" class="form-select form-select-sm mb-2">@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}" @selected($review->stars === $rating)>{{ $rating }} / 5</option>@endfor</select>
                        <label class="form-label small" for="editReviewComment{{ $review->id }}">Comment (optional)</label>
                        <textarea id="editReviewComment{{ $review->id }}" name="comment" maxlength="500" rows="3" class="form-control mb-2">{{ $review->comment }}</textarea>
                        <p class="small text-danger d-none" role="alert" data-review-error></p>
                        <button class="btn btn-primary btn-sm" type="submit">Save review</button>
                    </form>
                </details>
            @elseif($review->learner_id === auth()->id())
                <p class="small text-muted mt-2 mb-0">The 24-hour editing window has ended.</p>
            @endif
            @if($review->teacher_id === auth()->id())
                <details class="mt-2">
                    <summary class="small text-primary">Report review</summary>
                    <form action="{{ route('reports.store') }}" method="POST" data-review-report class="mt-2 border rounded-3 p-3">
                        @csrf
                        <input type="hidden" name="reportable_id" value="{{ $review->id }}"><input type="hidden" name="reportable_type" value="App\Models\SessionRating">
                        <p class="small text-muted">Disagreeing with a rating alone is not grounds for removal. Reports go to an admin; the review stays visible until a decision is made.</p>
                        <label for="reviewReason{{ $review->id }}" class="form-label small">Reason</label>
                        <select id="reviewReason{{ $review->id }}" name="reason" class="form-select form-select-sm mb-2" required><option value="">Choose a reason</option><option>Harassment or threats</option><option>Spam or unrelated content</option><option>Suspected retaliation</option></select>
                        <label for="reviewDetails{{ $review->id }}" class="form-label small">Details (optional)</label>
                        <textarea id="reviewDetails{{ $review->id }}" name="details" maxlength="1000" rows="3" class="form-control mb-2"></textarea>
                        <p class="small text-danger d-none" role="alert" data-review-error></p>
                        <button class="btn btn-outline-warning btn-sm" type="submit">Submit report</button>
                    </form>
                </details>
            @endif
        @endif
    </article>
@empty
    <p class="text-muted mt-3 mb-0">{{ $emptyReviewsMessage ?? 'No learner reviews yet.' }}</p>
@endforelse
