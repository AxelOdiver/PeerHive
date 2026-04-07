$(document).ready(function() {
  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  const $weekTiles = $('#weekTiles');
  
  const $selectedDayIndex = $('#selectedDayIndex');
  const $selectedDayLabel = $('#selectedDayLabel');
  const $startTimeInput = $('#startTime');
  const $endTimeInput = $('#endTime');
  const $availabilityForm = $('#availabilityForm');
  const $clearTimeBtn = $('#clearTimeBtn');

  let availability = {};

  function syncAvailability(schedule = []) {
    availability = {};

    schedule.forEach(item => {
      availability[item.day_index] = {
        start: item.start_time,
        end: item.end_time
      };
    });
  }

  function loadScheduleFromDatabase() {
    $.ajax({
      url: '/schedule/data',
      method: 'GET',
      success: function(data) {
        syncAvailability(data.schedule || []);
        renderTiles();
      },
      error: function(xhr, status, error) {
        console.error("Error loading schedule:", error);
        renderTiles();
      }
    });
  }

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
    
    showModal('availabilityModal');
  }
  
  $weekTiles.on('click', '.availability-tile', function() {
    const dayIndex = $(this).data('day-index');
    openModal(dayIndex);
  });
  
  $availabilityForm.on('submit', function(e) {
    e.preventDefault();
    
    const dayIndex = $selectedDayIndex.val();
    const start = $startTimeInput.val();
    const end = $endTimeInput.val();
    
    if (!start || !end) return;
    
    if (start >= end) {
      if(window.toast) toast('error', 'End time must be later than start time');
      return;
    }
    
    availability[dayIndex] = {
      start: start,
      end: end
    };
    
    $.ajax({
      url: '/schedule',
      method: 'POST',
      contentType: 'application/json',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: JSON.stringify({
        day_index: dayIndex,
        start_time: start,
        end_time: end
      }),
      success: function() {
        loadScheduleFromDatabase();
        hideModal('availabilityModal');
        if(window.toast) toast('success', 'Availability saved');
      },
      error: function() {
        if(window.toast) toast('error', 'Something went wrong while saving. Please try again.');
      }
    });
  });

  $clearTimeBtn.on('click', function() {
    const dayIndex = $selectedDayIndex.val();
    
    $.ajax({
      url: '/schedule',
      method: 'DELETE',
      contentType: 'application/json',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      data: JSON.stringify({
        day_index: dayIndex
      }),
      success: function() {
        delete availability[dayIndex];
        renderTiles();
        hideModal('availabilityModal');
        if(window.toast) toast('success', 'Time cleared');
      },
      error: function() {
        if(window.toast) toast('error', 'Could not clear time');
      }
    });
  });

});
