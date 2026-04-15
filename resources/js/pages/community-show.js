$(document).ready(function () {
    
    // ENTER EDIT MODE
    $('#editDescriptionBtn').on('click', function() {
        $('#descriptionViewMode').hide();       
        $('#descriptionEditMode').show();       
        $(this).hide();                         
    });
    
    // CANCEL EDIT
    $('#cancelEditBtn').on('click', function() {
        const currentText = $('#descriptionText').text().trim();
        $('#descriptionInput').val(currentText === 'No description yet.' ? '' : currentText);
        
        $('#descriptionEditMode').hide();       
        $('#descriptionViewMode').show();       
        $('#editDescriptionBtn').show();        
    });
    
    // SAVE EDIT 
    $('#saveEditBtn').on('click', function() {
        const communityId = $('#editDescriptionBtn').data('id');
        const newDescription = $('#descriptionInput').val().trim();
        const $saveBtn = $(this);
        
        if (!newDescription) {
            window.toast('error', 'Description cannot be empty.');
            return;
        }
        
        $saveBtn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: `/community/${communityId}`,
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            data: {
                description: newDescription
            },
            success: function (response) {
                $('#descriptionText').text(newDescription);
                
                $('#descriptionEditMode').hide();
                $('#descriptionViewMode').show();
                $('#editDescriptionBtn').show();
                
                window.toast('success', 'Description updated successfully!');
            },
            error: function (xhr) {
                window.toast('error', 'Failed to update description.');
                console.error(xhr.responseJSON?.message || 'Error occurred.');
            },
            complete: function() {
                $saveBtn.prop('disabled', false).text('Save');
            }
        });
    });
    
    $('#editTagsForm').on('submit', function(e) {
        e.preventDefault();
        
        let $form = $(this);
        let communityId = $('#editTagsCommunityId').val();
        let formData = $form.serialize(); 
        let $btn = $('#saveTagsBtn');
        
        // Show a loading state on the button
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...');
        
        // Clear old validation errors
        clearErrors($form);
        
        $.ajax({
            url: `/community/${communityId}/tags`,
            type: 'POST', 
            data: formData,
            dataType: 'json',
            headers: {
                'Accept': 'application/json'
            },
            success: function(response) {
                // Close modal and show success toast
                hideModal('editTagsModal');
                toast('success', response.message);
                
                // Refresh the page after 1.5s to show the newly saved tags
                setTimeout(() => {
                    location.reload();
                }, 1500);
            },
            error: function(xhr) {
                // Reset button if there is an error
                $btn.prop('disabled', false).text('Save Tags'); 
                
                if (xhr.status === 422) {
                    showFormErrors($form, xhr.responseJSON.errors);
                } else {
                    toast('error', xhr.responseJSON?.message || 'Something went wrong while saving tags.');
                }
            }
        });
    });
    
});