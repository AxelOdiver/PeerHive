import './password-toggle.js';
import './password-requirements.js';

// Register page - password toggle and form submission
$(document).ready(function() {
  const $form = $('#form');
  const $submitButton = $('#registerSubmitBtn');
  let submitting = false;

  function setSubmitting(value) {
    submitting = value;
    $submitButton.prop('disabled', value);
    $form.attr('aria-busy', String(value));
    $('#registerSubmitSpinner').toggleClass('d-none', !value);
    $('#registerSubmitText').text(value ? 'Creating account...' : 'Create account');
  }

  // Submit form logic
  function clearErrors() {
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('[data-error-for]').text('');
    $('#registerError').addClass('d-none').text('');
  }

  $form.on('submit', function(e) {
    e.preventDefault();
    if (submitting) return;
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
        sessionStorage.setItem('toast', JSON.stringify({
          type: 'success',
          message: response.message ?? 'Registration successful!',
        }));

        window.location.href = response.redirect ?? '/dashboard';
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

        $('#registerError').removeClass('d-none').text('Sorry, something went wrong. Please try again.');
      }
    });
  });
});
