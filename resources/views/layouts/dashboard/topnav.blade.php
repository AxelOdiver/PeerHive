<nav class="app-header navbar navbar-expand bg-body peerhive-topnav">
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
      
      <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="width: 320px; max-height: 70vh; overflow-y: auto;">
        <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
        <li class="px-3 pb-2 d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary btn-sm" data-notification-action="read-all">Mark all as read</button><button type="button" class="btn btn-outline-secondary btn-sm" data-notification-action="clear-read">Clear read notifications</button></li>
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
    
    <!-- Profile and appearance menu -->
    <li class="nav-item dropdown ph-account-nav">
      <button type="button" class="ph-profile-trigger dropdown-toggle" id="peerhiveAccountMenu" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Open account menu">
        <img id="sidebarUserAvatar" class="ph-topnav-avatar" src="{{ $sidebarAvatarUrl ?? auth()->user()->profile_picture_url ?? route('profile-picture.show', ['filename' => 'default']) }}" alt="" width="38" height="38" loading="eager" decoding="sync">
        <span class="ph-topnav-account-name d-none d-sm-inline" id="sidebarUserName">{{ trim(auth()->user()->first_name.' '.auth()->user()->middle_name.' '.auth()->user()->last_name) ?: 'My account' }}</span>
        <i class="bi bi-chevron-down ph-profile-chevron" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end ph-account-dropdown shadow" aria-labelledby="peerhiveAccountMenu">
        <li class="px-3 pt-3 pb-2">
          <span class="fw-semibold d-block text-truncate">{{ trim(auth()->user()->first_name.' '.auth()->user()->last_name) ?: 'My account' }}</span>
          <small class="d-block text-body-secondary text-truncate" id="sidebarUserEmail">{{ auth()->user()->email ?? '' }}</small>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item ph-account-item" href="{{ route('users.profile', auth()->user()) }}"><i class="bi bi-person-circle" aria-hidden="true"></i><span>View profile</span></a></li>
        <li><a class="dropdown-item ph-account-item" href="{{ route('profile') }}"><i class="bi bi-gear" aria-hidden="true"></i><span>Settings <small class="text-body-secondary">(Edit profile)</small></span></a></li>
        <li><hr class="dropdown-divider"></li>
        <li class="px-3 py-2 d-flex align-items-center justify-content-between gap-2">
          <span class="d-flex align-items-center gap-2"><i class="bi bi-moon-stars" aria-hidden="true"></i>Dark mode</span>
          <div class="form-check form-switch m-0">
            <input type="checkbox" class="form-check-input ph-dark-mode-switch" id="peerhiveDarkModeSwitch" role="switch" aria-label="Turn dark mode on or off">
          </div>
        </li>
        <li><button class="dropdown-item ph-account-item" type="button" data-bs-toggle="modal" data-bs-target="#peerhiveHelpModal"><i class="bi bi-question-circle" aria-hidden="true"></i><span>How PeerHive Works</span></button></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="dropdown-item ph-account-item ph-logout-item"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Log out</span></button>
          </form>
        </li>
      </ul>
    </li>
</ul>
</div>
</nav>
<!-- Shared How PeerHive Works guide, available from the profile menu and sidebar. -->
<div class="modal fade" id="peerhiveHelpModal" tabindex="-1" aria-labelledby="peerhiveHelpTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content ph-help-modal">
      <div class="modal-header">
        <div>
          <p class="ph-help-eyebrow mb-1">PEERHIVE GUIDE</p>
          <h2 class="modal-title fs-4 fw-bold" id="peerhiveHelpTitle">How PeerHive Works</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close How PeerHive Works"></button>
      </div>
      <div class="modal-body">
        <p class="text-body-secondary mb-4">Learn, share your skills, and connect with fellow students in a few simple steps.</p>
        {{-- The Help popup uses the exact same five-step carousel as the new-user dashboard. --}}
        <div class="ph-v45-modal-guide">
          @include('dashboard.quick-start', ['helpModal' => true])
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('profile') }}">Edit my profile</a>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const helpModal = document.getElementById('peerhiveHelpModal');
    helpModal?.addEventListener('show.bs.modal', () => {
      const accountTrigger = document.getElementById('peerhiveAccountMenu');
      if (accountTrigger && window.bootstrap?.Dropdown) {
        window.bootstrap.Dropdown.getOrCreateInstance(accountTrigger).hide();
      }
    });
    // Help is present on every authenticated page, including those without dashboard.js.
    // Initialize its own carousel only when the modal is first opened.
    let helpCarouselReady = false;
    helpModal?.addEventListener('shown.bs.modal', () => {
      if (helpCarouselReady || !window.bootstrap?.Carousel) return;
      const guide = document.getElementById('peerHiveHelpWalkthrough');
      if (!guide) return;
      helpCarouselReady = true;
      const instance = new window.bootstrap.Carousel(guide, { interval: false, wrap: false, touch: true, keyboard: true });
      const slides = Array.from(guide.querySelectorAll('.carousel-item'));
      const dots = Array.from(guide.querySelectorAll('[data-tutorial-slide]'));
      const previous = document.getElementById('peerHiveHelpPrevious');
      const next = document.getElementById('peerHiveHelpNext');
      previous?.addEventListener('click', () => instance.prev());
      next?.addEventListener('click', () => instance.next());
      dots.forEach((dot, index) => dot.addEventListener('click', () => instance.to(index)));
      guide.addEventListener('slid.bs.carousel', event => {
        if (previous) previous.disabled = event.to === 0;
        if (next) next.disabled = event.to === slides.length - 1;
        slides.forEach((slide, index) => slide.setAttribute('aria-hidden', String(index !== event.to)));
        dots.forEach((dot, index) => {
          dot.classList.toggle('is-active', index === event.to);
          dot.setAttribute('aria-current', index === event.to ? 'step' : 'false');
        });
        if (document.activeElement === previous && previous.disabled) next?.focus();
        if (document.activeElement === next && next.disabled) previous?.focus();
      });
    });
    const toggle = document.getElementById('peerhiveDarkModeSwitch');
    if (!toggle) return;
    const sync = () => {
      toggle.checked = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    };
    toggle.addEventListener('change', () => {
      const theme = toggle.checked ? 'dark' : 'light';
      try { localStorage.setItem('theme', theme); } catch {}
      document.documentElement.setAttribute('data-bs-theme', theme);
    });
    // Supports theme changes elsewhere in PeerHive without stale switch state.
    new MutationObserver(sync).observe(document.documentElement, {attributes: true, attributeFilter: ['data-bs-theme']});
    sync();
  });
</script>
