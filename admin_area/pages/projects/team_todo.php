<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id == 0) {
    echo "<script>window.location.href='index.php?projects';</script>";
    exit();
}

$get_project = "SELECT cp.*, c.name as client_name FROM client_projects cp JOIN clients c ON cp.client_id = c.id WHERE cp.id = $project_id";
$run_project = mysqli_query($con, $get_project);
$project = mysqli_fetch_assoc($run_project);

if (!$project) {
    echo "<script>window.location.href='index.php?projects';</script>";
    exit();
}

$assigned_employees = array_filter(explode(',', $project['assigned_employees']), function ($id) {
    return !empty(trim($id));
});

?>

<div class="page-wrapper premium-ui-enabled" style="background: #f8fafc; min-height: calc(100vh - 60px);">
    <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-list-alt" style="color: #dc2626;"></i> Team To-Do
            </h1>
            <div style="padding: 8px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                <i class="fa fa-building-o" style="color: #64748b;"></i> <?php echo htmlspecialchars($project['project_name']); ?>
            </div>
        </div>
        <div class="header-actions-premium" style="display: flex; gap: 16px; align-items: center;">
            <div style="position: relative;">
                <input type="date" id="task-date-filter" style="padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; font-weight: 500; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);" title="Filter by Due Date">
            </div>
            <div style="position: relative;">
                <i class="fa fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;"></i>
                <input type="text" id="task-search" placeholder="Search tasks..." style="width: 250px; padding: 10px 15px 10px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; font-weight: 500; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            </div>
            <a href="index.php?view_projects&id=<?php echo $project['client_id']; ?>" class="btn-premium-cancel">
                <i class="fa fa-arrow-left"></i> Back to Project
            </a>
        </div>
    </div>

    <div class="todo-board">
        <?php
        if (empty($assigned_employees)) {
            echo '<div style="text-align: center; width: 100%; padding: 50px; color: #64748b; font-weight: 600;">No employees assigned to this project.</div>';
        } else {
            foreach ($assigned_employees as $emp_id) {
                $emp_id = intval($emp_id);
                $get_emp = mysqli_query($con, "SELECT * FROM emp_list WHERE id = $emp_id");
                $emp = mysqli_fetch_assoc($get_emp);
                if (!$emp) continue;

                $emp_name = htmlspecialchars($emp['name']);
                $emp_job = htmlspecialchars($emp['job_title'] ?? 'Employee');
                $emp_img = !empty($emp['employee_image']) ? 'uploads/' . htmlspecialchars($emp['employee_image']) : null;
        ?>
                <div class="todo-column" data-emp-id="<?php echo $emp_id; ?>">
                    <div class="todo-col-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if ($emp_img && file_exists('../../' . $emp_img)) { ?>
                                <img src="<?php echo $emp_img; ?>" class="emp-avatar">
                            <?php } else { ?>
                                <div class="emp-avatar-fallback"><?php echo strtoupper(substr($emp_name, 0, 1)); ?></div>
                            <?php } ?>
                            <div>
                                <div class="emp-name"><?php echo $emp_name; ?></div>
                                <div class="emp-role"><?php echo $emp_job; ?></div>
                            </div>
                        </div>
                        <button class="icon-btn"><i class="fa fa-ellipsis-v"></i></button>
                    </div>

                    <?php if (function_exists('canAdminAccess') && (canAdminAccess('todo_insert') || canAdminAccess('project_assign_task'))): ?>
                        <div class="add-task-trigger" onclick="showAddTask(<?php echo $emp_id; ?>)">
                            <i class="fa fa-plus-circle" style="font-size: 16px; color: #94a3b8;"></i>
                            <span>Add a task</span>
                        </div>
                    <?php endif; ?>

                    <div class="add-task-form" id="add-form-<?php echo $emp_id; ?>" style="display: none;">
                        <input type="text" class="task-input" id="task-input-<?php echo $emp_id; ?>" placeholder="What needs to be done?">
                        <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                            <input type="date" class="task-date-input" id="task-date-<?php echo $emp_id; ?>">
                            <select class="task-priority-input" id="task-priority-<?php echo $emp_id; ?>">
                                <option value="Low">Low Priority</option>
                                <option value="Medium" selected>Medium Priority</option>
                                <option value="High">High Priority</option>
                            </select>
                            <button class="btn-premium-add" onclick="saveTask(<?php echo $emp_id; ?>)">Add</button>
                            <button class="btn-premium-cancel" onclick="hideAddTask(<?php echo $emp_id; ?>)">Cancel</button>
                        </div>
                    </div>

                    <div class="task-list" id="task-list-<?php echo $emp_id; ?>">
                        <div style="text-align: center; padding: 20px;"><i class="fa fa-spinner fa-spin" style="color: #cbd5e1;"></i></div>
                    </div>
                </div>
        <?php
            }
        }
        ?>
    </div>
