/**
 * Custom JavaScript for Alumni Network Management System
 */

// Global variables
let currentUser = null;
let notifications = [];

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Initialize tooltips
    initializeTooltips();

    // Initialize form validations
    initializeFormValidations();

    // Initialize notifications
    initializeNotifications();

    // Initialize search functionality
    initializeSearch();

    // Initialize lazy loading for images
    initializeLazyLoading();

    // Initialize smooth scrolling
    initializeSmoothScrolling();

    // Initialize auto-hide alerts
    initializeAutoHideAlerts();
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize form validations
 */
function initializeFormValidations() {
    // Custom validation for all forms
    const forms = document.querySelectorAll('.needs-validation');

    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Real-time validation feedback
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('blur', function () {
            validateField(this);
        });

        input.addEventListener('input', function () {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
}

/**
 * Validate individual form field
 */
function validateField(field) {
    const isValid = field.checkValidity();

    if (isValid) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
    }

    return isValid;
}

/**
 * Initialize notifications system
 */
function initializeNotifications() {
    // Check for new notifications every 5 minutes
    setInterval(checkNotifications, 300000);

    // Initial check
    checkNotifications();
}

/**
 * Check for new notifications
 */
function checkNotifications() {
    // This would typically make an AJAX call to get notifications
    // For now, we'll just update the UI if there are existing notification badges
    updateNotificationBadges();
}

/**
 * Update notification badges
 */
function updateNotificationBadges() {
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => {
        // Add animation when badge updates
        badge.classList.add('animate-bounce');
        setTimeout(() => {
            badge.classList.remove('animate-bounce');
        }, 1000);
    });
}

/**
 * Show notification toast
 */
function showNotification(message, type = 'info') {
    const toastContainer = getOrCreateToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${getIconForType(type)} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    toastContainer.appendChild(toast);

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Remove toast element after it's hidden
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

/**
 * Get or create toast container
 */
function getOrCreateToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1055';
        document.body.appendChild(container);
    }
    return container;
}

/**
 * Get icon for notification type
 */
function getIconForType(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle',
        'primary': 'bell'
    };
    return icons[type] || 'info-circle';
}

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInputs = document.querySelectorAll('.search-input');

    searchInputs.forEach(input => {
        let searchTimeout;

        input.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    performSearch(query, this);
                }, 300);
            }
        });
    });
}

/**
 * Perform search operation
 */
function performSearch(query, inputElement) {
    const searchType = inputElement.dataset.searchType || 'general';

    // Show loading indicator
    showSearchLoading(inputElement);

    // This would typically make an AJAX call
    setTimeout(() => {
        hideSearchLoading(inputElement);
        // Handle search results
    }, 500);
}

/**
 * Show search loading indicator
 */
function showSearchLoading(inputElement) {
    const loadingIcon = inputElement.parentElement.querySelector('.search-loading');
    if (loadingIcon) {
        loadingIcon.style.display = 'inline-block';
    }
}

/**
 * Hide search loading indicator
 */
function hideSearchLoading(inputElement) {
    const loadingIcon = inputElement.parentElement.querySelector('.search-loading');
    if (loadingIcon) {
        loadingIcon.style.display = 'none';
    }
}

/**
 * Initialize lazy loading for images
 */
function initializeLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');

    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

/**
 * Initialize smooth scrolling
 */
function initializeSmoothScrolling() {
    const links = document.querySelectorAll('a[href^="#"]');

    links.forEach(link => {
        link.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

/**
 * Initialize auto-hide alerts
 */
function initializeAutoHideAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');

    alerts.forEach(alert => {
        // Auto-hide after 5 seconds
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
}

/**
 * Format date for display
 */
function formatDate(dateString, format = 'full') {
    const date = new Date(dateString);
    const options = {
        full: {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        },
        short: {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        },
        time: {
            hour: '2-digit',
            minute: '2-digit'
        }
    };

    return date.toLocaleDateString('en-US', options[format]);
}

/**
 * Format number with comma
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Debounce function
 */
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;

        const later = function () {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };

        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);

        if (callNow) func.apply(context, args);
    };
}

/**
 * Throttle function
 */
function throttle(func, limit) {
    let inThrottle;
    return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Copy text to clipboard
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showNotification('Copied to clipboard!', 'success');
    } catch (err) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Copied to clipboard!', 'success');
    }
}

/**
 * Animate counter numbers
 */
function animateCounter(element, target, duration = 1000) {
    const start = parseInt(element.textContent) || 0;
    const range = target - start;
    const startTime = performance.now();

    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function (easeOutCubic)
        const easeOutCubic = 1 - Math.pow(1 - progress, 3);

        const current = Math.floor(start + (range * easeOutCubic));
        element.textContent = formatNumber(current);

        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        } else {
            element.textContent = formatNumber(target);
        }
    }

    requestAnimationFrame(updateCounter);
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate phone number format
 */
function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
}

/**
 * Show loading spinner
 */
function showLoading(element) {
    const originalContent = element.innerHTML;
    element.dataset.originalContent = originalContent;
    element.innerHTML = '<span class="loading"></span> Loading...';
    element.disabled = true;
}

/**
 * Hide loading spinner
 */
function hideLoading(element) {
    const originalContent = element.dataset.originalContent;
    if (originalContent) {
        element.innerHTML = originalContent;
        delete element.dataset.originalContent;
    }
    element.disabled = false;
}

/**
 * Handle AJAX errors
 */
function handleAjaxError(xhr, status, error) {
    console.error('AJAX Error:', status, error);

    let message = 'An error occurred. Please try again.';

    if (xhr.status === 404) {
        message = 'The requested resource was not found.';
    } else if (xhr.status === 500) {
        message = 'Server error. Please try again later.';
    } else if (xhr.status === 0) {
        message = 'Network connection error. Please check your internet connection.';
    }

    showNotification(message, 'danger');
}

/**
 * Export utility functions to global scope
 */
window.AlumniNetwork = {
    showNotification,
    formatDate,
    formatNumber,
    copyToClipboard,
    animateCounter,
    isValidEmail,
    isValidPhone,
    showLoading,
    hideLoading,
    debounce,
    throttle
};