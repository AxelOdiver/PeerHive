function toggleSwapSections(section) {
  $('[data-swap-section]').addClass('d-none');
  $(`[data-swap-section="${section}"]`).removeClass('d-none');
}

$(document).on('change', 'input[name="swapSection"]', function () {
  const section = this.id === 'swapSectionReceive' ? 'receive' : 'send';
  toggleSwapSections(section);
});

$(function () {
  toggleSwapSections($('input[name="swapSection"]:checked').attr('id') === 'swapSectionReceive' ? 'receive' : 'send');
});

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
    headers: {
      Accept: 'application/json'
    },
    success: function (response) {
      $card.fadeOut(200, function () {
        $(this).remove();
        refreshSwapPage();
      });

      if (window.toast) {
        window.toast('success', response.message || 'Swap request cancelled successfully.');
      }
    },
    error: function (xhr) {
      $button.prop('disabled', false).text('Cancel');

      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Unable to cancel swap right now.');
      }
    }
  });
});

function refreshSwapPage() {
  const $cards = $('.swap-card');
  const count = $cards.length;
  const $count = $('#swapCount');
  const $detailsCard = $('#swapDetailsCard');
  const $page = $('#swapPage');

  if ($count.length) {
    $count.text(count);
  }

  if (count === 0) {
    $('#swapHeading').remove();
    $('#swapList').remove();
    $detailsCard.remove();

    if (!$('#swapEmptyState').length) {
      $page.append(`
        <div id="swapEmptyState">
          <h2 class="fw-bold mb-4">Swap Dashboard</h2>
          <div class="alert alert-light text-center py-5 rounded-4 shadow-sm">
            <i class="bi bi-people fs-1 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">You haven't selected any peers to swap with.</h5>
            <p class="text-muted mb-0">Go back to the dashboard, check the boxes on the student cards, and click "Swap Selected"!</p>
            <a href="/dashboard" class="btn btn-primary mt-3">Find Peers</a>
          </div>
        </div>
      `);
    }
  }
}
