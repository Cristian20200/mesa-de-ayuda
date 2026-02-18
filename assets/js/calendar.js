(function () {
  const mount = document.getElementById('calendarWidget') || document.getElementById('calendar');
  if (!mount) return;

  const events = Array.isArray(window.ticketEvents) ? window.ticketEvents : [];
  const days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  let cursor = new Date();

  function normalizeDate(date) {
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return null;
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function groupEvents() {
    const map = new Map();
    events.forEach((ticket) => {
      const createdKey = normalizeDate(ticket.created_at);
      const scheduledKey = normalizeDate(ticket.scheduled_date);
      if (createdKey) {
        const arr = map.get(createdKey) || [];
        arr.push({ label: `#${ticket.id} registrado`, type: 'created' });
        map.set(createdKey, arr);
      }
      if (scheduledKey) {
        const arr = map.get(scheduledKey) || [];
        arr.push({ label: `#${ticket.id} planificado`, type: 'scheduled' });
        map.set(scheduledKey, arr);
      }
    });
    return map;
  }

  const grouped = groupEvents();

  function render() {
    const year = cursor.getFullYear();
    const month = cursor.getMonth();
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    const startDay = (first.getDay() + 6) % 7;

    mount.innerHTML = '';

    const controls = document.createElement('div');
    controls.className = 'calendar-controls';
    controls.innerHTML = `
      <button class="btn ghost" data-nav="prev">◀</button>
      <strong>${first.toLocaleString('es-ES', { month: 'long', year: 'numeric' })}</strong>
      <button class="btn ghost" data-nav="next">▶</button>
    `;

    const grid = document.createElement('div');
    grid.className = 'calendar-grid';

    days.forEach((day) => {
      const header = document.createElement('div');
      header.className = 'calendar-weekday';
      header.textContent = day;
      grid.appendChild(header);
    });

    for (let i = 0; i < startDay; i += 1) {
      const cell = document.createElement('div');
      cell.className = 'calendar-cell';
      grid.appendChild(cell);
    }

    for (let day = 1; day <= last.getDate(); day += 1) {
      const key = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const cell = document.createElement('div');
      cell.className = 'calendar-cell';
      cell.innerHTML = `<div class="date">${day}</div>`;

      (grouped.get(key) || []).forEach((event) => {
        const item = document.createElement('div');
        item.className = `event ${event.type}`;
        item.textContent = event.label;
        cell.appendChild(item);
      });

      grid.appendChild(cell);
    }

    controls.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const nav = target.getAttribute('data-nav');
      if (nav === 'prev') cursor = new Date(year, month - 1, 1);
      if (nav === 'next') cursor = new Date(year, month + 1, 1);
      render();
    });

    mount.appendChild(controls);
    mount.appendChild(grid);
  }

  render();
})();
