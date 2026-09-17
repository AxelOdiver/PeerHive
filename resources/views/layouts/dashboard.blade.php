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
      function fetchUnreadMessageCount() {
        fetch('/messages/unread-count')
          .then(response => response.json())
          .then(data => {
            const count = data.count;
            
            // CHANGED: Grab the new global badge instead of the separate message badge
            const globalBadge = document.getElementById('globalNotificationBadge');
            const sidebarBadge = document.getElementById('sidebarMessageBadge');
            
            // 1. Calculate and update the Unified Top Nav Badge (Invites + Messages)
            if (globalBadge) {
              const inviteCount = parseInt(globalBadge.getAttribute('data-invite-count')) || 0;
              const totalCount = count + inviteCount;
              
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
              notifList.innerHTML = ''; // Clear previous
              
              if (data.notifications && data.notifications.length > 0) {
                // Hide the "No notifications" text because we have messages
                if (noNotifMsg) noNotifMsg.classList.add('d-none');
                
                data.notifications.forEach(notif => {
                  notifList.innerHTML += `
                    <li>
                      <a href="/messages" class="dropdown-item border-bottom py-2 px-3 text-decoration-none">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                          <strong class="small text-primary">${notif.name}</strong>
                          <span class="badge bg-primary ms-2" style="font-size: 0.55rem;">New Message</span>
                        </div>
                        <p class="mb-1 text-muted small text-truncate" style="max-width: 95%;">${notif.text}</p>
                        <small class="text-muted" style="font-size: 0.65rem;">${notif.time}</small>
                      </a>
                    </li>
                  `;
                });
              } else {
                // Show the empty message again if there are no messages (and no invites)
                if (noNotifMsg) noNotifMsg.classList.remove('d-none');
              }
            }
          })
          .catch(error => console.error('Error fetching unread count:', error));
      }

      fetchUnreadMessageCount();
      setInterval(fetchUnreadMessageCount, 15000);
      window.refreshSidebarMessagesBadge = fetchUnreadMessageCount;
    });
  </script>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary" data-page="{{ Route::currentRouteName() }}" @if(session('error')) data-error-message="{{ session('error') }}" @elseif(session('success')) data-success-message="{{ session('success') }}" @endif>
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