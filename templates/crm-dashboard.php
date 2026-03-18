<?php
if (! defined('ABSPATH')) {
    exit;
}

$label_map = [
    'new_leads'  => __('New Leads', 'gc-dealership-crm'),
    'contacted'  => __('Contacted', 'gc-dealership-crm'),
    'quote_sent' => __('Quote Sent', 'gc-dealership-crm'),
    'sold'       => __('Sold', 'gc-dealership-crm'),
    'lost'       => __('Lost', 'gc-dealership-crm'),
];

$money = static function ($amount): string {
    return '$' . number_format_i18n((float) $amount, 2);
};

$export_nonce = wp_create_nonce('gc_crm_export');
$export_base  = home_url(add_query_arg([]));
?>
<div class="gc-crm-app" id="gc-crm-app">
    <nav class="gc-crm-nav" aria-label="CRM sections">
        <button class="gc-crm-nav__item is-active" data-view="dashboard"><?php esc_html_e('Dashboard', 'gc-dealership-crm'); ?></button>
        <button class="gc-crm-nav__item" data-view="leads"><?php esc_html_e('Leads', 'gc-dealership-crm'); ?></button>
        <button class="gc-crm-nav__item" data-view="contacts"><?php esc_html_e('Contacts', 'gc-dealership-crm'); ?></button>
        <button class="gc-crm-nav__item" data-view="reports"><?php esc_html_e('Reports', 'gc-dealership-crm'); ?></button>
    </nav>

    <section class="gc-crm-view is-active" data-view="dashboard">
        <div class="gc-crm-top">
            <div class="gc-crm-card"><strong><?php esc_html_e('Total Leads', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html((string) $data['totals']['total_leads']); ?></span></div>
            <div class="gc-crm-card"><strong><?php esc_html_e('Active Deals', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html((string) $data['totals']['active_deals']); ?></span></div>
            <div class="gc-crm-card"><strong><?php esc_html_e('Pipeline Value', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html($money($data['totals']['pipeline_value'])); ?></span></div>
            <div class="gc-crm-card"><strong><?php esc_html_e('Closed Sales', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html((string) $data['totals']['closed_sales']); ?></span></div>
            <div class="gc-crm-card"><strong><?php esc_html_e('Total Contacts', 'gc-dealership-crm'); ?></strong><span><?php echo esc_html((string) $data['totals']['total_contacts']); ?></span></div>
        </div>
        
        <div class="gc-crm-report-card gc-crm-todo">
            <div class="gc-crm-todo__head">
                <h4><?php esc_html_e('To Do List', 'gc-dealership-crm'); ?></h4>
                <button type="button" class="gc-crm-todo__clear" id="gc-crm-todo-clear"><?php esc_html_e('Clear All', 'gc-dealership-crm'); ?></button>
            </div>
            <form class="gc-crm-todo__add" id="gc-crm-todo-add-form">
                <input type="text" id="gc-crm-todo-add-input" placeholder="<?php esc_attr_e('Add a new to-do item', 'gc-dealership-crm'); ?>" />
                <button type="submit"><?php esc_html_e('Add', 'gc-dealership-crm'); ?></button>
            </form>
            <ul id="gc-crm-todo-list">
                <?php foreach (($data['todo_items'] ?? []) as $todo) : ?>
                    <?php if (! empty($todo['removed'])) {
                        continue;
                    } ?>
                    <li class="gc-crm-todo-item <?php echo ! empty($todo['checked']) ? 'is-checked' : ''; ?>" data-todo-id="<?php echo esc_attr((string) $todo['id']); ?>">
                        <label>
                            <input type="checkbox" class="gc-crm-todo-check" <?php checked(! empty($todo['checked'])); ?> />
                            <span class="gc-crm-todo-text"><?php echo esc_html((string) ($todo['text'] ?? '')); ?></span>
                        </label>
                        <div class="gc-crm-todo-item__actions">
                            <?php if (empty($todo['is_auto'])) : ?>
                                <button type="button" class="gc-crm-todo-edit"><?php esc_html_e('Edit', 'gc-dealership-crm'); ?></button>
                            <?php endif; ?>
                            <button type="button" class="gc-crm-todo-remove"><?php esc_html_e('Remove', 'gc-dealership-crm'); ?></button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="gc-crm-view" data-view="leads">
        <div class="gc-crm-section-head">
            <h3><?php esc_html_e('Leads Pipeline', 'gc-dealership-crm'); ?></h3>
            <a class="gc-crm-export" href="<?php echo esc_url(add_query_arg(['gc_crm_export' => 'leads', '_wpnonce' => $export_nonce], $export_base)); ?>"><?php esc_html_e('Export Leads CSV', 'gc-dealership-crm'); ?></a>
        </div>


        <button type="button" class="gc-crm-toggle-add" id="gc-crm-toggle-add"><?php esc_html_e('Add Lead', 'gc-dealership-crm'); ?></button>
        <div id="gc-crm-add-wrap" class="gc-crm-add-wrap" hidden>
            <form id="gc-crm-create-lead" class="gc-crm-create-lead">
                <input type="text" name="first_name" placeholder="First name" />
                <input type="text" name="last_name" placeholder="Last name" />
                <input type="email" name="email" placeholder="Email" />
                <input type="text" name="phone" placeholder="Phone" />
                <input type="text" name="title" placeholder="Lead title" />
                <select name="status">
                    <?php foreach ($label_map as $status_key => $label) : ?>
                        <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="details" placeholder="Details"></textarea>
                <button type="submit"><?php esc_html_e('Save Lead', 'gc-dealership-crm'); ?></button>
            </form>
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
    </section>

    <section class="gc-crm-view" data-view="contacts">
        <div class="gc-crm-section-head">
            <h3><?php esc_html_e('Contacts', 'gc-dealership-crm'); ?></h3>
            <a class="gc-crm-export" href="<?php echo esc_url(add_query_arg(['gc_crm_export' => 'contacts', '_wpnonce' => $export_nonce], $export_base)); ?>"><?php esc_html_e('Export Contacts CSV', 'gc-dealership-crm'); ?></a>
        </div>

        <div class="gc-crm-table-wrap">
            <table class="gc-crm-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'gc-dealership-crm'); ?></th>
                    <th><?php esc_html_e('Email', 'gc-dealership-crm'); ?></th>
                    <th><?php esc_html_e('Phone', 'gc-dealership-crm'); ?></th>
                    <th><?php esc_html_e('Company', 'gc-dealership-crm'); ?></th>
                    <th><?php esc_html_e('Created', 'gc-dealership-crm'); ?></th>
                    <th><?php esc_html_e('Actions', 'gc-dealership-crm'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($data['contacts'] as $contact) : ?>
                    <tr data-contact-id="<?php echo esc_attr((string) $contact['id']); ?>">
                        <td><?php echo esc_html(trim($contact['first_name'] . ' ' . $contact['last_name'])); ?></td>
                        <td><?php echo esc_html((string) $contact['email']); ?></td>
                        <td><?php echo esc_html((string) $contact['phone']); ?></td>
                        <td><?php echo esc_html((string) $contact['company']); ?></td>
                        <td><?php echo esc_html((string) $contact['created_at']); ?></td>
                        <td><button type="button" class="gc-crm-delete-contact" data-contact-id="<?php echo esc_attr((string) $contact['id']); ?>"><?php esc_html_e('Delete', 'gc-dealership-crm'); ?></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="gc-crm-view" data-view="reports">
        <div class="gc-crm-section-head">
            <h3><?php esc_html_e('Reports', 'gc-dealership-crm'); ?></h3>
            <a class="gc-crm-export" href="<?php echo esc_url(add_query_arg(['gc_crm_export' => 'reports', '_wpnonce' => $export_nonce], $export_base)); ?>"><?php esc_html_e('Export Reports CSV', 'gc-dealership-crm'); ?></a>
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
    </section>
</div>

<div class="gc-crm-modal" id="gc-crm-lead-modal" aria-hidden="true">
    <div class="gc-crm-modal__backdrop" data-close></div>
    <div class="gc-crm-modal__content">
        <button type="button" class="gc-crm-modal__close" data-close>&times;</button>
        <div id="gc-crm-lead-content"></div>
    </div>
</div>
