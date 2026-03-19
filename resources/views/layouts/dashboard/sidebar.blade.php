<aside id="appSidebar" class="app-sidebar bg-dark-subtle shadow" data-bs-theme="dark">
  <div class="sidebar-brand">
    <a href="{{ route('dashboard') }}" class="brand-link d-flex justify-content-center align-items-center w-100 p-0 gap-2">
      <span class="brand-text fw-semibold fs-2 display-6">PeerHive</span>
    </a>
  </div>

  <div class="sidebar-wrapper d-flex flex-column" style="min-height: calc(100vh - 60px);">
    <div class="sidebar-user border-bottom border-secondary-subtle">
      <div class="d-flex justify-content-center align-items-center gap-2 p-3">
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center dashboard-avatar">
          <i class="bi bi-person-fill text-white"></i>
        </div>
        <div class="sidebar-user-meta">
          <div class="fw-semibold text-white">{{ auth()->user()->name ?? 'User' }}</div>
          <small class="text-white-50 d-block">{{ auth()->user()->email ?? '' }}</small>
          <a href="{{ route('profile.edit') }}" class="small link-light">Edit profile</a>
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
        <li class="nav-item">
          <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="nav-icon bi bi-speedometer"></i>
            <p>Dashboard</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="{{ route('favorites') }}" class="nav-link {{ request()->routeIs('favorites') ? 'active' : '' }}">
            <i class="nav-icon bi bi-star"></i>
            <p>Favorites</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="{{ route('swap') }}" class="nav-link {{ request()->routeIs('swap') ? 'active' : '' }}">
            <i class="nav-icon bi bi-arrow-left-right"></i>
            <p>Swap</p>
          </a>
        </li>
         <li class="nav-item">
          <a href="{{ route('schedule') }}" class="nav-link {{ request()->routeIs('schedule') ? 'active' : '' }}">
            <i class="nav-icon bi bi-alarm"></i>
            <p>Schedule</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="{{ route('messages') }}" class="nav-link {{ request()->routeIs('messages') ? 'active' : '' }}">
            <i class="nav-icon bi bi-chat-dots"></i>
            <p>Messages</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="{{ route('history') }}" class="nav-link {{ request()->routeIs('history') ? 'active' : '' }}">
            <i class="nav-icon bi bi-clock-history"></i>
            <p>History</p>
          </a>
        </li>
      </ul>
    </nav>

    <div class="mt-auto border-top border-secondary-subtle p-2">
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="nav-link border-0 bg-transparent text-white w-100 d-flex align-items-center px-3 py-2">
          <i class="bi bi-box-arrow-right me-2"></i>
          <span>Log out</span>
        </button>
      </form>
    </div>
  </div>
</aside>
