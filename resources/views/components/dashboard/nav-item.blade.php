@props([
    'href',
    'icon',
    'label',
    'active' => false,
    'badge' => null,
])

<li class="nav-item">
    <a href="{{ $href }}" @class(['nav-link', 'active' => $active])>
        <i class="nav-icon {{ $icon }}"></i>
        <p class="d-flex align-items-center gap-2">
            {{ $label }}
            @if($badge)
            <span class="badge bg-danger rounded-pill" id="sidebarMessagesBadge">{{ $badge }}</span>
            @endif
        </p>
    </a>
</li>