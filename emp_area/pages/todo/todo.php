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

$filter_date = isset($_GET['date']) ? mysqli_real_escape_string($con, $_GET['date']) : date('Y-m-d');

// Fetch todos assigned to this employee
// Pending tasks (status=0) show up first
// Completed tasks (status=1) show up at the bottom of the table, filtered by selected date
$query = "SELECT t.*, p.project_name, c.name as client_name 
          FROM project_team_todos t 
          LEFT JOIN client_projects p ON t.project_id = p.id 
          LEFT JOIN clients c ON p.client_id = c.id
          WHERE t.emp_id = $emp_id 
          AND (t.status = 0 OR (t.status = 1 AND DATE(COALESCE(t.due_date, t.created_at)) = '$filter_date'))
          ORDER BY t.status ASC, CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END ASC, t.due_date ASC, t.id DESC";
$result = mysqli_query($con, $query);

?>

<div class="premium-ui-enabled">
    <div class="row">
        <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; margin-bottom: 0px;">
            <h1></h1>
            <div class="header-actions-premium" style="display: flex; gap: 16px; align-items: center;">
                <div style="position: relative; display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 13px; font-weight: 700; color: #64748b; margin: 0;">Completed On:</label>
                    <input type="date" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="window.location.href='index.php?todo&date=' + this.value" style="padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; font-weight: 600; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                </div>
                <button data-toggle="modal" data-target="#addTodoModal" class="btn-premium-add">
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
                                            <div style="font-weight: 700; color: <?php echo $is_completed ? '#64748b' : '#1e293b'; ?>; font-size: 14px; text-align: left; <?php echo $is_completed ? 'text-decoration: line-through;' : ''; ?>">
                                                <?php echo htmlspecialchars($row['task_name']); ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="font-weight: 700; color: #334155; font-size: 13px;">
                                                <?php echo $proj_name; ?>
                                            </div>
                                            <?php if (!empty($row['client_name'])) : ?>
                                                <div style="font-size: 11px; color: #94a3b8; font-weight: 600; margin-top: 2px;">
                                                    <i class="fa fa-user"></i> <?php echo htmlspecialchars($row['client_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight: 600; color: #475569; font-size: 13px; text-align: center;">
                                            <?php echo !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : '--'; ?>
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
</script>