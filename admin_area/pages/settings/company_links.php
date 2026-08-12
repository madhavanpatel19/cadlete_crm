<?php
if (!isset($con)) {
    if (!isset($con)) {
        include(__DIR__ . '/../../includes/db.php');
    }
}

// Fetch all employees for assignment
$empList = [];
$empQ = mysqli_query($con, "SELECT id, name, employee_image FROM emp_list WHERE deleted_at IS NULL ORDER BY name ASC");
while ($erow = mysqli_fetch_assoc($empQ)) {
    $empList[] = $erow;
}
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .select2-container {
        width: 100% !important;
    }

    .select2-container--default .select2-selection--multiple {
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        min-height: 48px !important;
        background-color: #fff !important;
        display: flex;
        align-items: center;
        padding: 0 8px;
        transition: all 0.3s ease;
    }

    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #ffeaeb !important;
        border: 1px solid #ffc9cb !important;
        border-radius: 6px !important;
        color: #dd2127 !important;
        padding: 4px 8px 4px 24px !important;
        margin-top: 6px !important;
        position: relative !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #dd2127 !important;
        border-right: 1px solid rgba(223, 33, 39, 0.2) !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 6px !important;
        margin: 0 !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        background-color: rgba(223, 33, 39, 0.1) !important;
        color: #b91c1c !important;
    }

    .select2-search--inline .select2-search__field {
        margin-top: 8px !important;
        font-family: inherit !important;
        color: #334155 !important;
    }
</style>

