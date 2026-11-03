@props(['method', 'details'])
@php($meetingUrl = $method === 'online' ? \App\Support\MeetingLink::url($details) : null)
@if($meetingUrl)
  <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="text-break">{{ $meetingUrl }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span class="visually-hidden"> (opens in a new tab)</span></a>
  <p class="small text-muted mt-2 mb-0">Opens an external meeting site. Only join hosts you trust; do not download unexpected files or share your PeerHive password.</p>
@else
  <p class="text-break mb-0" style="white-space:pre-wrap">{{ $details }}</p>
  @if($method === 'online')<p class="small text-muted mt-2 mb-0">This meeting link is not supported. Ask the host for an HTTPS Google Meet, Zoom, or Microsoft Teams link.</p>@endif
@endif
