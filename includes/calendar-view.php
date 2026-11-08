<?php
$calendarCategories = [
    'class' => 'Classes',
    'assignment' => 'Assignments',
    'examination' => 'Examinations',
    'presentation' => 'Presentations',
    'department' => 'Department Events',
    'seminar' => 'Seminars & Workshops',
    'holiday' => 'Holidays',
    'academic' => 'Academic',
    'personal' => 'Personal'
];
?>
<div class="calendar-page <?php echo $calendarIsAdmin ? 'calendar-admin' : 'calendar-student'; ?>"
     id="academicCalendar"
     data-api-url="<?php echo htmlspecialchars(url('api/calendar-api.php'), ENT_QUOTES, 'UTF-8'); ?>"
     data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
     data-can-manage-official="<?php echo $calendarCanManageOfficial ? '1' : '0'; ?>"
     data-can-manage-personal="<?php echo $calendarCanManagePersonal ? '1' : '0'; ?>"
     data-initial-batch="<?php echo $calendarSelectedBatch ? (int)$calendarSelectedBatch : ''; ?>">
    <header class="calendar-hero">
        <div>
            <span class="calendar-eyebrow"><i class="fas fa-sparkles"></i> ClassMate Planner</span>
            <h1>Academic Calendar</h1>
            <p><?php echo $calendarIsAdmin ? 'Plan and publish academic activities for your batch.' : 'Your classes, deadlines, exams and activities in one place.'; ?></p>
        </div>
        <?php if ($calendarCanManageOfficial || $calendarCanManagePersonal): ?>
            <button type="button" class="calendar-primary-button" id="calendarAddEvent">
                <i class="fas fa-plus"></i>
                <?php echo $calendarCanManageOfficial ? 'Add academic event' : 'Add personal event'; ?>
            </button>
        <?php endif; ?>
    </header>

    <section class="calendar-highlights" id="calendarHighlights" aria-label="Upcoming academic activities">
        <article class="calendar-highlight">
            <span class="highlight-icon highlight-class"><i class="fas fa-chalkboard"></i></span>
            <div><small>Next class</small><strong data-highlight="class">Loading…</strong><span data-highlight-meta="class"></span></div>
        </article>
        <article class="calendar-highlight">
            <span class="highlight-icon highlight-exam"><i class="fas fa-file-pen"></i></span>
            <div><small>Upcoming exam</small><strong data-highlight="examination">Loading…</strong><span data-highlight-meta="examination"></span></div>
        </article>
        <article class="calendar-highlight">
            <span class="highlight-icon highlight-assignment"><i class="fas fa-list-check"></i></span>
            <div><small>Nearest deadline</small><strong data-highlight="assignment">Loading…</strong><span data-highlight-meta="assignment"></span></div>
        </article>
        <article class="calendar-highlight">
            <span class="highlight-icon highlight-department"><i class="fas fa-calendar-day"></i></span>
            <div><small>Department event</small><strong data-highlight="department">Loading…</strong><span data-highlight-meta="department"></span></div>
        </article>
    </section>

    <section class="calendar-shell">
        <div class="calendar-main">
            <div class="calendar-toolbar">
                <div class="calendar-date-navigation">
                    <button type="button" class="calendar-icon-button" id="calendarPrevious" aria-label="Previous period"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="calendar-icon-button" id="calendarNext" aria-label="Next period"><i class="fas fa-chevron-right"></i></button>
                    <button type="button" class="calendar-today-button" id="calendarToday">Today</button>
                    <h2 id="calendarHeading" aria-live="polite"></h2>
                </div>
                <div class="calendar-toolbar-actions">
                    <?php if ($calendarIsAdmin && Authorization::isSuperAdmin()): ?>
                        <label class="calendar-batch-select">
                            <span class="sr-only">Calendar batch</span>
                            <select id="calendarBatch">
                                <option value="">All batches</option>
                                <?php foreach ($calendarBatches as $batch): ?>
                                    <option value="<?php echo (int)$batch['id']; ?>" <?php echo (string)$calendarSelectedBatch === (string)$batch['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($batch['label'] ?: $batch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label class="calendar-view-select">
                        <span class="sr-only">Calendar view</span>
                        <select id="calendarView">
                            <option value="month">Month</option>
                            <option value="week">Week</option>
                            <option value="day">Day</option>
                            <option value="agenda">Agenda</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="calendar-search-row">
                <label class="calendar-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span class="sr-only">Search events</span>
                    <input type="search" id="calendarSearch" placeholder="Search title, course, category or location…">
                </label>
                <button type="button" class="calendar-reminder-toggle" id="calendarReminderToggle" hidden>
                    <i class="fas fa-bell"></i> Enable reminders
                </button>
            </div>

            <div class="calendar-content" id="calendarContent" aria-live="polite">
                <div class="calendar-loading"><span class="calendar-spinner"></span> Loading your calendar…</div>
            </div>
            <div class="calendar-conflicts" id="calendarConflicts" hidden></div>
        </div>

        <aside class="calendar-sidebar">
            <section class="calendar-side-panel">
                <div class="calendar-panel-heading">
                    <div><span class="calendar-eyebrow">YOUR SCHEDULE</span><h3>Coming up</h3></div>
                    <i class="fas fa-arrow-trend-up"></i>
                </div>
                <div id="calendarUpcoming" class="calendar-upcoming-list">
                    <p class="calendar-muted">Loading events…</p>
                </div>
            </section>
            <section class="calendar-side-panel calendar-filter-panel">
                <div class="calendar-panel-heading">
                    <div><span class="calendar-eyebrow">PERSONALIZE</span><h3>Event types</h3></div>
                    <button type="button" class="calendar-text-button" id="calendarResetFilters">Reset</button>
                </div>
                <div class="calendar-category-filters">
                    <?php foreach ($calendarCategories as $category => $label): ?>
                        <label class="calendar-category-filter">
                            <input type="checkbox" value="<?php echo htmlspecialchars($category); ?>" checked>
                            <span class="category-dot category-<?php echo htmlspecialchars($category); ?>"></span>
                            <span><?php echo htmlspecialchars($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label class="calendar-reminder-setting">
                    <span><i class="fas fa-bell"></i> Personal event reminder</span>
                    <select id="calendarReminderMinutes">
                        <option value="0">At event time</option>
                        <option value="5">5 minutes before</option>
                        <option value="15">15 minutes before</option>
                        <option value="60">1 hour before</option>
                        <option value="1440" selected>1 day before</option>
                        <option value="10080">1 week before</option>
                    </select>
                </label>
            </section>
            <section class="calendar-side-panel calendar-key-panel">
                <span class="calendar-eyebrow">QUICK GUIDE</span>
                <p><i class="fas fa-circle-check"></i> Official events are managed by your batch CR.</p>
                <p><i class="fas fa-lock"></i> Your personal study events are private to you.</p>
            </section>
        </aside>
    </section>

    <button type="button" class="calendar-fab" id="calendarMobileAdd" aria-label="Add calendar event"
            <?php echo ($calendarCanManageOfficial || $calendarCanManagePersonal) ? '' : 'hidden'; ?>>
        <i class="fas fa-plus"></i>
    </button>
</div>

<div class="calendar-modal-backdrop" id="calendarEventModal" hidden>
    <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="calendarModalTitle">
        <header class="calendar-modal-header">
            <div><span class="calendar-eyebrow">CALENDAR EVENT</span><h2 id="calendarModalTitle">Add event</h2></div>
            <button type="button" class="calendar-icon-button" data-close-calendar-modal aria-label="Close"><i class="fas fa-times"></i></button>
        </header>
        <form id="calendarEventForm">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="source" value="<?php echo $calendarCanManageOfficial ? 'academic' : 'personal'; ?>">
            <div class="calendar-form-grid">
                <?php if ($calendarCanManageOfficial): ?>
                    <label class="calendar-form-field calendar-field-wide">
                        <span>Event title <b>*</b></span>
                        <input type="text" name="title" maxlength="200" required placeholder="e.g. Midterm examination">
                    </label>
                    <label class="calendar-form-field">
                        <span>Category</span>
                        <select name="type">
                            <option value="examination">Examination</option>
                            <option value="presentation">Presentation</option>
                            <option value="department">Department event</option>
                            <option value="seminar">Seminar / workshop</option>
                            <option value="holiday">Holiday</option>
                            <option value="academic">Other academic</option>
                        </select>
                    </label>
                    <label class="calendar-form-field">
                        <span>Course (optional)</span>
                        <select name="course_id">
                            <option value="">No course</option>
                            <?php foreach ($calendarCourses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' · ' . $course['course_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="calendar-form-field">
                        <span>Event status</span>
                        <select name="status">
                            <option value="scheduled">Scheduled</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </label>
                    <?php if (Authorization::isSuperAdmin()): ?>
                        <label class="calendar-form-field">
                            <span>Batch</span>
                            <select name="batch_id">
                                <option value="">All batches</option>
                                <?php foreach ($calendarBatches as $batch): ?>
                                    <option value="<?php echo (int)$batch['id']; ?>"><?php echo htmlspecialchars($batch['label'] ?: $batch['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                <?php else: ?>
                    <label class="calendar-form-field calendar-field-wide">
                        <span>Study event title <b>*</b></span>
                        <input type="text" name="title" maxlength="200" required placeholder="e.g. Review chapter 4">
                    </label>
                <?php endif; ?>
                <label class="calendar-form-field">
                    <span>Starts <b>*</b></span>
                    <input type="datetime-local" name="start" required>
                </label>
                <label class="calendar-form-field">
                    <span>Ends</span>
                    <input type="datetime-local" name="end">
                </label>
                <label class="calendar-form-field calendar-field-wide">
                    <span>Location</span>
                    <input type="text" name="location" maxlength="200" placeholder="Room, building, or online">
                </label>
                <label class="calendar-form-field calendar-field-wide">
                    <span>Description</span>
                    <textarea name="description" rows="3" maxlength="5000" placeholder="Add helpful details…"></textarea>
                </label>
                <?php if ($calendarCanManagePersonal): ?>
                    <label class="calendar-form-field" id="calendarPersonalReminderField">
                        <span>Reminder</span>
                        <select name="reminder_minutes">
                            <option value="">Use my default reminder</option>
                            <option value="0">At event time</option>
                            <option value="5">5 minutes before</option>
                            <option value="15">15 minutes before</option>
                            <option value="60">1 hour before</option>
                            <option value="1440">1 day before</option>
                            <option value="10080">1 week before</option>
                        </select>
                    </label>
                <?php endif; ?>
                <label class="calendar-all-day">
                    <input type="checkbox" name="all_day" value="1">
                    <span>All-day event</span>
                </label>
            </div>
            <p class="calendar-form-error" id="calendarFormError" role="alert" hidden></p>
            <footer class="calendar-modal-footer">
                <button type="button" class="calendar-danger-button" id="calendarDeleteEvent" hidden><i class="fas fa-trash"></i> Delete</button>
                <span></span>
                <button type="button" class="calendar-secondary-button" data-close-calendar-modal>Cancel</button>
                <button type="submit" class="calendar-primary-button" id="calendarSaveEvent"><i class="fas fa-check"></i> Save event</button>
            </footer>
        </form>
    </section>
</div>
