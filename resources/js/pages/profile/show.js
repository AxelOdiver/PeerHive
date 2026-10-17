import '../learning-request.js';
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
      $btn.find('.fav-label').text(saved ? 'Saved' : 'Save');

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
