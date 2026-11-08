(function () {
    'use strict';

    const root = document.getElementById('academicCalendar');
    if (!root) return;

    const apiUrl = root.dataset.apiUrl;
    const csrfToken = root.dataset.csrfToken;
    const canManageOfficial = root.dataset.canManageOfficial === '1';
    const canManagePersonal = root.dataset.canManagePersonal === '1';
    const content = document.getElementById('calendarContent');
    const heading = document.getElementById('calendarHeading');
    const viewSelect = document.getElementById('calendarView');
    const batchSelect = document.getElementById('calendarBatch');
    const searchInput = document.getElementById('calendarSearch');
    const modal = document.getElementById('calendarEventModal');
    const form = document.getElementById('calendarEventForm');
    const eventsByKey = new Map();
    const categoryLabels = {
        class: 'Class',
        assignment: 'Assignment',
        examination: 'Examination',
        presentation: 'Presentation',
        department: 'Department event',
        seminar: 'Seminar / workshop',
        holiday: 'Holiday',
        academic: 'Academic',
        personal: 'Personal'
    };
    const categoryInputs = Array.from(document.querySelectorAll('.calendar-category-filter input'));
    let activeDate = startOfDay(dhakaNow());
    let events = [];
    let preferences = null;
    let activeRequest = null;
    let lastFocusedElement = null;
    let reminderTimers = [];
    let viewWasSelected = false;

    function startOfDay(date) {
        return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
    }

    function dhakaNow() {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Dhaka',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23'
        }).formatToParts(new Date()).reduce(function (values, part) {
            values[part.type] = part.value;
            return values;
        }, {});
        return new Date(Date.UTC(
            Number(parts.year),
            Number(parts.month) - 1,
            Number(parts.day),
            Number(parts.hour),
            Number(parts.minute),
            Number(parts.second)
        ));
    }

    function dhakaEpoch(date) {
        return date.getTime() - 6 * 60 * 60 * 1000;
    }

    function dateKey(date) {
        return [
            date.getUTCFullYear(),
            String(date.getUTCMonth() + 1).padStart(2, '0'),
            String(date.getUTCDate()).padStart(2, '0')
        ].join('-');
    }

    function parseDate(value) {
        if (!value) return null;
        const parts = String(value).replace(' ', 'T').split(/[-T:]/);
        if (parts.length < 3) return null;
        return new Date(Date.UTC(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2]),
            Number(parts[3] || 0),
            Number(parts[4] || 0),
            Number(parts[5] || 0)
        ));
    }

    function formatDate(date, options) {
        return new Intl.DateTimeFormat('en-BD', Object.assign({ timeZone: 'UTC' }, options)).format(date);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function categoryOf(event) {
        return categoryLabels[event.type] ? event.type : 'academic';
    }

    function filteredEvents() {
        const visibleCategories = new Set(
            categoryInputs.filter(input => input.checked).map(input => input.value)
        );
        const query = searchInput.value.trim().toLocaleLowerCase();
        return events.filter(function (event) {
            if (!visibleCategories.has(categoryOf(event))) return false;
            if (!query) return true;
            const haystack = [
                event.title,
                event.description,
                event.location,
                event.course,
                event.courseCode,
                categoryLabels[event.type] || event.type,
                event.start
            ].join(' ').toLocaleLowerCase();
            return haystack.includes(query);
        });
    }

    function viewRange() {
        const view = viewSelect.value;
        if (view === 'day') {
            return [activeDate, activeDate];
        }
        if (view === 'week') {
            const first = new Date(activeDate);
            first.setUTCDate(first.getUTCDate() - ((first.getUTCDay() + 6) % 7));
            const last = new Date(first);
            last.setUTCDate(last.getUTCDate() + 6);
            return [first, last];
        }
        if (view === 'agenda') {
            const first = startOfDay(activeDate);
            const last = new Date(first);
            last.setUTCDate(last.getUTCDate() + 89);
            return [first, last];
        }

        const firstOfMonth = new Date(Date.UTC(activeDate.getUTCFullYear(), activeDate.getUTCMonth(), 1));
        const first = new Date(firstOfMonth);
        first.setUTCDate(first.getUTCDate() - ((first.getUTCDay() + 6) % 7));
        const last = new Date(first);
        last.setUTCDate(last.getUTCDate() + 41);
        return [first, last];
    }

    function setHeading() {
        const view = viewSelect.value;
        if (view === 'day') {
            heading.textContent = formatDate(activeDate, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        } else if (view === 'week') {
            const [first, last] = viewRange();
            heading.textContent = first.getUTCMonth() === last.getUTCMonth()
                ? formatDate(first, { month: 'long', day: 'numeric' }) + ' – ' + formatDate(last, { day: 'numeric', year: 'numeric' })
                : formatDate(first, { month: 'short', day: 'numeric' }) + ' – ' + formatDate(last, { month: 'short', day: 'numeric', year: 'numeric' });
        } else if (view === 'agenda') {
            const [first, last] = viewRange();
            heading.textContent = formatDate(first, { month: 'short', day: 'numeric' }) + ' – ' +
                formatDate(last, { month: 'short', day: 'numeric', year: 'numeric' });
        } else {
            heading.textContent = formatDate(activeDate, { month: 'long', year: 'numeric' });
        }
    }

    function localDateTimeInput(date) {
        return dateKey(date) + 'T' + String(date.getUTCHours()).padStart(2, '0') + ':' + String(date.getUTCMinutes()).padStart(2, '0');
    }

    function requestJson(url, options) {
        return fetch(url, options).then(async function (response) {
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Calendar request failed.');
            }
            return data;
        });
    }

    function loadCalendar() {
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        setHeading();
        content.innerHTML = '<div class="calendar-loading"><span class="calendar-spinner"></span> Loading your calendar…</div>';
        const [first, last] = viewRange();
        const params = new URLSearchParams({
            action: 'list',
            start: dateKey(first),
            end: dateKey(last)
        });
        if (batchSelect && batchSelect.value) params.set('batch_id', batchSelect.value);

        requestJson(apiUrl + '?' + params.toString(), { signal: activeRequest.signal })
            .then(function (data) {
                events = data.events || [];
                preferences = data.preferences || null;
                applyPreferences();
                renderCalendar();
                renderHighlights();
                renderUpcoming();
                renderConflicts();
                scheduleReminders();
            })
            .catch(function (error) {
                if (error.name === 'AbortError') return;
                content.innerHTML = '<div class="calendar-error"><i class="fas fa-triangle-exclamation"></i><strong>Calendar unavailable</strong><span>' +
                    escapeHtml(error.message) + '</span><button type="button" class="calendar-secondary-button" id="calendarRetry">Try again</button></div>';
                document.getElementById('calendarRetry').addEventListener('click', loadCalendar);
                document.getElementById('calendarUpcoming').innerHTML = '<p class="calendar-muted">Events could not be loaded.</p>';
            });
    }

    function applyPreferences() {
        if (!preferences) return;
        const viewIsValid = ['month', 'week', 'day', 'agenda'].includes(preferences.default_view);
        if (!viewWasSelected && viewIsValid) {
            viewSelect.value = preferences.default_view;
        }
        const savedCategories = (preferences.visible_categories || '').split(',');
        categoryInputs.forEach(input => {
            input.checked = savedCategories.includes(input.value);
        });
        const reminderSelect = document.getElementById('calendarReminderMinutes');
        if (reminderSelect) reminderSelect.value = String(preferences.reminder_minutes || 0);
    }

    function eventTime(event) {
        if (event.allDay) return 'All day';
        const start = parseDate(event.start);
        const end = parseDate(event.end);
        if (!start) return '';
        const startLabel = formatDate(start, { hour: 'numeric', minute: '2-digit' });
        if (!end || dateKey(start) !== dateKey(end)) return startLabel;
        return startLabel + ' – ' + formatDate(end, { hour: 'numeric', minute: '2-digit' });
    }

    function eventMarkup(event, compact) {
        const type = categoryOf(event);
        const time = eventTime(event);
        const status = event.status === 'cancelled' ? ' event-cancelled' : '';
        return '<button type="button" class="calendar-event-chip category-' + type + status +
            (compact ? ' compact' : '') + '" data-event-key="' + escapeHtml(event.source + ':' + event.id) + '" title="' +
            escapeHtml((time ? time + ' · ' : '') + event.title) + '">' +
            '<span class="event-chip-dot"></span>' +
            (!compact && time ? '<time>' + escapeHtml(time) + '</time>' : '') +
            '<span class="event-chip-title">' + escapeHtml(event.title) + '</span>' +
            '</button>';
    }

    function bindEventButtons(container) {
        container.querySelectorAll('[data-event-key]').forEach(function (button) {
            button.addEventListener('click', function () {
                const event = eventsByKey.get(button.dataset.eventKey);
                if (event) openEvent(event);
            });
        });
        container.querySelectorAll('[data-calendar-date]').forEach(function (button) {
            button.addEventListener('click', function () {
                const date = parseDate(button.dataset.calendarDate);
                if (!date) return;
                activeDate = startOfDay(date);
                viewSelect.value = 'day';
                viewWasSelected = true;
                loadCalendar();
            });
        });
    }

    function renderCalendar() {
        const visible = filteredEvents();
        eventsByKey.clear();
        visible.forEach(event => eventsByKey.set(event.source + ':' + event.id, event));
        if (viewSelect.value === 'month') renderMonth(visible);
        else if (viewSelect.value === 'week') renderWeek(visible);
        else if (viewSelect.value === 'day') renderDay(visible);
        else renderAgenda(visible);
    }

    function renderMonth(visible) {
        const [first] = viewRange();
        const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        let html = '<div class="calendar-month-grid"><div class="calendar-weekday-row">' +
            weekdays.map(day => '<div>' + day + '</div>').join('') + '</div><div class="calendar-month-days">';
        for (let i = 0; i < 42; i++) {
            const date = new Date(first);
            date.setUTCDate(first.getUTCDate() + i);
            const key = dateKey(date);
            const dayEvents = visible.filter(event => dateKey(parseDate(event.start)) === key);
            const today = key === dateKey(dhakaNow());
            const outside = date.getUTCMonth() !== activeDate.getUTCMonth();
            html += '<section class="calendar-day-cell' + (today ? ' is-today' : '') + (outside ? ' is-outside' : '') + '">' +
                '<button type="button" class="calendar-day-number" data-calendar-date="' + key + '" aria-label="View events on ' +
                escapeHtml(formatDate(date, { month: 'long', day: 'numeric' })) + '">' + date.getUTCDate() + '</button>' +
                '<div class="calendar-day-events">' + dayEvents.slice(0, 3).map(event => eventMarkup(event, true)).join('') +
                (dayEvents.length > 3 ? '<button type="button" class="calendar-more-events" data-calendar-date="' + key + '">+' +
                    (dayEvents.length - 3) + ' more</button>' : '') + '</div></section>';
        }
        html += '</div></div>';
        content.innerHTML = html;
        bindEventButtons(content);
    }

    function renderWeek(visible) {
        const [first] = viewRange();
        let html = '<div class="calendar-week-grid">';
        for (let i = 0; i < 7; i++) {
            const date = new Date(first);
            date.setUTCDate(first.getUTCDate() + i);
            const key = dateKey(date);
            const dayEvents = visible.filter(event => dateKey(parseDate(event.start)) === key);
            html += '<section class="calendar-week-column' + (key === dateKey(dhakaNow()) ? ' is-today' : '') + '">' +
                '<button type="button" class="calendar-week-date" data-calendar-date="' + key + '">' +
                '<span>' + formatDate(date, { weekday: 'short' }) + '</span><strong>' + date.getUTCDate() + '</strong></button>' +
                '<div class="calendar-week-events">' +
                (dayEvents.length ? dayEvents.map(event => eventMarkup(event, false)).join('') : '<span class="calendar-day-empty">No events</span>') +
                '</div></section>';
        }
        html += '</div>';
        content.innerHTML = html;
        bindEventButtons(content);
    }

    function renderDay(visible) {
        const key = dateKey(activeDate);
        const dayEvents = visible.filter(event => dateKey(parseDate(event.start)) === key);
        if (!dayEvents.length) {
            content.innerHTML = '<div class="calendar-empty-day"><span><i class="fas fa-mug-hot"></i></span><h3>A little breathing room</h3><p>No events scheduled for this day.</p>' +
                (canManageOfficial || canManagePersonal ? '<button type="button" class="calendar-primary-button" id="calendarEmptyAdd"><i class="fas fa-plus"></i> Add an event</button>' : '') + '</div>';
            const add = document.getElementById('calendarEmptyAdd');
            if (add) add.addEventListener('click', () => openNewEvent(activeDate));
            return;
        }
        const laneEnds = [];
        let html = '<div class="calendar-day-timeline">';
        dayEvents.forEach(function (event) {
            const start = parseDate(event.start);
            const end = parseDate(event.end) || start;
            let lane = laneEnds.findIndex(laneEnd => laneEnd <= (start ? start.getTime() : 0));
            if (lane < 0) lane = laneEnds.length;
            laneEnds[lane] = end ? end.getTime() : (start ? start.getTime() : 0);
            html += '<article class="calendar-timeline-event category-border-' + categoryOf(event) + '" style="--event-lane:' + lane + '">' +
                '<div class="calendar-timeline-time">' + escapeHtml(eventTime(event)) + '</div>' +
                '<div class="calendar-timeline-marker category-' + categoryOf(event) + '"></div>' +
                '<button type="button" class="calendar-timeline-content" data-event-key="' + escapeHtml(event.source + ':' + event.id) + '">' +
                '<span class="calendar-event-category category-text-' + categoryOf(event) + '">' + escapeHtml(categoryLabels[event.type] || 'Academic') +
                (event.status === 'cancelled' ? ' · Cancelled' : '') + '</span>' +
                '<strong>' + escapeHtml(event.title) + '</strong>' +
                (event.course ? '<span>' + escapeHtml((event.courseCode ? event.courseCode + ' · ' : '') + event.course) + '</span>' : '') +
                (event.location ? '<span><i class="fas fa-location-dot"></i> ' + escapeHtml(event.location) + '</span>' : '') +
                (event.description ? '<p>' + escapeHtml(event.description) + '</p>' : '') + '</button></article>';
        });
        html += '</div>';
        content.innerHTML = html;
        bindEventButtons(content);
    }

    function renderAgenda(visible) {
        const [rangeStart] = viewRange();
        const rangeStartKey = dateKey(rangeStart);
        const upcoming = visible.filter(event => dateKey(parseDate(event.start)) >= rangeStartKey);
        if (!upcoming.length) {
            content.innerHTML = '<div class="calendar-empty-day"><span><i class="fas fa-calendar-check"></i></span><h3>Nothing on the agenda</h3><p>No matching events in the next 90 days.</p></div>';
            return;
        }
        const grouped = new Map();
        upcoming.forEach(function (event) {
            const key = dateKey(parseDate(event.start));
            if (!grouped.has(key)) grouped.set(key, []);
            grouped.get(key).push(event);
        });
        let html = '<div class="calendar-agenda">';
        grouped.forEach(function (dayEvents, key) {
            const date = parseDate(key);
                const today = key === dateKey(dhakaNow());
                html += '<section class="calendar-agenda-day"><header><span class="agenda-date-number">' + date.getUTCDate() +
                '</span><div><strong>' + (today ? 'Today' : formatDate(date, { weekday: 'long' })) +
                '</strong><span>' + formatDate(date, { month: 'long', day: 'numeric', year: 'numeric' }) + '</span></div></header>' +
                '<div class="calendar-agenda-events">' + dayEvents.map(function (event) {
                    return '<button type="button" class="calendar-agenda-event category-border-' + categoryOf(event) +
                        '" data-event-key="' + escapeHtml(event.source + ':' + event.id) + '">' +
                        '<span class="agenda-event-time">' + escapeHtml(eventTime(event)) + '</span>' +
                        '<span class="agenda-event-main"><strong>' + escapeHtml(event.title) + '</strong><small>' +
                        escapeHtml(categoryLabels[event.type] || 'Academic') +
                        (event.course ? ' · ' + escapeHtml(event.course) : '') +
                        (event.location ? ' · ' + escapeHtml(event.location) : '') + '</small></span>' +
                        '<i class="fas fa-chevron-right"></i></button>';
                }).join('') + '</div></section>';
        });
        html += '</div>';
        content.innerHTML = html;
        bindEventButtons(content);
    }

    function timeUntil(date) {
        const milliseconds = dhakaEpoch(date) - Date.now();
        if (milliseconds <= 0) return 'Happening now';
        const days = Math.floor(milliseconds / 86400000);
        if (days === 0) {
            const hours = Math.max(1, Math.ceil(milliseconds / 3600000));
            return hours === 1 ? 'In 1 hour' : 'In ' + hours + ' hours';
        }
        if (days === 1) return 'Tomorrow';
        if (days < 30) return 'In ' + days + ' days';
        return formatDate(date, { month: 'short', day: 'numeric' });
    }

    function futureEvents(type) {
        return events.filter(function (event) {
            const date = parseDate(event.start);
            if (!date || dhakaEpoch(date) < Date.now() || event.status === 'cancelled') return false;
            return type === 'department'
                ? ['department', 'seminar', 'presentation', 'holiday', 'academic'].includes(event.type)
                : event.type === type;
        }).sort((a, b) => parseDate(a.start) - parseDate(b.start));
    }

    function renderHighlights() {
        const mappings = [
            ['class', 'class'],
            ['examination', 'examination'],
            ['assignment', 'assignment'],
            ['department', 'department']
        ];
        mappings.forEach(function ([key, type]) {
            const event = futureEvents(type)[0];
            const title = document.querySelector('[data-highlight="' + key + '"]');
            const meta = document.querySelector('[data-highlight-meta="' + key + '"]');
            title.textContent = event ? event.title : 'Nothing scheduled';
            meta.textContent = event ? timeUntil(parseDate(event.start)) + ' · ' + eventTime(event) : 'You’re all caught up';
            title.title = event ? event.title : '';
        });
    }

    function renderUpcoming() {
        const container = document.getElementById('calendarUpcoming');
        const upcoming = filteredEvents().filter(function (event) {
            const date = parseDate(event.start);
            return date && dhakaEpoch(date) >= Date.now() && event.status !== 'cancelled';
        }).sort((a, b) => parseDate(a.start) - parseDate(b.start)).slice(0, 6);
        if (!upcoming.length) {
            container.innerHTML = '<p class="calendar-muted">No upcoming events in this period.</p>';
            return;
        }
        eventsByKey.clear();
        filteredEvents().forEach(event => eventsByKey.set(event.source + ':' + event.id, event));
        container.innerHTML = upcoming.map(function (event) {
            const date = parseDate(event.start);
            return '<button type="button" class="calendar-upcoming-item category-border-' + categoryOf(event) +
                '" data-event-key="' + escapeHtml(event.source + ':' + event.id) + '">' +
                '<span class="upcoming-date"><strong>' + date.getUTCDate() + '</strong><small>' +
                formatDate(date, { month: 'short' }) + '</small></span>' +
                '<span class="upcoming-info"><strong>' + escapeHtml(event.title) + '</strong><small>' +
                escapeHtml(timeUntil(date) + ' · ' + (categoryLabels[event.type] || 'Academic')) + '</small></span>' +
                '<i class="fas fa-chevron-right"></i></button>';
        }).join('');
        bindEventButtons(container);
    }

    function renderConflicts() {
        const panel = document.getElementById('calendarConflicts');
        const [first, last] = viewRange();
        const firstKey = dateKey(first);
        const lastKey = dateKey(last);
        const visible = filteredEvents().filter(event => {
            const key = dateKey(parseDate(event.start));
            return key >= firstKey && key <= lastKey && event.status !== 'cancelled';
        });
        const conflicts = [];
        ['class', 'examination'].forEach(function (type) {
            const typed = visible.filter(event => event.type === type);
            for (let i = 0; i < typed.length; i++) {
                for (let j = i + 1; j < typed.length; j++) {
                    if (dateKey(parseDate(typed[i].start)) !== dateKey(parseDate(typed[j].start))) continue;
                    if (typed[i].allDay || typed[j].allDay) {
                        conflicts.push((type === 'class' ? 'Class schedule overlap' : 'Examination overlap') +
                            ': “' + typed[i].title + '” and “' + typed[j].title + '”.');
                        continue;
                    }
                    if (!typed[i].end || !typed[j].end) continue;
                    if (parseDate(typed[i].start) < parseDate(typed[j].end)
                        && parseDate(typed[j].start) < parseDate(typed[i].end)) {
                        conflicts.push((type === 'class' ? 'Class schedule overlap' : 'Examination overlap') +
                            ': “' + typed[i].title + '” and “' + typed[j].title + '”.');
                    }
                }
            }
        });
        const assignmentsByDay = new Map();
        visible.filter(event => event.type === 'assignment').forEach(function (event) {
            const key = dateKey(parseDate(event.start));
            assignmentsByDay.set(key, (assignmentsByDay.get(key) || 0) + 1);
        });
        assignmentsByDay.forEach(function (count, date) {
            if (count > 1) conflicts.push(count + ' assignment deadlines fall on ' +
                formatDate(parseDate(date), { month: 'short', day: 'numeric' }) + '.');
        });
        if (!conflicts.length) {
            panel.hidden = true;
            panel.innerHTML = '';
            return;
        }
        panel.hidden = false;
        panel.innerHTML = '<div class="conflict-heading"><i class="fas fa-triangle-exclamation"></i><strong>Schedule conflicts to review</strong></div>' +
            '<ul>' + conflicts.slice(0, 8).map(item => '<li>' + escapeHtml(item) + '</li>').join('') + '</ul>';
    }

    function setFormValue(name, value) {
        const field = form.elements.namedItem(name);
        if (!field) return;
        if (field.type === 'checkbox') {
            field.checked = value === true || value === 1 || value === '1';
        } else {
            field.value = value == null ? '' : value;
        }
    }

    function openModal() {
        lastFocusedElement = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('calendar-modal-open');
        modal.querySelector('input[name="title"]').focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('calendar-modal-open');
        form.reset();
        form.querySelectorAll('input, select, textarea').forEach(field => { field.disabled = false; });
        document.getElementById('calendarSaveEvent').hidden = false;
        document.getElementById('calendarDeleteEvent').hidden = true;
        document.getElementById('calendarFormError').hidden = true;
        if (lastFocusedElement) lastFocusedElement.focus();
    }

    function configureFormPermissions(source, editable) {
        const isPersonal = source === 'personal';
        setFormValue('source', source);
        const categoryField = form.elements.namedItem('type');
        const batchField = form.elements.namedItem('batch_id');
        const courseField = form.elements.namedItem('course_id');
        const reminderWrapper = document.getElementById('calendarPersonalReminderField');
        const statusField = form.elements.namedItem('status');
        if (categoryField) categoryField.closest('.calendar-form-field').hidden = isPersonal;
        if (courseField) courseField.closest('.calendar-form-field').hidden = isPersonal;
        if (batchField) batchField.closest('.calendar-form-field').hidden = isPersonal;
        if (statusField) statusField.closest('.calendar-form-field').hidden = isPersonal;
        if (reminderWrapper) reminderWrapper.hidden = !isPersonal;
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.name === 'id' || field.name === 'source') return;
            if (field.name === 'type' || field.name === 'batch_id' || field.name === 'course_id') {
                field.disabled = isPersonal || !editable;
            } else if (field.name === 'reminder_minutes') {
                field.disabled = !isPersonal || !editable;
            } else {
                field.disabled = !editable;
            }
        });
        document.getElementById('calendarSaveEvent').hidden = !editable;
        const canDelete = editable && (source === 'academic' ? canManageOfficial : canManagePersonal);
        document.getElementById('calendarDeleteEvent').hidden = !canDelete;
    }

    function openNewEvent(date) {
        if (!canManageOfficial && !canManagePersonal) return;
        form.reset();
        document.getElementById('calendarModalTitle').textContent = 'Add calendar event';
        setFormValue('source', canManageOfficial ? 'academic' : 'personal');
        if (batchSelect && form.elements.namedItem('batch_id')) {
            setFormValue('batch_id', batchSelect.value);
        }
        const chosenDate = date ? startOfDay(date) : startOfDay(activeDate);
        const today = startOfDay(dhakaNow());
        const chosen = chosenDate < today ? today : chosenDate;
        const defaultStart = new Date(chosen);
        if (defaultStart.getTime() < dhakaNow().getTime()) {
            defaultStart.setUTCHours(dhakaNow().getUTCHours() + 1, 0, 0, 0);
        } else {
            defaultStart.setUTCHours(9, 0, 0, 0);
        }
        const defaultEnd = new Date(defaultStart);
        defaultEnd.setUTCHours(defaultEnd.getUTCHours() + 1);
        setFormValue('start', localDateTimeInput(defaultStart));
        setFormValue('end', localDateTimeInput(defaultEnd));
        configureFormPermissions(canManageOfficial ? 'academic' : 'personal', true);
        openModal();
    }

    function openEvent(event) {
        if (event.url && !event.editable) {
            const detail = [
                event.title,
                categoryLabels[event.type] || 'Academic event',
                formatDate(parseDate(event.start), { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) +
                    (eventTime(event) ? ' · ' + eventTime(event) : ''),
                event.course ? (event.courseCode ? event.courseCode + ' · ' : '') + event.course : '',
                event.location || '',
                event.description || ''
            ].filter(Boolean).join('\n');
            const goToSource = window.confirm(detail + '\n\nOpen the related ClassMate page?');
            if (goToSource) window.location.href = event.url;
            return;
        }

        form.reset();
        document.getElementById('calendarModalTitle').textContent = event.editable ? 'Edit calendar event' : 'Event details';
        setFormValue('id', event.id);
        setFormValue('source', event.source);
        setFormValue('title', event.title);
        setFormValue('description', event.description);
        setFormValue('location', event.location);
        setFormValue('start', localDateTimeInput(parseDate(event.start)));
        setFormValue('end', event.end ? localDateTimeInput(parseDate(event.end)) : '');
        setFormValue('all_day', event.allDay ? '1' : '');
        setFormValue('type', event.type);
        setFormValue('status', event.status);
        setFormValue('course_id', event.courseId || '');
        setFormValue('batch_id', event.batchId || '');
        setFormValue('reminder_minutes', event.reminderMinutes == null ? '' : event.reminderMinutes);
        configureFormPermissions(event.source, event.editable);
        openModal();
    }

    function sendFormAction(action, data) {
        return requestJson(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: action, csrf_token: csrfToken }, data))
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        data.all_day = form.elements.namedItem('all_day').checked ? 1 : 0;
        const submit = document.getElementById('calendarSaveEvent');
        const error = document.getElementById('calendarFormError');
        submit.disabled = true;
        submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        error.hidden = true;
        sendFormAction('save_event', data)
            .then(function () {
                closeModal();
                loadCalendar();
            })
            .catch(function (requestError) {
                error.textContent = requestError.message;
                error.hidden = false;
            })
            .finally(function () {
                submit.disabled = false;
                submit.innerHTML = '<i class="fas fa-check"></i> Save event';
            });
    });

    document.getElementById('calendarDeleteEvent').addEventListener('click', function () {
        if (!window.confirm('Delete this calendar event? This cannot be undone.')) return;
        const data = {
            id: form.elements.namedItem('id').value,
            source: form.elements.namedItem('source').value
        };
        sendFormAction('delete_event', data)
            .then(function () {
                closeModal();
                loadCalendar();
            })
            .catch(function (error) {
                const message = document.getElementById('calendarFormError');
                message.textContent = error.message;
                message.hidden = false;
            });
    });

    function savePreferences() {
        const selected = categoryInputs.filter(input => input.checked).map(input => input.value);
        const reminderSelect = document.getElementById('calendarReminderMinutes');
        sendFormAction('preferences', {
            default_view: viewSelect.value,
            visible_categories: selected,
            reminder_minutes: reminderSelect ? Number(reminderSelect.value) : 1440
        }).catch(function (error) {
            console.error('Unable to save calendar preferences:', error);
        });
    }

    function scheduleReminders() {
        reminderTimers.forEach(timer => window.clearTimeout(timer));
        reminderTimers = [];
        const permissionButton = document.getElementById('calendarReminderToggle');
        if (!('Notification' in window)) {
            permissionButton.hidden = true;
            return;
        }
        permissionButton.hidden = Notification.permission === 'granted';
        if (Notification.permission !== 'granted') return;

        events.filter(event => event.type === 'personal').forEach(function (event) {
            const eventTimeMs = dhakaEpoch(parseDate(event.start));
            const reminderMinutes = event.reminderMinutes === null
                ? Number(document.getElementById('calendarReminderMinutes').value || 0)
                : event.reminderMinutes;
            const reminderTime = eventTimeMs - reminderMinutes * 60000;
            const delay = reminderTime - Date.now();
            const key = 'calendar-reminder-' + event.id + '-' + eventTimeMs;
            let reminderWasShown = false;
            try {
                reminderWasShown = localStorage.getItem(key) === '1';
            } catch (error) {
                console.warn('Calendar reminder history is unavailable.', error);
            }
            if (delay < 0 || delay > 2147480000 || reminderWasShown) return;
            reminderTimers.push(window.setTimeout(function () {
                if (Notification.permission !== 'granted') return;
                const notification = new Notification(event.title, {
                    body: reminderMinutes ? 'Your personal calendar event is coming up.' : 'Your personal calendar event is starting now.',
                    tag: key
                });
                notification.onclick = function () {
                    window.focus();
                    notification.close();
                };
                try {
                    localStorage.setItem(key, '1');
                } catch (error) {
                    console.warn('Unable to save calendar reminder history.', error);
                }
            }, delay));
        });
    }

    document.getElementById('calendarReminderToggle').addEventListener('click', async function () {
        if (!('Notification' in window)) return;
        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.hidden = true;
                scheduleReminders();
            } else if (permission === 'denied') {
                this.textContent = 'Browser notifications are blocked';
            }
        } catch (error) {
            console.error('Unable to enable calendar notifications:', error);
        }
    });

    document.getElementById('calendarPrevious').addEventListener('click', function () {
        const offset = viewSelect.value === 'month' ? -1 : (viewSelect.value === 'week' ? -7 : (viewSelect.value === 'agenda' ? -90 : -1));
        if (viewSelect.value === 'month') activeDate = new Date(Date.UTC(activeDate.getUTCFullYear(), activeDate.getUTCMonth() + offset, 1));
        else activeDate.setUTCDate(activeDate.getUTCDate() + offset);
        loadCalendar();
    });
    document.getElementById('calendarNext').addEventListener('click', function () {
        const offset = viewSelect.value === 'month' ? 1 : (viewSelect.value === 'week' ? 7 : (viewSelect.value === 'agenda' ? 90 : 1));
        if (viewSelect.value === 'month') activeDate = new Date(Date.UTC(activeDate.getUTCFullYear(), activeDate.getUTCMonth() + offset, 1));
        else activeDate.setUTCDate(activeDate.getUTCDate() + offset);
        loadCalendar();
    });
    document.getElementById('calendarToday').addEventListener('click', function () {
        activeDate = startOfDay(dhakaNow());
        loadCalendar();
    });
    viewSelect.addEventListener('change', function () {
        viewWasSelected = true;
        savePreferences();
        loadCalendar();
    });
    if (batchSelect) {
        batchSelect.addEventListener('change', function () {
            const url = new URL(window.location.href);
            if (this.value) url.searchParams.set('batch_id', this.value);
            else url.searchParams.delete('batch_id');
            window.history.replaceState({}, '', url);
            loadCalendar();
        });
    }
    searchInput.addEventListener('input', function () {
        renderCalendar();
        renderConflicts();
    });
    categoryInputs.forEach(input => input.addEventListener('change', function () {
        savePreferences();
        renderCalendar();
        renderConflicts();
    }));
    document.getElementById('calendarResetFilters').addEventListener('click', function () {
        categoryInputs.forEach(input => { input.checked = true; });
        savePreferences();
        renderCalendar();
        renderConflicts();
    });
    document.getElementById('calendarReminderMinutes').addEventListener('change', function () {
        savePreferences();
        scheduleReminders();
    });
    [document.getElementById('calendarAddEvent'), document.getElementById('calendarMobileAdd')]
        .filter(Boolean)
        .forEach(button => button.addEventListener('click', () => openNewEvent(activeDate)));
    modal.querySelectorAll('[data-close-calendar-modal]').forEach(button => button.addEventListener('click', closeModal));
    modal.addEventListener('click', event => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    loadCalendar();
})();
