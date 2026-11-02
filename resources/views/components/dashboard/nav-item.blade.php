@props([
    'href',
    'icon',
    'label',
    'active' => false,
    'badge' => null,
])

<li class="nav-item">
    <a href="{{ $href }}" @class(['nav-link', 'active' => $active]) aria-label="{{ $label }}" data-sidebar-tooltip="{{ $label }}" @if($active) aria-current="page" @endif>
        <i class="nav-icon {{ $icon }}" aria-hidden="true"></i>
        <p class="d-flex align-items-center gap-2">
            {{ $label }}
        </p>
            @if($label === 'Messages')
            <span class="badge bg-danger rounded-pill sidebar-unread-badge {{ $badge ? '' : 'd-none' }}" id="sidebarMessagesBadge">{{ $badge ?? 0 }}</span>
            @endif
            @if($label === 'Requests')
            <span class="badge bg-danger rounded-pill sidebar-unread-badge d-none" id="sidebarSwapBadge">0</span>
            @endif
    </a>
</li>
