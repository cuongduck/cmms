/**
 * CMMS Main JavaScript
 * Global utilities and common functionality
 */

// Extend CMMS object with additional utilities
if (!window.CMMS) {
    window.CMMS = {};
}
Object.assign(window.CMMS, {
    baseUrl: '<?php echo APP_URL; ?>',
    // Escape HTML
    escapeHtml: function(text) {
        if (text == null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },    
    // Show toast notification
    showToast: function(message, type = 'info') {
        const toast = document.getElementById('liveToast');
        const toastBody = toast.querySelector('.toast-body');
        const toastHeader = toast.querySelector('.toast-header');
        const icon = toastHeader.querySelector('i');
        
        // Update content
        toastBody.textContent = message;
        
        // Update icon and color based on type
        icon.className = 'me-2';
        switch(type) {
            case 'success':
                icon.classList.add('fas', 'fa-check-circle', 'text-success');
                break;
            case 'error':
                icon.classList.add('fas', 'fa-exclamation-circle', 'text-danger');
                break;
            case 'warning':
                icon.classList.add('fas', 'fa-exclamation-triangle', 'text-warning');
                break;
            default:
                icon.classList.add('fas', 'fa-info-circle', 'text-primary');
        }
        
        // Show toast
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    },
    
    // Show loading overlay
    showLoading: function() {
        document.getElementById('loadingOverlay').classList.remove('d-none');
    },
    
    // Hide loading overlay
    hideLoading: function() {
        document.getElementById('loadingOverlay').classList.add('d-none');
    },
    
    // Confirm dialog
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    // AJAX helper
ajax: function(options) {
    const defaults = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    options = Object.assign(defaults, options);
    
    // Nếu body là FormData, xóa Content-Type để browser set multipart/form-data
    if (options.body instanceof FormData) {
        delete options.headers['Content-Type'];
    }
    
    this.showLoading();
    
    fetch(options.url, options)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            this.hideLoading();
            if (options.success) {
                options.success(data);
            }
        })
        .catch(error => {
            this.hideLoading();
            console.error('Ajax error:', error, 'URL:', options.url);
            this.showToast(`Có lỗi xảy ra: ${error.message}`, 'error');
            if (options.error) {
                options.error(error);
            }
        });
},   
    // Format number
    formatNumber: function(number, decimals = 0) {
        return new Intl.NumberFormat('vi-VN', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    },
    
    // Format date
// Format date
formatDate: function(dateString, format = 'dd/MM/yyyy') {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
},
    
    // Format datetime
// Format datetime  
formatDateTime: function(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('vi-VN');
},
    
    // Validate form
    validateForm: function(formElement) {
        const form = typeof formElement === 'string' ? 
            document.getElementById(formElement) : formElement;
        
        return form.checkValidity();
    },
    
    // Submit form via AJAX
    submitForm: function(formElement, options = {}) {
        const form = typeof formElement === 'string' ? 
            document.getElementById(formElement) : formElement;
        
        if (!this.validateForm(form)) {
            form.classList.add('was-validated');
            return false;
        }
        
        const formData = new FormData(form);
        
        this.ajax({
            url: form.action || window.location.href,
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(data) {
                if (data.success) {
                    CMMS.showToast(data.message, 'success');
                    if (options.onSuccess) {
                        options.onSuccess(data);
                    } else if (options.redirect) {
                        setTimeout(() => {
                            window.location.href = options.redirect;
                        }, 1500);
                    }
                } else {
                    CMMS.showToast(data.message, 'error');
                    if (options.onError) {
                        options.onError(data);
                    }
                }
            },
            error: options.onError
        });
        
        return false;
    },
    
    // Delete confirmation
    deleteItem: function(url, message = 'Bạn có chắc chắn muốn xóa?') {
        this.confirm(message, function() {
            CMMS.ajax({
                url: url,
                method: 'POST',
                body: 'action=delete',
                success: function(data) {
                    if (data.success) {
                        CMMS.showToast(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        CMMS.showToast(data.message, 'error');
                    }
                }
            });
        });
    },
    
    // Data table utilities
    dataTable: {
        init: function(tableId, options = {}) {
            const table = document.getElementById(tableId);
            if (!table) return null;
            
            const defaults = {
                searching: true,
                sorting: true,
                pagination: true,
                pageSize: 20,
                responsive: true
            };
            
            const config = Object.assign(defaults, options);
            
            // Add search functionality
            if (config.searching) {
                this.addSearch(table);
            }
            
            // Add sorting functionality
            if (config.sorting) {
                this.addSorting(table);
            }
            
            // Add pagination
            if (config.pagination) {
                this.addPagination(table, config.pageSize);
            }
            
            return table;
        },
        
        addSearch: function(table) {
            const searchInput = table.querySelector('[data-search]');
            if (!searchInput) return;
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        },
        
        addSorting: function(table) {
            const headers = table.querySelectorAll('th[data-sort]');
            
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.innerHTML += ' <i class="fas fa-sort text-muted"></i>';
                
                header.addEventListener('click', () => {
                    this.sortTable(table, header);
                });
            });
        },
        
        sortTable: function(table, header) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const column = Array.from(header.parentNode.children).indexOf(header);
            const isAsc = header.classList.toggle('asc');
            
            // Update header icons
            table.querySelectorAll('th .fas').forEach(icon => {
                icon.className = 'fas fa-sort text-muted';
            });
            
            const icon = header.querySelector('.fas');
            icon.className = isAsc ? 'fas fa-sort-up text-primary' : 'fas fa-sort-down text-primary';
            
            rows.sort((a, b) => {
                const aText = a.children[column].textContent.trim();
                const bText = b.children[column].textContent.trim();
                
                // Try to parse as numbers
                const aNum = parseFloat(aText);
                const bNum = parseFloat(bText);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAsc ? aNum - bNum : bNum - aNum;
                }
                
                return isAsc ? aText.localeCompare(bText) : bText.localeCompare(bText);
            });
            
            rows.forEach(row => tbody.appendChild(row));
        },
        
        addPagination: function(table, pageSize) {
            // Implementation for client-side pagination
            // This is a simplified version
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length <= pageSize) return;
            
            let currentPage = 1;
            const totalPages = Math.ceil(rows.length / pageSize);
            
            const showPage = (page) => {
                rows.forEach((row, index) => {
                    const start = (page - 1) * pageSize;
                    const end = start + pageSize;
                    row.style.display = (index >= start && index < end) ? '' : 'none';
                });
            };
            
            showPage(1);
        }
    },
    
    // Form utilities
    form: {
        // Auto-save form data
        autoSave: function(formId, key) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const storageKey = `cmms_autosave_${key}`;
            
            // Load saved data
            this.loadFormData(form, storageKey);
            
            // Save on input
            form.addEventListener('input', () => {
                this.saveFormData(form, storageKey);
            });
            
            // Clear on submit
            form.addEventListener('submit', () => {
                localStorage.removeItem(storageKey);
            });
        },
        
        saveFormData: function(form, key) {
            const data = {};
            const formData = new FormData(form);
            
            for (let [name, value] of formData.entries()) {
                data[name] = value;
            }
            
            localStorage.setItem(key, JSON.stringify(data));
        },
        
        loadFormData: function(form, key) {
            const saved = localStorage.getItem(key);
            if (!saved) return;
            
            try {
                const data = JSON.parse(saved);
                Object.keys(data).forEach(name => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field && field.type !== 'file') {
                        if (field.type === 'checkbox' || field.type === 'radio') {
                            field.checked = data[name] === field.value;
                        } else {
                            field.value = data[name];
                        }
                    }
                });
            } catch (e) {
                console.error('Error loading form data:', e);
            }
        },
        
        // Reset form with animation
        reset: function(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            form.style.opacity = '0.5';
            form.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                form.reset();
                form.classList.remove('was-validated');
                
                // Clear custom validations
                form.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
                
                form.querySelectorAll('.invalid-feedback').forEach(el => {
                    el.textContent = '';
                });
                
                form.style.opacity = '';
                form.style.transform = '';
            }, 150);
        }
    },
    
    // File upload utilities
    upload: {
        // Drag and drop file upload
        initDropZone: function(elementId, options = {}) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const defaults = {
                allowedTypes: ['image/jpeg', 'image/png', 'image/gif'],
                maxSize: 5 * 1024 * 1024, // 5MB
                multiple: false,
                onSuccess: () => {},
                onError: () => {}
            };
            
            const config = Object.assign(defaults, options);
            
            element.addEventListener('dragover', (e) => {
                e.preventDefault();
                element.classList.add('drag-over');
            });
            
            element.addEventListener('dragleave', () => {
                element.classList.remove('drag-over');
            });
            
            element.addEventListener('drop', (e) => {
                e.preventDefault();
                element.classList.remove('drag-over');
                
                const files = Array.from(e.dataTransfer.files);
                this.handleFiles(files, config);
            });
        },
        
        handleFiles: function(files, config) {
            files.forEach(file => {
                if (!this.validateFile(file, config)) return;
                
                this.uploadFile(file, config);
            });
        },
        
        validateFile: function(file, config) {
            if (config.allowedTypes.length && !config.allowedTypes.includes(file.type)) {
                CMMS.showToast(`File type ${file.type} not allowed`, 'error');
                return false;
            }
            
            if (file.size > config.maxSize) {
                CMMS.showToast(`File size too large. Max: ${CMMS.formatFileSize(config.maxSize)}`, 'error');
                return false;
            }
            
            return true;
        },
        
        uploadFile: function(file, config) {
            const formData = new FormData();
            formData.append('file', file);
            
            CMMS.ajax({
                url: config.uploadUrl || 'api/upload.php',
                method: 'POST',
                body: formData,
                success: config.onSuccess,
                error: config.onError
            });
        }
    },
    
    // Notification system
    notification: {
        // Show notification with auto-dismiss
        show: function(message, type = 'info', duration = 5000) {
            const container = this.getContainer();
            const notification = this.create(message, type);
            
            container.appendChild(notification);
            
            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => {
                    this.dismiss(notification);
                }, duration);
            }
            
            return notification;
        },
        
        getContainer: function() {
            let container = document.getElementById('notification-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'notification-container';
                container.className = 'position-fixed top-0 end-0 p-3';
                container.style.zIndex = '1060';
                document.body.appendChild(container);
            }
            return container;
        },
        
        create: function(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show`;
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="CMMS.notification.dismiss(this.parentElement)"></button>
            `;
            
            // Animation
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                notification.style.transform = '';
            }, 10);
            
            return notification;
        },
        
        dismiss: function(notification) {
            notification.style.transform = 'translateX(100%)';
            notification.style.opacity = '0';
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.parentElement.removeChild(notification);
                }
            }, 300);
        }
    },
    
    // Local storage utilities
    storage: {
        set: function(key, value, expiry = null) {
            const data = {
                value: value,
                expiry: expiry ? Date.now() + expiry : null
            };
            localStorage.setItem(`cmms_${key}`, JSON.stringify(data));
        },
        
        get: function(key) {
            const item = localStorage.getItem(`cmms_${key}`);
            if (!item) return null;
            
            try {
                const data = JSON.parse(item);
                
                if (data.expiry && Date.now() > data.expiry) {
                    localStorage.removeItem(`cmms_${key}`);
                    return null;
                }
                
                return data.value;
            } catch (e) {
                return null;
            }
        },
        
        remove: function(key) {
            localStorage.removeItem(`cmms_${key}`);
        },
        
        clear: function() {
            Object.keys(localStorage).forEach(key => {
                if (key.startsWith('cmms_')) {
                    localStorage.removeItem(key);
                }
            });
        }
    },
    
    // Utility functions
    utils: {
        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // Throttle function
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },
        
        // Generate unique ID
        generateId: function(prefix = 'id') {
            return prefix + '_' + Math.random().toString(36).substr(2, 9);
        },
        
        // Copy to clipboard
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                return navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    CMMS.showToast('Copied to clipboard', 'success');
                } catch (err) {
                    CMMS.showToast('Failed to copy', 'error');
                }
                document.body.removeChild(textArea);
            }
        },
        
        // Format file size
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        // Scroll to element
        scrollTo: function(elementId, offset = 0) {
            const element = document.getElementById(elementId);
            if (element) {
                const y = element.getBoundingClientRect().top + window.pageYOffset + offset;
                window.scrollTo({ top: y, behavior: 'smooth' });
            }
        },
        
        // Check if element is in viewport
        isInViewport: function(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
    }
});

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize auto-save for forms with data-auto-save attribute
    document.querySelectorAll('form[data-auto-save]').forEach(form => {
        const key = form.dataset.autoSave || form.id;
        if (key) {
            CMMS.form.autoSave(form.id, key);
        }
    });
    
    // Initialize drop zones
    document.querySelectorAll('[data-drop-zone]').forEach(element => {
        CMMS.upload.initDropZone(element.id);
    });
    
    // Initialize data tables
    document.querySelectorAll('[data-table]').forEach(table => {
        CMMS.dataTable.init(table.id);
    });
    
    // Add smooth scrolling to anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add loading states to forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Đang xử lý...';
                
                // Reset after 30 seconds (fallback)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 30000);
            }
        });
    });
    
    // Initialize tooltips and popovers
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Handle session timeout warnings
    let sessionWarningShown = false;
    setInterval(function() {
        // Check if user is still active (simplified check)
        const loginTime = CMMS.storage.get('login_time');
        if (loginTime && Date.now() - loginTime > 50 * 60 * 1000 && !sessionWarningShown) {
            sessionWarningShown = true;
            CMMS.showToast('Phiên đăng nhập sắp hết hạn. Vui lòng lưu công việc.', 'warning', 10000);
        }
    }, 60000); // Check every minute
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S to save (prevent default browser save)
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const activeForm = document.querySelector('form:focus-within');
            if (activeForm) {
                activeForm.requestSubmit();
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const modal = bootstrap.Modal.getInstance(openModal);
                if (modal) modal.hide();
            }
        }
    });
});

