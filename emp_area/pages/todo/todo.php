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

$filter_date_raw = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$filter_date     = date('Y-m-d', strtotime($filter_date_raw));

// Fetch todos assigned to this employee
// Pending tasks (status=0) show up first
// Completed tasks (status=1) show up at the bottom of the table, filtered by selected date
$query = "SELECT t.*, p.project_name
          FROM project_team_todos t 
          LEFT JOIN client_projects p ON t.project_id = p.id 
          WHERE t.emp_id = $emp_id 
          AND (t.status = 0 OR (t.status = 1 AND DATE(COALESCE(t.completed_at, t.due_date, t.created_at)) = '$filter_date'))
          ORDER BY t.status ASC, CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END ASC, t.due_date ASC, t.id DESC";
$result = mysqli_query($con, $query);

?>

<div class="premium-ui-enabled">
    <div class="row">
        <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; margin-bottom: 0px;">
            <h1></h1>
            <div class="header-actions-premium" style="display: flex; gap: 14px; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px; background: #ffffff; padding: 6px 14px; border-radius: 12px; border: 1.5px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                    <i class="fa fa-calendar" style="color: #dd2127; font-size: 14px;"></i>
                    <span style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;">Completed On:</span>
                    <input type="date" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="if(this.value) window.location.href='index.php?todo&date=' + this.value" style="height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 13px; color: #0f172a; font-weight: 700; background: #f8fafc; outline: none; width: 145px; transition: all 0.2s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 3px rgba(221,33,39,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                </div>
                <button data-toggle="modal" data-target="#addTodoModal" class="btn-premium-add" style="height: 52px; display: inline-flex; align-items: center; gap: 8px; padding: 0 20px; font-size: 13px; font-weight: 700; border-radius: 12px;">
                    <i class="fa fa-plus"></i> Add Task
                </button>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="premium-card" style="border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 25px -5px rgba(0,0,0,0.08); background: #fff;">
                <div class="card-hdr" style="background: var(--p-bg-header); color: #fff; padding: 18px 25px; display: flex; align-items: center; gap: 12px; border: none;">
                    <i class="fa fa-list-ol" style="font-size: 16px; color: #fff;"></i>
                    <h3 style="margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">Assigned Tasks</h3>
                </div>
                <div class="table-responsive">
                    <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #fcfdfe; border-bottom: 1.5px solid #f1f5f9;">
                                <th style="text-align: center;">ID</th>
                                <th style="text-align: center;">Task Details</th>
                                <th style="text-align: center;">Project</th>
                                <th style="text-align: center;">Due Date</th>
                                <th style="text-align: center;">Priority</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0) : ?>
                                <?php while ($row = mysqli_fetch_assoc($result)) :
                                    $is_completed = intval($row['status']) === 1;

                                    // Priority badge
                                    $priority = strtolower($row['priority'] ?? 'medium');
                                    $priority_badge = 'background: #f1f5f9; color: #64748b;';
                                    if ($priority == 'high') $priority_badge = 'background: #fef2f2; color: #dc2626;';
                                    elseif ($priority == 'medium') $priority_badge = 'background: #eff6ff; color: #2563eb;';
                                    elseif ($priority == 'low') $priority_badge = 'background: #ecfdf5; color: #059669;';

                                    $proj_name = !empty($row['project_name']) ? htmlspecialchars($row['project_name']) : 'Global Task';
                                ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; <?php echo $is_completed ? 'background: #fafafa;' : ''; ?>">
                                        <td style="text-align: center; font-weight: 700; color: #64748b;">
                                            <span style="background:<?php echo $is_completed ? '#e2e8f0' : '#f1f5f9'; ?>; padding:4px 8px; border-radius:6px; font-size:12px; color:<?php echo $is_completed ? '#475569' : '#64748b'; ?>;">#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                        </td>
                                        <td>
                                            <div onclick="openEmpTaskDetail(<?php echo $row['id']; ?>)" style="font-weight: 700; color: <?php echo $is_completed ? '#64748b' : '#1e293b'; ?>; font-size: 14px; text-align: left; <?php echo $is_completed ? 'text-decoration: line-through;' : ''; ?> cursor: pointer;" title="Click to view task details and comments">
                                                <i class="fa fa-info-circle" style="color: #dd2127; margin-right: 6px;"></i> <?php echo htmlspecialchars($row['task_name']); ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="font-weight: 700; color: #334155; font-size: 13px;">
                                                <?php echo $proj_name; ?>
                                            </div>
                                        </td>
                                        <td style="font-weight: 600; color: #475569; font-size: 13px; text-align: center;">
                                            <?php echo !empty($row['due_date']) ? date('d-m-Y', strtotime($row['due_date'])) : '--'; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <span style="padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; <?php echo $priority_badge; ?> display: inline-block; <?php echo $is_completed ? 'opacity: 0.8;' : ''; ?>">
                                                <?php echo ucfirst($priority); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; padding: 15px;">
                                            <?php if (!$is_completed): ?>
                                                <button type="button" onclick="completeTodoTask(<?php echo $row['id']; ?>, this)" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s;">
                                                    <i class="fa fa-check"></i> Mark Complete
                                                </button>
                                            <?php else: ?>
                                                <span style="padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; display: inline-flex; align-items: center; gap: 5px;">
                                                    <i class="fa fa-check-circle"></i> Completed
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 60px 40px; color: #94a3b8;">
                                        <i class="fa fa-check-square-o" style="font-size: 42px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                                        <h4 style="color: #64748b; font-weight: 700; margin-bottom: 5px;">No Tasks Assigned</h4>
                                        <p style="font-size: 13px; font-weight: 500;">You're all caught up! There are no pending tasks.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Todo Modal -->
