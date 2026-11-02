<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard')</title>
  
  <script>
    // --- 1. Theme Configuration (Runs instantly to prevent white-flash) ---
    (() => {
      'use strict';
      
      const storedTheme = localStorage.getItem('theme');
      
      const getPreferredTheme = () => {
        if (storedTheme) {
          return storedTheme;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      };
      
      const setTheme = function (theme) {
        if (theme === 'auto') {
          document.documentElement.setAttribute(
            'data-bs-theme',
            window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
          );
        } else {
          document.documentElement.setAttribute('data-bs-theme', theme);
        }
      };
      
      setTheme(getPreferredTheme());
    })();

    // --- 2. Global Unread Messages & Notifications (Waits for HTML to load) ---
    document.addEventListener("DOMContentLoaded", function () {
      const escapeNotification = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
      let badgeRequestPending = false;
      function fetchUnreadMessageCount() {
        if (document.hidden || badgeRequestPending) return;
        badgeRequestPending = true;
        fetch('/messages/unread-count')
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

            // Update the Dropdown Notifications List
            const notifList = document.getElementById('messageNotificationsList');
            const noNotifMsg = document.getElementById('noNotificationsMsg');
            
            if (notifList) {
              notifList.innerHTML = '';
              (data.history || []).forEach(notif => {
                notifList.innerHTML += `<li><a href="${escapeNotification(notif.url)}" data-notification-id="${notif.id}" class="dropdown-item border-bottom py-2 px-3 text-decoration-none ${notif.read ? 'text-muted' : 'bg-primary-subtle'}"><div class="d-flex justify-content-between gap-2"><strong class="small text-wrap">${escapeNotification(notif.name)}</strong><small>${notif.read ? 'Read' : 'Unread'}</small></div><p class="mb-1 small text-wrap">${escapeNotification(notif.text)}</p><small>${escapeNotification(notif.time)}</small></a></li>`;
              });
              if (noNotifMsg) noNotifMsg.classList.toggle('d-none', (data.history || []).length > 0);

            }
          })
          .catch(error => console.error('Error fetching unread count:', error))
          .finally(() => { badgeRequestPending = false; });
      }

      async function notificationAction(path) {
        const response = await fetch(path, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json' } });
        if (!response.ok) throw new Error('Unable to update notifications.');
      }
      document.addEventListener('click', async event => {
        const link = event.target.closest('[data-notification-id]');
        const action = event.target.closest('[data-notification-action]');
        if (!link && !action) return;
        event.preventDefault();
        try {
          await notificationAction(link ? `/notifications/${link.dataset.notificationId}/read` : `/notifications/${action.dataset.notificationAction}`);
          if (link) window.location.assign(link.href);
          else fetchUnreadMessageCount();
        } catch (error) { window.toast?.('error', error.message); }
      });
      fetchUnreadMessageCount();
      setInterval(fetchUnreadMessageCount, 15000);
      document.addEventListener('visibilitychange', () => { if (!document.hidden) fetchUnreadMessageCount(); });
      window.refreshSidebarMessagesBadge = fetchUnreadMessageCount;
    });
  </script>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary" data-authenticated="true" data-page="{{ Route::currentRouteName() }}" @if(session('error')) data-error-message="{{ session('error') }}" @elseif(session('success')) data-success-message="{{ session('success') }}" @endif>
  <div class="app-wrapper">
    @include('layouts.dashboard.topnav')
    @include('layouts.dashboard.sidebar')
    
    <main class="app-main">
      <div class="app-content px-3 py-3 px-lg-4 py-lg-4">
        <div class="container-fluid px-0">
          @yield('content')
        </div>
      </div>
    </main>
    
    @include('layouts.dashboard.footer')
  </div>
</body>
</html>
