// Helper: build shared SweetAlert options that match the active theme
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

// Tab switching
$('#btnradio1').on('change', function () {
  $('#sentPanel').show();
  $('#receivedPanel').hide();
});

$('#btnradio2').on('change', function () {
  $('#sentPanel').hide();
  $('#receivedPanel').show();
});

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
      $button.prop('disabled', false).text('Unswap');

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
    title: isAccepting ? 'Accept Swap?' : 'Decline Swap?',
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
      const $actionsArea = $btn.closest('.d-flex.gap-2');

      const badgeClass = isAccepting ? 'text-bg-success' : 'text-bg-danger';
      const badgeLabel = isAccepting ? 'Accepted' : 'Declined';
      const newHtml = `
        <div class="w-100 text-end">
          <small class="text-muted d-block">Status</small>
          <span class="badge fs-6 px-3 py-2 ${badgeClass}">${badgeLabel}</span>
          ${isAccepting ? `<div class="mt-2"><a href="/messages" class="btn btn-sm btn-success rounded-pill"><i class="bi bi-chat-dots me-1"></i> Go to Messages</a></div>` : ''}
        </div>`;
      $actionsArea.replaceWith(newHtml);

      if (isAccepting) {
        window.Swal.fire(swapSwalOptions({
          title: 'Swap Accepted! 🎉',
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
  const $cards = $('.swap-card');

  if ($cards.length === 0) {
    $('#swapList').remove();
    $('h2.fw-bold').first().remove();

    if (!$('#swapEmptyState').length) {
      $('#sentPanel').append(`
        <div id="swapEmptyState">
          <h2 class="fw-bold mb-4">Swap Dashboard</h2>
          <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
            <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">You haven't selected any peers to swap with.</h5>
            <p class="text-muted mb-0">Go back to the dashboard and click "Swap" on a student card!</p>
            <a href="/dashboard" class="btn btn-primary mt-3">Find Peers</a>
          </div>
        </div>
      `);
    }
  }
}
