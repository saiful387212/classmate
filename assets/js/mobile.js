/**
 * ClassMate - Mobile Enhancements
 * Handles mobile-specific interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // ===== Detect if on mobile =====
    const isMobile = window.innerWidth <= 768;
    const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    // ===== Mobile Search Toggle =====
    const searchToggle = document.querySelector('.mobile-search-toggle');
    const searchOverlay = document.querySelector('.mobile-search-overlay');
    const closeSearch = document.querySelector('.close-search');
    
    if (searchToggle && searchOverlay) {
        searchToggle.addEventListener('click', function() {
            searchOverlay.classList.add('active');
            const input = searchOverlay.querySelector('input');
            if (input) {
                setTimeout(() => input.focus(), 100);
            }
        });
        
        if (closeSearch) {
            closeSearch.addEventListener('click', function() {
                searchOverlay.classList.remove('active');
            });
        }
        
        searchOverlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
        
        // Close on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && searchOverlay.classList.contains('active')) {
                searchOverlay.classList.remove('active');
            }
        });
    }
    
    // ===== Mobile Sidebar Swipe =====
    if (isTouch) {
        let startX = 0;
        let currentX = 0;
        let isSwiping = false;
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (sidebar) {
            document.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                if (startX < 30 && !sidebar.classList.contains('open')) {
                    isSwiping = true;
                }
            }, { passive: true });
            
            document.addEventListener('touchmove', function(e) {
                if (!isSwiping) return;
                currentX = e.touches[0].clientX;
                const diff = currentX - startX;
                if (diff > 10) {
                    openSidebar();
                    isSwiping = false;
                }
            }, { passive: true });
            
            document.addEventListener('touchend', function() {
                isSwiping = false;
            }, { passive: true });
        }
    }
    
    // ===== Better Touch Feedback =====
    if (isTouch) {
        document.querySelectorAll('.btn, .nav-link, .action-item, .mobile-nav-item').forEach(el => {
            el.addEventListener('touchstart', function() {
                this.style.transition = 'transform 0.1s ease';
            }, { passive: true });
            
            el.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            }, { passive: true });
        });
    }
    
    // ===== Mobile Viewport Fix =====
    function fixViewport() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', vh + 'px');
        
        // Fix for mobile browsers with address bar
        const appContainer = document.querySelector('.app-container');
        if (appContainer) {
            appContainer.style.minHeight = 'calc(var(--vh, 1vh) * 100)';
        }
    }
    
    fixViewport();
    window.addEventListener('resize', fixViewport);
    
    // ===== Prevent iOS Zoom on Inputs =====
    if (isMobile) {
        document.querySelectorAll('input, select, textarea').forEach(el => {
            el.style.fontSize = '16px';
        });
    }
    
    // ===== Smooth Scroll for Mobile =====
    if (isMobile) {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }
});

// ===== Expose mobile functions =====
window.openMobileSearch = function() {
    const overlay = document.querySelector('.mobile-search-overlay');
    if (overlay) {
        overlay.classList.add('active');
        const input = overlay.querySelector('input');
        if (input) {
            setTimeout(() => input.focus(), 100);
        }
    }
};

window.closeMobileSearch = function() {
    const overlay = document.querySelector('.mobile-search-overlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
};