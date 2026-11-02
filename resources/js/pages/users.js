$(document).ready(async function () {
  const table = $('#usersTable');
  const $tableShell = $('#usersTableShell');
  const $editForm = $('#userForm');
  const $saveButton = $('#sendEditUserBtn');
  const $passwordFields = $('#passwordFields');
  const $passwordHint = $('.password-hint');
  const $confirmPasswordWrap = $('#confirmPasswordWrap');
  const $modalTitle = $('#userModalLabel');
  const usersDataUrl = '/users/data';
  let formMode = 'create';

  if (!table.length) {
    return;
  }

  function clearValidationErrors() {
    $editForm.find('.is-invalid').removeClass('is-invalid');
    $editForm.find('.invalid-feedback[data-generated="true"]').remove();
  }

  function showValidationErrors(errors) {
    clearValidationErrors();

    Object.entries(errors).forEach(([fieldName, messages]) => {
      const $field = $editForm.find(`[name="${fieldName}"]`);

      if (!$field.length) {
        return;
      }

      if (fieldName === 'password_confirmation') {
        $confirmPasswordWrap.addClass('is-visible');
      }

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
    const isViewMode = mode === 'view';
    const isCreateMode = mode === 'create';

    $editForm.find('input:not([type="hidden"])').prop('readOnly', isViewMode);

    if (isViewMode) {
      $modalTitle.text('View User');
      $saveButton.addClass('d-none');
      $passwordFields.addClass('d-none');
      $confirmPasswordWrap.removeClass('is-visible');
      $passwordHint.show();
      $('#editUserPassword, #editUserPasswordConfirm').val('');
      clearValidationErrors();
      return;
    }

    if (isCreateMode) {
      $modalTitle.text('Create User');
      $saveButton.text('Create User');
      $saveButton.removeClass('d-none');
      $passwordFields.removeClass('d-none');
      $confirmPasswordWrap.addClass('is-visible');
      $passwordHint.hide();
      clearValidationErrors();
      return;
    }

    $modalTitle.text('Edit User');
    $saveButton.text('Save Changes');
    $saveButton.removeClass('d-none');
    $passwordFields.removeClass('d-none');
    $confirmPasswordWrap.removeClass('is-visible');
    $passwordHint.show();
  }

  const [{ default: DataTable }, { default: JSZip }, { default: pdfMake }] = await Promise.all([
    import('datatables.net-bs5'),
    import('jszip'),
    import('pdfmake/build/pdfmake'),
  ]);

  await Promise.all([
    import('datatables.net-buttons-bs5'),
    import('datatables.net-buttons/js/buttons.html5.mjs'),
    import('datatables.net-buttons/js/buttons.print.mjs'),
    import('pdfmake/build/vfs_fonts'),
  ]);

  window.JSZip = JSZip;
  window.pdfMake = pdfMake;
  DataTable.Buttons.jszip(JSZip);
  DataTable.Buttons.pdfMake(pdfMake);

  const usersTable = new DataTable(table[0], {
    ajax: {
      url: usersDataUrl,
      dataSrc: 'data',
    },
    columns: [
      { data: 'name', render: DataTable.render.text() },
      { data: 'email', render: DataTable.render.text() },
      { data: 'joined' },
      {
        data: null,
        orderable: false,
        searchable: false,
        className: 'text-center',
        render: function (data, type, row) {
          if (type !== 'display') {
            return row.id;
          }

          return `
            <a href="#" class="btn btn-sm btn-primary mb-1 view-user" data-user-id="${row.id}" title="View">
              <i class="bi bi-eye"></i>
            </a>
            <a href="#" class="btn btn-sm btn-secondary mb-1 edit-user" data-user-id="${row.id}" title='Edit'>
              <i class="bi bi-pencil"></i>
            </a>
            <a href="#" class="btn btn-sm btn-danger mb-1 delete-user" data-user-id="${row.id}" title='Delete'>
              <i class="bi bi-trash"></i>
            </a>
          `;
        },
      },
    ],
    lengthMenu: [
      [10, 25, 50, -1],
      [10, 25, 50, 'All']
    ],
    order: [[0, 'asc']],
    initComplete: function () {
      $tableShell.removeClass('is-loading');
    },
    layout: {
      topStart: {
        buttons: [
          {
            extend: 'copyHtml5',
            text: ' Copy',
            className: 'btn btn-secondary bi bi-clipboard',
          },
          {
            extend: 'csvHtml5',
            text: ' CSV',
            className: 'btn btn-secondary bi bi-file-earmark-spreadsheet',
          },
          {
            extend: 'excelHtml5',
            text: ' Excel',
            className: 'btn btn-secondary bi bi-file-earmark-excel',
          },
          {
            extend: 'pdfHtml5',
            text: ' PDF',
            className: 'btn btn-secondary bi bi-file-earmark-pdf',
          },
          {
            extend: 'print',
            text: ' Print',
            className: 'btn btn-secondary bi bi-printer',
          },
        ],
      },
      topEnd: 'search',
      bottomStart: ['pageLength', 'info'],
      bottomEnd: 'paging',
    },
  });

  $('.create-user').on('click', function () {
    $('#userForm')[0].reset();
    clearValidationErrors();
    setModalMode('create');
    formMode = 'create';
    $('#editUserId').val('');
    showModal('userModal');
  });

  table.on('click', '.view-user', function (e) {
    e.preventDefault();

    $('#userForm')[0].reset();
    clearValidationErrors();
    setModalMode('view');
    const userId = $(this).data('user-id');
    
    $.ajax({
      url: `/users/${userId}`,
      method: 'GET',
      success: function (response) {
        $('#editUserId').val(response.user.id);
        $('[name="first_name"]').val(response.user.first_name);
        $('[name="middle_name"]').val(response.user.middle_name);
        $('[name="last_name"]').val(response.user.last_name);
        $('[name="email"]').val(response.user.email);

        showModal('userModal');
      },
      error: function (xhr) {
        window.toast('error', 'Failed to load user details.');
        console.error(xhr.responseJSON?.message || 'An error occurred while fetching user details.');
      },
    });
  });

  table.on('click', '.edit-user', function (e) {
    e.preventDefault();
    formMode = 'edit';

    $('#userForm')[0].reset();
    clearValidationErrors();
    setModalMode('edit');
    const userId = $(this).data('user-id');

    $.ajax({
      url: `/users/${userId}`,
      method: 'GET',
      success: function (response) {
        $('#editUserId').val(response.user.id);
        $('[name="first_name"]').val(response.user.first_name);
        $('[name="middle_name"]').val(response.user.middle_name);
        $('[name="last_name"]').val(response.user.last_name);
        $('[name="email"]').val(response.user.email);

        showModal('userModal');
      },
      error: function (xhr) {
        window.toast('error', 'Failed to load user details.');
        console.error(xhr.responseJSON?.message || 'An error occurred while fetching user details.');
      },
    });
  });

  table.on('click', '.delete-user', async function (e) {
    const result = await confirmAction(
      'This user will be deleted permanently.',
      'Delete User'
    );

    if (result.isConfirmed) {
      const userId = $(this).data('user-id');

      $.ajax({
        url: `/users/${userId}`,
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        success: function (response) {
          window.toast('success', response.message || 'User deleted successfully.');
          usersTable.ajax.reload(null, false);
        },
        error: function (xhr) {
          window.toast('error', 'Failed to delete user.');
          console.error(xhr.responseJSON?.message || 'An error occurred while deleting the user.');
        },
      });
    }
  });

  $editForm.find('input').on('input', function () {
    const $field = $(this);
    $field.removeClass('is-invalid');

    const $feedback = $field.closest('.form-floating').next('.invalid-feedback[data-generated="true"]');
    if ($feedback.length) {
      $feedback.remove();
    }
  });

  $('[name="password"]').on('input', function () {
    if ($(this).prop('readOnly')) {
      return;
    }

    const value = $(this).val();
    if (value.length > 0) {
      $('#confirmPasswordWrap').addClass('is-visible');
    } else { 
      $('#confirmPasswordWrap').removeClass('is-visible');
    }
  });

  $('#userForm').on('submit', function (e) {
    e.preventDefault();

    const userId = $('#editUserId').val();
    const formData = $(this).serialize();

    const url = formMode === 'create' ? '/users' : `/users/${userId}`;
    const method = formMode === 'create' ? 'POST' : 'PUT';

    $.ajax({
      url: url,
      method: method,
      data: formData,
      success: function (response) {
        window.toast('success', response.message || 'User details saved successfully.');
        clearValidationErrors();
        hideModal('userModal');
        usersTable.ajax.reload(null, false);
      },
      error: function (xhr) {
        if (xhr.status === 422 && xhr.responseJSON?.errors) {
          showValidationErrors(xhr.responseJSON.errors);
          window.toast('error', 'Please fix the highlighted fields.');
          return;
        }

        window.toast('error', 'Failed to save user details.');
        console.error(xhr.responseJSON?.message || 'An error occurred while updating user details.');
      },
    });
  });

});
