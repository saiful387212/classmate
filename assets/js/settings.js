/**
 * ClassMate - Settings JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Tab Navigation =====
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all tabs
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active to current tab
            this.classList.add('active');
            const tabId = this.dataset.tab;
            const content = document.getElementById('tab-' + tabId);
            if (content) {
                content.classList.add('active');
            }
        });
    });
    
    // ===== Toggle Password Visibility =====
    window.togglePasswordVisibility = function(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        const toggleBtn = input.closest('.input-group').querySelector('.toggle-pin i');
        if (!toggleBtn) return;
        
        if (input.type === 'password') {
            input.type = 'text';
            toggleBtn.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            toggleBtn.className = 'fas fa-eye';
        }
    };
    
    // ===== Clear All Sessions =====
    window.clearAllSessions = function() {
        if (!confirm('Are you sure you want to clear all user sessions? This will log out all users.')) {
            return;
        }
        
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        
        fetch('<?php echo url('api/session-api.php'); ?>?action=clear_all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('All sessions cleared successfully!');
                location.reload();
            } else {
                alert('Failed to clear sessions: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => {
            alert('An error occurred. Please try again.');
        });
    };
    
    // ===== Clear Cache =====
    window.clearCache = function() {
        if (!confirm('Clear system cache?')) {
            return;
        }
        
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        
        fetch('<?php echo url('api/cache-api.php'); ?>?action=clear', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cache cleared successfully!');
            } else {
                alert('Failed to clear cache: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(() => {
            alert('An error occurred. Please try again.');
        });
    };
    
    // ===== Profile Form Validation =====
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword || confirmPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password to change it.');
                    return false;
                }
                
                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('New password must be at least 6 characters.');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match.');
                    return false;
                }
            }
            
            return true;
        });
    }
    
    // ===== Switch Auto-Submit =====
    document.querySelectorAll('.switch input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Set value to 1 if checked, else 0
            this.value = this.checked ? '1' : '0';
            
            // Find the parent form
            const form = this.closest('form');
            if (form) {
                // Auto-submit the form after a short delay
                clearTimeout(this.submitTimeout);
                this.submitTimeout = setTimeout(() => {
                    form.submit();
                }, 500);
            }
        });
    });
    
    // ===== System Info Refresh =====
    const refreshBtn = document.querySelector('.refresh-system-info');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            location.reload();
        });
    }
});