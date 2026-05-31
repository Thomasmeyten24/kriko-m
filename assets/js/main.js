/**
 * Scouts Kriko-M - Main Interactivity (Vanilla JS)
 * Manages responsive layout triggers, alert banners, and animation scroll reveals.
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initAnnouncements();
    initScrollReveal();
});

/**
 * Responsive mobile navigation toggle
 */
function initMobileMenu() {
    const navToggle = document.querySelector('.mobile-nav-toggle');
    const navMenu = document.querySelector('.nav');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            navMenu.classList.toggle('open');
            
            // Toggle hamburger icon (optional swap representation)
            const isOpen = navMenu.classList.contains('open');
            navToggle.innerHTML = isOpen ? '&times;' : '&#9776;';
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navMenu.contains(e.target) && !navToggle.contains(e.target)) {
                navMenu.classList.remove('open');
                navToggle.innerHTML = '&#9776;';
            }
        });
    }
}

/**
 * Handle closing and caching of top announcement banner
 */
function initAnnouncements() {
    const alertClose = document.querySelector('.alert-close');
    const alertBanner = document.querySelector('.alert-banner');
    
    if (alertClose && alertBanner) {
        // Check if banner was already closed in this session
        const isClosed = sessionStorage.getItem('kriko_alert_closed');
        if (isClosed) {
            alertBanner.style.display = 'none';
        }
        
        alertClose.addEventListener('click', () => {
            alertBanner.style.transition = 'opacity 0.3s ease, margin-top 0.3s ease';
            alertBanner.style.opacity = '0';
            setTimeout(() => {
                alertBanner.style.display = 'none';
                sessionStorage.setItem('kriko_alert_closed', 'true');
            }, 300);
        });
    }
}

/**
 * Simple fade-in animation on scroll reveal
 */
function initScrollReveal() {
    const revealElements = document.querySelectorAll('.tak-card, .calendar-item, .shop-card');
    
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.05,
            rootMargin: '0px 0px -50px 0px'
        });
        
        revealElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            observer.observe(el);
        });
    } else {
        // Fallback for older browsers
        revealElements.forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });
    }
}
