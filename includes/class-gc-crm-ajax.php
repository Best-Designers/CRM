<?php

if (! defined('ABSPATH')) {
    exit;
}

class GC_CRM_Ajax {
    public static function init(): void {
        add_action('wp_ajax_gc_crm_update_lead_status', [__CLASS__, 'update_lead_status']);
        add_action('wp_ajax_gc_crm_get_lead_detail', [__CLASS__, 'get_lead_detail']);
        add_action('wp_ajax_gc_crm_add_note', [__CLASS__, 'add_note']);
        add_action('wp_ajax_gc_crm_update_lead', [__CLASS__, 'update_lead']);
        add_action('wp_ajax_gc_crm_create_lead', [__CLASS__, 'create_lead']);
        add_action('wp_ajax_gc_crm_send_quote', [__CLASS__, 'send_quote']);
        add_action('wp_ajax_gc_crm_add_todo', [__CLASS__, 'add_todo']);
        add_action('wp_ajax_gc_crm_update_todo', [__CLASS__, 'update_todo']);
        add_action('wp_ajax_gc_crm_delete_todo', [__CLASS__, 'delete_todo']);
        add_action('wp_ajax_gc_crm_clear_todos', [__CLASS__, 'clear_todos']);
        add_action('wp_ajax_gc_crm_delete_lead', [__CLASS__, 'delete_lead']);
        add_action('wp_ajax_gc_crm_delete_contact', [__CLASS__, 'delete_contact']);
        add_action('wp_ajax_gc_crm_update_contact', [__CLASS__, 'update_contact']);
    }

    private static function guard(): void {
        check_ajax_referer('gc_crm_nonce', 'nonce');
        if (! current_user_can('manage_gc_crm')) {
            wp_send_json_error(['message' => __('Unauthorized', 'gc-dealership-crm')], 403);
        }
    }

    public static function create_lead(): void {
        self::guard();

        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $title      = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $details    = isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';
        $status     = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'new_leads';

        if (! $email && ! $phone) {
            wp_send_json_error(['message' => __('Email or phone is required.', 'gc-dealership-crm')], 400);
        }

        $allowed = ['new_leads', 'contacted', 'quote_sent', 'sold', 'lost'];
        if (! in_array($status, $allowed, true)) {
            $status = 'new_leads';
        }

        global $wpdb;
        $contacts_table = GC_CRM_DB::table('contacts');
        $leads_table    = GC_CRM_DB::table('leads');

        $contact_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$contacts_table} WHERE email = %s OR phone = %s LIMIT 1", $email, $phone));

