<?php
if ( ! defined( 'ABSPATH' ) ) exit;



add_action('wp_ajax_eftm_create_tenant', 'eftm_handle_create_tenant');
add_action('wp_ajax_eftm_check_property_availability', 'eftm_handle_check_property_availability');
add_action('wp_ajax_eftm_render_tenant_profile', 'eftm_handle_ajax_tenant_breakdown');
add_action('wp_ajax_nopriv_eftm_render_tenant_profile', 'eftm_handle_ajax_tenant_breakdown');
add_action('wp_ajax_eftm_edit_tenant', 'eftm_execute_ajax_tenant_modification');
add_action('wp_ajax_eftm_delete_tenant', 'eftm_execute_ajax_tenant_deletion');
add_action('wp_ajax_eftm_get_properties', 'eftm_global_execute_properties_fetch');



if ( ! function_exists('eftm_get_total_units') ) {
    function eftm_get_total_units($property_id) {
        $units = get_field('number_unit', $property_id);
        if (!$units) $units = get_post_meta($property_id, 'number_unit', true);
        if (!$units) $units = get_field('property_units', $property_id);
        if (!$units) $units = get_post_meta($property_id, 'property_units', true);
        return intval($units ?: 1);
    }
}
if ( ! function_exists('eftm_get_occupied_count') ) {
    function eftm_get_occupied_count($property_id, $exclude_tenant_id = 0) {
        $args = [
            'post_type' => 'ef_tenant',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'property_id',
                    'value' => $property_id,
                    'compare' => '='
                ],
                [
                    'key' => 'property_id',
                    'value' => '"' . $property_id . '"',
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'property_id',
                    'value' => 'i:' . $property_id . ';',
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'property_id',
                    'value' => 's:' . strlen($property_id) . ':"' . $property_id . '";',
                    'compare' => 'LIKE'
                ]
            ]
        ];
        if ($exclude_tenant_id) {
            $args['post__not_in'] = [$exclude_tenant_id];
        }
        $tenants = get_posts($args);
        return count($tenants);
    }
}



