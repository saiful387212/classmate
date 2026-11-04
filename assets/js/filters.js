/**
 * ClassMate - Unified Filters JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Mobile Filter Toggle =====
    const filterToggle = document.querySelector('.filter-toggle');
    const filterContent = document.querySelector('.filter-toggle-content');
    
    if (filterToggle && filterContent) {
        filterToggle.addEventListener('click', function() {
            const isOpen = filterContent.classList.toggle('open');
            this.innerHTML = isOpen ? 
                '<i class="fas fa-chevron-up"></i> Hide Filters' : 
                '<i class="fas fa-sliders-h"></i> Show Filters <span class="badge badge-info">' + getActiveFilterCount() + '</span>';
        });
    }
    
    // ===== Quick Filter Pills =====
    document.querySelectorAll('.quick-filter-pill').forEach(pill => {
        pill.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href) {
                window.location.href = href;
            }
        });
    });
    
    // ===== Clear Filters =====
    document.querySelectorAll('.clear-filters').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = new URL(window.location.href);
            // Remove all filter parameters
            const filterParams = ['search', 'status', 'course_id', 'type', 'category', 'date_from', 'date_to', 'batch', 'department', 'deadline_from', 'deadline_to'];
            filterParams.forEach(param => {
                url.searchParams.delete(param);
            });
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    });
    
    // ===== Remove Individual Filter =====
    document.querySelectorAll('.remove-filter').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const param = this.dataset.param;
            const value = this.dataset.value;
            const url = new URL(window.location.href);
            
            if (param && url.searchParams.get(param) === value) {
                url.searchParams.delete(param);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }
        });
    });
    
    // ===== Auto-submit on filter change =====
    document.querySelectorAll('.filter-auto-submit').forEach(el => {
        el.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    });
    
    // ===== Debounced Search =====
    document.querySelectorAll('.filter-search-debounce').forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const form = this.closest('form');
                if (form) {
                    form.submit();
                }
            }, 500);
        });
    });
    
    // ===== Count Active Filters =====
    window.getActiveFilterCount = function() {
        const url = new URL(window.location.href);
        const filterParams = ['search', 'status', 'course_id', 'type', 'category', 'date_from', 'date_to', 'batch', 'department', 'deadline_from', 'deadline_to'];
        let count = 0;
        filterParams.forEach(param => {
            if (url.searchParams.get(param)) {
                count++;
            }
        });
        return count;
    };
});

/**
 * Build filter URL with parameters
 */
function buildFilterUrl(baseUrl, filters) {
    const url = new URL(baseUrl);
    Object.keys(filters).forEach(key => {
        if (filters[key] !== null && filters[key] !== '' && filters[key] !== undefined) {
            url.searchParams.set(key, filters[key]);
        } else {
            url.searchParams.delete(key);
        }
    });
    url.searchParams.delete('page');
    return url.toString();
}

/**
 * Get filter value from URL
 */
function getFilterValue(param) {
    const url = new URL(window.location.href);
    return url.searchParams.get(param) || '';
}

// Export for global use
window.buildFilterUrl = buildFilterUrl;
window.getFilterValue = getFilterValue;