/**
 * BOM Parts JavaScript - /assets/js/bom-parts.js
 * JavaScript cho quản lý linh kiện BOM
 */

// Extend CMMS object for Parts management
if (!window.CMMS) {
    window.CMMS = {};
}

CMMS.Parts = {
    // Configuration
    config: {
        apiUrl: '/modules/bom/api/parts.php',
        currentPage: 1,
        pageSize: 20,
        selectedParts: []
    },
    
    // Initialize Parts module
    init: function() {
        this.bindEvents();
        this.initializeComponents();
        this.loadPartsList();
        console.log('Parts Module initialized');
    },
    
    // Bind event listeners
    bindEvents: function() {
        // Search functionality
        const searchInput = document.getElementById('partsSearch');
        if (searchInput) {
            searchInput.addEventListener('input', CMMS.utils.debounce((e) => {
                this.performSearch(e.target.value);
            }, 300));
        }
        
        // Filter changes
        const filterSelects = document.querySelectorAll('.parts-filter');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                this.applyFilters();
            });
        });
        
        // Parts form submission
        const partsForm = document.getElementById('partsForm');
        if (partsForm) {
            partsForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.savePart();
            });
        }
        
        // Part code input formatting
        const partCodeInput = document.getElementById('part_code');
        if (partCodeInput) {
            partCodeInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase();
            });
        }
        
        // Category change event
        const categorySelect = document.getElementById('category');
        if (categorySelect) {
            categorySelect.addEventListener('change', (e) => {
                this.onCategoryChange(e.target.value);
            });
        }
        
        // Supplier management
        const addSupplierBtn = document.getElementById('addSupplier');
        if (addSupplierBtn) {
            addSupplierBtn.addEventListener('click', () => {
                this.addSupplierRow();
            });
        }
        
        // Bulk selection
        const selectAllCheckbox = document.getElementById('selectAllParts');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }
    },
    
    // Initialize components
    initializeComponents: function() {
        // Initialize data tables
        const partsTable = document.getElementById('partsTable');
        if (partsTable) {
            CMMS.dataTable.init('partsTable', {
                searching: false,
                pageSize: this.config.pageSize
            });
        }
        
        // Initialize supplier management
        this.initializeSuppliers();
        
        // Initialize tooltips
        this.initializeTooltips();
    },
    
    // Search parts
    performSearch: function(searchTerm) {
        const filters = this.getFilters();
        filters.search = searchTerm;
        this.loadPartsList(filters);
    },
    
    // Apply filters
    applyFilters: function() {
        const filters = this.getFilters();
        this.loadPartsList(filters);
    },
    
    // Get current filter values
    getFilters: function() {
        return {
            category: document.getElementById('filterCategory')?.value || '',
            stock_status: document.getElementById('filterStockStatus')?.value || '',
            search: document.getElementById('partsSearch')?.value || ''
        };
    },
    
    // Load parts list
    loadPartsList: function(filters = {}) {
        CMMS.showLoading();
        
        const params = new URLSearchParams({
            action: 'list',
            page: this.config.currentPage,
            ...filters
        });
        
        CMMS.ajax({
            url: this.config.apiUrl + '?' + params,
            method: 'GET',
            success: (data) => {
                if (data.success) {
                    this.renderPartsList(data.data.parts);
                    this.renderPagination(data.data.pagination);
                    this.updateStatistics(data.data);
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            },
            error: () => {
                CMMS.showToast('Lỗi khi tải danh sách linh kiện', 'error');
            }
        });
    },
    
    // Render parts list
    renderPartsList: function(parts) {
        const tbody = document.querySelector('#partsTable tbody');
        if (!tbody) return;
        
        // Update total records counter
        const totalRecords = document.getElementById('totalRecords');
        if (totalRecords) {
            totalRecords.textContent = parts.length;
        }
        
        if (parts.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="bom-empty">
                            <div class="bom-empty-icon">
                                <i class="fas fa-cube"></i>
                            </div>
                            <div class="bom-empty-text">Không tìm thấy linh kiện nào</div>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Thêm linh kiện mới
                            </a>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = parts.map(part => `
            <tr class="fade-in-up" data-part-id="${part.id}">
                <td>
                    <div class="form-check">
                        <input class="form-check-input part-checkbox" type="checkbox" 
                               value="${part.id}" data-part-code="${part.part_code}">
                    </div>
                    <div class="d-flex flex-column">
                        <span class="part-code">${CMMS.escapeHtml(part.part_code)}</span>
                        <strong>${CMMS.escapeHtml(part.part_name)}</strong>
                        ${part.description ? `<small class="text-muted">${CMMS.escapeHtml(part.description)}</small>` : ''}
                    </div>
                </td>
                <td>
                    ${part.category ? `<span class="badge bg-secondary">${CMMS.escapeHtml(part.category)}</span>` : '<span class="text-muted">-</span>'}
                </td>
                <td class="text-center">
                    ${part.unit ? CMMS.escapeHtml(part.unit) : 'N/A'}
                </td>
                <td class="text-end">
                    <span class="cost-display">${this.formatCurrency(part.unit_price || 0)}</span>
                </td>
                <td class="text-center">
                    <div class="stock-indicator">
                        <span class="stock-dot ${(part.stock_status || 'ok').toLowerCase()}"></span>
                        <div>
                            <strong>${this.formatNumber(part.stock_quantity || 0, 1)}</strong>
                            <small class="d-block text-muted">${CMMS.escapeHtml(part.stock_unit || part.unit || '')}</small>
                            <span class="badge ${this.getStockStatusClass(part.stock_status)}">
                                ${this.getStockStatusText(part.stock_status)}
                            </span>
                        </div>
                    </div>
                </td>
                <td class="hide-mobile">
                    <small>${CMMS.escapeHtml(part.supplier_name || 'N/A')}</small>
                </td>
                <td class="text-center">
                    <span class="badge bg-info">${part.usage_count || 0}</span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="view.php?id=${part.id}" class="btn btn-outline-primary btn-sm" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="edit.php?id=${part.id}" class="btn btn-outline-warning btn-sm" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="CMMS.Parts.deletePart(${part.id})" class="btn btn-outline-danger btn-sm" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Bind checkbox events
        this.bindCheckboxEvents();
    },
    
    // Bind checkbox events for bulk operations
    bindCheckboxEvents: function() {
        const checkboxes = document.querySelectorAll('.part-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateSelectedParts();
            });
        });
    },
    
    // Update selected parts array
    updateSelectedParts: function() {
        const checkboxes = document.querySelectorAll('.part-checkbox:checked');
        this.config.selectedParts = Array.from(checkboxes).map(cb => ({
            id: cb.value,
            code: cb.dataset.partCode
        }));
        
        // Update bulk action buttons
        const bulkActions = document.getElementById('bulkActions');
        if (bulkActions) {
            bulkActions.style.display = this.config.selectedParts.length > 0 ? 'block' : 'none';
        }
        
        // Update select all checkbox
        const selectAllCheckbox = document.getElementById('selectAllParts');
        if (selectAllCheckbox) {
            const totalCheckboxes = document.querySelectorAll('.part-checkbox').length;
            const checkedCheckboxes = document.querySelectorAll('.part-checkbox:checked').length;
            
            selectAllCheckbox.checked = checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0;
            selectAllCheckbox.indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
        }
    },
    
    // Toggle select all
    toggleSelectAll: function(checked) {
        const checkboxes = document.querySelectorAll('.part-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
        });
        this.updateSelectedParts();
    },
    
    // Save part
    savePart: function() {
        const form = document.getElementById('partsForm');
        const formData = new FormData(form);
        
        // Validate form
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        // Check for required fields
        const partCode = formData.get('part_code');
        const partName = formData.get('part_name');
        
        if (!partCode || !partName) {
            CMMS.showToast('Vui lòng nhập đầy đủ mã và tên linh kiện', 'error');
            return;
        }
        
        // Add action
        const isEdit = formData.has('part_id');
        formData.append('action', isEdit ? 'update' : 'save');
        
        CMMS.ajax({
            url: this.config.apiUrl,
            method: 'POST',
            body: formData,
            success: (data) => {
                if (data.success) {
                    CMMS.showToast(data.message, 'success');
                    setTimeout(() => {
                        if (isEdit) {
                            window.location.href = 'view.php?id=' + formData.get('part_id');
                        } else {
                            window.location.href = 'view.php?id=' + data.part_id;
                        }
                    }, 1500);
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            }
        });
    },
    
    // Delete part
    deletePart: function(partId) {
        CMMS.confirm('Bạn có chắc chắn muốn xóa linh kiện này?', () => {
            CMMS.ajax({
                url: this.config.apiUrl,
                method: 'POST',
                body: `action=delete&id=${partId}`,
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
    
    // Bulk delete parts
    bulkDeleteParts: function() {
        if (this.config.selectedParts.length === 0) {
            CMMS.showToast('Vui lòng chọn ít nhất một linh kiện', 'warning');
            return;
        }
        
        const partCodes = this.config.selectedParts.map(p => p.code).join(', ');
        const message = `Bạn có chắc chắn muốn xóa ${this.config.selectedParts.length} linh kiện?\n\n${partCodes}`;
        
        CMMS.confirm(message, () => {
            const partIds = this.config.selectedParts.map(p => p.id);
            
            CMMS.ajax({
                url: this.config.apiUrl,
                method: 'POST',
                body: `action=bulk_delete&part_ids=${JSON.stringify(partIds)}`,
                success: (data) => {
                    if (data.success) {
                        CMMS.showToast(data.message, 'success');
                        this.loadPartsList();
                        this.config.selectedParts = [];
                    } else {
                        CMMS.showToast(data.message, 'error');
                    }
                }
            });
        });
    },
    
    // Export selected parts
    exportSelectedParts: function(format = 'excel') {
        if (this.config.selectedParts.length === 0) {
            CMMS.showToast('Vui lòng chọn ít nhất một linh kiện', 'warning');
            return;
        }
        
        const partIds = this.config.selectedParts.map(p => p.id);
        const params = new URLSearchParams({
            action: 'export_selected',
            format: format,
            part_ids: JSON.stringify(partIds)
        });
        
        window.open('/modules/bom/api/export.php?' + params, '_blank');
    },
    
    // Handle category change
    onCategoryChange: function(category) {
        // Update supplier suggestions based on category
        this.loadCategorySuggestions(category);
    },
    
    // Load supplier suggestions for category
    loadCategorySuggestions: function(category) {
        if (!category) return;
        
        CMMS.ajax({
            url: this.config.apiUrl + '?action=category_suppliers&category=' + encodeURIComponent(category),
            method: 'GET',
            success: (data) => {
                if (data.success && data.suppliers) {
                    this.updateSupplierSuggestions(data.suppliers);
                }
            }
        });
    },
    
    // Update supplier suggestions
    updateSupplierSuggestions: function(suppliers) {
        const supplierInput = document.getElementById('supplier_name');
        const supplierCode = document.getElementById('supplier_code');
        
        if (!supplierInput || suppliers.length === 0) return;
        
        // Create datalist for autocomplete
        let datalist = document.getElementById('supplier-suggestions');
        if (!datalist) {
            datalist = document.createElement('datalist');
            datalist.id = 'supplier-suggestions';
            supplierInput.parentNode.appendChild(datalist);
        }
        
        datalist.innerHTML = suppliers.map(supplier => 
            `<option value="${supplier.name}" data-code="${supplier.code}"></option>`
        ).join('');
        
        supplierInput.setAttribute('list', 'supplier-suggestions');
        
        // Handle supplier selection
        supplierInput.addEventListener('input', function() {
            const selectedSupplier = suppliers.find(s => s.name === this.value);
            if (selectedSupplier && supplierCode) {
                supplierCode.value = selectedSupplier.code;
            }
        });
    },
    
    // Initialize suppliers management
    initializeSuppliers: function() {
        const suppliersContainer = document.getElementById('suppliersContainer');
        if (!suppliersContainer) return;
        
        // Add event delegation for dynamic supplier rows
        suppliersContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-supplier')) {
                e.target.closest('.supplier-row').remove();
            }
        });
    },
    
    // Add supplier row
    addSupplierRow: function() {
        const container = document.getElementById('suppliersContainer');
        if (!container) return;
        
        const rowCount = container.children.length;
        const supplierRow = document.createElement('div');
        supplierRow.className = 'supplier-row row mb-2';
        supplierRow.innerHTML = `
            <div class="col-md-4">
                <input type="text" name="suppliers[${rowCount}][supplier_code]" 
                       class="form-control form-control-sm" placeholder="Mã NCC">
            </div>
            <div class="col-md-4">
                <input type="text" name="suppliers[${rowCount}][supplier_name]" 
                       class="form-control form-control-sm" placeholder="Tên NCC">
            </div>
            <div class="col-md-3">
                <input type="number" name="suppliers[${rowCount}][unit_price]" 
                       class="form-control form-control-sm" placeholder="Giá" step="0.01">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm remove-supplier">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        container.appendChild(supplierRow);
    },
    
    // Get stock status class
    getStockStatusClass: function(status) {
        const classes = {
            'OK': 'bg-success',
            'Low': 'bg-warning', 
            'Out of Stock': 'bg-danger'
        };
        
        return classes[status] || 'bg-secondary';
    },
    
    // Get stock status text
    getStockStatusText: function(status) {
        const texts = {
            'OK': 'Đủ hàng',
            'Low': 'Sắp hết', 
            'Out of Stock': 'Hết hàng'
        };
        
        return texts[status] || status || 'N/A';
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            minimumFractionDigits: 0
        }).format(amount);
    },
    
    // Format number
    formatNumber: function(number, decimals = 0) {
        return new Intl.NumberFormat('vi-VN', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    },
    
    // Initialize tooltips
    initializeTooltips: function() {
        const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipElements.forEach(element => {
            new bootstrap.Tooltip(element);
        });
    },
    
    // Update statistics
    updateStatistics: function(data) {
        // Update any statistics displays if needed
        const pagination = data.pagination;
        if (pagination) {
            const showingFrom = document.getElementById('showingFrom');
            const showingTo = document.getElementById('showingTo');
            const totalItems = document.getElementById('totalItems');
            
            if (showingFrom) showingFrom.textContent = ((pagination.current_page - 1) * pagination.per_page) + 1;
            if (showingTo) showingTo.textContent = Math.min(pagination.current_page * pagination.per_page, pagination.total_items);
            if (totalItems) totalItems.textContent = pagination.total_items;
        }
    },
    
    // Render pagination
    renderPagination: function(pagination) {
        const paginationContainer = document.getElementById('partsPagination');
        if (!paginationContainer || !pagination) return;
        
        if (pagination.total_pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        let html = '<nav><ul class="pagination pagination-sm justify-content-center">';
        
        // Previous
        if (pagination.current_page > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">‹</a>
            </li>`;
        } else {
            html += '<li class="page-item disabled"><span class="page-link">‹</span></li>';
        }
        
        // Page numbers
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
        } else {
            html += '<li class="page-item disabled"><span class="page-link">›</span></li>';
        }
        
        html += '</ul></nav>';
        
        paginationContainer.innerHTML = html;
        
        // Bind pagination events
        paginationContainer.querySelectorAll('a.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page);
                if (page) {
                    this.config.currentPage = page;
                    this.loadPartsList(this.getFilters());
                }
            });
        });
    }
};

// Global functions for parts management
window.bulkDeleteParts = () => CMMS.Parts.bulkDeleteParts();
window.exportSelectedParts = (format) => CMMS.Parts.exportSelectedParts(format);
window.addSupplierRow = () => CMMS.Parts.addSupplierRow();

// Auto-initialize based on page
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('/modules/bom/parts/')) {
        CMMS.Parts.init();
    }
});