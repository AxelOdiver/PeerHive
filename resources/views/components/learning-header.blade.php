@props(['title', 'subtitle'])
<a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Dashboard</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div><h1 class="h3 fw-bold mb-1">{{ $title }}</h1><p class="text-muted mb-0">{{ $subtitle }}</p></div>
  <div class="d-flex gap-2 flex-wrap">{{ $slot }}</div>
</div>
@if($errors->any())
  <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
