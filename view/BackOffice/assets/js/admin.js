// ── CUSTOM SELECT ──
function buildCustomSelect(sel) {
  const wrapper = document.createElement('div');
  wrapper.className = 'custom-select';

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'custom-select-btn';

  const label = document.createElement('span');
  label.className = 'custom-select-label';
  label.textContent = sel.options[sel.selectedIndex]?.text || '';

  const arrow = document.createElement('span');
  arrow.className = 'custom-select-arrow';
  arrow.innerHTML = `<svg viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

  btn.appendChild(label);
  btn.appendChild(arrow);

  const panel = document.createElement('div');
  panel.className = 'custom-select-panel';
  panel._wrapper = wrapper;
  document.body.appendChild(panel);

  function syncLabel() {
    const idx = sel.selectedIndex;
    if (idx >= 0) label.textContent = sel.options[idx].text;
    panel.querySelectorAll('.custom-select-option').forEach(o => {
      o.classList.toggle('selected', o.dataset.value === sel.value);
    });
  }

  function positionPanel() {
    const r = btn.getBoundingClientRect();
    panel.style.top     = (r.bottom + 7) + 'px';
    panel.style.left    = r.left + 'px';
    panel.style.minWidth = Math.max(r.width, 140) + 'px';
  }

  Array.from(sel.options).forEach(opt => {
    const item = document.createElement('button');
    item.type = 'button';
    item.className = 'custom-select-option' + (opt.selected ? ' selected' : '');
    item.dataset.value = opt.value;
    item.textContent = opt.text;
    item.addEventListener('click', () => {
      sel.value = opt.value;
      panel.classList.remove('open');
      wrapper.classList.remove('open');
      sel.dispatchEvent(new Event('change'));
    });
    panel.appendChild(item);
  });

  btn.addEventListener('click', e => {
    e.stopPropagation();
    const opening = !panel.classList.contains('open');
    closeAllCustomSelects();
    if (opening) { positionPanel(); panel.classList.add('open'); wrapper.classList.add('open'); }
  });

  // Override .value setter so programmatic assignments (openEditModal, etc.) sync the label
  const proto = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
  Object.defineProperty(sel, 'value', {
    get() { return proto.get.call(this); },
    set(v) { proto.set.call(this, v); syncLabel(); },
    configurable: true,
  });

  wrapper.appendChild(btn);
  sel.style.display = 'none';
  sel.parentNode.insertBefore(wrapper, sel);
}

function closeAllCustomSelects() {
  document.querySelectorAll('.custom-select-panel.open').forEach(p => {
    p.classList.remove('open');
    if (p._wrapper) p._wrapper.classList.remove('open');
  });
}

document.addEventListener('click', closeAllCustomSelects);

document.addEventListener("DOMContentLoaded", () => {
  const currentPage = window.location.pathname.split("/").pop();

  document.querySelectorAll(".nav-item").forEach((link) => {
    link.classList.toggle("active", link.getAttribute("href") === currentPage);
  });

  document.querySelectorAll(".filter, .status-chip, .link-btn").forEach((item) => {
    item.addEventListener("click", () => {
      const className = item.classList[0];
      item.parentElement?.querySelectorAll(`.${className}`).forEach((node) => {
        node.classList.remove("is-selected");
      });
      item.classList.add("is-selected");
    });
  });

  document.querySelectorAll(".searchbar input").forEach((input) => {
    input.addEventListener("focus", () => input.closest(".searchbar")?.classList.add("is-focused"));
    input.addEventListener("blur", () => input.closest(".searchbar")?.classList.remove("is-focused"));
  });

  document.querySelectorAll('.pill-select').forEach(buildCustomSelect);
});
