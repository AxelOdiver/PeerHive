$(document).ready(function () {
  const $editForm = $('#communityForm');
  const $saveButton = $('#saveCommunityBtn');
  const $modalTitle = $('#communityModal .modal-title');
  const communityModal = document.getElementById('communityModal');
  let communityModalOpener = null;
  let formMode = 'create';

  communityModal?.addEventListener('hide.bs.modal', function () {
    if (communityModal.contains(document.activeElement)) document.activeElement.blur();
  });
  communityModal?.addEventListener('hidden.bs.modal', function () {
    communityModalOpener?.focus();
  });

  function clearValidationErrors() {
    $editForm.find('.is-invalid').removeClass('is-invalid');
    $editForm.find('.invalid-feedback[data-generated="true"]').remove();
  }

  function showValidationErrors(errors) {
    clearValidationErrors();
    Object.entries(errors).forEach(([fieldName, messages]) => {
      const $field = $editForm.find(`[name="${fieldName}"]`);
      if (!$field.length) return;

      $field.addClass('is-invalid');
      const $feedback = $('<div>', {
        class: 'invalid-feedback d-block',
        'data-generated': 'true',
        text: messages[0],
      });

      const $formFloating = $field.closest('.form-floating');
      if ($formFloating.length) {
        $formFloating.after($feedback);
      } else {
        $field.after($feedback);
      }
    });
  }

  function setModalMode(mode) {
    const isCreateMode = mode === 'create';
    $editForm.find('input:not([type="hidden"]), textarea').prop('readOnly', false);

    if (isCreateMode) {
      $modalTitle.text('Create Community');
      $saveButton.text('Create Community');
      $saveButton.removeClass('d-none');
      clearValidationErrors();
      return;
    }
  }

  $('.create-community-btn').on('click', function () {
    communityModalOpener = this;
    $editForm[0].reset();
    clearValidationErrors();
    setModalMode('create');
    formMode = 'create';
    $('#editCommunityId').val('');
    window.showModal('communityModal');
  });

  $editForm.find('input, textarea').on('input', function () {
    const $field = $(this);
    $field.removeClass('is-invalid');
    const $feedback = $field.closest('.form-floating').next('.invalid-feedback[data-generated="true"]');
    if ($feedback.length) $feedback.remove();
  });

  $editForm.on('submit', function (e) {
    e.preventDefault();

    const communityId = $('#editCommunityId').val();
    const formData = $(this).serialize();
    const url = formMode === 'create' ? '/community' : `/community/${communityId}`;
    const method = formMode === 'create' ? 'POST' : 'PUT';

    $.ajax({
      url: url,
      method: method,
      data: formData,
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
      },
      success: function (response) {
        window.toast('success', response.message || 'Community saved successfully.');
        clearValidationErrors();
        window.hideModal('communityModal');
        setTimeout(() => location.reload(), 1000);
      },
      error: function (xhr) {
        if (xhr.status === 422 && xhr.responseJSON?.errors) {
          showValidationErrors(xhr.responseJSON.errors);
          window.toast('error', 'Please fix the highlighted fields.');
          return;
        }
        const fieldMessages = xhr.responseJSON?.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : '';
        const safeMessage = xhr.status >= 400 && xhr.status < 500 ? xhr.responseJSON?.message : '';
        console.error('Community save failed', xhr.status, xhr.responseJSON || xhr.responseText);
        window.toast('error', fieldMessages || safeMessage || `Could not save community (error ${xhr.status || 'network'}). See the browser console for details.`);
      },
    });
  });

  // --- DELETE METHOD ---
  // --- DELETE METHOD ---
  $(document).on('click', '.delete-community-btn', async function(e) {
    e.preventDefault(); 
    
    // Capture the exact button that was clicked
    const $btn = $(this).closest('.delete-community-btn');
    const communityId = $btn.data('id'); 
    
    // Capture the exact Bootstrap column wrapping the card
    const $cardToRemove = $btn.closest('.col-12'); 
    const result = await window.confirmAction(
      'Are you sure you want to delete this community?',
      'Delete Community'
    );
    
    if (result.isConfirmed) {
      $.ajax({
        url: `/community/${communityId}`,
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        success: function (response) {
          window.toast('success', response.message || 'Community deleted successfully.');
          
          //Smoothly fade the card out, then delete it from the HTML!
          $cardToRemove.fadeOut(400, function() {
             $(this).remove();
          });
        },
        error: function (xhr) {
          const errorMessage = xhr.responseJSON?.message || 'Failed to delete community.';
          window.toast('error', errorMessage);
        },
      });
    }
  });

});