<div class="page-wrapper premium-ui-enabled" style="background: #fafbfc; min-height: 100vh; padding: 30px 40px;">

    <!-- Level 1: Section Browser -->
    <div id="section-browser-view">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 20px;">
            <div style="position: relative; width: 350px;">
                <i class="fa fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" id="section-search-input" placeholder="Search sections..." style="width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            </div>
            <?php if (canAdminAccess('company_link_insert')): ?>
                <button type="button" id="btn-add-section" class="btn-premium-add">
                    <i class="fa fa-plus"></i> Add Section
                </button>
            <?php endif; ?>
        </div>



        <!-- Inline Section Add Form -->
        <div id="inline-section-form" style="display: none; background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 25px;">
            <form id="add-section-form-direct" style="display: flex; gap: 15px; align-items: flex-start;">
                <div style="flex: 1;">
                    <label style="font-weight: 700; color: #475569; font-size: 12px; margin-bottom: 8px; display: block;">Section Name</label>
                    <input type="text" id="new-section-name" class="p-input-premium" placeholder="e.g. Mechanical Engineering" required style="width: 100%; height: 48px; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; font-weight: 500; font-size: 14px; box-sizing: border-box;">
                </div>
                <div style="flex: 2;">
                    <label style="font-weight: 700; color: #475569; font-size: 12px; margin-bottom: 8px; display: block;">Assign Access to Employees</label>
                    <select id="new-section-employees" class="form-control" multiple style="width: 100%;">
                        <option value="all">Add All Employees</option>
                        <?php foreach ($empList as $e):
                            $emp_img = !empty($e['employee_image']) ? 'uploads/' . $e['employee_image'] : 'admin_images/default.png';
                        ?>
                            <option value="<?php echo $e['id']; ?>" data-image="<?php echo $emp_img; ?>">
                                <?php echo htmlspecialchars($e['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-top: 23px;">
                    <button type="submit" class="btn-premium-add">
                        Create Section
                    </button>
                </div>
            </form>
        </div>

        <!-- Sections Grid -->
        <div id="sections-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <!-- Sections will be loaded here as folders -->
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px 0;">
                <div class="premium-spinner"></div>
            </div>
        </div>

        <!-- Recently Updated Table -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 30px;">
            <div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: var(--p-bg-header);">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;">Pinned Links</h3>
            </div>
            <div class="table-responsive">
                <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th style="text-align: center;">Type</th>
                            <th style="text-align: center;">Section</th>
                            <th style="text-align: center;">Changed By</th>
                            <th style="text-align: center;">Date</th>
                            <th style="text-align: center;">Manage</th>
                        </tr>
                    </thead>
                    <tbody id="recently-updated-table">
                        <!-- populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Level 2: Resource Hub -->
    <div id="resource-hub-view" style="display: none; width: 100%;">
        <div style="margin-bottom: 20px;">
            <button type="button" id="btn-back-to-sections" style="background: transparent; border: none; color: #64748b; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s;">
                <i class="fa fa-arrow-left"></i> Back to Repository
            </button>
        </div>

        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="width: 60px; height: 60px; background: #ffeaeb; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #dd2127;">
                    <i class="fa fa-folder"></i>
                </div>
                <div>
                    <h2 id="hub-section-title" style="margin: 0; color: #0f172a; font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                        Section
                        <?php if (canAdminAccess('company_link_update')): ?>
                            <i id="btn-rename-section" class="fa fa-pencil" style="font-size: 14px; color: #dd2127; cursor: pointer;" title="Rename Section"></i>
                        <?php endif; ?>
                    </h2>
                    <p id="hub-section-stats" style="margin: 4px 0 0 0; color: #dd2127; font-size: 14px; font-weight: 500;">
                        0 Files &bull; 0 Links
                    </p>
                </div>
            </div>


            <div style="display: flex; gap: 10px; align-items: center;">

                <div style="position: relative;">
                    <i class="fa fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                    <input type="text" id="hub-search-input" placeholder="Search files and links..." style="width: 300px; padding: 12px 15px 12px 45px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; background: #fff;">
                </div>

                <div style="position: relative;">
                    <?php if (canAdminAccess('company_link_insert')): ?>
                        <button type="button" id="btn-add-resource-dropdown" class="btn-premium-add">
                            <i class="fa fa-plus"></i> Add Resource <i class="fa fa-caret-down"></i>
                        </button>
                    <?php endif; ?>
                    <div id="add-resource-menu" style="display: none; position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-top: 5px; z-index: 100; min-width: 180px; overflow: hidden;">
                        <div class="add-menu-item" data-type="link" style="padding: 12px 20px; cursor: pointer; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: 500; color: #475569; transition: 0.2s;"><i class="fa fa-link" style="margin-right: 8px; color: #3b82f6;"></i> Add Link</div>
                        <div class="add-menu-item" data-type="document" style="padding: 12px 20px; cursor: pointer; font-size: 14px; font-weight: 500; color: #475569; transition: 0.2s;"><i class="fa fa-file-text-o" style="margin-right: 8px; color: #10b981;"></i> Add Document</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Forms -->
        <div id="inline-add-link-form" class="resource-add-form" style="display: none; background: #f8fafc; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 25px;">
            <form id="add-link-form" onsubmit="submitResourceForm(event, this)">
                <input type="hidden" name="category" class="form-category">
                <input type="hidden" name="resource_type" value="link">
                <h4 style="margin: 0 0 15px 0; color: #0f172a; font-size: 16px;">Add New Link</h4>
                <div class="row">
                    <div class="col-md-5">
                        <label style="font-weight: 600; color: #475569; font-size: 12px; margin-bottom: 8px; display: block;">Link Name</label>
                        <input type="text" name="link_name" class="p-input-premium" placeholder="e.g. Figma Design" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; font-size: 14px;">
                    </div>
                    <div class="col-md-7">
                        <label style="font-weight: 600; color: #475569; font-size: 12px; margin-bottom: 8px; display: block;">URL</label>
                        <input type="url" name="link_url" class="p-input-premium" placeholder="https://..." required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; font-size: 14px;">
                    </div>
                </div>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" class="btn-premium-cancel">Cancel</button>
                    <button type="submit" class="btn-premium-add">Save Link</button>
                </div>
            </form>
        </div>

        <div id="inline-add-document-form" class="resource-add-form" style="display: none; background: #f8fafc; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 25px;">
            <form id="add-document-form" enctype="multipart/form-data" onsubmit="submitResourceForm(event, this)">
                <input type="hidden" name="category" class="form-category">
                <input type="hidden" name="resource_type" value="document">
                <h4 style="margin: 0 0 15px 0; color: #0f172a; font-size: 16px;">Upload Document</h4>
                <div class="row">
                    <div class="col-md-5">
                        <label style="font-weight: 600; color: #475569; font-size: 12px; margin-bottom: 8px; display: block;">Document Name</label>
                        <input type="text" name="link_name" class="p-input-premium" placeholder="e.g. Technical Spec" required style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; font-size: 14px;">
                    </div>
                    <div class="col-md-7">
                        <label style="font-weight: 600; color: #475569; font-size: 12px; margin-bottom: 8px; display: block;">Select File</label>
                        <input type="file" name="document_file" class="p-input-premium" required style="width: 100%; padding: 8px 14px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; font-size: 14px; background: #fff;">
                    </div>
                </div>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" class="btn-premium-cancel">Cancel</button>
                    <button type="submit" class="btn-premium-add">Upload File</button>
                </div>
            </form>
        </div>

        <!-- Search Bar -->
        <!-- Files Table -->
        <div style="margin-bottom: 40px;" id="files-section-container">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 700; color: #0f172a;" id="files-section-title">Files (0)</h3>
            <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff;">
                <div class="table-responsive">
                    <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th style="text-align: center;">Type</th>
                                <th style="text-align: center;">Uploaded By</th>
                                <th style="text-align: center;">Uploaded On</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="hub-files-table">
                            <!-- populated by js -->
                        </tbody>
                    </table>
                </div>
                <div id="view-all-files-container" style="padding: 15px 20px; background: #fff; border-top: 1px solid #e2e8f0; display: none;">
                    <a href="#" id="view-all-files-btn" style="color: #dd2127; font-size: 14px; font-weight: 600; text-decoration: none; outline: none;">View all files</a>
                </div>
            </div>
        </div>

        <!-- Links Table -->
        <div id="links-section-container">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 700; color: #0f172a;" id="links-section-title">Links (0)</h3>
            <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff;">
                <div class="table-responsive">
                    <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th style="text-align: center;">URL</th>
                                <th style="text-align: center;">Added By</th>
                                <th style="text-align: center;">Added On</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="hub-links-table">
                            <!-- populated by js -->
                        </tbody>
                    </table>
                </div>
                <div id="view-all-links-container" style="padding: 15px 20px; background: #fff; border-top: 1px solid #e2e8f0; display: none;">
                    <a href="#" id="view-all-links-btn" style="color: #dd2127; font-size: 14px; font-weight: 600; text-decoration: none; outline: none;">View all links</a>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Employee Assignment Modal -->
<div id="assignEmployeesModal" class="modal fade" tabindex="-1" role="dialog" style="z-index: 99999;">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2);">
            <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background: #ffeaeb; border-radius: 14px 14px 0 0; position: relative;">
                <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                    <div style="width: 36px; height: 36px; background: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-users" style="color: #fff; font-size: 14px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 17px; margin: 0;">Assign Access to <span id="assign-section-name" style="color: #dd2127;"></span></h5>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="modal-body" style="padding: 25px; background: #fff;">
                <p style="margin-top: 0; margin-bottom: 20px; font-size: 14px; color: #64748b;">Select which employees can view this section in their Quick Links area. If no employees are selected, this section will be hidden from everyone.</p>
                <form id="assign-employees-form">
                    <input type="hidden" name="category" id="assign-category-input">
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
                        <?php foreach ($empList as $e):
                            $img = !empty($e['employee_image']) ? 'uploads/' . $e['employee_image'] : 'admin_images/default.png';
                        ?>
                            <label style="display: flex; align-items: center; gap: 15px; padding: 10px; border-bottom: 1px solid #f1f5f9; cursor: pointer; margin: 0;">
                                <input type="checkbox" name="emp_ids[]" value="<?php echo $e['id']; ?>" class="emp-checkbox" style="width: 18px; height: 18px; accent-color: #dd2127;">
                                <img src="<?php echo htmlspecialchars($img); ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                <span style="font-weight: 600; color: #1e293b; font-size: 14px;"><?php echo htmlspecialchars($e['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="button" data-dismiss="modal" class="btn-premium-cancel">Cancel</button>
                        <button type="submit" class="btn-premium-add" id="btn-save-assignments">Save Assignments</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .premium-ui-enabled {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    }

    .folder-card {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .folder-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }

    .hub-resource-card {
        background: #fff;
        padding: 15px 20px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: 0.2s;
    }

    .hub-resource-card:hover {
        background: #f8fafc;
    }

    .resource-icon-mini {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .btn-hub-action {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        font-size: 14px;
    }

    .btn-hub-action:hover {
        background: #f1f5f9;
        color: #0f172a;
    }

    .premium-spinner {
        width: 40px;
        height: 40px;
        border: 3px solid rgba(0, 0, 0, 0.05);
        border-top: 3px solid #3b82f6;
        border-radius: 50%;
        margin: 0 auto;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .table-row-hover:hover {
        background: #f8fafc;
    }
</style>

<script>
    $(document).ready(function() {
        let allResources = [];
        // Fake currently logged in admin user for UI purposes
        const currentUserAvatar = `<?php echo isset($admin_image) && !empty($admin_image) ? 'admin_images/' . $admin_image : 'https://ui-avatars.com/api/?name=Admin&background=dd2127&color=fff'; ?>`;
        const currentUserName = `<?php echo isset($header_display_name) ? $header_display_name : 'Admin User'; ?>`;

        function getResourceType(url) {
            const ext = url.split('.').pop().toLowerCase();
            if (['pdf'].includes(ext)) return {
                type: 'PDF',
                icon: 'fa-file-pdf-o',
                color: '#ef4444',
                bg: '#fef2f2'
            };
            if (['xlsx', 'xls', 'csv'].includes(ext)) return {
                type: 'XLSX',
                icon: 'fa-file-excel-o',
                color: '#10b981',
                bg: '#ecfdf5'
            };
            if (['docx', 'doc'].includes(ext)) return {
                type: 'DOCX',
                icon: 'fa-file-word-o',
                color: '#3b82f6',
                bg: '#eff6ff'
            };
            if (['png', 'jpg', 'jpeg', 'gif', 'svg'].includes(ext)) return {
                type: 'Image',
                icon: 'fa-file-image-o',
                color: '#8b5cf6',
                bg: '#f5f3ff'
            };
            if (['zip', 'rar'].includes(ext)) return {
                type: 'ZIP',
                icon: 'fa-file-archive-o',
                color: '#f59e0b',
                bg: '#fffbeb'
            };

            return {
                type: 'Link',
                icon: 'fa-link',
                color: '#3b82f6',
                bg: '#eff6ff'
            };
        }

        function formatTimeAgo(dateString) {
            if (!dateString) return 'Unknown';
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return diffDays + ' days ago';
            if (diffDays < 14) return '1 week ago';
            if (diffDays < 30) return Math.floor(diffDays / 7) + ' weeks ago';
            return Math.floor(diffDays / 30) + ' months ago';
        }

        function formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function loadData(callback = null) {
            $.ajax({
                url: 'ajax/misc/ajax_company_links.php?action=fetch',
                method: 'GET',
                success: function(response) {
                    allResources = JSON.parse(response);
                    renderSections();
                    renderRecentlyUpdated();
                    if (callback) callback();
                }
            });
        }

        function renderSections() {
            const categories = [...new Set(allResources.map(item => item.category))];
            let html = '';

            categories.forEach(cat => {
                const items = allResources.filter(i => i.category === cat);
                let filesCount = 0;
                let linksCount = 0;
                let latestDate = null;

                items.forEach(item => {
                    const typeInfo = getResourceType(item.link_url);
                    if (typeInfo.type === 'Link') linksCount++;
                    else filesCount++;

                    if (item.created_at) {
                        const date = new Date(item.created_at);
                        if (!latestDate || date > latestDate) latestDate = date;
                    }
                });

                const updatedAgoText = latestDate ? formatTimeAgo(latestDate) : 'Unknown';

                html += `
                <div class="folder-card" data-category="${cat}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div style="width: 50px; height: 50px; background: #FFEAEB; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #dd2127; font-size: 24px;">
                                <i class="fa fa-folder"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; color: #0f172a; font-size: 16px; font-weight: 700;">${cat}</h3>
                                <p style="margin: 4px 0 0 0; color: #64748b; font-size: 12px;">
                                    <i class="fa fa-file-o" style="margin-right: 4px;"></i> ${filesCount} Files &nbsp;&nbsp;
                                    <i class="fa fa-link" style="margin-right: 4px;"></i> ${linksCount} Links
                                </p>
                            </div>
                        </div>
                        <div style="position: relative;" class="folder-menu-container">
                            <button class="btn-folder-menu" style="background: none; border: none; color: #cbd5e1; cursor: pointer; font-size: 16px;">
                                <i class="fa fa-ellipsis-h"></i>
                            </button>
                            <div class="folder-dropdown" style="display: none; position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-top: 5px; z-index: 100; min-width: 180px; overflow: hidden;">
                                <div class="btn-assign-folder" data-category="${cat}" style="padding: 12px 20px; cursor: pointer; font-size: 14px; font-weight: 500; color: #475569; transition: 0.2s;"><i class="fa fa-users" style="margin-right: 8px; color: #dd2127;"></i> Assign Access</div>
                            </div>
                        </div>
                    </div>
                    <div style="border-top: 1px solid #f1f5f9; padding-top: 15px; display: flex; align-items: center; color: #64748b; font-size: 12px;">
                        <i class="fa fa-calendar-o" style="margin-right: 6px;"></i> Updated ${updatedAgoText}
                    </div>
                </div>
                `;
            });

            if (categories.length === 0) {
                html = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px 0; background: #fff; border-radius: 12px; border: 1px dashed #e2e8f0;">
                    <div style="width: 60px; height: 60px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                        <i class="fa fa-folder-open-o" style="font-size: 24px; color: #94a3b8;"></i>
                    </div>
                    <h3 style="color: #64748b; font-weight: 600; font-size: 16px;">No sections found.</h3>
                    <p style="color: #94a3b8; font-size: 14px;">Create your first section using the button above.</p>
                </div>
                `;
            }

            $('#sections-grid').html(html);
        }

        function renderRecentlyUpdated() {
            // Sort by created_at DESC
            const pinned = [...allResources].filter(item => item.is_pinned == 1 || item.is_pinned == '1').sort((a, b) => {
                const dateA = a.created_at ? new Date(a.created_at) : new Date(0);
                const dateB = b.created_at ? new Date(b.created_at) : new Date(0);
                return dateB - dateA;
            });

            let html = '';
            pinned.forEach(item => {
                const typeInfo = getResourceType(item.link_url);
                const updatedOn = formatDateTime(item.created_at);

                let url = item.link_url;
                if (!url.startsWith('http://') && !url.startsWith('https://')) url = 'http://' + url;

                html += `
                <tr class="table-row-hover">
                    <td style="padding: 15px 20px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: flex-start; gap: 12px;">
                            <div style="width: 32px; height: 32px; border-radius: 6px; background: #dd2127; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                <i class="fa ${typeInfo.icon}"></i>
                            </div>
                            <span style="color: #0f172a; font-weight: 500; font-size: 14px;">${item.link_name}</span>
                        </div>
                    </td>
                    <td style="padding: 15px 20px; text-align: center; vertical-align: middle; color: #475569; font-size: 14px;">${typeInfo.type}</td>
                    <td style="padding: 15px 20px; text-align: center; vertical-align: middle;">
                        <span style="color: #dd2127; cursor: pointer; font-size: 14px;" onclick="openResourceHub('${item.category}')">${item.category}</span>
                    </td>
                    <td style="padding: 15px 20px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <img src="${item.uploader_photo ? 'uploads/' + item.uploader_photo : currentUserAvatar}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                            <span style="color: #475569; font-size: 14px;">${item.uploader_name || 'Admin'}</span>
                        </div>
                    </td>
                    <td style="padding: 15px 20px; text-align: center; vertical-align: middle; color: #475569; font-size: 14px;">${updatedOn}</td>
                    <td style="padding: 15px 20px; text-align: center; vertical-align: middle;">
                        <div style="display: flex; justify-content: center; gap: 8px;">
                            <button class="btn-icon-premium btn-pin-resource" data-id="${item.id}" data-pinned="${item.is_pinned}" style="background: #fefce8; border-color: #fef9c3; color: #eab308;" title="Unpin">
                                <i class="fa fa-thumb-tack"></i>
                            </button>
                            <a href="${url}" target="_blank" class="btn-icon-premium" style="background: #f0f9ff; border-color: #e0f2fe; color: #0284c7;" title="${typeInfo.type === 'Link' ? 'Visit' : 'Download'}">
                                <i class="fa ${typeInfo.type === 'Link' ? 'fa-external-link' : 'fa-download'}"></i>
                            </a>
                            <?php if (canAdminAccess('company_link_delete')): ?>
                            <button class="btn-icon-premium btn-delete-resource" data-id="${item.id}" style="background: #fef2f2; border-color: #fee2e2; color: #ef4444;" title="Delete">
                                <i class="fa fa-trash-o"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                `;
            });

            if (pinned.length === 0) {
                html = `<tr><td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">No pinned links to show.</td></tr>`;
            }

            $('#recently-updated-table').html(html);
        }

        function openResourceHub(category) {
            category = String(category);
            $('.form-category').val(category);

            const filtered = allResources.filter(item => String(item.category) === category);

            // Search filter
            const searchTerm = $('#hub-search-input').val() ? String($('#hub-search-input').val()).toLowerCase() : '';
            const searched = filtered.filter(item => String(item.link_name).toLowerCase().includes(searchTerm) || String(item.link_url).toLowerCase().includes(searchTerm));

            const files = [];
            const links = [];

            searched.forEach(item => {
                const typeInfo = getResourceType(item.link_url);
                if (typeInfo.type === 'Link') {
                    links.push({
                        item,
                        typeInfo
                    });
                } else {
                    files.push({
                        item,
                        typeInfo
                    });
                }
            });

            const renameIcon = <?php echo canAdminAccess('company_link_update') ? '`<i id="btn-rename-section" class="fa fa-pencil" style="font-size: 14px; color: #94a3b8; cursor: pointer;" title="Rename Section"></i>`' : '""'; ?>;
            $('#hub-section-title').html(`${category} Hub ${renameIcon}`);
            $('#hub-section-stats').html(`${files.length} Files &bull; ${links.length} Links`);
            $('#files-section-title').text(`Files (${files.length})`);
            $('#links-section-title').text(`Links (${links.length})`);

            let filesHtml = '';
            files.forEach(({
                item,
                typeInfo
            }, index) => {
                const dateOn = formatDateTime(item.created_at);
                let url = item.link_url;
                if (!url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('uploads/')) url = 'http://' + url;

                const displayStyle = index >= 5 ? 'display: none;' : '';
                const rowClass = index >= 5 ? 'table-row-hover extra-file-row' : 'table-row-hover';

                filesHtml += `
                <tr class="${rowClass}" style="${displayStyle}">
                    <td style="padding: 12px 20px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: flex-start; gap: 12px;">
                            <div class="resource-icon-mini" style="background: #ffeaeb; color: #dd2127; width: 32px; height: 32px; font-size: 16px;">
                                <i class="fa ${typeInfo.icon}"></i>
                            </div>
                            <span style="color: #0f172a; font-weight: 500; font-size: 14px;">${item.link_name}</span>
                        </div>
                    </td>
                    <td style="padding: 12px 20px; text-align: center; vertical-align: middle; color: #475569; font-size: 13px; font-weight: 500;">${typeInfo.type}</td>
                    <td style="padding: 12px 20px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <img src="${item.uploader_photo ? 'uploads/' + item.uploader_photo : currentUserAvatar}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                            <span style="color: #475569; font-size: 13px; font-weight: 500;">${item.uploader_name || 'Admin'}</span>
                        </div>
                    </td>
                    <td style="padding: 12px 20px; text-align: center; vertical-align: middle; color: #475569; font-size: 13px;">${dateOn}</td>
                    <td style="padding: 12px 20px; text-align: center; vertical-align: middle;">
                        <div style="display: flex; justify-content: center; gap: 8px;">
                            <button class="btn-icon-premium btn-pin-resource" data-id="${item.id}" data-pinned="${item.is_pinned}" style="background: ${item.is_pinned == 1 || item.is_pinned == '1' ? '#fefce8' : '#f1f5f9'}; border-color: ${item.is_pinned == 1 || item.is_pinned == '1' ? '#fef9c3' : '#e2e8f0'}; color: ${item.is_pinned == 1 || item.is_pinned == '1' ? '#eab308' : '#64748b'};" title="${item.is_pinned == 1 || item.is_pinned == '1' ? 'Unpin' : 'Pin'}">
                                <i class="fa fa-thumb-tack"></i>
                            </button>
                            <a href="${url}" target="_blank" class="btn-icon-premium" style="background: #f0f9ff; border-color: #e0f2fe; color: #0284c7;" title="Download">
                                <i class="fa fa-download"></i>
                            </a>
                            <?php if (canAdminAccess('company_link_delete')): ?>
                            <button class="btn-icon-premium btn-delete-resource" data-id="${item.id}" style="background: #fef2f2; border-color: #fee2e2; color: #ef4444;" title="Delete">
                                <i class="fa fa-trash-o"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                `;
            });

            if (files.length === 0) filesHtml = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">No files found</td></tr>`;
            $('#hub-files-table').html(filesHtml);

            if (files.length > 5) {
                $('#view-all-files-container').show();
                $('#view-all-files-btn').text(`View all ${files.length} files`);
            } else {
                $('#view-all-files-container').hide();
            }

            let linksHtml = '';
            links.forEach(({
                item,
                typeInfo
            }, index) => {
                const dateOn = formatDateTime(item.created_at);
                let url = item.link_url;
                if (!url.startsWith('http://') && !url.startsWith('https://')) url = 'http://' + url;

                const displayStyle = index >= 5 ? 'display: none;' : '';
                const rowClass = index >= 5 ? 'table-row-hover extra-link-row' : 'table-row-hover';

                linksHtml += `
                <tr class="${rowClass}" style="${displayStyle}">
                    <td style="padding: 12px 20px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: flex-start; gap: 12px;">
                            <div class="resource-icon-mini" style="background: #ffeaeb; color: #dd2127; width: 32px; height: 32px; font-size: 16px;">
                                <i class="fa ${typeInfo.icon}"></i>
                            </div>
                            <span style="color: #0f172a; font-weight: 500; font-size: 14px;">${item.link_name}</span>
                        </div>
                    </td>
                    <td style="padding: 12px 20px; text-align: center; vertical-align: middle;">
                        <a href="${url}" target="_blank" style="color: #dd2127; font-size: 13px; text-decoration: none; word-break: break-all;">${url}</a>
                    </td>
                    <td style="padding: 12px 20px; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <img src="${item.uploader_photo ? 'uploads/' + item.uploader_photo : currentUserAvatar}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                            <span style="color: #475569; font-size: 13px; font-weight: 500;">${item.uploader_name || 'Admin'}</span>
                        </div>
                    </td>
                    <td style="padding: 12px 20px; text-align: center; vertical-align: middle; color: #475569; font-size: 13px;">${dateOn}</td>
                    <td style="padding: 12px 20px; text-align: center; vertical-align: middle;">
                        <div style="display: flex; justify-content: center; gap: 8px;">
                            <button class="btn-icon-premium btn-pin-resource" data-id="${item.id}" data-pinned="${item.is_pinned}" style="background: ${item.is_pinned == 1 || item.is_pinned == '1' ? '#fefce8' : '#f1f5f9'}; border-color: ${item.is_pinned == 1 || item.is_pinned == '1' ? '#fef9c3' : '#e2e8f0'}; color: ${item.is_pinned == 1 || item.is_pinned == '1' ? '#eab308' : '#64748b'};" title="${item.is_pinned == 1 || item.is_pinned == '1' ? 'Unpin' : 'Pin'}">
                                <i class="fa fa-thumb-tack"></i>
                            </button>
                            <a href="${url}" target="_blank" class="btn-icon-premium" style="background: #f0f9ff; border-color: #e0f2fe; color: #0284c7;" title="Visit">
                                <i class="fa fa-external-link"></i>
                            </a>
                            <?php if (canAdminAccess('company_link_delete')): ?>
                            <button class="btn-icon-premium btn-delete-resource" data-id="${item.id}" style="background: #fef2f2; border-color: #fee2e2; color: #ef4444;" title="Delete">
                                <i class="fa fa-trash-o"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                `;
            });

            if (links.length === 0) linksHtml = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">No links found</td></tr>`;
            $('#hub-links-table').html(linksHtml);

            if (links.length > 5) {
                $('#view-all-links-container').show();
                $('#view-all-links-btn').text(`View all ${links.length} links`);
            } else {
                $('#view-all-links-container').hide();
            }

            $('#section-browser-view').hide();
            $('#resource-hub-view').fadeIn(200);
        }

        window.openResourceHub = openResourceHub;

        window.submitResourceForm = function(e, formElement) {
            e.preventDefault();
            const form = $(formElement);
            const formData = new FormData(formElement);

            // Forcefully inject category to avoid hidden input state bugs
            let currentCat = $('#hub-section-title').text().replace(' Hub ', '').trim();
            if (!currentCat) currentCat = $('.form-category').val();
            formData.set('category', currentCat);

            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.text();
            submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: 'ajax/misc/ajax_company_links.php?action=add',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                            formElement.reset();
                            $('.resource-add-form').slideUp();

                            // Restore hidden inputs after reset
                            $('.form-category').val(currentCat);

                            loadData(function() {
                                if ($('#resource-hub-view').is(':visible')) {
                                    openResourceHub(currentCat);
                                }
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Unknown error', 'error');
                        }
                    } catch (err) {
                        Swal.fire('Server Error', response.substring(0, 100), 'error');
                    }
                    submitBtn.prop('disabled', false).text(originalText);
                },
                error: function() {
                    Swal.fire('Network Error', 'Failed to submit form', 'error');
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        };

        loadData();

        // Event Handlers
        $(document).on('click', '#view-all-files-btn', function(e) {
            e.preventDefault();
            $('.extra-file-row').slideDown(200);
            $('#view-all-files-container').hide();
        });

        $(document).on('click', '#view-all-links-btn', function(e) {
            e.preventDefault();
            $('.extra-link-row').slideDown(200);
            $('#view-all-links-container').hide();
        });

        $(document).on('click', '.folder-card', function() {
            $('#hub-search-input').val('');
            openResourceHub($(this).data('category'));
        });

        $('#btn-back-to-sections').click(function() {
            $('#resource-hub-view').hide();
            $('#section-browser-view').fadeIn(200);
            loadData();
        });

        $('#btn-add-section').click(function() {
            $('#inline-section-form').slideToggle(200);
        });

        $('#section-search-input').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            if (searchTerm === '') {
                $('.folder-card').show();
            } else {
                $('.folder-card').each(function() {
                    const catName = $(this).data('category').toLowerCase();
                    if (catName.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });

        function formatEmployee(opt) {
            if (!opt.id) return opt.text;
            if (opt.id === 'all') {
                return $('<span><i class="fa fa-users" style="margin-right: 8px; color: #dd2127;"></i>' + opt.text + '</span>');
            }
            var img = $(opt.element).data('image');
            if (!img) return opt.text;
            return $('<span><img src="' + img + '" style="width:20px;height:20px;border-radius:50%;margin-right:8px;object-fit:cover;" />' + opt.text + '</span>');
        }

        $('#new-section-employees').select2({
            placeholder: "Select employees...",
            allowClear: true,
            templateResult: formatEmployee,
            templateSelection: formatEmployee
        });

        $('#new-section-employees').on('select2:select', function(e) {
            var data = e.params.data;
            if (data.id === 'all') {
                var allVals = [];
                $(this).find('option').each(function() {
                    if ($(this).val() !== 'all' && $(this).val() !== '') {
                        allVals.push($(this).val());
                    }
                });
                $(this).val(allVals).trigger('change');
            }
        });

        $('#add-section-form-direct').submit(function(e) {
            e.preventDefault();
            const name = $('#new-section-name').val().trim();
            if (name) {
                const checkedEmps = $('#new-section-employees').val() || [];

                const formData = new FormData();
                formData.append('category', name);
                checkedEmps.forEach(id => formData.append('emp_ids[]', id));

                const submitBtn = $(this).find('button[type="submit"]');
                const orig = submitBtn.text();
                submitBtn.text('Creating...').prop('disabled', true);

                $.ajax({
                    url: 'ajax/misc/ajax_company_links.php?action=save_assignments',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function() {
                        $('#inline-section-form').slideUp(200);
                        $('#hub-search-input').val('');
                        openResourceHub(name);
                        $('#new-section-name').val('');
                        $('#new-section-employees').val(null).trigger('change');
                        submitBtn.text(orig).prop('disabled', false);
                    }
                });
            }
        });

        $('#btn-add-resource-dropdown').click(function(e) {
            e.stopPropagation();
            $('#add-resource-menu').toggle();
        });

        $(document).click(function(e) {
            if (!$(e.target).closest('#btn-add-resource-dropdown, #add-resource-menu').length) {
                $('#add-resource-menu').hide();
            }
        });

        $('.add-menu-item').click(function(e) {
            e.stopPropagation();
            const type = $(this).data('type');
            $('#add-resource-menu').hide();
            $('.resource-add-form').hide();
            if (type === 'link') {
                $('#inline-add-link-form').slideDown(200);
            } else {
                $('#inline-add-document-form').slideDown(200);
            }
        });

        // Folder dropdown
        $(document).on('click', '.btn-folder-menu', function(e) {
            e.stopPropagation();
            $('.folder-dropdown').hide();
            $(this).next('.folder-dropdown').show();
        });

        $(document).click(function(e) {
            if (!$(e.target).closest('.folder-menu-container').length) {
                $('.folder-dropdown').hide();
            }
        });

        $(document).on('click', '.btn-assign-folder', function(e) {
            e.stopPropagation();
            $('.folder-dropdown').hide();
            const cat = $(this).data('category');
            $('#assign-section-name').text(cat);
            $('#assign-category-input').val(cat);

            // Uncheck all
            $('.emp-checkbox').prop('checked', false);

            // Fetch assignments
            $.ajax({
                url: 'ajax/misc/ajax_company_links.php?action=get_assignments',
                method: 'GET',
                data: {
                    category: cat
                },
                success: function(res) {
                    try {
                        const data = JSON.parse(res);
                        data.forEach(empId => {
                            $(`.emp-checkbox[value="${empId}"]`).prop('checked', true);
                        });
                        $('#assignEmployeesModal').modal('show');
                    } catch (e) {}
                }
            });
        });

        $('#assign-employees-form').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const btn = $('#btn-save-assignments');
            const orig = btn.text();
            btn.text('Saving...').prop('disabled', true);

            $.ajax({
                url: 'ajax/misc/ajax_company_links.php?action=save_assignments',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    try {
                        const data = JSON.parse(res);
                        if (data.status === 'success') {
                            $('#assignEmployeesModal').modal('hide');
                            Swal.fire('Success', 'Access updated!', 'success');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (e) {}
                    btn.text(orig).prop('disabled', false);
                }
            });
        });

        $('.btn-cancel-add').click(function() {
            $('.resource-add-form').slideUp(200);
        });

        $('#hub-search-input').on('input', function() {
            const cat = $('.form-category').val();
            openResourceHub(cat);
        });

        $(document).on('click', '.btn-delete-resource', function(e) {
            e.stopPropagation();
            const id = $(this).data('id');

            let currentCat = $('#hub-section-title').text().replace(' Hub', '').trim();
            if (!currentCat) currentCat = $('.form-category').val();

            Swal.fire({
                title: 'Delete Asset?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax/misc/ajax_company_links.php?action=delete',
                        method: 'POST',
                        data: {
                            id: id
                        },
                        success: function() {
                            loadData(function() {
                                if ($('#resource-hub-view').is(':visible')) {
                                    openResourceHub(currentCat);
                                }
                            });
                        }
                    });
                }
            });
        });

        $(document).on('click', '.btn-pin-resource', function(e) {
            e.stopPropagation();
            const id = $(this).data('id');
            const isPinned = $(this).data('pinned') == 1;
            const action = isPinned ? 'unpin' : 'pin';

            let currentCat = $('#hub-section-title').text().replace(' Hub', '').trim();
            if (!currentCat) currentCat = $('.form-category').val();

            $.ajax({
                url: 'ajax/misc/ajax_company_links.php?action=' + action,
                method: 'POST',
                data: {
                    id: id
                },
                success: function() {
                    loadData(function() {
                        if ($('#resource-hub-view').is(':visible')) {
                            openResourceHub(currentCat);
                        }
                    });
                }
            });
        });

        $(document).on('click', '#btn-rename-section', function(e) {
            e.stopPropagation();
            let currentCat = $('#hub-section-title').text().replace(' Hub', '').trim();
            if (!currentCat) currentCat = $('.form-category').val();

            Swal.fire({
                title: 'Rename Section',
                input: 'text',
                inputValue: currentCat,
                showCancelButton: true,
                confirmButtonText: 'Rename',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'You need to enter a name!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const newCat = result.value.trim();
                    if (newCat === currentCat) return;

                    $.ajax({
                        url: 'ajax/misc/ajax_company_links.php?action=rename_category',
                        method: 'POST',
                        data: {
                            old_category: currentCat,
                            new_category: newCat
                        },
                        success: function(resp) {
                            try {
                                const r = JSON.parse(resp);
                                if (r.status === 'success') {
                                    loadData(function() {
                                        openResourceHub(newCat);
                                    });
                                } else {
                                    Swal.fire('Error', r.message, 'error');
                                }
                            } catch (err) {}
                        }
                    });
                }
            });
        });
    });
</script>