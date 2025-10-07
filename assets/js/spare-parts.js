/**
 * Spare Parts JavaScript
 * /assets/js/spare-parts.js
 */

if (!window.CMMS) {
    window.CMMS = {};
}

CMMS.SpareParts = {
    // Configuration
    config: {
        apiUrl: '/modules/spare_parts/api/spare_parts.php',
        currentPage: 1,
        pageSize: 20,
        selectedParts: []
    },
    
    // Initialize module
    init: function() {
        this.bindEvents();
        console.log('Spare Parts Module initialized');
    },
    
    // Bind event listeners
    bindEvents: function() {
        // Search functionality
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', CMMS.utils.debounce((e) => {
                this.performSearch();
            }, 300));
        }
        
        // Filter changes
        const filterSelects = document.querySelectorAll('select[name="category"], select[name="manager"], select[name="stock_status"]');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                this.applyFilters();
            });
        });
        
        // Form submissions
        const forms = document.querySelectorAll('#sparePartsForm, #sparePartsEditForm');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });
        });
    },
    
    // Get current filter values
    getFilters: function() {
        return {
            category: document.querySelector('select[name="category"]')?.value || '',
            manager: document.querySelector('select[name="manager"]')?.value || '',
            stock_status: document.querySelector('select[name="stock_status"]')?.value || '',
            search: document.querySelector('input[name="search"]')?.value || ''
        };
    },
    
    // Perform search
    performSearch: function() {
        const form = document.querySelector('form');
        if (form) {
            form.submit();
        }
    },
    
    // Apply filters
    applyFilters: function() {
        const form = document.querySelector('form');
        if (form) {
            form.submit();
        }
    },
    
    // Handle form submit
    handleFormSubmit: function(form) {
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(form);
        const isEdit = formData.has('id');
        formData.append('action', isEdit ? 'update' : 'save');
        
        CMMS.ajax({
            url: this.config.apiUrl,
            method: 'POST',
            body: formData,
            success: (data) => {
                if (data.success) {
                    CMMS.showToast(data.message, 'success');
                    
                    if (formData.has('save_and_new')) {
                        form.reset();
                        form.classList.remove('was-validated');
                        const firstInput = form.querySelector('input[type="text"]');
                        if (firstInput) firstInput.focus();
                    } else {
                        setTimeout(() => {
                            const id = isEdit ? formData.get('id') : data.data.id;
                            window.location.href = 'view.php?id=' + id;
                        }, 1500);
                    }
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            },
            error: (error, response) => {
                if (response && response.message) {
                    CMMS.showToast(response.message, 'error');
                }
            }
        });
    },
    
    // Auto detect category
    autoDetectCategory: function(itemName, callback) {
        if (!itemName || itemName.length < 3) {
            if (callback) callback(null);
            return;
        }
        
        CMMS.ajax({
            url: this.config.apiUrl + '?action=detect_category&item_name=' + encodeURIComponent(itemName),
            method: 'GET',
            success: (data) => {
                if (data.success && callback) {
                    callback(data.data);
                } else if (callback) {
                    callback(null);
                }
            },
            error: () => {
                if (callback) callback(null);
            }
        });
    },
    
    // Display category result
    displayCategoryResult: function(elementId, categoryData) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        if (!categoryData) {
            element.innerHTML = '<span class="badge bg-secondary">Vật tư khác</span>';
            return;
        }
        
        const { category, confidence } = categoryData;
        let badgeClass = 'bg-success';
        
        if (confidence < 70) badgeClass = 'bg-warning text-dark';
        if (confidence < 40) badgeClass = 'bg-danger';
        
        element.innerHTML = `
            <span class="badge ${badgeClass}">${category}</span>
            <small class="ms-2 text-muted">(${confidence}%)</small>
        `;
    },
    
    // Helper functions
    formatNumber: function(number, decimals = 0) {
        return new Intl.NumberFormat('vi-VN', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number || 0);
    },
    
    getStockStatusClass: function(status) {
        const classes = {
            'OK': 'bg-success',
            'Low': 'bg-warning',
            'Reorder': 'bg-info',
            'Out of Stock': 'bg-danger'
        };
        return classes[status] || 'bg-secondary';
    },
    
    getStockStatusText: function(status) {
        const texts = {
            'OK': 'Đủ hàng',
            'Low': 'Sắp hết',
            'Reorder': 'Cần đặt hàng',
            'Out of Stock': 'Hết hàng'
        };
        return texts[status] || status;
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('/modules/spare_parts/')) {
        CMMS.SpareParts.init();
    }
});

/**
 * Delete spare part - Global function
 */
window.deleteSparePart = function(id, itemCode) {
    if (!confirm(`⚠️ CẢNH BÁO: Xóa vĩnh viễn "${itemCode}"?\n\nDữ liệu sẽ BỊ XÓA HOÀN TOÀN và KHÔNG THỂ KHÔI PHỤC!`)) {
        return false;
    }
    
    // Double confirm
    if (!confirm(`Xác nhận lần cuối: Xóa "${itemCode}"?`)) {
        return false;
    }
    
    CMMS.showLoading();
    
    fetch('api/spare_parts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'delete',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        CMMS.hideLoading();
        
        if (data.success) {
            CMMS.showToast(data.message || 'Đã xóa thành công', 'success');
            
            // Reload trang sau 1 giây
            setTimeout(() => {
                window.location.href = window.location.pathname;
            }, 1000);
        } else {
            CMMS.showToast(data.message || 'Có lỗi xảy ra', 'error');
        }
    })
    .catch(error => {
        CMMS.hideLoading();
        console.error('Delete error:', error);
        CMMS.showToast('Lỗi kết nối: ' + error.message, 'error');
    });
    
    return false;
};

/**
 * Reclassify spare part - Global function
 */
window.reclassifyPart = function(partId) {
    if (!confirm('Bạn có muốn phân loại lại vật tư này?')) {
        return false;
    }
    
    CMMS.showLoading();
    
    fetch('api/spare_parts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'reclassify',
            id: partId
        })
    })
    .then(response => response.json())
    .then(data => {
        CMMS.hideLoading();
        
        if (data.success) {
            const message = data.message + (data.data ? `\nDanh mục mới: ${data.data.new_category}` : '');
            CMMS.showToast(message, 'success');
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            CMMS.showToast(data.message || 'Có lỗi xảy ra', 'error');
        }
    })
    .catch(error => {
        CMMS.hideLoading();
        console.error('Reclassify error:', error);
        CMMS.showToast('Lỗi kết nối', 'error');
    });
    
    return false;
};

/**
 * Auto detect category - Global function
 */
window.autoDetectCategory = function(itemName, callback) {
    return CMMS.SpareParts.autoDetectCategory(itemName, callback);
};