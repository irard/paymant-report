<?php
add_shortcode('ef_dashboard_actions', function() { 
    if (!is_user_logged_in()) return '';
    $current_year = date('Y'); 
    $selected_year = isset($_GET['report_year']) ? intval($_GET['report_year']) : $current_year;
    ob_start(); 
    ?> 
    <div class="ef-filter-action-toolbar"> 
        <div class="ef-dropdown-wrapper"> 
            <select id="efYearFilter" class="ef-control-dropdown"> 
                <?php 
                $start_year = 1980;
                $end_year = intval(date('Y')) + 10;
                for ($y = $end_year; $y >= $start_year; $y--) {
                    printf('<option value="%1$d" %2$s>%1$d</option>', $y, selected($selected_year, $y, false));
                }
                ?>
            </select> 
        </div> 
 
        <div class="ef-btn-group"> 
            <button id="efBtnCSV" class="ef-btn ef-btn-secondary"> 
                <svg class="ef-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"> 
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path> 
                    <polyline points="14 2 14 8 20 8"></polyline> 
                    <line x1="12" y1="12" x2="12" y2="18"></line> 
                    <polyline points="9 15 12 18 15 15"></polyline> 
                </svg> 
                CSV 
            </button> 
 
            <button id="efBtnPDF" class="ef-btn ef-btn-primary"> 
                <svg class="ef-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"> 
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path> 
                    <polyline points="14 2 14 8 20 8"></polyline> 
                    <line x1="12" y1="12" x2="12" y2="18"></line> 
                    <polyline points="9 15 12 18 15 15"></polyline> 
                </svg> 
                PDF 
            </button> 
        </div> 
    </div> 
 
    <style> 
     
    </style> 
 
    <script> 
    document.addEventListener("DOMContentLoaded", function() { 
        const yearFilter = document.getElementById('efYearFilter'); 
        const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>"; 

        yearFilter.addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('report_year', this.value);
            window.location.href = url.toString();
        });
 
        function triggerDownload(format) { 
            const selectedYear = yearFilter.value; 
            const form = document.createElement('form'); 
            form.method = 'POST'; 
            form.action = ajaxUrl; 
            form.style.display = 'none'; 
 
            const actionInput = document.createElement('input'); 
            actionInput.name = 'action'; 
            actionInput.value = 'ef_export_report'; 
            form.appendChild(actionInput); 
 
            const yearInput = document.createElement('input'); 
            yearInput.name = 'report_year'; 
            yearInput.value = selectedYear; 
            form.appendChild(yearInput); 
 
            const formatInput = document.createElement('input'); 
            formatInput.name = 'report_format'; 
            formatInput.value = format; 
            form.appendChild(formatInput); 
 
            document.body.appendChild(form); 
            form.submit(); 
            document.body.removeChild(form); 
        } 
 
        document.getElementById('efBtnCSV').addEventListener('click', () => triggerDownload('csv')); 
        document.getElementById('efBtnPDF').addEventListener('click', () => triggerDownload('pdf')); 
    }); 
    </script> 
    <?php 
    return ob_get_clean(); 
}); 
 
