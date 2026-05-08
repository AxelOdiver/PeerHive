<style>
/* ── Search wrapper ───────────────────────────────────── */
#searchWrapper {
    position: relative;
    width: 300px;
}

#globalSearch {
    border-radius: 20px;
    padding-left: 2.4rem;
    padding-right: 1rem;
    font-size: 0.875rem;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

#globalSearch:focus {
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15);
}

.search-icon-prefix {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--bs-secondary-color);
    pointer-events: none;
    z-index: 5;
    font-size: 0.85rem;
}

/* ── Dropdown ─────────────────────────────────────────── */
#searchDropdown {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    z-index: 1055;
    max-height: 360px;
    overflow-y: auto;
    padding: 6px 0;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-10px);
    transition: opacity 0.16s ease, transform 0.16s ease;
}

#searchDropdown.is-open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}

.search-section-label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--bs-secondary-color);
    padding: 6px 14px 4px;
}

.search-item {
    padding: 8px 14px;
    color: var(--bs-body-color);
    transition: background 0.12s ease;
}

.search-item:hover {
    background: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}

.search-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--bs-primary);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.search-avatar.community-avatar {
    background: var(--bs-secondary-bg);
    color: var(--bs-secondary-color);
    font-size: 1rem;
}

.search-item-title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.2;
    color: var(--bs-body-color);
}

.search-item-sub {
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
    line-height: 1.2;
    margin-top: 1px;
}

.search-empty {
    padding: 16px 14px;
    font-size: 0.875rem;
    color: var(--bs-secondary-color);
    text-align: center;
}
</style>

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

    <!-- Notifications Dropdown Menu -->
    <ul class="navbar-nav ms-auto align-items-center gap-2">
      <li class="nav-item dropdown">
        <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-expanded="false">
          <i class="bi bi-bell"></i>
          <span class="navbar-badge badge text-bg-danger">3</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
          <a href="#" class="dropdown-item">
            <div class="d-flex">
              <div class="flex-shrink-0">
                <img src="https://media.istockphoto.com/id/1495088043/vector/user-profile-icon-avatar-or-person-icon-profile-picture-portrait-symbol-default-portrait.jpg?s=612x612&w=0&k=20&c=dhV2p1JwmloBTOaGAtaA3AW1KSnjsdMt7-U_3EZElZ0=" alt="User Avatar" class="img-size-50 rounded-circle me-3">
              </div>
              <div class="flex-grow-1">
                <h3 class="dropdown-item-title">
                  Axel Odiver
                  <span class="float-end fs-7 text-danger">
                    <i class="bi bi-star-fill"></i>
                  </span>
                </h3>
                <p class="fs-7">Call me whenever you can...</p>
                <p class="fs-7 text-secondary">
                  <i class="bi bi-clock-fill me-1"></i> 4 Hours Ago
                </p>
              </div>
            </div>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <div class="d-flex">
              <div class="flex-shrink-0">
                <img src="https://media.istockphoto.com/id/1495088043/vector/user-profile-icon-avatar-or-person-icon-profile-picture-portrait-symbol-default-portrait.jpg?s=612x612&w=0&k=20&c=dhV2p1JwmloBTOaGAtaA3AW1KSnjsdMt7-U_3EZElZ0=" alt="User Avatar" class="img-size-50 rounded-circle me-3">
              </div>
              <div class="flex-grow-1">
                <h3 class="dropdown-item-title">
                  John Paul Castro
                  <span class="float-end fs-7 text-secondary">
                    <i class="bi bi-star-fill"></i>
                  </span>
                </h3>
                <p class="fs-7">I got your message bro</p>
                <p class="fs-7 text-secondary">
                  <i class="bi bi-clock-fill me-1"></i> 4 Hours Ago
                </p>
              </div>
            </div>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <div class="d-flex">
              <div class="flex-shrink-0">
                <img src="https://media.istockphoto.com/id/1495088043/vector/user-profile-icon-avatar-or-person-icon-profile-picture-portrait-symbol-default-portrait.jpg?s=612x612&w=0&k=20&c=dhV2p1JwmloBTOaGAtaA3AW1KSnjsdMt7-U_3EZElZ0=" alt="User Avatar" class="img-size-50 rounded-circle me-3">
              </div>
              <div class="flex-grow-1">
                <h3 class="dropdown-item-title">
                  Dominic Belen
                  <span class="float-end fs-7 text-warning">
                    <i class="bi bi-star-fill"></i>
                  </span>
                </h3>
                <p class="fs-7">The subject goes here</p>
                <p class="fs-7 text-secondary">
                  <i class="bi bi-clock-fill me-1"></i> 4 Hours Ago
                </p>
              </div>
            </div>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
        </div>
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
