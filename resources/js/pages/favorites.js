import './learning-request.js';
// Bookmark (remove from favorites) toggle
$(document).on('click', '.fav-btn', function () {
  const btn = $(this);
  const itemId = btn.data('id');

  $.ajax({
    url: '/favorite/toggle',
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      item_id: itemId
    },
    success: function (response) {
      if (response.action === 'removed') {
        $(`#card-${itemId}`).fadeOut(200, function () {
          $(this).remove();
          if ($('#favoritesList .favorite-card').length === 0) {
            $('#empty-favorites-alert').fadeIn(100);
          }
        });
      }
    }
  });
});

// Like toggle
$(document).on('click', '.like-btn', function () {
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
      $btn.toggleClass('text-danger', liked);
      $btn.find('i')
        .toggleClass('bi-heart-fill', liked)
        .toggleClass('bi-heart', !liked);
      $(`.like-count[data-id="${userId}"]`).text(res.like_count);
    },
    error: function () {
      if (window.toast) window.toast('error', 'Could not update like.');
    }
  });
});
