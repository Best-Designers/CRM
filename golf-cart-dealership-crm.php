<?php
/**
 * Plugin Name: Golf Cart Dealership CRM
 * Description: Lightweight frontend CRM for golf cart dealerships with Contact Form 7 and WooCommerce integrations.
 * Version: 1.0.0
 * Author: Golf Cart CRM
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: gc-dealership-crm
 */

if (! defined('ABSPATH')) {
    exit;
}

define('GC_CRM_VERSION', '1.0.0');
define('GC_CRM_PLUGIN_FILE', __FILE__);
define('GC_CRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GC_CRM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GC_CRM_PLUGIN_DIR . 'includes/class-gc-crm-db.php';
require_once GC_CRM_PLUGIN_DIR . 'includes/class-gc-crm-auth.php';
require_once GC_CRM_PLUGIN_DIR . 'includes/class-gc-crm-shortcode.php';
require_once GC_CRM_PLUGIN_DIR . 'includes/class-gc-crm-integrations.php';
require_once GC_CRM_PLUGIN_DIR . 'includes/class-gc-crm-ajax.php';

register_activation_hook(__FILE__, ['GC_CRM_DB', 'activate']);

final class GC_Dealership_CRM {
    private static ?GC_Dealership_CRM $instance = null;

    public static function instance(): GC_Dealership_CRM {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_capability']);

        GC_CRM_Shortcode::init();
        GC_CRM_Integrations::init();
        GC_CRM_Ajax::init();
    }

    public function register_capability(): void {
        $role = get_role('administrator');
        if ($role && ! $role->has_cap('manage_gc_crm')) {
            $role->add_cap('manage_gc_crm');
        }
    }
}

GC_Dealership_CRM::instance();
