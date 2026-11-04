/**
 * ClassMate - Main JavaScript
 * Version: 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Initialize Components =====
    initSidebar();
    initDropdowns();
    initTabs();
    initModals();
    initToastNotifications();
    initFormValidation();
    initConfirmDialogs();
    initMobileNav();
    initSearch();
    initFileUpload();
    
    // ===== Sidebar Toggle =====
    function initSidebar() {
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const header = document.querySelector('.app-header');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
            
            // Close sidebar on overlay click
            if (overlay) {
                overlay.addEventListener('click', function() {
                    closeSidebar();
                });
            }
            
            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        }
        
        // Close sidebar on window resize to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        
        function toggleSidebar() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        function openSidebar() {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Expose functions globally
        window.toggleSidebar = toggleSidebar;
        window.closeSidebar = closeSidebar;
    }
    
    // ===== Dropdowns =====
    function initDropdowns() {
        const dropdownTriggers = document.querySelectorAll('[data-dropdown]');
        
        dropdownTriggers.forEach(trigger => {
            const dropdownId = trigger.dataset.dropdown;
            const dropdown = document.getElementById(dropdownId);
            
            if (dropdown) {
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(d => {
                        if (d.id !== dropdownId) {
                            d.classList.remove('show');
                        }
                    });
                    
                    dropdown.classList.toggle('show');
                    const isOpen = dropdown.classList.contains('show');
                    trigger.classList.toggle('active', isOpen);
                    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                        dropdown.classList.remove('show');
                        trigger.classList.remove('active');
                        trigger.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
        
        // Close dropdowns on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.dropdown-menu.show').forEach(d => {
                    d.classList.remove('show');
                    const trigger = document.querySelector('[data-dropdown="' + d.id + '"]');
                    if (trigger) {
                        trigger.classList.remove('active');
                        trigger.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
    }
    
    // ===== Tabs =====
    function initTabs() {
        const tabGroups = document.querySelectorAll('.tabs');
        
        tabGroups.forEach(group => {
            const tabs = group.querySelectorAll('.tab-item');
            const contents = group.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const target = this.dataset.tab;
                    
                    // Remove active from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Hide all contents
                    contents.forEach(c => c.style.display = 'none');
                    
                    // Show target content
                    const targetContent = document.getElementById(target);
                    if (targetContent) {
                        targetContent.style.display = 'block';
                    }
                });
            });
            
            // Show first tab by default
            if (tabs.length > 0) {
                tabs[0].click();
            }
        });
    }
    
    // ===== Modals =====
    function initModals() {
        // Open modal
        document.querySelectorAll('[data-modal]').forEach(btn => {
            btn.addEventListener('click', function() {
                const modalId = this.dataset.modal;
                const modal = document.getElementById(modalId);
                if (modal) {
                    openModal(modal);
                }
            });
        });
        
        // Close modal
        document.querySelectorAll('.modal-close, .modal-overlay .close-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                if (modal) {
                    closeModal(modal);
                }
            });
        });
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this);
                }
            });
        });
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    closeModal(modal);
                });
            }
        });
        
        function openModal(modal) {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }
            if (!modal) return;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modal) {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }
            if (!modal) return;
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Expose functions globally
        window.openModal = openModal;
        window.closeModal = closeModal;
    }
    
    // ===== Toast Notifications =====
    function initToastNotifications() {
        // Auto-close toasts after 5 seconds
        document.querySelectorAll('.toast:not(.persistent)').forEach(toast => {
            setTimeout(() => {
                removeToast(toast);
            }, 5000);
        });
        
        // Close button
        document.querySelectorAll('.toast .toast-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const toast = this.closest('.toast');
                if (toast) {
                    removeToast(toast);
                }
            });
        });
    }
    
    function removeToast(toast) {
        toast.classList.add('removing');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }
    
    // ===== Form Validation =====
    function initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const requiredFields = this.querySelectorAll('[required]');
                
                requiredFields.forEach(field => {
                    const errorEl = field.closest('.form-group')?.querySelector('.form-error');
                    
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                        
                        if (errorEl) {
                            errorEl.textContent = 'This field is required';
                            errorEl.style.display = 'block';
                        }
                    } else {
                        field.classList.remove('error');
                        if (errorEl) {
                            errorEl.style.display = 'none';
                        }
                    }
                    
                    // Email validation
                    if (field.type === 'email' && field.value.trim()) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(field.value.trim())) {
                            isValid = false;
                            field.classList.add('error');
                            if (errorEl) {
                                errorEl.textContent = 'Please enter a valid email address';
                                errorEl.style.display = 'block';
                            }
                        }
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = form.querySelector('.error');
                    if (firstError) {
                        firstError.focus();
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
            
            // Clear error on input
            form.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('input', function() {
                    this.classList.remove('error');
                    const errorEl = this.closest('.form-group')?.querySelector('.form-error');
                    if (errorEl) {
                        errorEl.style.display = 'none';
                    }
                });
            });
        });
    }
    
    // ===== Confirm Dialogs =====
    function initConfirmDialogs() {
        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', function(e) {
                const message = this.dataset.confirm || 'Are you sure you want to proceed?';
                if (!confirm(message)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        });
    }
    
    // ===== Mobile Navigation =====
    function initMobileNav() {
        const navItems = document.querySelectorAll('.mobile-nav-item');
        
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove active from all
                navItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
    
    // ===== Search =====
    function initSearch() {
        const searchInputs = document.querySelectorAll('.search-input input, .search-bar input');
        
        searchInputs.forEach(input => {
            input.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    this.closest('form')?.submit();
                }
            });
            
            // Debounced search
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    const value = this.value.trim();
                    if (value.length >= 2) {
                        // Trigger search
                        const form = this.closest('form');
                        if (form) {
                            form.submit();
                        }
                    }
                }, 500);
            });
        });
    }
    
    // ===== File Upload =====
    function initFileUpload() {
        document.querySelectorAll('.file-upload input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const preview = this.closest('.file-upload').querySelector('.file-preview');
                    if (preview) {
                        const icon = preview.querySelector('i');
                        const name = preview.querySelector('.file-name');
                        const size = preview.querySelector('.file-size');
                        
                        if (icon) {
                            const ext = file.name.split('.').pop().toLowerCase();
                            const iconMap = {
                                'pdf': 'fa-file-pdf',
                                'doc': 'fa-file-word',
                                'docx': 'fa-file-word',
                                'xls': 'fa-file-excel',
                                'xlsx': 'fa-file-excel',
                                'ppt': 'fa-file-powerpoint',
                                'pptx': 'fa-file-powerpoint',
                                'jpg': 'fa-file-image',
                                'jpeg': 'fa-file-image',
                                'png': 'fa-file-image',
                                'zip': 'fa-file-archive'
                            };
                            icon.className = 'fas ' + (iconMap[ext] || 'fa-file');
                        }
                        
                        if (name) {
                            name.textContent = file.name.length > 30 ? file.name.substring(0, 27) + '...' : file.name;
                        }
                        
                        if (size) {
                            size.textContent = formatFileSize(file.size);
                        }
                        
                        preview.style.display = 'flex';
                    }
                }
            });
        });
    }
    
    // ===== Utility: Format File Size =====
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // ===== Auto-close Alerts =====
    document.querySelectorAll('.alert-dismissible .btn-close').forEach(btn => {
        btn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }
        });
    });
});

// ===== Handle Flash Messages =====
// This function is called from PHP to display flash messages as toasts
function showToast(message, type = 'info', title = '') {
    const container = document.querySelector('.toast-container');
    if (!container) {
        const newContainer = document.createElement('div');
        newContainer.className = 'toast-container';
        document.body.appendChild(newContainer);
    }
    
    const toastContainer = document.querySelector('.toast-container');
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
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
    
    toastContainer.appendChild(toast);
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('removing');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }, 5000);
}

// ===== PIN Toggle =====
function togglePinVisibility(inputId = 'pin') {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const toggleBtn = input.closest('.input-group')?.querySelector('.toggle-pin i');
    if (!toggleBtn) return;
    
    if (input.type === 'password') {
        input.type = 'text';
        toggleBtn.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        toggleBtn.className = 'fas fa-eye';
    }
}