/**
 * ClassMate - Student Assignments JavaScript
 * Live countdown timers for assignment deadlines
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Initialize all countdown timers
    const countdowns = document.querySelectorAll('.countdown-container');
    
    if (countdowns.length > 0) {
        // Update all countdowns every second
        setInterval(function() {
            countdowns.forEach(function(container) {
                updateCountdown(container);
            });
        }, 1000);
        
        // Initial update
        countdowns.forEach(function(container) {
            updateCountdown(container);
        });
    }
    
    /**
     * Update a single countdown timer
     */
    function updateCountdown(container) {
        const deadlineTimestamp = parseInt(container.dataset.deadline);
        const now = Math.floor(Date.now() / 1000);
        const diff = deadlineTimestamp - now;
        
        const timeElement = container.querySelector('.countdown-time');
        if (!timeElement) return;
        
        let timeString;
        let label;
        
        if (diff <= 0) {
            // Overdue - show how long ago
            const overdue = Math.abs(diff);
            const days = Math.floor(overdue / 86400);
            const hours = Math.floor((overdue % 86400) / 3600);
            const minutes = Math.floor((overdue % 3600) / 60);
            
            if (days > 0) {
                timeString = `${days}d ${hours}h ${minutes}m ago`;
            } else if (hours > 0) {
                timeString = `${hours}h ${minutes}m ago`;
            } else {
                timeString = `${minutes}m ago`;
            }
            
            label = 'Overdue by';
            container.querySelector('.countdown-label').textContent = label;
            container.classList.add('overdue');
        } else {
            // Time remaining
            const days = Math.floor(diff / 86400);
            const hours = Math.floor((diff % 86400) / 3600);
            const minutes = Math.floor((diff % 3600) / 60);
            const seconds = Math.floor(diff % 60);
            
            if (days > 0) {
                timeString = `${days}d ${hours}h ${minutes}m ${seconds}s`;
            } else if (hours > 0) {
                timeString = `${hours}h ${minutes}m ${seconds}s`;
            } else if (minutes > 0) {
                timeString = `${minutes}m ${seconds}s`;
            } else {
                timeString = `${seconds}s`;
            }
            
            label = 'Time Remaining';
            container.querySelector('.countdown-label').textContent = label;
            container.classList.remove('overdue');
        }
        
        // Update time display with animation for visual feedback
        timeElement.textContent = timeString;
        
        // Add a subtle pulse effect when time is critical
        if (diff > 0 && diff < 3600) { // Less than 1 hour
            timeElement.style.color = '#EF4444';
            timeElement.style.fontWeight = '700';
        } else if (diff > 0 && diff < 86400) { // Less than 1 day
            timeElement.style.color = '#F59E0B';
            timeElement.style.fontWeight = '600';
        } else {
            timeElement.style.color = '';
            timeElement.style.fontWeight = '';
        }
    }
});

/**
 * Format time remaining for static display (fallback)
 */
function formatTimeRemaining(deadlineTimestamp) {
    const now = Math.floor(Date.now() / 1000);
    const diff = deadlineTimestamp - now;
    
    if (diff <= 0) {
        return 'Overdue';
    }
    
    const days = Math.floor(diff / 86400);
    const hours = Math.floor((diff % 86400) / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    
    if (days > 0) {
        return `${days}d ${hours}h ${minutes}m`;
    } else if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
}

// Export for use in other scripts
window.formatTimeRemaining = formatTimeRemaining;