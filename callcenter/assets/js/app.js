/* ============================================================
   app.js — Ovijat Call Center
   Autocomplete, duplicate check, toasts, smart hints, auto-format
   ============================================================ */

'use strict';

/* ── Toast notification ─────────────────────────────────── */
function showToast(msg, type = 'success', duration = 4000) {
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const colors = { success:'#198754', danger:'#dc3545', warning:'#fd7e14', info:'#0dcaf0' };
  const id = 'toast_' + Date.now();
  const html = `
    <div id="${id}" class="toast align-items-center border-0 text-white show"
         style="background:${colors[type]||colors.info};margin-bottom:.5rem"
         role="alert" aria-live="assertive">
      <div class="d-flex">
        <div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
      </div>
    </div>`;
  container.insertAdjacentHTML('beforeend', html);
  const el = document.getElementById(id);
  setTimeout(() => { el && el.remove(); }, duration);
}

/* ── Auto-dismiss flash alerts ──────────────────────────── */
document.querySelectorAll('.flash-alert').forEach(el => {
  setTimeout(() => {
    const bs = bootstrap.Alert.getOrCreateInstance(el);
    bs && bs.close();
  }, 5000);
});

/* ── Phone auto-format ──────────────────────────────────── */
function formatPhone(val) {
  const digits = val.replace(/\D/g, '');
  if (digits.length === 11 && digits.startsWith('0')) {
    return digits.slice(0,3) + '-' + digits.slice(3,7) + '-' + digits.slice(7);
  }
  return val;
}
document.addEventListener('blur', function(e) {
  if (e.target.matches('input[data-phone]')) {
    e.target.value = formatPhone(e.target.value);
  }
}, true);

/* ── Name auto-capitalize ───────────────────────────────── */
document.addEventListener('blur', function(e) {
  if (e.target.matches('input[data-capitalize]')) {
    e.target.value = e.target.value.replace(/\b\w/g, c => c.toUpperCase());
  }
}, true);

/* ── Autocomplete widget ─────────────────────────────────── */
class Autocomplete {
  constructor(input, options = {}) {
    this.input      = input;
    this.idField    = options.idField    || (input.dataset.acIdField || null);
    this.source     = options.source    || input.dataset.autocomplete;
    this.minChars   = options.minChars  || 1;
    this.onSelect   = options.onSelect  || null;
    this.onContactSelect = options.onContactSelect || null;
    this.dropdown   = null;
    this.timer      = null;
    this.activeIdx  = -1;
    this._build();
  }

  _build() {
    this.input.setAttribute('autocomplete', 'off');
    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative;display:contents';
    this.input.parentNode.insertBefore(wrap, this.input);
    wrap.appendChild(this.input);

    this.dropdown = document.createElement('div');
    this.dropdown.className = 'ac-dropdown';
    this.dropdown.style.display = 'none';
    // Position relative to input
    this.input.parentNode.style.position = 'relative';
    this.input.parentNode.appendChild(this.dropdown);

    this.input.addEventListener('input', () => {
      clearTimeout(this.timer);
      const q = this.input.value.trim();
      if (q.length < this.minChars) { this.hide(); return; }
      this.timer = setTimeout(() => this._fetch(q), 280);
    });

    this.input.addEventListener('keydown', e => {
      const items = this.dropdown.querySelectorAll('.ac-item');
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        this.activeIdx = Math.min(this.activeIdx + 1, items.length - 1);
        this._highlight(items);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        this.activeIdx = Math.max(this.activeIdx - 1, 0);
        this._highlight(items);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (this.activeIdx >= 0 && items[this.activeIdx]) {
          items[this.activeIdx].click();
        }
      } else if (e.key === 'Escape') {
        this.hide();
      }
    });

