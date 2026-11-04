/**
 * ClassMate - Fund Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Initialize Charts =====
    const chartElements = document.querySelectorAll('.chart-container canvas');
    chartElements.forEach(canvas => {
        // Charts are initialized in the PHP with Chart.js data
        // This is just a placeholder for any additional chart functionality
    });
    
    // ===== Delete Confirmation =====
    window.confirmDelete = function(description, amount) {
        const modal = document.getElementById('deleteModal');
        if (!modal) return false;
        
        document.getElementById('deleteDescription').textContent = description;
        document.getElementById('deleteAmount').textContent = 'BDT ' + amount;
        
        const confirmBtn = document.getElementById('deleteConfirmBtn');
        const currentUrl = window.location.href;
        const separator = currentUrl.includes('?') ? '&' : '?';
        confirmBtn.href = currentUrl + separator + 'confirm=yes';
        
        openModal('deleteModal');
        return false;
    };
    
    // ===== Auto-refresh balance (optional) =====
    // Refresh the page every 60 seconds to update balances
    if (document.querySelector('.fund-stats-grid')) {
        setTimeout(function() {
            // Only refresh if user is active
            if (!document.hidden) {
                location.reload();
            }
        }, 60000);
    }
});

/**
 * Format currency for display
 */
function formatCurrency(amount) {
    return 'BDT ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Export for use in other scripts
 */
window.formatCurrency = formatCurrency;