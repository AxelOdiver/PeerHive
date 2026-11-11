import { isValidPeerhiveName } from './register-validation.js';

/** Generate a username suggestion from the two name fields. Browser-only UI helper. */
export function usernameBase(firstName, lastName) {
  const normalize = (value) => String(value || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');
  const first = normalize(firstName);
  const last = normalize(lastName);
  return `${first || 'peer'}_${last || 'student'}`.slice(0, 25).replace(/_+$/g, '');
}

export function generateUsername(firstName, lastName, randomNumber = null) {
  const number = Number.isInteger(randomNumber) ? randomNumber : (() => {
    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
      const sample = new Uint32Array(1);
      crypto.getRandomValues(sample);
      return sample[0] % 10000;
    }
    return Math.floor(Math.random() * 10000);
  })();
  return `${usernameBase(firstName, lastName).slice(0, 25)}${String(number).padStart(4, '0')}`.slice(0, 30);
}

export function initUsernameGenerator(root = document) {
  const first = root.getElementById('register-first_name');
  const last = root.getElementById('register-last_name');
  const username = root.getElementById('register-username');
  const generate = root.getElementById('peerhiveUsernameGenerator');
  if (!first || !last || !username || !generate) return;
  const validName = (value) => isValidPeerhiveName(value.trim());
  const updateVisibility = () => {
    generate.hidden = !(validName(first.value) && validName(last.value));
  };
  first.addEventListener('input', updateVisibility);
  last.addEventListener('input', updateVisibility);
  generate.addEventListener('click', () => {
    if (generate.hidden) return;
    username.value = generateUsername(first.value, last.value);
    username.dispatchEvent(new Event('input', { bubbles: true }));
    username.focus();
  });
  updateVisibility();
}
