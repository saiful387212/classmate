/**
 * ClassMate - Form Validation
 */

class FormValidator {
    constructor(form) {
        this.form = form;
        this.rules = {};
        this.errors = {};
        this.init();
    }
    
    init() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                this.scrollToFirstError();
            }
        });
        
        // Real-time validation on blur
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
            
            field.addEventListener('input', () => {
                const errorEl = this.getErrorElement(field);
                if (errorEl) {
                    errorEl.style.display = 'none';
                }
                field.classList.remove('error', 'success');
            });
        });
    }
    
    addRule(fieldName, rule, message = null) {
        if (!this.rules[fieldName]) {
            this.rules[fieldName] = [];
        }
        this.rules[fieldName].push({ rule, message });
        return this;
    }
    
    validate() {
        this.errors = {};
        let isValid = true;
        
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    validateField(field) {
        const fieldName = field.name || field.id;
        const value = field.value.trim();
        const rules = this.rules[fieldName] || [];
        let isValid = true;
        
        // Check required
        if (field.hasAttribute('required') && !value) {
            this.setError(field, 'This field is required');
            return false;
        }
        
        // Skip validation if field is empty and not required
        if (!value) {
            this.clearError(field);
            return true;
        }
        
        // Apply rules
        for (const ruleObj of rules) {
            const { rule, message } = ruleObj;
            
            switch (rule) {
                case 'email':
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        this.setError(field, message || 'Please enter a valid email address');
                        isValid = false;
                    }
                    break;
                    
                case 'min':
                    const min = parseInt(message);
                    if (value.length < min) {
                        this.setError(field, `Must be at least ${min} characters`);
                        isValid = false;
                    }
                    break;
                    
                case 'max':
                    const max = parseInt(message);
                    if (value.length > max) {
                        this.setError(field, `Must not exceed ${max} characters`);
                        isValid = false;
                    }
                    break;
                    
                case 'numeric':
                    if (!/^\d+$/.test(value)) {
                        this.setError(field, 'Must be a number');
                        isValid = false;
                    }
                    break;
                    
                case 'phone':
                    if (!/^[0-9+\-\s()]{8,15}$/.test(value)) {
                        this.setError(field, 'Please enter a valid phone number');
                        isValid = false;
                    }
                    break;
                    
                case 'date':
                    if (isNaN(Date.parse(value))) {
                        this.setError(field, 'Please enter a valid date');
                        isValid = false;
                    }
                    break;
                    
                case 'match':
                    const matchField = document.getElementById(message);
                    if (matchField && value !== matchField.value.trim()) {
                        this.setError(field, 'Values do not match');
                        isValid = false;
                    }
                    break;
                    
                default:
                    break;
            }
        }
        
        if (isValid) {
            this.clearError(field);
            field.classList.add('success');
        }
        
        return isValid;
    }
    
    setError(field, message) {
        field.classList.add('error');
        field.classList.remove('success');
        
        const errorEl = this.getErrorElement(field);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
    }
    
    clearError(field) {
        field.classList.remove('error');
        
        const errorEl = this.getErrorElement(field);
        if (errorEl) {
            errorEl.style.display = 'none';
        }
    }
    
    getErrorElement(field) {
        const formGroup = field.closest('.form-group');
        if (formGroup) {
            return formGroup.querySelector('.form-error');
        }
        return null;
    }
    
    scrollToFirstError() {
        const firstError = this.form.querySelector('.error');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

// Initialize form validators
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        const validator = new FormValidator(form);
        
        // Add common rules from data attributes
        form.querySelectorAll('[data-validate]').forEach(field => {
            const rules = field.dataset.validate.split('|');
            const fieldName = field.name || field.id;
            
            rules.forEach(rule => {
                const parts = rule.split(':');
                const ruleName = parts[0];
                const ruleValue = parts[1] || null;
                
                validator.addRule(fieldName, ruleName, ruleValue);
            });
        });
    });
});