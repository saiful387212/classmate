/**
 * ClassMate - Notification System
 * Handles real-time notification updates, dropdown interaction, and read status
 */

(function() {
    'use strict';
    
    // Configuration
    const POLL_INTERVAL = 30000; // 30 seconds
    const API_URL = window.API_NOTIFICATIONS_URL || (window.BASE_URL ? window.BASE_URL + '/api/notifications.php' : '/api/notifications.php');
    
    let lastNotificationCount = -1;
    let pollTimer = null;
    
    // DOM elements
    let badgeEl, dropdownEl, listEl, markAllBtn, notifBtn;
    
    function init() {
        badgeEl = document.getElementById('notificationBadge');
        dropdownEl = document.getElementById('notificationDropdown');
        listEl = document.getElementById('notificationList');
        markAllBtn = document.getElementById('markAllBtn');
        notifBtn = document.getElementById('notificationBtn');
        
        if (!notifBtn) return; // Not logged in or no notifications UI
        
        // Listen to notification bell button click to refresh list when opened
        notifBtn.addEventListener('click', function() {
            setTimeout(function() {
                if (dropdownEl && dropdownEl.classList.contains('show')) {
                    loadNotifications();
                }
            }, 60);
        });
        
        // Attach click handlers to any existing server-rendered notification items
        attachItemHandlers();
        
        // Initial count check
        checkCount();
        
        // Start polling
        startPolling();
        
        // Refresh on visibility change
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                checkCount();
            }
        });
    }
    
    function checkCount() {
        fetch(API_URL + '?action=count', { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateBadge(data.count);
                    
                    // Play sound or show toast for new notifications
                    if (lastNotificationCount >= 0 && data.count > lastNotificationCount) {
                        onNewNotification(data.count - lastNotificationCount);
                    }
                    lastNotificationCount = data.count;
                }
            })
            .catch(() => {});
    }
    
    function updateBadge(count) {
        if (!badgeEl) return;
        
        if (count > 0) {
            badgeEl.textContent = count > 99 ? '99+' : count;
            badgeEl.style.display = 'inline-block';
            if (notifBtn) notifBtn.classList.add('has-unread');
        } else {
            badgeEl.style.display = 'none';
            if (notifBtn) notifBtn.classList.remove('has-unread');
        }
        
        if (markAllBtn) {
            markAllBtn.style.display = count > 0 ? 'inline-flex' : 'none';
        }
    }
    
    function onNewNotification(count) {
        // Visual pulse on bell
        if (notifBtn) {
            notifBtn.classList.add('pulse');
            setTimeout(() => notifBtn.classList.remove('pulse'), 2000);
        }
        
        // Show toast if available
        if (typeof showToast === 'function') {
            showToast(`You have ${count} new notification${count > 1 ? 's' : ''}`, 'info');
        }
    }
    
    function loadNotifications() {
        if (!listEl) return;
        
        fetch(API_URL + '?action=list', { credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    return;
                }
                
                if (data.notifications.length === 0) {
                    listEl.innerHTML = `
                        <div class="empty-notifications">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications yet</p>
                            <small>You're all caught up!</small>
                        </div>
                    `;
                    updateBadge(0);
                    return;
                }
                
                listEl.innerHTML = data.notifications.map(n => renderNotification(n)).join('');
                attachItemHandlers();
                updateBadge(data.unread_count);
            })
            .catch(() => {});
    }
    
    function renderNotification(n) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        const colors = {
            success: 'success',
            error: 'danger',
            warning: 'warning',
            info: 'info'
        };
        
        const icon = icons[n.type] || icons.info;
        const color = colors[n.type] || colors.info;
        
        return `
            <a href="${escapeHtml(n.link || 'javascript:void(0)')}" 
               class="notification-item ${n.is_read ? 'read' : 'unread'}" 
               data-id="${n.id}" 
               data-link="${escapeHtml(n.link || '')}"
               data-read="${n.is_read ? '1' : '0'}">
                <div class="notification-icon notification-icon-${color}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${escapeHtml(n.title)}</div>
                    <div class="notification-message">${escapeHtml(n.message)}</div>
                    <div class="notification-time">
                        <i class="fas fa-clock"></i> ${escapeHtml(n.time_ago)}
                    </div>
                </div>
                ${!n.is_read ? '<span class="notification-dot"></span>' : ''}
            </a>
        `;
    }
    
    function attachItemHandlers() {
        if (!listEl) return;
        listEl.querySelectorAll('.notification-item').forEach(item => {
            if (item.dataset.boundHandler === '1') return;
            item.dataset.boundHandler = '1';
            
            item.addEventListener('click', function(e) {
                const id = this.dataset.id;
                const link = this.dataset.link;
                const isRead = this.dataset.read === '1';
                
                if (!isRead && id) {
                    markAsRead(id);
                }
                
                if (link && link !== '' && link !== '#' && link !== 'javascript:void(0)') {
                    window.location.href = link;
                }
            });
        });
    }
    
    function markAsRead(id) {
        fetch(API_URL + '?action=mark_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_read', id: parseInt(id) }),
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update item in all lists on page
                document.querySelectorAll(`.notification-item[data-id="${id}"]`).forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    item.dataset.read = '1';
                    const dot = item.querySelector('.notification-dot');
                    if (dot) dot.remove();
                });
                
                if (typeof data.unread_count !== 'undefined') {
                    updateBadge(data.unread_count);
                    lastNotificationCount = data.unread_count;
                } else if (lastNotificationCount > 0) {
                    lastNotificationCount--;
                    updateBadge(lastNotificationCount);
                }
            }
        })
        .catch(() => {});
    }
    
    window.markAllNotificationsRead = function() {
        fetch(API_URL + '?action=mark_all_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' }),
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    item.dataset.read = '1';
                    const dot = item.querySelector('.notification-dot');
                    if (dot) dot.remove();
                });
                
                updateBadge(0);
                lastNotificationCount = 0;
                
                if (typeof showToast === 'function') {
                    showToast('All notifications marked as read', 'success');
                }
            }
        })
        .catch(() => {});
    };
    
    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(checkCount, POLL_INTERVAL);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Expose for compatibility with buttons
    window.markNotificationRead = markAsRead;
    window.markAllAsRead = window.markAllNotificationsRead;
})();