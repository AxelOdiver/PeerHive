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
    
    // Create Post AJAX Logic
    $('#createPostForm').on('submit', function(e) {
        e.preventDefault();
        
        let $form = $(this);
        let communityId = $('#communityIdForPost').val();
        let formData = $form.serialize(); 
        let $btn = $('#savePostBtn');
        
        // Loading state
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Posting...');
        
        clearErrors($form);
        
        $.ajax({
            url: `/community/${communityId}/posts`,
            type: 'POST', 
            data: formData,
            dataType: 'json',
            headers: { 'Accept': 'application/json' },
            success: function(response) {
                hideModal('createPostModal');
                toast('success', response.message);
                
                // Clear the form inputs for the next time it opens
                $form[0].reset();
                
                // Refresh to show the new post in the feed
                setTimeout(() => {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Post'); 
                
                if (xhr.status === 422) {
                    showFormErrors($form, xhr.responseJSON.errors);
                } else {
                    toast('error', xhr.responseJSON?.message || 'Something went wrong while posting.');
                }
            }
        });
    });
    
    // --- DELETE POST OR COMMENT METHOD ---
    $(document).on('click', '.delete-post-btn, .delete-comment-btn', async function(e) {
        e.preventDefault(); 
        
        const $btn = $(this).closest('button');
        const url = $btn.data('url');
        
        // Determine if we clicked a post or a comment for the alert text
        const itemType = $btn.hasClass('delete-post-btn') ? 'post' : 'comment';
        
        const result = await window.confirmAction(
            `Are you sure you want to delete this ${itemType}? This action cannot be undone.`,
            `Delete ${itemType.charAt(0).toUpperCase() + itemType.slice(1)}?`
        );
        
        if (result.isConfirmed) {
            $.ajax({
                url: url,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                success: function (response) {
                    // Capitalize the first letter for the success toast
                    const capitalizedType = itemType.charAt(0).toUpperCase() + itemType.slice(1);
                    window.toast('success', response.message || `${capitalizedType} deleted successfully.`);
                    
                    // Reload the page to show the item is gone
                    setTimeout(() => location.reload(), 1000);
                },
                error: function (xhr) {
                    const errorMessage = xhr.responseJSON?.message || `Failed to delete ${itemType}.`;
                    window.toast('error', errorMessage);
                },
            });
        }
    });
    
    // --- INVITE PEER SEARCH AJAX ---
    let searchTimeout = null;
    const $searchInput = $('#inviteSearchInput');
    const $userIdInput = $('#inviteUserId');
    const $dropdown = $('#inviteDropdown');
    const $searchWrapper = $('#inviteSearchWrapper');
    
    // Only run this if the user actually has the invite form rendered on their screen
    if ($searchInput.length) {
        $searchInput.on('input', function() {
            clearTimeout(searchTimeout);
            let query = $(this).val().trim();
            
            // Clear hidden ID if they delete the search text
            if (query.length === 0) {
                $userIdInput.val('');
            }
            
            // Wait for at least 2 characters before hitting the database
            if (query.length < 2) {
                $dropdown.hide();
                return;
            }
            
            // Debounce the AJAX call to prevent server spam
            searchTimeout = setTimeout(() => {
                $.ajax({
                    url: '/users/search',
                    method: 'GET',
                    data: { q: query },
                    dataType: 'json',
                    success: function(users) {
                        $dropdown.empty();
                        
                        if (users.length === 0) {
                            $dropdown.append('<div class="p-2 text-muted small text-center">No students found.</div>');
                        } else {
                            users.forEach(user => {
                                // 1. Determine which avatar to show (Picture or Initial)
                                let avatarHtml = '';
                                if (user.profile_picture) {
                                    avatarHtml = `<img src="/storage/${user.profile_picture}" alt="${user.name}" class="rounded-circle object-fit-cover shadow-sm" style="width: 32px; height: 32px;">`;
                                } else {
                                    avatarHtml = `<div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                    <span class="text-light fw-bold small">${user.name.charAt(0).toUpperCase()}</span>
                                                  </div>`;
                                }

                                // 2. Build the dropdown item using jQuery syntax with the avatar included
                                const $item = $('<a>', {
                                    href: '#',
                                    class: 'dropdown-item py-2 border-bottom d-flex align-items-center gap-3',
                                    html: `
                                        ${avatarHtml}
                                        <div>
                                            <div class="fw-bold fs-6 mb-0" style="line-height: 1.2;">${user.name}</div>
                                            <div class="small text-muted">${user.email}</div>
                                        </div>
                                    `
                                });
                                
                                // Click event to select the peer
                                $item.on('click', function(e) {
                                    e.preventDefault();
                                    $searchInput.val(user.name);
                                    $userIdInput.val(user.id);
                                    $dropdown.hide();
                                });
                                
                                $dropdown.append($item);
                            });
                        }
                        $dropdown.show();
                    },
                    error: function(xhr) {
                        console.error('Failed to fetch students:', xhr);
                        window.toast('error', 'Could not load search results.');
                    }
                });
            }, 300);
        });
        
        // Close dropdown if the user clicks anywhere outside of the search wrapper
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#inviteSearchWrapper').length) {
                $dropdown.hide();
            }
        });
    }
    
    // --- REMOVE MEMBER AJAX LOGIC ---
    $(document).on('click', '.remove-member-btn', async function(e) {
        e.preventDefault(); 
        
        const $btn = $(this);
        const url = $btn.data('url');
        
        // Trigger your custom confirmation alert
        const result = await window.confirmAction(
            'Are you sure you want to remove this student from the community?',
            'Remove Member?'
        );
        
        if (result.isConfirmed) {
            // Show a loading spinner on the button
            const originalContent = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.ajax({
                url: url,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                success: function (response) {
                    window.toast('success', response.message || 'Member removed successfully.');
                    
                    // Reload the page to refresh the member list after 1 second
                    setTimeout(() => location.reload(), 1000);
                },
                error: function (xhr) {
                    const errorMessage = xhr.responseJSON?.message || 'Failed to remove member.';
                    window.toast('error', errorMessage);
                    
                    // Reset button if it fails
                    $btn.prop('disabled', false).html(originalContent);
                },
            });
        }
    });

    // --- LEAVE COMMUNITY AJAX LOGIC ---
    $(document).on('click', '.leave-community-btn', async function(e) {
        e.preventDefault(); 
        
        const $btn = $(this);
        const url = $btn.data('url');
        
        const result = await window.confirmAction(
            'Are you sure you want to leave this community?',
            'Leave Community?'
        );
        
        if (result.isConfirmed) {
            const originalContent = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            
            $.ajax({
                url: url,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                success: function (response) {
                    window.toast('success', response.message || 'You have left the community.');
                    
                    // Redirect back to the main communities hub after 1.5 seconds
                    setTimeout(() => {
                        window.location.href = '/community'; 
                    }, 1500);
                },
                error: function (xhr) {
                    const errorMessage = xhr.responseJSON?.message || 'Failed to leave community.';
                    window.toast('error', errorMessage);
                    $btn.prop('disabled', false).html(originalContent);
                },
            });
        }
    });

});