<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
/** @var mysqli $con */

if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
    exit;
}


// Fetch all distinct category sections from database
$db_tab_secs_res = mysqli_query($con, "SELECT DISTINCT category_section FROM vendors WHERE deleted_at IS NULL AND category_section != '' ORDER BY category_section ASC");
$db_tab_secs = [];
if ($db_tab_secs_res) {
    while ($r = mysqli_fetch_assoc($db_tab_secs_res)) {
        $db_tab_secs[] = $r['category_section'];
    }
}
$first_sec = !empty($db_tab_secs) ? $db_tab_secs[0] : '';
?>
<style>
    .v-tab-btn {
        background: transparent;
        border: none;
        padding: 9px 16px;
        font-size: 13px;
        font-weight: 700;
        color: #64748b;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        position: relative;
    }

    .v-tab-btn:hover {
        color: #1e293b;
        background: #f8fafc;
    }

    .v-tab-btn.active {
        color: #dd2127;
        background: #fff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    }

    .v-tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 12px;
        right: 12px;
        height: 2.5px;
        background: #dd2127;
        border-radius: 2px;
    }

    .v-group-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #f1f5f9;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        overflow: hidden;
    }

    .v-group-hdr {
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
        background: #fff;
        transition: background 0.15s ease;
    }

    .v-group-hdr:hover {
        background: #fcfdfe;
    }

    .v-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .v-table {
        width: 100%;
        min-width: 1220px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .v-table th {
        background: #fcfdfe;
        border-bottom: 1.5px solid #f1f5f9;
        font-size: 11.5px;
        font-weight: 800;
        color: #64748b;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 14px 14px;
        text-align: left;
        white-space: nowrap;
    }

    .v-table td {
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
        color: #334155;
        padding: 14px 14px;
        vertical-align: middle;
        word-wrap: break-word;
        word-break: break-word;
    }

    .v-table tr:hover td {
        background: #fcfdfe;
    }

    .v-page-btn {
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 12.5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .v-page-btn:hover:not(.disabled) {
        background: #f8fafc;
        color: #dd2127;
        border-color: #dd2127;
    }

    .v-page-btn.active {
        background: #dd2127;
        color: #fff;
        border-color: #dd2127;
        font-weight: 700;
        box-shadow: 0 2px 6px rgba(221, 33, 39, 0.25);
    }

    .v-page-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f8fafc;
    }
</style>
<div class="page-wrapper premium-ui-enabled">
    <!-- Top Action Card Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; background: #fff; border-radius: 14px; padding: 14px 20px; border: 1px solid #f1f5f9; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
        <div>
            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">Vendor Management</h2>
        </div>

        <!-- Top Right Action Controls -->
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <div style="position: relative; width: 230px;">
                <i class="fa fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px; z-index: 1;"></i>
                <input type="text" id="vendorSearchInput" onkeyup="handleVendorSearch()" placeholder="Search vendor..." style="width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 9px 14px 9px 38px; font-size: 13px; outline: none; background: #f8fafc; color: #1e293b; transition: all 0.2s ease;" onfocus="this.style.background='#fff'; this.style.borderColor='#dd2127';" onblur="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
            </div>
            <a href="index.php?add_vendor" class="btn-premium-add">
                <i class="fa fa-plus"></i> Add New Vendor
            </a>
        </div>
    </div>

    <?php if (!empty($db_tab_secs)): ?>
        <!-- Section Category Tabs Bar -->
        <div style="background: #fff; border-radius: 14px; border: 1px solid #f1f5f9; padding: 6px 10px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); overflow-x: auto;">
            <div id="vendor-category-tabs" style="display: flex; gap: 6px; min-width: max-content;">
                <?php
                $is_first_tab = true;
                foreach ($db_tab_secs as $t_sec) {
                    $act_cls = $is_first_tab ? 'active' : '';
                    echo "<button class='v-tab-btn $act_cls' data-sec='" . htmlspecialchars($t_sec) . "' onclick=\"switchVendorSection('" . htmlspecialchars(addslashes($t_sec)) . "', this)\">";
                    echo "<i class='fa fa-tags' style='color: #dd2127;'></i> " . htmlspecialchars($t_sec);
                    echo "</button>";
                    $is_first_tab = false;
                }
                ?>
            </div>
        </div>

        <!-- Main Vendors Accordion Container -->
        <div id="vendor-accordions-container">
            <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                <i class="fa fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 10px; font-size: 14px;">Loading vendors...</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Empty State when no vendor categories exist -->
        <div style="background: #fff; border-radius: 16px; border: 1px solid #f1f5f9; padding: 60px 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.02); margin-top: 20px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #ffeaeb; color: #dd2127; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 18px;">
                <i class="fa fa-truck"></i>
            </div>
            <h3 style="margin: 0 0 8px; font-size: 20px; font-weight: 800; color: #0f172a;">No Vendors or Category Sections Yet</h3>
            <p style="margin: 0 0 24px; font-size: 14px; color: #64748b;">Add your first vendor to create a category section and organize your vendor directory.</p>
            <a href="index.php?add_vendor" class="btn-premium-add">
                <i class="fa fa-plus"></i> Add New Vendor
            </a>
        </div>
    <?php endif; ?>
</div>



<script>
    let currentVendorSection = '<?php echo !empty($first_sec) ? htmlspecialchars(addslashes($first_sec)) : ''; ?>';
    let vendorPaginationStore = {};

    $(document).ready(function() {
        if (currentVendorSection) {
            loadVendorsForSection(currentVendorSection);
        }
    });

    function switchVendorSection(sec, btn) {
        currentVendorSection = sec;
        $('.v-tab-btn').removeClass('active');
        $(btn).addClass('active');
        loadVendorsForSection(sec);
    }

    function handleVendorSearch() {
        if (currentVendorSection) {
            loadVendorsForSection(currentVendorSection);
        }
    }

    function loadVendorsForSection(sec) {
        const search = $('#vendorSearchInput').val().trim();

        $('#vendor-accordions-container').html('<div style="text-align: center; padding: 40px 20px; color: #94a3b8;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top: 10px; font-size: 14px;">Loading vendors...</p></div>');

        $.ajax({
            url: 'ajax/vendors/ajax_vendors.php',
            type: 'GET',
            data: {
                action: 'get_vendors',
                section: sec,
                search: search
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    renderVendorAccordions(res.grouped, sec);
                } else {
                    $('#vendor-accordions-container').html('<div style="text-align: center; padding: 30px; color: #ef4444;">' + escapeHtml(res.message) + '</div>');
                }
            },
            error: function() {
                $('#vendor-accordions-container').html('<div style="text-align: center; padding: 30px; color: #ef4444;">Failed to connect to server</div>');
            }
        });
    }

    function renderVendorAccordions(groupedData, sec) {
        vendorPaginationStore = {};
        const allSubs = Object.keys(groupedData);
        let html = '';

        if (allSubs.length === 0) {
            html = `
                <div style="background: #fff; border-radius: 14px; border: 1px solid #f1f5f9; padding: 40px 20px; text-align: center;">
                    <p style="color: #64748b; font-size: 14px; margin: 0 0 14px;">No vendors found matching criteria under ${escapeHtml(sec)}.</p>
                    <a href="index.php?add_vendor" class="btn-premium-add">
                        + Add Vendor to ${escapeHtml(sec)}
                    </a>
                </div>
            `;
            $('#vendor-accordions-container').html(html);
            return;
        }

        allSubs.forEach(function(subName, idx) {
            const list = groupedData[subName] || [];
            const count = list.length;
            const isFirst = (idx === 0);
            const accordionId = 'v-acc-' + idx;

            vendorPaginationStore[accordionId] = {
                list: list,
                currentPage: 1,
                pageSize: 5
            };

            html += `
                <div class="v-group-card">
                    <div class="v-group-hdr" onclick="toggleVendorAccordion('${accordionId}')">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 26px; height: 26px; border-radius: 50%; background: #ffeaeb; color: #dd2127; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0;">
                                <i class="fa fa-chain-broken"></i>
                            </div>
                            <span style="font-weight: 800; font-size: 14px; color: #1e293b;">${escapeHtml(subName)}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 12px; font-weight: 700; color: #94a3b8; background: #f8fafc; padding: 4px 12px; border-radius: 20px; border: 1px solid #e2e8f0;">
                                ${count} Vendors
                            </span>
                            <i class="fa ${isFirst ? 'fa-chevron-up' : 'fa-chevron-down'}" id="${accordionId}-icon" style="color: #94a3b8; font-size: 12px;"></i>
                        </div>
                    </div>
                    <div id="${accordionId}" style="display: ${isFirst ? 'block' : 'none'}; border-top: 1px solid #f1f5f9;">
            `;

            if (count === 0) {
                html += `
                    <div style="text-align: center; padding: 30px; color: #94a3b8; font-size: 13px;">
                        No vendors added under ${escapeHtml(subName)}.
                        <br>
                        <a href="index.php?add_vendor" class="btn-premium-add" style="margin-top: 10px; display: inline-block;">
                            + Add Vendor
                        </a>
                    </div>
                `;
            } else {
                html += `
                    <div class="v-table-wrap">
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th style="width: 110px; text-align: center;">Vendor ID</th>
                                    <th style="width: 160px; text-align: center;">Company Name</th>
                                    <th style="width: 140px; text-align: center;">Contact Person</th>
                                    <th style="width: 130px; text-align: center;">Phone</th>
                                    <th style="width: 180px; text-align: center;">Email</th>
                                    <th style="width: 110px; text-align: center;">City</th>
                                    <th style="width: 160px; text-align: center;">Address</th>
                                    <th style="width: 90px; text-align: center;">Projects</th>
                                    <th style="width: 140px; text-align: center;">Notes</th>
                                    <th style="width: 100px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="${accordionId}-tbody">
                                <!-- Populated dynamically by renderAccordionPage -->
                            </tbody>
                        </table>
                    </div>
                    <div id="${accordionId}-pagination" style="padding: 14px 20px; background: #fff; font-size: 13px; color: #64748b; font-weight: 600; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9;">
                        <!-- Populated dynamically by renderAccordionPage -->
                    </div>
                `;
            }

            html += `
                    </div>
                </div>
            `;
        });

        $('#vendor-accordions-container').html(html);

        Object.keys(vendorPaginationStore).forEach(function(accId) {
            if (vendorPaginationStore[accId].list.length > 0) {
                renderAccordionPage(accId, 1);
            }
        });
    }

    function renderAccordionPage(accId, page) {
        const store = vendorPaginationStore[accId];
        if (!store) return;

        const list = store.list;
        const total = list.length;
        const pageSize = store.pageSize;
        const totalPages = Math.ceil(total / pageSize) || 1;

        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        store.currentPage = page;

        const startIdx = (page - 1) * pageSize;
        const endIdx = Math.min(startIdx + pageSize, total);
        const slice = list.slice(startIdx, endIdx);

        let tbodyHtml = '';
        slice.forEach(function(v) {
            tbodyHtml += `
                <tr>
                    <td style="font-weight: 700; color: #64748b; text-align: center;">${escapeHtml(v.vendor_custom_id || ('VND-' + String(v.id).padStart(3, '0')))}</td>
                    <td style="font-weight: 700; color: #1e293b; text-align: center;">${escapeHtml(v.company_name)}</td>
                    <td style="text-align: center;">${escapeHtml(v.contact_person || '-')}</td>
                    <td style="font-weight: 600; word-break: break-all; text-align: center;">${escapeHtml(v.phone || '-')}</td>
                    <td style="word-break: break-all; text-align: center;">${escapeHtml(v.email || '-')}</td>
                    <td style="text-align: center;">${escapeHtml(v.city || '-')}</td>
                    <td style="font-size: 12px; color: #64748b; line-height: 1.4; text-align: center;">${escapeHtml(v.address || '-')}</td>
                    <td style="text-align: center; font-weight: 700;">${v.projects_count}</td>
                    <td style="font-size: 12.5px; color: #64748b; text-align: center;">${escapeHtml(v.notes || '-')}</td>
                    <td style="text-align: center;">
                        <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                            <a href="index.php?edit_vendor=${v.id}" class="btn-icon-premium btn-icon-sm btn-icon-edit" title="Edit Vendor">
                                <i class="fa fa-pencil"></i>
                            </a>
                            <button type="button" class="btn-icon-premium btn-icon-sm btn-icon-delete" onclick="deleteVendor(${v.id})" title="Delete Vendor">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        $('#' + accId + '-tbody').html(tbodyHtml);

        const showingStart = total > 0 ? (startIdx + 1) : 0;
        let pagHtml = `<span>Showing ${showingStart} to ${endIdx} of ${total} entries</span><div style="display: flex; gap: 6px; align-items: center;">`;

        const prevDisabled = (page <= 1) ? 'disabled' : '';
        pagHtml += `<button type="button" class="v-page-btn ${prevDisabled}" ${prevDisabled ? 'disabled' : ''} onclick="renderAccordionPage('${accId}', ${page - 1})">&lt;</button>`;

        for (let p = 1; p <= totalPages; p++) {
            const actClass = (p === page) ? 'active' : '';
            pagHtml += `<button type="button" class="v-page-btn ${actClass}" onclick="renderAccordionPage('${accId}', ${p})">${p}</button>`;
        }

        const nextDisabled = (page >= totalPages) ? 'disabled' : '';
        pagHtml += `<button type="button" class="v-page-btn ${nextDisabled}" ${nextDisabled ? 'disabled' : ''} onclick="renderAccordionPage('${accId}', ${page + 1})">&gt;</button>`;

        pagHtml += `</div>`;

        $('#' + accId + '-pagination').html(pagHtml);
    }

    function toggleVendorAccordion(id) {
        $('#' + id).slideToggle(180, function() {
            const isVisible = $(this).is(':visible');
            $('#' + id + '-icon').toggleClass('fa-chevron-up', isVisible).toggleClass('fa-chevron-down', !isVisible);
        });
    }

    function deleteVendor(id) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you really want to delete this vendor?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    performDelete(id);
                }
            });
        } else {
            if (confirm('Are you sure you want to delete this vendor?')) {
                performDelete(id);
            }
        }
    }

    function performDelete(id) {
        $.ajax({
            url: 'ajax/vendors/ajax_vendors.php',
            type: 'POST',
            data: {
                action: 'delete_vendor',
                id: id
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Deleted!',
                            text: 'Vendor deleted successfully.',
                            icon: 'success',
                            confirmButtonColor: '#dd2127'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', res.message || 'Error deleting vendor', 'error');
                    } else {
                        alert(res.message || 'Error deleting vendor');
                    }
                }
            },
            error: function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Server error deleting vendor', 'error');
                } else {
                    alert('Server error deleting vendor');
                }
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
</script>