/**
 * BOM Module JavaScript - /assets/js/bom.js
 * Main JavaScript functionality for BOM module
 */

if (!window.CMMS) {
    console.error('CMMS namespace not found. Please include main.js first.');
    window.CMMS = {};
}

CMMS.BOM = {
    // Configuration
    config: {
        apiUrl: '/modules/bom/api/',
        currentBOMId: null,
        currentPage: 1,
        pageSize: 20
    },
    
    // Initialize BOM module
    init: function() {
        this.bindEvents();
        this.initializeComponents();
        console.log('BOM Module initialized');
    },
    
    // Bind event listeners
    bindEvents: function() {
        // Search functionality
        const searchInput = document.getElementById('bomSearch');
        if (searchInput) {
            searchInput.addEventListener('input', CMMS.utils.debounce((e) => {
                this.performSearch(e.target.value);
            }, 300));
        }
        
        // Filter changes
        const filterSelects = document.querySelectorAll('.bom-filter');
        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                this.applyFilters();
            });
        });
        
        // BOM form submission
        const bomForm = document.getElementById('bomForm');
        if (bomForm) {
            bomForm.addEventListener('submit', (e) => {
                e.preventDefault();
                if (bomForm.querySelector('input[name="bom_id"]')) {
                    this.updateBOM(bomForm);
                } else {
                    this.submitBOM(bomForm);
                }
            });
        }
        
        // Add BOM item button
        const addItemBtn = document.getElementById('addBOMItem');
        if (addItemBtn) {
            addItemBtn.addEventListener('click', () => {
                this.addBOMItem();
            });
        }
        
        // Machine type selection change
        const machineTypeSelect = document.getElementById('machine_type_id');
        if (machineTypeSelect) {
            machineTypeSelect.addEventListener('change', (e) => {
                this.generateBOMCode(e.target.value);
            });
        }
        
        // Parts page specific
        const partsSearch = document.getElementById('partsSearch');
        if (partsSearch) {
            partsSearch.addEventListener('input', CMMS.utils.debounce((e) => {
                this.searchParts(e.target.value);
            }, 300));
        }
        
        const categoryFilter = document.getElementById('filterCategory');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', () => {
                this.applyFilters();
            });
        }
        
        const stockFilter = document.getElementById('filterStockStatus');
        if (stockFilter) {
            stockFilter.addEventListener('change', () => {
                this.applyFilters();
            });
        }
    },
    
    // Initialize components
    initializeComponents: function() {
        // Initialize data tables
        const bomTable = document.getElementById('bomTable');
        if (bomTable) {
            CMMS.dataTable.init('bomTable', {
                searching: false,
                pageSize: this.config.pageSize
            });
        }
        
        // Initialize tooltips for BOM items
        this.initializeTooltips();
        
        // Load initial data
        this.loadBOMList();
        
        // Add initial BOM item row for add.php
        if (document.getElementById('bomItemsBody') && document.getElementById('bomItemsBody').children.length === 0) {
            this.addBOMItem();
        }
    },
    
    // Initialize tooltips
    initializeTooltips: function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },
    
    // Search BOMs
    performSearch: function(searchTerm) {
        const filters = this.getFilters();
        filters.search = searchTerm;
        this.loadBOMList(filters);
    },
    
    // Apply filters
    applyFilters: function() {
        const filters = this.getFilters();
        this.loadBOMList(filters);
    },
    
    // Get current filter values
    getFilters: function() {
        return {
            machine_type: document.getElementById('filterMachineType')?.value || '',
            priority: document.getElementById('filterPriority')?.value || '',
            stock_status: document.getElementById('filterStockStatus')?.value || '',
            search: document.getElementById('bomSearch')?.value || ''
        };
    },
    
    // Generate BOM code
    generateBOMCode: function(machineTypeId) {
        if (!machineTypeId) {
            document.getElementById('bom_code').value = '';
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'generateCode');
        formData.append('machine_type_id', machineTypeId);
        
        CMMS.ajax({
            url: this.config.apiUrl + 'bom.php',
            method: 'POST',
            body: formData,
            success: (data) => {
                if (data.success) {
                    document.getElementById('bom_code').value = data.data.code;
                } else {
                    CMMS.showToast(data.message || 'Lỗi tạo mã BOM', 'error');
                }
            },
            error: () => {
                CMMS.showToast('Lỗi khi tạo mã BOM', 'error');
            }
        });
    },
    
    // Load BOM list
    loadBOMList: function(filters = {}) {
        console.log('Loading BOM list with filters:', filters);
        
        if (window.CMMS && typeof CMMS.showLoading === 'function') {
            CMMS.showLoading();
        }

        const params = new URLSearchParams({
            action: 'list',
            page: this.config.currentPage,
            ...filters
        });
        
        CMMS.ajax({
            url: this.config.apiUrl + 'bom.php?' + params,
            method: 'GET',
            credentials: 'same-origin',
            success: (data) => {
                console.log('API Response:', data);
                
                if (data.success) {
                    const boms = Array.isArray(data.data) ? data.data : [];
                    this.renderBOMList(boms);
                    
                    if (data.pagination) {
                        this.renderPagination(data.pagination);
                    }
                } else {
                    console.error('API Error:', data.message);
                    if (data.message && data.message.includes('Authentication')) {
                        CMMS.showToast('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 'error');
                        setTimeout(() => {
                            window.location.href = '/login.php';
                        }, 2000);
                        return;
                    }
                    
                    CMMS.showToast(data.message || 'Lỗi không xác định', 'error');
                    this.renderBOMList([]);
                }
            },
            error: (error) => {
                console.error('Ajax Error:', error);
                if (error.message && error.message.includes('401')) {
                    CMMS.showToast('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 'error');
                    setTimeout(() => {
                        window.location.href = '/login.php';
                    }, 2000);
                } else {
                    CMMS.showToast('Lỗi khi tải danh sách BOM', 'error');
                }
                this.renderBOMList([]);
            }
        });
    },
    
    // Render BOM list
    renderBOMList: function(boms) {
        const tbody = document.querySelector('#bomTable tbody');
        if (!tbody) return;
        
        if (boms.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="bom-empty">
                            <div class="bom-empty-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="bom-empty-text">Chưa có BOM nào</div>
                            <a href="/modules/bom/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Thêm BOM đầu tiên
                            </a>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = boms.map(bom => `
            <tr class="fade-in-up">
                <td>
                    <div class="d-flex flex-column">
                        <span class="bom-code">${CMMS.escapeHtml(bom.bom_code)}</span>
                        <strong>${CMMS.escapeHtml(bom.bom_name)}</strong>
                    </div>
                </td>
                <td>${CMMS.escapeHtml(bom.machine_type_name)}</td>
                <td>${CMMS.escapeHtml(bom.version)}</td>
                <td class="text-center">${bom.total_items}</td>
                <td class="text-end">${CMMS.BOM.formatCurrency(bom.total_cost)}</td>
                <td class="hide-mobile">${CMMS.formatDateTime(bom.created_at)}</td>
                <td class="hide-mobile">${CMMS.escapeHtml(bom.created_by_name)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="/modules/bom/view.php?id=${bom.id}" class="btn btn-outline-primary btn-sm" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/modules/bom/edit.php?id=${bom.id}" class="btn btn-outline-warning btn-sm" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="CMMS.BOM.deleteBOM(${bom.id})" class="btn btn-outline-danger btn-sm" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },
    
    // Render pagination
    renderPagination: function(pagination) {
        const paginationElement = document.getElementById('bomPagination');
        if (!paginationElement) return;
        
        let pages = [];
        for (let i = 1; i <= pagination.total_pages; i++) {
            pages.push(`
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="CMMS.BOM.changePage(${i});return false;">${i}</a>
                </li>
            `);
        }
        
        paginationElement.innerHTML = `
            <nav aria-label="BOM pagination">
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item ${pagination.has_previous ? '' : 'disabled'}">
                        <a class="page-link" href="#" onclick="CMMS.BOM.changePage(${pagination.current_page - 1});return false;">Trước</a>
                    </li>
                    ${pages.join('')}
                    <li class="page-item ${pagination.has_next ? '' : 'disabled'}">
                        <a class="page-link" href="#" onclick="CMMS.BOM.changePage(${pagination.current_page + 1});return false;">Sau</a>
                    </li>
                </ul>
            </nav>
        `;
    },
    
    // Change page
    changePage: function(page) {
        if (page < 1) return;
        this.config.currentPage = page;
        this.loadBOMList();
    },
    
    // Delete BOM
    deleteBOM: function(bomId) {
        CMMS.confirm('Bạn có chắc chắn muốn xóa BOM này?', () => {
            CMMS.ajax({
                url: this.config.apiUrl + 'bom.php',
                method: 'POST',
                body: `action=delete&id=${bomId}`,
                success: (data) => {
                    if (data.success) {
                        CMMS.showToast(data.message, 'success');
                        this.loadBOMList();
                    } else {
                        CMMS.showToast(data.message, 'error');
                    }
                }
            });
        });
    },
    
    // Export BOM
    exportBOM: function(bomId, format = 'excel') {
        const url = `/modules/bom/export.php?id=${bomId}&format=${format}`;
        window.location.href = url;
    },
    
    // Import BOM
    importBOM: function() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.xlsx,.xls';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'import');
            
            CMMS.ajax({
                url: this.config.apiUrl + 'bom.php',
                method: 'POST',
                body: formData,
                success: (data) => {
                    if (data.success) {
                        CMMS.showToast(data.message, 'success');
                        this.loadBOMList();
                    } else {
                        CMMS.showToast(data.message, 'error');
                    }
                }
            });
        };
        input.click();
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    },
    
    // Add BOM item
    addBOMItem: function() {
        const tbody = document.getElementById('bomItemsBody');
        if (!tbody) return;
        const rowIndex = tbody.children.length;
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>
                <select name="items[${rowIndex}][part_id]" class="form-select part-select" required onchange="CMMS.BOM.updatePartDetails(this)">
                    <option value="">-- Chọn linh kiện --</option>
                    ${window.bomPartsData ? window.bomPartsData.map(part => `<option value="${part.id}" data-price="${part.unit_price}" data-unit="${part.unit}" data-name="${part.part_name}" data-code="${part.part_code}">${part.part_code} - ${part.part_name}</option>`).join('') : ''}
                </select>
            </td>
            <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control quantity" min="0.01" step="0.01" required oninput="updateTotals()"></td>
            <td><input type="text" name="items[${rowIndex}][unit]" class="form-control" readonly></td>
            <td><input type="number" name="items[${rowIndex}][unit_price]" class="form-control" readonly></td>
            <td>
                <select name="items[${rowIndex}][priority]" class="form-select">
                    ${window.bomConfig && Array.isArray(window.bomConfig.priorities) ? window.bomConfig.priorities.map(p => `<option value="${p}">${p}</option>`).join('') : '<option value="Medium">Medium</option>'}
                </select>
            </td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); updateTotals()">Xóa</button></td>
        `;
    },
    
    // Update part details
    updatePartDetails: function(select) {
        const row = select.closest('tr');
        const option = select.selectedOptions[0];
        row.querySelector('input[name$="[unit]"]').value = option.dataset.unit || '';
        row.querySelector('input[name$="[unit_price]"]').value = option.dataset.price || 0;
        updateTotals();
    },
    
    // Add part to BOM (from quick search)
    addPartToBOM: function(partId) {
        const existingSelects = document.querySelectorAll('select[name$="[part_id]"]');
        for (let select of existingSelects) {
            if (select.value == partId) {
                select.closest('tr').style.backgroundColor = '#fef3c7';
                setTimeout(() => {
                    select.closest('tr').style.backgroundColor = '';
                }, 2000);
                CMMS.showToast('Linh kiện này đã có trong BOM', 'warning');
                return;
            }
        }
        
        this.addBOMItem();
        const tbody = document.getElementById('bomItemsBody');
        const newRow = tbody.lastElementChild;
        const partSelect = newRow.querySelector('select[name$="[part_id]"]');
        
        if (partSelect) {
            partSelect.value = partId;
            partSelect.dispatchEvent(new Event('change'));
        }
        
        document.getElementById('quickSearch').value = '';
        document.getElementById('quickSearchResults').innerHTML = '';
        
        const quantityInput = newRow.querySelector('input[name$="[quantity]"]');
        if (quantityInput) {
            quantityInput.focus();
            quantityInput.select();
        }
        
        updateTotals();
    },
    
    // Submit BOM (for add)
    submitBOM: function(form) {
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            CMMS.showToast('Vui lòng kiểm tra dữ liệu form', 'warning');
            return;
        }
        
        const formData = new FormData(form);
        formData.append('action', 'save');
        
        CMMS.showLoading();
        CMMS.ajax({
            url: this.config.apiUrl + 'bom.php',
            method: 'POST',
            body: formData,
            success: (data) => {
                CMMS.hideLoading();
                if (data.success) {
                    CMMS.showToast(data.message || 'Tạo BOM thành công!', 'success');
                    setTimeout(() => window.location.href = '/modules/bom/index.php', 1500);
                } else {
                    CMMS.showToast(data.message || 'Lỗi lưu BOM', 'error');
                }
            },
            error: (err) => {
                CMMS.hideLoading();
                CMMS.showToast('Lỗi kết nối server: ' + err.message, 'error');
                console.error(err);
            }
        });
    },
    
    // Update BOM (for edit)
    updateBOM: function(form) {
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            CMMS.showToast('Vui lòng kiểm tra dữ liệu form', 'warning');
            return;
        }
        
        const formData = new FormData(form);
        formData.append('action', 'update');
        
        CMMS.showLoading();
        CMMS.ajax({
            url: this.config.apiUrl + 'bom.php',
            method: 'POST',
            body: formData,
            success: (data) => {
                CMMS.hideLoading();
                if (data.success) {
                    CMMS.showToast(data.message || 'Cập nhật BOM thành công!', 'success');
                    setTimeout(() => window.location.href = '/modules/bom/view.php?id=' + formData.get('bom_id'), 1500);
                } else {
                    CMMS.showToast(data.message || 'Lỗi cập nhật BOM', 'error');
                }
            },
            error: (err) => {
                CMMS.hideLoading();
                CMMS.showToast('Lỗi kết nối server: ' + err.message, 'error');
                console.error(err);
            }
        });
    },
    
    // Load parts list
    loadPartsList: function(filters = {}) {
        CMMS.showLoading();
        
        const params = new URLSearchParams({
            action: 'list',
            ...filters
        });
        
        CMMS.ajax({
            url: '/modules/bom/api/parts.php?' + params,
            method: 'GET',
            success: (data) => {
                if (data.success) {
                    this.renderPartsList(data.data);
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            }
        });
    },
    
    // Search parts
    searchParts: function(searchTerm) {
        const filters = this.getFilters();
        filters.search = searchTerm;
        this.loadPartsList(filters);
    },
    
    // Render parts list
    renderPartsList: function(parts) {
        const tbody = document.querySelector('#partsTable tbody');
        if (!tbody) return;
        
        if (parts.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="bom-empty">
                            <div class="bom-empty-icon">
                                <i class="fas fa-cube"></i>
                            </div>
                            <div class="bom-empty-text">Chưa có linh kiện nào</div>
                            <a href="/modules/bom/parts/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Thêm linh kiện đầu tiên
                            </a>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = parts.map(part => `
            <tr class="fade-in-up">
                <td>
                    <div class="d-flex flex-column">
                        <span class="part-code">${CMMS.escapeHtml(part.part_code)}</span>
                        <strong>${CMMS.escapeHtml(part.part_name)}</strong>
                    </div>
                </td>
                <td>
                    <span class="badge bg-secondary">${CMMS.escapeHtml(part.category || 'N/A')}</span>
                </td>
                <td class="text-center">
                    ${part.unit ? CMMS.escapeHtml(part.unit) : 'N/A'}
                </td>
                <td class="text-end">
                    <span class="cost-display">${CMMS.BOM.formatCurrency(part.unit_price || 0)}</span>
                </td>
                <td class="text-center">
                    <div class="stock-indicator">
                        <span class="stock-dot ${part.stock_status?.toLowerCase() || 'ok'}"></span>
                        ${part.stock_quantity || 0} ${part.stock_unit || part.unit || ''}
                    </div>
                </td>
                <td class="hide-mobile">
                    <small>${CMMS.escapeHtml(part.supplier_name || 'N/A')}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="/modules/bom/parts/view.php?id=${part.id}" class="btn btn-outline-primary btn-sm" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/modules/bom/parts/edit.php?id=${part.id}" class="btn btn-outline-warning btn-sm" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="CMMS.Parts.deletePart(${part.id})" class="btn btn-outline-danger btn-sm" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },
    
    // Delete part
    deletePart: function(partId) {
        CMMS.confirm('Bạn có chắc chắn muốn xóa linh kiện này?', () => {
            CMMS.ajax({
                url: '/modules/bom/api/parts.php',
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
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const path = window.location.pathname;
    
    if (path.includes('/modules/bom/parts/')) {
        CMMS.Parts.init();
    } else if (path.includes('/modules/bom/')) {
        CMMS.BOM.init();
    }
    
    // Global BOM utilities
    window.addBOMItem = () => CMMS.BOM.addBOMItem();
    window.removeBOMItem = (btn) => CMMS.BOM.removeBOMItem(btn);
    window.exportBOM = (id, format) => CMMS.BOM.exportBOM(id, format);
    window.importBOM = () => CMMS.BOM.importBOM();
});