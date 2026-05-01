@php
  $navItems = [
      [
          'href' => route('dashboard'),
          'icon' => 'bi bi-speedometer',
          'label' => 'Dashboard',
          'active' => request()->routeIs('dashboard'),
      ],
      [
          'href' => route('favorites.index'),
          'icon' => 'bi bi-star',
          'label' => 'Favorites',
          'active' => request()->routeIs('favorites.index'),
      ],
      [
          'href' => route('swap'),
          'icon' => 'bi bi-arrow-left-right',
          'label' => 'Swap',
          'active' => request()->routeIs('swap'),
      ],
      [
          'href' => route('schedule'),
          'icon' => 'bi bi-alarm',
          'label' => 'Schedule',
          'active' => request()->routeIs('schedule'),
      ],
      [
          'href' => route('messages'),
          'icon' => 'bi bi-chat-dots',
          'label' => 'Messages',
          'active' => request()->routeIs('messages'),
      ],
      [
          'href' => route('history'),
          'icon' => 'bi bi-clock-history',
          'label' => 'History',
          'active' => request()->routeIs('history'),
      ],
      [
          'href' => route('community'),
          'icon' => 'bi bi-diagram-3',
          'label' => 'Community',
          'active' => request()->routeIs('community'),
      ],
    ];
      // only admin users will see the following links
      if (auth()->check() && auth()->user()->role === 'admin') {
      
      $navItems[] = [
          'href' => route('admin.qualifications'), 
          'icon' => 'bi bi-shield-lock',
          'label' => 'Approvals',
          'active' => request()->routeIs('admin.qualifications'),
      ];

      $navItems[] = [
          'href' => route('users'),
          'icon' => 'bi bi-people',
          'label' => 'Users Data',
          'active' => request()->routeIs('users'),
      ];
    }
@endphp

<aside id="appSidebar" class="app-sidebar bg-dark-subtle shadow" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="{{ route('dashboard') }}" class="brand-link d-flex justify-content-center align-items-center w-100 p-0 gap-2">
      <span class="brand-text fw-semibold fs-2 display-6">PeerHive</span>
    </a>
  </div>

  <div class="sidebar-wrapper d-flex flex-column" style="min-height: calc(100vh - 60px);">
    <div class="sidebar-user border-bottom border-secondary-subtle">
      <div class="d-flex justify-content-start align-items-center gap-2 p-3">
        <img
          src="{{ auth()->user()->profile_picture_url ?? '/images/profile-placeholder.jpeg' }}"
          alt="{{ trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) ?: 'User' }}"
          id="sidebarUserAvatar"
          class="rounded-circle dashboard-avatar me-2 flex-shrink-0 object-fit-cover"
          width="40"
          height="40"
        >

        <div class="sidebar-user-meta ">
          <div class="fw-semibold text-white" id="sidebarUserName">{{ auth()->user()->first_name. ' ' .auth()->user()->middle_name. ' ' .auth()->user()->last_name ?? 'User' }}</div>
          <small class="text-white-50 d-block" id="sidebarUserEmail">{{ auth()->user()->email ?? '' }}</small>
          <a href="{{ route('profile') }}" class="small link-light">Edit profile</a>
        </div>
      </div>
    </div>
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
          />
        @endforeach
      </ul>
    </nav>

    <div class="border-top border-secondary-subtle p-1">
      <form method="POST" action="{{ route('logout') }}" class="w-100">
        @csrf
        <button type="submit" class="logout-btn text-start">
          <i class="bi bi-box-arrow-right me-2"></i>
          <span>Log out</span>
        </button>
      </form>
  </div>
</aside>
