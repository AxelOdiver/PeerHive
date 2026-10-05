const password = document.querySelector('input[name="password"][aria-describedby="password-requirements"]');
const requirements = document.querySelector('[data-password-requirements]');

if (password && requirements) {
  const update = () => {
    const value = password.value;
    requirements.closest('#password-requirements').hidden = document.activeElement !== password && value.length === 0;
    const rules = {
      length: [...value].length >= 8,
      uppercase: /[A-Z]/.test(value),
      number: /[0-9]/.test(value),
      special: /[^A-Za-z0-9]/.test(value),
    };
    for (const [rule, met] of Object.entries(rules)) {
      const item = requirements.querySelector(`[data-password-rule="${rule}"]`);
      item.classList.toggle('text-success', met);
      item.querySelector('i').className = `bi ${met ? 'bi-check-circle-fill' : 'bi-circle'} me-1`;
      item.querySelector('[data-rule-status]').textContent = met ? 'Met: ' : 'Not met: ';
    }
  };
  password.addEventListener('input', update);
  password.addEventListener('change', update);
  password.addEventListener('focus', update);
  password.addEventListener('blur', update);
  update();
}
