// HSMS Ethiopia - Main JavaScript File
// Version 1.0.0

document.addEventListener('DOMContentLoaded', function() {

    // --- Sidebar Toggle ---
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle-btn');
    const mainContent = document.querySelector('.main-content');
    const mobileOverlay = document.createElement('div');

    // Create mobile overlay for better UX on small screens
    mobileOverlay.className = 'mobile-sidebar-overlay';
    mobileOverlay.style.cssText = `
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 998;
    `;
    document.body.appendChild(mobileOverlay);

    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const isSidebarOpen = sidebar.style.width === '240px';

            if (isSidebarOpen) {
                // Close sidebar
                sidebar.style.width = '0';
                mainContent.style.marginLeft = '0';
                mobileOverlay.style.display = 'none';
                document.body.style.overflow = 'auto';
            } else {
                // Open sidebar
                sidebar.style.width = '240px';
                if (window.innerWidth <= 768) {
                    mainContent.style.marginLeft = '0';
                    mobileOverlay.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    mainContent.style.marginLeft = '240px';
                }
            }

            // Toggle aria-expanded attribute for accessibility
            this.setAttribute('aria-expanded', !isSidebarOpen);
        });

        // Close sidebar when clicking on overlay
        mobileOverlay.addEventListener('click', function() {
            sidebar.style.width = '0';
            mainContent.style.marginLeft = '0';
            this.style.display = 'none';
            document.body.style.overflow = 'auto';
            sidebarToggle.setAttribute('aria-expanded', 'false');
        });

        // Handle responsive behavior
        function handleResize() {
            if (window.innerWidth > 768) {
                // Desktop: show sidebar by default
                sidebar.style.width = '240px';
                mainContent.style.marginLeft = '240px';
                mobileOverlay.style.display = 'none';
                document.body.style.overflow = 'auto';
            } else {
                // Mobile: hide sidebar by default
                sidebar.style.width = '0';
                mainContent.style.marginLeft = '0';
                mobileOverlay.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Initialize and add resize listener
        handleResize();
        window.addEventListener('resize', handleResize);
    }

    // --- Dropdown Menus ---
    document.querySelectorAll('.dropdown-toggle').forEach(item => {
        item.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    if (menu !== dropdownMenu) {
                        menu.style.display = 'none';
                        menu.previousElementSibling.classList.remove('active');
                    }
                });

                // Toggle current dropdown
                const isVisible = dropdownMenu.style.display === 'block';
                dropdownMenu.style.display = isVisible ? 'none' : 'block';
                this.classList.toggle('active', !isVisible);

                // Position dropdown
                positionDropdown(dropdownMenu, this);
            }
        });
    });

    // Function to position dropdowns properly
    function positionDropdown(dropdown, toggle) {
        const rect = toggle.getBoundingClientRect();
        const viewportHeight = window.innerHeight;

        // Check if dropdown would overflow bottom of viewport
        const dropdownHeight = dropdown.offsetHeight;
        const spaceBelow = viewportHeight - rect.bottom;

        if (spaceBelow < dropdownHeight && rect.top > dropdownHeight) {
            // Position above toggle if there's not enough space below
            dropdown.style.bottom = '100%';
            dropdown.style.top = 'auto';
        } else {
            // Default position below
            dropdown.style.top = '100%';
            dropdown.style.bottom = 'auto';
        }
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
                if (menu.previousElementSibling) {
                    menu.previousElementSibling.classList.remove('active');
                }
            });
        }
    });

    // Close dropdowns on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
                if (menu.previousElementSibling) {
                    menu.previousElementSibling.classList.remove('active');
                }
            });
        }
    });

    // --- Modal System ---
    class Modal {
        constructor(modalId) {
            this.modal = document.getElementById(modalId);
            if (!this.modal) return;

            this.closeButtons = this.modal.querySelectorAll('.modal-close, [data-dismiss="modal"]');
            this.overlay = this.modal.querySelector('.modal-overlay');
            this.content = this.modal.querySelector('.modal-content');

            this.init();
        }

        init() {
            // Close buttons
            this.closeButtons.forEach(btn => {
                btn.addEventListener('click', () => this.close());
            });

            // Close on overlay click
            if (this.overlay) {
                this.overlay.addEventListener('click', (e) => {
                    if (e.target === this.overlay) this.close();
                });
            }

            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                    this.close();
                }
            });
        }

        open() {
            this.modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        close() {
            this.modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }

    // Initialize all modals
    document.querySelectorAll('.modal').forEach(modal => {
        new Modal(modal.id);
    });

    // Open modal triggers
    document.querySelectorAll('[data-toggle="modal"]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-target');
            if (modalId) {
                const modal = document.querySelector(modalId);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        });
    });

    // --- Enhanced Form Validation ---
    document.querySelectorAll('form').forEach(form => {
        // Real-time validation
        form.querySelectorAll('[required]').forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });

            input.addEventListener('input', function() {
                // Clear error as user types
                if (this.value.trim()) {
                    clearError(this);
                }
            });
        });

        // Form submission validation
        form.addEventListener('submit', function(event) {
            if (!validateForm(this)) {
                event.preventDefault();
                // Focus on first invalid field
                const firstInvalid = this.querySelector('.error');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });

    function validateField(field) {
        const value = field.value.trim();
        const errorElement = field.parentElement.querySelector('.error-message') || createErrorElement(field);

        // Clear previous error
        clearError(field);

        // Required validation
        if (field.hasAttribute('required') && !value) {
            showError(field, 'This field is required');
            return false;
        }

        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showError(field, 'Please enter a valid email address');
                return false;
            }
        }

        // Password strength (if needed)
        if (field.type === 'password' && value) {
            if (value.length < 8) {
                showError(field, 'Password must be at least 8 characters');
                return false;
            }
        }

        // Custom pattern validation
        if (field.getAttribute('pattern')) {
            const pattern = new RegExp(field.getAttribute('pattern'));
            if (value && !pattern.test(value)) {
                const customMessage = field.getAttribute('data-error') || 'Invalid format';
                showError(field, customMessage);
                return false;
            }
        }

        return true;
    }

    function validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            // Show error summary
            const errorCount = form.querySelectorAll('.error').length;
            showToast(`Please correct ${errorCount} error${errorCount > 1 ? 's' : ''} in the form`, 'error');
        }

        return isValid;
    }

    function createErrorElement(field) {
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        field.parentElement.appendChild(errorElement);
        return errorElement;
    }

    function showError(field, message) {
        field.classList.add('error');
        const errorElement = field.parentElement.querySelector('.error-message');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }

    function clearError(field) {
        field.classList.remove('error');
        const errorElement = field.parentElement.querySelector('.error-message');
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    }

    // --- Active Sidebar Link with Nested Menu Support ---
    function setActiveSidebarLink() {
        const currentPath = window.location.pathname;
        const currentUrl = window.location.href;

        // Find all sidebar links
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        let activeFound = false;

        sidebarLinks.forEach(link => {
            link.classList.remove('active');

            // Check if current URL matches link href
            if (link.href === currentUrl) {
                link.classList.add('active');
                expandParentMenu(link);
                activeFound = true;
            }
        });

        // If no exact match, check for partial matches
        if (!activeFound) {
            sidebarLinks.forEach(link => {
                const linkPath = new URL(link.href).pathname;
                if (currentPath.startsWith(linkPath) && linkPath !== '/') {
                    link.classList.add('active');
                    expandParentMenu(link);
                }
            });
        }
    }

    function expandParentMenu(link) {
        let parent = link.closest('.has-submenu');
        while (parent) {
            parent.classList.add('expanded');
            const toggle = parent.querySelector('.submenu-toggle');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
            parent = parent.parentElement.closest('.has-submenu');
        }
    }

    // Initialize active sidebar link
    setActiveSidebarLink();

    // --- Submenu Toggle ---
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const parent = this.closest('.has-submenu');
            const isExpanded = parent.classList.contains('expanded');

            // Close all other submenus at same level
            if (!isExpanded) {
                const siblings = parent.parentElement.querySelectorAll('.has-submenu');
                siblings.forEach(sibling => {
                    if (sibling !== parent) {
                        sibling.classList.remove('expanded');
                        sibling.querySelector('.submenu-toggle')?.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // Toggle current submenu
            parent.classList.toggle('expanded');
            this.setAttribute('aria-expanded', !isExpanded);
        });
    });

    // --- Toast Notification System ---
    function showToast(message, type = 'info', duration = 5000) {
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-message">${message}</span>
                <button class="toast-close">&times;</button>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Add show class after a delay
        setTimeout(() => toast.classList.add('show'), 10);

        // Auto remove
        const autoRemove = setTimeout(() => {
            removeToast(toast);
        }, duration);

        // Manual close
        toast.querySelector('.toast-close').addEventListener('click', () => {
            clearTimeout(autoRemove);
            removeToast(toast);
        });
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        `;
        document.body.appendChild(container);
        return container;
    }

    function removeToast(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 300);
    }

    // --- AJAX Form Submission ---
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('[type="submit"]');
            const originalText = submitBtn.textContent;
            const formData = new FormData(this);

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            try {
                const response = await fetch(this.action, {
                    method: this.method,
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message || 'Success!', 'success');

                    // If form has data-reset attribute, reset it
                    if (this.hasAttribute('data-reset')) {
                        this.reset();
                    }

                    // If form has data-redirect attribute, redirect
                    if (this.hasAttribute('data-redirect')) {
                        setTimeout(() => {
                            window.location.href = this.getAttribute('data-redirect');
                        }, 1500);
                    }
                } else {
                    showToast(result.message || 'Error occurred!', 'error');

                    // Show field-specific errors if available
                    if (result.errors) {
                        Object.keys(result.errors).forEach(fieldName => {
                            const field = this.querySelector(`[name="${fieldName}"]`);
                            if (field) {
                                showError(field, result.errors[fieldName]);
                            }
                        });
                    }
                }
            } catch (error) {
                showToast('Network error occurred. Please try again.', 'error');
            } finally {
                // Restore button state
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    });

    // --- Data Table Enhancements ---
    if (document.querySelector('.data-table')) {
        // Add sorting functionality
        document.querySelectorAll('.data-table th[data-sortable]').forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(this);
            });
        });
    }

    function sortTable(header) {
        const table = header.closest('table');
        const columnIndex = Array.from(header.parentElement.children).indexOf(header);
        const isAscending = header.classList.toggle('asc');

        // Clear other sort indicators
        table.querySelectorAll('th[data-sortable]').forEach(th => {
            if (th !== header) {
                th.classList.remove('asc', 'desc');
            }
        });

        header.classList.toggle('desc', !isAscending);

        // Get all rows except header
        const rows = Array.from(table.querySelectorAll('tbody tr'));

        rows.sort((a, b) => {
            const aValue = a.children[columnIndex].textContent.trim();
            const bValue = b.children[columnIndex].textContent.trim();

            // Try numeric comparison first
            const aNum = parseFloat(aValue.replace(/[^0-9.-]+/g, ''));
            const bNum = parseFloat(bValue.replace(/[^0-9.-]+/g, ''));

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAscending ? aNum - bNum : bNum - aNum;
            }

            // Fallback to string comparison
            return isAscending
                ? aValue.localeCompare(bValue)
                : bValue.localeCompare(aValue);
        });

        // Reorder rows
        const tbody = table.querySelector('tbody');
        rows.forEach(row => tbody.appendChild(row));
    }

    // --- Utility Functions ---

    // Debounce function for resize events
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Format date
    function formatDate(date, format = 'dd/mm/yyyy') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();

        return format
            .replace('dd', day)
            .replace('mm', month)
            .replace('yyyy', year);
    }

    // Expose utility functions to global scope
    window.HSMS = {
        showToast,
        formatDate,
        Modal
    };

    // Initialize all tooltips if Bootstrap is not available
    if (typeof bootstrap === 'undefined') {
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.title;
                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';

                this._tooltip = tooltip;
            });

            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    delete this._tooltip;
                }
            });
        });
    }

    // Print functionality
    document.querySelectorAll('[data-print]').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-print');
            const element = target ? document.querySelector(target) : document.body;

            if (element) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Print - HSMS Ethiopia</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                @media print {
                                    @page { margin: 0; }
                                    body { margin: 1.6cm; }
                                }
                            </style>
                        </head>
                        <body>
                            ${element.innerHTML}
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            }
        });
    });

    // Initialize components
    console.log('HSMS Ethiopia JavaScript initialized successfully.');
});

// Export for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {};
}