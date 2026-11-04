import '../learning-request.js';

const reviewModal = document.getElementById('allReviewsModal');
if (reviewModal) {
  const content = document.getElementById('allReviewsContent');
  let stars = '', sort = 'newest', pending = null, version = 0;
  function loadReviews(page = 1) {
    const current = ++version;
    pending?.abort();
    const scrollTop = reviewModal.querySelector('.modal-body');
    content.setAttribute('aria-busy', 'true');
    pending = $.ajax({
      url: content.dataset.reviewsUrl,
      data: { ...(stars ? {stars} : {}), sort, page },
      success: response => {
        if (current !== version) return;
        $(content).html(response.html);
        scrollTop.scrollTop = 0;
      },
      error: (xhr, status) => {
        if (status === 'abort' || current !== version) return;
        $(content).empty().append($('<p class="text-danger" role="alert"></p>').text('Could not load reviews. Please retry.'))
          .append($('<button type="button" class="btn btn-outline-primary" data-reviews-retry>Retry</button>'));
      },
      complete: () => { if (current === version) content.setAttribute('aria-busy', 'false'); },
    });
  }
  reviewModal.addEventListener('show.bs.modal', () => loadReviews());
  reviewModal.addEventListener('hidden.bs.modal', () => { ++version; pending?.abort(); content.setAttribute('aria-busy', 'false'); });
  $(content).on('click', '[data-review-stars]', function () { stars = this.dataset.reviewStars; loadReviews(); });
  $(content).on('change', '#allReviewSort', function () { sort = this.value; loadReviews(); });
  $(content).on('click', '[data-review-pagination] a', function (event) {
    event.preventDefault();
    loadReviews(Number(new URL(this.href).searchParams.get('page')) || 1);
  });
  $(content).on('click', '[data-reviews-retry]', () => loadReviews());
  if (reviewModal.classList.contains('show')) loadReviews();
  $(content).on('submit', '[data-review-edit], [data-review-report]', function (event) {
    event.preventDefault();
    const form = this, editing = form.hasAttribute('data-review-edit');
    const $submit = $(form).find('button[type="submit"]'), $error = $(form).find('[data-review-error]');
    $submit.prop('disabled', true); $error.addClass('d-none');
    $.ajax({url: form.action, method: editing ? 'PATCH' : 'POST', data: $(form).serialize(),
      success: response => {
        window.toast?.('success', response.message);
        if (editing) window.location.reload();
        else $(form).closest('details').replaceWith($('<p class="small text-success mt-2 mb-0" role="status"></p>').text('Report submitted for admin review.'));
      },
      error: xhr => $error.text(Object.values(xhr.responseJSON?.errors || {}).flat()[0] || xhr.responseJSON?.message || 'Could not save. Please retry.').removeClass('d-none'),
      complete: () => $submit.prop('disabled', false),
    });
  });
}
$(document).on('click', '.like-profile-btn', function () {
  const $btn = $(this);
  const userId = $btn.data('id');

  $.ajax({
    url: '/likes/toggle',
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      user_id: userId
    },
    success: function (res) {
      const liked = res.action === 'liked';

      $btn.toggleClass('btn-danger', liked).toggleClass('btn-outline-danger', !liked);
      $btn.find('i').toggleClass('bi-heart-fill', liked).toggleClass('bi-heart', !liked);
      $btn.find('.like-label').text(liked ? 'Unlike' : 'Like');
      $('#profile-like-count').text(res.like_count);
    },
    error: function (xhr) {
      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Could not update like.');
      }
    }
  });
});

$(document).on('click', '.fav-profile-btn', function () {
  const $btn = $(this);
  const userId = $btn.data('id');

  $.ajax({
    url: '/favorite/toggle',
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      item_id: userId
    },
    success: function (res) {
      const saved = res.action === 'swapped';

      $btn.toggleClass('btn-warning', saved).toggleClass('btn-outline-warning', !saved);
      $btn.find('i').toggleClass('bi-bookmark-fill', saved).toggleClass('bi-bookmark', !saved);
      $btn.find('.fav-label').text(saved ? 'Favorited' : 'Add to favorites');

      if (window.toast) {
        window.toast('success', saved ? 'Added to favorites!' : 'Removed from favorites.');
      }
    },
    error: function (xhr) {
      if (window.toast) {
        window.toast('error', xhr.responseJSON?.message || 'Could not update favorite.');
      }
    }
  });
});
