/**
 * Dashboard global search.
 * Attaches to #globalSearch and renders student results in the top nav.
 */
$(document).ready(function () {
    const $input = $('#globalSearch');
    const $wrapper = $('#searchWrapper');
    const $dropdown = $('#searchDropdown');
    let debounceTimer = null;
    let activeRequest = null;

    if (!$input.length) return;

    function renderDropdown(data) {
        const users = data.users || [];

        if (!users.length) {
            openDropdown(`
                <div class="search-empty">
                    <i class="bi bi-search me-2 opacity-50"></i>No students found
                </div>
            `);
            return;
        }

        const html = users.map((user) => `
            <a href="${user.url}" class="search-item d-flex align-items-center gap-2 text-decoration-none">
                <div class="search-avatar">${escapeHtml(user.initials)}</div>
                <div class="search-item-text overflow-hidden">
                    <div class="search-item-title">${escapeHtml(user.name)}</div>
                    <div class="search-item-sub text-truncate">${escapeHtml(user.availability)}</div>
                </div>
            </a>
        `).join('');

        openDropdown(html);
    }

    function showLoading() {
        openDropdown(`
            <div class="search-empty">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>Searching...
            </div>
        `);
    }

    function openDropdown(html) {
        $dropdown.html(html).addClass('is-open');
        $input.attr('aria-expanded', 'true');
    }

    function hideDropdown() {
        $dropdown.removeClass('is-open').html('');
        $input.attr('aria-expanded', 'false');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $input.on('input', function () {
        const q = $(this).val().trim();
        clearTimeout(debounceTimer);

        if (activeRequest) {
            activeRequest.abort();
            activeRequest = null;
        }

        if (q.length < 2) {
            hideDropdown();
            return;
        }

        showLoading();

        debounceTimer = setTimeout(() => {
            activeRequest = $.ajax({
                url: '/search',
                method: 'GET',
                data: { q },
                success: renderDropdown,
                error: (_, status) => {
                    if (status === 'abort') return;

                    openDropdown(`
                        <div class="search-empty text-danger">
                            <i class="bi bi-exclamation-circle me-1"></i>Something went wrong.
                        </div>
                    `);
                },
                complete: () => {
                    activeRequest = null;
                },
            });
        }, 280);
    });

    $(document).on('click', function (e) {
        if ($wrapper.length && !$wrapper[0].contains(e.target)) {
            hideDropdown();
        }
    });

    $input.on('focus', function () {
        if ($(this).val().trim().length >= 2) {
            $(this).trigger('input');
        }
    });

    $input.on('keydown', function (e) {
        if (e.key === 'Escape') {
            hideDropdown();
            $(this).blur();
        }
    });
});
