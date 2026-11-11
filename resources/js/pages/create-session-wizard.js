// PeerHive v4.7.2: progressive form UI. The Laravel POST and server validation remain unchanged.
const form = document.getElementById('phSessionWizardForm');

if (form) {
  const panels = [...form.querySelectorAll('[data-ph-wizard-panel]')];
  const indicators = [...document.querySelectorAll('[data-ph-wizard-indicator]')];
  const back = form.querySelector('[data-ph-wizard-back]');
  const next = form.querySelector('[data-ph-wizard-next]');
  const submit = form.querySelector('[data-ph-wizard-submit]');
  const counter = document.querySelector('[data-ph-wizard-counter]');
  const status = form.querySelector('[data-ph-wizard-status]');
  const total = panels.length;
  const subject = form.querySelector('[data-ph-session-subject]');
  const subjectHint = form.querySelector('[data-ph-subject-hint]');
  const tutorHelp = form.querySelector('[data-ph-subject-tutor-help]');
  const formatButtons = [...form.querySelectorAll('input[name="format"]')];
  const verifiedOptionCount = subject?.querySelectorAll('[data-ph-subject-role="tutor"]').length || 0;

  function syncSubjects() {
    if (!subject) return;
    const role = form.querySelector('input[name="format"]:checked')?.value === 'tutor' ? 'tutor' : 'peer';
    const oldSelection = subject.value;
    subject.querySelectorAll('[data-ph-subject-group]').forEach(group => {
      const enabled = group.dataset.phSubjectGroup === role;
      group.disabled = !enabled;
      group.hidden = !enabled;
      group.querySelectorAll('option').forEach(option => { option.disabled = !enabled; option.hidden = !enabled; });
    });
    const chosen = [...subject.options].find(option => option.value === oldSelection && !option.disabled);
    if (!chosen || !oldSelection) subject.value = '';
    else subject.selectedIndex = chosen.index;
    if (subjectHint) subjectHint.textContent = role === 'tutor'
      ? (verifiedOptionCount ? 'Only approved teaching subjects are available.' : 'No approved teaching subjects yet.')
      : 'Choose an approved subject, learning topic, or a subject from your communities.';
    if (tutorHelp) tutorHelp.hidden = role !== 'tutor';
    updateReview();
  }
  let currentStep = 1;

  const selectedText = id => {
    const element = form.querySelector(`#${id}`);
    if (!element) return '';
    return element.tagName === 'SELECT' ? element.selectedOptions[0]?.textContent?.trim() || '' : element.value.trim();
  };

  function updateReview() {
    const data = {
      title: selectedText('title') || 'Not entered',
      community: selectedText('community_id') || 'Not selected',
      subject: `${form.querySelector('input[name="format"]:checked')?.value === 'tutor' ? 'Tutor-led' : 'Peer study'} · ${selectedText('subject') || 'No subject'}`,
      schedule: [selectedText('starts_at')?.replace('T', ' '), selectedText('timezone')].filter(Boolean).join(' · ') || 'Not selected',
      capacity: `${selectedText('duration') || '?'} min · ${selectedText('capacity') || '?'} participants`,
      method: selectedText('meeting_method') === 'in_person' ? 'In person' : 'Online',
    };
    for (const [key, value] of Object.entries(data)) {
      const target = form.querySelector(`[data-ph-review="${key}"]`);
      if (target) target.textContent = value;
    }
    const meetingLabel = form.querySelector('[data-ph-meeting-label]');
    if (meetingLabel) meetingLabel.textContent = data.method === 'Online' ? 'Meeting link' : 'Meeting venue';
  }

  function showStep(step, focus = false) {
    currentStep = Math.min(total, Math.max(1, step));
    panels.forEach((panel, index) => {
      panel.hidden = index !== currentStep - 1;
    });
    indicators.forEach((indicator, index) => {
      indicator.classList.toggle('is-active', index === currentStep - 1);
      indicator.classList.toggle('is-complete', index < currentStep - 1);
      indicator.setAttribute('aria-current', index === currentStep - 1 ? 'step' : 'false');
    });
    back.hidden = currentStep === 1;
    next.hidden = currentStep === total;
    submit.hidden = currentStep !== total;
    if (counter) counter.textContent = `Step ${currentStep} of ${total}`;
    if (status) status.textContent = currentStep === total ? 'Review your details, then create the session.' : 'Your entries stay in place when moving between steps.';
    if (currentStep === total) updateReview();
    if (focus) panels[currentStep - 1].querySelector('h3')?.focus?.();
  }

  function validateStep(step) {
    const inputs = [...panels[step - 1].querySelectorAll('input, select, textarea')].filter(element => !element.disabled);
    const invalid = inputs.find(element => !element.checkValidity());
    if (!invalid) return true;
    showStep(step);
    invalid.focus();
    invalid.reportValidity();
    return false;
  }

  // With JavaScript disabled, all panels remain visible and the browser validates the normal form.
  // With JavaScript enabled, validate the visible panel before advancing, and every panel on submit.
  form.noValidate = true;
  form.dataset.phWizardReady = 'true';
  next.addEventListener('click', () => {
    if (validateStep(currentStep)) showStep(currentStep + 1);
  });
  back.addEventListener('click', () => showStep(currentStep - 1));
  formatButtons.forEach(button => button.addEventListener('change', syncSubjects));
  syncSubjects();
  form.addEventListener('input', updateReview);
  form.addEventListener('change', updateReview);
  form.addEventListener('submit', event => {
    for (let step = 1; step <= total; step += 1) {
      if (!validateStep(step)) {
        event.preventDefault();
        return;
      }
    }
    if (submit.disabled) {
      event.preventDefault();
      return;
    }
    submit.disabled = true;
    submit.textContent = 'Creating session…';
  });
  const initial = Number.parseInt(form.dataset.phInitialStep || '1', 10);
  showStep(Number.isInteger(initial) ? initial : 1);
  const serverInvalid = form.querySelector('.is-invalid');
  if (serverInvalid) {
    const containingPanel = serverInvalid.closest('[data-ph-wizard-panel]');
    const errorStep = Number(containingPanel?.dataset.phWizardPanel || initial || 1);
    showStep(errorStep);
    requestAnimationFrame(() => serverInvalid.focus({preventScroll: true}));
  }
}
