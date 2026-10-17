export function hasReachedPolicyEnd(element) {
  return element.clientHeight > 0 && element.scrollTop + element.clientHeight >= element.scrollHeight - 2;
}

export function initPolicyAcceptance(doc, Modal) {
  const checkbox = doc.getElementById('accept_policies');
  const modal = doc.getElementById('policyAcceptanceModal');
  const content = doc.getElementById('policyScrollContent');
  const accept = doc.getElementById('acceptPoliciesBtn');
  const hint = doc.getElementById('policyScrollHint');
  let accepted = false;
  let reachedEnd = false;
  const dialog = Modal.getOrCreateInstance(modal);

  function update() {
    reachedEnd = reachedEnd || hasReachedPolicyEnd(content);
    accept.disabled = !reachedEnd;
    accept.classList.toggle('btn-primary', reachedEnd);
    accept.classList.toggle('btn-secondary', !reachedEnd);
    hint.textContent = reachedEnd ? 'You can now accept the Terms and acknowledge the Privacy Policy.' : 'Scroll to the end to enable Accept.';
  }
  function open() {
    reachedEnd = false;
    accept.disabled = true;
    accept.classList.remove('btn-primary');
    accept.classList.add('btn-secondary');
    hint.textContent = 'Scroll to the end to enable Accept.';
    dialog.show();
  }
  checkbox.addEventListener('click', event => {
    if (accepted) {
      accepted = false; // A subsequent click can revoke acceptance normally.
      return;
    }
    event.preventDefault();
    open();
  });
  checkbox.addEventListener('invalid', event => {
    event.preventDefault();
    open();
  });
  for (const id of ['reviewTermsLink', 'reviewPrivacyLink']) {
    doc.getElementById(id).addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      open();
    });
  }
  modal.addEventListener('shown.bs.modal', () => {
    content.scrollTop = 0;
    update();
    content.focus();
  });
  content.addEventListener('scroll', update);
  doc.defaultView.addEventListener('resize', () => {
    if (modal.classList.contains('show')) update();
  });
  accept.addEventListener('click', () => {
    if (!reachedEnd || accept.disabled) return;
    accepted = true;
    checkbox.checked = true;
    checkbox.classList.remove('is-invalid');
    doc.querySelector('[data-error-for="accept_policies"]').textContent = '';
    dialog.hide();
    checkbox.focus();
  });
  modal.addEventListener('hidden.bs.modal', () => {
    if (!accepted) checkbox.checked = false;
    checkbox.focus();
  });
}
