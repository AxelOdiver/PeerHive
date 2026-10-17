let requestVersion = 0;
let peerSubjects = [], mySubjects = [], loading = false, sending = false;

export function canSendLearningRequest(type, peerCount, ownCount) {
  return peerCount > 0 && (type === 'learning' || (type === 'skill_swap' && ownCount > 0));
}

function renderType() {
  const swapping = $('#learningRequestType').val() === 'skill_swap';
  $('#offeredSubjectWrap').prop('hidden', !swapping);
  $('#offeredSubject').prop('disabled', !swapping || loading || sending).prop('required', swapping);
  $('#requestedSubject').prop('disabled', loading || sending);
  $('#learningRequestType').prop('disabled', sending);
  $('#learningRequestHint').text(swapping
    ? 'Both partners must be verified for the subject they offer to teach.'
    : 'You do not need a teaching qualification to learn. Your tutor must be verified for the selected subject.');
  $('#sendSwapRequestBtn').prop('disabled', loading || sending || !canSendLearningRequest($('#learningRequestType').val(), peerSubjects.length, mySubjects.length));
  if (!loading && !sending) {
    $('#learningRequestStatus').text(!peerSubjects.length ? 'This peer has no approved teaching subjects yet. Choose a verified teaching partner.'
      : swapping && !mySubjects.length ? 'You need Teaching Verification to offer a skill swap. You can still choose Request learning help.' : '');
  }
}

async function loadOptions() {
  const version = ++requestVersion, id = $('#swapUserId').val();
  loading = true; peerSubjects = []; mySubjects = [];
  $('#retryLearningOptions').prop('hidden', true);
  $('#requestedSubject, #offeredSubject').empty();
  renderType();
  $('#learningRequestStatus').text('Loading approved subjects…');
  try {
    const response = await fetch('/swap/options/' + encodeURIComponent(id), {headers: {Accept: 'application/json'}});
    if (!response.ok) throw new Error('Unable to load');
    const data = await response.json();
    if (version !== requestVersion) return;
    peerSubjects = data.peer_subjects; mySubjects = data.my_subjects;
    $('#learningPeerName').text(data.peer_name);
    for (const [selector, subjects] of [['#requestedSubject', peerSubjects], ['#offeredSubject', mySubjects]]) {
      $(selector).append(new Option('Choose a subject', ''));
      subjects.forEach(subject => $(selector).append(new Option(subject, subject)));
    }
    loading = false; renderType();
  } catch {
    if (version !== requestVersion) return;
    loading = false; renderType();
    $('#learningRequestStatus').text('Could not load approved subjects. Please retry.');
    $('#retryLearningOptions').prop('hidden', false);
  }
}

$(document).on('click', '.open-swap-modal', function () {
  if (sending) return;
  $('#swapRequestForm')[0].reset();
  $('#swapUserId').val($(this).data('id'));
  $('#learningPeerName').text('');
  window.showModal('swapModal');
  loadOptions();
});
$(document).on('change', '#learningRequestType', renderType);
$(document).on('click', '#retryLearningOptions', loadOptions);
$(document).on('hidden.bs.modal', '#swapModal', () => { ++requestVersion; });
$(document).on('submit', '#swapRequestForm', function (event) {
  event.preventDefault();
  if (sending || loading || !canSendLearningRequest($('#learningRequestType').val(), peerSubjects.length, mySubjects.length)) return;
  if (!this.reportValidity()) return;
  const payload = $(this).serialize();
  sending = true; renderType();
  $('#sendSwapRequestBtn').text('Sending…');
  $.ajax({
    url: '/swap/add', method: 'POST', data: payload,
    headers: {Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
    success: response => {
      window.hideModal('swapModal');
      window.toast?.('success', 'Request sent successfully.');
      window.location.assign(response.redirect || '/swap');
    },
    error: xhr => {
      sending = false; renderType();
      const errors = xhr.responseJSON?.errors;
      $('#learningRequestStatus').text(errors ? Object.values(errors).flat().join(' ') : xhr.responseJSON?.message || 'Could not send your request. Please try again.');
    },
    complete: () => { sending = false; $('#sendSwapRequestBtn').text('Send Request'); renderTypeAfterSend(); },
  });
});

// Preserve validation errors while restoring controls after a failed send.
function renderTypeAfterSend() {
  const status = $('#learningRequestStatus').text();
  renderType();
  $('#learningRequestStatus').text(status);
}
