// shared SweetAlert options that match the active theme
function swapSwalOptions(overrides = {}) {
  const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

  return {
    showCancelButton: true,
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    focusCancel: true,
    buttonsStyling: false,
    background: isDark ? '#212529' : '#ffffff',
    color: isDark ? '#f8f9fa' : '#212529',
    // willOpen forcefully sets the background so Swal's own CSS cannot win
    willOpen: (popup) => {
      popup.style.setProperty('background-color', isDark ? '#212529' : '#ffffff', 'important');
      popup.style.setProperty('color', isDark ? '#f8f9fa' : '#212529', 'important');
    },
    customClass: {
      popup: `shadow border-0 rounded-4 ${isDark ? 'confirm-dialog-dark' : 'confirm-dialog-light'}`,
      title: 'h4 mb-2',
      htmlContainer: isDark ? 'text-light-emphasis' : 'text-muted',
      cancelButton: 'btn btn-outline-secondary',
      actions: 'gap-2',
    },
    ...overrides,
  };
}

// Keep the existing radio-driven tabs and request polling. Panels fade in via scoped CSS.
function showRequestTab(tab) {
  const ids = { sent: 'btnradio1', received: 'btnradio2', history: 'btnradio3' };
  if (!Object.prototype.hasOwnProperty.call(ids, tab)) tab = 'sent';
  const radio = document.getElementById(ids[tab]);
  if (radio) radio.checked = true;
  for (const [name, id] of Object.entries(ids)) {
    const label = document.querySelector(`label[for="${id}"]`);
    if (label) label.classList.toggle('is-active', name === tab);
    const panel = document.getElementById(`${name}Panel`);
    if (panel) panel.hidden = name !== tab;
  }
  document.querySelector('[data-ph-tabs-key="requests"]')?.dispatchEvent(new Event('ph:tabs-change'));
}

for (const [id, name] of Object.entries({ btnradio1: 'sent', btnradio2: 'received', btnradio3: 'history' })) {
  document.getElementById(id)?.addEventListener('change', event => {
    if (!event.target.checked) return;
    showRequestTab(name);
    const url = new URL(window.location.href);
    if (name === 'sent') url.searchParams.delete('tab');
    else url.searchParams.set('tab', name);
    window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
  });
}
const requestedTab = new URLSearchParams(window.location.search).get('tab');
showRequestTab(['sent', 'received', 'history'].includes(requestedTab) ? requestedTab : 'sent');

// Cancel / Unswap (sent tab)
$(document).on('submit', '.cancel-swap-form', function (e) {
  e.preventDefault();

  const $form = $(this);
  const $button = $form.find('.cancel-swap-btn');
  const $card = $form.closest('.swap-card');

  $button.prop('disabled', true).text('Cancelling...');

  $.ajax({
    url: $form.attr('action'),
    method: 'POST',
    data: $form.serialize(),
    headers: { Accept: 'application/json' },
    success: function (response) {
      $card.fadeOut(200, function () {
        $(this).remove();
        refreshSentCount();
      });

      if (window.toast) {
        window.toast('success', response.message || 'Swap request cancelled successfully.');
      }
    },
    error: function (xhr) {
      $button.prop('disabled', false).text('Cancel request');

      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Unable to cancel swap right now.');
      }
    }
  });
});

