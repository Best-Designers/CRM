<?php

if (! defined('ABSPATH')) {
    exit;
}

class GC_CRM_Auth {
    public static function can_access(): bool {
        return is_user_logged_in() && current_user_can('manage_gc_crm');
    }

    public static function maybe_handle_login(int $page_id = 0): ?string {
        if (! isset($_POST['gc_crm_login_nonce'])) {
            return null;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gc_crm_login_nonce'])), 'gc_crm_login')) {
            return __('Security check failed.', 'gc-dealership-crm');
        }

        $username = isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '';
        $password = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
        $remember = ! empty($_POST['rememberme']);

        $creds = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            return $user->get_error_message();
        }

        if (! user_can($user, 'manage_gc_crm')) {
            wp_logout();
            return __('Your account does not have CRM access.', 'gc-dealership-crm');
        }

        $redirect = $page_id ? get_permalink($page_id) : home_url('/');
        wp_safe_redirect($redirect ?: home_url('/'));
        exit;
    }
}
