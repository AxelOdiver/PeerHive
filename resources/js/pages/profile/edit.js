import { initTopicPickers } from '../topic-picker.js';

$(document).ready(function() {
    initTopicPickers();
    const validTabs = ['general', 'academic', 'skills', 'verification', 'privacy', 'account'];
    const tabFields = {
        general: ['first_name', 'middle_name', 'last_name', 'username', 'description', 'profile_picture'],
        academic: ['course', 'course_unlisted', 'course_other', 'school', 'school_location'],
        skills: ['expertise[]', 'expertise', 'learning_interests[]', 'learning_interests'],
        privacy: ['show_course', 'show_expertise', 'show_school', 'show_school_location'],
        account: [],
    };
    let savedFields = new Map();
    let profileReady = false;
    let currentServerProfile = null;
    let bypassTabGuard = false;
    let allowUnload = false;
    let saveInFlight = false;
    const namePattern = /^[\p{L}\p{M}]+(?:[ '-][\p{L}\p{M}]+)*$/u;
    function validateName(element, show = false) {
        const value = element.value.trim();
        const valid = (value || !element.required) && (!value || namePattern.test(value));
        if (show || element.classList.contains('is-invalid')) {
            element.classList.toggle('is-invalid', !valid);
            const msg = document.querySelector(`[data-error-for="${element.name}"]`);
            if (msg) msg.textContent = valid ? '' : 'Use letters, spaces, apostrophes, or hyphens only.';
        }
        return Boolean(valid);
    }
    $('[data-ph-person-name]').on('blur', function () { validateName(this, true); });
    $('[data-ph-person-name]').on('input', function () { if (this.classList.contains('is-invalid')) validateName(this, true); });
    function snapshot(fields) {
        const map = new Map();
        for (const [name, value] of fields.entries()) {
            if (name === 'profile_picture' || name === 'password' || name === 'password_confirmation') continue;
            if (!map.has(name)) map.set(name, []);
            map.get(name).push(value);
        }
        for (const name of ['expertise[]', 'learning_interests[]']) {
            if (!map.has(name)) map.set(name, []);
        }
        return map;
    }
    function normalizedSnapshot(form) {
        const formData = new FormData(form);
        for (const field of ['course', 'expertise', 'school', 'school_location']) {
            formData.set(`show_${field}`, $(`#show_${field}`).prop('checked') ? '1' : '0');
        }
        return snapshot(formData);
    }
    function selectedTab() {
        return document.querySelector('[data-ph-settings-tab].active')?.dataset.phSettingsTab || 'general';
    }
    function showSettingsTab(tab) {
        if (!validTabs.includes(tab)) return;
        const element = document.getElementById('ph-settings-tab-' + tab);
        if (element) window.bootstrap.Tab.getOrCreateInstance(element).show();
    }
    function requestedTab() {
        if (window.location.hash === '#teaching-verification') return 'verification';
        if (window.location.hash === '#learning-interests') return 'skills';
        const tab = new URLSearchParams(window.location.search).get('tab');
        return validTabs.includes(tab) ? tab : 'general';
    }
    // Cancel an attempted tab switch before Bootstrap hides the dirty pane.
    $('[data-ph-settings-tab]').on('show.bs.tab', function (event) {
        if (saveInFlight) { event.preventDefault(); return; }
        if (bypassTabGuard || !profileReady || !isTabDirty(selectedTab())) return;
        event.preventDefault();
        const target = this;
        confirmDiscard().then(discard => {
            if (!discard) return;
            discardCurrentDraft();
            bypassTabGuard = true;
            window.bootstrap.Tab.getOrCreateInstance(target).show();
            bypassTabGuard = false;
        });
    });
    $('[data-ph-settings-tab]').on('shown.bs.tab', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', this.dataset.phSettingsTab);
        if (url.hash === '#learning-interests' || url.hash === '#teaching-verification') url.hash = '';
        window.history.replaceState(null, '', url.pathname + url.search + url.hash);
    });
    window.addEventListener('hashchange', () => showSettingsTab(requestedTab()));
    showSettingsTab(requestedTab());
    // Reuse one catalog suggestion form from the three relevant tabs.
    $('#catalogSuggestionModal').on('show.bs.modal', function (event) {
        const kind = event.relatedTarget?.dataset.catalogSuggestKind;
        if (kind === 'program' || kind === 'topic') {
            $('#catalogSuggestionKind').val(kind).trigger('change');
            $('#catalogSuggestionTitle').text(kind === 'program' ? 'Suggest a degree program' : 'Suggest a learning topic');
            $('#catalogSuggestionDescription').text(kind === 'program'
                ? 'Suggest a missing degree program for admin review.'
                : 'Suggest a missing learning or teaching topic for admin review. Suggestions do not count as teaching verification.');
        }
    });
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
        
        $('#profileImage').off('error.profileImage').one('error.profileImage', function () {
            $(this).attr('src', placeholderImage);
        }).attr('src', imageUrl);
        $('[name="first_name"]').val(user.first_name ?? '');
        $('[name="middle_name"]').val(user.middle_name ?? '');
        $('[name="last_name"]').val(user.last_name ?? '');
        $('[name="email"]').val(user.email ?? '');
        $('[name="username"]').val(user.username ?? '');
        if (user.course && !$('[name="course"] option').toArray().some(option => option.value === user.course)) {
            $('[name="course"]').append(new Option(user.course + ' (not in shared list)', user.course));
        }
        for (const field of ['course', 'school', 'school_location']) {
            $(`[name="${field}"]`).val(user[field] ?? '');
        }
        $('#course').trigger('change');
        for (const field of ['learning_interests', 'expertise']) {
            const $select = $(`[name="${field}[]"]`);
            for (const value of user[field] ?? []) if (!$select.find('option').toArray().some(option => option.value === value)) $select.append(new Option(value + ' (previous entry)', value));
            $select.val(user[field] ?? []);
            $select[0]?.dispatchEvent(new Event('change', {bubbles: true}));
        }
        for (const field of ['course', 'expertise', 'school', 'school_location']) {
            $(`#show_${field}`).prop('checked', Boolean(user[`show_${field}`]));
        }
        
        $('#descriptionText').text(user.description ? user.description : 'No description yet.');
        $('[name="description"]').val(user.description ?? '');
        
        $('#profileSummaryName').text(fullName);
        $('#profileSummaryEmail').text(user.email ?? '-');

        
        if (options.passwordChanged) {
            $('#profilePasswordStatus').text('Updated');
        }
    }

    $.ajax({
        url: '/profile/show',
        method: 'GET',
        success: function (response) {
            renderProfile(response.user);
            currentServerProfile = response.user;
            savedFields = normalizedSnapshot($form[0]);
            profileReady = true;
        },
        error: function (xhr) {
            toast('error', 'Failed to load profile data. Please refresh the page.');
            console.error(xhr.responseText);
        }
    });

    $('#course').on('change', function () {
        const other = this.value === '__other';
        $('#courseUnlisted').val(other ? '1' : '0');
        $('#courseOtherWrap').prop('hidden', !other);
        $('#course_other').prop('required', other);
    });
    function updateSuggestionFields() {
        const topic = $('#catalogSuggestionKind').val() === 'topic';
        $('#catalogSuggestionName').attr('placeholder', topic
            ? 'For example: Japanese Conversation'
            : 'For example: BS Tourism Management (BSTM)');
        $('#catalogSubjectWrap').prop('hidden', !topic);
        $('#catalogOtherSubjectWrap').prop('hidden', !topic || Boolean($('#catalogSubject').val()));
        $('#catalogSubjectName').prop('required', topic && !$('#catalogSubject').val());
    }
    $('#catalogSuggestionKind, #catalogSubject').on('change', updateSuggestionFields);
    updateSuggestionFields();
    
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
    
    $('.ph-settings-save').on('click', function(e) {
        e.preventDefault();
        $form.trigger('submit');    
    });
    
    $form.on('submit', function(e) {
        e.preventDefault();
        if (!profileReady) { toast('error', 'Profile is still loading. Please refresh and try again.'); return; }
        clearErrors($form);
        
        const currentTab = selectedTab();
        if (currentTab === 'general') {
            const invalid = [...document.querySelectorAll('[data-ph-person-name]')].filter(el => !validateName(el, true));
            if (invalid.length) { invalid[0].focus(); toast('error', 'Please correct the name fields.'); return; }
        }
        saveInFlight = true;
        $(`[data-ph-settings-save="${currentTab}"]`).prop('disabled', true);
        const formData = new FormData(this);
        for (const field of ['course', 'expertise', 'school', 'school_location']) {
            formData.set(`show_${field}`, $(`#show_${field}`).prop('checked') ? '1' : '0');
        }
        for (const field of ['learning_interests', 'expertise']) {
            if (!formData.has(`${field}[]`)) formData.set(field, '');
        }
        // Each settings tab saves its own fields. Other fields come from the last
        // confirmed server state, because the legacy endpoint validates the full profile.
        for (const [name, values] of savedFields.entries()) {
            if (tabFields[currentTab]?.includes(name)) continue;
            formData.delete(name);
            if (name === 'learning_interests[]' || name === 'expertise[]') formData.delete(name.replace('[]', ''));
            for (const value of values) formData.append(name, value);
            if ((name === 'learning_interests[]' || name === 'expertise[]') && !values.length) {
                formData.set(name.replace('[]', ''), '');
            }
        }
        if (currentTab !== 'account') {
            formData.delete('password');
            formData.delete('password_confirmation');
        }
        if (currentTab !== 'general') formData.delete('profile_picture');
        // The profile photo input already belongs to this form; do not append a duplicate.
        formData.set('_method', 'PUT');
        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: formData,
            contentType: false,   
            processData: false,  
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                saveInFlight = false;
                $(`[data-ph-settings-save="${currentTab}"]`).prop('disabled', false);
                currentServerProfile = response.user;
                // Persist only submitted values in the baseline. Other tabs retain
                // their unsaved drafts instead of being unexpectedly overwritten.
                const submitted = snapshot(formData);
                for (const name of tabFields[currentTab] ?? []) {
                    if (name === 'profile_picture' || name === 'password' || name === 'password_confirmation') continue;
                    savedFields.set(name, submitted.get(name) ?? []);
                }
                if (currentTab === 'academic') {
                    const storedCourse = response.user.course || '';
                    if (storedCourse && !$('#course option').toArray().some(option => option.value === storedCourse)) {
                        $('#course').append(new Option(storedCourse + ' (not in shared list)', storedCourse));
                    }
                    $('#course').val(storedCourse).trigger('change');
                    savedFields.set('course', [storedCourse]);
                    savedFields.set('course_unlisted', ['0']);
                    savedFields.set('course_other', ['']);
                    for (const field of ['school', 'school_location']) savedFields.set(field, [response.user[field] || '']);
                }
                if (currentTab === 'skills') {
                    for (const field of ['expertise', 'learning_interests']) {
                        const values = response.user[field] || [];
                        savedFields.set(field + '[]', values);
                        savedFields.delete(field);
                        const select = $(`[name="${field}[]"]`)[0];
                        if (select) {
                            for (const option of select.options) option.selected = values.includes(option.value);
                            select.dispatchEvent(new Event('change', {bubbles:true}));
                        }
                    }
                }
                if (currentTab === 'privacy') {
                    for (const field of ['course', 'expertise', 'school', 'school_location']) {
                        savedFields.set('show_' + field, [response.user['show_' + field] ? '1' : '0']);
                    }
                }
                if (currentTab === 'general') {
                    for (const field of ['first_name','middle_name','last_name','username','description']) {
                        savedFields.set(field, [response.user[field] || '']);
                    }
                    $('#descriptionEditMode').hide();
                    $('#descriptionViewMode').show();
                    $('#editDescriptionBtn').show();
                    $('#descriptionText').text(response.user.description || 'No description yet.');
                    $('#profileSummaryName').text(getFullName(response.user));
                    $('#profileImage').attr('src', response.user.profile_picture_url ?? placeholderImage);

                    $('#profilePictureUpload').val('');
                }
                $('#sidebarUserName').text(getFullName(response.user));
                $('#sidebarUserEmail').text(response.user.email ?? '');
                $('#sidebarUserAvatar').attr('src', response.user.profile_picture_url ?? placeholderImage);
                toast('success', response.message ?? 'Profile updated successfully.');
            },
            error: function(xhr) {
                saveInFlight = false;
                $(`[data-ph-settings-save="${currentTab}"]`).prop('disabled', false);
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON?.errors || {};
                    const interestError = Object.keys(errors).find(key => key.startsWith('learning_interests.'));
                    if (interestError) errors.learning_interests = errors[interestError];
                    const labelError = Object.keys(errors).find(key => key.startsWith('expertise.'));
                    if (labelError) errors.expertise = errors[labelError];
                    const key = Object.keys(errors)[0]?.split('.')[0];
                    const relevantTab = Object.keys(tabFields).find(tab =>
                        (tabFields[tab] || []).some(name => name.replace('[]', '') === key));
                    if (relevantTab) showSettingsTab(relevantTab);
                    showFormErrors($form, errors);
                    for (const field of ['learning_interests', 'expertise']) if (errors[field]) $(`#${field}_picker`).addClass('is-invalid');
                    return;
                }
                toast('error', 'Sorry, something went wrong. Please try again.');
            }
        });
    });
    
    $('#profilePictureUpload').on('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            // Instantly reveal the remove button without a page refresh
            $('#removePictureBtn').removeClass('d-none');
            
            // Create a live preview of the newly selected image
            const reader = new FileReader();
            
            reader.onload = function(event) {
                $('#profileImage').attr('src', event.target.result);
            };
            
            reader.readAsDataURL(file);
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
            processData: false, 
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
                    allowUnload = true; // The qualification was just saved.
                    window.location.reload();
                });
            },
            error: function(xhr) {
                // Catch Laravel Validation Errors (like File Too Large)
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
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

    $(document).on('click', '#removePictureBtn', async function(e) {
        e.preventDefault(); 
        
        const result = await window.confirmAction(
            'Are you sure you want to remove your profile picture? This cannot be undone.',
            'Remove Profile Picture?'
        );
        
        // If they click confirm, submit the form to the backend
        if (result.isConfirmed) {
            if (isTabDirty('general') && !(await confirmDiscard())) return;
            allowUnload = true;
            $('#removePictureForm').submit();
        }
    });


    // Compare current controls with the last successful server response.
    // No autosave. Files and secret fields are tracked without retaining their values.
    function isTabDirty(tab) {
        if (!profileReady) return false;
        if (tab === 'account') {
            return [...document.querySelectorAll('#phEmailChangeForm input:not([type="hidden"]), #phPasswordChangeForm input:not([type="hidden"])')]
                .some(el => el.value.length > 0);
        }
        if (tab === 'verification') {
            return Boolean(document.querySelector('#qualificationForm input[type="file"]')?.files?.length)
                || Boolean(document.querySelector('#qualificationForm [name="subject"]')?.value);
        }
        if (tab === 'general' && document.getElementById('profilePictureUpload')?.files?.length) return true;
        const now = normalizedSnapshot($form[0]);
        return (tabFields[tab] || []).some(name => {
            if (name === 'profile_picture' || name === 'password' || name === 'password_confirmation') return false;
            return JSON.stringify(now.get(name) || []) !== JSON.stringify(savedFields.get(name) || []);
        });
    }
    function hasUnsavedChanges() {
        return validTabs.some(isTabDirty) || Boolean(document.getElementById('catalogSuggestionName')?.value.trim());
    }
    function discardCurrentDraft() {
        const tab = selectedTab();
        if (tab === 'account') {
            $('#phEmailChangeForm, #phPasswordChangeForm').each(function() { this.reset(); });
            refreshPasswordRules();
        } else if (tab === 'verification') {
            document.getElementById('qualificationForm')?.reset();
        } else if (currentServerProfile) {
            renderProfile(currentServerProfile);
            $('#profilePictureUpload').val('');
            $('#removePictureBtn').toggleClass('d-none', !currentServerProfile.profile_picture_url);
            $('#descriptionEditMode').hide();
            $('#descriptionViewMode').show();
            $('#editDescriptionBtn').show();
        }
        clearErrors($form);
    }
    function confirmDiscard() {
        return new Promise(resolve => {
            const modalEl = document.getElementById('phUnsavedChangesModal');
            const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            let discarded = false;
            $('#phDiscardChangesBtn').one('click', function () { discarded = true; modal.hide(); });
            $(modalEl).one('hidden.bs.modal', () => {
                $('#phDiscardChangesBtn').off('click');
                resolve(discarded);
            });
            modal.show();
        });
    }
    // Browser Back, refresh, and close use the browser's required native warning.
    // Chrome, Firefox, and Safari do not allow a custom message for beforeunload.
    window.addEventListener('beforeunload', event => {
        if (!allowUnload && hasUnsavedChanges()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    // Same-tab navigation can show the branded dialog before navigation occurs.
    document.addEventListener('click', event => {
        const anchor = event.target.closest('a[href]');
        if (!anchor || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || anchor.target === '_blank' || anchor.hasAttribute('download')) return;
        const destination = anchor.href;
        if (!destination || destination.startsWith('javascript:')) return;
        if (new URL(destination, window.location.href).href === window.location.href) return;
        if (!hasUnsavedChanges()) return;
        event.preventDefault();
        confirmDiscard().then(discard => {
            if (discard) { allowUnload = true; window.location.assign(destination); }
        });
    }, true);
    // A catalog form posts a suggestion and reloads the page. Warn about other drafts.
    $('#catalogSuggestionForm').on('submit', function (event) {
        if (!profileReady || !validTabs.some(isTabDirty)) { allowUnload = true; return; }
        event.preventDefault();
        const form = this;
        confirmDiscard().then(discard => {
            if (discard) { allowUnload = true; form.submit(); }
        });
    });

    // Reuse validation messages from the existing page without leaking secrets in logs.
    function showSecurityErrors(form, errors) {
        $(form).find('.is-invalid').removeClass('is-invalid');
        $(form).find('[data-security-error]').text('');
        for (const [name, messages] of Object.entries(errors)) {
            const input = form.querySelector(`[name="${name}"]`);
            if (input) input.classList.add('is-invalid');
            const message = form.querySelector(`[data-security-error="${name}"]`);
            if (message) message.textContent = messages[0] ?? 'Please check this field.';
        }
    }
    function submitSecurityForm(selector, messageSelector) {
        $(selector).on('submit', function (event) {
            event.preventDefault();
            const form = this;
            showSecurityErrors(form, {});
            if (!form.checkValidity()) { form.reportValidity(); return; }
            if (selector === '#phPasswordChangeForm') {
                const newPassword = form.elements.password.value;
                const matches = newPassword === form.elements.password_confirmation.value;
                const strong = newPassword.length >= 8 && /[A-Z]/.test(newPassword) && /[0-9]/.test(newPassword) && /[^A-Za-z0-9]/.test(newPassword);
                if (!strong || !matches) {
                    showSecurityErrors(form, {password: [strong ? 'Password confirmation does not match.' : 'Include 8 characters, one uppercase letter, number, and special character.']});
                    return;
                }
            }
            const $button = $(form).find('button[type="submit"]');
            $button.prop('disabled', true);
            $(messageSelector).text('');
            $.ajax({
                url: form.action, method: 'POST', data: $(form).serialize(),
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success(response) {
                    form.reset();
                    refreshPasswordRules();
                    $(messageSelector).removeClass('text-danger').addClass('text-success').text(response.message || 'Updated successfully.');
                    toast('success', response.message || 'Updated successfully.');
                },
                error(xhr) {
                    if (xhr.status === 422) {
                        showSecurityErrors(form, xhr.responseJSON?.errors || {});
                    } else {
                        $(messageSelector).removeClass('text-success').addClass('text-danger')
                            .text(xhr.responseJSON?.message || 'Unable to complete this request. Check your connection and mail settings, then try again.');
                    }
                },
                complete() { $button.prop('disabled', false); }
            });
        });
    }
    function refreshPasswordRules() {
        const value = document.getElementById('ph-new-password')?.value || '';
        const matches = {
            length: value.length >= 8,
            uppercase: /[A-Z]/.test(value),
            number: /[0-9]/.test(value),
            special: /[^A-Za-z0-9]/.test(value)
        };
        for (const [rule, ok] of Object.entries(matches)) {
            const element = document.querySelector(`[data-ph-rule="${rule}"]`);
            if (!element) continue;
            element.classList.toggle('is-met', ok);
            const icon = element.querySelector('.bi');
            if (icon) icon.className = ok ? 'bi bi-check-circle-fill me-1' : 'bi bi-circle me-1';
        }
    }
    // v4.6.2: local visibility toggles only. Never persist or transmit the reveal state.
    const accountPasswordFields = new Set([
        'ph-email-current-password', 'ph-current-password',
        'ph-new-password', 'ph-confirm-password'
    ]);
    function hideAccountPasswordFields(form) {
        form.querySelectorAll('[data-ph-password-toggle]').forEach(button => {
            const id = button.dataset.phPasswordToggle;
            if (!accountPasswordFields.has(id)) return;
            const field = document.getElementById(id);
            if (!field || !form.contains(field)) return;
            field.type = 'password';
            button.setAttribute('aria-pressed', 'false');
            const label = button.dataset.phPasswordLabel || 'password';
            button.setAttribute('aria-label', `Show ${label}`);
            button.title = `Show ${label}`;
            const icon = button.querySelector('i');
            if (icon) icon.className = 'bi bi-eye';
        });
    }
    $(document).on('click', '[data-ph-password-toggle]', function () {
        const id = this.dataset.phPasswordToggle;
        if (!accountPasswordFields.has(id)) return;
        const field = document.getElementById(id);
        if (!field || !(this.closest('form')?.contains(field))) return;
        const revealing = field.type === 'password';
        field.type = revealing ? 'text' : 'password';
        this.setAttribute('aria-pressed', revealing ? 'true' : 'false');
        const label = this.dataset.phPasswordLabel || 'password';
        this.setAttribute('aria-label', `${revealing ? 'Hide' : 'Show'} ${label}`);
        this.title = `${revealing ? 'Hide' : 'Show'} ${label}`;
        const icon = this.querySelector('i');
        if (icon) icon.className = revealing ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
    document.querySelectorAll('#phEmailChangeForm, #phPasswordChangeForm').forEach(form => {
        form.addEventListener('reset', () => hideAccountPasswordFields(form));
    });
    $('#ph-new-password').on('input', refreshPasswordRules);
    refreshPasswordRules();
    submitSecurityForm('#phEmailChangeForm', '#ph-email-change-message');
    submitSecurityForm('#phPasswordChangeForm', '#ph-password-change-message');

});
