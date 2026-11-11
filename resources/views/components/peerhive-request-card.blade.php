@php
    $requestVariant = $variant ?? 'sent';
    $isHistory = $requestVariant === 'history';
    $isReceived = $requestVariant === 'received';
    $peer = $isReceived ? $swap->requester : ($isHistory && $swap->requested_user_id === auth()->id() ? $swap->requester : $swap->requestedUser);
    $kindLabel = $swap->request_type === 'learning' ? 'Learning help' : ($swap->request_type === 'skill_swap' ? 'Skill swap' : 'Earlier request');
    $historyState = $isHistory ? data_get($swap->conversation?->session_context, 'state') : null;
    $statusLabel = $isHistory ? ($historyState === 'cancelled' ? 'Session cancelled' : 'Session finished') : ucfirst($swap->status);
    $statusTone = $isHistory ? ($historyState === 'cancelled' ? 'cancelled' : 'finished') : $swap->status;
    $cardClass = $isHistory ? 'history-request-card' : ($isReceived ? 'received-swap-card' : 'swap-card');
@endphp
<div class="col-12 col-md-6 mb-3 {{ $cardClass }}" id="swap-{{ $swap->id }}" data-swap-id="{{ $swap->id }}" data-status="{{ $swap->status }}">
    <article class="card ph-request-card h-100">
        <div class="card-body p-3 p-lg-4 d-flex flex-column">
            <div class="ph-request-card-heading d-flex align-items-start gap-3 mb-3">
                @if($peer->profile_picture)
                    <img src="{{ asset('storage/' . $peer->profile_picture) }}" class="ph-request-avatar rounded-circle flex-shrink-0" alt="" loading="lazy">
                @else
                    <span class="ph-request-avatar ph-request-avatar--fallback rounded-circle flex-shrink-0" aria-hidden="true">{{ strtoupper(substr($peer->first_name, 0, 1)) }}{{ strtoupper(substr($peer->last_name, 0, 1)) }}</span>
                @endif
                <div class="min-w-0 flex-grow-1">
                    <h3 class="h6 fw-bold mb-1 text-break">{{ $peer->first_name }} {{ $peer->last_name }}</h3>
                    <span class="small text-body-secondary text-break">{{ $peer->username ? '@' . $peer->username : 'Learning partner' }}</span>
                </div>
                @if(!$isHistory && $swap->status === 'accepted')
                    <button type="button" class="btn ph-request-icon-action remove-swap-btn" data-id="{{ $swap->id }}" title="Remove connection" aria-label="Remove connection with {{ $peer->first_name }}"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                @endif
            </div>
            <div class="ph-request-card-main flex-grow-1">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                    <span class="ph-request-type"><i class="bi {{ $swap->request_type === 'skill_swap' ? 'bi-arrow-left-right' : 'bi-book' }}" aria-hidden="true"></i>{{ $kindLabel }}</span>
                    <span class="ph-request-status ph-request-status--{{ $statusTone }}">{{ $statusLabel }}</span>
                </div>
                <h4 class="h5 fw-bold mb-2 text-break">{{ $swap->requested_subject ?: 'Subject not recorded' }}</h4>
                @if($swap->offered_subject)
                    <p class="small text-body-secondary mb-2"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>In exchange for {{ $swap->offered_subject }}</p>
                @endif
                @if(!$isHistory)
                    <div class="ph-request-context"><x-learning-request-details :swap="$swap" /></div>
                @else
                    <p class="small text-body-secondary mb-2">{{ $swap->request_type === 'skill_swap' ? 'Skill exchange with your learning partner.' : 'Learning session with your partner.' }}</p>
                @endif
                @if($swap->message && !$isHistory)
                    <p class="ph-request-note small mb-3">{{ $swap->message }}</p>
                @endif
                @if($isHistory)
                    <div class="ph-request-history-note small"><i class="bi {{ $historyState === 'cancelled' ? 'bi-info-circle' : 'bi-check-circle' }} me-2" aria-hidden="true"></i>{{ $historyState === 'cancelled' ? 'This session was cancelled.' : 'This session has been completed.' }}</div>
                @endif
            </div>
            <div class="ph-request-actions mt-3">
                @if($isHistory)
                    @if($swap->conversation)
                        <a href="{{ route('messages', ['conversation' => $swap->conversation->id]) }}" class="btn btn-outline-primary w-100 ph-request-review-link"><i class="bi bi-chat-left-text me-2" aria-hidden="true"></i>Review conversation</a>
                    @else
                        <p class="small text-body-secondary mb-0">Conversation is unavailable.</p>
                    @endif
                @elseif($swap->status === 'pending' && !$isReceived)
                    <form method="POST" action="{{ route('swap.destroy', $swap) }}" class="cancel-swap-form m-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-secondary w-100 cancel-swap-btn">Cancel request</button>
                    </form>
                @elseif($swap->status === 'pending' && $isReceived)
                    <div class="d-flex gap-2 ph-request-response-actions">
                        <button type="button" class="btn btn-primary flex-fill respond-swap-btn" data-swap-id="{{ $swap->id }}" data-action="accepted"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Accept</button>
                        <button type="button" class="btn btn-outline-danger flex-fill respond-swap-btn" data-swap-id="{{ $swap->id }}" data-action="declined"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Decline</button>
                    </div>
                @elseif($swap->status === 'accepted' && $swap->conversation)
                    <a href="{{ route('messages', ['conversation' => $swap->conversation->id]) }}" class="btn btn-swap w-100"><i class="bi bi-chat-dots me-2" aria-hidden="true"></i>Open conversation</a>
                @elseif($swap->status === 'accepted')
                    <span class="text-body-secondary small">The conversation is being prepared.</span>
                @elseif($swap->status === 'declined')
                    <span class="text-body-secondary small">No further action is required.</span>
                @endif
            </div>
        </div>
    </article>
</div>
