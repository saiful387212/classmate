/**
 * ClassMate - Notification System
 */

class NotificationSystem {
    constructor() {
        this.container = null;
        this.init();
    }
    
    init() {
        this.container = document.querySelector('.toast-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    }
    
    show(message, type = 'info', title = '', duration = 5000) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${icons[type] || icons.info}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title || titles[type] || 'Notice'}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.closest('.toast').remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        this.container.appendChild(toast);
        
        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.add('removing');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }
            }, duration);
        }
        
        return toast;
    }
    
    success(message, title = 'Success') {
        return this.show(message, 'success', title);
    }
    
    error(message, title = 'Error') {
        return this.show(message, 'error', title);
    }
    
    warning(message, title = 'Warning') {
        return this.show(message, 'warning', title);
    }
    
    info(message, title = 'Information') {
        return this.show(message, 'info', title);
    }
}

// Initialize notification system
const notifications = new NotificationSystem();

// Expose global
window.notifications = notifications;
window.showToast = notifications.show.bind(notifications);