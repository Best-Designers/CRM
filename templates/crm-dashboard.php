<?php
if (! defined('ABSPATH')) {
    exit;
}

$label_map = [
    'new_leads' => __('New Leads', 'gc-dealership-crm'),
    'contacted' => __('Contacted', 'gc-dealership-crm'),
    'quote_sent'=> __('Quote Sent', 'gc-dealership-crm'),
    'sold'      => __('Sold', 'gc-dealership-crm'),
    'lost'      => __('Lost', 'gc-dealership-crm'),
];
?>
<?php
$money = static function ($amount) {
    if (function_exists('wc_price')) {
        return wc_price((float) $amount);
    }

    return '$' . number_format_i18n((float) $amount, 2);
};
?>
<div class="gc-crm-app" id="gc-crm-app">
    <div class="gc-crm-top">
        <div class="gc-crm-card"><strong><?php esc_html_e('Total Leads', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html((string) $data['totals']['total_leads']); ?></span></div>
        <div class="gc-crm-card"><strong><?php esc_html_e('Active Deals', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html((string) $data['totals']['active_deals']); ?></span></div>
        <div class="gc-crm-card"><strong><?php esc_html_e('Total Pipeline Value', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html($money($data['totals']['pipeline_value'])); ?></span></div>
        <div class="gc-crm-card"><strong><?php esc_html_e('Closed Sales', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html((string) $data['totals']['closed_sales']); ?></span></div>
    </div>

    <div class="gc-crm-filters">
        <input type="search" id="gc-crm-search" placeholder="<?php esc_attr_e('Search by name, email, phone', 'gc-dealership-crm'); ?>" />
        <select id="gc-crm-status-filter">
            <option value=""><?php esc_html_e('All Statuses', 'gc-dealership-crm'); ?></option>
            <?php foreach ($label_map as $status_key => $label) : ?>
                <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="gc-crm-board" id="gc-crm-board">
        <?php foreach ($label_map as $status_key => $label) : ?>
            <section class="gc-crm-column" data-status="<?php echo esc_attr($status_key); ?>">
                <h3><?php echo esc_html($label); ?> <small>(<?php echo esc_html((string) count($data['kanban'][$status_key])); ?>)</small></h3>
                <div class="gc-crm-dropzone">
                    <?php foreach ($data['kanban'][$status_key] as $lead) :
                        $full_name = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
                        ?>
                        <article class="gc-crm-lead" draggable="true" data-lead-id="<?php echo esc_attr((string) $lead['id']); ?>" data-status="<?php echo esc_attr($status_key); ?>">
                            <header>
                                <strong><?php echo esc_html($full_name ?: __('Unnamed Lead', 'gc-dealership-crm')); ?></strong>
                                <span><?php echo esc_html($money((float) $lead['estimated_value'])); ?></span>
                            </header>
                            <p><?php echo esc_html($lead['title']); ?></p>
                            <ul>
                                <li><?php echo esc_html((string) $lead['email']); ?></li>
                                <li><?php echo esc_html((string) $lead['phone']); ?></li>
                            </ul>
                            <button type="button" class="gc-crm-open-lead" data-lead-id="<?php echo esc_attr((string) $lead['id']); ?>"><?php esc_html_e('View', 'gc-dealership-crm'); ?></button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="gc-crm-reports">
        <div class="gc-crm-report-card">
            <h4><?php esc_html_e('Leads by Status', 'gc-dealership-crm'); ?></h4>
            <ul>
                <?php foreach ($data['report_status'] as $row) : ?>
                    <li><span><?php echo esc_html($label_map[$row['status']] ?? $row['status']); ?></span><strong><?php echo esc_html((string) $row['total']); ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="gc-crm-report-card">
            <h4><?php esc_html_e('Product Performance', 'gc-dealership-crm'); ?></h4>
            <ul>
                <?php foreach ($data['report_product'] as $row) :
                    $conversion = ((int) $data['totals']['closed_sales'] > 0) ? round(((float) $row['lead_count'] / (int) $data['totals']['closed_sales']) * 100, 2) : 0;
                    ?>
                    <li>
                        <span><?php echo esc_html((string) $row['product_name']); ?></span>
                        <small><?php echo esc_html(sprintf(__('Leads: %d | Revenue: %s | Conversion: %s%%', 'gc-dealership-crm'), (int) $row['lead_count'], $money((float) $row['revenue']), $conversion)); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="gc-crm-modal" id="gc-crm-lead-modal" aria-hidden="true">
    <div class="gc-crm-modal__backdrop" data-close></div>
    <div class="gc-crm-modal__content">
        <button type="button" class="gc-crm-modal__close" data-close>&times;</button>
        <div id="gc-crm-lead-content"></div>
    </div>
</div>
