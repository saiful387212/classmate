/**
 * ClassMate - Registration JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Toggle PIN Visibility =====
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
    
    // ===== PIN Strength Meter =====
    const pinInput = document.getElementById('pin');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    if (pinInput) {
        pinInput.addEventListener('input', function() {
            const pin = this.value;
            const strength = checkPINStrength(pin);
            updateStrengthMeter(strength);
        });
    }
    
    function checkPINStrength(pin) {
        let strength = 0;
        
        if (pin.length >= 6) strength += 1;
        if (pin.length >= 8) strength += 1;
        if (pin.length >= 10) strength += 1;
        if (/^[0-9]+$/.test(pin)) strength += 1;
        if (!/(.)\1{2,}/.test(pin)) strength += 1;
        if (!/(012|123|234|345|456|567|678|789|890)/.test(pin)) strength += 1;
        
        return Math.min(strength, 5);
    }
    
    function updateStrengthMeter(strength) {
        const percentage = (strength / 5) * 100;
        
        if (strengthBar) {
            strengthBar.style.width = percentage + '%';
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
    }
});

// ===== Export for global use =====
window.checkPINStrength = checkPINStrength;
window.updateStrengthMeter = updateStrengthMeter;