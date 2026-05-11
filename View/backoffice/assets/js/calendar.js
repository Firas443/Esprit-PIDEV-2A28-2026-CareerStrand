const COURSES = [];

const TRACK_COLORS = {
  Identity:      { pill:'pill-identity',      dot:'#95abeb', border:'#6f8fd8' },
  Learning:      { pill:'pill-learning',      dot:'#59d39b', border:'#59d39b' },
  Opportunity:   { pill:'pill-opportunity',   dot:'#f5bf65', border:'#f5bf65' },
  Design:        { pill:'pill-design',        dot:'#c4b5fd', border:'#a78bfa' },
  Communication: { pill:'pill-communication', dot:'#ff8564', border:'#ff6e45' },
  Technical:     { pill:'pill-technical',     dot:'#5eead4', border:'#2dd4bf' },
  Planned:       { pill:'pill-identity',      dot:'#95abeb', border:'#6f8fd8' },
  Ongoing:       { pill:'pill-learning',      dot:'#59d39b', border:'#59d39b' },
  Completed:     { pill:'pill-opportunity',   dot:'#f5bf65', border:'#f5bf65' },
};

let state   = {};
let now     = new Date();
let curYear = now.getFullYear();
let curMon  = now.getMonth();
let selDay  = null;

function scheduled() {
  return COURSES
    .filter(c => state[c.id]?.scheduled)
    .map(c => ({ ...c, ...state[c.id] }));
}

function fmtDate(str) {
  if (!str) return '—';
  const [y,m,d] = str.split('-');
  const mo = ['jan','fév','mar','avr','mai','jun','jul','aoû','sep','oct','nov','déc'];
  return `${parseInt(d)} ${mo[parseInt(m)-1]} ${y}`;
}

function eventsOnDate(year, month, day) {
  const d = new Date(year, month, day);
  return scheduled().filter(c => {
    const s = new Date(c.start + 'T00:00:00');
    const e = new Date(c.end   + 'T00:00:00');
    return d >= s && d <= e;
  });
}

function renderCalendar() {
  const grid  = document.getElementById('cal-grid');
  const title = document.getElementById('month-title');
  const months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                  'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  title.textContent = months[curMon] + ' ' + curYear;
  grid.innerHTML = '';

  const firstDay    = new Date(curYear, curMon, 1).getDay();
  const offset      = firstDay === 0 ? 6 : firstDay - 1;
  const daysInMonth = new Date(curYear, curMon + 1, 0).getDate();
  const daysInPrev  = new Date(curYear, curMon, 0).getDate();
  const totalCells  = Math.ceil((offset + daysInMonth) / 7) * 7;

  for (let i = 0; i < totalCells; i++) {
    let day, mo = curMon, yr = curYear, other = false;

    if (i < offset) {
      day = daysInPrev - offset + i + 1;
      mo  = curMon - 1; if (mo < 0) { mo = 11; yr--; }
      other = true;
    } else if (i >= offset + daysInMonth) {
      day = i - offset - daysInMonth + 1;
      mo  = curMon + 1; if (mo > 11) { mo = 0; yr++; }
      other = true;
    } else {
      day = i - offset + 1;
    }

    const isToday = !other && day === now.getDate() && curMon === now.getMonth() && curYear === now.getFullYear();
    const isSel   = selDay && !other && selDay.day === day && selDay.month === curMon && selDay.year === curYear;
    const evs     = other ? [] : eventsOnDate(yr, mo, day);

    let pillsHtml = evs.slice(0,2).map(e => {
      const tc = TRACK_COLORS[e.track] || TRACK_COLORS.Planned;
      return `<div class="ev-pill ${tc.pill}" onclick="clickPill(event,${yr},${mo},${day})">${e.title}</div>`;
    }).join('');
    if (evs.length > 2) pillsHtml += `<div class="ev-more">+${evs.length - 2}</div>`;

    const cls = ['cal-day', isToday?'today':'', other?'other-month':'', isSel?'selected':''].filter(Boolean).join(' ');
    const cell = document.createElement('div');
    cell.className = cls;
    cell.innerHTML = `<div class="day-num">${day}</div><div class="day-events">${pillsHtml}</div>`;
    if (!other) cell.addEventListener('click', () => selectDay(yr, mo, day));
    grid.appendChild(cell);
  }

  renderStats();
  renderLegend();
}

