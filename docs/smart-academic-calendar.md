# Smart Academic Calendar

## Installation

1. Back up the database.
2. Confirm the existing `batches`, `admins`, `courses`, `classes`, `class_routines`,
   `class_routine_occurrences`, `assignments`, and `class_enrollments` tables are
   installed. Apply the existing batch and routine migrations first if needed.
3. Import `migrations/20261107_add_smart_calendar.sql` into the ClassMate database
   using phpMyAdmin (select the application database, then choose **Import**).
   The migration creates `academic_events`, `personal_events`, and
   `calendar_preferences`; it does not duplicate class or assignment records.
4. Upload the new files and modified navigation files with the rest of the
   application. There are no Composer or npm dependencies to install.
5. Sign in as a batch-scoped admin/CR and open **Academic Calendar**. Confirm that
   the admin's `batch_id` points to the batch they manage. Super admins can select
   a batch or publish an event to all batches.

## Calendar data and access

- Calendar class entries come from `classes` and recurring `class_routines`;
  per-date routine cancellations are read from `class_routine_occurrences`.
- Published/open assignment deadlines come directly from `assignments`.
- Exams, presentations, departmental activities, seminars, holidays, and other
  academic events are saved to `academic_events`.
- Batch-scoped admins can create, edit, cancel, and delete official events only
  in their assigned batch. A super admin can manage all batches.
- Students can view batch-wide and all-batch official events, and can manage only
  their own personal study events.
- Exam creation and official-event cancellation/rescheduling use the existing
  in-app notification manager. Routine class cancellations and restorations
  also notify the affected batch.
- Personal browser reminders require notification permission and the calendar
  page to remain open. They are browser-local timers, not background web-push
  notifications.
- The application timezone is `Asia/Dhaka`. Event times are entered and stored
  as local application datetimes.

## Manual testing checklist

### Access and permissions

- [ ] A batch-scoped CR sees only their batch's classes, routines, assignments,
      and academic events.
- [ ] A CR can create, edit, cancel, and delete events in their batch.
- [ ] A CR cannot edit or delete another batch's event by changing the request ID.
- [ ] A super admin can browse all batches, select a single batch, and create
      either batch-scoped or all-batch events.
- [ ] A student sees only enrolled class/assignment data and authorized academic
      events; attempts to write an official event are rejected.
- [ ] A student can create, edit, and delete their own personal study events, but
      cannot access another user's personal events.

### Calendar behavior

- [ ] Month navigation renders all dates and event category colors correctly.
- [ ] Week, day, and agenda views show correct dates and event times.
- [ ] Search and category filters update the calendar, highlights, and upcoming
      list without reloading the page; preferences persist after reload.
- [ ] Class cancellations appear as cancelled events.
- [ ] Assignment deadlines, examinations, next class, and department activities
      appear in the highlights/upcoming panel.
- [ ] Overlapping classes/examinations and multiple same-day assignment deadlines
      display conflict warnings.
- [ ] Event descriptions, locations, and titles containing HTML are displayed as
      text and cannot inject markup.
- [ ] Exam creation and event schedule changes create the expected in-app
      notifications.
- [ ] Personal browser reminders work after permission is granted; verify the
      browser's notification behavior on both desktop and mobile.

### Responsive and deployment

- [ ] Test at desktop, tablet, and 360px mobile widths, including the floating
      create button and bottom navigation.
- [ ] Test with reduced-motion preference and keyboard-only navigation.
- [ ] Verify `Asia/Dhaka` date/time display on the production server.
- [ ] Confirm the existing routine occurrence migration is installed; the API
      relies on it to display dated class cancellations.
