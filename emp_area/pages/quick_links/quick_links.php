<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// Only allow access if logged in as employee
if (!isset($_SESSION['emp_id']) || !isset($_SESSION['emp_name'])) {
    header('Location: ../../pages/auth/login.php');
    exit();
}
$emp_id = $_SESSION['emp_id'];

// Fetch allowed categories for this employee
$allowed_categories = [];
$cat_q = mysqli_query($con, "SELECT category FROM company_links_assignments WHERE emp_id = '$emp_id'");
while ($row = mysqli_fetch_assoc($cat_q)) {
    $allowed_categories[] = "'" . mysqli_real_escape_string($con, $row['category']) . "'";
}

// If no categories allowed, we just fetch nothing
$links_data = [];
if (!empty($allowed_categories)) {
    $cat_list = implode(',', $allowed_categories);
    $query = "SELECT * FROM company_links WHERE category IN ($cat_list) ORDER BY category ASC, created_at DESC";
    $run = mysqli_query($con, $query);
    while ($row = mysqli_fetch_assoc($run)) {
        $links_data[] = $row;
    }
}
?>

<div class="premium-ui-enabled">
    <!-- Level 1: Section Browser -->
    <div id="emp-section-browser-view">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 20px;">
            <div style="position: relative; width: 350px;">
                <i class="fa fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" id="emp-section-search-input" placeholder="Search sections..." style="width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            </div>
            <button type="button" id="btn-add-personal-section" class="btn-premium-add">
                <i class="fa fa-plus"></i> New Section
            </button>
        </div>

        <!-- Inline Personal Section Add Form -->
        <div id="inline-personal-section-form" style="display: none; background: #fff; padding: 20px 25px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 25px;">
            <form id="add-personal-section-form" style="display: flex; gap: 15px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label style="font-weight: 700; color: #475569; font-size: 12px; margin-bottom: 8px; display: block;">Private Section Name</label>
                    <input type="text" id="new-personal-section-name" class="p-input-premium" placeholder="e.g. My Personal Stuff" required style="width: 100%; height: 48px; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; outline: none; font-weight: 500; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <button type="submit" class="btn-premium-add">
                        Create Section
                    </button>
                </div>
            </form>
        </div>

        <div id="emp-sections-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <!-- Rendered by JS -->
        </div>

        <!-- Pinned Links Table -->
        <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 30px;">
            <div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: var(--p-bg-header);">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #fff;">Pinned Resources (Private)</h3>
            </div>
            <div class="table-responsive">
                <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th style="text-align: center;">Type</th>
                            <th style="text-align: center;">Section</th>
                            <th style="text-align: center;">Updated On</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="emp-pinned-links-table">
                        <!-- populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Level 2: Resource Hub -->
    <div id="emp-resource-hub-view" style="display: none; width: 100%;">
        <div style="margin-bottom: 20px;">
            <button type="button" id="btn-back-to-sections" style="background: transparent; border: none; color: #64748b; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s;">
                <i class="fa fa-arrow-left"></i> Back to Sections
            </button>
        </div>

        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="width: 60px; height: 60px; background: #ffeaeb; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #dd2127;">
                    <i class="fa fa-folder"></i>
                </div>
                <div>
                    <h2 id="hub-section-title" style="margin: 0; color: #0f172a; font-size: 24px; font-weight: 800;">Section</h2>
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
                    <button type="button" id="btn-add-resource-dropdown" class="btn-premium-add">
                        <i class="fa fa-plus"></i> Add Resource <i class="fa fa-caret-down"></i>
                    </button>
                    <div id="add-resource-menu" style="display: none; position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); margin-top: 5px; z-index: 100; min-width: 180px; overflow: hidden;">
                        <div class="add-menu-item" data-type="link" style="padding: 12px 20px; cursor: pointer; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: 500; color: #475569; transition: 0.2s;"><i class="fa fa-link" style="margin-right: 8px; color: #3b82f6;"></i> Add Link</div>
                        <div class="add-menu-item" data-type="document" style="padding: 12px 20px; cursor: pointer; font-size: 14px; font-weight: 500; color: #475569; transition: 0.2s;"><i class="fa fa-file-text-o" style="margin-right: 8px; color: #10b981;"></i> Add Document</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Forms -->
        <div id="inline-add-link-form" class="resource-add-form" style="display: none; background: #f8fafc; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 25px;">
            <form id="add-link-form" method="POST" onsubmit="submitResourceForm(event, this)">
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
            <form id="add-document-form" method="POST" enctype="multipart/form-data" onsubmit="submitResourceForm(event, this)">
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
                                <th style="text-align: center;">Updated On</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="hub-files-table"></tbody>
                    </table>
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
                                <th style="text-align: center;">Updated On</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="hub-links-table"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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

    .table-row-hover:hover {
        background: #f8fafc;
    }

    .resource-icon-mini {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .add-menu-item:hover {
        background: #f8fafc;
        color: #0f172a !important;
    }
</style>

<script>
    $(document).ready(function() {
        let allCompanyResources = <?php echo json_encode($links_data); ?>;
        let allPersonalResources = [];
        let allPersonalCategories = [];
        let currentFolderMode = 'company';

        // â”€â”€ Hide "Add Resource" btn until a personal folder is opened â”€â”€
        $('#btn-add-resource-dropdown').hide();

        function loadPersonalData(callback) {
            $.when(
                $.ajax({
                    url: 'ajax/ajax_emp_personal_resources.php',
                    data: {
                        action: 'fetch_categories'
                    },
                    type: 'GET'
                }),
                $.ajax({
                    url: 'ajax/ajax_emp_personal_resources.php',
                    data: {
                        action: 'fetch_resources'
                    },
                    type: 'GET'
                })
            ).done(function(catResp, resResp) {
                try {
                    let catData = JSON.parse(catResp[0]);
                    let resData = JSON.parse(resResp[0]);
                    if (catData.status === 'success') allPersonalCategories = catData.data;
                    if (resData.status === 'success') allPersonalResources = resData.data;
                    renderSections();
                    if (callback) callback();
                } catch (e) {
                    console.error('loadPersonalData parse error', e);
                }
            }).fail(function() {
                console.error('loadPersonalData AJAX failed â€“ check that ajax/ajax_emp_personal_resources.php is reachable');
            });
        }

        function getResourceType(url) {
            const ext = (url || '').split('.').pop().toLowerCase();
            if (ext === 'pdf') return {
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

        function formatDateTime(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleString('en-US', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function renderSections() {
            try {
                const companyCategories = [...new Set(allCompanyResources.map(item => item.category))];
                let html = '';

                companyCategories.forEach(cat => {
                    const items = allCompanyResources.filter(i => i.category === cat);
                    let filesCount = 0,
                        linksCount = 0;
                    items.forEach(item => {
                        getResourceType(item.link_url).type === 'Link' ? linksCount++ : filesCount++;
                    });
                    html += `
                    <div class="folder-card" data-category="${cat}" data-mode="company">
                        <div style="display:flex;gap:15px;align-items:center;">
                            <div style="width:50px;height:50px;background:#FFEAEB;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#dd2127;font-size:24px;">
                                <i class="fa fa-folder"></i>
                            </div>
                            <div>
                                <h3 style="margin:0;color:#0f172a;font-size:16px;font-weight:700;">${cat}</h3>
                                <p style="margin:4px 0 0 0;color:#64748b;font-size:12px;">
                                    <i class="fa fa-file-o" style="margin-right:4px;"></i>${filesCount} Files &nbsp;&nbsp;
                                    <i class="fa fa-link" style="margin-right:4px;"></i>${linksCount} Links
                                </p>
                            </div>
                        </div>
                    </div>`;
                });

                allPersonalCategories.forEach(cat => {
                    const items = allPersonalResources.filter(i => i.category === cat);
                    let filesCount = 0,
                        linksCount = 0;
                    items.forEach(item => {
                        getResourceType(item.link_url).type === 'Link' ? linksCount++ : filesCount++;
                    });

                    const deleteSectionBtn = `<button class="btn-delete-section" data-category="${cat}" style="position:absolute; top:10px; right:10px; background:transparent; border:none; color:#cbd5e1; cursor:pointer; font-size:16px; padding:5px; transition:0.3s;"><i class="fa fa-trash"></i></button>`;

                    html += `
                    <div class="folder-card" data-category="${cat}" data-mode="personal" style="border:1px solid #bae6fd;background:#f0f9ff; position:relative;">
                        ${deleteSectionBtn}
                        <div style="display:flex;gap:15px;align-items:center;">
                            <div style="width:50px;height:50px;background:#e0f2fe;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#0284c7;font-size:24px;">
                                <i class="fa fa-lock"></i>
                            </div>
                            <div>
                                <h3 style="margin:0;color:#0f172a;font-size:16px;font-weight:700;">${cat} <span style="font-size:10px;background:#0284c7;color:#fff;padding:2px 6px;border-radius:10px;margin-left:6px;font-weight:600;">Private</span></h3>
                                <p style="margin:4px 0 0 0;color:#64748b;font-size:12px;">
                                    <i class="fa fa-file-o" style="margin-right:4px;"></i>${filesCount} Files &nbsp;&nbsp;
                                    <i class="fa fa-link" style="margin-right:4px;"></i>${linksCount} Links
                                </p>
                            </div>
                        </div>
                    </div>`;
                });

                if (companyCategories.length === 0 && allPersonalCategories.length === 0) {
                    html = `<div style="grid-column:1/-1;text-align:center;padding:60px 0;background:#fff;border-radius:12px;border:1px dashed #e2e8f0;">
                        <i class="fa fa-folder-open-o" style="font-size:40px;color:#cbd5e1;margin-bottom:15px;"></i>
                        <h3 style="color:#64748b;font-weight:600;font-size:16px;">No resources available</h3>
                        <p style="color:#94a3b8;font-size:14px;">Create a personal section using the "New Section" button above.</p>
                    </div>`;
                }

                $('#emp-sections-grid').html(html);

                // ── Populate Pinned Links Table ──
                const pinnedCompany = allCompanyResources.filter(r => r.is_pinned == 1);
                const pinnedPersonal = allPersonalResources.filter(r => r.is_pinned == 1);
                const allPinned = [...pinnedCompany, ...pinnedPersonal].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                let pinnedHtml = '';
                allPinned.forEach(item => {
                    const typeInfo = getResourceType(item.link_url);
                    const updatedOn = formatDateTime(item.created_at);
                    let url = item.link_url;
                    if (!url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('uploads/')) url = 'http://' + url;

                    const isPrivate = allPersonalResources.includes(item);
                    const mode = isPrivate ? 'personal' : 'company';

                    pinnedHtml += `
                    <tr class="table-row-hover">
                        <td style="padding: 15px 20px; vertical-align: middle;">
                            <div style="display: flex; align-items: center; justify-content: flex-start; gap: 12px;">
                                <div style="width: 32px; height: 32px; border-radius: 6px; background: ${isPrivate ? '#e0f2fe' : '#ffeaeb'}; color: ${isPrivate ? '#0284c7' : '#dd2127'}; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                                    <i class="fa ${typeInfo.icon}"></i>
                                </div>
                                <span style="color: #0f172a; font-weight: 500; font-size: 14px;">${item.link_name}</span>
                            </div>
                        </td>
                        <td style="padding: 15px 20px; text-align: center; vertical-align: middle; color: #475569; font-size: 14px;">${typeInfo.type}</td>
                        <td style="padding: 15px 20px; text-align: center; vertical-align: middle;">
                            <span style="color: ${isPrivate ? '#0284c7' : '#dd2127'}; cursor: pointer; font-size: 14px; font-weight: 500;" onclick="openResourceHub('${item.category}', '${mode}')">${item.category} ${isPrivate ? '(Private)' : ''}</span>
                        </td>
                        <td style="padding: 15px 20px; text-align: center; vertical-align: middle; color: #475569; font-size: 14px;">${updatedOn}</td>
                        <td style="padding: 15px 20px; text-align: center; vertical-align: middle;">
                            <div style="display: flex; justify-content: center; gap: 8px;">
                                <button class="btn-icon-premium btn-pin-resource" data-id="${item.id}" data-pinned="${item.is_pinned}" data-mode="${mode}" style="background: #fefce8; border-color: #fef9c3; color: #eab308;" title="Unpin">
                                    <i class="fa fa-thumb-tack"></i>
                                </button>
                                <a href="${url}" target="_blank" class="btn-icon-premium" style="background: #f0f9ff; border-color: #e0f2fe; color: #0284c7;" title="${typeInfo.type === 'Link' ? 'Visit' : 'Download'}">
                                    <i class="fa ${typeInfo.type === 'Link' ? 'fa-external-link' : 'fa-download'}"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    `;
                });

                if (allPinned.length === 0) {
                    pinnedHtml = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">No pinned links</td></tr>`;
                }

                $('#emp-pinned-links-table').html(pinnedHtml);

            } catch (e) {
                console.error('renderSections error', e);
            }
        }

        function openResourceHub(category, mode) {
            category = String(category);
            currentFolderMode = mode;

            const listToUse = mode === 'personal' ? allPersonalResources : allCompanyResources;
            const filtered = listToUse.filter(item => String(item.category) === category);
            const searchTerm = String($('#hub-search-input').val() || '').toLowerCase();
            const searched = filtered.filter(item =>
                String(item.link_name).toLowerCase().includes(searchTerm) ||
                String(item.link_url).toLowerCase().includes(searchTerm)
            );

            const files = [],
                links = [];
            searched.forEach(item => {
                const typeInfo = getResourceType(item.link_url);
                typeInfo.type === 'Link' ? links.push({
                    item,
                    typeInfo
                }) : files.push({
                    item,
                    typeInfo
                });
            });

            const privateLabel = mode === 'personal' ?
                '<span style="font-size:12px;background:#0284c7;color:#fff;padding:2px 8px;border-radius:10px;margin-left:8px;font-weight:600;vertical-align:middle;">Private</span>' :
                '';
            $('#hub-section-title').html(category + privateLabel);
            $('#hub-section-stats').html(`${files.length} Files &bull; ${links.length} Links`);
            $('#files-section-title').text(`Files (${files.length})`);
            $('#links-section-title').text(`Links (${links.length})`);

            if (mode === 'personal') {
                $('#btn-add-resource-dropdown').show();
            } else {
                $('#btn-add-resource-dropdown').hide();
            }

            // Render Files
            let filesHtml = '';
            files.forEach(({
                item,
                typeInfo
            }) => {
                const dateOn = formatDateTime(item.created_at);
                let url = item.link_url;
                if (!url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('uploads/')) url = 'http://' + url;
                if (mode === 'company' && url.startsWith('uploads/')) url = '../admin_area/' + url;
                if (mode === 'personal' && url.startsWith('uploads/')) url = 'emp_area/' + url;

                const pinBg = (item.is_pinned == 1) ? '#fefce8' : '#f1f5f9';
                const pinBorder = (item.is_pinned == 1) ? '#fef9c3' : '#e2e8f0';
                const pinColor = (item.is_pinned == 1) ? '#eab308' : '#64748b';
                const pinTitle = (item.is_pinned == 1) ? 'Unpin' : 'Pin';

                const pinBtn = `<button class="btn-icon-premium btn-pin-resource" data-id="${item.id}" data-pinned="${item.is_pinned}" data-mode="${mode}" style="background:${pinBg};border-color:${pinBorder};color:${pinColor};" title="${pinTitle}"><i class="fa fa-thumb-tack"></i></button>`;
                const deleteBtn = mode === 'personal' ? `<button class="btn-icon-premium btn-delete-resource" data-id="${item.id}" style="background:#fef2f2;border-color:#fecaca;color:#ef4444;" title="Delete"><i class="fa fa-trash"></i></button>` : '';

                filesHtml += `
                <tr class="table-row-hover">
                    <td style="padding:12px 20px;vertical-align:middle;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div class="resource-icon-mini" style="background:${typeInfo.bg};color:${typeInfo.color};"><i class="fa ${typeInfo.icon}"></i></div>
                            <span style="color:#0f172a;font-weight:500;font-size:14px;">${item.link_name}</span>
                        </div>
                    </td>
                    <td style="padding:12px 20px;text-align:center;vertical-align:middle;color:#475569;font-size:13px;font-weight:500;">${typeInfo.type}</td>
                    <td style="padding:12px 20px;text-align:center;vertical-align:middle;color:#475569;font-size:13px;">${dateOn}</td>
                    <td style="padding:12px 20px;text-align:center;vertical-align:middle;">
                        <div style="display:flex;justify-content:center;gap:8px;">
                            ${pinBtn}
                            <a href="${url}" target="_blank" class="btn-icon-premium" style="background:#f0f9ff;border-color:#e0f2fe;color:#0284c7;" title="Download"><i class="fa fa-download"></i></a>
                            ${deleteBtn}
                        </div>
                    </td>
                </tr>`;
            });
            if (files.length === 0) filesHtml = `<tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">No files found</td></tr>`;
            $('#hub-files-table').html(filesHtml);

            // Render Links
            let linksHtml = '';
            links.forEach(({
                item,
                typeInfo
            }) => {
                const dateOn = formatDateTime(item.created_at);
                let url = item.link_url;
                if (!url.startsWith('http://') && !url.startsWith('https://')) url = 'http://' + url;

                const pinBg = (item.is_pinned == 1) ? '#fefce8' : '#f1f5f9';
                const pinBorder = (item.is_pinned == 1) ? '#fef9c3' : '#e2e8f0';
                const pinColor = (item.is_pinned == 1) ? '#eab308' : '#64748b';
                const pinTitle = (item.is_pinned == 1) ? 'Unpin' : 'Pin';

                const pinBtn = `<button class="btn-icon-premium btn-pin-resource" data-id="${item.id}" data-pinned="${item.is_pinned}" data-mode="${mode}" style="background:${pinBg};border-color:${pinBorder};color:${pinColor};" title="${pinTitle}"><i class="fa fa-thumb-tack"></i></button>`;
                const deleteBtn = mode === 'personal' ? `<button class="btn-icon-premium btn-delete-resource" data-id="${item.id}" style="background:#fef2f2;border-color:#fecaca;color:#ef4444;" title="Delete"><i class="fa fa-trash"></i></button>` : '';

                linksHtml += `
                <tr class="table-row-hover">
                    <td style="padding:12px 20px;vertical-align:middle;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div class="resource-icon-mini" style="background:${typeInfo.bg};color:${typeInfo.color};"><i class="fa ${typeInfo.icon}"></i></div>
                            <span style="color:#0f172a;font-weight:500;font-size:14px;">${item.link_name}</span>
                        </div>
                    </td>
                    <td style="padding:12px 20px;text-align:center;vertical-align:middle;">
                        <a href="${url}" target="_blank" style="color:#dd2127;font-size:13px;text-decoration:none;word-break:break-all;">${url}</a>
                    </td>
                    <td style="padding:12px 20px;text-align:center;vertical-align:middle;color:#475569;font-size:13px;">${dateOn}</td>
                    <td style="padding:12px 20px;text-align:center;vertical-align:middle;">
                        <div style="display:flex;justify-content:center;gap:8px;">
                            ${pinBtn}
                            <a href="${url}" target="_blank" class="btn-icon-premium" style="background:#f0f9ff;border-color:#e0f2fe;color:#0284c7;" title="Visit"><i class="fa fa-external-link"></i></a>
                            ${deleteBtn}
                        </div>
                    </td>
                </tr>`;
            });
            if (links.length === 0) linksHtml = `<tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">No links found</td></tr>`;
            $('#hub-links-table').html(linksHtml);

            $('#emp-section-browser-view').hide();
            $('#emp-resource-hub-view').fadeIn(200);
        }

        // ── Initial load ──
        loadPersonalData();

        // ── Section card click ──
        $(document).on('click', '.folder-card', function() {
            $('#hub-search-input').val('');
            openResourceHub($(this).data('category'), $(this).data('mode'));
        });

        // ── Back button ──
        $('#btn-back-to-sections').click(function() {
            $('#emp-resource-hub-view').hide();
            $('#emp-section-browser-view').fadeIn(200);
            $('#btn-add-resource-dropdown').hide();
            $('.resource-add-form').hide();
        });

        // ── Hub search ──
        $('#hub-search-input').on('keyup', function() {
            const cat = $('#hub-section-title').text().replace('Private', '').trim();
            if (cat) openResourceHub(cat, currentFolderMode);
        });

        // ── Add resource dropdown ──
        $('#btn-add-resource-dropdown').click(function(e) {
            e.stopPropagation();
            $('#add-resource-menu').toggle();
        });
        $(document).click(function(e) {
            if (!$(e.target).closest('#btn-add-resource-dropdown, #add-resource-menu').length) {
                $('#add-resource-menu').hide();
            }
        });

        // ── Add link / document menu items ──
        $('.add-menu-item').click(function() {
            const type = $(this).data('type');
            $('#add-resource-menu').hide();
            $('.resource-add-form').hide();
            const cat = $('#hub-section-title').text().replace('Private', '').trim();
            $('.form-category').val(cat);
            if (type === 'link') $('#inline-add-link-form').slideDown();
            else if (type === 'document') $('#inline-add-document-form').slideDown();
        });

        // ── Cancel buttons ──
        $('.btn-premium-cancel').click(function() {
            $('.resource-add-form').slideUp();
            $(this).closest('form')[0].reset();
        });

        // ── New personal section button ──
        $('#btn-add-personal-section').click(function() {
            $('#inline-personal-section-form').slideToggle(200);
        });

        // ── Create personal section form submit ──
        $('#add-personal-section-form').submit(function(e) {
            e.preventDefault();
            const name = $('#new-personal-section-name').val().trim();
            if (!name) return;
            const submitBtn = $(this).find('button[type="submit"]');
            const orig = submitBtn.text();
            submitBtn.text('Creating...').prop('disabled', true);
            $.ajax({
                url: 'ajax/ajax_emp_personal_resources.php',
                type: 'POST',
                data: {
                    action: 'add_category',
                    category: name
                },
                success: function(resp) {
                    try {
                        const data = JSON.parse(resp);
                        if (data.status === 'success') {
                            $('#inline-personal-section-form').slideUp();
                            $('#new-personal-section-name').val('');
                            loadPersonalData(function() {
                                openResourceHub(name, 'personal');
                            });
                        } else {
                            if (typeof Swal !== 'undefined') Swal.fire('Error', data.message, 'error');
                            else alert(data.message);
                        }
                    } catch (err) {
                        alert('Server error');
                    }
                    submitBtn.text(orig).prop('disabled', false);
                },
                error: function() {
                    alert('Network error');
                    submitBtn.text(orig).prop('disabled', false);
                }
            });
        });

        // ── Resource form submit (link or document) ──
        window.submitResourceForm = function(e, formElement) {
            e.preventDefault();
            const form = $(formElement);
            const formData = new FormData(formElement);
            let currentCat = $('#hub-section-title').text().replace('Private', '').trim();
            formData.set('category', currentCat);
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.text();
            submitBtn.prop('disabled', true).text('Saving...');
            const ajaxUrl = 'ajax/ajax_emp_personal_resources.php';
            formData.append('action', 'add_resource');
            $.ajax({
                url: ajaxUrl,
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
                            loadPersonalData(function() {
                                openResourceHub(currentCat, 'personal');
                            });
                        } else {
                            if (typeof Swal !== 'undefined') Swal.fire('Error', data.message || 'Unknown error', 'error');
                            else alert('Error: ' + (data.message || 'Unknown error'));
                        }
                    } catch (err) {
                        if (typeof Swal !== 'undefined') Swal.fire('Server Error', response.substring(0, 200), 'error');
                        else alert('Server Error: ' + response.substring(0, 200));
                    }
                    submitBtn.prop('disabled', false).text(originalText);
                },
                error: function() {
                    if (typeof Swal !== 'undefined') Swal.fire('Network Error', 'Failed to submit form', 'error');
                    else alert('Network Error');
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        };

        // ── Section grid search ──
        $('#emp-section-search-input').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('.folder-card').each(function() {
                const catName = String($(this).data('category')).toLowerCase();
                $(this).toggle(searchTerm === '' || catName.includes(searchTerm));
            });
        });

        // ── Pin/Unpin resource ──
        $(document).on('click', '.btn-pin-resource', function() {
            const btn = $(this);
            const id = btn.data('id');
            const mode = btn.data('mode') || currentFolderMode;
            const currentPinned = btn.data('pinned');
            const newPinned = (currentPinned == 1) ? 0 : 1;
            $.ajax({
                url: 'ajax/ajax_emp_personal_resources.php',
                method: 'POST',
                data: {
                    action: 'toggle_pin',
                    id: id,
                    is_pinned: newPinned,
                    mode: mode
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                            const list = mode === 'personal' ? allPersonalResources : allCompanyResources;
                            const item = list.find(r => r.id == id);
                            if (item) item.is_pinned = newPinned;

                            const currentCat = $('#hub-section-title').text().replace('Private', '').trim();
                            if (currentCat && currentCat !== 'Section') {
                                // re-render hub if still open
                                openResourceHub(currentCat, currentFolderMode);
                            }
                            // also refresh main grid if they pinned from somewhere else
                            renderSections();
                        } else {
                            alert('Failed to pin: ' + (data.message || 'Unknown error'));
                        }
                    } catch (e) {
                        alert('Server error while pinning.');
                    }
                }
            });
        });

        // ── Delete Section ──
        $(document).on('click', '.btn-delete-section', function(e) {
            e.stopPropagation(); // Prevent opening the folder
            const cat = $(this).data('category');
            Swal.fire({
                title: 'Delete Section?',
                text: 'This will permanently delete the section and all resources inside it.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax/ajax_emp_personal_resources.php',
                        type: 'POST',
                        data: {
                            action: 'delete_category',
                            category: cat
                        },
                        success: function(resp) {
                            try {
                                const data = JSON.parse(resp);
                                if (data.status === 'success') {
                                    loadPersonalData();
                                } else {
                                    Swal.fire('Error', data.message, 'error');
                                }
                            } catch (e) {
                                Swal.fire('Error', 'Server error', 'error');
                            }
                        }
                    });
                }
            });
        });

        // ── Delete resource ──
        $(document).on('click', '.btn-delete-resource', function() {
            if (currentFolderMode !== 'personal') return;
            const id = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will permanently delete the resource.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax/ajax_emp_personal_resources.php',
                        type: 'POST',
                        data: {
                            action: 'delete_resource',
                            id: id
                        },
                        success: function(resp) {
                            try {
                                const data = JSON.parse(resp);
                                if (data.status === 'success') {
                                    loadPersonalData(function() {
                                        const cat = $('#hub-section-title').text().replace('Private', '').trim();
                                        openResourceHub(cat, 'personal');
                                    });
                                } else {
                                    Swal.fire('Error', data.message, 'error');
                                }
                            } catch (e) {
                                Swal.fire('Error', 'Server error', 'error');
                            }
                        }
                    });
                }
            });
        });

    });
</script>