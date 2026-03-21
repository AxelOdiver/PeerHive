<!doctype html>
<html lang="en" data-bs-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard')</title>
    <script>
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
              window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
            );
          } else {
            document.documentElement.setAttribute('data-bs-theme', theme);
          }
        };

        setTheme(getPreferredTheme());
      })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
      @include('layouts.dashboard.topnav')
      @include('layouts.dashboard.sidebar')

      <main class="app-main">
        <div class="app-content-header">
          <div class="container-fluid">
            <div class="row">
              <div class="col-sm-6">
                <u class="mb-0">@yield('page-title', 'Dashboard')</u>
              </div>
            </div>
          </div>
        </div>
        <div class="app-content">
          <div class="container-fluid">
            @yield('content')
          </div>
        </div>
      </main>

      @include('layouts.dashboard.footer')
    </div>

    <script>
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
              window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
            );
          } else {
            document.documentElement.setAttribute('data-bs-theme', theme);
          }
        };

        setTheme(getPreferredTheme());

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
          const storedTheme = localStorage.getItem('theme');
          if (storedTheme !== 'light' && storedTheme !== 'dark') {
            setTheme(getPreferredTheme());
          }
        });

        window.addEventListener('DOMContentLoaded', () => {
          const $ = window.jQuery;

          if (!$) {
            return;
          }

          const showActiveTheme = (theme, focus = false) => {
            const themeSwitcher = $('#bd-theme');

            if (!themeSwitcher.length) {
              return;
            }

            const themeSwitcherText = $('#bd-theme-text');
            const activeThemeIcon = $('.theme-icon-active i');
            const btnToActive = $(`[data-bs-theme-value="${theme}"]`);
            const iconOfActiveBtn = btnToActive.find('i').attr('class');

            $('[data-bs-theme-value]').each((_, element) => {
              const $element = $(element);

              $element.attr('aria-pressed', 'false');
              $element.find('.bi-check-lg').addClass('d-none');
            });

            btnToActive.attr('aria-pressed', 'true');
            btnToActive.find('.bi-check-lg').removeClass('d-none');
            activeThemeIcon.attr('class', iconOfActiveBtn);
            const themeSwitcherLabel = `${themeSwitcherText.text()} (${btnToActive.data('bs-theme-value')})`;
            themeSwitcher.attr('aria-label', themeSwitcherLabel);

            if (focus) {
              themeSwitcher.trigger('focus');
            }
          };

          showActiveTheme(getPreferredTheme());

          $('[data-bs-theme-value]').on('click', function () {
            const theme = $(this).data('bs-theme-value');
            localStorage.setItem('theme', theme);
            setTheme(theme);
            showActiveTheme(theme, true);
          });
        });

      })();
      function toast(type, message) {
        let bgClass = 'bg-success-subtle text-success';
        
        if (type === 'error') {
          bgClass = 'bg-danger-subtle text-danger';
        }

        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: type,
          title: message,
          showConfirmButton: false,
          timer: 2500,
          timerProgressBar: true,
          customClass: {
            popup: bgClass
          }
        });
      }

      const pendingToast = sessionStorage.getItem('toast');

      if (pendingToast) {
        try {
          const { type, message } = JSON.parse(pendingToast);
          sessionStorage.removeItem('toast');

          if (type && message) {
            toast(type, message);
          }
        } catch (error) {
          sessionStorage.removeItem('toast');
        }
      }

      @if(session('error'))
          toast('error', '{{ session('error') }}'); 
      @elseif(session('success'))
          toast('success', '{{ session('success') }}'); 
      @endif
    </script>

    @stack('scripts')
  </body>
</html>
