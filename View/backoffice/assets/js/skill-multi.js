(function () {
  function isJsonLike(value) {
    const text = String(value || '').trim();
    return text.startsWith('[') || text.startsWith('{');
  }

  function normalizeText(value) {
    return String(value || '').trim();
  }

  function splitDelimitedSkills(value) {
    return String(value || '')
      .split(/[,;|•\n]+/)
      .map((skill) => skill.trim())
      .filter(Boolean);
  }

  function dedupeRows(rows) {
    const seen = new Set();
    const out = [];
    rows.forEach((row) => {
      const key = normalizeText(row.name || row.skillName || row.value).toLowerCase();
      if (!key || seen.has(key)) {
        return;
      }
      seen.add(key);
      out.push({
        name: normalizeText(row.name || row.skillName || row.value),
        level: row.level ?? '50',
        primary: row.primary ? 1 : 0,
      });
    });
    return out;
  }

  function parseSkillValue(value) {
    const raw = normalizeText(value);
    if (!raw) {
      return [];
    }

    if (isJsonLike(raw)) {
      try {
        const parsed = JSON.parse(raw);
        const list = Array.isArray(parsed) ? parsed : [parsed];
        return dedupeRows(
          list
            .map((item) => {
              if (typeof item === 'string') {
                return { name: item, level: '50', primary: 0 };
              }
              if (item && typeof item === 'object') {
                return {
                  name: item.name ?? item.skillName ?? item.value ?? '',
                  level: item.level ?? item.requiredLevel ?? '50',
                  primary: item.primary ?? item.isPrimary ?? 0,
                };
              }
              return { name: '', level: '50', primary: 0 };
            })
        );
      } catch (error) {
        // Fall back to delimited text if JSON decoding fails.
      }
    }

    return dedupeRows(
      splitDelimitedSkills(raw).map((name, index) => ({
        name,
        level: '50',
        primary: index === 0 ? 1 : 0,
      }))
    );
  }

  function serializeRows(container, rows) {
    const normalized = dedupeRows(rows);
    const useJson = container.dataset.skillStore === 'json'
      || container.dataset.skillWithLevel === '1'
      || container.dataset.skillWithPrimary === '1';

    if (useJson) {
      return JSON.stringify(normalized.map((row) => ({
        name: row.name,
        level: row.level ?? '50',
        primary: row.primary ? 1 : 0,
      })));
    }

    return normalized.map((row) => row.name).join(', ');
  }

  function getRows(container) {
    const list = container.querySelector('.skill-multi-list');
    if (!list) {
      return [];
    }

    return Array.from(list.querySelectorAll('.skill-multi-row')).map((rowEl) => {
      const nameInput = rowEl.querySelector('.skill-multi-input');
      const levelSelect = rowEl.querySelector('.skill-multi-level');
      const primaryCheck = rowEl.querySelector('.skill-multi-primary');
      return {
        name: normalizeText(nameInput?.value),
        level: levelSelect ? levelSelect.value : '50',
        primary: primaryCheck ? (primaryCheck.checked ? 1 : 0) : 0,
      };
    }).filter((row) => row.name);
  }

  function getTargetInput(container) {
    const targetId = container.dataset.skillTarget;
    return targetId ? document.getElementById(targetId) : null;
  }

  function syncContainer(container) {
    const target = getTargetInput(container);
    if (!target) {
      return '';
    }

    const value = serializeRows(container, getRows(container));
    target.value = value;
    return value;
  }

  function buildRow(container, value) {
    const list = container.querySelector('.skill-multi-list');
    if (!list) {
      return null;
    }

    const supportsLevel = container.dataset.skillWithLevel === '1';
    const supportsPrimary = container.dataset.skillWithPrimary === '1';
    const rowData = typeof value === 'object' && value !== null
      ? {
          name: normalizeText(value.name || value.skillName || value.value),
          level: value.level ?? value.requiredLevel ?? '50',
          primary: value.primary ?? value.isPrimary ?? 0,
        }
      : { name: normalizeText(value), level: '50', primary: 0 };

    const row = document.createElement('div');
    row.className = 'skill-multi-row';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'skill-multi-input';
    input.placeholder = container.dataset.skillPlaceholder || 'Search or type a skill';
    if (container.dataset.skillDatalist) {
      input.setAttribute('list', container.dataset.skillDatalist);
    }
    input.value = rowData.name;

    input.addEventListener('input', () => syncContainer(container));
    input.addEventListener('change', () => syncContainer(container));

    row.appendChild(input);

    if (supportsLevel) {
      const level = document.createElement('select');
      level.className = 'skill-multi-level';
      [
        { value: '20', label: 'Beginner' },
        { value: '50', label: 'Intermediate' },
        { value: '80', label: 'Advanced' },
      ].forEach((opt) => {
        const option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.label;
        if (String(rowData.level) === opt.value) {
          option.selected = true;
        }
        level.appendChild(option);
      });
      level.addEventListener('change', () => syncContainer(container));
      row.appendChild(level);
    }

    if (supportsPrimary) {
      const primaryLabel = document.createElement('label');
      primaryLabel.className = 'skill-multi-primary-label';
      const primary = document.createElement('input');
      primary.type = 'checkbox';
      primary.className = 'skill-multi-primary';
      primary.checked = !!Number(rowData.primary);
      primary.addEventListener('change', () => {
        if (primary.checked) {
          container.querySelectorAll('.skill-multi-primary').forEach((checkbox) => {
            if (checkbox !== primary) {
              checkbox.checked = false;
            }
          });
        }
        syncContainer(container);
      });
      primaryLabel.appendChild(primary);
      primaryLabel.appendChild(document.createTextNode('Primary'));
      row.appendChild(primaryLabel);
    }

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'skill-multi-remove';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => {
      row.remove();
      if (!list.querySelector('.skill-multi-row')) {
        appendRow(container, { name: '', level: '50', primary: 0 });
      }
      syncContainer(container);
    });
    row.appendChild(remove);

    list.appendChild(row);
    return row;
  }

  function appendRow(container, value) {
    buildRow(container, value);
  }

  function renderFromValue(container, value) {
    const list = container.querySelector('.skill-multi-list');
    if (!list) {
      return;
    }

    list.innerHTML = '';
    const rows = parseSkillValue(value);
    if (rows.length) {
      rows.forEach((row) => buildRow(container, row));
    } else {
      buildRow(container, { name: '', level: '50', primary: 0 });
    }

    syncContainer(container);
  }

  function register(container) {
    if (!container || container._skillMultiRegistered) {
      return;
    }

    const target = getTargetInput(container);
    const list = container.querySelector('.skill-multi-list');
    if (!target || !list) {
      return;
    }

    container._skillMultiRegistered = true;
    container._skillMulti = {
      sync: () => syncContainer(container),
      set: (value) => renderFromValue(container, value),
      clear: () => renderFromValue(container, ''),
      values: () => getRows(container),
    };

    if (!target.value && container.dataset.initialValue) {
      target.value = container.dataset.initialValue;
    }

    renderFromValue(container, target.value);

    const addButton = container.querySelector('.skill-multi-add');
    if (addButton) {
      addButton.addEventListener('click', () => {
        appendRow(container, { name: '', level: '50', primary: 0 });
        syncContainer(container);
      });
    }

    const form = container.closest('form');
    if (form && !form._skillMultiBound) {
      form.addEventListener('submit', () => {
        document.querySelectorAll('[data-skill-multi]').forEach((multi) => {
          if (multi._skillMulti) {
            multi._skillMulti.sync();
          }
        });
      });
      form._skillMultiBound = true;
    }
  }

  function setSkillMultiValue(targetId, value) {
    const container = document.querySelector(`[data-skill-multi][data-skill-target="${targetId}"]`);
    const target = document.getElementById(targetId);
    const serialized = typeof value === 'string' ? value : JSON.stringify(value || []);
    if (target) {
      target.value = serialized;
    }
    if (container && container._skillMulti) {
      container._skillMulti.set(serialized);
      return;
    }
    if (container) {
      container.dataset.initialValue = serialized;
    }
  }

  function clearSkillMultiValue(targetId) {
    setSkillMultiValue(targetId, '');
  }

  function syncSkillMultiValue(targetId) {
    const container = document.querySelector(`[data-skill-multi][data-skill-target="${targetId}"]`);
    if (container && container._skillMulti) {
      return container._skillMulti.sync();
    }
    const target = document.getElementById(targetId);
    return target ? target.value : '';
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-skill-multi]').forEach(register);
  });

  window.SkillMulti = {
    parse: parseSkillValue,
    split: splitDelimitedSkills,
    set: setSkillMultiValue,
    clear: clearSkillMultiValue,
    sync: syncSkillMultiValue,
    values(targetId) {
      const container = document.querySelector(`[data-skill-multi][data-skill-target="${targetId}"]`);
      return container && container._skillMulti ? container._skillMulti.values() : [];
    },
  };
})();