    document.addEventListener('click', e => {
      if (!this.input.contains(e.target) && !this.dropdown.contains(e.target)) this.hide();
    });
  }

  _highlight(items) {
    items.forEach((el, i) => el.classList.toggle('focused', i === this.activeIdx));
  }

  async _fetch(q) {
    let url = '';
    if (this.source === 'contacts') {
      url = `${APP.apiUrl}?action=search_contacts&q=${encodeURIComponent(q)}`;
    } else if (this.source === 'users') {
      url = `${APP.apiUrl}?action=search_users&q=${encodeURIComponent(q)}`;
    } else if (this.source === 'outcomes') {
      url = `${APP.apiUrl}?action=search_outcomes&q=${encodeURIComponent(q)}`;
    } else if (this.source === 'campaigns') {
      url = `${APP.apiUrl}?action=search_campaigns&q=${encodeURIComponent(q)}`;
    } else {
      return;
    }

    try {
      const resp = await fetch(url, { headers: { 'X-CSRF-Token': APP.csrfToken } });
      const data = await resp.json();
      this._render(data);
    } catch(e) {
      // Silently fail — may be offline
    }
  }

  _render(items) {
    this.dropdown.innerHTML = '';
    this.activeIdx = -1;
    if (!items || items.length === 0) {
      this.dropdown.innerHTML = '<div class="ac-item text-muted">No results found</div>';
      this.show();
      return;
    }
    items.forEach(item => {
      const el = document.createElement('div');
      el.className = 'ac-item';
      el.innerHTML = this._itemHtml(item);
      el.addEventListener('click', () => this._select(item));
      this.dropdown.appendChild(el);
    });
    this.show();
  }

  _itemHtml(item) {
    if (this.source === 'contacts') {
      const last = item.last_interaction ? `<span>Last: ${item.last_interaction}</span>` : '';
      return `<div class="ac-name">${esc(item.name)}</div>
              <div class="ac-meta d-flex gap-2">
                <span>${esc(item.phone||'')}</span>
                ${item.contact_type ? `<span class="badge bg-secondary" style="font-size:.65rem">${esc(item.contact_type)}</span>` : ''}
                ${last}
              </div>`;
    }
    if (this.source === 'users') {
      return `<div class="ac-name">${esc(item.name)}</div>
              <div class="ac-meta">${esc(item.role||'')}</div>`;
    }
    if (this.source === 'outcomes') {
      return `<div class="ac-name d-flex align-items-center gap-2">
                <span style="width:10px;height:10px;border-radius:50%;background:${esc(item.color||'#999')};display:inline-block"></span>
                ${esc(item.name)}
              </div>`;
    }
    if (this.source === 'campaigns') {
      return `<div class="ac-name">${esc(item.name)}</div>
              <div class="ac-meta">${esc(item.status||'')}</div>`;
    }
    return `<div class="ac-name">${esc(item.name||item.label||'')}</div>`;
  }

  _select(item) {
    this.input.value = item.name || item.label || '';
    if (this.idField) {
      const hidden = document.getElementById(this.idField) || document.querySelector(`[name="${this.idField}"]`);
      if (hidden) hidden.value = item.id || '';
    }
    this.hide();
    if (this.onSelect) this.onSelect(item);
    if (this.source === 'contacts' && typeof window.onContactSelected === 'function') {
      window.onContactSelected(item);
    }
  }

  show() { this.dropdown.style.display = 'block'; }
  hide() { this.dropdown.style.display = 'none'; }
}

function esc(s) {
  const d = document.createElement('div');
  d.textContent = s || '';
  return d.innerHTML;
}