// Accept / Decline (received tab)
$(document).on('click', '.respond-swap-btn', async function () {
  const $btn = $(this);
  const swapId = $btn.data('swap-id');
  const action = $btn.data('action'); // 'accepted' or 'declined'
  const isAccepting = action === 'accepted';

  const result = await window.Swal.fire(swapSwalOptions({
    title: isAccepting ? 'Accept Request?' : 'Decline Request?',
    text: isAccepting
      ? 'You will be connected and redirected to Messages.'
      : 'Are you sure you want to decline this swap request?',
    icon: isAccepting ? 'question' : 'warning',
    confirmButtonText: isAccepting ? 'Yes, Accept!' : 'Yes, Decline',
    customClass: {
      ...swapSwalOptions().customClass,
      confirmButton: isAccepting ? 'btn btn-success' : 'btn btn-danger',
    },
  }));

  if (!result.isConfirmed) return;

  $btn.prop('disabled', true);
  $btn.siblings('.respond-swap-btn').prop('disabled', true);

  $.ajax({
    url: `/swap/${swapId}/respond`,
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      status: action,
    },
    headers: { Accept: 'application/json' },
    success: function (response) {
      const $card = $btn.closest('.received-swap-card');
      $card.attr('data-status', response.swap_status);
      $card.find('.ph-request-status').removeClass('ph-request-status--pending ph-request-status--accepted ph-request-status--declined').addClass(isAccepting ? 'ph-request-status--accepted' : 'ph-request-status--declined').text(isAccepting ? 'Accepted' : 'Declined');
      const $actionsArea = $btn.closest('.ph-request-actions');
      $actionsArea.empty();
      if (isAccepting && response.redirect) $('<a>', { href: response.redirect, class: 'btn btn-swap w-100', text: 'Open conversation' }).appendTo($actionsArea);
      else if (!isAccepting) $('<span>', { class: 'text-body-secondary small', text: 'No further action is required.' }).appendTo($actionsArea);
      window.refreshSidebarMessagesBadge?.();

      if (isAccepting) {
        window.Swal.fire(swapSwalOptions({
          title: 'Request Accepted! 🎉',
          text: 'Redirecting you to Messages...',
          icon: 'success',
          timer: 2000,
          timerProgressBar: true,
          showConfirmButton: false,
          showCancelButton: false,
        })).then(() => {
          window.location.href = response.redirect || '/messages';
        });
      } else {
        if (window.toast) {
          window.toast('info', 'Swap request declined.');
        }
      }
    },
    error: function (xhr) {
      $btn.prop('disabled', false);
      $btn.siblings('.respond-swap-btn').prop('disabled', false);

      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Something went wrong. Please try again.');
      }
    }
  });
});

function refreshSentCount() {
  // Re-render counts and the empty state from the server. Avoid mutating page headings.
  refreshSwapPanels();
}

$(document).on('click', '.remove-swap-btn', async function(e) {
    e.preventDefault(); 
    
    const $btn = $(this);
    const swapId = $btn.data('id'); 
    const $cardToRemove = $btn.closest('.col-md-6'); 
    
    const result = await window.confirmAction(
      'Are you sure you want to remove this swap connection?',
      'Remove Swap'
    );
    
    if (result.isConfirmed) {
      $.ajax({
        url: `/swap/${swapId}`,
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
          'Accept': 'application/json'
        },
        success: function (response) {
          window.toast('success', response.message || 'Swap removed successfully.');
          
          $cardToRemove.fadeOut(200, function() {
              $(this).remove();
              refreshSwapPanels();
          });
        },
        error: function (xhr) {
          const errorMessage = xhr.responseJSON?.message || 'Failed to remove swap.';
          window.toast('error', errorMessage);
        },
      });
    }
});

// Keep both users' request lists current without leaving the page.
let swapRefreshPending = false;
async function refreshSwapPanels() {
  if (document.hidden || swapRefreshPending || window.Swal?.isVisible() || $('.respond-swap-btn:disabled, .cancel-swap-btn:disabled').length) return;
  swapRefreshPending = true;
  try {
    const selectedTab = $('#btnradio1').is(':checked') ? 'sent' : ($('#btnradio2').is(':checked') ? 'received' : 'history');
    const response = await fetch(`/swap?refresh=1&tab=${selectedTab}`, { headers: { Accept: 'text/html' } });
    if (!response.ok || response.redirected) return;
    window.refreshSidebarMessagesBadge?.();
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    for (const id of ['sentPanel', 'receivedPanel', 'historyPanel']) {
      const replacement = page.getElementById(id);
      if (replacement) document.getElementById(id).innerHTML = replacement.innerHTML;
    }
    for (const id of ['btnradio1', 'btnradio2', 'btnradio3']) {
      const label = page.querySelector(`label[for="${id}"]`);
      if (label) document.querySelector(`label[for="${id}"]`).innerHTML = label.innerHTML;
    }
    document.querySelector('[data-ph-tabs-key="requests"]')?.dispatchEvent(new Event('ph:tabs-change'));
  } catch (error) { console.error('Unable to refresh requests', error); }
  finally { swapRefreshPending = false; }
}
setInterval(refreshSwapPanels, 15000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshSwapPanels(); });

$('#btnradio1').on('change', refreshSwapPanels);
$('#btnradio2, #btnradio3').on('change', refreshSwapPanels);