<div id="addTodoModal" class="modal fade" tabindex="-1" role="dialog" style="z-index: 99999;">
    <div class="modal-dialog" role="document" style="max-width: 500px; margin: 50px auto;">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); background: #fff;">
            <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background: #ffeaeb; border-radius: 20px 20px 0 0; position: relative;">
                <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                    <div style="width: 38px; height: 38px; background: #dd2127; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(221, 33, 39, 0.25);">
                        <i class="fa fa-tasks" style="color: #fff; font-size: 15px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 17px; margin: 0;">Add Personal Task</h5>
                        <p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b; font-weight: 600;">Create a new item in your personal to-do list</p>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="modal-body" style="padding: 25px; background: #fff;">
                <form id="add-todo-form">
                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px; display: block;">Task Name <span style="color:#dd2127;">*</span></label>
                        <input type="text" name="task_name" required class="p-input-premium" placeholder="What do you need to do?">
                    </div>
                    <div style="margin-bottom: 18px;">
                        <label style="font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px; display: block;">Due Date</label>
                        <input type="date" name="due_date" class="p-input-premium">
                    </div>
                    <div style="margin-bottom: 24px;">
                        <label style="font-weight: 700; color: #475569; font-size: 13px; margin-bottom: 8px; display: block;">Priority</label>
                        <select name="priority" class="p-input-premium">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px;">
                        <button type="button" data-dismiss="modal" class="btn-premium-cancel">Cancel</button>
                        <button type="submit" id="btn-save-todo" class="btn-premium-add">Save Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#add-todo-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#btn-save-todo');
            submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: 'ajax_add_todo.php',
                method: 'POST',
                data: form.serialize(),
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.status === 'success') {
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                            submitBtn.prop('disabled', false).text('Save Task');
                        }
                    } catch (err) {
                        alert('Server Error occurred.');
                        submitBtn.prop('disabled', false).text('Save Task');
                    }
                },
                error: function() {
                    alert('Network error occurred.');
                    submitBtn.prop('disabled', false).text('Save Task');
                }
            });
        });
    });

    function completeTodoTask(taskId, btn) {
        const $btn = $(btn);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Completing...');
        $.ajax({
            url: 'ajax_toggle_todo.php',
            type: 'POST',
            data: {
                task_id: taskId
            },
            dataType: 'json',
            success: function(r) {
                if (r.success) {
                    location.reload();
                } else {
                    alert(r.message || 'Failed to complete task.');
                    $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Mark Complete');
                }
            },
            error: function() {
                alert('Network error while completing task.');
                $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Mark Complete');
            }
        });
    }

    /* ===== EMPLOYEE TASK DETAIL POPUP MODAL (READ-ONLY TITLE/DATE/PRIORITY/DESC) ===== */
    let _empModalTaskId = null;
    let _empModalStatus = 0;

    function openEmpTaskDetail(taskId) {
        _empModalTaskId = taskId;
        $('#emp-td-title').val('');
        $('#emp-td-description').val('');
        $('#emp-td-due-date-display').text('--').css('color', '#94a3b8');
        $('#emp-td-priority-display').text('--').css('color', '#94a3b8');
        $('#emp-td-activity').html('<div style="text-align:center;padding:20px;color:#94a3b8;"><i class="fa fa-circle-o-notch fa-spin"></i></div>');
        $('#emp-td-check-circle').css({
            'background': 'transparent',
            'border-color': '#cbd5e1',
            'color': 'transparent'
        });

        $('#empTaskDetailOverlay').fadeIn(200);
        $('body').css('overflow', 'hidden');

        $.ajax({
            url: '../admin_area/ajax/projects/ajax_get_todo_detail.php',
            method: 'POST',
            data: {
                task_id: taskId
            },
            success: function(res) {
                if (!res || !res.success) return;
                const t = res.task;
                _empModalStatus = parseInt(t.status);

                $('#emp-td-title').val(t.task_name);
                $('#emp-td-description').val(t.description || 'No description provided');
                $('#emp-td-project-name').text(t.project_name || 'Personal / General Task');

                // Due date formatting
                if (t.due_date && t.due_date !== '0000-00-00' && t.due_date !== '0000-00-00 00:00:00') {
                    const d = new Date(t.due_date.replace(/-/g, '/'));
                    if (!isNaN(d.getTime())) {
                        const dateStr = d.toLocaleDateString('en-GB', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        });
                        $('#emp-td-due-date-display').text(dateStr).css('color', '#334155');
                    } else {
                        $('#emp-td-due-date-display').text(t.due_date).css('color', '#334155');
                    }
                } else {
                    $('#emp-td-due-date-display').text('No due date').css('color', '#94a3b8');
                }

                // Priority formatting
                if (t.priority) {
                    const prioColor = t.priority.toLowerCase() === 'high' ? '#dc2626' : (t.priority.toLowerCase() === 'medium' ? '#2563eb' : '#059669');
                    $('#emp-td-priority-display').text(t.priority).css('color', prioColor);
                } else {
                    $('#emp-td-priority-display').text('Medium').css('color', '#2563eb');
                }

                // Status checkmark
                if (_empModalStatus === 1) {
                    $('#emp-td-check-circle').css({
                        'background': '#10b981',
                        'border-color': '#10b981',
                        'color': '#ffffff'
                    });
                } else {
                    $('#emp-td-check-circle').css({
                        'background': 'transparent',
                        'border-color': '#cbd5e1',
                        'color': 'transparent'
                    });
                }

                renderEmpTdActivity(res.comments || [], t);
            }
        });
    }

    function closeEmpTaskDetail() {
        $('#empTaskDetailOverlay').fadeOut(180);
        $('body').css('overflow', '');
        _empModalTaskId = null;
    }

    function empTdToggleStatus() {
        if (!_empModalTaskId) return;
        const newStatus = _empModalStatus === 1 ? 0 : 1;
        $.ajax({
            url: '../admin_area/ajax/projects/ajax_toggle_team_todo.php',
            method: 'POST',
            data: {
                task_id: _empModalTaskId,
                status: newStatus
            },
            dataType: 'json',
            success: function(res) {
                if (res && res.success) {
                    _empModalStatus = newStatus;
                    if (newStatus === 1) {
                        $('#emp-td-check-circle').css({
                            'background': '#10b981',
                            'border-color': '#10b981',
                            'color': '#ffffff'
                        });
                    } else {
                        $('#emp-td-check-circle').css({
                            'background': 'transparent',
                            'border-color': '#cbd5e1',
                            'color': 'transparent'
                        });
                    }
                    if (typeof location !== 'undefined') {
                        setTimeout(() => location.reload(), 400);
                    }
                } else {
                    alert(res ? res.message : 'Failed to update status.');
                }
            },
            error: function() {
                alert('Network error while toggling task status.');
            }
        });
    }

    function empTdSubmitComment() {
        const comment = $('#emp-td-comment-input').val().trim();
        if (!comment || !_empModalTaskId) return;
        const btn = $('#emp-td-comment-save');
        btn.prop('disabled', true).text('Saving...');
        $.ajax({
            url: '../admin_area/ajax/projects/ajax_add_todo_comment.php',
            method: 'POST',
            dataType: 'json',
            data: {
                task_id: _empModalTaskId,
                comment: comment,
                posted_by: 'employee'
            },
            success: function(res) {
                btn.prop('disabled', false).text('Save');
                if (typeof res === 'string') {
                    try {
                        res = JSON.parse(res);
                    } catch (e) {}
                }
                if (res && res.success) {
                    $('#emp-td-comment-input').val('');
                    $('#emp-td-comment-actions').hide();
                    $.ajax({
                        url: '../admin_area/ajax/projects/ajax_get_todo_detail.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            task_id: _empModalTaskId
                        },
                        success: function(r) {
                            if (typeof r === 'string') {
                                try {
                                    r = JSON.parse(r);
                                } catch (e) {}
                            }
                            if (r && r.success) renderEmpTdActivity(r.comments || [], r.task);
                        }
                    });
                } else {
                    alert(res && res.message ? res.message : 'Could not save comment.');
                }
            },
            error: function(xhr, status, err) {
                btn.prop('disabled', false).text('Save');
                alert('Server error while saving comment.');
            }
        });
    }

    function renderEmpTdActivity(comments, task) {
        const list = $('#emp-td-activity');
        list.empty();
        comments.forEach(c => {
            const author = c.author_name || c.admin_name || c.comment_author_emp_name || 'User';
            const init = author.charAt(0).toUpperCase();
            const empTag = c.emp_name ?
                `<span style="display:inline-block; background:#ffeaeb; color:#dd2127; font-size:10px; font-weight:700; border-radius:4px; padding:1px 7px; margin-left:8px; vertical-align:middle;">${escapeHtmlEmp(c.emp_name)}</span>` :
                '';
            list.append(`
                <div class="td-act-item" style="display:flex; gap:12px; margin-bottom:14px;">
                    <div style="width:32px; height:32px; border-radius:50%; background:#dd2127; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0;">${escapeHtmlEmp(init)}</div>
                    <div style="flex:1; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <strong style="font-size:13px; color:#0f172a;">${escapeHtmlEmp(author)}</strong>${empTag}
                            <small style="font-size:11px; color:#94a3b8;">${empTdTimeAgo(c.created_at)}</small>
                        </div>
                        <div style="font-size:13px; color:#334155; line-height:1.4;">${escapeHtmlEmp(c.comment)}</div>
                    </div>
                </div>
            `);
        });

        if (task.created_at) {
            list.append(`
                <div style="font-size:12px; color:#64748b; padding:8px 0; border-top:1px solid #f1f5f9; display:flex; align-items:center; gap:8px;">
                    <i class="fa fa-plus-circle" style="color:#dd2127;"></i> Task created &nbsp;<small style="color:#94a3b8;">${empTdTimeAgo(task.created_at)}</small>
                </div>
            `);
        }

        if (comments.length === 0 && !task.created_at) {
            list.html('<div style="color:#94a3b8;font-size:13px;text-align:center;padding:12px;">No activity yet</div>');
        }
    }

    function empTdTimeAgo(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(/-/g, '/'));
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function escapeHtmlEmp(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return String(unsafe).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
</script>

<!-- ===== EMPLOYEE TASK DETAIL MODAL HTML ===== -->
<div id="empTaskDetailOverlay" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); overflow-y: auto; padding: 40px 16px;">
    <div style="background: #ffffff; border-radius: 20px; max-width: 860px; width: 100%; margin: 0 auto; padding: 24px 28px; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.25); position: relative; box-sizing: border-box;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #475569;">
                <i class="fa fa-briefcase" style="color: #dd2127;"></i> <span id="emp-td-project-name">Project</span>
            </div>
            <button type="button" onclick="closeEmpTaskDetail()" style="width: 32px; height: 32px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; font-size: 15px; cursor: pointer;" title="Close"><i class="fa fa-times"></i></button>
        </div>

        <div style="display: flex; gap: 28px; flex-wrap: wrap;">
            <!-- Left Side: Read-Only Info -->
            <div style="flex: 1; min-width: 320px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px;">
                    <div id="emp-td-check-circle" onclick="empTdToggleStatus()" style="width: 24px; height: 24px; border-radius: 50%; border: 2px solid #cbd5e1; cursor: pointer; display: flex; align-items: center; justify-content: center; color: transparent; transition: 0.2s;" title="Toggle Mark Complete">
                        <i class="fa fa-check" style="font-size: 12px;"></i>
                    </div>
                    <input type="text" id="emp-td-title" readonly style="flex: 1; font-size: 18px; font-weight: 800; color: #0f172a; border: none; background: transparent; pointer-events: none; outline: none;">
                </div>

                <div style="display: flex; gap: 16px; margin-bottom: 20px; background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div>
                        <label style="display: block; font-size: 10px; font-weight: 800; color: #94a3b8; letter-spacing: 0.5px;">DUE DATE</label>
                        <div id="emp-td-due-date-display" style="font-size: 13px; font-weight: 700; color: #334155; margin-top: 2px;">--</div>
                    </div>
                    <div style="border-left: 1px solid #e2e8f0; padding-left: 16px;">
                        <label style="display: block; font-size: 10px; font-weight: 800; color: #94a3b8; letter-spacing: 0.5px;">PRIORITY</label>
                        <div id="emp-td-priority-display" style="font-size: 13px; font-weight: 700; color: #dc2626; margin-top: 2px;">--</div>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 8px; letter-spacing: 0.5px;"><i class="fa fa-align-left"></i> Description</label>
                    <textarea id="emp-td-description" readonly rows="5" style="width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; font-size: 13px; color: #334155; background: #f8fafc; outline: none; resize: vertical;"></textarea>
                </div>
            </div>

            <!-- Right Side: Comment & Activity Stream -->
            <div style="flex: 1; min-width: 300px; border-left: 1px solid #f1f5f9; padding-left: 24px;">
                <h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 800; color: #0f172a;"><i class="fa fa-comments-o"></i> Comments & Activity</h4>

                <div style="margin-bottom: 18px;">
                    <textarea id="emp-td-comment-input" rows="2" placeholder="Write a comment..." style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px; font-size: 13px; outline: none;" onfocus="document.getElementById('emp-td-comment-actions').style.display='flex';" onkeydown="if(event.ctrlKey && event.key==='Enter'){empTdSubmitComment();}"></textarea>
                    <div id="emp-td-comment-actions" style="display: none; justify-content: space-between; align-items: center; margin-top: 8px;">
                        <span style="font-size: 11px; color: #94a3b8;">Ctrl + Enter to post</span>
                        <button type="button" id="emp-td-comment-save" onclick="empTdSubmitComment()" style="background: #dd2127; color: #fff; border: none; border-radius: 8px; padding: 6px 16px; font-size: 12px; font-weight: 700; cursor: pointer;">Save</button>
                    </div>
                </div>

                <div id="emp-td-activity" style="max-height: 280px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>