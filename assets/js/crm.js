(function () {
  'use strict';

  const board = document.getElementById('gc-crm-board');
  if (!board || typeof gcCrmData === 'undefined') {
    return;
  }

  let dragging = null;

  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

  const request = async (action, data = {}, method = 'POST') => {
    const payload = new URLSearchParams({ action, nonce: gcCrmData.nonce, ...data });
    const url = method === 'GET' ? `${gcCrmData.ajaxUrl}?${payload.toString()}` : gcCrmData.ajaxUrl;

    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: method === 'POST' ? payload.toString() : undefined,
      credentials: 'same-origin',
    });

    return response.json();
  };

  const navItems = document.querySelectorAll('.gc-crm-nav__item');
  const views = document.querySelectorAll('.gc-crm-view');
  navItems.forEach((item) => {
    item.addEventListener('click', () => {
      const target = item.dataset.view;
      navItems.forEach((i) => i.classList.toggle('is-active', i === item));
      views.forEach((view) => view.classList.toggle('is-active', view.dataset.view === target));
    });
  });

  board.querySelectorAll('.gc-crm-lead').forEach((card) => {
    card.addEventListener('dragstart', () => {
      dragging = card;
      card.classList.add('is-dragging');
    });

    card.addEventListener('dragend', () => {
      card.classList.remove('is-dragging');
      dragging = null;
    });
  });

  board.querySelectorAll('.gc-crm-dropzone').forEach((zone) => {
    zone.addEventListener('dragover', (e) => e.preventDefault());
    zone.addEventListener('drop', async (e) => {
      e.preventDefault();
      if (!dragging) return;

      const column = zone.closest('.gc-crm-column');
      const status = column ? column.dataset.status : '';
      if (!status) return;

      zone.prepend(dragging);
      const leadId = dragging.dataset.leadId;
      const result = await request('gc_crm_update_lead_status', { lead_id: leadId, status });

      if (!result.success) {
        alert((result.data && result.data.message) || gcCrmData.strings.error);
      }
    });
  });

  const modal = document.getElementById('gc-crm-lead-modal');
  const content = document.getElementById('gc-crm-lead-content');
  const closeModal = () => modal && modal.setAttribute('aria-hidden', 'true');
  document.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', closeModal));

  document.querySelectorAll('.gc-crm-open-lead').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const result = await request('gc_crm_get_lead_detail', { lead_id: btn.dataset.leadId }, 'GET');
      if (!result.success || !content || !modal) {
        return;
      }

      const { lead, products, notes, activity } = result.data;
      content.innerHTML = `
        <h3>${escapeHtml(lead.first_name || '')} ${escapeHtml(lead.last_name || '')}</h3>
        <p>${escapeHtml(lead.email || '')} • ${escapeHtml(lead.phone || '')}</p>
        <p>${escapeHtml(lead.title || '')}</p>
        <div class="gc-crm-grid-2">
          <div>
            <h4>Products</h4>
            <ul>${products.map((p) => `<li>${escapeHtml(p.product_name)} (${escapeHtml(p.product_sku || 'N/A')})</li>`).join('') || '<li>None</li>'}</ul>
          </div>
          <div>
            <h4>Activity</h4>
            <ul>${activity.map((a) => `<li>${escapeHtml(a.message)} <small>${escapeHtml(a.created_at)}</small></li>`).join('')}</ul>
          </div>
        </div>
        <div>
          <h4>Notes</h4>
          <ul>${notes.map((n) => `<li>${escapeHtml(n.note)} <small>${escapeHtml(n.display_name || 'System')} - ${escapeHtml(n.created_at)}</small></li>`).join('')}</ul>
          <textarea id="gc-crm-note-input" placeholder="Add note"></textarea>
          <button type="button" id="gc-crm-note-save">Save Note</button>
        </div>
      `;
      modal.setAttribute('aria-hidden', 'false');

      const saveBtn = document.getElementById('gc-crm-note-save');
      if (saveBtn) {
        saveBtn.addEventListener('click', async () => {
          const noteInput = document.getElementById('gc-crm-note-input');
          const note = noteInput ? noteInput.value.trim() : '';
          if (!note) return;
          const saveResult = await request('gc_crm_add_note', { lead_id: lead.id, note });
          if (saveResult.success) {
            btn.click();
          }
        });
      }
    });
  });

  const searchInput = document.getElementById('gc-crm-search');
  const statusFilter = document.getElementById('gc-crm-status-filter');

  const applyFilters = () => {
    const query = (searchInput?.value || '').toLowerCase();
    const status = statusFilter?.value || '';

    board.querySelectorAll('.gc-crm-lead').forEach((card) => {
      const text = card.textContent.toLowerCase();
      const cardStatus = card.dataset.status;
      const textMatch = !query || text.includes(query);
      const statusMatch = !status || cardStatus === status;
      card.style.display = textMatch && statusMatch ? '' : 'none';
    });
  };

  if (searchInput) searchInput.addEventListener('input', applyFilters);
  if (statusFilter) statusFilter.addEventListener('change', applyFilters);
})();
