<?php

if (! defined('ABSPATH')) {
    exit;
}

class GC_CRM_DB {
    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        $queries = [];

        $queries[] = "CREATE TABLE {$prefix}gc_crm_contacts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            last_name VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            company VARCHAR(190) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY email_idx (email),
            KEY phone_idx (phone)
        ) {$charset};";

        $queries[] = "CREATE TABLE {$prefix}gc_crm_leads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id BIGINT UNSIGNED NOT NULL,
            assigned_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(120) NOT NULL DEFAULT '',
            source_ref VARCHAR(190) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'new_leads',
            title VARCHAR(255) NOT NULL DEFAULT '',
            details LONGTEXT NULL,
            estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            last_contacted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY contact_status_idx (contact_id, status),
            KEY assigned_status_idx (assigned_user_id, status),
            KEY created_at_idx (created_at)
        ) {$charset};";

        $queries[] = "CREATE TABLE {$prefix}gc_crm_deals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            stage VARCHAR(30) NOT NULL DEFAULT 'open',
            close_date DATE NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_stage_idx (lead_id, stage),
            KEY close_date_idx (close_date)
        ) {$charset};";

        $queries[] = "CREATE TABLE {$prefix}gc_crm_notes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            note LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_created_idx (lead_id, created_at)
        ) {$charset};";

        $queries[] = "CREATE TABLE {$prefix}gc_crm_activity (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            activity_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_activity_idx (lead_id, created_at),
            KEY type_idx (activity_type)
        ) {$charset};";

        $queries[] = "CREATE TABLE {$prefix}gc_crm_product_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_sku VARCHAR(120) NOT NULL DEFAULT '',
            product_url VARCHAR(255) NOT NULL,
            product_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY lead_product_idx (lead_id, product_id),
            KEY product_id_idx (product_id)
        ) {$charset};";

        foreach ($queries as $query) {
            dbDelta($query);
        }

        self::create_default_options();

        $role = get_role('administrator');
        if ($role && ! $role->has_cap('manage_gc_crm')) {
            $role->add_cap('manage_gc_crm');
        }
    }

    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'gc_crm_' . $name;
    }

    public static function now(): string {
        return current_time('mysql');
    }

    private static function create_default_options(): void {
        add_option('gc_crm_cf7_form_id', 0);

        add_option('gc_crm_cf7_field_map', [
            'first_name'    => 'first_name',
            'last_name'     => 'last_name',
            'email'         => 'your-email',
            'phone'         => 'your-phone',
            'message'       => 'your-message',
            'product_id'    => 'gc_product_id',
            'product_name'  => 'gc_product_name',
            'product_sku'   => 'gc_product_sku',
            'product_url'   => 'gc_product_url',
            'product_price' => 'gc_product_price',
            'source'        => 'gc_source',
        ]);
    }
}
