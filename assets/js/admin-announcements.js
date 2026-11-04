/**
 * ClassMate - Admin Announcements JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Auto-submit filters =====
    document.querySelectorAll('.filter-auto-submit').forEach(el => {
        el.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // ===== Search debounce =====
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const form = this.closest('form');
                if (form) {
                    form.submit();
                }
            }, 500);
        });
    }
    
    // ===== Confirm delete =====
    document.querySelectorAll('[data-confirm-delete]').forEach(el => {
        el.addEventListener('click', function(e) {
            if (!confirm('Delete this announcement? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});