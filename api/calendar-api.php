<?php
/**
 * Calendar API - combines existing schedules with managed academic events.
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

header('Content-Type: application/json; charset=utf-8');

function calendarRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function calendarDateTime(string $value, bool $required = true): ?string
{
    if ($value === '' && !$required) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))
        || $date->format('Y-m-d\TH:i') !== $value) {
        throw new InvalidArgumentException('Enter a valid start and end date/time.');
    }

    return $date->format('Y-m-d H:i:s');
}

function calendarInputString(array $input, string $key, int $maxLength): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Check the text fields and try again.');
    }

    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $maxLength) {
        throw new InvalidArgumentException('One of the text fields is too long.');
    }
    return $value;
}

function calendarEvent(array $row, string $source, string $type): array
{
    return [
        'id' => (int)$row['id'],
        'source' => $source,
        'type' => $type,
        'title' => $row['title'],
        'description' => $row['description'] ?? '',
        'start' => $row['start_datetime'],
        'end' => $row['end_datetime'] ?? null,
        'location' => $row['location'] ?? '',
        'allDay' => (bool)($row['is_all_day'] ?? false),
        'status' => $row['status'] ?? 'scheduled',
        'batchId' => isset($row['batch_id']) ? (int)$row['batch_id'] : null,
        'courseId' => isset($row['course_id']) ? (int)$row['course_id'] : null,
        'course' => $row['course_title'] ?? '',
        'courseCode' => $row['course_code'] ?? '',
        'url' => $row['url'] ?? '',
        'editable' => false
    ];
}

try {
    $db = Database::getInstance();
    $userType = Session::get('user_type');
    $userId = (int)Session::get('user_id');
    $isAdmin = $userType === 'admin';
    $isStudent = $userType === 'student';
    if (!$isAdmin && !$isStudent) {
        calendarRespond(['success' => false, 'message' => 'You are not allowed to use the calendar.'], 403);
    }
    $adminBatchId = $isAdmin ? Authorization::getAdminBatchId() : null;
    $studentBatchId = $isStudent ? (int)(Authorization::getStudentBatchId() ?? 0) : 0;
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'list') {
        $start = $_GET['start'] ?? '';
        $end = $_GET['end'] ?? '';
        if (!is_string($start) || !is_string($end)) {
            calendarRespond(['success' => false, 'message' => 'Select a valid calendar range.'], 400);
        }
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (!$startDate || !$endDate || $endDate < $startDate
            || $endDate->diff($startDate)->days > 370) {
            calendarRespond(['success' => false, 'message' => 'Select a valid calendar range of up to one year.'], 400);
        }

        $events = [];
        $requestedBatch = $_GET['batch_id'] ?? null;
        if ($requestedBatch !== null && $requestedBatch !== '') {
            $requestedBatch = filter_var($requestedBatch, FILTER_VALIDATE_INT);
            if ($requestedBatch === false || $requestedBatch < 1) {
                calendarRespond(['success' => false, 'message' => 'Select a valid batch.'], 400);
            }
        }
        $batchFilter = $isStudent ? $studentBatchId : $requestedBatch;
        if ($isAdmin && $adminBatchId !== null) {
            $batchFilter = $adminBatchId;
        }

        $academicSql = "
            SELECT e.*, c.course_title, c.course_code
            FROM academic_events e
            LEFT JOIN courses c ON c.id = e.course_id
            WHERE e.start_datetime < DATE_ADD(?, INTERVAL 1 DAY)
              AND (e.end_datetime IS NULL OR e.end_datetime >= ?)
        ";
        $academicParams = [$endDate->format('Y-m-d'), $startDate->format('Y-m-d')];
        if ($isStudent) {
            $academicSql .= " AND (e.visibility = 'all' OR e.batch_id = ?)";
            $academicParams[] = $studentBatchId;
        } elseif ($isAdmin && $adminBatchId !== null) {
            $academicSql .= " AND (e.visibility = 'all' OR e.batch_id = ?)";
            $academicParams[] = $adminBatchId;
        } elseif ($isAdmin && $batchFilter !== null && $batchFilter > 0) {
            $academicSql .= " AND (e.visibility = 'all' OR e.batch_id = ?)";
            $academicParams[] = $batchFilter;
        }
        $academicSql .= " ORDER BY e.start_datetime LIMIT 1000";
        $stmt = $db->prepare($academicSql);
        $stmt->execute($academicParams);
        while ($row = $stmt->fetch()) {
            $event = calendarEvent($row, 'academic', $row['event_type']);
            $event['editable'] = $isAdmin && ($adminBatchId === null
                || (int)$row['batch_id'] === $adminBatchId);
            $events[] = $event;
        }

        $classParams = [$endDate->format('Y-m-d'), $startDate->format('Y-m-d')];
        $classSql = "
            SELECT c.id, c.batch_id, c.class_title AS title, c.description,
                   CONCAT(c.class_date, ' ', c.start_time) AS start_datetime,
                   CONCAT(c.class_date, ' ', c.end_time) AS end_datetime,
                   COALESCE(NULLIF(c.venue, ''), c.room, '') AS location,
                   c.status, crs.course_title, crs.course_code
            FROM classes c
            JOIN courses crs ON crs.id = c.course_id
            WHERE c.class_date BETWEEN ? AND ?
        ";
        if ($batchFilter !== null) {
            $classSql .= " AND c.batch_id = ?";
            $classParams[] = $batchFilter;
        }
        if ($isStudent) {
            $classSql .= " AND EXISTS (
                SELECT 1 FROM class_enrollments ce
                WHERE ce.class_id = c.id AND ce.student_id = ? AND ce.status = 'active'
            )";
            $classParams[] = $userId;
        }
        $classSql .= " ORDER BY c.class_date, c.start_time LIMIT 1000";
        $stmt = $db->prepare($classSql);
        $stmt->execute($classParams);
        while ($row = $stmt->fetch()) {
            $event = calendarEvent($row, 'class', 'class');
            $event['url'] = url(($isStudent ? 'student' : 'admin') . '/class-details.php?id=' . (int)$row['id']);
            $events[] = $event;
        }

        $routineSql = "
            SELECT cr.id, cr.batch_id, cr.class_title AS title, '' AS description,
                   cr.start_time, cr.end_time, cr.room AS location, cr.day_of_week,
                   crs.course_title, crs.course_code
            FROM class_routines cr
            JOIN courses crs ON crs.id = cr.course_id
            WHERE 1=1
        ";
        $routineParams = [];
        if ($batchFilter !== null) {
            $routineSql .= " AND cr.batch_id = ?";
            $routineParams[] = $batchFilter;
        }
        $routineSql .= " ORDER BY cr.start_time LIMIT 500";
        $stmt = $db->prepare($routineSql);
        $stmt->execute($routineParams);
        $routines = $stmt->fetchAll();

        $occurrencesSql = "
            SELECT o.routine_id, o.class_date, o.status
            FROM class_routine_occurrences o
            JOIN class_routines cr ON cr.id = o.routine_id
            WHERE o.class_date BETWEEN ? AND ?
        ";
        $occurrencesParams = [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
        if ($batchFilter !== null) {
            $occurrencesSql .= " AND cr.batch_id = ?";
            $occurrencesParams[] = $batchFilter;
        }
        $occurrencesStmt = $db->prepare($occurrencesSql);
        $occurrencesStmt->execute($occurrencesParams);
        $occurrences = [];
        while ($occurrence = $occurrencesStmt->fetch()) {
            $occurrences[$occurrence['routine_id'] . ':' . $occurrence['class_date']] = $occurrence['status'];
        }
        $dateCursor = $startDate;
        while ($dateCursor <= $endDate) {
            $date = $dateCursor->format('Y-m-d');
            $dayName = $dateCursor->format('l');
            foreach ($routines as $routine) {
                if ($routine['day_of_week'] !== $dayName) {
                    continue;
                }
                $occurrenceStatus = $occurrences[$routine['id'] . ':' . $date] ?? null;
                $row = [
                    'id' => (int)$routine['id'] * 10000 + (int)$dateCursor->format('z'),
                    'batch_id' => $routine['batch_id'],
                    'title' => $routine['title'],
                    'description' => '',
                    'start_datetime' => $date . ' ' . $routine['start_time'],
                    'end_datetime' => $date . ' ' . $routine['end_time'],
                    'location' => $routine['location'],
                    'status' => $occurrenceStatus ?: 'scheduled',
                    'course_title' => $routine['course_title'],
                    'course_code' => $routine['course_code']
                ];
                $event = calendarEvent($row, 'routine', 'class');
                $event['url'] = url(($isStudent ? 'student' : 'admin') . '/classes.php');
                $events[] = $event;
            }
            $dateCursor = $dateCursor->modify('+1 day');
        }

        $assignmentSql = "
            SELECT a.id, c.batch_id, a.title, a.description,
                   a.deadline AS start_datetime, a.deadline AS end_datetime,
                   '' AS location, a.status, crs.course_title, crs.course_code
            FROM assignments a
            JOIN classes c ON c.id = a.class_id
            JOIN courses crs ON crs.id = c.course_id
            WHERE a.status IN ('published', 'open')
              AND a.deadline >= ? AND a.deadline < DATE_ADD(?, INTERVAL 1 DAY)
        ";
        $assignmentParams = [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
        if ($batchFilter !== null) {
            $assignmentSql .= " AND c.batch_id = ?";
            $assignmentParams[] = $batchFilter;
        }
        if ($isStudent) {
            $assignmentSql .= " AND EXISTS (
                SELECT 1 FROM class_enrollments ce
                WHERE ce.class_id = c.id AND ce.student_id = ? AND ce.status = 'active'
            )";
            $assignmentParams[] = $userId;
        }
        $assignmentSql .= " ORDER BY a.deadline LIMIT 1000";
        $stmt = $db->prepare($assignmentSql);
        $stmt->execute($assignmentParams);
        while ($row = $stmt->fetch()) {
            $event = calendarEvent($row, 'assignment', 'assignment');
            $event['url'] = url(($isStudent ? 'student' : 'admin') . '/assignment-details.php?id=' . (int)$row['id']);
            $events[] = $event;
        }

        $personalStmt = $db->prepare("
            SELECT p.*, NULL AS batch_id
            FROM personal_events p
            WHERE p.user_type = ? AND p.user_id = ?
              AND p.start_datetime < DATE_ADD(?, INTERVAL 1 DAY)
              AND (p.end_datetime IS NULL OR p.end_datetime >= ?)
            ORDER BY p.start_datetime LIMIT 500
        ");
        $personalStmt->execute([$userType, $userId, $endDate->format('Y-m-d'), $startDate->format('Y-m-d')]);
        while ($row = $personalStmt->fetch()) {
            $event = calendarEvent($row, 'personal', 'personal');
            $event['editable'] = true;
            $event['reminderMinutes'] = $row['reminder_minutes'] === null ? null : (int)$row['reminder_minutes'];
            $events[] = $event;
        }

        usort($events, static function ($left, $right) {
            return strcmp($left['start'], $right['start']);
        });
        $seen = [];
        $events = array_values(array_filter($events, static function ($event) use (&$seen) {
            $key = implode('|', [
                $event['type'],
                $event['title'],
                $event['start'],
                $event['end'] ?? '',
                $event['courseCode']
            ]);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));

        $prefStmt = $db->prepare("
            SELECT default_view, visible_categories, reminder_minutes
            FROM calendar_preferences WHERE user_type = ? AND user_id = ?
        ");
        $prefStmt->execute([$userType, $userId]);
        $preferences = $prefStmt->fetch() ?: [
            'default_view' => 'month',
            'visible_categories' => 'class,assignment,examination,presentation,department,seminar,holiday,academic,personal',
            'reminder_minutes' => 1440
        ];

        calendarRespond(['success' => true, 'events' => $events, 'preferences' => $preferences]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        calendarRespond(['success' => false, 'message' => 'Unsupported request method.'], 405);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !is_string($input['csrf_token'] ?? null)
        || !Security::verifyCSRFToken($input['csrf_token'])) {
        calendarRespond(['success' => false, 'message' => 'Invalid security token. Refresh and try again.'], 403);
    }
    $action = $input['action'] ?? $action;
    if (!is_string($action)) {
        calendarRespond(['success' => false, 'message' => 'Invalid calendar action.'], 400);
    }

    if ($action === 'preferences') {
        $view = $input['default_view'] ?? '';
        $allowedViews = ['month', 'week', 'day', 'agenda'];
        $categories = $input['visible_categories'] ?? [];
        $allowedCategories = ['class', 'assignment', 'examination', 'presentation', 'department', 'seminar', 'holiday', 'academic', 'personal'];
        if (!in_array($view, $allowedViews, true) || !is_array($categories)) {
            calendarRespond(['success' => false, 'message' => 'Invalid calendar preferences.'], 400);
        }
        $categories = array_values(array_intersect(
            array_filter($categories, static function ($category) {
                return is_string($category);
            }),
            $allowedCategories
        ));
        $reminderMinutes = filter_var($input['reminder_minutes'] ?? 1440, FILTER_VALIDATE_INT);
        if ($reminderMinutes === false || $reminderMinutes < 0 || $reminderMinutes > 10080) {
            calendarRespond(['success' => false, 'message' => 'Reminder must be between 0 minutes and 7 days.'], 400);
        }
        $stmt = $db->prepare("
            INSERT INTO calendar_preferences (user_type, user_id, default_view, visible_categories, reminder_minutes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE default_view = VALUES(default_view),
                visible_categories = VALUES(visible_categories), reminder_minutes = VALUES(reminder_minutes)
        ");
        $stmt->execute([$userType, $userId, $view, implode(',', $categories), $reminderMinutes]);
        calendarRespond(['success' => true]);
    }

    if ($action === 'save_event') {
        $rawEventId = $input['id'] ?? 0;
        $eventId = filter_var($rawEventId, FILTER_VALIDATE_INT);
        if ($eventId === false || $eventId < 0) {
            calendarRespond(['success' => false, 'message' => 'Invalid event.'], 400);
        }
        $source = $input['source'] ?? ($isAdmin ? 'academic' : 'personal');
        if ($source === 'academic') {
            if (!$isAdmin) {
                calendarRespond(['success' => false, 'message' => 'Only batch CRs and administrators can manage official events.'], 403);
            }
            $title = calendarInputString($input, 'title', 200);
            $description = calendarInputString($input, 'description', 5000);
            $type = $input['type'] ?? 'department';
            $eventStatus = $input['status'] ?? 'scheduled';
            $allowedTypes = ['examination', 'presentation', 'department', 'seminar', 'holiday', 'academic'];
            $start = calendarDateTime(calendarInputString($input, 'start', 16));
            $end = calendarDateTime(calendarInputString($input, 'end', 16), false);
            $location = calendarInputString($input, 'location', 200);
            $rawBatchId = $input['batch_id'] ?? '';
            $batchId = $adminBatchId;
            if ($adminBatchId === null && $rawBatchId !== '') {
                $batchId = filter_var($rawBatchId, FILTER_VALIDATE_INT);
                if ($batchId === false || $batchId < 1) {
                    calendarRespond(['success' => false, 'message' => 'Select an active batch.'], 400);
                }
            }
            $rawCourseId = $input['course_id'] ?? '';
            $courseId = null;
            if ($rawCourseId !== '') {
                $courseId = filter_var($rawCourseId, FILTER_VALIDATE_INT);
                if ($courseId === false || $courseId < 1) {
                    calendarRespond(['success' => false, 'message' => 'Select a valid course.'], 400);
                }
            }
            $visibility = $batchId === null ? 'all' : 'batch';
            if ($adminBatchId === null && $batchId !== null) {
                $batchCheck = $db->prepare("SELECT id FROM batches WHERE id = ? AND is_active = 1");
                $batchCheck->execute([$batchId]);
                if (!$batchCheck->fetchColumn()) {
                    calendarRespond(['success' => false, 'message' => 'Select an active batch.'], 400);
                }
            }
            if ($courseId !== null) {
                $courseCheck = $db->prepare("SELECT id FROM courses WHERE id = ?");
                $courseCheck->execute([$courseId]);
                if (!$courseCheck->fetchColumn()) {
                    calendarRespond(['success' => false, 'message' => 'Select a valid course.'], 400);
                }
            }
            if ($title === '' || !is_string($type) || !in_array($type, $allowedTypes, true)
                || !in_array($eventStatus, ['scheduled', 'cancelled'], true)
                || ($end !== null && $end < $start)) {
                calendarRespond(['success' => false, 'message' => 'Check the title, category, location, and event times.'], 400);
            }
            $allDay = !empty($input['all_day']) ? 1 : 0;
            if ($eventId > 0) {
                [$scopeSql, $scopeParams] = Authorization::adminBatchFilter();
                $currentStmt = $db->prepare("SELECT id, status, start_datetime, batch_id FROM academic_events WHERE id = ?" . $scopeSql);
                $currentStmt->execute(array_merge([$eventId], $scopeParams));
                $currentEvent = $currentStmt->fetch();
                if (!$currentEvent) {
                    calendarRespond(['success' => false, 'message' => 'Event not found in your batch.'], 404);
                }
                $update = $db->prepare("
                    UPDATE academic_events SET title = ?, description = ?, event_type = ?,
                        start_datetime = ?, end_datetime = ?, location = ?, is_all_day = ?, status = ?,
                        batch_id = ?, course_id = ?, visibility = ?
                    WHERE id = ?" . $scopeSql
                );
                $update->execute(array_merge([
                    $title, $description ?: null, $type, $start, $end, $location ?: null,
                    $allDay, $eventStatus, $batchId, $courseId, $visibility, $eventId
                ], $scopeParams));
                if ($update->rowCount() > 0) {
                    $scheduleChanged = $currentEvent['start_datetime'] !== $start
                        || $currentEvent['status'] !== $eventStatus;
                    if ($scheduleChanged) {
                        $notifier = new NotificationManager();
                        $noticeTitle = $eventStatus === 'cancelled' ? 'Academic event cancelled' : 'Academic schedule updated';
                        $noticeMessage = $eventStatus === 'cancelled'
                            ? $title . ' has been cancelled.'
                            : $title . ' has been rescheduled to ' . date('d M Y, h:i A', strtotime($start)) . '.';
                        if ($batchId === null) {
                            $notifier->notifyStudents($noticeTitle, $noticeMessage, 'warning', url('student/calendar.php'));
                        } else {
                            $notifier->notifyBatchStudents((int)$batchId, $noticeTitle, $noticeMessage, 'warning', url('student/calendar.php'));
                        }
                    }
                }
            } else {
                $insert = $db->prepare("
                    INSERT INTO academic_events
                        (batch_id, course_id, created_by, title, description, event_type, start_datetime,
                         end_datetime, location, is_all_day, visibility, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $batchId, $courseId, $userId, $title, $description ?: null, $type, $start,
                    $end, $location ?: null, $allDay, $visibility, $eventStatus
                ]);
                $eventId = (int)$db->lastInsertId();
                if ($type === 'examination' && $eventStatus === 'scheduled') {
                    $notifier = new NotificationManager();
                    $message = $title . ' is scheduled for ' . date('d M Y, h:i A', strtotime($start)) . '.';
                    if ($batchId === null) {
                        $notifier->notifyStudents('New examination scheduled', $message, 'info', url('student/calendar.php'));
                    } else {
                        $notifier->notifyBatchStudents($batchId, 'New examination scheduled', $message, 'info', url('student/calendar.php'));
                    }
                }
                logActivity('calendar_event_created', "Created calendar event: {$title}");
            }
            calendarRespond(['success' => true, 'id' => $eventId]);
        }

        if ($source === 'personal') {
            $title = calendarInputString($input, 'title', 200);
            $description = calendarInputString($input, 'description', 5000);
            $start = calendarDateTime(calendarInputString($input, 'start', 16));
            $end = calendarDateTime(calendarInputString($input, 'end', 16), false);
            $location = calendarInputString($input, 'location', 200);
            $reminder = $input['reminder_minutes'] ?? '';
            $reminder = $reminder === '' ? null : filter_var($reminder, FILTER_VALIDATE_INT);
            if ($title === '' || ($end !== null && $end < $start)
                || ($reminder !== null && ($reminder === false || $reminder < 0 || $reminder > 10080))) {
                calendarRespond(['success' => false, 'message' => 'Check the title, location, reminder, and event times.'], 400);
            }
            if ($eventId > 0) {
                $stmt = $db->prepare("
                    UPDATE personal_events SET title = ?, description = ?, start_datetime = ?,
                        end_datetime = ?, location = ?, is_all_day = ?, reminder_minutes = ?
                    WHERE id = ? AND user_type = ? AND user_id = ?
                ");
                $stmt->execute([
                    $title, $description ?: null, $start, $end, $location ?: null,
                    !empty($input['all_day']) ? 1 : 0, $reminder, $eventId, $userType, $userId
                ]);
                if ($stmt->rowCount() === 0) {
                    $check = $db->prepare("SELECT id FROM personal_events WHERE id = ? AND user_type = ? AND user_id = ?");
                    $check->execute([$eventId, $userType, $userId]);
                    if (!$check->fetchColumn()) {
                        calendarRespond(['success' => false, 'message' => 'Personal event not found.'], 404);
                    }
                }
            } else {
                $stmt = $db->prepare("
                    INSERT INTO personal_events
                        (user_type, user_id, title, description, start_datetime, end_datetime,
                         location, is_all_day, reminder_minutes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userType, $userId, $title, $description ?: null, $start, $end,
                    $location ?: null, !empty($input['all_day']) ? 1 : 0, $reminder
                ]);
                $eventId = (int)$db->lastInsertId();
            }
            calendarRespond(['success' => true, 'id' => $eventId]);
        }
        calendarRespond(['success' => false, 'message' => 'Invalid event type.'], 400);
    }

    if ($action === 'delete_event') {
        $eventId = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
        $source = $input['source'] ?? '';
        if ($eventId === false || $eventId < 1) {
            calendarRespond(['success' => false, 'message' => 'Invalid event.'], 400);
        }
        if ($source === 'personal') {
            $stmt = $db->prepare("DELETE FROM personal_events WHERE id = ? AND user_type = ? AND user_id = ?");
            $stmt->execute([$eventId, $userType, $userId]);
        } elseif ($source === 'academic' && $isAdmin) {
            [$scopeSql, $scopeParams] = Authorization::adminBatchFilter();
            $stmt = $db->prepare("DELETE FROM academic_events WHERE id = ?" . $scopeSql);
            $stmt->execute(array_merge([$eventId], $scopeParams));
        } else {
            calendarRespond(['success' => false, 'message' => 'You cannot delete this event.'], 403);
        }
        if ($stmt->rowCount() < 1) {
            calendarRespond(['success' => false, 'message' => 'Event not found or you do not have permission.'], 404);
        }
        calendarRespond(['success' => true]);
    }

    calendarRespond(['success' => false, 'message' => 'Invalid calendar action.'], 400);
} catch (InvalidArgumentException $e) {
    calendarRespond(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('Calendar API error: ' . $e->getMessage());
    calendarRespond(['success' => false, 'message' => 'Calendar request failed. Please try again.'], 500);
}