/* ── Initialize autocomplete inputs ────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('input[data-autocomplete]').forEach(el => {
    new Autocomplete(el);
  });
});

/* ── Global search ──────────────────────────────────────── */
(function() {
  const searchInput   = document.getElementById('globalSearch');
  const searchResults = document.getElementById('globalSearchResults');
  if (!searchInput || !searchResults) return;

  let timer = null;
  searchInput.addEventListener('input', function() {
    clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 2) { searchResults.style.display = 'none'; return; }
    timer = setTimeout(async () => {
      try {
        const resp = await fetch(`${APP.apiUrl}?action=global_search&q=${encodeURIComponent(q)}`);
        const data = await resp.json();
        renderGlobalSearch(data);
      } catch(e) {}
    }, 300);
  });

  function renderGlobalSearch(data) {
    searchResults.innerHTML = '';
    const contacts = data.contacts || [];
    if (contacts.length === 0) {
      searchResults.innerHTML = '<div class="p-3 text-muted small">No results found</div>';
    } else {
      contacts.slice(0, 8).forEach(c => {
        const item = document.createElement('a');
        item.className = 'dropdown-item d-flex flex-column py-2';
        item.href = `${APP.baseUrl}/modules/contacts/view.php?id=${c.id}`;
        item.innerHTML = `
          <span class="fw-semibold">${esc(c.name)}</span>
          <span class="text-muted" style="font-size:.72rem">${esc(c.phone||'')}${c.company?' · '+esc(c.company):''}</span>`;
        searchResults.appendChild(item);
      });
      if (data.total > 8) {
        const more = document.createElement('a');
        more.className = 'dropdown-item text-primary small text-center py-2';
        more.href = `${APP.baseUrl}/modules/contacts/index.php?q=${encodeURIComponent(searchInput.value)}`;
        more.textContent = `View all ${data.total} results →`;
        searchResults.appendChild(more);
      }
    }
    searchResults.style.display = 'block';
  }

  document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.style.display = 'none';
    }
  });
})();

