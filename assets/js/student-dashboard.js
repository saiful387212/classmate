/**
 * ClassMate - Student Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const tomorrowCard = document.getElementById('tomorrow-routine-card');
    const downloadButton = document.getElementById('download-tomorrow-routine');

    if (downloadButton && tomorrowCard) {
        downloadButton.addEventListener('click', function() {
            downloadRoutineCard(tomorrowCard);
        });
    }

    updateRoutineStatuses();
    window.setInterval(updateRoutineStatuses, 30000);

    if (tomorrowCard) {
        window.setInterval(function() {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const expectedDate = [
                tomorrow.getFullYear(),
                String(tomorrow.getMonth() + 1).padStart(2, '0'),
                String(tomorrow.getDate()).padStart(2, '0')
            ].join('-');
            if (tomorrowCard.dataset.routineDate !== expectedDate) {
                window.location.reload();
            }
        }, 60000);
    }
    
    // ===== Initialize Monthly Chart =====
    if (document.getElementById('monthlyChart')) {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        
        const data = window.monthlyData || {
            scheduled: 0,
            completed: 0,
            cancelled: 0,
            retaked: 0
        };
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Scheduled', 'Completed', 'Cancelled', 'Retaked'],
                datasets: [{
                    data: [
                        data.scheduled || 0,
                        data.completed || 0,
                        data.cancelled || 0,
                        data.retaked || 0
                    ],
                    backgroundColor: [
                        '#4F46E5',  // Primary - Scheduled
                        '#10B981',  // Success - Completed
                        '#EF4444',  // Danger - Cancelled
                        '#F59E0B'   // Warning - Retaked
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 12,
                                family: 'Inter'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    function updateRoutineStatuses() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.routine-class-item[data-auto-status="true"]').forEach(function(item) {
            const start = Number(item.dataset.startAt);
            const end = Number(item.dataset.endAt);
            const status = item.querySelector('[data-routine-status]');
            if (!status || !start || !end) return;

            let label = 'Scheduled';
            let statusClass = 'routine-status-scheduled';
            if (now >= end) {
                label = 'Completed';
                statusClass = 'routine-status-completed';
            } else if (now >= start) {
                label = 'In Progress';
                statusClass = 'routine-status-live';
            }

            const statusIcon = status.querySelector('i');
            status.textContent = label;
            if (statusIcon) {
                statusIcon.className = 'far fa-clock';
                statusIcon.setAttribute('aria-hidden', 'true');
                status.prepend(statusIcon);
            }
            status.classList.remove('routine-status-scheduled', 'routine-status-live', 'routine-status-completed', 'routine-status-cancelled');
            status.classList.add(statusClass);
        });

        const completedCount = document.querySelector('[data-today-completed-count]');
        const todayCard = document.getElementById('today-routine-card');
        if (completedCount && todayCard) {
            const completed = Array.from(todayCard.querySelectorAll('[data-routine-status]'))
                .filter(function(status) { return status.textContent.trim() === 'Completed'; }).length;
            completedCount.textContent = completed + ' Completed';
        }
    }

    function downloadRoutineCard(card) {
        const items = Array.from(card.querySelectorAll('.routine-download-item'));
        const width = 760;
        const padding = 30;
        const rowHeight = 248;
        const headerHeight = 164;
        const height = headerHeight + Math.max(items.length, 1) * (rowHeight + 14) + 35;
        const scale = 2;
        const canvas = document.createElement('canvas');
        canvas.width = width * scale;
        canvas.height = height * scale;
        const context = canvas.getContext('2d');
        if (!context) return;

        context.scale(scale, scale);
        context.fillStyle = '#f3f6fb';
        context.fillRect(0, 0, width, height);

        const date = new Date(card.dataset.routineDate + 'T00:00:00');
        context.save();
        drawRoundedRect(context, 18, 18, width - 36, headerHeight - 8, 18);
        context.clip();
        const headerGradient = context.createLinearGradient(18, 18, width - 18, headerHeight);
        headerGradient.addColorStop(0, '#18385f');
        headerGradient.addColorStop(1, '#213e68');
        context.fillStyle = headerGradient;
        context.fillRect(18, 18, width - 36, headerHeight);
        context.restore();

        drawCalendarIcon(context, width - 66, 49, '#ffffff');
        context.fillStyle = '#5eead4';
        context.font = '700 34px Arial, sans-serif';
        context.fillText('▦', padding, 75);
        context.fillStyle = '#ffffff';
        context.font = '700 29px Arial, sans-serif';
        context.fillText("Tomorrow's Classes", padding + 54, 64, width - 190);
        context.fillStyle = '#5eead4';
        context.fillRect(padding + 54, 74, 112, 3);
        context.fillStyle = '#cbd9eb';
        context.font = '16px Arial, sans-serif';
        context.fillText(date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }), padding + 54, 104, width - 100);
        context.fillStyle = '#d7e3f2';
        context.font = '600 13px Arial, sans-serif';
        context.fillText(items.length + (items.length === 1 ? ' CLASS SCHEDULED' : ' CLASSES SCHEDULED'), padding, 139);

        if (items.length === 0) {
            context.fillStyle = '#ffffff';
            drawRoundedRect(context, padding, headerHeight + 14, width - padding * 2, 112, 14);
            context.fill();
            context.fillStyle = '#475569';
            context.font = '18px Arial, sans-serif';
            context.fillText('No classes scheduled for tomorrow.', padding + 20, headerHeight + 78);
        }

        items.forEach(function(item, index) {
            const top = headerHeight + 14 + index * (rowHeight + 14);
            context.fillStyle = '#ffffff';
            drawRoundedRect(context, padding, top, width - padding * 2, rowHeight, 15);
            context.fill();

            context.fillStyle = '#19a88a';
            drawRoundedRect(context, padding, top, 6, rowHeight, 3);
            context.fill();

            const status = item.querySelector('[data-routine-status]');
            const statusText = status ? status.textContent.trim() : 'Scheduled';
            const statusStyles = {
                Scheduled: { background: '#e0e7ff', foreground: '#3730a3' },
                'In Progress': { background: '#d1fae5', foreground: '#065f46' },
                Completed: { background: '#e5e7eb', foreground: '#374151' },
                Cancelled: { background: '#fee2e2', foreground: '#991b1b' }
            };
            const style = statusStyles[statusText] || statusStyles.Scheduled;

            const titleX = padding + 104;
            context.beginPath();
            context.arc(padding + 53, top + 48, 32, 0, Math.PI * 2);
            context.fillStyle = '#dcf7f2';
            context.fill();
            drawGraduationCap(context, padding + 53, top + 48);

            const badgeWidth = Math.min(context.measureText(statusText).width + 38, 128);
            const badgeX = width - padding - badgeWidth - 16;
            context.fillStyle = style.background;
            drawRoundedRect(context, badgeX, top + 26, badgeWidth, 32, 16);
            context.fill();
            context.beginPath();
            context.arc(badgeX + 16, top + 42, 5, 0, Math.PI * 2);
            context.strokeStyle = style.foreground;
            context.lineWidth = 2;
            context.stroke();
            context.beginPath();
            context.moveTo(badgeX + 16, top + 39);
            context.lineTo(badgeX + 16, top + 42);
            context.lineTo(badgeX + 18, top + 44);
            context.stroke();
            context.fillStyle = style.foreground;
            context.font = '600 13px Arial, sans-serif';
            context.fillText(statusText, badgeX + 27, top + 47, badgeWidth - 34);

            context.fillStyle = '#1e293b';
            context.font = '700 21px Arial, sans-serif';
            context.fillText(item.dataset.course || 'Class', titleX, top + 43, badgeX - titleX - 18);
            context.fillStyle = '#718198';
            context.font = '15px Arial, sans-serif';
            context.fillText(item.dataset.title || '', titleX, top + 70, badgeX - titleX - 18);

            const panelX = padding + 22;
            const panelY = top + 94;
            const panelWidth = width - padding * 2 - 44;
            context.fillStyle = '#f2f6fc';
            drawRoundedRect(context, panelX, panelY, panelWidth, 70, 12);
            context.fill();
            context.fillStyle = '#2776b8';
            context.font = '600 12px Arial, sans-serif';
            context.fillText('CLASS CODE', panelX + 18, panelY + 25);
            context.fillStyle = '#1e3555';
            context.font = '700 16px Arial, sans-serif';
            context.fillText(item.dataset.code || '-', panelX + 18, panelY + 50, panelWidth * 0.4);

            const dividerX = panelX + panelWidth * 0.43;
            context.fillStyle = '#d6e0ed';
            context.fillRect(dividerX, panelY + 12, 1, 46);
            context.fillStyle = '#2776b8';
            context.font = '600 12px Arial, sans-serif';
            context.fillText('TIME', dividerX + 18, panelY + 25);
            context.fillStyle = '#153557';
            context.font = '700 16px Arial, sans-serif';
            context.fillText(item.dataset.time || '-', dividerX + 18, panelY + 50, panelWidth - (dividerX - panelX) - 30);

            const footerY = top + 194;
            drawMapPin(context, panelX + 10, footerY - 5, '#199b88');
            context.fillStyle = '#718198';
            context.font = '12px Arial, sans-serif';
            context.fillText('ROOM', panelX + 34, footerY - 3);
            context.fillStyle = '#1e3555';
            context.font = '700 14px Arial, sans-serif';
            context.fillText(item.dataset.room || 'TBA', panelX + 34, footerY + 18, 145);

            const teacherX = panelX + panelWidth * 0.38;
            context.fillStyle = '#e0e7ff';
            context.beginPath();
            context.arc(teacherX + 10, footerY + 1, 7, 0, Math.PI * 2);
            context.fill();
            context.fillRect(teacherX + 2, footerY + 9, 16, 8);
            context.fillStyle = '#718198';
            context.font = '12px Arial, sans-serif';
            context.fillText('TEACHER', teacherX + 28, footerY - 3);
            context.fillStyle = '#1e3555';
            context.font = '700 14px Arial, sans-serif';
            context.fillText(item.dataset.instructor || '-', teacherX + 28, footerY + 18, panelWidth - (teacherX - panelX) - 42);
        });

        context.fillStyle = '#94a3b8';
        context.font = '12px Arial, sans-serif';
        context.fillText('ClassMate - Your class schedule', padding, height - 27);

        const link = document.createElement('a');
        link.download = 'tomorrow-classes-' + card.dataset.routineDate + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    function drawCalendarIcon(context, x, y, color) {
        context.strokeStyle = color;
        context.lineWidth = 3;
        drawRoundedRect(context, x, y, 28, 27, 4);
        context.stroke();
        context.beginPath();
        context.moveTo(x + 1, y + 8);
        context.lineTo(x + 27, y + 8);
        context.moveTo(x + 8, y - 3);
        context.lineTo(x + 8, y + 5);
        context.moveTo(x + 20, y - 3);
        context.lineTo(x + 20, y + 5);
        context.stroke();
    }

    function drawGraduationCap(context, x, y) {
        context.fillStyle = '#17627a';
        context.beginPath();
        context.moveTo(x - 19, y - 5);
        context.lineTo(x, y - 15);
        context.lineTo(x + 19, y - 5);
        context.lineTo(x, y + 4);
        context.closePath();
        context.fill();
        context.fillRect(x - 12, y + 2, 24, 4);
        context.strokeStyle = '#17627a';
        context.lineWidth = 2;
        context.beginPath();
        context.moveTo(x + 17, y - 5);
        context.lineTo(x + 17, y + 8);
        context.stroke();
        context.beginPath();
        context.arc(x + 17, y + 10, 2, 0, Math.PI * 2);
        context.fill();
    }

    function drawMapPin(context, x, y, color) {
        context.fillStyle = color;
        context.beginPath();
        context.arc(x, y, 8, Math.PI, 0);
        context.lineTo(x, y + 13);
        context.closePath();
        context.fill();
        context.fillStyle = '#ffffff';
        context.beginPath();
        context.arc(x, y - 1, 2.5, 0, Math.PI * 2);
        context.fill();
    }

    function drawRoundedRect(context, x, y, width, height, radius) {
        context.beginPath();
        context.moveTo(x + radius, y);
        context.lineTo(x + width - radius, y);
        context.quadraticCurveTo(x + width, y, x + width, y + radius);
        context.lineTo(x + width, y + height - radius);
        context.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        context.lineTo(x + radius, y + height);
        context.quadraticCurveTo(x, y + height, x, y + height - radius);
        context.lineTo(x, y + radius);
        context.quadraticCurveTo(x, y, x + radius, y);
        context.closePath();
    }
    
    // ===== Auto-refresh notifications =====
    setInterval(function() {
        const apiUrl = window.API_NOTIFICATIONS_URL || ((window.BASE_URL || '') + '/api/notifications.php');
        fetch(apiUrl + '?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    const badge = document.querySelector('.notification-btn .badge');
                    if (badge) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'inline';
                    }
                }
            })
            .catch(() => {});
    }, 30000); // Every 30 seconds
    
    // ===== Class status auto-update =====
    const currentTime = new Date();
    document.querySelectorAll('.class-item').forEach(item => {
        const timeElement = item.querySelector('.class-time');
        if (timeElement) {
            // Update time remaining every minute
            setInterval(() => {
                // Refresh page to get updated times
                // Or implement real-time countdown
            }, 60000);
        }
    });
});

// ===== Utility: Format time remaining =====
function formatTimeRemaining(timestamp) {
    const now = new Date().getTime();
    const diff = timestamp - now;
    
    if (diff <= 0) return 'Now';
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (days > 0) {
        return `${days}d ${hours}h`;
    } else if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
}