function eftm_handle_create_tenant() {
    if ( ! isset($_POST['create_tenant_nonce']) || ! wp_verify_nonce($_POST['create_tenant_nonce'], 'create_tenant_action') ) {
        wp_send_json_error('Invalid request (nonce).', 403);
    }
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized.', 403);
    }
    $name   = sanitize_text_field($_POST['tenant_name'] ?? '');
    $email  = sanitize_email($_POST['tenant_email'] ?? '');
    $phone  = sanitize_text_field($_POST['tenant_phone'] ?? '');
    $address= sanitize_text_field($_POST['tenant_address'] ?? '');
    $unit   = intval($_POST['tenant_unit'] ?? 1);
    $rent   = floatval($_POST['tenant_rent'] ?? 0);
    $start  = sanitize_text_field($_POST['tenant_start'] ?? '');
    $end    = sanitize_text_field($_POST['tenant_end'] ?? '');
    $due    = intval($_POST['tenant_due_day'] ?? 0);
    $propId = intval($_POST['property_id'] ?? 0);
    if ( empty($name) ) wp_send_json_error('Name is required.');
    if ( empty($email) || ! is_email($email) ) wp_send_json_error('Valid email is required.');
    $tenant_id = wp_insert_post([
        'post_type'   => 'ef_tenant',
        'post_title'  => $name,
        'post_status' => 'publish',
    ], true);
    if ( is_wp_error($tenant_id) || ! $tenant_id ) wp_send_json_error('Could not create tenant');
    $meta = [
        'tenant_email' => $email, 'tenant_phone' => $phone, 'tenant_address' => $address,
        'tenant_unit' => $unit, 'monthly_rent' => $rent, 'tenant_start' => $start,
        'tenant_end' => $end, 'rent_due_day' => $due, 'property_id' => $propId
    ];
    foreach ($meta as $k => $v) {
        if ( function_exists('update_field') ) update_field($k, $v, $tenant_id);
        else update_post_meta($tenant_id, $k, $v);
    }
    wp_send_json_success(['id' => $tenant_id]);
}
function eftm_handle_check_property_availability() {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'property_availability_nonce')) {
        wp_send_json_error('Invalid security check.', 403);
    }
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized.', 403);
    }
    $property_id = intval($_POST['property_id'] ?? 0);
    $tenant_id   = intval($_POST['tenant_id'] ?? 0);
    if (!$property_id) wp_send_json_error('Property ID is required.');
    $property_address = get_field('add_address', $property_id) ?: get_post_meta($property_id, 'add_address', true) ?: get_the_title($property_id);
    $total_units = eftm_get_total_units($property_id);
    $occupied_count = eftm_get_occupied_count($property_id, $tenant_id);
    $available_units = max(0, $total_units - $occupied_count);
    wp_send_json_success([
        'property_id' => $property_id,
        'property_address' => $property_address,
        'all_occupied' => ($available_units === 0),
        'next_available_unit' => $occupied_count + 1,
    ]);
}
function eftm_handle_ajax_tenant_breakdown() {
    $tenant_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : 0;
    if (!$tenant_id) wp_die();
    $tenant_name    = get_the_title($tenant_id);
    $monthly_rent   = floatval(get_field('monthly_rent', $tenant_id) ?: get_post_meta($tenant_id, 'monthly_rent', true));
    $due_day        = get_field('rent_due_day', $tenant_id) ?: get_post_meta($tenant_id, 'rent_due_day', true);
    $email          = get_field('tenant_email', $tenant_id) ?: get_post_meta($tenant_id, 'tenant_email', true);
    $phone          = get_field('tenant_phone', $tenant_id) ?: get_post_meta($tenant_id, 'tenant_phone', true);
    $address        = get_field('tenant_address', $tenant_id) ?: get_post_meta($tenant_id, 'tenant_address', true);
    $contract_start = get_field('tenant_start', $tenant_id) ?: get_post_meta($tenant_id, 'tenant_start', true);
    $contract_end   = get_field('tenant_end', $tenant_id) ?: get_post_meta($tenant_id, 'tenant_end', true);
    $unit           = get_field('tenant_unit', $tenant_id) ?: get_post_meta($tenant_id, 'tenant_unit', true);
    $prop_field     = get_field('property_id', $tenant_id) ?: get_post_meta($tenant_id, 'property_id', true);
    $propId = is_object($prop_field) ? $prop_field->ID : (is_array($prop_field) ? ($prop_field['ID'] ?? $prop_field[0]->ID ?? 0) : intval($prop_field));
    $property_display = $propId ? get_the_title($propId) : 'No Property Assigned';
    $payment_history = [];
    $p_query = new WP_Query([
        'post_type'      => 'payment', 'post_status'    => 'publish', 'posts_per_page' => -1,
        'meta_query'     => [['key' => 'associated_tenant', 'value' => $tenant_id, 'compare' => '=']],
        'meta_key'       => 'date_of_payment', 'orderby' => 'meta_value', 'order' => 'DESC'
    ]);
    if ($p_query->have_posts()) {
        while ($p_query->have_posts()) {
            $p_query->the_post();
            $pay_id = get_the_ID();
            $raw_date = get_field('date_of_payment', $pay_id) ?: get_post_meta($pay_id, 'date_of_payment', true);
            $ts = $raw_date ? strtotime($raw_date) : get_the_date('U', $pay_id);
            $payment_history[] = [
                'id' => $pay_id, 'period' => date('Y-m', $ts), 'date' => date('n/j/Y', $ts),
                'method' => (get_field('mode_of_payment', $pay_id) ?: get_post_meta($pay_id, 'mode_of_payment', true)) ?: 'Cash',
                'amount' => floatval(get_field('amount_paid', $pay_id) ?: get_post_meta($pay_id, 'amount_paid', true))
            ];
        }
        wp_reset_postdata();
    }
    $initials = ''; $words = explode(' ', $tenant_name); foreach ($words as $w) $initials .= strtoupper(substr($w, 0, 1));
    $initials = substr($initials, 0, 2);
    $due_day_display = !empty($due_day) ? $due_day . (in_array($due_day, [1,21,31]) ? 'st' : (in_array($due_day, [2,22]) ? 'nd' : (in_array($due_day, [3,23]) ? 'rd' : 'th'))) : '-';
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div class="ef-header-profile-block">
            <div class="ef-profile-avatar-big"><?php echo esc_html($initials); ?></div>
            <div>
                <h2 style="margin: 0 0 2px 0; font-size: 22px; color: #0f172a; font-weight: 700;"><?php echo esc_html($tenant_name); ?></h2>
                <div style="color: #64748b; font-size: 14px; font-weight: 500;"><?php echo esc_html($property_display); ?></div>
            </div>
        </div>
        <?php if (is_user_logged_in()) : ?>
        <div class="ef-action-btn-group">
            <button class="ef-btn-action"
                    data-id="<?php echo $tenant_id; ?>" data-name="<?php echo esc_attr($tenant_name); ?>" data-property-id="<?php echo $propId; ?>"
                    data-unit="<?php echo esc_attr($unit); ?>" data-rent="<?php echo $monthly_rent; ?>" data-address="<?php echo esc_attr($address); ?>"
                    data-email="<?php echo esc_attr($email); ?>" data-phone="<?php echo esc_attr($phone); ?>" data-start="<?php echo $contract_start; ?>"
                    data-end="<?php echo $contract_end; ?>" data-due="<?php echo esc_attr($due_day); ?>" onclick="efTriggerFormPopup(this)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"></path></svg> Edit
            </button>
            <button class="ef-btn-action ef-btn-delete-icon" onclick="efLiveDeleteTenant(<?php echo $tenant_id; ?>, '<?php echo esc_js($tenant_name); ?>')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="ef-meta-grid-3col">
        <div class="ef-meta-info-card"><div class="ef-meta-card-label">Monthly rent</div><div class="ef-meta-card-value ef-text-green">AED <?php echo number_format($monthly_rent, 2); ?></div></div>
        <div class="ef-meta-info-card"><div class="ef-meta-card-label">Due day</div><div class="ef-meta-card-value"><?php echo esc_html($due_day_display); ?> <span style="font-size: 13px; font-weight:400; color: #64748b;">of month</span></div></div>
        <div class="ef-meta-info-card"><div class="ef-meta-card-label">Payments</div><div class="ef-meta-card-value"><?php echo count($payment_history); ?></div></div>
    </div>
    <div class="ef-section-title-sub">Contact & Contract Details</div>
    <div class="ef-contact-row-flex" style="margin-bottom: 12px;">
        <div class="ef-contact-item">✉️ <?php echo esc_html($email ?: 'None'); ?></div>
        <div class="ef-contact-item">📞 <?php echo esc_html($phone ?: 'None'); ?></div>
    </div>
    <div class="ef-contact-row-flex" style="margin-bottom: 16px;"><div class="ef-contact-item">📍 <?php echo esc_html($address ?: 'No Address Provided'); ?></div></div>
    <div style="font-size: 14px; display: flex; align-items: center;"><span class="ef-contract-badge-pill">Contract Period</span><span style="color: #0f172a; font-weight: 500;"><?php echo esc_html($contract_start ?: 'N/A'); ?> ➔ <?php echo esc_html($contract_end ?: 'N/A'); ?></span></div>
    <div class="ef-section-title-sub" style="margin-top: 32px;">Payment Ledger History</div>
    <div class="ef-payment-history-list">
        <?php if (!empty($payment_history)) : foreach ($payment_history as $p) : ?>
            <div class="ef-payment-row-card">
                <div class="ef-pay-row-left"><div class="ef-pay-period-title"><?php echo esc_html($p['period']); ?></div><div class="ef-pay-meta-sub"><?php echo esc_html($p['method']); ?> · <?php echo esc_html($p['date']); ?></div></div>
                <div class="ef-pay-row-right">
                    <div class="ef-pay-amount-value">AED <?php echo number_format($p['amount'], 2); ?></div>
                    <button class="ef-btn-receipt"
                            data-ref="REC-<?php echo str_pad($p['id'], 6, '0', STR_PAD_LEFT); ?>" data-tenant="<?php echo esc_attr($tenant_name); ?>"
                            data-prop="<?php echo esc_attr($property_display); ?>" data-date="<?php echo esc_attr($p['date']); ?>"
                            data-period="<?php echo esc_attr($p['period']); ?>" data-method="<?php echo esc_attr($p['method']); ?>"
                            data-amount="<?php echo number_format($p['amount'], 2); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Receipt
                    </button>
                </div>
            </div>
        <?php endforeach; else : ?>
            <div style="color: #64748b; font-size: 14px; font-style: italic; padding: 20px 0; text-align: center; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px;">No recorded invoice transactions found.</div>
        <?php endif; ?>
    </div>
    <?php
    wp_die();
}
function eftm_execute_ajax_tenant_modification() {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'edit_tenant_nonce')) {
        wp_send_json_error('Invalid security check.', 403);
    }
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized.', 403);
    }
    $tid = intval($_POST['tenant_id'] ?? 0);
    if (!$tid) wp_send_json_error('Tenant ID is required.');
    $name = sanitize_text_field($_POST['new_name'] ?? '');
    wp_update_post(['ID' => $tid, 'post_title' => $name]);
    $meta = [
        'tenant_email' => sanitize_email($_POST['new_email'] ?? ''),
        'tenant_phone' => sanitize_text_field($_POST['new_phone'] ?? ''),
        'tenant_address' => sanitize_text_field($_POST['new_address'] ?? ''),
        'tenant_unit' => intval($_POST['new_unit'] ?? 1),
        'monthly_rent' => floatval($_POST['new_rent'] ?? 0),
        'tenant_start' => sanitize_text_field($_POST['new_start'] ?? ''),
        'tenant_end' => sanitize_text_field($_POST['new_end'] ?? ''),
        'rent_due_day' => intval($_POST['new_due_day'] ?? 0),
        'property_id' => intval($_POST['new_property_id'] ?? 0)
    ];
    foreach ($meta as $k => $v) {
        if ( function_exists('update_field') ) update_field($k, $v, $tid);
        else update_post_meta($tid, $k, $v);
    }
    wp_send_json_success();
}
function eftm_execute_ajax_tenant_deletion() {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'delete_tenant_nonce')) {
        wp_send_json_error('Invalid security check.', 403);
    }
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized.', 403);
    }
    $tid = intval($_POST['tenant_id'] ?? 0);
    if ($tid && wp_delete_post($tid, true)) wp_send_json_success();
    else wp_send_json_error('Delete failed.');
}
function eftm_global_execute_properties_fetch() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized.', 403);
    }
    $q = new WP_Query(['post_type' => 'property', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    $res = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) {
            $q->the_post();
            $id = get_the_ID();
            $res[] = [
                'id' => $id, 'name' => get_the_title(),
                'address' => get_field('add_address', $id) ?: get_post_meta($id, 'add_address', true) ?: '',
                'units' => eftm_get_total_units($id),
                'rent' => get_field('property_rent', $id) ?: get_post_meta($id, 'property_rent', true),
                'date' => get_field('property_start_date', $id) ?: get_post_meta($id, 'property_start_date', true),
                'end_date' => get_field('property_end_date', $id) ?: get_post_meta($id, 'property_end_date', true),
                'due_day' => get_field('property_due_day', $id) ?: get_post_meta($id, 'property_due_day', true),
            ];
        }
        wp_reset_postdata();
    }
    wp_send_json_success($res);
}
