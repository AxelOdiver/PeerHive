document.querySelectorAll('[data-copy-session]').forEach(button => {
  button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(button.dataset.copySession);
      window.toast?.('success', 'Session link copied. You can paste it into a group chat.');
    } catch {
      window.toast?.('error', 'Could not copy. Copy the address from your browser instead.');
    }
  });
});
