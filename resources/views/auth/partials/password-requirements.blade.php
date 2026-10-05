<div id="password-requirements" class="text-body-secondary mb-3" style="font-size: .75rem; line-height: 1.5;" hidden>
  <ul class="list-unstyled row row-cols-2 g-1 mb-0" data-password-requirements aria-label="Password requirements">
    @foreach (['length' => 'At least 8 characters', 'uppercase' => 'One uppercase letter', 'number' => 'One number', 'special' => 'One special character'] as $rule => $label)
      <li class="col" data-password-rule="{{ $rule }}"><i class="bi bi-circle me-1" aria-hidden="true"></i><span class="visually-hidden" data-rule-status>Not met: </span>{{ $label }}</li>
    @endforeach
  </ul>
</div>
<noscript><p class="small text-body-secondary">Use at least 8 characters, one uppercase letter, one number, and one special character.</p></noscript>
