<?php

add_shortcode('ef_log_payment_btn', function() {
    if (!is_user_logged_in()) return '';
    $tenants_query = new WP_Query([
        'post_type'      => 'ef_tenant',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    ]);

    ob_start();
    ?>

    <button class="ef-main-action-trigger-btn" onclick="efOpenLogPaymentModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Log payment
    </button>

    <style>
        .ef-modal-backdrop-blur {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        body.ef-no-scroll {
            overflow: hidden !important;
        }
    </style>
    <div id="efLogPaymentGlobalModal" class="ef-modal-backdrop-blur" onclick="if(event.target == this) { this.style.display='none'; document.body.classList.remove('ef-no-scroll'); }">
        <div class="ef-modal-window-surface" style="position: relative;">

            <div class="ef-modal-window-header">
                <h3 id="efModalTitle">Log a payment</h3>
                <button class="ef-modal-close-x" onclick="document.getElementById('efLogPaymentGlobalModal').style.display='none'; document.body.classList.remove('ef-no-scroll');">✕</button>
            </div>

            <div class="ef-modal-input-field-group">
                <label>Tenant</label>
                <select id="ef_log_tenant" class="ef-modal-element-input">
                    <option value="">Select Tenant Profile...</option>
                    <?php
                    if ($tenants_query->have_posts()) {
                        while ($tenants_query->have_posts()) {
                            $tenants_query->the_post();
                            echo '<option value="'.get_the_ID().'">'.get_the_title().'</option>';
                        }
                        wp_reset_postdata();
                    }
                    ?>
                </select>
            </div>

            <div class="ef-input-flex-row-2col">
                <div class="ef-modal-input-field-group" style="margin-bottom:0;">
                    <label>Amount (AED)</label>
                    <input type="number" id="ef_log_amount" placeholder="1200" class="ef-modal-element-input">
                </div>
                <div class="ef-modal-input-field-group" style="margin-bottom:0;">
                    <label>Method</label>
                    <select id="ef_log_method" class="ef-modal-element-input">
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cash">Cash</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Card Payment">Card Payment</option>
                    </select>
                </div>
            </div>

            <div class="ef-input-flex-row-2col">
                <div class="ef-modal-input-field-group" style="margin-bottom:0;">
                    <label>Period (YYYY-MM)</label>
                    <input type="text" id="ef_log_period" value="<?php echo date('Y-m'); ?>" placeholder="e.g. 2026-05" class="ef-modal-element-input">
                </div>
                <div class="ef-modal-input-field-group" style="margin-bottom:0;">
                    <label>Date</label>
                    <input type="date" id="ef_log_date" value="<?php echo date('Y-m-d'); ?>" class="ef-modal-element-input">
                </div>
            </div>

            <div class="ef-checkbox-custom-row">
                <input type="checkbox" id="ef_log_receipt_pdf" checked>
                <label for="ef_log_receipt_pdf">Generate PDF receipt automatically</label>
            </div>

            <input type="hidden" id="ef_log_payment_id" value="">
            <input type="hidden" id="ef_payment_nonce" value="<?php echo wp_create_nonce('ef_payment_nonce'); ?>">

            <div class="ef-modal-action-footer-cluster">
                <button class="ef-btn-modal-base ef-btn-modal-cancel" onclick="document.getElementById('efLogPaymentGlobalModal').style.display='none'; document.body.classList.remove('ef-no-scroll');">Cancel</button>
                <button id="efSubmitBtn" class="ef-btn-modal-base ef-btn-modal-submit" onclick="efSubmitNewPaymentRecord()">Log payment</button>
            </div>

        </div>
    </div>

    <script>
    function efOpenLogPaymentModal() {
        document.getElementById('efModalTitle').innerText = 'Log a payment';
        document.getElementById('efSubmitBtn').innerText = 'Log payment';
        document.getElementById('ef_log_payment_id').value = '';
        document.getElementById('ef_log_tenant').value = '';
        document.getElementById('ef_log_amount').value = '';
        document.getElementById('ef_log_method').value = 'Bank Transfer';
        document.getElementById('ef_log_period').value = '<?php echo date('Y-m'); ?>';
        document.getElementById('ef_log_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('efLogPaymentGlobalModal').style.display = 'flex';
        document.body.classList.add('ef-no-scroll');
    }

    function efSubmitNewPaymentRecord() {
        const payment_id = document.getElementById('ef_log_payment_id').value;
        const tenant = document.getElementById('ef_log_tenant').value;
        const amount = document.getElementById('ef_log_amount').value;
        const method = document.getElementById('ef_log_method').value;
        const date = document.getElementById('ef_log_date').value;
        const period = document.getElementById('ef_log_period').value;

        if(!tenant || !amount || !date || !period) {
            alert('Please select a tenant and enter all payment parameters (including period).');
            return;
        }

        var fd = new FormData();
        fd.append('action', payment_id ? 'ef_action_update_payment_record' : 'ef_action_create_payment_record');
        fd.append('nonce', document.getElementById('ef_payment_nonce').value);
        if (payment_id) fd.append('payment_id', payment_id);
        fd.append('tenant_id', tenant);
        fd.append('amount', amount);
        fd.append('method', method);
        fd.append('date', date);
        fd.append('period', period);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(res => {
            if(res.success) {
                document.getElementById('efLogPaymentGlobalModal').style.display = 'none';
                document.body.classList.remove('ef-no-scroll');

                window.dispatchEvent(new CustomEvent('ef_global_tenant_updated', {
                    detail: { id: tenant }
                }));

                window.location.reload();
            } else {
                alert('Database entry submission failure.');
            }
        }).catch(err => { alert('Database communication channel interrupted.'); });
    }
    </script>
    <?php
    return ob_get_clean();
});
