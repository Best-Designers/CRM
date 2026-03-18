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
    }

    private static function guard(): void {
        check_ajax_referer('gc_crm_nonce', 'nonce');
        if (! current_user_can('manage_gc_crm')) {
            wp_send_json_error(['message' => __('Unauthorized', 'gc-dealership-crm')], 403);
        }
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
        $updated = $wpdb->update(
            GC_CRM_DB::table('leads'),
            ['status' => $status, 'updated_at' => GC_CRM_DB::now()],
            ['id' => $lead_id],
            ['%s', '%s'],
            ['%d']
        );

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
        $leads_table    = GC_CRM_DB::table('leads');
        $contacts_table = GC_CRM_DB::table('contacts');

        $lead = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT l.*, c.first_name, c.last_name, c.email, c.phone
                FROM {$leads_table} l
                INNER JOIN {$contacts_table} c ON c.id = l.contact_id
                WHERE l.id = %d",
                $lead_id
            ),
            ARRAY_A
        );

        if (! $lead) {
            wp_send_json_error(['message' => __('Lead not found.', 'gc-dealership-crm')], 404);
        }

        $products = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . GC_CRM_DB::table('product_links') . ' WHERE lead_id = %d ORDER BY id DESC',
                $lead_id
            ),
            ARRAY_A
        );

        $notes = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT n.*, u.display_name FROM ' . GC_CRM_DB::table('notes') . ' n LEFT JOIN ' . $wpdb->users . ' u ON n.user_id = u.ID WHERE n.lead_id = %d ORDER BY n.created_at DESC',
                $lead_id
            ),
            ARRAY_A
        );

        $activity = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT a.*, u.display_name FROM ' . GC_CRM_DB::table('activity') . ' a LEFT JOIN ' . $wpdb->users . ' u ON a.user_id = u.ID WHERE a.lead_id = %d ORDER BY a.created_at DESC LIMIT 30',
                $lead_id
            ),
            ARRAY_A
        );

        wp_send_json_success([
            'lead'     => $lead,
            'products' => $products,
            'notes'    => $notes,
            'activity' => $activity,
        ]);
    }

    public static function add_note(): void {
        self::guard();

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $note    = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        if (! $lead_id || ! $note) {
            wp_send_json_error(['message' => __('Lead and note are required.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $wpdb->insert(
            GC_CRM_DB::table('notes'),
            [
                'lead_id'    => $lead_id,
                'user_id'    => get_current_user_id(),
                'note'       => $note,
                'created_at' => GC_CRM_DB::now(),
            ],
            ['%d', '%d', '%s', '%s']
        );

        GC_CRM_Integrations::log_activity($lead_id, get_current_user_id(), 'note_added', 'A new note was added.');
        wp_send_json_success(['message' => __('Note added.', 'gc-dealership-crm')]);
    }

    public static function update_lead(): void {
        self::guard();

        $lead_id   = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $status    = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $assigned  = isset($_POST['assigned_user_id']) ? absint($_POST['assigned_user_id']) : 0;
        $value     = isset($_POST['estimated_value']) ? (float) wp_unslash($_POST['estimated_value']) : 0;

        if (! $lead_id) {
            wp_send_json_error(['message' => __('Invalid lead id.', 'gc-dealership-crm')], 400);
        }

        $allowed = ['new_leads', 'contacted', 'quote_sent', 'sold', 'lost'];
        if ($status && ! in_array($status, $allowed, true)) {
            wp_send_json_error(['message' => __('Invalid status.', 'gc-dealership-crm')], 400);
        }

        global $wpdb;
        $data   = ['updated_at' => GC_CRM_DB::now()];
        $format = ['%s'];

        if ($status) {
            $data['status'] = $status;
            $format[]       = '%s';
        }

        $data['assigned_user_id'] = $assigned;
        $format[]                 = '%d';

        $data['estimated_value'] = $value;
        $format[]                = '%f';

        $updated = $wpdb->update(
            GC_CRM_DB::table('leads'),
            $data,
            ['id' => $lead_id],
            $format,
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => __('Update failed.', 'gc-dealership-crm')], 500);
        }

        GC_CRM_Integrations::log_activity($lead_id, get_current_user_id(), 'lead_updated', 'Lead details were updated.');

        wp_send_json_success(['message' => __('Lead updated successfully.', 'gc-dealership-crm')]);
    }
}
