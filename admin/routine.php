<?php
/**
 * Admin - Weekly Routine Management
 */

require_once __DIR__ . '/../includes/config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Weekly Class Routine';
$pageCss = ['admin.css', 'admin-routine.css'];
$isAdmin = true;

try {
    $db = Database::getInstance();
    $adminBatchId = Authorization::getAdminBatchId();
    $stmt = $db->query("SELECT id, name, label FROM batches WHERE is_active = 1 ORDER BY FIELD(name, 'First year', 'Second year', 'Third Year', 'Fourth year'), name");
    $batches = $stmt->fetchAll();
    $courses = [];
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $batchId = $adminBatchId ?? (int)($_POST['batch_id'] ?? 0);
                $batchCheck = $db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
                $batchCheck->execute([$batchId]);
                if (!$batchId || !$batchCheck->fetchColumn()) {
                    throw new RuntimeException('Select an active batch for this routine.');
                }
                $course_id = (int)$_POST['course_id'];
                $day_of_week = $_POST['day_of_week'];
                $class_code = $_POST['class_code'];
                $class_title = $_POST['class_title'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $room = $_POST['room'] ?? '';
                $instructor = $_POST['instructor'];
                
                $stmt = $db->prepare("INSERT INTO class_routines (batch_id, course_id, day_of_week, class_code, class_title, start_time, end_time, room, instructor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$batchId, $course_id, $day_of_week, $class_code, $class_title, $start_time, $end_time, $room, $instructor]);
                
                Session::setFlash('success', 'Routine class added successfully.');
                header("Location: " . url('admin/routine.php'));
                exit;
            } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                $batchId = $adminBatchId ?? (int)($_POST['batch_id'] ?? 0);
                $id = (int)$_POST['id'];
                $course_id = (int)$_POST['course_id'];
                $day_of_week = $_POST['day_of_week'];
                $class_code = $_POST['class_code'];
                $class_title = $_POST['class_title'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $room = $_POST['room'] ?? '';
                $instructor = $_POST['instructor'];
                
                $batchSql = $adminBatchId === null ? '' : ' AND batch_id = ?';
                $params = [$course_id, $day_of_week, $class_code, $class_title, $start_time, $end_time, $room, $instructor, $batchId, $id];
                if ($adminBatchId !== null) {
                    $params[] = $adminBatchId;
                }
                $stmt = $db->prepare("UPDATE class_routines SET course_id=?, day_of_week=?, class_code=?, class_title=?, start_time=?, end_time=?, room=?, instructor=?, batch_id=? WHERE id=?" . $batchSql);
                $stmt->execute($params);
                
                Session::setFlash('success', 'Routine class updated successfully.');
                header("Location: " . url('admin/routine.php'));
                exit;
            } elseif ($_POST['action'] === 'set_occurrence_status') {
                if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                    throw new RuntimeException('Invalid security token. Please reload the page and try again.');
                }

                $routineId = (int)($_POST['routine_id'] ?? 0);
                $classDate = trim($_POST['class_date'] ?? '');
                $status = $_POST['status'] ?? '';
                if ($routineId < 1 || !in_array($status, ['scheduled', 'completed', 'cancelled'], true)) {
                    throw new RuntimeException('Select a valid routine and class status.');
                }

                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $classDate);
                if (!$date || $date->format('Y-m-d') !== $classDate || $classDate < date('Y-m-d')) {
                    throw new RuntimeException('Select a valid class date that is not in the past.');
                }

                [$routineBatchSql, $routineBatchParams] = Authorization::adminBatchFilter('cr');
                $stmt = $db->prepare("SELECT cr.day_of_week FROM class_routines cr WHERE cr.id = ?" . $routineBatchSql);
                $stmt->execute(array_merge([$routineId], $routineBatchParams));
                $routineDay = $stmt->fetchColumn();
                if (!$routineDay) {
                    throw new RuntimeException('Routine class not found or you do not have permission to manage it.');
                }
                if ($date->format('l') !== $routineDay) {
                    throw new RuntimeException('The selected date must match this routine class day.');
                }

                $stmt = $db->prepare("
                    INSERT INTO class_routine_occurrences (routine_id, class_date, status, updated_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), updated_by = VALUES(updated_by)
                ");
                $stmt->execute([$routineId, $classDate, $status, Session::get('user_id')]);

                Session::setFlash('success', 'Class occurrence status updated.');
                header("Location: " . url('admin/routine.php'));
                exit;
            } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                [$batchSql, $batchParams] = Authorization::adminBatchFilter();
                $stmt = $db->prepare("DELETE FROM class_routines WHERE id = ?" . $batchSql);
                $stmt->execute(array_merge([$id], $batchParams));
                
                Session::setFlash('success', 'Routine class deleted successfully.');
                header("Location: " . url('admin/routine.php'));
                exit;
            }
        }
    }
    
    // Fetch courses for the dropdown before routine data so both remain independent.
    $stmt = $db->query("SELECT id, course_code, course_title FROM courses ORDER BY course_title ASC");
    $courses = $stmt->fetchAll();

    // Fetch all routine classes
    [$batchSql, $batchParams] = Authorization::adminBatchFilter('cr');
    $stmt = $db->prepare("
        SELECT cr.*, c.course_title AS routine_course_title
        FROM class_routines cr 
        JOIN courses c ON cr.course_id = c.id 
        WHERE 1=1" . $batchSql . "
        ORDER BY 
            FIELD(cr.day_of_week, 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            cr.start_time ASC
    ");
    $stmt->execute($batchParams);
    $routines = $stmt->fetchAll();
    
} catch (Throwable $e) {
    error_log("Routine management error: " . $e->getMessage());
    $isRoutineMigrationMissing = $e instanceof PDOException
        && $e->getCode() === '42S02'
        && (strpos($e->getMessage(), 'class_routines') !== false || strpos($e->getMessage(), 'class_routine_occurrences') !== false);
    $message = $e instanceof RuntimeException
        ? $e->getMessage()
        : ($isRoutineMigrationMissing
            ? 'Routine tables are missing. Run the routine installer as an admin, then reload this page.'
            : 'An error occurred while managing routines.');
    Session::setFlash('error', $message);
    $routines = [];
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="admin-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4" style="display:flex; justify-content:space-between; align-items:center;">
        <h2><i class="fas fa-calendar-alt text-primary"></i> Weekly Class Routine</h2>
        <button class="btn btn-primary" onclick="document.getElementById('addRoutineModal').style.display='flex'">
            <i class="fas fa-plus"></i> Add Routine Class
        </button>
    </div>

    <!-- Routine Display Grouped by Day -->
    <?php
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $groupedRoutines = [];
    foreach ($routines as $r) {
        $groupedRoutines[$r['day_of_week']][] = $r;
    }
    ?>

    <div class="routine-grid" style="display: grid; gap: 20px;">
        <?php foreach ($days as $day): ?>
            <div class="card">
                <div class="card-header bg-light">
                    <h3 class="card-title"><i class="fas fa-calendar-day text-info"></i> <?php echo $day; ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($groupedRoutines[$day])): ?>
                        <p class="text-muted text-center py-3">No classes scheduled for <?php echo $day; ?>.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Course</th>
                                        <th>Class Info</th>
                                        <th>Instructor</th>
                                        <th>Room</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groupedRoutines[$day] as $r): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-primary"><?php echo date('h:i A', strtotime($r['start_time'])); ?> - <?php echo date('h:i A', strtotime($r['end_time'])); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($r['routine_course_title']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($r['class_title']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($r['class_code']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($r['instructor']); ?></td>
                                            <td><?php echo htmlspecialchars($r['room']); ?></td>
                                            <td>
                                                <?php
                                                $nextRoutineDate = new DateTimeImmutable('today');
                                                while ($nextRoutineDate->format('l') !== $r['day_of_week']) {
                                                    $nextRoutineDate = $nextRoutineDate->modify('+1 day');
                                                }
                                                ?>
                                                <form method="POST" class="routine-status-form">
                                                    <?php echo Security::csrfField(); ?>
                                                    <input type="hidden" name="action" value="set_occurrence_status">
                                                    <input type="hidden" name="routine_id" value="<?php echo (int)$r['id']; ?>">
                                                    <input type="date" name="class_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $nextRoutineDate->format('Y-m-d'); ?>" aria-label="Class date" required>
                                                    <select name="status" aria-label="Class status">
                                                        <option value="scheduled">Scheduled</option>
                                                        <option value="completed">Completed</option>
                                                        <option value="cancelled">Cancelled</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">Set Status</button>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this class?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Routine Modal -->
