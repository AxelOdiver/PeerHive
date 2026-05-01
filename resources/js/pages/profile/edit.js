$(document).ready(function() {
    const $form = $('#form');
    const placeholderImage = '/images/profile-placeholder.jpeg';
    
    function getFullName(user) {
        return [user.first_name, user.middle_name, user.last_name]
        .filter(Boolean)
        .join(' ')
        .trim();
    }
    
    function renderProfile(user, options = {}) {
        const fullName = getFullName(user) || 'User';
        const imageUrl = user.profile_picture_url ?? placeholderImage;
        
        $('#profileImage').attr('src', imageUrl);
        $('[name="first_name"]').val(user.first_name ?? '');
        $('[name="middle_name"]').val(user.middle_name ?? '');
        $('[name="last_name"]').val(user.last_name ?? '');
        $('[name="email"]').val(user.email ?? '');
        
        $('#descriptionText').text(user.description ? user.description : 'No description yet.');
        $('[name="description"]').val(user.description ?? '');
        
        $('#profileSummaryName').text(fullName);
        $('#profileSummaryEmail').text(user.email ?? '-');
        $('#profilePhotoStatus')
        .text(user.profile_picture_url ? 'Uploaded' : 'No photo')
        .toggleClass('text-bg-secondary', !user.profile_picture_url)
        .toggleClass('text-bg-success', Boolean(user.profile_picture_url));
        
        if (options.passwordChanged) {
            $('#profilePasswordStatus').text('Updated');
        }
    }
    
    $.ajax({
        url: '/profile/show',
        method: 'GET',
        success: function (response) {
            renderProfile(response.user);
        },
        error: function (xhr) {
            toast('error', 'Failed to load profile data. Please refresh the page.');
            console.error(xhr.responseText);
        }
    });
    
    $('#editDescriptionBtn').on('click', function() {
        $('#descriptionViewMode').hide();       // Hide the text
        $('#descriptionEditMode').show();       // Show the textarea
        $(this).hide();                         // Hide the Edit button itself
    });
    
    $('#cancelEditBtn').on('click', function() {
        const currentText = $('#descriptionText').text().trim();
        $('[name="description"]').val(currentText === 'No description yet.' ? '' : currentText);
        $('#descriptionEditMode').hide();       // Hide the textarea
        $('#descriptionViewMode').show();       // Show the text
        $('#editDescriptionBtn').show();        // Show the Edit button itself
    });
    
    $('#saveChangesBtn').on('click', function(e) {
        e.preventDefault();
        $form.trigger('submit');    
    });
    
    $form.on('submit', function(e) {
        e.preventDefault();
        clearErrors($form);
        
        const formData = new FormData(this); // captures all form fields
        formData.append('_method', 'PUT');
        formData.append('description', $('[name="description"]').val());
        
        // Manually append the file since it's outside the form
        const file = $('#profilePictureUpload')[0].files[0];
        if (file) {
            formData.append('profile_picture', file);
        }
        
        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: formData,
            contentType: false,   // let the browser set multipart boundary
            processData: false,   // don't let jQuery serialize it
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $('#descriptionEditMode').hide();
                $('#descriptionViewMode').show();
                $('#editDescriptionBtn').show();
                renderProfile(response.user, {
                    passwordChanged: Boolean(formData.get('password')),
                });
                const fullName = getFullName(response.user);
                $('#sidebarUserName').text(fullName);
                $('#sidebarUserEmail').text(response.user.email ?? '');
                $('#sidebarUserAvatar').attr('src', response.user.profile_picture_url ?? '/images/profile-placeholder.jpeg');
                $('[name="password"]').val('');
                $('[name="password_confirmation"]').val('');
                $('#confirmPasswordWrap').removeClass('is-visible');
                toast('success', response.message ?? 'Profile updated successfully.');
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON?.errors || {};
                    showFormErrors($form, errors);
                    return;
                }
                toast('error', 'Sorry, something went wrong. Please try again.');
            }
        });
    });
    
    $('#profilePictureUpload').on('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(event) {
                $('#profileImage').attr('src', event.target.result);
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    $('[name="password"]').on('input', function () {
        const value = $(this).val();
        if (value.length > 0) {
            $('#confirmPasswordWrap').addClass('is-visible');
        } else { 
            $('#confirmPasswordWrap').removeClass('is-visible');
        }
    });

    $('#qualificationForm').on('submit', function(e) {
        e.preventDefault(); // STOPS THE PAGE FROM REFRESHING!

        const formData = new FormData(this);
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const swalThemeClass = isDark ? 'bg-dark text-light border shadow' : 'bg-white text-dark border shadow';

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            processData: false, // Required for file uploads
            contentType: false, // Required for file uploads
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                // Show Success SweetAlert
                window.Swal.fire({
                    title: 'Success!',
                    text: response.message,
                    icon: 'success',
                    confirmButtonText: 'Return',
                    customClass: {
                        popup: swalThemeClass,
                        confirmButton: 'btn btn-primary px-4'
                    }
                }).then(() => {
                    // Reload the page ONLY after they click "Awesome" to show the yellow "Pending" box
                    window.location.reload(); 
                });
            },
            error: function(xhr) {
                // Catch Laravel Validation Errors (like File Too Large)
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    // Combine all error arrays into a single string
                    const errorMessages = Object.values(errors).map(err => err.join('\n')).join('\n');

                    window.Swal.fire({
                        title: 'Submission Failed',
                        text: errorMessages,
                        icon: 'error',
                        confirmButtonText: 'Try Again',
                        customClass: {
                            popup: swalThemeClass,
                            confirmButton: 'btn btn-primary fw-bold px-4'
                        }
                    });
                } else {
                    toast('error', 'Something went wrong with the server.');
                }
            }
        });
    });
});


