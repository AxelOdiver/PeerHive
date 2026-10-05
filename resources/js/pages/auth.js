import './password-toggle.js';
// Login page - password toggle and form submission
$(document).ready(function() {
  const $form = $('#form');
  const $submitButton = $('#loginSubmitBtn');
  const $submitSpinner = $('#loginSubmitSpinner');
  const $submitText = $('#loginSubmitText');

  // Submit form logic
  function clearErrors() {
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('[data-error-for]').text('');
    $('#loginError').addClass('d-none').text('');
  }

  function setSubmitting(isSubmitting) {
    $submitButton.prop('disabled', isSubmitting);
    $submitSpinner.toggleClass('d-none', !isSubmitting);
    $submitText.text(isSubmitting ? 'Logging in...' : 'Log in');
  }

  $form.on('submit', function(e) {
    e.preventDefault();
    clearErrors();
    setSubmitting(true);

    $.ajax({
      url: $form.attr('action'),
      method: 'POST',
      data: $form.serialize(),
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      success: function(response) {
        window.location.assign(response.redirect);
      },
      error: function(xhr) {
        setSubmitting(false);

        if (xhr.status === 422) {
          const errors = xhr.responseJSON?.errors || {};
          for (const field in errors) {
            const msg = errors[field]?.[0] ?? 'Invalid input';
            const $input = $form.find(`[name="${field}"]`);
            $input.addClass('is-invalid');
            $form.find(`[data-error-for="${field}"]`).text(msg);
          }
          return;
        }

        const msg = xhr.responseJSON?.message ?? 'Login failed';
        $('#loginError').removeClass('d-none').text(msg);
      }
    });
  });
});
