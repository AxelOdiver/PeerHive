@props(['user'])
@if($user->teaching_ratings_count)
<div class="small" aria-label="{{ number_format($user->teaching_ratings_avg_stars, 1) }} out of 5 from {{ $user->teaching_ratings_count }} learner ratings"><span class="text-warning" aria-hidden="true">@for($star=1;$star<=5;$star++)<i class="bi {{ $user->teaching_ratings_avg_stars >= $star ? 'bi-star-fill' : ($user->teaching_ratings_avg_stars >= $star-0.5 ? 'bi-star-half' : 'bi-star') }}"></i>@endfor</span> {{ number_format($user->teaching_ratings_avg_stars, 1) }} / 5 <span class="text-muted">({{ $user->teaching_ratings_count }} {{ Str::plural('rating', $user->teaching_ratings_count) }})</span></div>
@else
<div class="small text-muted">No learner ratings yet</div>
@endif
