@vite(['resources/css/app.css', 'resources/js/app.js'])

<nav class="app-header navbar navbar-expand bg-body">
  <div class="container-fluid">
    <ul class="navbar-nav w-75 me-2">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
          <i class="bi bi-list"></i>
        </a>
      </li>
      
      <li class="nav-item d-flex align-items-center" id="searchWrapper">
        <div class="position-relative w-100">
          <i class="bi bi-search search-icon-prefix"></i>
          <input
          type="text"
          id="globalSearch"
          class="form-control form-control-sm"
          placeholder="Search students, communities…"
          autocomplete="off"
          aria-expanded="false"
          aria-controls="searchDropdown"
          >
          <div id="searchDropdown"></div>
        </div>
      </li>
    </ul>
    
    <!-- Right-Aligned Nav Items (Notifications & Theme) -->
    <ul class="navbar-nav ms-auto align-items-center gap-2">
      
      <!-- Notifications Dropdown -->
      <li class="nav-item dropdown">
        <a class="nav-link d-flex align-items-center" data-bs-toggle="dropdown" href="#" aria-expanded="false">
          <span class="position-relative d-inline-block">
            <i class="bi bi-bell-fill fs-5"></i>
            
            @php $inviteCount = auth()->user()->pendingInvites->count(); @endphp
            
            <!-- UNIFIED NOTIFICATION BADGE -->
            <span id="globalNotificationBadge" 
                  class="position-absolute badge rounded-pill bg-danger {{ $inviteCount > 0 ? '' : 'd-none' }}" 
                  style="top: 0px; right: -6px; font-size: 0.6rem; padding: 0.2em 0.4em;" 
                  data-invite-count="{{ $inviteCount }}">
              {{ $inviteCount > 0 ? $inviteCount : '0' }}
            </span>
          </span>
        </a>
      
      <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="width: 320px;">
        <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
        <div id="messageNotificationsList"></div>
        
        @forelse(auth()->user()->pendingInvites as $invite)
        <li>
          <div class="dropdown-item-text border-bottom py-2">
            <p class="mb-2 text-wrap small">
              <strong>{{ $invite->community->user->first_name }}</strong> invited you to join <strong>{{ $invite->community->name }}</strong>
            </p>
            <div class="d-flex gap-2">
              <!-- Accept Button -->
              <form action="{{ route('community.invite.accept', $invite) }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-success btn-sm">Accept</button>
              </form>
              
              <!-- Decline Button -->
              <form action="{{ route('community.invite.decline', $invite) }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm">Decline</button>
              </form>
            </div>
          </div>
        </li>
        @empty
        <li id="noNotificationsMsg"><span class="dropdown-item text-muted small">No new notifications.</span></li>
        @endforelse
      </ul>
    </li>
    
    <!-- Theme Mode -->
    <li class="nav-item dropdown">
      <button
      class="btn btn-link nav-link py-2 px-0 px-lg-2 dropdown-toggle d-flex align-items-center"
      id="bd-theme"
      type="button"
      aria-expanded="false"
      data-bs-toggle="dropdown"
      data-bs-display="static"
      >
      <span class="theme-icon-active">
        <i class="my-1"></i>
      </span>
    </button>
    <ul
    class="dropdown-menu dropdown-menu-end"
    aria-labelledby="bd-theme-text"
    style="--bs-dropdown-min-width: 8rem;"
    >
    <li>
      <button
      type="button"
      class="dropdown-item d-flex align-items-center"
      data-bs-theme-value="light"
      aria-pressed="false"
      >
      <i class="bi bi-sun-fill me-2"></i>
      Light
      <i class="bi bi-check-lg ms-auto d-none"></i>
    </button>
  </li>
  <li>
    <button
    type="button"
    class="dropdown-item d-flex align-items-center"
    data-bs-theme-value="dark"
    aria-pressed="false"
    >
    <i class="bi bi-moon-fill me-2"></i>
    Dark
    <i class="bi bi-check-lg ms-auto d-none"></i>
  </button>
</li>
<li>
  <button
  type="button"
  class="dropdown-item d-flex align-items-center"
  data-bs-theme-value="auto"
  aria-pressed="true"
  >
  <i class="bi bi-circle-fill-half-stroke me-2"></i>
  Auto
  <i class="bi bi-check-lg ms-auto d-none"></i>
</button>
</li>
</ul>
</li>
</ul>
</div>
</nav>