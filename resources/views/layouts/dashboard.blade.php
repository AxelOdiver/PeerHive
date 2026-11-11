<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard')</title>
  <link rel="icon" type="image/png" href="{{ asset('images/peerhive-logo.png') }}?v=2">
  @php($sidebarAvatarUrl = auth()->user()->profile_picture_url ?? route('profile-picture.show', ['filename' => 'default']))
  <link rel="preload" as="image" href="{{ $sidebarAvatarUrl }}" fetchpriority="high">
  @include('layouts.partials.theme-init')
  
  <script>
    // Global Unread Messages & Notifications (Waits for HTML to load)
    document.addEventListener("DOMContentLoaded", function () {
      const escapeNotification = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
      let notificationHistory = [];
      let notificationView = 'all';
      const notificationList = document.getElementById('messageNotificationsList');
      let notificationToolbar = null;
      let renderedNotificationList = null;
      const notificationHref = value => {
        try {
          const url = new URL(String(value ?? ''), window.location.origin);
          if (url.origin !== window.location.origin) return '/messages';
          return url.pathname + url.search + url.hash;
        } catch { return '/messages'; }
      };
      const notificationIcon = kind => ({
        message: 'bi-chat-dots', swap: 'bi-arrow-left-right', session: 'bi-calendar-check',
        community: 'bi-people', group: 'bi-people', report: 'bi-shield-check'
      }[String(kind ?? '').toLowerCase()] ?? 'bi-bell');

      function initNotificationToolbar() {
        if (!notificationList || notificationToolbar) return;
        // Old bulk-clear removes database rows, which polling can regenerate.
        // Replace it with persistent, per-event dismissal instead.
        document.querySelectorAll('[data-notification-action="clear-read"]').forEach(node => node.remove());
        const panel = notificationList.closest('.dropdown-menu');
        if (panel) {
          panel.classList.add('ph-notify-panel');
          // Bootstrap must close on outside clicks, not while managing alerts.
          const dropdownToggle = panel.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]');
          if (dropdownToggle) dropdownToggle.setAttribute('data-bs-auto-close', 'outside');
          const readAll = panel.querySelector('[data-notification-action="read-all"]');
          const title = panel.querySelector('.dropdown-header');
          if (readAll && title) {
            const oldRow = readAll.closest('li');
            const heading = title.closest('li');
            if (heading) {
              heading.classList.add('ph-notify-heading');
              heading.appendChild(readAll);
              if (oldRow && oldRow !== heading && oldRow.childElementCount === 0) oldRow.remove();
            }
          }
        }
        notificationToolbar = document.createElement('li');
        notificationToolbar.className = 'ph-notification-tools';
        notificationToolbar.innerHTML = `<div class="d-flex align-items-center justify-content-between gap-2">
          <div class="d-flex gap-2" role="group" aria-label="Filter notifications">
            <button type="button" class="btn btn-sm btn-primary" data-ph-notify-filter="all" aria-pressed="true">All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ph-notify-filter="unread" aria-pressed="false">Unread <span data-ph-notify-count></span></button>
          </div>
          <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none" data-ph-notify-clear-all title="Clear alerts, not messages or invitations">Clear all</button>
        </div><div class="visually-hidden" role="status" aria-live="polite" data-ph-notify-status></div>`;
        notificationList.before(notificationToolbar);
        document.getElementById('noNotificationsMsg')?.classList.add('d-none');
      }
      function renderNotificationHistory() {
        if (!notificationList) return;
        initNotificationToolbar();
        const unreadCount = notificationHistory.filter(item => !item.read).length;
        const shown = notificationHistory.filter(item => notificationView === 'all' || !item.read);
        notificationToolbar?.querySelectorAll('[data-ph-notify-filter]').forEach(button => {
          const active = button.dataset.phNotifyFilter === notificationView;
          button.classList.toggle('btn-primary', active);
          button.classList.toggle('btn-outline-secondary', !active);
          button.setAttribute('aria-pressed', String(active));
        });
        const number = notificationToolbar?.querySelector('[data-ph-notify-count]');
        if (number) number.textContent = unreadCount ? `(${unreadCount})` : '';
        const markup = shown.length ? shown.map(notif => {
          const id = Number(notif.id);
          if (!Number.isSafeInteger(id) || id < 1) return '';
          const unread = !notif.read;
          const title = escapeNotification(notif.name);
          const content = escapeNotification(notif.text);
          const when = escapeNotification(notif.time);
          const icon = notificationIcon(notif.kind);
          const href = escapeNotification(notificationHref(notif.url));
          return `<div class="ph-notify-item ${unread ? 'ph-notify-unread' : ''}" role="listitem">
            <a href="${href}" class="ph-notify-open" data-ph-notify-open="${id}" aria-label="Open ${title}">
              <span class="ph-notify-icon"><i class="bi ${icon}" aria-hidden="true"></i></span>
              <span class="ph-notify-copy">
                <span class="ph-notify-title"><strong class="small">${title}</strong>${unread ? '<span class="ph-notify-dot" aria-label="Unread"></span>' : ''}</span>
                <span class="small text-body-secondary">${content}</span>
              </span>
            </a>
            <div class="ph-notify-bottom">
              <small class="text-body-secondary">${when}</small>
              <div class="ph-notify-actions" role="group" aria-label="Notification actions">
                <button type="button" class="btn ph-notify-action" data-ph-notify-op="read-state" data-id="${id}" data-read="${unread ? '1' : '0'}" title="Mark as ${unread ? 'read' : 'unread'}" aria-label="Mark as ${unread ? 'read' : 'unread'}"><i class="bi ${unread ? 'bi-check2-circle' : 'bi-circle'}" aria-hidden="true"></i></button>
                <button type="button" class="btn ph-notify-action ph-notify-delete" data-ph-notify-op="dismiss" data-id="${id}" title="Delete notification" aria-label="Delete notification"><i class="bi bi-trash3" aria-hidden="true"></i></button>
              </div>
            </div>
          </div>`;
        }).join('') : `<div class="ph-notify-empty text-body-secondary" role="status"><i class="bi bi-bell-slash me-2" aria-hidden="true"></i>${notificationView === 'unread' ? 'No unread notifications.' : 'You’re all caught up.'}</div>`;
        if (markup === renderedNotificationList) return;

        const focused = document.activeElement;
        const restoreFocus = notificationList.contains(focused);
        notificationList.innerHTML = markup;
        renderedNotificationList = markup;
        if (restoreFocus) {
          const replacement = Array.from(notificationList.querySelectorAll('[data-ph-notify-open], [data-ph-notify-op]'))
            .find(control => control.dataset.phNotifyOpen === focused.dataset.phNotifyOpen
              && control.dataset.id === focused.dataset.id
              && control.dataset.phNotifyOp === focused.dataset.phNotifyOp);
          const fallback = notificationToolbar?.querySelector('[data-ph-notify-filter][aria-pressed="true"]');
          if (replacement) replacement.focus({ preventScroll: true });
          else fallback?.focus();
        }
      }
      let badgeRequestPending = false;
      function fetchUnreadMessageCount() {
        if (document.hidden || badgeRequestPending) return;
        badgeRequestPending = true;
        return fetch('/messages/unread-count')
          .then(response => response.json())
          .then(data => {
            const count = data.count;
            const swapBadge = document.getElementById('sidebarSwapBadge');
            if (swapBadge) {
              swapBadge.textContent = (data.swap_unread_count || 0);
              swapBadge.classList.toggle('d-none', (data.swap_unread_count || 0) === 0);
            }
            
            // CHANGED: Grab the new global badge instead of the separate message badge
            const globalBadge = document.getElementById('globalNotificationBadge');
            const sidebarBadge = document.getElementById('sidebarMessagesBadge');
            
            // 1. Calculate and update the Unified Top Nav Badge (Invites + Messages)
            if (globalBadge) {
              const inviteCount = parseInt(globalBadge.getAttribute('data-invite-count')) || 0;
              const totalCount = inviteCount + (data.history_unread_count || 0);
              
              if (totalCount > 0) {
                globalBadge.textContent = totalCount > 99 ? '99+' : totalCount;
                globalBadge.classList.remove('d-none');
              } else {
                globalBadge.classList.add('d-none');
              }
            }

            // 2. Update the Sidebar Badge (Messages ONLY)
            if (sidebarBadge) {
              if (count > 0) {
                sidebarBadge.textContent = count;
                sidebarBadge.classList.remove('d-none');
              } else {
                sidebarBadge.classList.add('d-none');
              }
            }

            // Refresh visible alerts while preserving the All/Unread filter.
            notificationHistory = Array.isArray(data.history) ? data.history : [];
            renderNotificationHistory();
          })
          .catch(error => console.error('Error fetching unread count:', error))
          .finally(() => { badgeRequestPending = false; });
      }

      async function notificationAction(path, data = null) {
        const response = await fetch(path, { method: 'POST', headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json', ...(data ? {'Content-Type': 'application/json'} : {})
        }, ...(data ? {body: JSON.stringify(data)} : {}) });
        if (!response.ok) throw new Error('Could not update the notification. Try again.');
      }
      document.addEventListener('click', async event => {
        const filter = event.target.closest('[data-ph-notify-filter]');
        if (filter) {
          event.preventDefault(); event.stopPropagation();
          notificationView = filter.dataset.phNotifyFilter === 'unread' ? 'unread' : 'all';
          renderNotificationHistory(); return;
        }
        const clearAll = event.target.closest('[data-ph-notify-clear-all]');
        const itemAction = event.target.closest('[data-ph-notify-op]');
        const open = event.target.closest('[data-ph-notify-open]');
        const legacy = event.target.closest('[data-notification-action]');
        if (!clearAll && !itemAction && !open && !legacy) return;
        event.preventDefault();
        if (clearAll || itemAction || legacy) event.stopPropagation();
        if (clearAll && !notificationHistory.length) return;
        if (clearAll && !window.confirm('Clear all notification alerts? Messages, sessions, and invitations will not be deleted.')) return;
        const source = clearAll || itemAction || legacy || open;
        if (source.dataset.phNotifyBusy === '1') return;
        source.dataset.phNotifyBusy = '1';
        source.setAttribute('aria-disabled', 'true');
        try {
          if (clearAll) await notificationAction('/notifications/clear-all');
          else if (itemAction) {
            const id = Number(itemAction.dataset.id);
            if (!Number.isSafeInteger(id) || id < 1) return;
            if (itemAction.dataset.phNotifyOp === 'dismiss') await notificationAction(`/notifications/${id}/dismiss`);
            else await notificationAction(`/notifications/${id}/read-state`, {read: itemAction.dataset.read === '1'});
          } else if (legacy) await notificationAction(`/notifications/${legacy.dataset.notificationAction}`);
          else if (open) {
            await notificationAction(`/notifications/${open.dataset.phNotifyOpen}/read`);
            window.location.assign(open.href); return;
          }
          await fetchUnreadMessageCount();
          const status = notificationToolbar?.querySelector('[data-ph-notify-status]');
          if (status) status.textContent = clearAll ? 'Notifications cleared.' : itemAction?.dataset.phNotifyOp === 'dismiss' ? 'Notification deleted.' : 'Notifications updated.';
        } catch (error) { window.toast?.('error', error.message); }
        finally { delete source.dataset.phNotifyBusy; source.removeAttribute('aria-disabled'); }
      });
      initNotificationToolbar();
      fetchUnreadMessageCount();
      setInterval(fetchUnreadMessageCount, 15000);
      document.addEventListener('visibilitychange', () => { if (!document.hidden) fetchUnreadMessageCount(); });
      window.refreshSidebarMessagesBadge = fetchUnreadMessageCount;
    });
  </script>

  <style>
    /* v4.7.7g.3: more readable notification dropdown; no backend changes. */
    .ph-notify-panel { width:min(430px, calc(100vw - 24px)) !important; min-width:0; max-height:min(78vh, 700px) !important; overflow-x:hidden; overflow-y:auto; box-sizing:border-box; padding:4px 0; }
    .ph-notify-heading { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px 8px; }
    .ph-notify-heading .dropdown-header { padding:0; margin:0; font-size:1rem; font-weight:700; line-height:1.4; color:var(--bs-body-color); }
    .ph-notify-heading [data-notification-action="read-all"] { background:transparent; border:0; padding:5px 2px; box-shadow:none; font-size:.78rem; line-height:1.4; color:var(--bs-link-color); white-space:nowrap; }
    .ph-notify-heading [data-notification-action="read-all"]:hover { text-decoration:underline; color:var(--bs-link-hover-color); }
    .ph-notification-tools { padding:5px 16px 12px; border-bottom:1px solid var(--bs-border-color); }
    .ph-notification-tools [data-ph-notify-filter], .ph-notification-tools [data-ph-notify-clear-all] { min-height:34px; border-radius:9px; font-size:.82rem; }
    .ph-notification-tools [data-ph-notify-clear-all] { padding-inline:5px; white-space:nowrap; }
    .ph-notify-item { position:relative; display:block; min-width:0; border-bottom:1px solid var(--bs-border-color); padding:3px 0 8px; cursor:pointer; }
    .ph-notify-item:last-child { border-bottom:0; }
    .ph-notify-item.ph-notify-unread { background:var(--bs-tertiary-bg); }
    .ph-notify-item:hover { background:var(--bs-secondary-bg); }
    .ph-notify-open { min-width:0; display:flex; gap:12px; align-items:flex-start; padding:13px 16px 3px; color:var(--bs-body-color); text-decoration:none; border-radius:8px; }
    .ph-notify-open:hover { color:var(--bs-body-color); }
    /* Stretch the real link over the entire notification, including the timestamp.
       Keep management buttons above it so they never navigate by accident. */
    .ph-notify-open::after { content:""; position:absolute; inset:0; z-index:1; }
    .ph-notify-open:focus-visible { outline:none; }
    .ph-notify-open:focus-visible::after { outline:2px solid var(--bs-primary); outline-offset:-2px; border-radius:6px; }
    .ph-notify-action:focus-visible, .ph-notification-tools button:focus-visible { outline:2px solid var(--bs-primary); outline-offset:2px; }
    .ph-notify-icon { display:grid; place-items:center; flex:0 0 36px; width:36px; height:36px; border-radius:11px; background:var(--bs-secondary-bg); color:var(--bs-primary); }
    .ph-notify-copy { flex:1; display:grid; gap:4px; min-width:0; overflow-wrap:break-word; line-height:1.45; }
    .ph-notify-title { display:flex; align-items:center; flex-wrap:wrap; gap:7px; }
    .ph-notify-title strong { font-weight:650; }
    .ph-notify-dot { flex:0 0 7px; width:7px; height:7px; border-radius:50%; background:#ffa500; }
    .ph-notify-bottom { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:3px 16px 0 64px; min-height:35px; }
    .ph-notify-bottom small { font-size:.73rem; white-space:nowrap; }
    .ph-notify-actions { position:relative; z-index:2; display:flex; align-items:center; justify-content:flex-end; gap:5px; margin-left:auto; flex:0 0 auto; }
    /* Higher specificity than Bootstrap .btn: guarantee equal square hit targets and
       true horizontal/vertical icon alignment after Vite CSS is loaded. */
    .ph-notify-actions .btn.ph-notify-action { display:inline-flex; align-items:center; justify-content:center; flex:0 0 36px; width:36px; height:36px; min-width:36px; min-height:36px; margin:0; padding:0; border:0; border-radius:9px; box-shadow:none; line-height:1; vertical-align:middle; background:transparent; color:var(--bs-secondary-color); }
    .ph-notify-actions .btn.ph-notify-action .bi { display:block; flex:none; width:1.25rem; height:1.25rem; font-size:1.125rem; line-height:1.25rem; text-align:center; }
    .ph-notify-actions .btn.ph-notify-action .bi::before { vertical-align:0; }
    .ph-notify-actions .btn.ph-notify-action:hover { background:var(--bs-secondary-bg); color:var(--bs-body-color); }
    .ph-notify-actions .btn.ph-notify-delete:hover { color:var(--bs-danger); }
    .ph-notify-empty { padding:22px 16px; font-size:.875rem; text-align:center; }
    @media (max-width:575.98px) { .ph-notify-panel { width:calc(100vw - 16px) !important; max-width:430px; } .ph-notify-open { padding-inline:13px; } .ph-notify-bottom { padding-right:13px; padding-left:61px; } .ph-notification-tools { padding-inline:13px; } .ph-notify-heading { padding-inline:13px; } }
  </style>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
</head>
<body class="layout-fixed fixed-header sidebar-expand-lg sidebar-mini bg-body-tertiary" data-authenticated="true" data-page="{{ Route::currentRouteName() }}" @if(session('error')) data-error-message="{{ session('error') }}" @elseif(session('success')) data-success-message="{{ session('success') }}" @endif>
  <script id="sidebar-initial-state">
    // Apply the saved layout before any sidebar or page content can be painted.
    (() => {
      const desktop = window.matchMedia('(min-width: 992px)').matches;
      let savedState = null;
      try { savedState = localStorage.getItem('lte.sidebar.state'); } catch {}
      document.body.classList.add(!desktop || savedState === 'sidebar-collapse' ? 'sidebar-collapse' : 'sidebar-open');
    })();
  </script>
  <div class="app-wrapper">
    @include('layouts.dashboard.topnav')
    @include('layouts.dashboard.sidebar')
    
    <main class="app-main">
      <div class="app-content px-3 py-3 px-lg-4 py-lg-4 @yield('content-class')">
        <div class="container-fluid px-0">
          @yield('content')
        </div>
      </div>
    </main>
    
    @include('layouts.dashboard.footer')
  </div>
  @stack('modals')
</body>
</html>
