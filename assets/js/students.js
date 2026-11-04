/**
 * ClassMate - Student Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== PIN Reset Modal =====
    window.openResetPinModal = function(studentId, studentName) {
        document.getElementById('resetStudentId').value = studentId;
        document.getElementById('resetStudentName').textContent = studentName;
        document.getElementById('new_pin').value = '';
        openModal('resetPinModal');
    };
    
    // ===== Generate Random PIN =====
    window.generatePIN = function() {
        const pin = String(Math.floor(100000 + Math.random() * 900000));
        document.getElementById('new_pin').value = pin;
    };
    
    // ===== PIN Validation =====
    const pinForm = document.getElementById('resetPinForm');
    if (pinForm) {
        pinForm.addEventListener('submit', function(e) {
            const pin = document.getElementById('new_pin').value;
            if (!/^\d{6}$/.test(pin)) {
                e.preventDefault();
                alert('Please enter a valid 6-digit PIN.');
                return false;
            }
            
            // Confirm with admin
            if (!confirm('Reset PIN for this student? The student will need this new PIN to login.')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
    
    // ===== Registration Approve/Reject =====
    window.openApproveModal = function(id, name) {
        document.getElementById('approveName').textContent = name;
        document.getElementById('approveForm').action = 'registrations.php?action=approve&id=' + id;
        document.getElementById('approveNotes').value = '';
        openModal('approveModal');
    };
    
    window.openRejectModal = function(id, name) {
        document.getElementById('rejectName').textContent = name;
        document.getElementById('rejectForm').action = 'registrations.php?action=reject&id=' + id;
        document.getElementById('rejectNotes').value = '';
        openModal('rejectModal');
    };
    
    // ===== Confirm Actions =====
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
});

/**
 * Format student status for display
 */
function getStatusBadge(status) {
    const badges = {
        'active': 'success',
        'inactive': 'warning',
        'pending': 'warning',
        'suspended': 'danger',
        'rejected': 'danger'
    };
    return badges[status] || 'secondary';
}

/**
 * Format student status label
 */
function getStatusLabel(status) {
    const labels = {
        'active': 'Active',
        'inactive': 'Inactive',
        'pending': 'Pending',
        'suspended': 'Suspended',
        'rejected': 'Rejected'
    };
    return labels[status] || status;
}

// Export for global use
window.getStatusBadge = getStatusBadge;
window.getStatusLabel = getStatusLabel;