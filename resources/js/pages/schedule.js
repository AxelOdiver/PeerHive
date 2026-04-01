// Schedule page - availability picker
$(document).ready(function() {
  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  const $weekTiles = $('#weekTiles');
  const modalEl = document.getElementById('availabilityModal');
  const modal = new bootstrap.Modal(modalEl);
  
  const $selectedDayIndex = $('#selectedDayIndex');
  const $selectedDayLabel = $('#selectedDayLabel');
  const $startTimeInput = $('#startTime');
  const $endTimeInput = $('#endTime');
  const $form = $('#availabilityForm');
  const $clearTimeBtn = $('#clearTimeBtn');

  let availability = {};

  // --- 1. GET SCHEDULE ---
  function loadScheduleFromDatabase() {
    fetch('/schedule/data')
      .then(res => {
        if (!res.ok) throw new Error('Server error'); // Safety check
        return res.json();
      })
      .then(data => {
        availability = {}; 
        
        if (data.schedule) {
          data.schedule.forEach(item => {
            availability[item.day_index] = {
              start: item.start_time,
              end: item.end_time
            };
          });
        }
        
        renderTiles(); 
      })
      .catch(error => {
        console.error("Error loading schedule:", error);
        renderTiles(); 
      });
  }

  // Load immediately on page open
  loadScheduleFromDatabase();
  
  function formatTime(time24) {
    if (!time24) return '';
    
    const parts = time24.split(':');
    let hour = parseInt(parts[0], 10);
    const minute = parts[1];
    const ampm = hour >= 12 ? 'PM' : 'AM';
    
    hour = hour % 12 || 12;
    
    return hour + ':' + minute + ' ' + ampm;
  }
  
  function getDisplayRange(dayIndex) {
    const data = availability[dayIndex];
    
    if (!data || !data.start || !data.end) {
      return '';
    }
    
    return formatTime(data.start) + ' - ' + formatTime(data.end);
  }
  
  function renderTiles() {
    $weekTiles.empty();
    
    $.each(days, function(index, day) {
      const range = getDisplayRange(index);
      const hasAvailability = !!range;
      
      const tileHtml = `
        <div class="col">
          <div class="availability-tile ${hasAvailability ? 'active' : ''}" data-day-index="${index}">
            <div>
              <div class="availability-day">${day}</div>
              <div class="availability-status">${hasAvailability ? 'Available' : 'No time selected'}</div>
            </div>
            <div class="availability-time ${hasAvailability ? '' : 'availability-empty'}">
              ${hasAvailability ? range : 'Tap to set time'}
            </div>
          </div>
        </div>
      `;
      
      $weekTiles.append(tileHtml);
    });
  }
  
  function openModal(dayIndex) {
    const existing = availability[dayIndex] || {};
    
    $selectedDayIndex.val(dayIndex);
    $selectedDayLabel.val(days[dayIndex]);
    $startTimeInput.val(existing.start || '');
    $endTimeInput.val(existing.end || '');
    
    modal.show();
  }
  
  $weekTiles.on('click', '.availability-tile', function() {
    const dayIndex = $(this).data('day-index');
    openModal(dayIndex);
  });
  
  // --- 2. SAVE SCHEDULE (POST) ---
  $form.on('submit', function(e) {
    e.preventDefault();
    
    const dayIndex = $selectedDayIndex.val();
    const start = $startTimeInput.val();
    const end = $endTimeInput.val();
    
    if (!start || !end) return;
    
    if (start >= end) {
      if(window.toast) toast('error', 'End time must be later than start time');
      return;
    }
    
    // Save locally for instant UI update
    availability[dayIndex] = {
      start: start,
      end: end
    };
    
    fetch('/schedule', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      body: JSON.stringify({
        day_index: dayIndex,
        start_time: start,
        end_time: end
      })
    })
    .then(res => {
      if (!res.ok) throw new Error('Save failed'); // Safety check
      return res.json();
    })
    .then(() => {
      // Refresh the data from the server to guarantee it matches
      return fetch('/schedule/data');
    })
    .then(res => res.json())
    .then(data => {
      availability = {};

      data.schedule.forEach(item => {
        availability[item.day_index] = {
          start: item.start_time,
          end: item.end_time
        };
      });

      renderTiles();
      modal.hide();
      if(window.toast) toast('success', 'Availability saved');
    })
    .catch(() => {
      if(window.toast) toast('error', 'Something went wrong while saving. Please try again.');
    });
  });

  // --- 3. DELETE SCHEDULE (DELETE) ---
  $clearTimeBtn.on('click', function() {
    const dayIndex = $selectedDayIndex.val();
    
    fetch('/schedule', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      body: JSON.stringify({
        day_index: dayIndex
      })
    })
    .then(res => {
      if (!res.ok) throw new Error('Delete failed'); // Safety check
      return res.json();
    })
    .then(() => {
      delete availability[dayIndex];
      renderTiles();
      modal.hide();
      if(window.toast) toast('success', 'Time cleared');
    })
    .catch(() => {
      if(window.toast) toast('error', 'Could not clear time');
    });
  });

});