<div id="addRoutineModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="modal-content" style="background:#fff; border-radius:10px; width:90%; max-width:600px; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <div class="modal-header d-flex justify-content-between align-items-center mb-4" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h3 class="m-0">Add Weekly Class</h3>
            <button type="button" class="btn btn-sm btn-outline" style="border:none; font-size:1.5rem; background:transparent;" onclick="document.getElementById('addRoutineModal').style.display='none'">
                &times;
            </button>
        </div>
        <form method="POST">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="add">

            <?php if ($adminBatchId === null): ?>
                <div class="form-group mb-3">
                    <label>Batch</label>
                    <select name="batch_id" class="form-control" required>
                        <option value="">Select Batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo (int)$batch['id']; ?>"><?php echo htmlspecialchars($batch['label'] ?: 'Batch ' . $batch['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="batch_id" value="<?php echo $adminBatchId; ?>">
            <?php endif; ?>
            
            <div class="form-group mb-3" style="margin-bottom:1rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Day of Week</label>
                <select name="day_of_week" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" required>
                    <option value="">Select Day</option>
                    <option value="Sunday">Sunday</option>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>
            </div>
            
            <div class="form-group mb-3" style="margin-bottom:1rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Course</label>
                <select name="course_id" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['course_title'] . ' (' . $c['course_code'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row" style="display:flex; gap:15px; margin-bottom:1rem;">
                <div class="col-md-6 form-group" style="flex:1;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Class Title</label>
                    <input type="text" name="class_title" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" required placeholder="e.g. Chapter 1: Intro">
                </div>
                <div class="col-md-6 form-group" style="flex:1;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Class Code</label>
                    <input type="text" name="class_code" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" required placeholder="e.g. PHY101-01">
                </div>
            </div>
            
            <div class="row" style="display:flex; gap:15px; margin-bottom:1rem;">
                <div class="col-md-6 form-group" style="flex:1;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Start Time</label>
                    <input type="time" name="start_time" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" required>
                </div>
                <div class="col-md-6 form-group" style="flex:1;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">End Time</label>
                    <input type="time" name="end_time" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" required>
                </div>
            </div>
            
            <div class="row" style="display:flex; gap:15px; margin-bottom:1rem;">
                <div class="col-md-6 form-group" style="flex:1;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Room (Optional)</label>
                    <input type="text" name="room" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" placeholder="e.g. Room 301">
                </div>
                <div class="col-md-6 form-group" style="flex:1;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Instructor</label>
                    <input type="text" name="instructor" class="form-control" style="width:100%; padding:0.5rem; border:1px solid #ccc; border-radius:5px;" required>
                </div>
            </div>
            
            <div class="form-actions" style="text-align:right; margin-top:20px;">
                <button type="button" class="btn btn-outline" style="padding:0.5rem 1rem; margin-right:10px; border-radius:5px; border:1px solid #ccc; background:transparent;" onclick="document.getElementById('addRoutineModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding:0.5rem 1rem; border-radius:5px; background:var(--primary); color:white; border:none;">Save Class</button>
            </div>
        </form>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>
