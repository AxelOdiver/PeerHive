// Registration name and blur validation. No external dependencies.
export function sanitizePeerhiveName(value) {
  return String(value).replace(/[^\p{L}\p{M} '\-]/gu, '').replace(/ {2,}/g, ' ').replace(/^ +/, '');
}

export function isValidPeerhiveName(value) {
  return /^[\p{L}\p{M}]+(?:[ '\-][\p{L}\p{M}]+)*$/u.test(value);
}

export function initRegistrationValidation(doc = document) {
  const form = doc.getElementById('form');
  if (!form || !doc.getElementById('registerSubmitBtn')) return;

  for (const name of ['first_name', 'middle_name', 'last_name']) {
    form.elements[name]?.setAttribute('data-peerhive-name', '');
  }
  const lastNameField = form.elements.last_name;
  if (lastNameField && !doc.getElementById('peerhive-name-guidance')) {
    const hint = doc.createElement('div');
    hint.id = 'peerhive-name-guidance';
    hint.className = 'form-text mb-3';
    hint.textContent = 'Names can contain letters, spaces, apostrophes, and hyphens.';
    lastNameField.closest('.input-group')?.after(hint);
    lastNameField.setAttribute('aria-describedby', 'peerhive-name-guidance');
  }

  const fields = Array.from(form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]'));
  const touched = new WeakSet();

  function validate(field) {
    const error = form.querySelector(`[data-error-for="${field.name}"]`);
    if (!error) return true;

    const value = field.value.trim();
    let message = '';
    if (field.required && !value) {
      message = 'This field is required.';
    } else if (field.hasAttribute('data-peerhive-name') && value && !isValidPeerhiveName(value)) {
      message = 'Use letters, spaces, apostrophes, or hyphens only.';
    } else if (field.type === 'email' && value && !field.validity.valid) {
      message = 'Enter a valid email address.';
    } else if (field.name === 'username' && value && !field.validity.valid) {
      message = 'Use 3–30 letters, numbers, or underscores.';
    } else if (field.name === 'password_confirmation' && value && value !== form.elements.password.value) {
      message = 'Passwords do not match.';
    }

    field.classList.toggle('is-invalid', !!message);
    error.textContent = message;
    return !message;
  }

  for (const field of fields) {
    field.addEventListener('input', () => {
      if (field.hasAttribute('data-peerhive-name')) {
        const sanitized = sanitizePeerhiveName(field.value);
        if (sanitized !== field.value) {
          const before = field.value.slice(0, field.selectionStart ?? field.value.length);
          const offset = sanitizePeerhiveName(before).length;
          field.value = sanitized;
          try { field.setSelectionRange(offset, offset); } catch {}
        }
      }
      if (touched.has(field) || field.classList.contains('is-invalid')) validate(field);
      if (field.name === 'password') {
        const confirm = form.elements.password_confirmation;
        if (confirm && touched.has(confirm)) validate(confirm);
      }
    });
    field.addEventListener('blur', () => {
      touched.add(field);
      validate(field);
    });
  }

  form.addEventListener('submit', event => {
    let firstInvalid = null;
    for (const field of fields) {
      touched.add(field);
      if (!validate(field) && !firstInvalid) firstInvalid = field;
    }
    if (firstInvalid) {
      event.preventDefault();
      event.stopImmediatePropagation();
      firstInvalid.focus();
    }
  }, true);
}
