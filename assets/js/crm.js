(function () {
  'use strict';

  const board = document.getElementById('gc-crm-board');
  if (!board || typeof gcCrmData === 'undefined') return;

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

  const refreshSoon = () => setTimeout(() => window.location.reload(), 250);

  document.querySelectorAll('.gc-crm-nav__item').forEach((item) => {
    item.addEventListener('click', () => {
      const target = item.dataset.view;
      document.querySelectorAll('.gc-crm-nav__item').forEach((i) => i.classList.toggle('is-active', i === item));
      document.querySelectorAll('.gc-crm-view').forEach((view) => view.classList.toggle('is-active', view.dataset.view === target));
    });
  });

  const toggleAdd = document.getElementById('gc-crm-toggle-add');
  const addWrap = document.getElementById('gc-crm-add-wrap');
  if (toggleAdd && addWrap) {
    toggleAdd.addEventListener('click', () => {
      const isHidden = addWrap.hasAttribute('hidden');
      if (isHidden) {
        addWrap.removeAttribute('hidden');
        toggleAdd.textContent = 'Close Add Lead';
      } else {
        addWrap.setAttribute('hidden', 'hidden');
        toggleAdd.textContent = 'Add Lead';
      }
    });
  }

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

      const result = await request('gc_crm_update_lead_status', { lead_id: dragging.dataset.leadId, status });
      if (!result.success) alert((result.data && result.data.message) || gcCrmData.strings.error);
    });
  });

  const modal = document.getElementById('gc-crm-lead-modal');
  const content = document.getElementById('gc-crm-lead-content');
  const closeModal = () => modal && modal.setAttribute('aria-hidden', 'true');
  document.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', closeModal));

  const bindLeadModalActions = (lead) => {
    const saveLead = document.getElementById('gc-crm-save-lead');
    const saveNote = document.getElementById('gc-crm-note-save');
    const sendQuote = document.getElementById('gc-crm-send-quote');

    if (saveLead) {
      saveLead.addEventListener('click', async () => {
        const payload = {
          lead_id: lead.id,
          first_name: document.getElementById('gc-first-name').value,
          last_name: document.getElementById('gc-last-name').value,
          email: document.getElementById('gc-email').value,
          phone: document.getElementById('gc-phone').value,
          title: document.getElementById('gc-title').value,
          details: document.getElementById('gc-details').value,
          status: document.getElementById('gc-status').value,
          assigned_user_id: document.getElementById('gc-assigned').value,
          estimated_value: document.getElementById('gc-value').value,
        };
        const result = await request('gc_crm_update_lead', payload);
        alert((result.data && result.data.message) || (result.success ? 'Saved.' : gcCrmData.strings.error));
        if (result.success) refreshSoon();
      });
    }

    if (saveNote) {
      saveNote.addEventListener('click', async () => {
        const note = document.getElementById('gc-crm-note-input').value.trim();
        if (!note) return;
        const result = await request('gc_crm_add_note', { lead_id: lead.id, note });
        if (result.success) alert('Note saved');
      });
    }

    if (sendQuote) {
      sendQuote.addEventListener('click', async () => {
        const result = await request('gc_crm_send_quote', {
          lead_id: lead.id,
          quote_amount: document.getElementById('gc-quote-amount').value,
          quote_message: document.getElementById('gc-quote-message').value,
        });
        alert((result.data && result.data.message) || (result.success ? 'Quote sent' : gcCrmData.strings.error));
        if (result.success) refreshSoon();
      });
    }
  };

  document.querySelectorAll('.gc-crm-open-lead').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const result = await request('gc_crm_get_lead_detail', { lead_id: btn.dataset.leadId }, 'GET');
      if (!result.success || !content || !modal) return;

      const { lead, products, notes, activity, users } = result.data;
      const userOptions = (users || []).map((user) => `<option value="${escapeHtml(user.ID)}" ${String(user.ID) === String(lead.assigned_user_id) ? 'selected' : ''}>${escapeHtml(user.display_name)}</option>`).join('');

      content.innerHTML = `
        <h3>Lead Details</h3>
        <div class="gc-crm-grid-2">
          <div>
            <label>First Name</label><input id="gc-first-name" value="${escapeHtml(lead.first_name || '')}" />
            <label>Last Name</label><input id="gc-last-name" value="${escapeHtml(lead.last_name || '')}" />
            <label>Email</label><input id="gc-email" value="${escapeHtml(lead.email || '')}" />
            <label>Phone</label><input id="gc-phone" value="${escapeHtml(lead.phone || '')}" />
            <label>Title</label><input id="gc-title" value="${escapeHtml(lead.title || '')}" />
            <label>Details</label><textarea id="gc-details">${escapeHtml(lead.details || '')}</textarea>
            <label>Status</label>
            <select id="gc-status">
              <option value="new_leads" ${lead.status === 'new_leads' ? 'selected' : ''}>New Leads</option>
              <option value="contacted" ${lead.status === 'contacted' ? 'selected' : ''}>Contacted</option>
              <option value="quote_sent" ${lead.status === 'quote_sent' ? 'selected' : ''}>Quote Sent</option>
              <option value="sold" ${lead.status === 'sold' ? 'selected' : ''}>Sold</option>
              <option value="lost" ${lead.status === 'lost' ? 'selected' : ''}>Lost</option>
            </select>
            <label>Assigned User</label><select id="gc-assigned"><option value="0">Unassigned</option>${userOptions}</select>
            <label>Estimated Value</label><input id="gc-value" type="number" step="0.01" value="${escapeHtml(lead.estimated_value || 0)}" />
            <button type="button" id="gc-crm-save-lead">Save Lead</button>
          </div>
          <div>
            <h4>Interested Product(s)</h4>
            <ul>${products.map((p) => `<li>${p.image_url ? `<img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.product_name)}" style="max-width:120px;display:block;margin-bottom:4px;border-radius:8px;" />` : ''}${escapeHtml(p.product_name)} (${escapeHtml(p.product_sku || 'N/A')})</li>`).join('') || '<li>None linked</li>'}</ul>
            <h4>Send Quote</h4>
            <input id="gc-quote-amount" type="number" step="0.01" value="${escapeHtml(lead.estimated_value || 0)}" />
            <textarea id="gc-quote-message" placeholder="Optional quote message"></textarea>
            <button type="button" id="gc-crm-send-quote">Send Quote Email</button>
            <h4>Notes</h4>
            <ul>${notes.map((n) => `<li>${escapeHtml(n.note)} <small>${escapeHtml(n.display_name || 'System')} - ${escapeHtml(n.created_at)}</small></li>`).join('')}</ul>
            <textarea id="gc-crm-note-input" placeholder="Add note"></textarea>
            <button type="button" id="gc-crm-note-save">Save Note</button>
          </div>
        </div>
        <h4>Activity</h4>
        <ul>${activity.map((a) => `<li>${escapeHtml(a.message)} <small>${escapeHtml(a.created_at)}</small></li>`).join('')}</ul>
      `;

      modal.setAttribute('aria-hidden', 'false');
      bindLeadModalActions(lead);
    });
  });

  const createLeadForm = document.getElementById('gc-crm-create-lead');
  if (createLeadForm) {
    createLeadForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = Object.fromEntries(new FormData(createLeadForm).entries());
      const result = await request('gc_crm_create_lead', payload);
      alert((result.data && result.data.message) || (result.success ? 'Lead created.' : gcCrmData.strings.error));
      if (result.success) {
        createLeadForm.reset();
        refreshSoon();
      }
    });
  }

  const searchInput = document.getElementById('gc-crm-search');
  const statusFilter = document.getElementById('gc-crm-status-filter');
  const applyFilters = () => {
    const query = (searchInput?.value || '').toLowerCase();
    const status = statusFilter?.value || '';
    board.querySelectorAll('.gc-crm-lead').forEach((card) => {
      const text = card.textContent.toLowerCase();
      const cardStatus = card.dataset.status;
      card.style.display = ((!query || text.includes(query)) && (!status || cardStatus === status)) ? '' : 'none';
    });
  };

  if (searchInput) searchInput.addEventListener('input', applyFilters);
  if (statusFilter) statusFilter.addEventListener('change', applyFilters);
})();
