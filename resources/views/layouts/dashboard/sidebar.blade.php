@php
  $navItemsUser = [
      [
          'href' => route('dashboard'),
          'icon' => 'bi bi-speedometer',
          'label' => 'Dashboard',
          'active' => request()->routeIs('dashboard'),
      ],
      [
          'href' => route('community'),
          'icon' => 'bi bi-diagram-3',
          'label' => 'Community',
          'active' => request()->routeIs('community', 'community.show'),
      ],
      [
          'href' => route('favorites.index'),
          'icon' => 'bi bi-star',
          'label' => 'My Favorites',
          'active' => request()->routeIs('favorites.index'),
      ],
      [
          'href' => route('swap'),
          'icon' => 'bi bi-arrow-left-right',
          'label' => 'Requests',
          'active' => request()->routeIs('swap'),
      ],
      [
          'href' => route('schedule'),
          'icon' => 'bi bi-alarm',
          'label' => 'My Schedule',
          'active' => request()->routeIs('schedule'),
      ],
      [
          'href' => route('messages'),
          'icon' => 'bi bi-chat-dots',
          'label' => 'Messages',
          'active' => request()->routeIs('messages'),
          'badge' => \App\Models\Conversation::whereHas('users', fn ($q) => $q->where('users.id', auth()->id()))
              ->with('users')
              ->get()
              ->filter(function ($conv) {
                  $pivot = $conv->users->firstWhere('id', auth()->id())->pivot;
                  if ($conv->isMutedFor(auth()->id()) || !$conv->visibleTo(auth()->id())) return false;

                  return $conv->messages()->visibleTo(auth()->id())
                      ->where('sender_id', '!=', auth()->id())
                      ->where('id', '>', max($pivot->last_read_message_id, $pivot->cleared_through_message_id))
                      ->exists();
              })
              ->count() ?: null,
      ],
      [
          'href' => route('history'),
          'icon' => 'bi bi-clock-history',
          'label' => 'History',
          'active' => request()->routeIs('history'),
      ],
    ];

    $navItemsAdmin = [
      [
          'href' => route('users'),
          'icon' => 'bi bi-people',
          'label' => 'Users Data',
          'active' => request()->routeIs('users'),
      ],
      [
          'href' => route('admin.qualifications'), 
          'icon' => 'bi bi-shield-lock',
          'label' => 'Approvals',
          'active' => request()->routeIs('admin.qualifications', 'admin.catalog*'),
      ],
      [
          'href' => route('admin.learning-paths'),
          'icon' => 'bi bi-diagram-3',
          'label' => 'Skill Tree',
          'active' => request()->routeIs('admin.learning-paths*'),
      ],
      [
          'href' => route('admin.reports.index'),
          'icon' => 'bi bi-flag-fill',
          'label' => 'Manage Reports',
          'active' => request()->routeIs('admin.reports.*'),
      ],
      [
          'href' => route('community'),
          'icon' => 'bi bi-diagram-3',
          'label' => 'Community',
          'active' => request()->routeIs('community'),
      ]
    
    ];

    if (auth()->check() && auth()->user()->role === 'admin') {
      $navItems = $navItemsAdmin;
    } else {
      $navItems = $navItemsUser;
    }
@endphp

<aside id="appSidebar" class="app-sidebar bg-dark-subtle shadow" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="{{ route('dashboard') }}" class="brand-link peerhive-sidebar-brand" aria-label="PeerHive dashboard">
      <span class="peerhive-logo-slot"><x-peerhive-logo /></span>
      <span class="peerhive-wordmark-clip"><span class="peerhive-wordmark">PeerHive</span></span>
    </a>
  </div>

  <div class="sidebar-wrapper d-flex flex-column" style="min-height: calc(100vh - 60px);">
     <!-- Sidebar Menu -->
    <nav class="mt-3 flex-grow-1">
      <ul
        class="nav sidebar-menu flex-column"
        data-lte-toggle="treeview"
        role="navigation"
        aria-label="Main navigation"
        data-accordion="false"
      >
        @foreach ($navItems as $item)
          <x-dashboard.nav-item
            :href="$item['href']"
            :icon="$item['icon']"
            :label="$item['label']"
            :active="$item['active']"
            :badge="$item['badge'] ?? null"
          />
        @endforeach
      </ul>
    </nav>

    <nav class="ph-sidebar-bottom mt-auto" aria-label="Help">
      <ul class="nav sidebar-menu flex-column" role="navigation">
        <li class="nav-item">
          <button type="button" class="nav-link ph-sidebar-help" data-bs-toggle="modal" data-bs-target="#peerhiveHelpModal" aria-label="How PeerHive Works" title="How PeerHive Works">
            <i class="nav-icon bi bi-question-circle" aria-hidden="true"></i>
            <p>Help</p>
          </button>
        </li>
      </ul>
    </nav>
  </div>
</aside>
