@props(['title', 'subtitle', 'backUrl' => null, 'backLabel' => 'Back to dashboard'])
<x-page-header :title="$title" :subtitle="$subtitle" :back-url="$backUrl ?? route('dashboard')" :back-label="$backLabel">{{ $slot }}</x-page-header>
@if($errors->any())
  <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
