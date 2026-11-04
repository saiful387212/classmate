/**
 * ClassMate - Edit Class JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Toggle Course Form =====
    window.toggleCourseForm = function() {
        const wrapper = document.getElementById('courseFormWrapper');
        if (wrapper.style.display === 'none') {
            wrapper.style.display = 'block';
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            wrapper.style.display = 'none';
        }
    };
    
    // ===== End Time Validation =====
    const startTime = document.getElementById('start_time');
    const endTime = document.getElementById('end_time');
    
    function validateTimes() {
        if (startTime.value && endTime.value) {
            if (endTime.value <= startTime.value) {
                endTime.setCustomValidity('End time must be after start time.');
                endTime.classList.add('error');
            } else {
                endTime.setCustomValidity('');
                endTime.classList.remove('error');
            }
        }
    }
    
    if (startTime && endTime) {
        startTime.addEventListener('change', validateTimes);
        endTime.addEventListener('change', validateTimes);
    }
    
    // ===== Form Validation =====
    const form = document.getElementById('editClassForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const classCode = document.getElementById('class_code');
            const classTitle = document.getElementById('class_title');
            const instructor = document.getElementById('instructor');
            const courseSelect = document.getElementById('course_id');
            
            if (!courseSelect.value) {
                e.preventDefault();
                alert('Please select a course.');
                courseSelect.focus();
                return false;
            }
            
            if (classCode.value.trim().length < 3) {
                e.preventDefault();
                alert('Class code must be at least 3 characters.');
                classCode.focus();
                return false;
            }
            
            if (classTitle.value.trim().length < 3) {
                e.preventDefault();
                alert('Class title must be at least 3 characters.');
                classTitle.focus();
                return false;
            }
            
            if (instructor.value.trim().length < 2) {
                e.preventDefault();
                alert('Please enter the instructor name.');
                instructor.focus();
                return false;
            }
            
            return true;
        });
    }
    
    // ===== Unsaved Changes Warning =====
    let formChanged = false;
    
    if (form) {
        form.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('change', function() {
                formChanged = true;
            });
            
            // Reset formChanged when form is submitted
            if (field.closest('form')) {
                field.closest('form').addEventListener('submit', function() {
                    formChanged = false;
                });
            }
        });
    }
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // ===== Confirm Cancel =====
    document.querySelectorAll('.btn-outline, .btn-info').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (formChanged) {
                if (!confirm('You have unsaved changes. Are you sure you want to leave?')) {
                    e.preventDefault();
                }
            }
        });
    });
});