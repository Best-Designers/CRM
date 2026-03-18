<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="gc-crm-login-wrap">
    <form method="post" class="gc-crm-login-form">
        <h2><?php esc_html_e('Golf Cart CRM Login', 'gc-dealership-crm'); ?></h2>
        <?php if (! empty($error)) : ?>
            <div class="gc-crm-alert"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <label for="gc-crm-log"><?php esc_html_e('Username or Email', 'gc-dealership-crm'); ?></label>
        <input id="gc-crm-log" type="text" name="log" required />

        <label for="gc-crm-pwd"><?php esc_html_e('Password', 'gc-dealership-crm'); ?></label>
        <input id="gc-crm-pwd" type="password" name="pwd" required />

        <label class="gc-crm-remember"><input type="checkbox" name="rememberme" value="forever" /> <?php esc_html_e('Remember me', 'gc-dealership-crm'); ?></label>

        <?php wp_nonce_field('gc_crm_login', 'gc_crm_login_nonce'); ?>
        <button type="submit"><?php esc_html_e('Sign In', 'gc-dealership-crm'); ?></button>
    </form>
</div>
