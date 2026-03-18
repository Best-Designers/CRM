<?php

if (! defined('ABSPATH')) {
    exit;
}

class GC_CRM_Shortcode {
    public static function init(): void {
        add_shortcode('gc_dealership_crm', [__CLASS__, 'render']);
        add_action('init', [__CLASS__, 'handle_csv_export']);
    }

    public static function render(array $atts = []): string {
        $page_id = get_the_ID() ?: 0;
        $error   = GC_CRM_Auth::maybe_handle_login($page_id);

        if (! GC_CRM_Auth::can_access()) {
            return self::render_login($error);
        }

        self::enqueue_assets();

        $data = self::get_dashboard_data();

        ob_start();
        include GC_CRM_PLUGIN_DIR . 'templates/crm-dashboard.php';
        return (string) ob_get_clean();
    }

    public static function handle_csv_export(): void {
        if (! isset($_GET['gc_crm_export'])) {
            return;
        }

        if (! is_user_logged_in() || ! current_user_can('manage_gc_crm')) {
            wp_die(esc_html__('Unauthorized request.', 'gc-dealership-crm'), 403);
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (! wp_verify_nonce($nonce, 'gc_crm_export')) {
            wp_die(esc_html__('Invalid export nonce.', 'gc-dealership-crm'), 400);
        }

        $type = sanitize_key((string) wp_unslash($_GET['gc_crm_export']));
        $allowed = ['leads', 'contacts', 'reports'];
        if (! in_array($type, $allowed, true)) {
            wp_die(esc_html__('Invalid export type.', 'gc-dealership-crm'), 400);
        }

        self::stream_csv($type);
        exit;
    }

    private static function stream_csv(string $type): void {
        $filename = sprintf('gc-crm-%s-%s.csv', $type, gmdate('Y-m-d-H-i-s'));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $handle = fopen('php://output', 'w');
        if (! $handle) {
            return;
        }

        global $wpdb;

        if ($type === 'leads') {
            $rows = $wpdb->get_results(
                'SELECT l.id, l.status, l.source, l.title, l.estimated_value, l.created_at, l.updated_at,
                    c.first_name, c.last_name, c.email, c.phone
                FROM ' . GC_CRM_DB::table('leads') . ' l
                INNER JOIN ' . GC_CRM_DB::table('contacts') . ' c ON c.id = l.contact_id
                ORDER BY l.created_at DESC',
                ARRAY_A
            );

            fputcsv($handle, ['Lead ID', 'Status', 'Source', 'Title', 'Estimated Value', 'First Name', 'Last Name', 'Email', 'Phone', 'Created At', 'Updated At']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['status'],
                    $row['source'],
                    $row['title'],
                    $row['estimated_value'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['phone'],
                    $row['created_at'],
                    $row['updated_at'],
                ]);
            }
        }

        if ($type === 'contacts') {
            $rows = $wpdb->get_results(
                'SELECT id, first_name, last_name, email, phone, company, created_at, updated_at FROM ' . GC_CRM_DB::table('contacts') . ' ORDER BY created_at DESC',
                ARRAY_A
            );
            fputcsv($handle, ['Contact ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Created At', 'Updated At']);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
        }

        if ($type === 'reports') {
            $status_rows = $wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . GC_CRM_DB::table('leads') . ' GROUP BY status', ARRAY_A);
            $product_rows = $wpdb->get_results('SELECT product_id, product_name, COUNT(*) AS lead_count, COALESCE(SUM(product_price),0) AS revenue FROM ' . GC_CRM_DB::table('product_links') . ' GROUP BY product_id, product_name ORDER BY lead_count DESC', ARRAY_A);

            fputcsv($handle, ['Leads by Status']);
            fputcsv($handle, ['Status', 'Count']);
            foreach ($status_rows as $row) {
                fputcsv($handle, [$row['status'], $row['total']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Revenue by Product']);
            fputcsv($handle, ['Product ID', 'Product Name', 'Lead Count', 'Revenue']);
            foreach ($product_rows as $row) {
                fputcsv($handle, [$row['product_id'], $row['product_name'], $row['lead_count'], $row['revenue']]);
            }
        }

        fclose($handle);
    }

    private static function render_login(?string $error = null): string {
        ob_start();
        include GC_CRM_PLUGIN_DIR . 'templates/login-form.php';
        return (string) ob_get_clean();
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style('gc-crm-style', GC_CRM_PLUGIN_URL . 'assets/css/crm.css', [], GC_CRM_VERSION);
        wp_enqueue_script('gc-crm-script', GC_CRM_PLUGIN_URL . 'assets/js/crm.js', [], GC_CRM_VERSION, true);

        wp_localize_script('gc-crm-script', 'gcCrmData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('gc_crm_nonce'),
            'strings' => [
                'error' => __('Something went wrong. Please try again.', 'gc-dealership-crm'),
            ],
        ]);
    }

    private static function get_dashboard_data(): array {
        global $wpdb;

        $leads_table    = GC_CRM_DB::table('leads');
        $deals_table    = GC_CRM_DB::table('deals');
        $contacts_table = GC_CRM_DB::table('contacts');

        $status_list = ['new_leads', 'contacted', 'quote_sent', 'sold', 'lost'];

        $totals = [
            'total_leads'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$leads_table}"),
            'active_deals'       => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$deals_table} WHERE stage = %s", 'open')),
            'pipeline_value'     => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(estimated_value),0) FROM {$leads_table} WHERE status NOT IN (%s,%s)", 'sold', 'lost')),
            'closed_sales'       => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$leads_table} WHERE status = %s", 'sold')),
            'closed_sales_value' => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(estimated_value),0) FROM {$leads_table} WHERE status = %s", 'sold')),
            'total_contacts'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$contacts_table}"),
        ];

        $kanban = [];
        foreach ($status_list as $status) {
            $kanban[$status] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT l.id, l.status, l.title, l.estimated_value, l.created_at,
                            c.first_name, c.last_name, c.email, c.phone
                     FROM {$leads_table} l
                     INNER JOIN {$contacts_table} c ON c.id = l.contact_id
                     WHERE l.status = %s
                     ORDER BY l.updated_at DESC
                     LIMIT 50",
                    $status
                ),
                ARRAY_A
            );
        }

        $contacts = $wpdb->get_results(
            "SELECT id, first_name, last_name, email, phone, company, created_at FROM {$contacts_table} ORDER BY created_at DESC LIMIT 250",
            ARRAY_A
        );

        $report_status = $wpdb->get_results(
            "SELECT status, COUNT(*) AS total FROM {$leads_table} GROUP BY status",
            ARRAY_A
        );

        $product_links  = GC_CRM_DB::table('product_links');
        $report_product = $wpdb->get_results(
            "SELECT product_id, product_name,
                COUNT(*) AS lead_count,
                COALESCE(SUM(product_price),0) AS revenue
             FROM {$product_links}
             GROUP BY product_id, product_name
             ORDER BY lead_count DESC
             LIMIT 25",
            ARRAY_A
        );

        return [
            'totals'         => $totals,
            'kanban'         => $kanban,
            'contacts'       => $contacts,
            'report_status'  => $report_status,
            'report_product' => $report_product,
        ];
    }
}
