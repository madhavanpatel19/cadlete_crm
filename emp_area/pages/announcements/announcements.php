<?php
// =============================================================
// emp_area/pages/announcements/announcements.php
// List all announcements
// =============================================================
if (!isset($_SESSION['emp_id'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit();
}

$emp_id = $_SESSION['emp_id'];

// Get all announcements
$query = "SELECT * FROM announcements WHERE is_active = 1 AND (publish_date IS NULL OR publish_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY publish_date DESC";
$result = mysqli_query($con, $query);
?>
<div class="premium-ui-enabled">
    <div class="row">
        <div class="col-lg-12">
            <div class="premium-card">
                <div class="card-hdr">
                    <i class="fa fa-list"></i>
                    <h3>All Announcements</h3>
                </div>
                <div class="table-responsive">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">#</th>
                                <th>Title</th>
                                <th style="text-align: center;">Posted On</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0) : $i = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)) :
                                    $ann_id = $row['id'];
                                    $check_read = "SELECT id FROM announcement_read WHERE announcement_id='$ann_id' AND emp_id='$emp_id'";
                                    $run_check = mysqli_query($con, $check_read);
                                    $is_unread = (mysqli_num_rows($run_check) == 0);
                                ?>
                                    <tr style="<?php echo $is_unread ? 'background: #fff8f8;' : ''; ?>">
                                        <td style="text-align: center; font-weight: 700; color: #64748b;"><?php echo $i++; ?></td>
                                        <td style="font-weight: 600; color: #1e293b;">
                                            <?php echo htmlspecialchars($row['title']); ?>
                                            <?php if ($is_unread) echo '<span class="p-badge p-badge-danger" style="margin-left:8px;">New</span>'; ?>
                                        </td>
                                        <td style="font-weight: 600; color: #64748b; text-align: center;"><?php echo date('d M Y, h:i A', strtotime($row['publish_date'] ?? $row['created_at'])); ?></td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; justify-content: center;">
                                                <button type="button" class="btn-icon-premium btn-icon-view view-announcement-btn"
                                                    data-id="<?php echo $ann_id; ?>"
                                                    data-title="<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>"
                                                    data-date="<?php echo date('d M Y, h:i A', strtotime($row['publish_date'] ?? $row['created_at'])); ?>"
                                                    data-message="<?php echo htmlspecialchars($row['message'], ENT_QUOTES); ?>"
                                                    title="View Announcement">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fa fa-folder-open-o" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>
                                        No announcements found.
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

<!-- Modal Pop-Up for Announcement Details -->
<div class="modal fade" id="announcementModal" tabindex="-1" role="dialog" style="z-index: 99999;">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px; width: 90%; margin: 0 auto; display: flex; align-items: center; min-height: calc(100vh - 60px);">
        <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%;">
            <div class="modal-header" style="background: #ffeaeb; color: #0f172a; padding: 22px 30px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 14px; width: calc(100% - 40px);">
                    <div style="width: 40px; height: 40px; background: #dd2127; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fa fa-bullhorn" style="color: #fff; font-size: 16px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" id="announcementModalTitle" style="font-weight: 800; color: #0f172a; font-size: 18px; margin: 0; line-height: 1.3;">Announcement Detail</h5>
                        <div id="announcementModalDate" style="font-size: 12px; color: #64748b; font-weight: 600; margin-top: 3px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-body" style="padding: 30px; background: #fff;">
                <div id="announcementModalMessage" style="font-size: 15px; line-height: 1.8; color: #334155; white-space: pre-wrap; font-family: 'Inter', sans-serif;"></div>
            </div>
            <div class="modal-footer" style="padding: 16px 30px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                <button type="button" class="btn-premium-cancel" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('.view-announcement-btn').on('click', function() {
            const $btn = $(this);
            const annId = $btn.data('id');
            const title = $btn.data('title');
            const date = $btn.data('date');
            const message = $btn.data('message');

            $('#announcementModalTitle').text(title);
            $('#announcementModalDate').html('<i class="fa fa-clock-o"></i> Posted on: ' + date);
            $('#announcementModalMessage').text(message);

            $('#announcementModal').modal('show');

            // Mark as read via AJAX
            $.ajax({
                url: 'ajax_mark_announcement_read.php',
                type: 'POST',
                data: {
                    announcement_id: annId
                },
                success: function() {
                    const $row = $btn.closest('tr');
                    $row.css('background', '');
                    $row.find('.p-badge-danger').fadeOut();
                }
            });
        });
    });
</script>