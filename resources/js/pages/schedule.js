// PeerHive v4.7.5: multiple weekly availability slots, separate from booked sessions.
$(document).ready(function() {
  const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  const weekTiles = document.getElementById('weekTiles');
  const form = document.getElementById('availabilityForm');
  const selectedDay = document.getElementById('selectedDayIndex');
  const selectedDayLabel = document.getElementById('selectedDayLabel');
  const slotsContainer = document.getElementById('phAvailabilitySlots');
  const addSlot = document.getElementById('phAddAvailabilitySlot');
  const clearDay = document.getElementById('clearTimeBtn');
  const save = document.getElementById('phSaveAvailability');
  const errors = document.getElementById('phAvailabilityErrors');
  const timezoneNote = document.getElementById('phAvailabilityTimezone');
  const maxSlots = 8;
  const availability = Array.from({length: 7}, () => []);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const endpoint = '/schedule';
  const tabs = document.querySelectorAll('#availabilityTab, #bookingsTab');
  tabs.forEach(tab => tab.addEventListener('shown.bs.tab', () => {
    const indicator = tab.closest('[data-ph-tabs]');
    indicator?.querySelectorAll('.ph-tabs-item').forEach(item => item.classList.toggle('is-active', item === tab));
    indicator?.dispatchEvent(new CustomEvent('ph:tabs-change', {bubbles:true}));
    const panel = document.querySelector(tab.dataset.bsTarget);
    if (panel && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      panel.classList.remove('ph-tabs-page-panel');
      void panel.offsetWidth;
      panel.classList.add('ph-tabs-page-panel');
    }
  }));

  const calendar = document.getElementById('bookingCalendar');
  if (calendar) {
    const events = JSON.parse(document.getElementById('bookingCalendarEvents')?.textContent || '[]');
    let month = new Date();
    month.setDate(1);
    const title = document.getElementById('bookingCalendarTitle');
    const renderCalendar = () => {
      const year = month.getFullYear();
      const monthIndex = month.getMonth();
      title.textContent = month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
      calendar.replaceChildren();
      ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach(day => {
        const label = document.createElement('div');
        label.className = 'calendar-weekday text-center small fw-semibold text-muted pb-2';
        label.textContent = day;
        calendar.append(label);
      });
      const offset = (new Date(year, monthIndex, 1).getDay() + 6) % 7;
      const count = new Date(year, monthIndex + 1, 0).getDate();
      for (let i = 0; i < offset + count; i++) {
        const cell = document.createElement('div');
        cell.className = '';
        if (i >= offset) {
          const day = i - offset + 1;
          const dateKey = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
          cell.classList.add('border', 'rounded', 'p-1');
          cell.style.minHeight = '88px';
          const number = document.createElement('div');
          number.className = 'small fw-semibold';
          number.textContent = day;
          cell.append(number);
          events.filter(event => event.date === dateKey).forEach(event => {
            const link = document.createElement('a');
            link.className = 'calendar-event d-block small text-truncate text-decoration-none mt-1';
            link.href = event.url;
            link.textContent = `${event.time} · ${event.title}`;
            link.title = `${event.time} · ${event.title}`;
            cell.append(link);
          });
        }
        calendar.append(cell);
      }
    };
    document.getElementById('calendarPrevious').addEventListener('click', () => { month.setMonth(month.getMonth() - 1); renderCalendar(); });
    document.getElementById('calendarNext').addEventListener('click', () => { month.setMonth(month.getMonth() + 1); renderCalendar(); });
    renderCalendar();
  }


  function formatTime(raw) {
    if (!raw) return '';
    const [hours, minutes] = raw.split(':');
    const hour = Number(hours);
    return `${hour % 12 || 12}:${minutes} ${hour >= 12 ? 'PM' : 'AM'}`;
  }
  const trimmed = time => (time || '').slice(0, 5);
  function setError(message) {
    errors.textContent = message || '';
    errors.hidden = !message;
  }
  function announce(type, message) {
    if (window.toast) window.toast(type, message);
  }
  function request(method, payload) {
    return $.ajax({
      url: endpoint, method, contentType: 'application/json',
      headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
      data: payload ? JSON.stringify(payload) : undefined,
    });
  }
  function renderTiles() {
    if (!weekTiles) return;
    weekTiles.replaceChildren();
    days.forEach((name, index) => {
      const slots = availability[index];
      const col = document.createElement('div'); col.className = 'col';
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `availability-tile ph-availability-day ${slots.length ? 'active' : ''}`;
      button.dataset.dayIndex = index;
      button.setAttribute('aria-label', `${name}: ${slots.length} available time slots. Edit availability`);
      const head = document.createElement('span'); head.className = 'ph-availability-day-head';
      const title = document.createElement('strong'); title.className = 'availability-day'; title.textContent = name;
      const count = document.createElement('span'); count.className = 'ph-availability-count';
      count.textContent = slots.length ? `${slots.length} ${slots.length === 1 ? 'slot' : 'slots'}` : 'Not set';
      head.append(title, count); button.append(head);
      const ranges = document.createElement('span'); ranges.className = 'ph-availability-ranges';
      if (slots.length) slots.forEach(slot => {
        const line = document.createElement('span'); line.className = 'ph-availability-range';
        const icon = document.createElement('i'); icon.className = 'bi bi-clock'; icon.setAttribute('aria-hidden', 'true');
        const time = document.createElement('span'); time.textContent = `${formatTime(slot.start_time)} – ${formatTime(slot.end_time)}`;
        line.append(icon, time); ranges.append(line);
      });
      else {
        const empty = document.createElement('span'); empty.className = 'availability-empty';
        empty.textContent = 'No availability set'; ranges.append(empty);
      }
      const edit = document.createElement('span'); edit.className = 'ph-availability-edit';
      edit.textContent = slots.length ? 'Edit availability' : 'Add availability';
      button.append(ranges, edit); col.append(button); weekTiles.append(col);
    });
  }
  function setSchedule(records) {
    availability.forEach(list => list.splice(0));
    for (const item of records || []) {
      const day = Number(item.day_index);
      if (day < 0 || day > 6 || !Number.isInteger(day)) continue;
      availability[day].push({start_time:trimmed(item.start_time),end_time:trimmed(item.end_time)});
    }
    availability.forEach(slots => slots.sort((a,b) => a.start_time.localeCompare(b.start_time)));
    renderTiles();
  }
  function loadSchedule() {
    return $.getJSON('/schedule/data').done(data => setSchedule(data.schedule || []))
      .fail(() => { announce('error', 'Could not load your availability. Reload this page.'); });
  }
  function createSlotRow(start = '', end = '') {
    const row = document.createElement('div'); row.className = 'ph-availability-slot';
    const heading = document.createElement('div'); heading.className = 'd-flex justify-content-between align-items-center gap-2 mb-2';
    const indexLabel = document.createElement('strong'); indexLabel.className = 'ph-availability-slot-label small';
    const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger ph-slot-remove';
    const trash = document.createElement('i'); trash.className = 'bi bi-trash me-1'; trash.setAttribute('aria-hidden','true');
    remove.append(trash, document.createTextNode('Remove')); remove.addEventListener('click', () => {
      row.remove(); numberRows(); setError('');
    });
    heading.append(indexLabel, remove);
    const grid = document.createElement('div'); grid.className = 'row g-2';
    [['Start time',start,'ph-slot-start'], ['End time',end,'ph-slot-end']].forEach(([labelText, value, cls]) => {
      const wrapper = document.createElement('div'); wrapper.className = 'col-6';
      const label = document.createElement('label'); label.className = 'form-label small'; label.textContent = labelText;
      const input = document.createElement('input'); input.className = `form-control ${cls}`;
      input.type = 'time'; input.required = true; input.step = 60; input.value = value;
      const id = `ph-availability-${Math.random().toString(36).slice(2)}`;
      input.id = id; label.htmlFor = id;
      wrapper.append(label, input); grid.append(wrapper);
    });
    row.append(heading,grid); slotsContainer.append(row); numberRows();
  }
  function numberRows() {
    const rows = [...slotsContainer.querySelectorAll('.ph-availability-slot')];
    rows.forEach((row,index) => {
      row.querySelector('.ph-availability-slot-label').textContent = `Time slot ${index+1}`;
    });
    addSlot.disabled = rows.length >= maxSlots;
    document.getElementById('phAvailabilitySlotCount').textContent = `${rows.length} ${rows.length === 1 ? 'slot' : 'slots'}`;
  }
  function openModal(day) {
    if (!Number.isInteger(day) || day < 0 || day > 6) return;
    selectedDay.value = day; selectedDayLabel.value = days[day];
    slotsContainer.replaceChildren(); setError('');
    (availability[day].length ? availability[day] : [{start_time:'',end_time:''}])
      .forEach(slot => createSlotRow(slot.start_time, slot.end_time));
    window.showModal?.('availabilityModal');
  }
  weekTiles?.addEventListener('click', event => {
    const tile = event.target.closest('[data-day-index]');
    if (tile) openModal(Number(tile.dataset.dayIndex));
  });
  addSlot?.addEventListener('click', () => { if (slotsContainer.children.length < maxSlots) createSlotRow(); });
  form?.addEventListener('submit', async event => {
    event.preventDefault(); setError('');
    const dayIndex = Number(selectedDay.value);
    const rows = [...slotsContainer.querySelectorAll('.ph-availability-slot')];
    const slots = [];
    for (const row of rows) {
      const a = row.querySelector('.ph-slot-start'); const b = row.querySelector('.ph-slot-end');
      if (!a.value || !b.value) {
        setError('Enter a start and end time for every slot.'); (!a.value ? a : b).focus(); return;
      }
      if (a.value >= b.value) { setError('The end time must be later than the start time.'); b.focus(); return; }
      slots.push({start_time:a.value, end_time:b.value});
    }
    slots.sort((a,b) => a.start_time.localeCompare(b.start_time));
    for(let i=1;i<slots.length;i++) if (slots[i].start_time < slots[i-1].end_time) {
      setError('Time slots cannot overlap. You can use consecutive time slots.'); return;
    }
    if (!slots.length) { setError('Use Clear day to remove all availability.'); return; }
    save.disabled = true;
    try {
      await request('POST', {day_index:dayIndex,slots});
      await loadSchedule();
      window.hideModal?.('availabilityModal');
      announce('success', 'Availability saved.');
    } catch(xhr) {
      const validation = xhr.responseJSON?.errors;
      const message = validation ? Object.values(validation).flat()[0] : 'Could not save your availability. Please try again.';
      setError(message); announce('error', message);
    } finally { save.disabled = false; }
  });
  clearDay?.addEventListener('click', async () => {
    const dayIndex = Number(selectedDay.value);
    if (!Number.isInteger(dayIndex) || dayIndex < 0 || dayIndex > 6) return;
    if (typeof window.confirmAction !== 'function') {
      setError('Confirmation dialog is unavailable. Reload the page and try again.');
      return;
    }
    const result = await window.confirmAction(
      `Remove all availability for ${days[dayIndex]}? This action cannot be undone.`,
      `Clear ${days[dayIndex]} availability?`,
      '#availabilityModal'
    );
    if (!result?.isConfirmed) return;
    clearDay.disabled = true;
    try {
      await request('DELETE', {day_index:dayIndex});
      await loadSchedule();
      window.hideModal?.('availabilityModal'); announce('success', 'Availability cleared.');
    } catch { setError('Could not clear your availability. Please try again.'); }
    finally { clearDay.disabled = false; }
  });
  loadSchedule();
  if (window.location.hash === '#bookings') {
    const tab = document.getElementById('bookingsTab');
    if (tab) window.bootstrap?.Tab.getOrCreateInstance(tab).show();
  }
});
