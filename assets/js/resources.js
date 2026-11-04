/**
 * ClassMate - Resources JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Auto-submit on filter change
    document.querySelectorAll('.resources-filter-form select').forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Debounced search
    const searchInput = document.querySelector('.search-input-wrapper input');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const form = this.closest('form');
            timeout = setTimeout(() => {
                form.submit();
            }, 600);
        });
    }
});