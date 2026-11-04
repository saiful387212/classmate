/**
 * ClassMate - Search Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('headerSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput || !searchResults) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            searchResults.classList.remove('active');
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Close search on click outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.remove('active');
        }
    });
    
    // Close on escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.classList.remove('active');
            searchInput.blur();
        }
    });
    
    function performSearch(query) {
        // Show loading state
        searchResults.innerHTML = '<div class="search-empty"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        searchResults.classList.add('active');
        
        // Make AJAX request
        fetch(`${BASE_URL}/api/search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results.length > 0) {
                    let html = '';
                    data.results.forEach(result => {
                        html += `
                            <a href="${result.url}" class="search-result-item">
                                <i class="fas ${result.icon}"></i>
                                <span class="result-text">${result.title}</span>
                                <span class="result-type">${result.type}</span>
                            </a>
                        `;
                    });
                    searchResults.innerHTML = html;
                } else {
                    searchResults.innerHTML = `
                        <div class="search-empty">
                            <i class="fas fa-search"></i>
                            No results found for "${query}"
                        </div>
                    `;
                }
            })
            .catch(() => {
                searchResults.innerHTML = `
                    <div class="search-empty">
                        <i class="fas fa-exclamation-circle"></i>
                        Error performing search
                    </div>
                `;
            });
    }
});

// Notification functions
function markNotificationRead(id) {
    const baseUrl = window.BASE_URL || '';
    fetch(`${baseUrl}/api/notifications.php?action=mark_read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_read', id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const item = document.querySelector(`.notification-item[data-id="${id}"]`);
            if (item) {
                item.classList.remove('unread');
                item.classList.add('read');
                const dot = item.querySelector('.notification-dot');
                if (dot) dot.remove();
                
                // Update badge count
                updateNotificationBadge();
            }
        }
    });
}

function markAllAsRead() {
    const baseUrl = window.BASE_URL || '';
    fetch(`${baseUrl}/api/notifications.php?action=mark_all_read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_all_read' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const dot = item.querySelector('.notification-dot');
                if (dot) dot.remove();
            });
            updateNotificationBadge();
            showToast('All notifications marked as read', 'success');
        }
    });
}

function updateNotificationBadge() {
    const badge = document.querySelector('.notification-btn .badge');
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    const count = unreadItems.length;
    
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }
}