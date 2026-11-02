import './learning-request.js';
// Manual tutorial: swipe, arrow keys, or use the visible controls.
const tutorial = document.getElementById('peerHiveWalkthrough');
if (tutorial) {
  const carousel = new window.bootstrap.Carousel(tutorial, { interval: false, wrap: false, touch: true, keyboard: true });
  const slides = [...tutorial.querySelectorAll('.carousel-item')];
  const dots = [...tutorial.querySelectorAll('[data-tutorial-slide]')];
  const previous = document.getElementById('tutorialPrevious');
  const next = document.getElementById('tutorialNext');
  previous.addEventListener('click', () => carousel.prev());
  next.addEventListener('click', () => carousel.next());
  dots.forEach((dot, index) => dot.addEventListener('click', () => carousel.to(index)));
  tutorial.addEventListener('slide.bs.carousel', event => {
    dots.forEach((dot, index) => {
      dot.classList.toggle('is-active', index === event.to);
      dot.setAttribute('aria-current', index === event.to ? 'step' : 'false');
    });
  });
  tutorial.addEventListener('slid.bs.carousel', event => {
    previous.disabled = event.to === 0;
    next.disabled = event.to === slides.length - 1;
    slides.forEach((slide, index) => slide.setAttribute('aria-hidden', String(index !== event.to)));
    dots.forEach((dot, index) => {
      dot.classList.toggle('is-active', index === event.to);
      dot.setAttribute('aria-current', index === event.to ? 'step' : 'false');
    });
    if (document.activeElement === previous && previous.disabled) next.focus();
    if (document.activeElement === next && next.disabled) previous.focus();
  });
}

// Favorite (bookmark) toggle
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
      const buttons = $(`.fav-btn[data-id="${itemId}"]`);
      if (response.action === 'swapped') {
        buttons.html('<i class="bi bi-bookmark-fill text-warning"></i>');
      } else {
        buttons.html('<i class="bi bi-bookmark"></i>');
      }
    },
    error: function (xhr) {
      console.log('Error:', xhr.responseText);
    }
  });
});

// Heart / Like toggle
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

      const buttons = $(`.like-btn[data-id="${userId}"]`);
      buttons.toggleClass('text-danger', liked).attr('title', liked ? 'Unlike' : 'Like');
      buttons.find('i')
        .toggleClass('bi-heart-fill', liked)
        .toggleClass('bi-heart', !liked);

      // Update the count next to the button
      $(`.like-count[data-id="${userId}"]`).text(res.like_count);
    },
    error: function (xhr) {
      if (window.toast) window.toast('error', 'Could not update like.');
    }
  });
});
