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
      if (response.action === 'swapped') {
        btn.html('<i class="bi bi-bookmark-fill text-warning"></i>');
      } else {
        btn.html('<i class="bi bi-bookmark"></i>');
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

      $btn.toggleClass('text-danger', liked);
      $btn.find('i')
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

// Swap request form submit
$(document).on('submit', '#swapRequestForm', function (e) {
  e.preventDefault();
  
  const $form = $(this);
  const $submitButton = $('#sendSwapRequestBtn');
  
  $submitButton.prop('disabled', true).text('Sending...');
  
  $.ajax({
    url: '/swap/add',
    method: 'POST',
    data: {
      _token: $('meta[name="csrf-token"]').attr('content'),
      user_id: $('#swapUserId').val(),
      message: $('#swapRequestMessage').val()
    },
    success: function (response) {
      const modalElement = document.getElementById('swapModal');
      const modalInstance = window.bootstrap.Modal.getInstance(modalElement);
      
      if (modalInstance) {
        modalInstance.hide();
      }
      
      $form[0].reset();
      $('#swapUserId').val('');
      
      if (window.toast) {
        window.toast('success', 'Swap request sent successfully');
      }
      
      window.location.assign(response.redirect || '/swap');
    },
    error: function(xhr) {
      // 1. Re-enable the button so it isn't stuck loading
      $submitButton.prop('disabled', false).text('Send Request');
      
      // 2. THE GATEKEEPER ERROR CATCHER
      if (xhr.status === 403) {
        // Grab the error message sent by Laravel
        const errorMessage = xhr.responseJSON?.message || 'You must be verified to swap.';
        
        // Hide the modal so the SweetAlert popup is the center of attention
        const modalElement = document.getElementById('swapModal');
        const modalInstance = window.bootstrap.Modal.getInstance(modalElement);
        if (modalInstance) {
          modalInstance.hide();
        }
        
        // 1. Correctly check if Bootstrap's dark mode is active
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        
        window.Swal.fire({
          title: 'Verification Required',
          text: errorMessage,
          icon: 'warning',
          confirmButtonText: 'Understood',
          
          // 2. Force SweetAlert to use Bootstrap's exact dark/light hex colors
          background: isDark ? '#212529' : '#ffffff', 
          color: isDark ? '#f8f9fa' : '#212529',      
          
          customClass: {
            // 3. Keep the borders clean and rounded without fighting SweetAlert's default styles
            popup: 'shadow-lg border-0 rounded-4', 
            confirmButton: 'btn btn-primary px-4'
          }
        });
      } else {
        // 3. Handle any other random errors normally (like 500 server errors)
        if (window.toast) {
          window.toast('error', xhr.responseJSON?.message || 'Unable to send swap request right now');
        }
      }
    }
  });
});

// Open swap modal and set user ID
$(document).on('click', '.open-swap-modal', function () {
  const userId = $(this).data('id');
  $('#swapUserId').val(userId);
  $('#swapRequestMessage').val('');
  showModal('swapModal');
});
