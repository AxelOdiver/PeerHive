$(document).on('click', '.like-profile-btn', function () {
  const $btn = $(this);
  const userId = $btn.data('id');

  $.ajax({
    url: '/likes/toggle',
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      user_id: userId
    },
    success: function (res) {
      const liked = res.action === 'liked';

      $btn.toggleClass('btn-danger', liked).toggleClass('btn-outline-danger', !liked);
      $btn.find('i').toggleClass('bi-heart-fill', liked).toggleClass('bi-heart', !liked);
      $btn.find('.like-label').text(liked ? 'Unlike' : 'Like');
      $('#profile-like-count').text(res.like_count);
    },
    error: function (xhr) {
      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Could not update like.');
      }
    }
  });
});

$(document).on('click', '.fav-profile-btn', function () {
  const $btn = $(this);
  const userId = $btn.data('id');

  $.ajax({
    url: '/favorite/toggle',
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      item_id: userId
    },
    success: function (res) {
      const saved = res.action === 'swapped';

      $btn.toggleClass('btn-warning', saved).toggleClass('btn-outline-warning', !saved);
      $btn.find('i').toggleClass('bi-bookmark-fill', saved).toggleClass('bi-bookmark', !saved);
      $btn.find('.fav-label').text(saved ? 'Saved' : 'Save');

      if (window.toast) {
        window.toast('success', saved ? 'Added to favorites!' : 'Removed from favorites.');
      }
    },
    error: function (xhr) {
      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Could not update favorite.');
      }
    }
  });
});

$(document).on('click', '.open-swap-modal', function () {
  $('#swapUserId').val($(this).data('id'));
  $('#swapRequestMessage').val('');
  showModal('swapModal');
});

$(document).on('submit', '#swapRequestForm', function (e) {
  e.preventDefault();

  const $form = $(this);
  const $submitButton = $('#sendSwapRequestBtn');

  $submitButton.prop('disabled', true).text('Sending...');

  $.ajax({
    url: '/swap/add',
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      user_id: $('#swapUserId').val(),
      message: $('#swapRequestMessage').val()
    },
    success: function (response) {
      hideModal('swapModal');
      $form[0].reset();
      $('#swapUserId').val('');

      if (window.toast) {
        window.toast('success', 'Swap request sent successfully');
      }

      window.location.assign(response.redirect || '/swap');
    },
    error: function (xhr) {
      $submitButton.prop('disabled', false).text('Send Request');

      if (xhr.status === 403 && window.Swal) {
        hideModal('swapModal');

        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

        window.Swal.fire({
          title: 'Verification Required',
          text: xhr.responseJSON?.message || 'You must be verified to swap.',
          icon: 'warning',
          confirmButtonText: 'Understood',
          background: isDark ? '#212529' : '#ffffff',
          color: isDark ? '#f8f9fa' : '#212529',
          customClass: {
            popup: 'shadow-lg border-0 rounded-4',
            confirmButton: 'btn btn-primary px-4'
          }
        });

        return;
      }

      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Unable to send swap request right now');
      }
    }
  });
});
