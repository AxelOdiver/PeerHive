<script id="theme-initial-state">
  // Apply the saved appearance before styles load to avoid a light flash.
  (() => {
    let theme = 'auto';
    try {
      const storedTheme = localStorage.getItem('theme');
      if (['light', 'dark', 'auto'].includes(storedTheme)) theme = storedTheme;
    } catch {}
    document.documentElement.setAttribute('data-bs-theme', theme === 'auto'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : theme);
  })();
</script>