        if ($contact_id) {
            $wpdb->update($contacts_table, ['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'phone' => $phone, 'updated_at' => GC_CRM_DB::now()], ['id' => $contact_id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);
        } else {
            $wpdb->insert($contacts_table, ['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'phone' => $phone, 'created_at' => GC_CRM_DB::now(), 'updated_at' => GC_CRM_DB::now()], ['%s', '%s', '%s', '%s', '%s', '%s']);
            $contact_id = (int) $wpdb->insert_id;
        }

        $wpdb->insert($leads_table, ['contact_id' => $contact_id, 'assigned_user_id' => get_current_user_id(), 'source' => 'manual_entry', 'source_ref' => 'crm_manual', 'status' => $status, 'title' => $title ?: __('Manual lead', 'gc-dealership-crm'), 'details' => $details, 'estimated_value' => 0, 'created_at' => GC_CRM_DB::now(), 'updated_at' => GC_CRM_DB::now()], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s']);

        $lead_id = (int) $wpdb->insert_id;
        GC_CRM_Integrations::log_activity($lead_id, get_current_user_id(), 'lead_created_manual', 'Lead manually created from CRM interface.');

        wp_send_json_success(['message' => __('Lead created successfully.', 'gc-dealership-crm'), 'lead_id' => $lead_id]);
    }

    public static function update_lead_status(): void {
        self::guard();

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $status  = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $allowed = ['new_leads', 'contacted', 'quote_sent', 'sold', 'lost'];

        if (! $lead_id || ! in_array($status, $allowed, true)) {
            wp_send_json_error(['message' => __('Invalid data', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $updated = $wpdb->update(GC_CRM_DB::table('leads'), ['status' => $status, 'updated_at' => GC_CRM_DB::now()], ['id' => $lead_id], ['%s', '%s'], ['%d']);

        if ($updated === false) {
            wp_send_json_error(['message' => __('Unable to update lead.', 'gc-dealership-crm')], 500);
        }

        GC_CRM_Integrations::log_activity($lead_id, get_current_user_id(), 'status_changed', 'Lead status changed to ' . $status);
        wp_send_json_success(['message' => __('Lead updated.', 'gc-dealership-crm')]);
    }

    public static function get_lead_detail(): void {
        self::guard();

        $lead_id = isset($_GET['lead_id']) ? absint($_GET['lead_id']) : 0;
        if (! $lead_id) {
            wp_send_json_error(['message' => __('Invalid lead id', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare('SELECT l.*, c.first_name, c.last_name, c.email, c.phone FROM ' . GC_CRM_DB::table('leads') . ' l INNER JOIN ' . GC_CRM_DB::table('contacts') . ' c ON c.id = l.contact_id WHERE l.id = %d', $lead_id), ARRAY_A);

        if (! $lead) {
            wp_send_json_error(['message' => __('Lead not found.', 'gc-dealership-crm')], 404);
        }

        $products = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . GC_CRM_DB::table('product_links') . ' WHERE lead_id = %d ORDER BY id DESC', $lead_id), ARRAY_A);
        foreach ($products as &$product) {
            $product['image_url'] = ! empty($product['product_id']) ? (string) get_the_post_thumbnail_url((int) $product['product_id'], 'medium') : '';
        }
        unset($product);

        $notes    = $wpdb->get_results($wpdb->prepare('SELECT n.*, u.display_name FROM ' . GC_CRM_DB::table('notes') . ' n LEFT JOIN ' . $wpdb->users . ' u ON n.user_id = u.ID WHERE n.lead_id = %d ORDER BY n.created_at DESC', $lead_id), ARRAY_A);
        $activity = $wpdb->get_results($wpdb->prepare('SELECT a.*, u.display_name FROM ' . GC_CRM_DB::table('activity') . ' a LEFT JOIN ' . $wpdb->users . ' u ON a.user_id = u.ID WHERE a.lead_id = %d ORDER BY a.created_at DESC LIMIT 30', $lead_id), ARRAY_A);

        wp_send_json_success(['lead' => $lead, 'products' => $products, 'notes' => $notes, 'activity' => $activity, 'users' => get_users(['fields' => ['ID', 'display_name']])]);
    }

    public static function add_note(): void {
        self::guard();

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $note    = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        if (! $lead_id || ! $note) {
            wp_send_json_error(['message' => __('Lead and note are required.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $wpdb->insert(GC_CRM_DB::table('notes'), ['lead_id' => $lead_id, 'user_id' => get_current_user_id(), 'note' => $note, 'created_at' => GC_CRM_DB::now()], ['%d', '%d', '%s', '%s']);
        GC_CRM_Integrations::log_activity($lead_id, get_current_user_id(), 'note_added', 'A new note was added.');

        wp_send_json_success(['message' => __('Note added.', 'gc-dealership-crm')]);
    }

    public static function update_lead(): void {
        self::guard();

        $lead_id    = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $status     = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $assigned   = isset($_POST['assigned_user_id']) ? absint($_POST['assigned_user_id']) : 0;
        $value      = isset($_POST['estimated_value']) ? (float) wp_unslash($_POST['estimated_value']) : 0;
        $title      = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $details    = isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

        if (! $lead_id) {
            wp_send_json_error(['message' => __('Invalid lead id.', 'gc-dealership-crm')], 400);
        }

        $allowed = ['new_leads', 'contacted', 'quote_sent', 'sold', 'lost'];
        if ($status && ! in_array($status, $allowed, true)) {
            wp_send_json_error(['message' => __('Invalid status.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $contact_id = (int) $wpdb->get_var($wpdb->prepare('SELECT contact_id FROM ' . GC_CRM_DB::table('leads') . ' WHERE id = %d', $lead_id));
        if ($contact_id > 0) {
            $wpdb->update(GC_CRM_DB::table('contacts'), ['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'phone' => $phone, 'updated_at' => GC_CRM_DB::now()], ['id' => $contact_id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);
        }

        $data = ['updated_at' => GC_CRM_DB::now(), 'assigned_user_id' => $assigned, 'estimated_value' => $value, 'title' => $title, 'details' => $details];
        $format = ['%s', '%d', '%f', '%s', '%s'];
        if ($status) {
            $data['status'] = $status;
            $format[] = '%s';
        }

        $updated = $wpdb->update(GC_CRM_DB::table('leads'), $data, ['id' => $lead_id], $format, ['%d']);

        if ($updated === false) {
            wp_send_json_error(['message' => __('Update failed.', 'gc-dealership-crm')], 500);
        }

        GC_CRM_Integrations::log_activity($lead_id, get_current_user_id(), 'lead_updated', 'Lead details were updated.');
        wp_send_json_success(['message' => __('Lead updated successfully.', 'gc-dealership-crm')]);
    }

    public static function send_quote(): void {
        self::guard();

        $lead_id       = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $quote_amount  = isset($_POST['quote_amount']) ? (float) wp_unslash($_POST['quote_amount']) : 0;
        $quote_message = isset($_POST['quote_message']) ? sanitize_textarea_field(wp_unslash($_POST['quote_message'])) : '';

        if (! $lead_id || $quote_amount <= 0) {
            wp_send_json_error(['message' => __('Lead and quote amount are required.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare('SELECT l.id, c.first_name, c.last_name, c.email FROM ' . GC_CRM_DB::table('leads') . ' l INNER JOIN ' . GC_CRM_DB::table('contacts') . ' c ON c.id = l.contact_id WHERE l.id = %d', $lead_id), ARRAY_A);

        if (! $lead || empty($lead['email'])) {
            wp_send_json_error(['message' => __('Lead email is required to send a quote.', 'gc-dealership-crm')], 400);
        }

        $product = $wpdb->get_row($wpdb->prepare('SELECT product_id, product_name FROM ' . GC_CRM_DB::table('product_links') . ' WHERE lead_id = %d ORDER BY id DESC LIMIT 1', $lead_id), ARRAY_A);
        $product_name  = $product['product_name'] ?? __('Selected golf cart', 'gc-dealership-crm');
        $product_image = (! empty($product['product_id'])) ? (string) get_the_post_thumbnail_url((int) $product['product_id'], 'large') : '';

        $subject = sprintf(__('Your Quote for %s', 'gc-dealership-crm'), $product_name);
        $body = sprintf(
            '<p>Hi %s,</p><p>Thanks for your interest in <strong>%s</strong>.</p>%s<p><strong>Quoted price:</strong> %s</p><p>%s</p><p>Reply to this email if you have any questions.</p>',
            esc_html(trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')) ?: __('there', 'gc-dealership-crm')),
            esc_html($product_name),
            $product_image ? '<p><img src="' . esc_url($product_image) . '" alt="' . esc_attr($product_name) . '" style="max-width:420px;height:auto;border-radius:8px;" /></p>' : '',
            esc_html('$' . number_format($quote_amount, 2)),
            nl2br(esc_html($quote_message ?: __('This quote is based on your current product inquiry and can be adjusted with options.', 'gc-dealership-crm')))
        );

        $sent = wp_mail($lead['email'], $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        if (! $sent) {
            wp_send_json_error(['message' => __('Quote email failed to send.', 'gc-dealership-crm')], 500);
        }

        $wpdb->update(GC_CRM_DB::table('leads'), ['status' => 'quote_sent', 'estimated_value' => $quote_amount, 'updated_at' => GC_CRM_DB::now()], ['id' => $lead_id], ['%s', '%f', '%s'], ['%d']);
        GC_CRM_Integrations::log_activity($lead_id, get_current_user_id(), 'quote_sent', 'Quote sent to lead via CRM email workflow.', ['quote_amount' => $quote_amount]);

        wp_send_json_success(['message' => __('Quote sent successfully.', 'gc-dealership-crm')]);
    }
    
    public static function add_todo(): void {
        self::guard();

        $text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
        if ($text === '') {
            wp_send_json_error(['message' => __('Task text is required.', 'gc-dealership-crm')], 400);
        }

        $state = GC_CRM_Shortcode::get_todo_state();
        $state['manual'][] = [
            'id'      => 'manual_' . wp_generate_uuid4(),
            'text'    => $text,
            'checked' => false,
        ];
        GC_CRM_Shortcode::save_todo_state($state);

        wp_send_json_success(['message' => __('Task added.', 'gc-dealership-crm')]);
    }

    public static function update_todo(): void {
        self::guard();

        $todo_id  = isset($_POST['todo_id']) ? sanitize_text_field(wp_unslash($_POST['todo_id'])) : '';
        $text     = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : null;
        $checked  = isset($_POST['checked']) ? (int) $_POST['checked'] : null;
        $is_auto  = strpos($todo_id, 'auto:') === 0;

        if ($todo_id === '') {
            wp_send_json_error(['message' => __('Task id is required.', 'gc-dealership-crm')], 400);
        }

        $state = GC_CRM_Shortcode::get_todo_state();

        if ($is_auto) {
            $auto_key = sanitize_key(substr($todo_id, 5));
            if ($auto_key === '') {
                wp_send_json_error(['message' => __('Invalid auto task id.', 'gc-dealership-crm')], 400);
            }

            $auto = $state['auto'][$auto_key] ?? [];
            if ($text !== null) {
                $auto['text'] = $text;
            }
            if ($checked !== null) {
                $auto['checked'] = $checked === 1;
            }
            if (isset($_POST['removed'])) {
                $auto['removed'] = ((int) $_POST['removed']) === 1;
            }
            $state['auto'][$auto_key] = $auto;
            GC_CRM_Shortcode::save_todo_state($state);
            wp_send_json_success(['message' => __('Task updated.', 'gc-dealership-crm')]);
        }

        $found = false;
        foreach ($state['manual'] as $index => $item) {
            if (($item['id'] ?? '') !== $todo_id) {
                continue;
            }
            if ($text !== null) {
                $state['manual'][$index]['text'] = $text;
            }
            if ($checked !== null) {
                $state['manual'][$index]['checked'] = $checked === 1;
            }
            $found = true;
            break;
        }

        if (! $found) {
            wp_send_json_error(['message' => __('Task not found.', 'gc-dealership-crm')], 404);
        }

        GC_CRM_Shortcode::save_todo_state($state);
        wp_send_json_success(['message' => __('Task updated.', 'gc-dealership-crm')]);
    }

    public static function delete_todo(): void {
        self::guard();

        $todo_id = isset($_POST['todo_id']) ? sanitize_text_field(wp_unslash($_POST['todo_id'])) : '';
        if ($todo_id === '') {
            wp_send_json_error(['message' => __('Task id is required.', 'gc-dealership-crm')], 400);
        }

        $state = GC_CRM_Shortcode::get_todo_state();
        if (strpos($todo_id, 'auto:') === 0) {
            $auto_key = sanitize_key(substr($todo_id, 5));
            $auto = $state['auto'][$auto_key] ?? [];
            $auto['removed'] = true;
            $state['auto'][$auto_key] = $auto;
            GC_CRM_Shortcode::save_todo_state($state);
            wp_send_json_success(['message' => __('Task removed.', 'gc-dealership-crm')]);
        }

        $state['manual'] = array_values(array_filter($state['manual'], static function ($item) use ($todo_id) {
            return ($item['id'] ?? '') !== $todo_id;
        }));
        GC_CRM_Shortcode::save_todo_state($state);

        wp_send_json_success(['message' => __('Task removed.', 'gc-dealership-crm')]);
    }

    public static function clear_todos(): void {
        self::guard();

        $state = GC_CRM_Shortcode::get_todo_state();
        $state['manual'] = [];
        foreach ($state['auto'] as $key => $item) {
            $state['auto'][$key]['removed'] = true;
            $state['auto'][$key]['checked'] = false;
        }
        GC_CRM_Shortcode::save_todo_state($state);

        wp_send_json_success(['message' => __('To-do list cleared.', 'gc-dealership-crm')]);
    }

    public static function delete_lead(): void {
        self::guard();

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        if (! $lead_id) {
            wp_send_json_error(['message' => __('Invalid lead id.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;

        $wpdb->delete(GC_CRM_DB::table('notes'), ['lead_id' => $lead_id], ['%d']);
        $wpdb->delete(GC_CRM_DB::table('activity'), ['lead_id' => $lead_id], ['%d']);
        $wpdb->delete(GC_CRM_DB::table('deals'), ['lead_id' => $lead_id], ['%d']);
        $wpdb->delete(GC_CRM_DB::table('product_links'), ['lead_id' => $lead_id], ['%d']);
        $deleted = $wpdb->delete(GC_CRM_DB::table('leads'), ['id' => $lead_id], ['%d']);

        if (! $deleted) {
            wp_send_json_error(['message' => __('Unable to delete lead.', 'gc-dealership-crm')], 500);
        }

        wp_send_json_success(['message' => __('Lead deleted.', 'gc-dealership-crm')]);
    }

    public static function delete_contact(): void {
        self::guard();

        $contact_id = isset($_POST['contact_id']) ? absint($_POST['contact_id']) : 0;
        if (! $contact_id) {
            wp_send_json_error(['message' => __('Invalid contact id.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $lead_ids = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . GC_CRM_DB::table('leads') . ' WHERE contact_id = %d', $contact_id));
        foreach ($lead_ids as $lead_id) {
            $lead_id = (int) $lead_id;
            $wpdb->delete(GC_CRM_DB::table('notes'), ['lead_id' => $lead_id], ['%d']);
            $wpdb->delete(GC_CRM_DB::table('activity'), ['lead_id' => $lead_id], ['%d']);
            $wpdb->delete(GC_CRM_DB::table('deals'), ['lead_id' => $lead_id], ['%d']);
            $wpdb->delete(GC_CRM_DB::table('product_links'), ['lead_id' => $lead_id], ['%d']);
            $wpdb->delete(GC_CRM_DB::table('leads'), ['id' => $lead_id], ['%d']);
        }

        $deleted = $wpdb->delete(GC_CRM_DB::table('contacts'), ['id' => $contact_id], ['%d']);
        if (! $deleted) {
            wp_send_json_error(['message' => __('Unable to delete contact.', 'gc-dealership-crm')], 500);
        }

        wp_send_json_success(['message' => __('Contact deleted.', 'gc-dealership-crm')]);
    }
    
    public static function update_contact(): void {
        self::guard();

        $contact_id = isset($_POST['contact_id']) ? absint($_POST['contact_id']) : 0;
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $company    = isset($_POST['company']) ? sanitize_text_field(wp_unslash($_POST['company'])) : '';

        if (! $contact_id) {
            wp_send_json_error(['message' => __('Invalid contact id.', 'gc-dealership-crm')], 400);
        }

        if ($email === '' && $phone === '') {
            wp_send_json_error(['message' => __('Email or phone is required.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $updated = $wpdb->update(
            GC_CRM_DB::table('contacts'),
            [
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $email,
                'phone'      => $phone,
                'company'    => $company,
                'updated_at' => GC_CRM_DB::now(),
            ],
            ['id' => $contact_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => __('Unable to update contact.', 'gc-dealership-crm')], 500);
        }

        wp_send_json_success(['message' => __('Contact updated.', 'gc-dealership-crm')]);
    }
}
