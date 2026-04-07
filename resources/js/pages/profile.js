// Profile edit page - form submission
$(document).ready(function() {
  const $profileForm = $('#profileForm');

  function clearErrors() {
    $profileForm.find('.is-invalid').removeClass('is-invalid');
    $profileForm.find('[data-error-for]').text('');
    $('#registerError').addClass('d-none').text('');
  }

  $profileForm.on('submit', function(e) {
    e.preventDefault();
    clearErrors();

    $.ajax({
      url: $profileForm.attr('action'),
      method: 'POST',
      data: $profileForm.serialize(),
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        toast('success', 'Profile updated successfully!');
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      },
      error: function(xhr) {
        if (xhr.status === 422) {
          const errors = xhr.responseJSON?.errors || {};
          for (const field in errors) {
            const msg = errors[field]?.[0] ?? 'Invalid input';
            const $input = $profileForm.find(`[name="${field}"]`);
            $input.addClass('is-invalid');
            $profileForm.find(`[data-error-for="${field}"]`).text(msg);
          }
          return;
        }

        toast('error', 'Sorry, something went wrong. Please try again.');
      }
    });
  });
});