</div>

<style>
    .todo-board {
        display: flex;
        gap: 20px;
        overflow-x: auto;
        padding-bottom: 20px;
        align-items: flex-start;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
    }

    .todo-board::-webkit-scrollbar {
        height: 8px;
    }

    .todo-board::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .todo-board::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .todo-board::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .todo-column {
        min-width: 360px;
        max-width: 360px;
        flex: 0 0 360px;
        background: #ffffff;
        border: 1px solid #e8edf3;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .todo-column:hover {
        box-shadow: 0 4px 16px rgba(220, 38, 38, 0.1);
        transform: translateY(-2px);
    }

    .todo-col-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-left: 4px solid #dc2626;
        background: #fff;
    }

    .emp-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .emp-avatar-fallback {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        color: #dc2626;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 16px;
        border: 2px solid #fecaca;
        flex-shrink: 0;
    }

    .completed-section-header {
        margin: 4px 16px 4px;
        padding: 10px 12px;
        border-top: 1px solid #f1f5f9;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #94a3b8;
        font-weight: 600;
        font-size: 11px;
        border-radius: 8px;
        transition: background 0.15s;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        user-select: none;
    }

    .completed-section-header:hover {
        background: #f8fafc;
        color: #64748b;
    }

    .emp-name {
        font-weight: 700;
        font-size: 15px;
        color: #0f172a;
        line-height: 1.3;
    }

    .emp-role {
        font-weight: 500;
        font-size: 11px;
        color: #94a3b8;
        margin-top: 2px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .icon-btn {
        background: transparent;
        border: none;
        color: #cbd5e1;
        cursor: pointer;
        font-size: 15px;
        padding: 6px 8px;
        border-radius: 6px;
        transition: all 0.15s ease;
        line-height: 1;
    }

    .icon-btn:hover {
        color: #64748b;
        background: #f1f5f9;
    }

    .add-task-trigger {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        padding: 10px 16px;
        margin-bottom: 5px;
        transition: 0.2s;
    }

    .add-task-trigger:hover {
        color: #dc2626;
    }

    .add-task-trigger:hover i {
        color: #dc2626 !important;
    }

    .add-task-form {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px;
        margin: 0 16px 15px 16px;
    }

    .task-input {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 500;
        outline: none;
        transition: 0.2s;
    }

    .task-input:focus {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .task-date-input,
    .task-priority-input,
    .task-project-input {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        outline: none;
    }

    .task-project-input {
        font-size: 13px;
    }

    .save-task-btn {
        background: #dc2626;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .cancel-task-btn {
        background: #fff;
        color: #64748b;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .task-list {
        max-height: 400px;
        overflow-y: auto;
        padding: 0 16px 12px;
    }

    .task-list::-webkit-scrollbar {
        width: 4px;
    }

    .task-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .task-list::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 4px;
    }

    .task-list::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }

    .task-item {
        display: flex;
        flex-direction: column;
        padding: 12px 0;
        border-bottom: 1px solid #f8fafc;
        transition: background 0.15s ease;
        width: 100%;
    }

    .task-item:last-child {
        border-bottom: none;
    }

    .task-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .task-checkbox:hover {
        border-color: #dc2626;
        background: #fef2f2;
    }

    .task-checkbox i {
        display: none;
        color: #fff;
        font-size: 10px;
    }

    .task-item.completed .task-checkbox {
        background: #dc2626;
        border-color: #dc2626;
    }

    .task-item.completed .task-checkbox i {
        display: block;
    }

    .task-proj-name {
        font-size: 10px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .task-name {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.45;
        word-break: break-word;
        flex: 1;
        min-width: 0;
    }

    .task-item.completed .task-name {
        text-decoration: line-through;
        color: #94a3b8;
    }

    .task-meta {
        display: flex;
        align-items: center;
        width: 100%;
        box-sizing: border-box;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
        padding-left: 30px;
    }

    .date-badge {
        background: #fef2f2;
        color: #dc2626;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid #fecaca;
        white-space: nowrap;
        flex-shrink: 0;
        display: inline-block;
        line-height: 1.3;
    }

    .priority-flag {
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 8px;
        border-radius: 20px;
        white-space: nowrap;
        flex-shrink: 0;
        line-height: 1.3;
    }

    .priority-High {
        color: #dc2626;
        background: #fef2f2;
    }

    .priority-Medium {
        color: #d97706;
        background: #fffbeb;
    }

    .priority-Low {
        color: #16a34a;
        background: #f0fdf4;
    }
</style>

<script>
    const canTodoDelete = <?php echo (function_exists('canAdminAccess') && (canAdminAccess('todo_delete') || canAdminAccess('project_assign_task'))) ? 'true' : 'false'; ?>;
    const canTodoUpdate = <?php echo (function_exists('canAdminAccess') && (canAdminAccess('todo_update') || canAdminAccess('project_assign_task'))) ? 'true' : 'false'; ?>;
</script>
<script>
    const projectId = <?php echo $project_id; ?>;

    $(document).ready(function() {
        // Load tasks for all columns
        $('.todo-column').each(function() {
            const empId = $(this).data('emp-id');
            loadTasks(empId);
        });

        function applyFilters() {
            const searchVal = $('#task-search').val();
            const term = searchVal ? searchVal.toLowerCase() : '';
            const filterDate = $('#task-date-filter').val();

            const empCompletedCounts = {};
            const empPendingCounts = {};

            $('.task-item').each(function() {
                const name = $(this).find('.task-name').text().toLowerCase();
                const proj = $(this).find('.task-proj-name').text().toLowerCase();
                const taskDate = $(this).attr('data-date');
                const isCompleted = $(this).hasClass('completed');

                const parentListId = $(this).closest('[id^="task-list-"]').attr('id');
                const empId = parentListId ? parentListId.replace('task-list-', '') : null;

                if (empId && empCompletedCounts[empId] === undefined) {
                    empCompletedCounts[empId] = 0;
                    empPendingCounts[empId] = 0;
                }

                let matchText = true;
                if (term) {
                    matchText = name.includes(term) || proj.includes(term);
                }

                let showTask = true;
                if (filterDate) {
                    if (isCompleted) {
                        showTask = (taskDate === filterDate);
                    } else {
                        showTask = true;
                    }
                }

                if (matchText && showTask) {
                    $(this).show();
                    if (empId) {
                        if (isCompleted) {
                            empCompletedCounts[empId]++;
                        } else {
                            empPendingCounts[empId]++;
                        }
                    }
                } else {
                    $(this).hide();
                }
            });

            $('.todo-column').each(function() {
                const empId = $(this).data('emp-id');
                const compCount = empCompletedCounts[empId] || 0;
                const pendCount = empPendingCounts[empId] || 0;
                const header = $(this).find('.completed-section-header');

                if (compCount > 0) {
                    header.show();
                    $(`#completed-text-${empId}`).text(`Completed (${compCount})`);
                } else {
                    header.hide();
                    $(`#completed-tasks-${empId}`).hide();
                    $(`#completed-icon-${empId}`).removeClass('fa-chevron-right').addClass('fa-chevron-down');
                }

                const totalVisible = compCount + pendCount;
                const hasServerMsg = $(this).find('.empty-server-msg').length > 0;

                $(this).find('.filter-empty-msg').remove();

                if (totalVisible === 0 && !hasServerMsg) {
                    $(this).find('.task-list').append('<div class="filter-empty-msg" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px;">No tasks match filters</div>');
                }
            });
        }

        $('#task-search').on('keyup', applyFilters);
        $('#task-date-filter').on('change', applyFilters);
    });

    function showAddTask(empId) {
        $(`#add-form-${empId}`).slideDown(200);
        $(`#task-input-${empId}`).focus();

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        $(`#task-date-${empId}`).val(`${yyyy}-${mm}-${dd}`);
    }

    function hideAddTask(empId) {
        $(`#add-form-${empId}`).slideUp(200);
        $(`#task-input-${empId}`).val('');
        $(`#task-date-${empId}`).val('');
        $(`#task-priority-${empId}`).val('Medium');
    }

    function loadTasks(empId) {
        $.ajax({
            url: 'ajax/projects/ajax_get_team_todos.php',
            method: 'POST',
            data: {
                project_id: projectId,
                emp_id: empId
            },
            success: function(res) {
                if (res && res.success) {
                    renderTasks(empId, res.tasks || []);
                } else {
                    $(`#task-list-${empId}`).html('<div class="empty-server-msg" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px;">' + (res && res.message ? escapeHtml(res.message) : 'No tasks assigned yet') + '</div>');
                }
            },
            error: function() {
                $(`#task-list-${empId}`).html('<div class="empty-server-msg" style="color: #ef4444; font-size: 13px; text-align: center; padding: 20px;">Error loading tasks</div>');
            }
        });
    }

    function renderTasks(empId, tasks) {
        const list = $(`#task-list-${empId}`);
        list.empty();

        if (!tasks || tasks.length === 0) {
            list.html('<div class="empty-server-msg" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px;">No tasks assigned yet</div>');
            return;
        }

        let pendingTasksHtml = '';
        let completedTasksHtml = '';
        let completedCount = 0;

        tasks.forEach(task => {
            try {
                const isCompleted = parseInt(task.status) === 1;
                const itemClass = isCompleted ? 'task-item completed' : 'task-item';

                let dateBadge = '';
                if (task.due_date) {
                    const due = new Date(task.due_date);
                    const today = new Date();
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);

                    let dateStr = due.toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short'
                    });
                    if (due.toDateString() === today.toDateString()) {
                        dateStr = 'Today';
                    } else if (due.toDateString() === tomorrow.toDateString()) {
                        dateStr = 'Tomorrow';
                    }
                    dateBadge = `<div class="date-badge">${dateStr}</div>`;
                }

                let priorityHtml = '';
                if (task.priority) {
                    priorityHtml = `<div class="priority-flag priority-${task.priority}"><i class="fa fa-flag"></i> ${task.priority}</div>`;
                }

                let addedBadge = '';
                if (task.created_at) {
                    const createdAt = new Date(task.created_at.replace(/-/g, '/'));
                    const addedStr = createdAt.toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short'
                    }) + ', ' + createdAt.toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit'
                    });
                    addedBadge = `<div style="font-size: 11px; color: #94a3b8; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; margin-left: auto; white-space: nowrap;"><i class="fa fa-clock-o"></i> ${addedStr}</div>`;
                }

                const projName = task.project_name ? escapeHtml(task.project_name) : 'Project Task';
                const safeDate = task.due_date ? task.due_date : '';
                const taskNameStyle = isCompleted ? 'text-decoration: line-through; color: #94a3b8;' : '';

                const checkboxHtml = canTodoUpdate ?
                    `<div class="task-checkbox" onclick="toggleTask(${task.id}, ${empId}, ${isCompleted ? 0 : 1})"><i class="fa fa-check"></i></div>` :
                    `<div class="task-checkbox" style="cursor: default; opacity: 0.5;"><i class="fa fa-check"></i></div>`;

                let dropdownHtml = '';
                if (canTodoDelete) {
                    dropdownHtml = `
                        <div class="dropdown" style="flex-shrink: 0; margin-left: 8px;">
                            <div class="task-menu-btn" data-toggle="dropdown" style="cursor: pointer; padding: 2px 4px; color: #64748b;">
                                <i class="fa fa-ellipsis-v"></i>
                            </div>
                            <ul class="dropdown-menu dropdown-menu-right" style="border-radius: 8px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); padding: 5px 0; min-width: 120px;">
                                <li><a href="#" onclick="deleteTask(${task.id}, ${empId}); return false;" style="color: #ef4444; font-weight: 600; padding: 10px 20px;"><i class="fa fa-trash-o" style="margin-right: 8px;"></i> Delete</a></li>
                            </ul>
                        </div>
                    `;
                }

                const html = `
                    <div class="${itemClass}" data-task-id="${task.id}" data-date="${safeDate}">
                        <div style="display: flex; align-items: flex-start; gap: 10px; width: 100%;">
                            ${checkboxHtml}
                            <div class="task-name" style="${taskNameStyle}">${escapeHtml(task.task_name)}</div>
                            ${dropdownHtml}
                        </div>
                        <div class="task-meta">
                            ${priorityHtml}
                            ${dateBadge}
                            <div class="task-proj-name"><i class="fa fa-building-o"></i> ${projName}</div>
                            ${addedBadge}
                        </div>
                    </div>
                `;

                if (isCompleted) {
                    completedTasksHtml += html;
                    completedCount++;
                } else {
                    pendingTasksHtml += html;
                }
            } catch (err) {
                console.error("Error rendering task item:", err);
            }
        });

        list.append(pendingTasksHtml);

        if (completedCount > 0) {
            const completedSection = `
                <div class="completed-section-header" onclick="toggleCompletedSection(${empId})">
                    <i class="fa fa-chevron-down" id="completed-icon-${empId}" style="transition: transform 0.3s; font-size: 11px;"></i>
                    <span id="completed-text-${empId}">Completed (${completedCount})</span>
                </div>
                <div id="completed-tasks-${empId}" style="display: none;">
                    ${completedTasksHtml}
                </div>
            `;
            list.append(completedSection);
        }

        $('#task-search').trigger('keyup');
    }

    function saveTask(empId) {
        const name = $(`#task-input-${empId}`).val().trim();
        const date = $(`#task-date-${empId}`).val();
        const priority = $(`#task-priority-${empId}`).val();

        if (!name) {
            Swal.fire("Required", "Please enter task name", "warning");
            return;
        }

        $.ajax({
            url: 'ajax/projects/ajax_add_team_todo.php',
            method: 'POST',
            data: {
                project_id: projectId,
                emp_id: empId,
                task_name: name,
                due_date: date,
                priority: priority
            },
            success: function(res) {
                if (res.success) {
                    hideAddTask(empId);
                    loadTasks(empId);
                } else {
                    Swal.fire("Error", "Could not add task.", "error");
                }
            }
        });
    }

    function toggleTask(taskId, empId, newStatus) {
        $.ajax({
            url: 'ajax/projects/ajax_toggle_team_todo.php',
            method: 'POST',
            data: {
                task_id: taskId,
                status: newStatus
            },
            success: function(res) {
                if (res && res.success) {
                    loadTasks(empId);
                } else {
                    Swal.fire("Error", (res && res.message) ? res.message : "Could not update task.", "error");
                }
            },
            error: function() {
                Swal.fire("Error", "Network error updating task.", "error");
            }
        });
    }

    function deleteTask(taskId, empId) {
        Swal.fire({
            title: 'Delete Task?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/projects/ajax_delete_team_todo.php',
                    method: 'POST',
                    data: {
                        task_id: taskId
                    },
                    success: function(res) {
                        if (res.success) {
                            loadTasks(empId);
                        } else {
                            Swal.fire("Error", res.message || "Failed to delete task", "error");
                        }
                    }
                });
            }
        });
    }

    function toggleCompletedSection(empId) {
        $(`#completed-tasks-${empId}`).slideToggle(200);
        const icon = $(`#completed-icon-${empId}`);
        if (icon.hasClass('fa-chevron-down')) {
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>