add_shortcode('monthly_breakdown', function() { 
    if (!is_user_logged_in()) return '';
    $current_year = date('Y'); 
    $selected_year = isset($_GET['report_year']) ? intval($_GET['report_year']) : $current_year;
 
    $payment_post_type = 'payment';        
    $use_acf_date_field = false;           
    $acf_date_field_name = 'payment_date';  
 
    $t_query = new WP_Query(array( 
        'post_type'      => 'ef_tenant', 
        'post_status'    => 'publish', 
        'posts_per_page' => -1, 
    )); 
 
    $tenants = [];
    if ($t_query->have_posts()) { 
        while ($t_query->have_posts()) { 
            $t_query->the_post(); 
            $t_id = get_the_ID(); 
            $tenants[] = [
                'rent'  => floatval(get_field('monthly_rent', $t_id) ?: get_post_meta($t_id, 'monthly_rent', true)),
                'start' => get_field('tenant_start', $t_id) ?: get_post_meta($t_id, 'tenant_start', true),
                'end'   => get_field('tenant_end', $t_id) ?: get_post_meta($t_id, 'tenant_end', true),
            ];
        } 
        wp_reset_postdata(); 
    } 
 
    $chart_labels = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'); 
    $monthly_data = array(); 
 
    for ($m = 1; $m <= 12; $m++) { 
        $padded_month = str_pad($m, 2, '0', STR_PAD_LEFT); 
        $first_day_of_month = $selected_year . '-' . $padded_month . '-01';
        $last_day_of_month  = date('Y-m-t', strtotime($first_day_of_month));

        $monthly_rent_expected = 0.00;
        foreach ($tenants as $tenant) {
            $t_start = $tenant['start'];
            $t_end   = $tenant['end'];
            
            // Check if tenant was active during this month
            // Active if (start date <= last day of month) AND (no end date OR end date >= first day of month)
            $is_active = true;
            if (!empty($t_start) && $t_start > $last_day_of_month) $is_active = false;
            if ($is_active && !empty($t_end) && $t_end < $first_day_of_month) $is_active = false;

            if ($is_active) {
                $monthly_rent_expected += $tenant['rent'];
            }
        }
 
        $args = array( 
            'post_type'      => $payment_post_type, 
            'post_status'    => 'publish', 
            'posts_per_page' => -1, 
        ); 
 
        if ($use_acf_date_field) { 
            $start_date = $selected_year . $padded_month . '01'; 
            $end_date   = $selected_year . $padded_month . '31'; 
            $args['meta_query'] = array( 
                array( 
                    'key'     => $acf_date_field_name, 
                    'value'   => array($start_date, $end_date), 
                    'compare' => 'BETWEEN', 
                    'type'    => 'DATE' 
                ) 
            ); 
        } else { 
            $args['date_query'] = array( 
                array( 
                    'year'  => $selected_year, 
                    'month' => $padded_month, 
                ), 
            ); 
        } 
 
        $p_query = new WP_Query($args); 
        $revenue_collected = 0.00; 
 
        if ($p_query->have_posts()) { 
            while ($p_query->have_posts()) { 
                $p_query->the_post(); 
                $pay_id = get_the_ID();
                $pay_val = get_field('amount_paid', $pay_id) ?: get_post_meta($pay_id, 'amount_paid', true); 
                if ($pay_val) { 
                    $revenue_collected += floatval($pay_val); 
                } 
            } 
            wp_reset_postdata(); 
        } 
 
        $gap = $monthly_rent_expected - $revenue_collected; 
        if ($gap < 0) { $gap = 0; } 
 
        $rate = ($monthly_rent_expected > 0) ? ($revenue_collected / $monthly_rent_expected) * 100 : 0; 
        if ($rate > 100) { $rate = 100; } 
 
        $monthly_data[$m] = array( 
            'label'    => $chart_labels[$m - 1] . ' ' . $selected_year, 
            'revenue'  => $revenue_collected, 
            'expected' => $monthly_rent_expected, 
            'gap'      => $gap, 
            'rate'     => round($rate) . '%' 
        ); 
    } 
 
    ob_start(); 
    ?> 
 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
 
    
 
    <div class="ef-breakdown-wrapper"> 
        <div class="ef-breakdown-header"> 
            <h3 class="ef-breakdown-title">Monthly Revenue Breakdown</h3> 
            <div class="ef-breakdown-subtitle">Financial performance metrics for <?php echo $selected_year; ?></div> 
        </div> 
 
        <div class="ef-chart-container"> 
            <canvas id="efRevenueChart"></canvas> 
        </div> 
 
        <div style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;"> 
            <table class="ef-breakdown-table" style="min-width: 600px;"> 
                <thead> 
                    <tr> 
                        <th>Month</th> 
                        <th class="ef-col-right">Revenue Collected</th> 
                        <th class="ef-col-right">Expected Rent</th> 
                        <th class="ef-col-right">Remaining Gap</th> 
                        <th class="ef-col-right">Collection Rate</th> 
                    </tr> 
                </thead> 
                <tbody> 
                    <?php foreach ($monthly_data as $data): ?> 
                    <tr> 
                        <td class="ef-txt-bold" style="color: #0f172a;"><?php echo $data['label']; ?></td> 
                        <td class="ef-col-right ef-txt-bold ef-brand-success">AED <?php echo number_format($data['revenue']); ?></td> 
                        <td class="ef-col-right ef-brand-muted">AED <?php echo number_format($data['expected']); ?></td> 
                        <td class="ef-col-right <?php echo ($data['gap'] > 0) ? 'ef-brand-danger ef-txt-bold' : 'ef-brand-muted'; ?>"> 
                            AED <?php echo number_format($data['gap']); ?> 
                        </td> 
                        <td class="ef-col-right ef-txt-bold" style="color: #0f172a;"><?php echo $data['rate']; ?></td> 
                    </tr> 
                    <?php endforeach; ?> 
                </tbody> 
            </table> 
        </div> 
    </div> 
 
    <script> 
    document.addEventListener("DOMContentLoaded", function() { 
        const ctx = document.getElementById('efRevenueChart').getContext('2d'); 
        const revenueData = [<?php echo implode(',', array_column($monthly_data, 'revenue')); ?>]; 
 
        new Chart(ctx, { 
            type: 'line', 
            data: { 
                labels: <?php echo json_encode($chart_labels); ?>, 
                datasets: [{ 
                    label: 'Revenue Collected', 
                    data: revenueData, 
                    borderColor: '#00a86b',  
                    backgroundColor: 'rgba(0, 168, 107, 0.05)',  
                    fill: true, 
                    tension: 0.35,  
                    pointRadius: 3, 
                    borderWidth: 2, 
                    pointBackgroundColor: '#00a86b' 
                }] 
            }, 
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } }, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        ticks: { 
                            callback: function(value) { return 'AED ' + value.toLocaleString(); }, 
                            color: '#64748b' 
                        }, 
                        grid: { borderDash: [4, 4], color: '#f1f5f9' } 
                    }, 
                    x: {  
                        ticks: { color: '#64748b' }, 
                        grid: { display: false }  
                    } 
                } 
            } 
        }); 
    }); 
    </script> 
 
    <?php 
    return ob_get_clean(); 
});