function clickPill(e, yr, mo, day) { e.stopPropagation(); selectDay(yr, mo, day); }

function selectDay(yr, mo, day) {
  selDay = { year: yr, month: mo, day };
  switchTab('day');
  renderCalendar();
  renderDayDetail();
}

function renderDayDetail() {
  if (!selDay) return;
  const { year, month, day } = selDay;
  const months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                  'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  document.getElementById('detail-title').textContent = `${day} ${months[month]} ${year}`;

  const evs = eventsOnDate(year, month, day);
  const el  = document.getElementById('detail-events');

  if (evs.length === 0) {
    el.innerHTML = '<div class="empty-state">Aucun cours actif ce jour.</div>';
    return;
  }

  el.innerHTML = evs.map(ev => {
    const tc   = TRACK_COLORS[ev.track] || TRACK_COLORS.Planned;
    const days = Math.round((new Date(ev.end) - new Date(ev.start)) / 86400000) + 1;
    return `
      <div class="detail-ev" style="border-left-color:${tc.border}">
        <div class="detail-ev-title">${ev.title}</div>
        <div class="detail-ev-track">${ev.track} · ${days} jours · ${ev.Progress ?? 0}%</div>
        <div class="detail-ev-dates">
          <span class="range-pill">📅 ${fmtDate(ev.start)}</span>
          <span class="range-pill">→ ${fmtDate(ev.end)}</span>
        </div>
      </div>`;
  }).join('');
}

function renderAllCourses() {
  const el = document.getElementById('all-courses-list');
  el.innerHTML = COURSES.map(c => {
    const s  = state[c.id] || {};
    const tc = TRACK_COLORS[c.track] || TRACK_COLORS.Planned;
    const dateInfo = s.scheduled
      ? `<div class="course-item-dates">📅 ${fmtDate(s.start)}<br>→ ${fmtDate(s.end)}</div>`
      : `<div class="not-scheduled">Non planifié</div>`;
    return `
      <div class="course-item">
        <div class="course-item-top">
          <div class="course-item-name">${c.title}</div>
          <span class="ev-pill ${tc.pill}" style="flex-shrink:0">${c.track}</span>
        </div>
        ${dateInfo}
      </div>`;
  }).join('');
}

function renderLegend() {
  const tracks = [...new Set(COURSES.map(c => c.track))];
  const el = document.getElementById('legend-grid');
  el.innerHTML = tracks.map(t => {
    const tc = TRACK_COLORS[t] || TRACK_COLORS.Planned;
    return `<div class="legend-chip"><div class="legend-dot" style="background:${tc.dot}"></div>${t}</div>`;
  }).join('');
}

function renderStats() {
  const today    = new Date();
  const sched    = scheduled();
  const planned  = sched.length;
  const active   = sched.filter(c => {
    const s = new Date(c.start + 'T00:00:00');
    const e = new Date(c.end   + 'T00:00:00');
    return today >= s && today <= e;
  }).length;
  const upcoming = sched.filter(c => new Date(c.start + 'T00:00:00') > today).length;
  const done     = sched.filter(c => new Date(c.end   + 'T00:00:00') < today).length;

  document.getElementById('s-planned').textContent     = planned;
  document.getElementById('s-planned-sub').textContent = `sur ${COURSES.length} cours`;
  document.getElementById('s-active').textContent      = active;
  document.getElementById('s-upcoming').textContent    = upcoming;
  document.getElementById('s-done').textContent        = done;
}

function switchTab(tab) {
  document.getElementById('tab-day').classList.toggle('active', tab === 'day');
  document.getElementById('tab-courses').classList.toggle('active', tab === 'courses');
  document.getElementById('panel-day').style.display     = tab === 'day'     ? 'block' : 'none';
  document.getElementById('panel-courses').style.display = tab === 'courses' ? 'block' : 'none';
  if (tab === 'courses') renderAllCourses();
  if (tab === 'day' && selDay) renderDayDetail();
}

function prevMonth() { curMon--; if (curMon < 0) { curMon = 11; curYear--; } renderCalendar(); }
function nextMonth() { curMon++; if (curMon > 11) { curMon = 0;  curYear++; } renderCalendar(); }
function goToday()   { curYear = now.getFullYear(); curMon = now.getMonth(); renderCalendar(); }