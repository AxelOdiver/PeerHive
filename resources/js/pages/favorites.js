// Favorites page - favorite button toggle
$(document).on('click', '.fav-btn', function() {
  let btn = $(this);
  let itemId = btn.data('id');

  $.ajax({
    url: '/favorite/toggle',
    method: "POST",
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      item_id: itemId
    },
    success: function(response) {
      if (response.action === 'removed') {
        $('#card-' + itemId).fadeOut(100, function() { 
          $(this).remove(); 

          if ($('#favoritesList .favorite-card').length === 0) {
             $('#empty-favorites-alert').fadeIn(100);
          }
        });
      }
    },
    error: function(xhr) {
      console.log("Error:", xhr.responseText);
    }
  });
});
