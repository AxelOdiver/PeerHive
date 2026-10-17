@props(['swap'])
<div class="small mt-2">
  @if($swap->request_type === 'learning')
    <span class="badge text-bg-info">Learning help</span>
    <p class="mb-1 mt-1">{{ $swap->requester->first_name }} wants to learn <strong>{{ $swap->requested_subject }}</strong> from {{ $swap->requestedUser->first_name }}.</p>
  @elseif($swap->request_type === 'skill_swap')
    <span class="badge text-bg-primary">Skill swap</span>
    <p class="mb-1 mt-1">{{ $swap->requester->first_name }} learns <strong>{{ $swap->requested_subject }}</strong> and offers to teach <strong>{{ $swap->offered_subject }}</strong> to {{ $swap->requestedUser->first_name }}.</p>
  @else
    <p class="text-muted mb-1">Legacy swap request — teaching subjects were not recorded.
      @if($swap->status === 'pending') The sender must cancel and resend this request using the new form before it can be accepted. @endif
    </p>
  @endif
</div>
