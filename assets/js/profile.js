/**
 * ClassMate - Profile JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Toggle Password/PIN Visibility =====
    window.togglePinVisibility = function(inputId) {
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
    
    // ===== Profile Form Validation =====
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name');
            const email = document.getElementById('email');
            
            if (fullName && fullName.value.trim().length < 2) {
                e.preventDefault();
                alert('Please enter your full name (minimum 2 characters).');
                fullName.focus();
                return false;
            }
            
            if (email && email.value.trim() && !isValidEmail(email.value.trim())) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                email.focus();
                return false;
            }
            
            return true;
        });
    }
    
    // ===== Password Form Validation =====
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (!currentPassword.value.trim()) {
                e.preventDefault();
                alert('Please enter your current password.');
                currentPassword.focus();
                return false;
            }
            
            if (newPassword.value.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters.');
                newPassword.focus();
                return false;
            }
            
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('New passwords do not match.');
                confirmPassword.focus();
                return false;
            }
            
            return true;
        });
    }
    
    // ===== PIN Form Validation =====
    const pinForm = document.getElementById('pinForm');
    if (pinForm) {
        pinForm.addEventListener('submit', function(e) {
            const currentPin = document.getElementById('current_pin');
            const newPin = document.getElementById('new_pin');
            const confirmPin = document.getElementById('confirm_pin');
            
            if (!currentPin.value.trim()) {
                e.preventDefault();
                alert('Please enter your current PIN.');
                currentPin.focus();
                return false;
            }
            
            if (newPin.value.length < 6) {
                e.preventDefault();
                alert('New PIN must be at least 6 digits.');
                newPin.focus();
                return false;
            }
            
            if (!/^\d+$/.test(newPin.value)) {
                e.preventDefault();
                alert('PIN must contain only numbers.');
                newPin.focus();
                return false;
            }
            
            if (newPin.value !== confirmPin.value) {
                e.preventDefault();
                alert('New PINs do not match.');
                confirmPin.focus();
                return false;
            }
            
            return true;
        });
    }
    
    // ===== Email Validation Helper =====
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    // ===== PIN Strength Indicator =====
    const newPin = document.getElementById('new_pin');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    if (newPin) {
        newPin.addEventListener('input', function() {
            const pin = this.value;
            let strength = 0;
            
            if (pin.length >= 6) strength += 1;
            if (pin.length >= 8) strength += 1;
            if (pin.length >= 10) strength += 1;
            if (/^\d+$/.test(pin)) strength += 1;
            if (!/(.)\1{2,}/.test(pin)) strength += 1;
            if (!/(012|123|234|345|456|567|678|789|890)/.test(pin)) strength += 1;
            
            strength = Math.min(strength, 5);
            
            if (strengthBar) {
                strengthBar.style.width = (strength / 5 * 100) + '%';
            }
            
            let color, text;
            if (strength === 0) {
                color = '#E5E7EB';
                text = 'Enter a PIN';
            } else if (strength <= 1) {
                color = '#EF4444';
                text = 'Weak - Use at least 6 digits';
            } else if (strength <= 2) {
                color = '#F59E0B';
                text = 'Fair - Add more digits';
            } else if (strength <= 3) {
                color = '#3B82F6';
                text = 'Good - Strong PIN';
            } else {
                color = '#10B981';
                text = 'Strong - Excellent PIN!';
            }
            
            if (strengthBar) {
                strengthBar.style.background = color;
            }
            if (strengthText) {
                strengthText.textContent = text;
                strengthText.style.color = color;
            }
        });
    }
});