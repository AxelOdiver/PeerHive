// Global utility functions
export function clearErrors($form, alertId = null) {
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('[data-error-for]').text('');

    if (alertId) {
        $(`#${alertId}`).addClass('d-none').text('');
    }
    
}

export function showFormErrors($form, errors) {
    if (!$form || !errors) return;

    // clear previous errors
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('[data-error-for]').text('');

    for (const field in errors) {
        const msg = errors[field]?.[0] ?? 'Invalid input';

        const $input = $form.find(`[name="${field}"]`);
        const $error = $form.find(`[data-error-for="${field}"]`);

        if ($input.length) {
            $input.addClass('is-invalid');
        }

        if ($error.length) {
            $error.text(msg);
        }
    }
}

export function toast(type, message) {
  let bgClass = 'bg-success-subtle text-success';
  
  if (type === 'error') {
    bgClass = 'bg-danger-subtle text-danger';
  }

  window.Swal.fire({
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

export function confirmAction(text = 'Please confirm that you want to continue.', confirmButtonText = 'Confirm') {
  const theme = document.documentElement.getAttribute('data-bs-theme') || 'light';
  const isDark = theme === 'dark';

  return window.Swal.fire({
    title: 'Are you sure?',
    text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusCancel: true,
    allowOutsideClick: true,
    allowEscapeKey: true,
    buttonsStyling: false,
    background: isDark ? '#212529' : '#ffffff',
    color: isDark ? '#f8f9fa' : '#212529',
    customClass: {
      popup: `shadow border-0 rounded-4 confirm-dialog ${isDark ? 'confirm-dialog-dark' : 'confirm-dialog-light'}`,
      title: 'h4 mb-2',
      htmlContainer: isDark ? 'text-light-emphasis' : 'text-muted',
      actions: 'gap-2',
      confirmButton: 'btn btn-danger',
      cancelButton: 'btn btn-outline-secondary',
    },
  });
}

export function handleSessionToast() {
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

  // Also handle session messages from Laravel
  if (document.body.dataset.errorMessage) {
    toast('error', document.body.dataset.errorMessage);
  } else if (document.body.dataset.successMessage) {
    toast('success', document.body.dataset.successMessage);
  }
}

export function getPreferredTheme() {
  const storedTheme = localStorage.getItem('theme');

  if (storedTheme) {
    return storedTheme;
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function setTheme(theme) {
  if (theme === 'auto') {
    document.documentElement.setAttribute(
      'data-bs-theme',
      window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
    );
  } else {
    document.documentElement.setAttribute('data-bs-theme', theme);
  }
}

function getThemeIcon(theme) {
  if (theme === 'dark') {
    return 'bi-moon-fill';
  }

  if (theme === 'auto') {
    return 'bi-circle-fill-half-stroke';
  }

  return 'bi-sun-fill';
}

export function showActiveTheme(theme) {
  const themeSwitcher = document.querySelector('#bd-theme');

  if (!themeSwitcher) {
    return;
  }

  const activeTheme = theme === 'auto'
    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    : theme;

  const activeIcon = themeSwitcher.querySelector('.theme-icon-active i');

  if (activeIcon) {
    activeIcon.className = `bi ${getThemeIcon(theme)} my-1`;
  }

  document.querySelectorAll('[data-bs-theme-value]').forEach((toggle) => {
    const isActive = toggle.getAttribute('data-bs-theme-value') === theme;
    const checkIcon = toggle.querySelector('.bi-check-lg');

    toggle.classList.toggle('active', isActive);
    toggle.setAttribute('aria-pressed', String(isActive));

    if (checkIcon) {
      checkIcon.classList.toggle('d-none', !isActive);
    }
  });

  themeSwitcher.setAttribute('aria-label', `Theme (${activeTheme})`);
}

export function initThemeSelector() {
  const themeToggles = document.querySelectorAll('[data-bs-theme-value]');

  if (!themeToggles.length) {
    return;
  }

  const applyTheme = (theme) => {
    localStorage.setItem('theme', theme);
    setTheme(theme);
    showActiveTheme(theme);
  };

  showActiveTheme(getPreferredTheme());

  themeToggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      applyTheme(toggle.getAttribute('data-bs-theme-value'));
    });
  });

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const storedTheme = localStorage.getItem('theme') || 'auto';

    if (storedTheme === 'auto') {
      setTheme('auto');
      showActiveTheme('auto');
    }
  });
}

export function showModal(modalId) {
  const modalElement = document.getElementById(modalId);

  if (!modalElement || !window.bootstrap) {
    return;
  }

  window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
}

export function hideModal(modalId) {
  const modalElement = document.getElementById(modalId);

  if (!modalElement || !window.bootstrap) {
    return;
  }

  window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
}
