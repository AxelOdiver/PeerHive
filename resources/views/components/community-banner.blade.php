@props(['community', 'size' => 'standard', 'manage' => false, 'viewable' => false])
@php
  $banner = $community->banner;
  $canManage = $manage && (auth()->id() === $community->user_id || auth()->user()->role === 'admin');
  $positionX = $banner?->position_x ?? 50;
  $positionY = $banner?->position_y ?? 50;
  $zoom = $banner?->zoom ?? 1;
@endphp
<div class="community-banner community-banner--{{ $size }} position-relative overflow-hidden" style="--banner-x: {{ $positionX }}%; --banner-y: {{ $positionY }}%; --banner-zoom: {{ $zoom }}">
  @if($banner && $viewable)
    <button type="button" class="community-banner-trigger position-relative z-2 d-block w-100 p-0 border-0 bg-transparent" data-community-banner-url="{{ route('community.banner.show', $banner) }}" data-community-banner-id="{{ $banner->id }}" data-community-banner-name="{{ $community->name }}" data-community-banner-reportable="{{ auth()->id() !== $banner->uploaded_by ? '1' : '0' }}" aria-label="View banner for {{ $community->name }}">
      <img src="{{ route('community.banner.show', $banner) }}" alt="Banner for {{ $community->name }}" class="community-banner-image community-banner-image--{{ $size }}" loading="lazy">
    </button>
  @elseif($banner)
    <img src="{{ route('community.banner.show', $banner) }}" alt="Banner for {{ $community->name }}" class="community-banner-image community-banner-image--{{ $size }}" loading="lazy">
  @else
    <img src="https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg" alt="" class="community-banner-image community-banner-image--{{ $size }}" loading="lazy">
  @endif
  @if($canManage)
    <button type="button" class="btn btn-light btn-sm position-absolute bottom-0 end-0 m-3 z-3" data-community-banner-upload="{{ route('community.banner.store', $community) }}" data-current-banner-id="{{ $banner?->id }}" data-current-banner-url="{{ $banner ? route('community.banner.show', $banner) : '' }}" data-current-banner-x="{{ $positionX }}" data-current-banner-y="{{ $positionY }}" data-current-banner-zoom="{{ $zoom }}"><i class="bi bi-image me-1" aria-hidden="true"></i>{{ $banner ? 'Change banner' : 'Upload banner' }}</button>
  @endif
</div>
@if($viewable || $canManage)
@once
  @push('modals')
    <x-community-banner-dialogs />
  @endpush
@endonce
@endif
