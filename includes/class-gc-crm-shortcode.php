<?php

if (! defined('ABSPATH')) {
    exit;
}

class GC_CRM_Shortcode {
    public static function init(): void {
        add_shortcode('gc_dealership_crm', [__CLASS__, 'render']);
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
            'total_leads'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$leads_table}"),
            'active_deals'      => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$deals_table} WHERE stage = %s", 'open')),
            'pipeline_value'    => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(estimated_value),0) FROM {$leads_table} WHERE status NOT IN (%s,%s)", 'sold', 'lost')),
            'closed_sales'      => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$leads_table} WHERE status = %s", 'sold')),
            'closed_sales_value'=> (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(estimated_value),0) FROM {$leads_table} WHERE status = %s", 'sold')),
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

        $report_status = $wpdb->get_results(
            "SELECT status, COUNT(*) AS total FROM {$leads_table} GROUP BY status",
            ARRAY_A
        );

        $product_links = GC_CRM_DB::table('product_links');
        $report_product = $wpdb->get_results(
            "SELECT product_id, product_name,
                COUNT(*) AS lead_count,
                COALESCE(SUM(product_price),0) AS revenue
             FROM {$product_links}
             GROUP BY product_id, product_name
             ORDER BY lead_count DESC
             LIMIT 10",
            ARRAY_A
        );

        return [
            'totals'         => $totals,
            'kanban'         => $kanban,
            'report_status'  => $report_status,
            'report_product' => $report_product,
        ];
    }
}
