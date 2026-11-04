/**
 * ClassMate - Edit Fund Transaction JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Type Selector =====
    const typeOptions = document.querySelectorAll('.type-option input[type="radio"]');
    
    typeOptions.forEach(input => {
        input.addEventListener('change', function() {
            const type = this.value;
            
            // Update selected state
            document.querySelectorAll('.type-option').forEach(el => {
                el.classList.remove('selected');
            });
            const parent = this.closest('.type-option');
            if (parent) {
                parent.classList.add('selected');
            }
            
            // Show/hide student select for refunds
            const studentSelect = document.getElementById('studentSelect');
            if (type === 'refund') {
                studentSelect.style.display = 'block';
            } else {
                studentSelect.style.display = 'none';
                document.getElementById('student_id').value = '';
            }
        });
    });
    
    // ===== Form Validation =====
    const form = document.querySelector('.transaction-form');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const amount = document.getElementById('amount');
            const description = document.getElementById('description');
            const transactionDate = document.getElementById('transaction_date');
            
            // Validate amount
            if (parseFloat(amount.value) <= 0) {
                e.preventDefault();
                alert('Amount must be greater than 0.');
                amount.focus();
                return false;
            }
            
            // Validate description
            if (description.value.trim() === '') {
                e.preventDefault();
                alert('Description is required.');
                description.focus();
                return false;
            }
            
            // Validate date
            if (transactionDate.value === '') {
                e.preventDefault();
                alert('Transaction date is required.');
                transactionDate.focus();
                return false;
            }
            
            return true;
        });
    }
    
    // ===== Confirmation before leaving =====
    let formChanged = false;
    
    form.querySelectorAll('input, textarea, select').forEach(field => {
        field.addEventListener('change', function() {
            formChanged = true;
        });
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
});