/* ── Duplicate check ─────────────────────────────────────── */
async function checkDuplicate(name, phone, callback) {
  try {
    const resp = await fetch(`${APP.apiUrl}?action=check_duplicate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': APP.csrfToken
      },
      body: `name=${encodeURIComponent(name)}&phone=${encodeURIComponent(phone)}&csrf_token=${APP.csrfToken}`
    });
    const data = await resp.json();
    callback(data);
  } catch(e) {
    callback({ found: false, matches: [] });
  }
}

function showDuplicateModal(matches, onConfirm) {
  let existing = document.getElementById('dupModal');
  if (existing) existing.remove();

  const rows = matches.map(m =>
    `<tr>
      <td><a href="${APP.baseUrl}/modules/contacts/view.php?id=${m.id}" target="_blank">${esc(m.name)}</a></td>
      <td>${esc(m.phone)}</td>
      <td>${esc(m.contact_type||'')}</td>
      <td><a href="${APP.baseUrl}/modules/contacts/form.php?id=${m.id}" class="btn btn-sm btn-outline-primary">Use</a></td>
    </tr>`
  ).join('');

  const modal = document.createElement('div');
  modal.innerHTML = `
  <div class="modal fade" id="dupModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header border-warning">
          <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Similar Contacts Found</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-2">These contacts have similar name or phone. You may want to use an existing one:</p>
          <table class="table table-sm table-hover">
            <thead><tr><th>Name</th><th>Phone</th><th>Type</th><th></th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-warning" id="dupConfirmBtn">
            Create Anyway
          </button>
        </div>
      </div>
    </div>
  </div>`;
  document.body.appendChild(modal);

  const bsModal = new bootstrap.Modal(document.getElementById('dupModal'));
  bsModal.show();
  document.getElementById('dupConfirmBtn').addEventListener('click', function() {
    bsModal.hide();
    onConfirm();
  });
}

/* ── Contact form duplicate interception ────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('contactForm');
  if (!form) return;

  form.addEventListener('submit', async function(e) {
    const forceFld = form.querySelector('[name="force_create"]');
    if (forceFld && forceFld.value === '1') return; // already confirmed

    const name  = (form.querySelector('[name="name"]') || {}).value  || '';
    const phone = (form.querySelector('[name="phone"]') || {}).value || '';
    const isEdit = form.querySelector('[name="id"]') && form.querySelector('[name="id"]').value;
    if (isEdit) return; // skip duplicate check on edit

    e.preventDefault();
    checkDuplicate(name, phone, function(data) {
      if (data.found && data.matches.length > 0) {
        showDuplicateModal(data.matches, function() {
          if (forceFld) forceFld.value = '1';
          else {
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = 'force_create'; h.value = '1';
            form.appendChild(h);
          }
          form.submit();
        });
      } else {
        form.submit();
      }
    });
  });
});

/* ── Confirm delete ──────────────────────────────────────── */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  e.preventDefault();
  const msg = btn.dataset.confirm || 'Are you sure?';
  if (confirm(msg)) {
    const form = btn.closest('form') || btn.form;
    if (form) form.submit();
    else window.location = btn.href;
  }
});

/* ── Copy for WhatsApp ───────────────────────────────────── */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-copy-wa]');
  if (!btn) return;
  const target = document.getElementById(btn.dataset.copyWa) ||
                 document.querySelector(btn.dataset.copyWa);
  const text = target ? target.textContent : btn.dataset.text;
  if (!text) return;
  navigator.clipboard.writeText(text.trim()).then(() => {
    showToast('Copied to clipboard! Paste in WhatsApp.', 'success', 3000);
  }).catch(() => {
    // Fallback
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('Copied!', 'success', 3000);
  });
});

/* ── Bulk WhatsApp copy ──────────────────────────────────── */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('#bulkCopyWA');
  if (!btn) return;
  const checked = document.querySelectorAll('.row-check:checked');
  if (checked.length === 0) { showToast('Select at least one row', 'warning'); return; }
  const texts = Array.from(checked).map(cb => {
    const row = cb.closest('tr');
    return row ? row.dataset.waText || row.textContent.replace(/\s+/g,' ').trim() : '';
  }).filter(Boolean).join('\n---\n');
  navigator.clipboard.writeText(texts).then(() => {
    showToast(`Copied ${checked.length} items for WhatsApp`, 'success');
  });
});

/* ── Print button ────────────────────────────────────────── */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-print]');
  if (!btn) return;
  window.print();
});

/* ── Table inline filter ─────────────────────────────────── */
document.addEventListener('input', function(e) {
  if (!e.target.matches('[data-filter-table]')) return;
  const tableId = e.target.dataset.filterTable;
  const table   = document.getElementById(tableId);
  if (!table) return;
  const q = e.target.value.toLowerCase();
  table.querySelectorAll('tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

/* ── Tooltip init ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    bootstrap.Tooltip.getOrCreateInstance(el);
  });
});

/* ── Duration input helper (mm:ss → seconds) ────────────── */
function parseDuration(str) {
  if (!str) return 0;
  const parts = str.split(':').map(Number);
  if (parts.length === 2) return parts[0] * 60 + parts[1];
  if (parts.length === 3) return parts[0]*3600 + parts[1]*60 + parts[2];
  return parseInt(str) || 0;
}
function secondsToMmss(s) {
  const m = Math.floor(s / 60);
  const sec = s % 60;
  return String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
}

/* ── Auto call timer ─────────────────────────────────────── */
let callTimerInterval = null;
let callTimerSeconds  = 0;

function startCallTimer(displayEl) {
  callTimerSeconds = 0;
  callTimerInterval = setInterval(() => {
    callTimerSeconds++;
    if (displayEl) displayEl.textContent = secondsToMmss(callTimerSeconds);
  }, 1000);
}
function stopCallTimer(durationInput) {
  clearInterval(callTimerInterval);
  if (durationInput) durationInput.value = callTimerSeconds;
  return callTimerSeconds;
}

window.startCallTimer = startCallTimer;
window.stopCallTimer  = stopCallTimer;
window.parseDuration  = parseDuration;
window.secondsToMmss  = secondsToMmss;
window.showToast      = showToast;
window.Autocomplete   = Autocomplete;
