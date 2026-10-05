document.addEventListener('click', (event) => {
  const button = event.target.closest('button.toggle-password');
  if (!button) return;
  const input = document.getElementById(button.getAttribute('aria-controls'));
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.setAttribute('aria-pressed', String(show));
  const label = button.getAttribute('aria-label').replace(/^(Show|Hide) /, '');
  button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${label}`);
  button.querySelector('i').className = `bi ${show ? 'bi-eye-fill' : 'bi-eye-slash-fill'}`;
});
