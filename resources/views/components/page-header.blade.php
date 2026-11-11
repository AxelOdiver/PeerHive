@props(['title', 'subtitle' => null, 'backUrl' => null, 'backLabel' => 'Back'])
@if($backUrl)
  <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm mb-3 ph-page-back">
    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ $backLabel }}
  </a>
@endif
<header class="ph-page-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
  <div class="ph-page-header-copy">
    <h1 class="h3 fw-bold mb-1">{{ $title }}</h1>
    @if($subtitle)<p class="text-body-secondary mb-0">{{ $subtitle }}</p>@endif
  </div>
  <div class="ph-page-header-actions d-flex flex-wrap gap-2 align-items-center">{{ $slot }}</div>
</header>
