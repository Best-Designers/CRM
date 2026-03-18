<?php

if (! defined('ABSPATH')) {
    exit;
}

class GC_CRM_Integrations {
    public static function init(): void {
        add_action('wpcf7_mail_sent', [__CLASS__, 'capture_cf7_submission']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_product_assets']);
        add_action('woocommerce_after_add_to_cart_button', [__CLASS__, 'render_inquiry_button']);
        add_action('wp_footer', [__CLASS__, 'render_product_modal']);
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function maybe_enqueue_product_assets(): void {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        wp_enqueue_style('gc-crm-product-style', GC_CRM_PLUGIN_URL . 'assets/css/product-inquiry.css', [], GC_CRM_VERSION);
        wp_enqueue_script('gc-crm-product-script', GC_CRM_PLUGIN_URL . 'assets/js/product-inquiry.js', [], GC_CRM_VERSION, true);
    }

    public static function render_inquiry_button(): void {
        global $product;

        if (! function_exists('is_product') || ! is_product() || ! $product instanceof WC_Product) {
            return;
        }

        $payload = [
            'id'    => (int) $product->get_id(),
            'name'  => $product->get_name(),
            'sku'   => (string) $product->get_sku(),
            'url'   => get_permalink($product->get_id()),
            'price' => (float) wc_get_price_to_display($product),
        ];

        echo '<button type="button" class="button alt gc-crm-inquire-button" data-gc-product="' . esc_attr(wp_json_encode($payload)) . '">';
        echo esc_html__('Inquire for More Information', 'gc-dealership-crm');
        echo '</button>';
    }

    public static function render_product_modal(): void {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }

        echo '<div class="gc-crm-modal" id="gc-crm-product-modal" aria-hidden="true">';
        echo '<div class="gc-crm-modal__backdrop" data-gc-close></div>';
        echo '<div class="gc-crm-modal__content" role="dialog" aria-modal="true">';
        echo '<button type="button" class="gc-crm-modal__close" data-gc-close aria-label="' . esc_attr__('Close', 'gc-dealership-crm') . '">&times;</button>';
        echo '<h3>' . esc_html__('Product Inquiry', 'gc-dealership-crm') . '</h3>';

        if (! shortcode_exists('contact-form-7')) {
            echo '<p>' . esc_html__('Contact Form 7 is required to render the inquiry form.', 'gc-dealership-crm') . '</p>';
        } else {
            $form_id = self::get_cf7_form_id();
            if ($form_id > 0) {
                echo do_shortcode('[contact-form-7 id="' . $form_id . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                echo '<p>' . esc_html__('No Contact Form 7 form was found. Create one and optionally set option `gc_crm_cf7_form_id`.', 'gc-dealership-crm') . '</p>';
            }
        }

        echo '</div>';
        echo '</div>';
    }

    private static function get_cf7_form_id(): int {
        $stored = (int) get_option('gc_crm_cf7_form_id', 0);
        if ($stored > 0) {
            return $stored;
        }

        $forms = get_posts([
            'post_type'      => 'wpcf7_contact_form',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'ASC',
        ]);

        return ! empty($forms[0]) ? (int) $forms[0] : 0;
    }

      public static function register_admin_page(): void {
        add_options_page(
            __('Golf Cart CRM Settings', 'gc-dealership-crm'),
            __('Golf Cart CRM', 'gc-dealership-crm'),
            'manage_options',
            'gc-crm-settings',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function register_settings(): void {
        register_setting(
            'gc_crm_settings',
            'gc_crm_cf7_form_id',
            [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 0,
            ]
        );

        add_settings_section(
            'gc_crm_cf7_section',
            __('Contact Form 7 Integration', 'gc-dealership-crm'),
            static function (): void {
                echo '<p>' . esc_html__('Choose which Contact Form 7 form should be rendered by the product inquiry modal.', 'gc-dealership-crm') . '</p>';
            },
            'gc-crm-settings'
        );

        add_settings_field(
            'gc_crm_cf7_form_id',
            __('Inquiry Form', 'gc-dealership-crm'),
            [__CLASS__, 'render_cf7_form_field'],
            'gc-crm-settings',
            'gc_crm_cf7_section'
        );
    }

    public static function render_admin_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Golf Cart CRM Settings', 'gc-dealership-crm'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('gc_crm_settings');
                do_settings_sections('gc-crm-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function render_cf7_form_field(): void {
        $selected = (int) get_option('gc_crm_cf7_form_id', 0);
        $forms    = get_posts([
            'post_type'      => 'wpcf7_contact_form',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        echo '<select name="gc_crm_cf7_form_id" id="gc_crm_cf7_form_id">';
        echo '<option value="0">' . esc_html__('Auto-select first form', 'gc-dealership-crm') . '</option>';
        foreach ($forms as $form) {
            printf(
                '<option value="%1$d" %2$s>%3$s (#%1$d)</option>',
                (int) $form->ID,
                selected($selected, (int) $form->ID, false),
                esc_html(get_the_title($form))
            );
        }
        echo '</select>';
    }

    public static function capture_cf7_submission($contact_form): void {
        if (! class_exists('WPCF7_Submission')) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if (! $submission) {
            return;
        }

        $posted_data = (array) $submission->get_posted_data();
        $map         = (array) get_option('gc_crm_cf7_field_map', []);

        $map = apply_filters('gc_crm_cf7_field_map', $map, $contact_form);

        $first_name = self::extract_field($posted_data, $map, 'first_name');
        $last_name  = self::extract_field($posted_data, $map, 'last_name');
        $email      = sanitize_email(self::extract_field($posted_data, $map, 'email'));
        $phone      = self::extract_field($posted_data, $map, 'phone');
        $message    = self::extract_field($posted_data, $map, 'message');
        $source     = self::extract_field($posted_data, $map, 'source');
        $product_estimated_value = (float) self::extract_field($posted_data, $map, 'product_price');

        if ($first_name && ! $last_name && strpos($first_name, ' ') !== false) {
            $name_parts = preg_split('/\s+/', trim($first_name));
            if (is_array($name_parts) && count($name_parts) > 1) {
                $last_name  = (string) array_pop($name_parts);
                $first_name = implode(' ', $name_parts);
            }
        }

        if (! $email && ! $phone) {
            return;
        }

        global $wpdb;

        $contacts_table = GC_CRM_DB::table('contacts');
        $leads_table    = GC_CRM_DB::table('leads');

        $contact_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$contacts_table} WHERE email = %s OR phone = %s LIMIT 1",
                $email,
                $phone
            )
        );

        if ($contact_id) {
            $wpdb->update(
                $contacts_table,
                [
                    'first_name' => sanitize_text_field($first_name),
                    'last_name'  => sanitize_text_field($last_name),
                    'email'      => $email,
                    'phone'      => sanitize_text_field($phone),
                    'updated_at' => GC_CRM_DB::now(),
                ],
                ['id' => $contact_id],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $contacts_table,
                [
                    'first_name' => sanitize_text_field($first_name),
                    'last_name'  => sanitize_text_field($last_name),
                    'email'      => $email,
                    'phone'      => sanitize_text_field($phone),
                    'created_at' => GC_CRM_DB::now(),
                    'updated_at' => GC_CRM_DB::now(),
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );
            $contact_id = (int) $wpdb->insert_id;
        }

        $lead_title = $message ? wp_trim_words(sanitize_text_field($message), 8, '...') : __('Website inquiry', 'gc-dealership-crm');

        $wpdb->insert(
            $leads_table,
            [
                'contact_id'       => $contact_id,
                'assigned_user_id' => 0,
                'source'           => sanitize_text_field($source ?: 'contact_form_7'),
                'source_ref'       => 'cf7:' . absint($contact_form->id()),
                'status'           => 'new_leads',
                'title'            => $lead_title,
                'details'          => wp_kses_post($message),
                'estimated_value'  => $product_estimated_value,
                'created_at'       => GC_CRM_DB::now(),
                'updated_at'       => GC_CRM_DB::now(),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s']
        );

        $lead_id = (int) $wpdb->insert_id;
        self::log_activity($lead_id, 0, 'lead_created', 'Lead created from Contact Form 7 submission.');

        self::maybe_link_product($lead_id, $posted_data, $map);
        self::send_auto_response($email, $first_name, $last_name);
    }

    private static function maybe_link_product(int $lead_id, array $posted_data, array $map): void {
        $product_id    = absint(self::extract_field($posted_data, $map, 'product_id'));
        $product_name  = self::extract_field($posted_data, $map, 'product_name');
        $product_sku   = self::extract_field($posted_data, $map, 'product_sku');
        $product_url   = esc_url_raw(self::extract_field($posted_data, $map, 'product_url'));
        $product_price = (float) self::extract_field($posted_data, $map, 'product_price');

        if (! $product_id && ! $product_name) {
            return;
        }

        global $wpdb;
        $table = GC_CRM_DB::table('product_links');

        $wpdb->insert(
            $table,
            [
                'lead_id'       => $lead_id,
                'product_id'    => $product_id,
                'product_name'  => sanitize_text_field($product_name),
                'product_sku'   => sanitize_text_field($product_sku),
                'product_url'   => $product_url,
                'product_price' => $product_price,
                'created_at'    => GC_CRM_DB::now(),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%f', '%s']
        );

        self::log_activity($lead_id, 0, 'product_linked', 'Product linked to lead from inquiry submission.');
    }

    public static function log_activity(int $lead_id, int $user_id, string $type, string $message, array $meta = []): void {
        global $wpdb;
        $table = GC_CRM_DB::table('activity');
        $wpdb->insert(
            $table,
            [
                'lead_id'       => $lead_id,
                'user_id'       => $user_id,
                'activity_type' => sanitize_key($type),
                'message'       => sanitize_text_field($message),
                'meta'          => wp_json_encode($meta),
                'created_at'    => GC_CRM_DB::now(),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
    }

    private static function send_auto_response(string $email, string $first_name, string $last_name): void {
        if (! $email) {
            return;
        }

        $name = trim($first_name . ' ' . $last_name);
        $subject = __('Thanks for contacting us about a golf cart', 'gc-dealership-crm');
        $message = sprintf(
            "Hi %s,

Thanks for contacting us. A dealership team member will reach out shortly with details, availability, and pricing options.

If you have specific requirements (seats, range, accessories), just reply to this email.",
            $name ?: __('there', 'gc-dealership-crm')
        );

        wp_mail($email, $subject, $message);
    }

    private static function extract_field(array $posted_data, array $map, string $key): string {
        $field = isset($map[$key]) ? (string) $map[$key] : '';
        if ($field && isset($posted_data[$field])) {
            return self::normalize_field_value($posted_data[$field]);
        }

        $fallback_map = [
            'first_name' => ['first_name', 'your-first-name', 'first-name', 'your-name', 'name', 'fname'],
            'last_name'  => ['last_name', 'your-last-name', 'last-name', 'lname'],
            'email'      => ['your-email', 'email', 'user_email'],
            'phone'      => ['your-phone', 'phone', 'tel', 'mobile'],
            'message'    => ['your-message', 'message', 'inquiry'],
            'source'     => ['gc_source', 'source'],
            'product_id' => ['gc_product_id', 'product_id'],
            'product_name' => ['gc_product_name', 'product_name'],
            'product_sku' => ['gc_product_sku', 'product_sku'],
            'product_url' => ['gc_product_url', 'product_url'],
            'product_price' => ['gc_product_price', 'product_price'],
        ];

        foreach ($fallback_map[$key] ?? [] as $candidate) {
            if (isset($posted_data[$candidate])) {
                return self::normalize_field_value($posted_data[$candidate]);
            }
        }

        return '';
    }

    private static function normalize_field_value($value): string {
        if (is_array($value)) {
            $value = implode(', ', array_map('sanitize_text_field', $value));
        }

        return sanitize_text_field((string) $value);
    }
}
