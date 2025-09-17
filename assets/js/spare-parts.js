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
        this.loadPartsList();
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
        const filterSelects = document.querySelectorAll('select[name^="filter"], select[name="category"], select[name="manager"], select[name="stock_status"]');
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
    
    // Load parts list
    loadPartsList: function(filters = {}) {
        const params = new URLSearchParams({
            action: 'list',
            page: this.config.currentPage,
            ...this.getFilters(),
            ...filters
        });
        
        CMMS.ajax({
            url: this.config.apiUrl + '?' + params,
            method: 'GET',
            success: (data) => {
                if (data.success) {
                    this.renderPartsList(data.data.parts || []);
                    this.renderPagination(data.data.pagination);
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            },
            error: () => {
                CMMS.showToast('Lỗi khi tải danh sách spare parts', 'error');
            }
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
        this.config.currentPage = 1;
        this.loadPartsList();
    },
    
    // Apply filters
    applyFilters: function() {
        this.config.currentPage = 1;
        this.loadPartsList();
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
                        // Reset form for new entry
                        form.reset();
                        form.classList.remove('was-validated');
                        const firstInput = form.querySelector('input[type="text"]');
                        if (firstInput) firstInput.focus();
                    } else {
                        // Redirect to view page
                        setTimeout(() => {
                            const id = isEdit ? formData.get('id') : data.data.id;
                            window.location.href = 'view.php?id=' + id;
                        }, 1500);
                    }
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            }
        });
    },
    
    // Delete spare part
    deletePart: function(id) {
        CMMS.confirm('Bạn có chắc chắn muốn xóa spare part này?', () => {
            CMMS.ajax({
                url: this.config.apiUrl,
                method: 'POST',
                body: `action=delete&id=${id}`,
                success: (data) => {
                    if (data.success) {
                        CMMS.showToast(data.message, 'success');
                        this.loadPartsList();
                    } else {
                        CMMS.showToast(data.message, 'error');
                    }
                }
            });
        });
    },
    
    // Render parts list (for AJAX loading)
    renderPartsList: function(parts) {
        const tbody = document.querySelector('#sparePartsTable tbody');
        if (!tbody) return;
        
        if (parts.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-cube fa-3x mb-2 d-block text-muted"></i>
                        Không tìm thấy spare part nào
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = parts.map(part => `
            <tr class="${part.is_critical ? 'table-warning' : ''}">
                <td>
                    <span class="part-code">${CMMS.escapeHtml(part.item_code)}</span>
                    ${part.is_critical ? '<span class="badge badge-warning ms-1">Critical</span>' : ''}
                </td>
                <td>
                    <strong>${CMMS.escapeHtml(part.item_name)}</strong>
                    ${part.description ? `<small class="d-block text-muted">${CMMS.escapeHtml(part.description.substr(0, 50))}...</small>` : ''}
                </td>
                <td>${part.category ? CMMS.escapeHtml(part.category) : '-'}</td>
                <td class="text-center">
                    <strong>${this.formatNumber(part.current_stock, 2)}</strong>
                    <small class="d-block text-muted">${CMMS.escapeHtml(part.stock_unit)}</small>
                </td>
                <td class="text-center">
                    <small>${this.formatNumber(part.min_stock, 0)} / ${this.formatNumber(part.max_stock, 0)}</small>
                    ${part.suggested_order_qty > 0 ? `<small class="d-block text-info">Đề xuất: ${this.formatNumber(part.suggested_order_qty, 0)}</small>` : ''}
                </td>
                <td>
                    <span class="badge ${this.getStockStatusClass(part.stock_status)}">
                        ${this.getStockStatusText(part.stock_status)}
                    </span>
                </td>
                <td>
                    ${part.manager_name ? `<small>${CMMS.escapeHtml(part.manager_name)}</small>` : '<small class="text-muted">Chưa phân công</small>'}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="view.php?id=${part.id}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="edit.php?id=${part.id}" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `).join('');
    },
    
    // Render pagination
    renderPagination: function(pagination) {
        const container = document.getElementById('sparePartsPagination');
        if (!container || !pagination || pagination.total_pages <= 1) return;
        
        let html = '<nav><ul class="pagination pagination-sm justify-content-center">';
        
        // Previous
        if (pagination.current_page > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">‹</a>
            </li>`;
        }
        
        // Pages
        const start = Math.max(1, pagination.current_page - 2);
        const end = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = start; i <= end; i++) {
            if (i === pagination.current_page) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }
        }
        
        // Next
        if (pagination.current_page < pagination.total_pages) {
            html += `<li class="page-item">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">›</a>
            </li>`;
        }
        
        html += '</ul></nav>';
        container.innerHTML = html;
        
        // Bind pagination events
        container.querySelectorAll('a.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                if (page) {
                    this.config.currentPage = page;
                    this.loadPartsList();
                }
            });
        });
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
    },

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
    
    // Function để hiển thị category detection result
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
    
    // Function để batch reclassify
    batchReclassify: function(partIds) {
        if (!partIds || partIds.length === 0) {
            CMMS.showToast('Không có spare part nào được chọn', 'warning');
            return;
        }
        
        CMMS.confirm(`Bạn có muốn phân loại lại ${partIds.length} spare parts?`, () => {
            CMMS.ajax({
                url: this.config.apiUrl,
                method: 'POST',
                body: JSON.stringify({
                    action: 'batch_reclassify',
                    part_ids: partIds
                }),
                headers: {
                    'Content-Type': 'application/json'
                },
                success: (data) => {
                    if (data.success) {
                        CMMS.showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        CMMS.showToast(data.message, 'error');
                    }
                }
            });
        });
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('/modules/spare_parts/')) {
        CMMS.SpareParts.init();
    }
});

// Global functions
window.deleteSparePart = (id) => CMMS.SpareParts.deletePart(id);

window.reclassifyPart = function(partId) {
    CMMS.SpareParts.batchReclassify([partId]);
};

window.autoDetectCategory = function(itemName, callback) {
    return CMMS.SpareParts.autoDetectCategory(itemName, callback);
};