// Handle page visibility changes
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Page became visible - could refresh data here
        console.log('Page became visible');
    } else {
        // Page became hidden - could save drafts here
        console.log('Page became hidden');
    }
});

// Handle online/offline status
window.addEventListener('online', function() {
    CMMS.showToast('Kết nối internet đã được khôi phục', 'success');
});

window.addEventListener('offline', function() {
    CMMS.showToast('Mất kết nối internet. Một số tính năng có thể không hoạt động.', 'warning', 0);
});

// Handle before unload (warn about unsaved changes)
window.addEventListener('beforeunload', function(e) {
    const unsavedForms = document.querySelectorAll('form[data-modified="true"]');
    if (unsavedForms.length > 0) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
});

// Track form modifications
document.addEventListener('input', function(e) {
    if (e.target.closest('form')) {
        e.target.closest('form').dataset.modified = 'true';
    }
});

document.addEventListener('submit', function(e) {
    if (e.target.tagName === 'FORM') {
        e.target.dataset.modified = 'false';
    }
});

// Console welcome message
console.log('%cCMMS System', 'color: #1e3a8a; font-size: 20px; font-weight: bold;');
console.log('%cEquipment Management System v' + (window.APP_VERSION || '1.0.0'), 'color: #64748b;');
console.log('%cDeveloped by Cuongduck', 'color: #64748b;');