@if($pendingInvite)
  <div class="mb-3"><p>You have an invitation to this community.</p><form action="{{ route('community.invite.accept', $pendingInvite) }}" method="POST">@csrf<button class="btn btn-primary" type="submit">Accept invitation</button></form></div>
@elseif($joinRequest?->status === 'pending')
  <div><p class="text-muted">Your join request is awaiting approval.</p><form action="{{ route('community.join.cancel', $community) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-outline-secondary" type="submit">Cancel join request</button></form></div>
@elseif($memberCount >= $community->member_limit)
  <p class="text-muted mb-0">This community is full.</p>
@else
  @if($joinRequest?->status === 'declined')<p class="text-muted">Your previous join request was declined.</p>@endif
  <form action="{{ route('community.join', $community) }}" method="POST">@csrf<button class="btn btn-primary" type="submit">{{ $community->visibility === 'private' ? 'Request to join' : 'Join Community' }}</button></form>
@endif
