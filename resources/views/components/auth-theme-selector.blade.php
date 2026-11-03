<div class="auth-appearance">
  <div class="btn-group auth-theme-options" role="group" aria-label="Color theme">
    @foreach(['light' => ['sun-fill', 'Light'], 'dark' => ['moon-fill', 'Dark'], 'auto' => ['circle-half', 'Auto']] as $mode => [$icon, $label])
      <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2" data-bs-theme-value="{{ $mode }}" aria-pressed="false" @if($mode === 'auto') title="Follow your device's appearance" @endif>
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $label }}
      </button>
    @endforeach
  </div>
</div>
