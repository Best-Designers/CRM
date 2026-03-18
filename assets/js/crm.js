(function ($) {
  'use strict';

  if (typeof gcCrmData === 'undefined') return;

  const ajaxurl = window.ajaxurl || gcCrmData.ajaxUrl;
  const $doc = $(document);
  const $body = $('body');
  let draggingLead = null;

  const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  };

  const request = (action, data = {}, method = 'POST') => {
    const payload = { action, nonce: gcCrmData.nonce, ...data };
    return $.ajax({
      url: ajaxurl,
      method,
      dataType: 'json',
      data: payload,
    }).catch(() => ({ success: false, data: { message: gcCrmData.strings.error } }));
  };

  const notify = (result, successText) => {
    const message = (result && result.data && result.data.message) || (result && result.success ? successText : gcCrmData.strings.error);
    if (message) window.alert(message);
  };

  const refreshSoon = () => window.setTimeout(() => window.location.reload(), 200);

 const applyLeadFilters = () => {
    const query = ($('#gc-crm-search').val() || '').toLowerCase();
    const status = $('#gc-crm-status-filter').val() || '';

  $('#gc-crm-board .gc-crm-lead').each(function () {
      const $card = $(this);
      const text = $card.text().toLowerCase();
      const cardStatus = $card.data('status');
      const visible = (!query || text.indexOf(query) >= 0) && (!status || String(cardStatus) === status);
      $card.toggle(visible);
    });
    };

  const renderLeadModal = (data) => {
    const lead = data.lead || {};
    const products = data.products || [];
    const notes = data.notes || [];
    const activity = data.activity || [];
    const users = data.users || [];

    const userOptions = users.map((user) => `<option value="${escapeHtml(user.ID)}" ${String(user.ID) === String(lead.assigned_user_id) ? 'selected' : ''}>${escapeHtml(user.display_name)}</option>`).join('');
    const productItems = products.length
      ? products.map((p) => `<li>${p.image_url ? `<img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.product_name)}" style="max-width:120px;display:block;margin-bottom:4px;border-radius:8px;" />` : ''}${escapeHtml(p.product_name)} (${escapeHtml(p.product_sku || 'N/A')})</li>`).join('')
      : '<li>None linked</li>';

    const noteItems = notes.map((n) => `<li>${escapeHtml(n.note)} <small>${escapeHtml(n.display_name || 'System')} - ${escapeHtml(n.created_at)}</small></li>`).join('');
    const activityItems = activity.map((a) => `<li>${escapeHtml(a.message)} <small>${escapeHtml(a.created_at)}</small></li>`).join('');

    $('#gc-crm-lead-content').html(`
      <h3>Lead Details</h3>
      <div class="gc-crm-grid-2" data-lead-id="${escapeHtml(lead.id)}">
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
            <option value="archived_sold" ${lead.status === 'archived_sold' ? 'selected' : ''}>Archived (Sold)</option>
            <option value="lost" ${lead.status === 'lost' ? 'selected' : ''}>Lost</option>
          </select>
          <label>Assigned User</label><select id="gc-assigned"><option value="0">Unassigned</option>${userOptions}</select>
          <label>Estimated Value</label><input id="gc-value" type="number" step="0.01" value="${escapeHtml(lead.estimated_value || 0)}" />
          <button type="button" id="gc-crm-save-lead">Save Lead</button>
          <button type="button" id="gc-crm-delete-lead">Delete Lead</button>
          <button type="button" id="gc-crm-archive-lead" ${lead.status === 'sold' ? '' : 'hidden'}>Archive Sold Lead</button>
        </div>
        <div>
          <h4>Interested Product(s)</h4>
          <ul>${productItems}</ul>
          <h4>Send Quote</h4>
          <input id="gc-quote-amount" type="number" step="0.01" value="${escapeHtml(lead.estimated_value || 0)}" />
          <textarea id="gc-quote-message" placeholder="Optional quote message"></textarea>
          <button type="button" id="gc-crm-send-quote">Send Quote Email</button>
          <h4>Notes</h4>
          <ul>${noteItems}</ul>
          <textarea id="gc-crm-note-input" placeholder="Add note"></textarea>
          <button type="button" id="gc-crm-note-save">Save Note</button>
        </div>
      </div>
      <h4>Activity</h4>
      <ul>${activityItems}</ul>
    `);
    
    $('#gc-crm-lead-modal').attr('aria-hidden', 'false');
  };

    // Navigation / view switching
  $doc.on('click', '.gc-crm-nav__item', function (e) {
    e.preventDefault();
    const $item = $(this);
    const target = $item.data('view');
    $('.gc-crm-nav__item').removeClass('is-active');
    $item.addClass('is-active');
    $('.gc-crm-view').removeClass('is-active');
    $(`.gc-crm-view[data-view="${target}"]`).addClass('is-active');
  });

    // Toggle Add Lead panel
  $doc.on('click', '#gc-crm-toggle-add', function (e) {
    e.preventDefault();
    const $wrap = $('#gc-crm-add-wrap');
    const isHidden = $wrap.is('[hidden]');
    if (isHidden) {
      $wrap.removeAttr('hidden');
      $(this).text('Close Add Lead');
    } else {
      $wrap.attr('hidden', 'hidden');
      $(this).text('Add Lead');
    }
    });
  
    // Drag and drop pipeline updates
  $doc.on('dragstart', '.gc-crm-lead', function () {
    draggingLead = this;
    $(this).addClass('is-dragging');
  });

  $doc.on('dragend', '.gc-crm-lead', function () {
    $(this).removeClass('is-dragging');
    draggingLead = null;
  });

    $doc.on('dragover', '.gc-crm-dropzone', function (e) {
    e.preventDefault();
  });

  $doc.on('drop', '.gc-crm-dropzone', async function (e) {
    e.preventDefault();
    if (!draggingLead) return;

    const $zone = $(this);
    const status = $zone.closest('.gc-crm-column').data('status');
    const leadId = $(draggingLead).data('lead-id');

    if (!status || !leadId) return;
    $zone.prepend(draggingLead);

    const result = await request('gc_crm_update_lead_status', { lead_id: leadId, status });
    if (!result.success) {
      notify(result, 'Unable to update lead status.');
      return;
    }

  refreshSoon();
  });

      // Lead modal open/close
  $doc.on('click', '[data-close]', function (e) {
    e.preventDefault();
    $('#gc-crm-lead-modal').attr('aria-hidden', 'true');
  });

  $doc.on('click', '.gc-crm-open-lead', async function (e) {
    e.preventDefault();
    const leadId = $(this).data('lead-id');
    if (!leadId) return;

  const result = await request('gc_crm_get_lead_detail', { lead_id: leadId }, 'GET');
    if (!result.success || !result.data) {
      notify(result, 'Unable to load lead details.');
      return;
    }

    renderLeadModal(result.data);
  });

  // Lead modal actions
  $doc.on('change', '#gc-status', function () {
    $('#gc-crm-archive-lead').prop('hidden', $(this).val() !== 'sold');
  });

  $doc.on('click', '#gc-crm-save-lead', async function (e) {
    e.preventDefault();
    const leadId = $('#gc-crm-lead-content .gc-crm-grid-2').data('lead-id');
    if (!leadId) return;

  const payload = {
      lead_id: leadId,
      first_name: $('#gc-first-name').val() || '',
      last_name: $('#gc-last-name').val() || '',
      email: $('#gc-email').val() || '',
      phone: $('#gc-phone').val() || '',
      title: $('#gc-title').val() || '',
      details: $('#gc-details').val() || '',
      status: $('#gc-status').val() || '',
      assigned_user_id: $('#gc-assigned').val() || 0,
      estimated_value: $('#gc-value').val() || 0,
        };

  const result = await request('gc_crm_update_lead', payload);
    notify(result, 'Lead saved.');
    if (result.success) refreshSoon();
  });

  $doc.on('click', '#gc-crm-delete-lead', async function (e) {
    e.preventDefault();
    const leadId = $('#gc-crm-lead-content .gc-crm-grid-2').data('lead-id');
    if (!leadId) return;
    if (!window.confirm('Delete this lead? This cannot be undone.')) return;

  const result = await request('gc_crm_delete_lead', { lead_id: leadId });
    notify(result, 'Lead deleted.');
    if (result.success) refreshSoon();
  });

  $doc.on('click', '#gc-crm-archive-lead', async function (e) {
    e.preventDefault();
    const leadId = $('#gc-crm-lead-content .gc-crm-grid-2').data('lead-id');
    if (!leadId) return;
    if (!window.confirm('Archive this sold lead?')) return;

    const result = await request('gc_crm_archive_lead', { lead_id: leadId });
    notify(result, 'Lead archived.');
    if (result.success) refreshSoon();
  });

    $doc.on('click', '#gc-crm-note-save', async function (e) {
    e.preventDefault();
    const leadId = $('#gc-crm-lead-content .gc-crm-grid-2').data('lead-id');
    const note = ($('#gc-crm-note-input').val() || '').trim();
    if (!leadId || !note) return;

    const result = await request('gc_crm_add_note', { lead_id: leadId, note });
    notify(result, 'Note saved.');
    if (result.success) refreshSoon();
  });

  $doc.on('click', '#gc-crm-send-quote', async function (e) {
    e.preventDefault();
    const leadId = $('#gc-crm-lead-content .gc-crm-grid-2').data('lead-id');
    if (!leadId) return;

    const result = await request('gc_crm_send_quote', {
      lead_id: leadId,
      quote_amount: $('#gc-quote-amount').val() || 0,
      quote_message: $('#gc-quote-message').val() || '',
    });

  notify(result, 'Quote sent.');
    if (result.success) refreshSoon();
  });

     // Lead create form
  $doc.on('submit', '#gc-crm-create-lead', async function (e) {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(this).entries());
    const result = await request('gc_crm_create_lead', payload);
    notify(result, 'Lead created.');
    if (result.success) refreshSoon();
  });

        // Lead filters
  $doc.on('input', '#gc-crm-search', applyLeadFilters);
  $doc.on('change', '#gc-crm-status-filter', applyLeadFilters);

            // To-do add (works for existing and dynamically rendered forms)
  $doc.on('submit', '#gc-crm-todo-add-form', async function (e) {
    e.preventDefault();
    const text = ($('#gc-crm-todo-add-input').val() || '').trim();
    if (!text) return;

    const result = await request('gc_crm_add_todo', { text });
    notify(result, 'Task added.');
    if (result.success) refreshSoon();
  });

    // To-do delete/edit with full delegation
  $doc.on('click', '.gc-crm-todo-delete', async function (e) {
    e.preventDefault();
    const $item = $(this).closest('.gc-crm-todo-item');
    const todoId = $item.data('todo-id');
    if (!todoId) return;

    const result = await request('gc_crm_delete_todo', { todo_id: todoId });
    notify(result, 'Task removed.');
    if (result.success) refreshSoon();
  });

  $doc.on('click', '.gc-crm-todo-edit', async function (e) {
    e.preventDefault();
    const $item = $(this).closest('.gc-crm-todo-item');
    const todoId = $item.data('todo-id');
    if (!todoId) return;

    const currentText = ($item.find('.gc-crm-todo-text').text() || '').trim();
    const nextText = window.prompt('Edit to-do item', currentText);
    if (nextText === null) return;

    const text = nextText.trim();
    if (!text) return;

    const result = await request('gc_crm_update_todo', { todo_id: todoId, text });
    notify(result, 'Task updated.');
    if (result.success) refreshSoon();
  });

  // Contacts edit/delete with robust delegation
  $doc.on('click', '.gc-crm-delete-contact', async function (e) {
    e.preventDefault();
    const $row = $(this).closest('tr');
    const contactId = $(this).data('contact-id') || $row.data('contact-id');
    if (!contactId) return;
    if (!window.confirm('Delete this contact and related leads? This cannot be undone.')) return;

    const result = await request('gc_crm_delete_contact', { contact_id: contactId });
    notify(result, 'Contact deleted.');
    if (result.success) refreshSoon();
  });

  $doc.on('click', '.gc-crm-edit-contact', async function (e) {
    e.preventDefault();
    const $row = $(this).closest('tr');
    const contactId = $(this).data('contact-id') || $row.data('contact-id');
    if (!contactId) return;

    const currentName = ($row.children().eq(0).text() || '').trim();
    const firstName = window.prompt('First name', currentName.split(' ')[0] || '');
    if (firstName === null) return;
    const lastName = window.prompt('Last name', currentName.split(' ').slice(1).join(' ') || '');
    if (lastName === null) return;
    const email = window.prompt('Email', ($row.children().eq(1).text() || '').trim());
    if (email === null) return;
    const phone = window.prompt('Phone', ($row.children().eq(2).text() || '').trim());
    if (phone === null) return;
    const company = window.prompt('Company', ($row.children().eq(3).text() || '').trim());
    if (company === null) return;

    const result = await request('gc_crm_update_contact', {
      contact_id: contactId,
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      email: email.trim(),
      phone: phone.trim(),
      company: company.trim(),
    });

    notify(result, 'Contact updated.');
    if (result.success) refreshSoon();
  });

  // Accessibility: keyboard activation for non-button delete element
  $doc.on('keydown', '.gc-crm-todo-delete', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    e.preventDefault();
    $(this).trigger('click');
  });

  // Ensure default AJAX endpoint exists in case of external scripts expecting it
  if (!window.ajaxurl) {
    window.ajaxurl = ajaxurl;
  }

  // Prevent accidental native drag image selection outside cards
  $body.attr('data-gc-crm-ready', '1');
})(jQuery);
