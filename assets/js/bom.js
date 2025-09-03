/**
 * BOM Module JavaScript - /assets/js/bom.js
 * Main JavaScript functionality for BOM module
 */

// Extend CMMS object for BOM module
if (!window.CMMS) {
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
                this.saveBOM();
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
    },
    
    // Initialize components
    initializeComponents: function() {
        // Initialize data tables
        const bomTable = document.getElementById('bomTable');
        if (bomTable) {
            CMMS.dataTable.init('bomTable', {
                searching: false, // We handle search separately
                pageSize: this.config.pageSize
            });
        }
        
        // Initialize tooltips for BOM items
        this.initializeTooltips();
        
        // Load initial data
        this.loadBOMList();
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
    
    // Load BOM list
// Load BOM list
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
            credentials: 'same-origin', // Include cookies/session
            success: (data) => {
                console.log('API Response:', data);
                
                if (data.success) {
                    // Check if data.data is array
                    const boms = Array.isArray(data.data) ? data.data : [];
                    this.renderBOMList(boms);
                    
                    if (data.pagination) {
                        this.renderPagination(data.pagination);
                    }
                } else {
                    console.error('API Error:', data.message);
                    
                    // Handle authentication error specifically
                    if (data.message && data.message.includes('Authentication')) {
                        CMMS.showToast('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 'error');
                        setTimeout(() => {
                            window.location.href = '/login.php';
                        }, 2000);
                        return;
                    }
                    
                    CMMS.showToast(data.message || 'Lỗi không xác định', 'error');
                    // Show empty state
                    this.renderBOMList([]);
                }
            },
            error: (error) => {
                console.error('Ajax Error:', error);
                
                // Handle different types of errors
                if (error.message && error.message.includes('401')) {
                    CMMS.showToast('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 'error');
                    setTimeout(() => {
                        window.location.href = '/login.php';
                    }, 2000);
                } else {
                    CMMS.showToast('Lỗi khi tải danh sách BOM', 'error');
                }
                
                // Show empty state
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
                                <i class="fas fa-plus me-2"></i>Tạo BOM đầu tiên
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
                        <strong>${CMMS.escapeHtml(bom.bom_name)}</strong>
                        <small class="text-muted">${CMMS.escapeHtml(bom.bom_code)}</small>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span>${CMMS.escapeHtml(bom.machine_type_name)}</span>
                        <small class="text-muted part-code">${CMMS.escapeHtml(bom.machine_type_code)}</small>
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-info">${bom.total_items || 0}</span>
                </td>
                <td class="text-end">
                    <span class="cost-display">${this.formatCurrency(bom.total_cost || 0)}</span>
                </td>
                <td class="hide-mobile">
                    <small class="text-muted">${CMMS.formatDate(bom.created_at)}</small>
                </td>
                <td class="hide-mobile">
                    <small class="text-muted">${CMMS.escapeHtml(bom.created_by_name || '')}</small>
                </td>
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
    
    // Generate BOM code automatically
    generateBOMCode: function(machineTypeId) {
        if (!machineTypeId) return;
        
        CMMS.ajax({
            url: this.config.apiUrl + 'bom.php',
            method: 'POST',
            body: `action=generateCode&machine_type_id=${machineTypeId}`,
            success: (data) => {
                if (data.success) {
                    const bomCodeInput = document.getElementById('bom_code');
                    if (bomCodeInput) {
                        bomCodeInput.value = data.code;
                    }
                }
            }
        });
    },
    
    // Add new BOM item row
    addBOMItem: function() {
        const tbody = document.getElementById('bomItemsBody');
        if (!tbody) return;
        
        const rowCount = tbody.children.length;
        const newRow = this.createBOMItemRow(rowCount);
        
        tbody.appendChild(newRow);
        
        // Focus on part selection
        const partSelect = newRow.querySelector('select[name$="[part_id]"]');
        if (partSelect) {
            partSelect.focus();
        }
        
        // Update row numbers
        this.updateRowNumbers();
    },
    
    // Create BOM item row
    createBOMItemRow: function(index, itemData = {}) {
        const row = document.createElement('tr');
        row.className = 'bom-item-row bom-item-new';
        
        row.innerHTML = `
            <td class="text-center">${index + 1}</td>
            <td>
                <select name="items[${index}][part_id]" class="form-select" required>
                    <option value="">-- Chọn linh kiện --</option>
                    ${this.getPartsOptions(itemData.part_id)}
                </select>
            </td>
            <td>
                <input type="number" name="items[${index}][quantity]" class="form-control" 
                       value="${itemData.quantity || 1}" min="0.01" step="0.01" required>
            </td>
            <td>
                <select name="items[${index}][unit]" class="form-select">
                    ${this.getUnitsOptions(itemData.unit)}
                </select>
            </td>
            <td>
                <input type="text" name="items[${index}][position]" class="form-control" 
                       value="${itemData.position || ''}" placeholder="Vị trí lắp đặt">
            </td>
            <td>
                <select name="items[${index}][priority]" class="form-select">
                    <option value="Low" ${itemData.priority === 'Low' ? 'selected' : ''}>Thấp</option>
                    <option value="Medium" ${itemData.priority === 'Medium' || !itemData.priority ? 'selected' : ''}>Trung bình</option>
                    <option value="High" ${itemData.priority === 'High' ? 'selected' : ''}>Cao</option>
                    <option value="Critical" ${itemData.priority === 'Critical' ? 'selected' : ''}>Nghiêm trọng</option>
                </select>
            </td>
            <td>
                <input type="number" name="items[${index}][maintenance_interval]" class="form-control" 
                       value="${itemData.maintenance_interval || ''}" placeholder="Giờ">
            </td>
            <td class="text-center">
                <button type="button" class="btn-remove-item" onclick="CMMS.BOM.removeBOMItem(this)">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        
        // Bind events for the new row
        this.bindRowEvents(row);
        
        return row;
    },
    
    // Remove BOM item row
    removeBOMItem: function(button) {
        const row = button.closest('tr');
        if (row) {
            row.remove();
            this.updateRowNumbers();
            this.calculateTotalCost();
        }
    },
    
    // Update row numbers after add/remove
    updateRowNumbers: function() {
        const rows = document.querySelectorAll('#bomItemsBody tr');
        rows.forEach((row, index) => {
            const numberCell = row.querySelector('td:first-child');
            if (numberCell) {
                numberCell.textContent = index + 1;
            }
            
            // Update input names
            const inputs = row.querySelectorAll('input, select');
            inputs.forEach(input => {
                const name = input.name;
                if (name && name.includes('[')) {
                    input.name = name.replace(/\[\d+\]/, `[${index}]`);
                }
            });
        });
    },
    
    // Bind events for BOM item row
    bindRowEvents: function(row) {
        // Part selection change
        const partSelect = row.querySelector('select[name$="[part_id]"]');
        if (partSelect) {
            partSelect.addEventListener('change', (e) => {
                this.onPartChange(e.target);
            });
        }
        
        // Quantity change
        const quantityInput = row.querySelector('input[name$="[quantity]"]');
        if (quantityInput) {
            quantityInput.addEventListener('input', () => {
                this.calculateTotalCost();
            });
        }
    },
    
    // Handle part selection change
    onPartChange: function(partSelect) {
        const partId = partSelect.value;
        if (!partId) return;
        
        // Get part details and update UI
        CMMS.ajax({
            url: this.config.apiUrl + 'parts.php',
            method: 'POST',
            body: `action=getDetails&id=${partId}`,
            success: (data) => {
                if (data.success) {
                    const row = partSelect.closest('tr');
                    const unitSelect = row.querySelector('select[name$="[unit]"]');
                    
                    if (unitSelect && data.part.unit) {
                        unitSelect.value = data.part.unit;
                    }
                    
                    // Update cost calculation
                    this.calculateTotalCost();
                }
            }
        });
    },
    
    // Calculate total BOM cost
    calculateTotalCost: function() {
        let totalCost = 0;
        const rows = document.querySelectorAll('#bomItemsBody tr');
        
        rows.forEach(row => {
            const partSelect = row.querySelector('select[name$="[part_id]"]');
            const quantityInput = row.querySelector('input[name$="[quantity]"]');
            
            if (partSelect && quantityInput && partSelect.value && quantityInput.value) {
                const partOption = partSelect.selectedOptions[0];
                const unitPrice = parseFloat(partOption.dataset.price || 0);
                const quantity = parseFloat(quantityInput.value || 0);
                
                totalCost += unitPrice * quantity;
            }
        });
        
        const totalCostElement = document.getElementById('totalCost');
        if (totalCostElement) {
            totalCostElement.textContent = this.formatCurrency(totalCost);
        }
    },
    
    // Save BOM
    saveBOM: function() {
        const form = document.getElementById('bomForm');
        const formData = new FormData(form);
        
        // Validate form
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        // Add action
        formData.append('action', 'save');
        
        CMMS.ajax({
            url: this.config.apiUrl + 'bom.php',
            method: 'POST',
            body: formData,
            success: (data) => {
                if (data.success) {
                    CMMS.showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = '/modules/bom/view.php?id=' + data.bom_id;
                    }, 1500);
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            }
        });
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
        const url = `${this.config.apiUrl}export.php?action=bom&id=${bomId}&format=${format}`;
        window.open(url, '_blank');
    },
    
    // Import BOM from Excel
    importBOM: function() {
        const fileInput = document.getElementById('bomImportFile');
        if (!fileInput || !fileInput.files[0]) {
            CMMS.showToast('Vui lòng chọn file để import', 'warning');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('action', 'import');
        
        CMMS.ajax({
            url: '/modules/bom/imports/bom_import.php',
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
    },
    
    // Get parts options HTML
    getPartsOptions: function(selectedId = '') {
        // This should be populated from server data
        if (window.bomPartsData) {
            return window.bomPartsData.map(part => 
                `<option value="${part.id}" data-price="${part.unit_price}" data-unit="${part.unit}" 
                 ${part.id == selectedId ? 'selected' : ''}>${part.part_code} - ${part.part_name}</option>`
            ).join('');
        }
        return '';
    },
    
    // Get units options HTML
    getUnitsOptions: function(selectedUnit = '') {
        const units = ['Cái', 'Bộ', 'Chiếc', 'Kg', 'g', 'Lít', 'ml', 'm', 'cm', 'mm', 'Tấm', 'Cuộn', 'Gói', 'Hộp', 'Thùng'];
        return units.map(unit => 
            `<option value="${unit}" ${unit === selectedUnit ? 'selected' : ''}>${unit}</option>`
        ).join('');
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            minimumFractionDigits: 0
        }).format(amount);
    },
    
    // Initialize tooltips
    initializeTooltips: function() {
        const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipElements.forEach(element => {
            new bootstrap.Tooltip(element);
        });
    },
    
    // Render pagination
    renderPagination: function(pagination) {
        const paginationContainer = document.getElementById('bomPagination');
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
                    this.loadBOMList(this.getFilters());
                }
            });
        });
    }
};

// Parts management functionality
CMMS.Parts = {
    // Initialize parts management
    init: function() {
        this.bindEvents();
        this.loadPartsList();
    },
    
    // Bind events
    bindEvents: function() {
        // Search parts
        const searchInput = document.getElementById('partsSearch');
        if (searchInput) {
            searchInput.addEventListener('input', CMMS.utils.debounce((e) => {
                this.searchParts(e.target.value);
            }, 300));
        }
        
        // Category filter
        const categoryFilter = document.getElementById('filterCategory');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', () => {
                this.applyFilters();
            });
        }
        
        // Stock status filter
        const stockFilter = document.getElementById('filterStockStatus');
        if (stockFilter) {
            stockFilter.addEventListener('change', () => {
                this.applyFilters();
            });
        }
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
    
    // Apply filters
    applyFilters: function() {
        const filters = this.getFilters();
        this.loadPartsList(filters);
    },
    
    // Get filter values
    getFilters: function() {
        return {
            category: document.getElementById('filterCategory')?.value || '',
            stock_status: document.getElementById('filterStockStatus')?.value || '',
            search: document.getElementById('partsSearch')?.value || ''
        };
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
    // Check which page we're on and initialize accordingly
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