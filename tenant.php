<?php
if ( ! defined( "ABSPATH" ) ) exit;
add_shortcode('add_tenant', function(){
    if (!is_user_logged_in()) return '';
    ob_start(); ?>
    <button id="atc-open" class="atc-open-btn">+ Add tenant</button>
    <div id="atc-modal" class="atc-modal" aria-hidden="true" style="display:none;" onclick="if(event.target === this) { this.style.display='none'; document.body.classList.remove('ef-no-scroll'); }">
      <div class="atc-panel" role="dialog" aria-modal="true" aria-labelledby="atc-title">
        <div class="atc-header">
          <h3 id="atc-title">Add New Tenant</h3>
          <button id="atc-close" class="atc-close" aria-label="Close">&times;</button>
        </div>
        <form id="atc-form" class="atc-form" autocomplete="off">
          <?php wp_nonce_field('create_tenant_action','create_tenant_nonce'); ?>
          <div class="atc-row-full"><label class="atc-label">Name <input id="atc-name" name="tenant_name" required></label></div>
          <div class="atc-row-full"><label class="atc-label">Property <select id="atc-property" name="property_id" required><option value="">Loading properties…</option></select></label></div>
          <div class="atc-row"><label class="atc-label">Unit <input id="atc-unit" name="tenant_unit" value="1" readonly></label>
                               <label class="atc-label">Rent (AED) <input id="atc-rent" name="tenant_rent" type="number" step="100" value="" placeholder="Enter Amount" min="1000" required></label></div>
          <div class="atc-row"><label class="atc-label">Email <input id="atc-email" name="tenant_email" type="email" required></label>
                               <label class="atc-label">Phone <input id="atc-phone" name="tenant_phone" type="tel" required></label></div>
          <div class="atc-row-full"><label class="atc-label">Address <input id="atc-address" name="tenant_address"></label></div>
          <div class="atc-row contract"><label class="atc-label">Contract start <input id="atc-start" name="tenant_start" type="date" required></label>
                               <label class="atc-label">Contract end <input id="atc-end" name="tenant_end" type="date" required></label>
                               <label class="atc-label">Due day <input id="atc-due-display" type="text" required placeholder="Select day" readonly></label></div>
          <div id="atc-due-popup" class="atc-due-popup"><div class="atc-due-grid"><?php for ($i=1;$i<=31;$i++) echo "<button type='button' class='atc-due-box' data-day='$i'>$i</button>"; ?></div></div>
          <input type="hidden" id="atc-due" name="tenant_due_day" required>
          <div class="atc-actions"><button type="button" id="atc-cancel" class="atc-btn-cancel">Cancel</button><button type="submit" class="atc-btn-primary">Add tenant</button></div>
        </form>
      </div>
    </div>
    <script>
    (function(){
      const ajax = '<?php echo admin_url("admin-ajax.php"); ?>';
      document.addEventListener('DOMContentLoaded', function(){
        const openBtn = document.getElementById('atc-open'), modal = document.getElementById('atc-modal'), closeBtn = document.getElementById('atc-close'), cancelBtn = document.getElementById('atc-cancel'), form = document.getElementById('atc-form'), propSelect = document.getElementById('atc-property');
        if (!openBtn || !modal || !form) return;
        function openModal(){
            modal.style.display = 'flex';
            document.body.classList.add('ef-no-scroll');
            loadProperties();
        }
        function closeModal(){
            modal.style.display = 'none';
            document.body.classList.remove('ef-no-scroll');
            form.reset();
        }
        openBtn.onclick = (e) => { e.preventDefault(); openModal(); };
        closeBtn.onclick = cancelBtn.onclick = () => closeModal();
        modal.onclick = (e) => { if(e.target===modal) closeModal(); };
        function loadProperties(){
          propSelect.innerHTML = '<option value="">Loading…</option>';
          fetch(ajax + '?action=eftm_get_properties').then(r=>r.json()).then(res=>{
              propSelect.innerHTML = '<option value="">Select property</option>';
              res.data.forEach(p=>{
                  const opt=document.createElement('option'); opt.value=p.id; opt.textContent=p.name;
                  opt.dataset.rent=p.rent; opt.dataset.start=p.date; opt.dataset.end=p.end_date; opt.dataset.due=p.due_day;
                  propSelect.appendChild(opt);
              });
          });
        }
        propSelect.onchange = function(){
            const pid = this.value; if(!pid) return;
            const unitInput = document.getElementById('atc-unit');
            unitInput.value = 'Checking availability...';
            fetch(ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'eftm_check_property_availability', property_id:pid, security:'<?php echo wp_create_nonce("property_availability_nonce"); ?>'}) })
            .then(r=>r.json()).then(res=>{
                if(res.success){
                    document.getElementById('atc-address').value = res.data.property_address;
                    if(res.data.all_occupied){
                        unitInput.value = 'Not available';
                        unitInput.setAttribute('data-status', 'not-available');
                        form.querySelector('button[type="submit"]').disabled=true;
                    }
                    else {
                        unitInput.value = 'Available';
                        unitInput.setAttribute('data-status', 'available');
                        form.querySelector('button[type="submit"]').disabled=false;
                        const opt = propSelect.selectedOptions[0];
                        document.getElementById('atc-rent').value = opt.dataset.rent || 1000;
                        if(opt.dataset.start) document.getElementById('atc-start').value = new Date(opt.dataset.start).toISOString().split('T')[0];
                        if(opt.dataset.end) document.getElementById('atc-end').value = new Date(opt.dataset.end).toISOString().split('T')[0];
                        if(opt.dataset.due){ document.getElementById('atc-due').value=opt.dataset.due; document.getElementById('atc-due-display').value=opt.dataset.due; }
                    }
                }
            });
        };
        const dueDisp=document.getElementById('atc-due-display'), duePop=document.getElementById('atc-due-popup'), dueInp=document.getElementById('atc-due');
        dueDisp.onclick = () => duePop.style.display='flex';
        duePop.onclick = e => {
          if(e.target.dataset.day){ dueInp.value=e.target.dataset.day; dueDisp.value=e.target.dataset.day; duePop.style.display='none'; }
          else if(e.target === duePop) { duePop.style.display = 'none'; }
        };
        form.onsubmit = e => {
          e.preventDefault(); const fd=new FormData(form); fd.append('action','eftm_create_tenant');
          fetch(ajax,{method:'POST', body:fd}).then(r=>r.json()).then(res=>{ if(res.success){ alert('Added!'); document.body.classList.remove('ef-no-scroll'); location.reload(); } });
        };
      });
    })();
    </script>
    <?php return ob_get_clean();
});
add_shortcode('tenant_header', function() {
    $q = new WP_Query(['post_type'=>'ef_tenant', 'post_status'=>'publish', 'posts_per_page'=>-1]);
    $count = $q->found_posts;
    wp_reset_postdata();
    return '<div style="margin-bottom:16px;">
            <div style="font-size:15px; color:#64748b; font-weight:500;">' . $count . ' active tenants</div>
        </div>';
});
add_shortcode('tenant_list', function() {
    $payment_post_type = 'payment'; $tenant_meta_key = 'associated_tenant';
    $q = new WP_Query(['post_type'=>'ef_tenant', 'post_status'=>'publish', 'posts_per_page'=>-1, 'order'=>'ASC']);
    ob_start(); ?>
    <div class="ef-dashboard-sidebar-independent">
        <input type="text" class="ef-search-box" placeholder="Search tenants..." id="efTenantSearch">
        <div id="efTenantListWrapper">
            <?php if ($q->have_posts()) : $count=0; $current_month=date('m'); $current_year=date('Y'); while ($q->have_posts()) : $q->the_post();
                $tid=get_the_ID(); $rent=get_field('monthly_rent', $tid) ?: get_post_meta($tid, 'monthly_rent', true); $name=get_the_title();
                $pid=get_field('property_id', $tid) ?: get_post_meta($tid, 'property_id', true);
                $addr=$pid?(get_field('add_address',$pid)?:get_post_meta($pid,'add_address',true)?:get_the_title($pid)):'No Address';
                $paid_this_month = 0.00;
                $p_query = new WP_Query(['post_type'=>$payment_post_type,'post_status'=>'publish','posts_per_page'=>-1,'date_query'=>[['year'=>$current_year,'month'=>$current_month]],'meta_query'=>[['key'=>$tenant_meta_key,'value'=>$tid,'compare'=>'=']]]);
                if($p_query->have_posts()){ while($p_query->have_posts()){$p_query->the_post(); $p_id = get_the_ID(); $paid_this_month+=floatval(get_field('amount_paid',$p_id) ?: get_post_meta($p_id, 'amount_paid', true));}wp_reset_postdata();}
                $initials=''; $words=explode(' ', $name); foreach($words as $w) $initials.=strtoupper(substr($w,0,1));
            ?>
                <div class="ef-tenant-card-item" data-id="<?php echo $tid; ?>" id="tenant-row-<?php echo $tid; ?>" onclick="efDispatchGlobalView(<?php echo $tid; ?>)">
                    <div class="ef-tenant-meta"><div class="ef-avatar-circle"><?php echo substr($initials,0,2); ?></div>
                        <div class="ef-tenant-info-text"><div class="ef-tenant-name"><?php echo esc_html($name); ?></div><div class="ef-tenant-location"><?php echo esc_html($addr); ?></div></div>
                    </div>
                    <div class="ef-tenant-right-aside"><div class="ef-tenant-rent-badge">AED <span class="tenant-list-rent"><?php echo number_format(floatval($rent)); ?></span><div style="font-size:10px; font-weight:400; color:#64748b; margin-top:-2px;">monthly</div></div>
                        <?php if (floatval($rent) <= 0): ?><div style="font-size: 10px; color: #64748b; margin-top:4px;">No Rent Set</div>
                        <?php elseif ($paid_this_month >= floatval($rent)): ?><div class="ef-payment-status-badge status-paid">Paid</div>
                        <?php elseif ($paid_this_month > 0): ?><div class="ef-payment-status-badge status-partial">Partial</div>
                        <?php else: ?><div class="ef-payment-status-badge status-unpaid">Unpaid</div><?php endif; ?>
                    </div>
                </div>
            <?php $count++; endwhile; wp_reset_postdata(); endif; ?>
        </div>
    </div>
    <script>
    function efDispatchGlobalView(tid){
        window.dispatchEvent(new CustomEvent('ef_load_tenant',{detail:{id:tid}}));
        document.querySelectorAll('.ef-tenant-card-item').forEach(c=>c.classList.remove('ef-active-tenant'));
        document.getElementById('tenant-row-'+tid).classList.add('ef-active-tenant');
    }
    document.getElementById('efTenantSearch').oninput=function(){
        let f=this.value.toLowerCase();
        document.querySelectorAll('.ef-tenant-card-item').forEach(c=>{
            c.style.display=c.querySelector('.ef-tenant-name').textContent.toLowerCase().includes(f)?'flex':'none';
        });
    };
    </script>
    <?php return ob_get_clean();
});
add_shortcode('tenant_profile', function() {
    ob_start(); ?>
    <style>
        @media (min-width: 769px) {
            #efTenantProfileModal {
                display: block !important;
                position: static !important;
                background: none !important;
                width: auto !important;
                height: auto !important;
                flex: 1;
                z-index: auto !important;
            }
            #efTenantProfileModal .ef-popup-modal-box {
                position: static !important;
                max-width: none !important;
                width: 100% !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: none !important;
                height: auto !important;
                overflow: visible !important;
            }
            #efTenantProfileModal .ef-popup-modal-box > button {
                display: none !important;
            }
            #efWorkspaceDynamicScreen {
                background: #fff;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                padding: 24px;
            }
        }
        /* Basic modal styles in case they are not defined elsewhere */
        .ef-popup-modal-container {
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
        .atc-modal {
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
        .atc-panel {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            position: relative;
            height: 60%;
            overflow-y: auto;
        }
        .ef-popup-modal-box {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            height: 60%;
            overflow-y: auto;
        }
        @media (min-width: 280px) and (max-width: 769px) {
            .atc-row.contract {
                display: block;
            }
            .ef-grid-modal-2col.contract {
                display: block;
            }
        }
    </style>
    <div id="efTenantProfileModal" class="ef-popup-modal-container" style="display:none;" onclick="if(event.target === this) { this.style.display='none'; document.body.classList.remove('ef-no-scroll'); }">
        <div class="ef-popup-modal-box" style="max-width: 800px; width: 90%; position: relative;">
            <button onclick="document.getElementById('efTenantProfileModal').style.display='none'; document.body.classList.remove('ef-no-scroll');" style="position: absolute; top: 15px; right: 15px; border: none; background: transparent; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
            <div id="efWorkspaceDynamicScreen">
                <div style="text-align:center; padding: 40px; color: #64748b;">Select a tenant to view profile.</div>
            </div>
        </div>
    </div>
    <div id="efGlobalRightEditModal" class="ef-popup-modal-container" style="display:none;" onclick="if(event.target === this) { this.style.display='none'; document.body.classList.remove('ef-no-scroll'); }">
        <div class="ef-popup-modal-box" style="position: relative;">
            <button onclick="document.getElementById('efGlobalRightEditModal').style.display='none'; document.body.classList.remove('ef-no-scroll');" style="position: absolute; top: 15px; right: 15px; border: none; background: transparent; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
            <h3 style="margin-top:0;">Modify Tenant Profile</h3>
            <input type="hidden" id="modal_field_id">
            <div class="ef-form-group"><label>Name</label><input type="text" id="modal_field_name" class="ef-form-input-box"></div>
            <div class="ef-form-group"><label>Property</label><select id="modal_field_property" class="ef-form-input-box"></select></div>
            <div class="ef-grid-modal-2col">
                <div class="ef-form-group"><label>Unit</label><input type="text" id="modal_field_unit" class="ef-form-input-box" readonly></div>
                <div class="ef-form-group"><label>Rent (AED)</label><input type="number" id="modal_field_rent" class="ef-form-input-box" step="0.01"></div>
            </div>
            <div class="ef-grid-modal-2col">
                <div class="ef-form-group"><label>Email</label><input type="email" id="modal_field_email" class="ef-form-input-box"></div>
                <div class="ef-form-group"><label>Phone</label><input type="text" id="modal_field_phone" class="ef-form-input-box"></div>
            </div>
            <div class="ef-form-group"><label>Address</label><input type="text" id="modal_field_address" class="ef-form-input-box"></div>
            <div class="ef-grid-modal-2col contract">
                <div class="ef-form-group"><label>Start Date</label><input type="date" id="modal_field_start" class="ef-form-input-box"></div>
                <div class="ef-form-group"><label>End Date</label><input type="date" id="modal_field_end" class="ef-form-input-box"></div>
            </div>
            <div class="ef-form-group"><label>Due Day</label><input type="text" id="modal_field_due_day_display" class="ef-form-input-box" readonly></div>
            <input type="hidden" id="modal_field_due_day">
            <div id="ef-modal-due-popup" class="ef-modal-due-popup"><div class="ef-due-grid"><?php for($i=1;$i<=31;$i++)echo "<button class='ef-due-box' data-day='$i'>$i</button>"; ?></div></div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:10px;">
                <button onclick="document.getElementById('efGlobalRightEditModal').style.display='none'; document.body.classList.remove('ef-no-scroll');" class="ef-btn-action">Cancel</button>
                <button onclick="efSubmitModalFormEdits()" id="efModalActionBtn" class="ef-btn-action">Save changes</button>
            </div>
        </div>
    </div>
    <div id="receipt-template" style="display:none;">
        <div class="receipt-container" style="padding:40px; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#334155; width:800px; background:#fff;">
            <div class="receipt-header" style="display:flex; justify-content:space-between; border-bottom:2px solid #f1f5f9; padding-bottom:20px;">
                <div style="display:flex; align-items:center; gap:15px;">
                    <img src="https://estateflow.digital/wp-content/uploads/2026/06/cropped-estate.png" style="width:48px; height:48px; object-fit:contain;">
                    <div>
                        <div style="color:#0f172a; font-size:22px; font-weight:800; letter-spacing:-0.5px;">ESTATE FLOW</div>
                        <div style="color:#64748b; font-size:13px; font-weight:500;">Property Suite</div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="color:#00a86b; font-size:24px; font-weight:800; margin-bottom:4px; letter-spacing:-0.5px;">PAYMENT RECEIPT</div>
                    <div style="color:#64748b; font-size:13px;">Receipt Reference: <span id="r-ref" style="font-weight:700; color:#0f172a;"></span></div>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; margin:32px 0;">
                <div>
                    <div style="color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:12px; letter-spacing:0.5px;">Received From</div>
                    <div id="r-tenant" style="font-size:20px; font-weight:700; color:#0f172a; margin-bottom:4px;"></div>
                    <div id="r-prop" style="color:#64748b; font-size:14px;"></div>
                </div>
                <div style="text-align:right; min-width:240px;">
                    <div style="color:#94a3b8; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:12px; letter-spacing:0.5px;">Transaction Details</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; font-size:13px; text-align:left;">
                        <span style="color:#64748b; font-weight:600;">Payment Date:</span><span id="r-date" style="text-align:right; font-weight:700; color:#0f172a;"></span>
                        <span style="color:#64748b; font-weight:600;">Allocation Period:</span><span id="r-period" style="text-align:right; font-weight:700; color:#0f172a;"></span>
                        <span style="color:#64748b; font-weight:600;">Method:</span><span id="r-method" style="text-align:right; font-weight:700; color:#0f172a;"></span>
                    </div>
                </div>
            </div>
            <table style="width:100%; border-collapse:collapse; margin-top:32px;">
                <thead>
                    <tr>
                        <th style="text-align:left; border-bottom:1px solid #e2e8f0; padding:12px 0; color:#94a3b8; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">Description</th>
                        <th style="text-align:right; border-bottom:1px solid #e2e8f0; padding:12px 0; color:#94a3b8; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">Total Allocation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding:20px 0; border-bottom:1px solid #f1f5f9;">
                            <div style="font-weight:700; color:#0f172a; margin-bottom:4px; font-size:15px;">Rental Payment Allocation</div>
                            <div id="r-desc" style="color:#64748b; font-size:13px;"></div>
                        </td>
                        <td style="text-align:right; padding:20px 0; border-bottom:1px solid #f1f5f9; font-weight:700; font-size:16px; color:#0f172a;">
                            AED <span id="r-amt-table"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div style="display:flex; justify-content:flex-end; align-items:center; gap:32px; margin-top:32px;">
                <span style="font-weight:700; color:#0f172a; font-size:15px;">Amount Paid:</span>
                <span style="color:#00a86b; font-size:24px; font-weight:800; letter-spacing:-0.5px;">AED <span id="r-amt-total"></span></span>
            </div>
            <div style="text-align:center; color:#94a3b8; font-size:11px; margin-top:60px; padding-top:20px; border-top:1px solid #f1f5f9;">
                This is an electronically generated document. No physical signature is required.<br>
                <span style="font-style:italic; color:#cbd5e1; margin-top:8px; display:block;">Thank you for your business!</span>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    (function(){
        const ajax = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.addEventListener('ef_load_tenant', e => {
            const screen = document.getElementById('efWorkspaceDynamicScreen');
            const modal = document.getElementById('efTenantProfileModal');
            if (window.innerWidth <= 768) {
                modal.style.display = 'flex';
                document.body.classList.add('ef-no-scroll');
            }
            screen.innerHTML = '<div style="text-align:center; padding:40px;">Loading profile...</div>';
            const fd = new FormData(); fd.append('action', 'eftm_render_tenant_profile'); fd.append('tenant_id', e.detail.id);
            fetch(ajax, { method: 'POST', body: fd }).then(r => r.text()).then(html => {
                screen.innerHTML = html; attachReceiptEvents();
            });
        });
        window.attachReceiptEvents = function() {
            document.querySelectorAll('.ef-btn-receipt').forEach(btn => {
                btn.onclick = () => {
                    const d = btn.dataset;
                    document.getElementById('r-ref').innerText = d.ref; document.getElementById('r-tenant').innerText = d.tenant; document.getElementById('r-prop').innerText = d.prop;
                    document.getElementById('r-date').innerText = d.date; document.getElementById('r-period').innerText = d.period; document.getElementById('r-method').innerText = d.method;
                    document.getElementById('r-amt-table').innerText = d.amount; document.getElementById('r-amt-total').innerText = d.amount;
                    document.getElementById('r-desc').innerText = `Unit Reference: ${d.prop} (${d.period})`;
                    const element = document.querySelector('#receipt-template .receipt-container');
                    html2pdf().from(element).set({
                        margin: 0.2,
                        filename: `Receipt-${d.ref}.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, logging: false, useCORS: true },
                        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                    }).save();
                };
            });
        }
        window.efTriggerFormPopup = function(btn) {
            const d = btn.dataset;
            document.getElementById('modal_field_id').value = d.id;
            document.getElementById('modal_field_name').value = d.name;
            document.getElementById('modal_field_unit').value = d.unit;
            document.getElementById('modal_field_rent').value = d.rent;
            document.getElementById('modal_field_address').value = d.address;
            document.getElementById('modal_field_email').value = d.email;
            document.getElementById('modal_field_phone').value = d.phone;
            document.getElementById('modal_field_start').value = d.start;
            document.getElementById('modal_field_end').value = d.end;
            document.getElementById('modal_field_due_day').value = d.due;
            document.getElementById('modal_field_due_day_display').value = d.due;
            loadEditProps(d.propertyId);
            document.getElementById('efGlobalRightEditModal').style.display = 'flex';
            document.body.classList.add('ef-no-scroll');
        }
        function loadEditProps(sel){
            const s = document.getElementById('modal_field_property'); s.innerHTML='<option>Loading...</option>';
            fetch(ajax+'?action=eftm_get_properties').then(r=>r.json()).then(res=>{
                s.innerHTML='<option value="">Select property</option>';
                res.data.forEach(p=>{ const o=document.createElement('option'); o.value=p.id; o.textContent=p.name; o.dataset.rent=p.rent; o.dataset.start=p.date; o.dataset.end=p.end_date; o.dataset.due=p.due_day; if(p.id==sel) o.selected=true; s.appendChild(o); });
                if(sel) s.dispatchEvent(new Event('change'));
            });
        }
        document.getElementById('modal_field_property').onchange = function(){
            const pid = this.value; if(!pid) return;
            const u = document.getElementById('modal_field_unit'); u.value='Checking...';
            fetch(ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'eftm_check_property_availability', property_id:pid, tenant_id:document.getElementById('modal_field_id').value, security:'<?php echo wp_create_nonce("property_availability_nonce"); ?>'}) })
            .then(r=>r.json()).then(res=>{
                if(res.success){
                    document.getElementById('modal_field_address').value = res.data.property_address;
                    if(res.data.all_occupied){
                        u.value = 'Not available';
                        u.setAttribute('data-status', 'not-available');
                        document.getElementById('efModalActionBtn').disabled=true;
                    }
                    else {
                        u.value = 'Available';
                        u.setAttribute('data-status', 'available');
                        document.getElementById('efModalActionBtn').disabled=false;
                        const opt = this.selectedOptions[0];
                        document.getElementById('modal_field_rent').value = opt.dataset.rent || 1000;
                        if(opt.dataset.start) document.getElementById('modal_field_start').value = new Date(opt.dataset.start).toISOString().split('T')[0];
                        if(opt.dataset.end) document.getElementById('modal_field_end').value = new Date(opt.dataset.end).toISOString().split('T')[0];
                        if(opt.dataset.due){ document.getElementById('modal_field_due_day').value=opt.dataset.due; document.getElementById('modal_field_due_day_display').value=opt.dataset.due; }
                    }
                }
            });
        };
        const mDueDisp=document.getElementById('modal_field_due_day_display'), mDuePop=document.getElementById('ef-modal-due-popup'), mDueInp=document.getElementById('modal_field_due_day');
        mDueDisp.onclick=()=>mDuePop.style.display='flex';
        mDuePop.onclick=e=>{
          if(e.target.dataset.day){mDueInp.value=e.target.dataset.day; mDueDisp.value=e.target.dataset.day; mDuePop.style.display='none';}
          else if(e.target === mDuePop) { mDuePop.style.display = 'none'; }
        };
        window.efSubmitModalFormEdits = function(){
            const b = document.getElementById('efModalActionBtn'); b.disabled=true;
            const fd = new FormData(); fd.append('action','eftm_edit_tenant'); fd.append('security','<?php echo wp_create_nonce("edit_tenant_nonce"); ?>');
            fd.append('tenant_id', document.getElementById('modal_field_id').value); fd.append('new_name', document.getElementById('modal_field_name').value);
            fd.append('new_property_id', document.getElementById('modal_field_property').value); fd.append('new_unit', document.getElementById('modal_field_unit').value);
            fd.append('new_rent', document.getElementById('modal_field_rent').value); fd.append('new_address', document.getElementById('modal_field_address').value);
            fd.append('new_email', document.getElementById('modal_field_email').value); fd.append('new_phone', document.getElementById('modal_field_phone').value);
            fd.append('new_start', document.getElementById('modal_field_start').value); fd.append('new_end', document.getElementById('modal_field_end').value);
            fd.append('new_due_day', document.getElementById('modal_field_due_day').value);
            fetch(ajax, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{ if(res.success){ alert('Saved!'); document.body.classList.remove('ef-no-scroll'); location.reload(); } });
        }
        window.efLiveDeleteTenant = function(id, name) {
            if (confirm(`Delete ${name}?`)) {
                const fd = new FormData(); fd.append('action', 'eftm_delete_tenant'); fd.append('tenant_id', id); fd.append('security', '<?php echo wp_create_nonce("delete_tenant_nonce"); ?>');
                fetch(ajax, { method: 'POST', body: fd }).then(r => r.json()).then(res => { if (res.success) location.reload(); });
            }
        }
    })();
    </script>
    <?php return ob_get_clean();
});
add_shortcode('tenant_manager', function(){
    return '<div style="font-family:system-ui, sans-serif;">' .
           do_shortcode('[tenant_header]') .
           '<div style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start;">' .
           do_shortcode('[tenant_list]') .
           do_shortcode('[tenant_profile]') .
           '</div></div>';
});



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