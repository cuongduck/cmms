/**
 * Equipment Module JavaScript
 * Global utilities and functionality for Equipment management
 */

// Extend CMMS object with Equipment utilities
if (!window.CMMS) {
    window.CMMS = {};
}
Object.assign(window.CMMS, {
    
    equipment: {
        // Current state
        currentPage: 1,
        currentData: [],
        selectedItems: new Set(),
        currentFilters: {
            search: '',
            industry_id: '',
            workshop_id: '',
            line_id: '',
            area_id: '',
            machine_type_id: '',
            status: '',
            criticality: '',
            owner_id: '',
            sort_by: 'name',
            sort_order: 'ASC'
        },
        
        // Configuration
        config: {
            itemsPerPage: 20,
            maxItemsPerPage: 100,
            debounceDelay: 500,
            imagePreviewSize: 150,
            allowedImageTypes: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            maxImageSize: 5 * 1024 * 1024 // 5MB
        },
        
        // Initialize equipment module
        init: function() {
            console.log('Equipment module initializing...');
            this.initializeEventListeners();
            this.loadData();
            this.initializeKeyboardShortcuts();
        },
        
        // Initialize all event listeners
        initializeEventListeners: function() {
            console.log('Initializing equipment event listeners...');
            
            // Filter form listeners
            this.initializeFilterListeners();
            
            // Search with debounce
            this.initializeSearchListener();
            
            // Selection listeners
            this.initializeSelectionListeners();
            
            // Image upload listeners
            this.initializeImageListeners();
            
            // Modal listeners
            this.initializeModalListeners();
            
            // Bulk action listeners
            this.initializeBulkActionListeners();
        },
        
        // Initialize filter listeners
        initializeFilterListeners: function() {
            const filterInputs = document.querySelectorAll('#filterForm input, #filterForm select');
            
            filterInputs.forEach(input => {
                input.addEventListener('change', (e) => {
                    console.log('Filter changed:', e.target.id, e.target.value);
                    
                    // Special handling for dependent dropdowns
                    if (e.target.id === 'industryFilter') {
                        this.updateWorkshopFilter();
                    } else if (e.target.id === 'workshopFilter') {
                        this.updateLineFilter();
                    } else if (e.target.id === 'lineFilter') {
                        this.updateAreaFilter();
                    }
                    
                    this.currentPage = 1;
                    this.loadData();
                });
            });
        },
        
        // Initialize search listener with debounce
        initializeSearchListener: function() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    console.log('Search input:', e.target.value);
                    this.currentPage = 1;
                    this.loadData();
                }, this.config.debounceDelay);
            });
        },
        
        // Initialize selection listeners
        initializeSelectionListeners: function() {
            // Select all checkbox
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', (e) => {
                    this.handleSelectAll(e.target.checked);
                });
            }
        },
        
        // Initialize image listeners
        initializeImageListeners: function() {
            // Image click for preview
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('equipment-image')) {
                    this.showImagePreview(e.target.src, e.target.alt);
                }
            });
            
            // File upload drag and drop
            this.initializeDragAndDrop();
        },
        
        // Initialize modal listeners
        initializeModalListeners: function() {
            // Modal cleanup on close
            document.addEventListener('hidden.bs.modal', (e) => {
                if (e.target.id === 'bulkActionsModal') {
                    this.clearSelection();
                }
            });
        },
        
        // Initialize bulk action listeners
        initializeBulkActionListeners: function() {
            const bulkButtons = document.querySelectorAll('[data-bulk-action]');
            bulkButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    const action = e.target.dataset.bulkAction;
                    this.handleBulkAction(action);
                });
            });
        },
        
        // Initialize keyboard shortcuts
        initializeKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                // Only process if not typing in an input
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }
                
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key.toLowerCase()) {
                        case 'f':
                            e.preventDefault();
                            document.getElementById('searchInput')?.focus();
                            break;
                        case 'a':
                            e.preventDefault();
                            this.handleSelectAll(true);
                            break;
                        case 'r':
                            e.preventDefault();
                            this.refreshData();
                            break;
                        case 'n':
                            e.preventDefault();
                            window.location.href = 'add.php';
                            break;
                    }
                }
                
                // Escape key
                if (e.key === 'Escape') {
                    this.clearSelection();
                    this.resetFilters();
                }
            });
        },
        
        // Load equipment data from API
        loadData: async function() {
            console.log('Loading equipment data...');
            try {
                this.showLoading(true);
                
                // Build filter parameters
                const params = this.buildFilterParams();
                
                const response = await fetch(`api/equipment.php?${params}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('Equipment API Response:', result);

                if (result.success && result.data) {
                    this.currentData = result.data.equipment || [];
                    this.renderTable(this.currentData);
                    this.renderPagination(result.data.pagination);
                    this.updatePaginationInfo(result.data.pagination);
                    this.updateStats();
                } else {
                    console.error('API Error:', result.message);
                    CMMS.showToast(result.message || 'Lỗi khi tải dữ liệu', 'error');
                    this.renderTable([]);
                }
            } catch (error) {
                console.error('Load data error:', error);
                CMMS.showToast('Lỗi kết nối: ' + error.message, 'error');
                this.renderTable([]);
            } finally {
                this.showLoading(false);
            }
        },
        
        // Build filter parameters for API call
        buildFilterParams: function() {
            // Get current filter values from DOM
            const searchEl = document.getElementById('searchInput');
            const industryEl = document.getElementById('industryFilter');
            const workshopEl = document.getElementById('workshopFilter');
            const lineEl = document.getElementById('lineFilter');
            const areaEl = document.getElementById('areaFilter');
            const machineTypeEl = document.getElementById('machineTypeFilter');
            const statusEl = document.getElementById('statusFilter');
            const criticalityEl = document.getElementById('criticalityFilter');
            const ownerEl = document.getElementById('ownerFilter');
            
            this.currentFilters = {
                search: searchEl?.value.trim() || '',
                industry_id: industryEl?.value || '',
                workshop_id: workshopEl?.value || '',
                line_id: lineEl?.value || '',
                area_id: areaEl?.value || '',
                machine_type_id: machineTypeEl?.value || '',
                status: statusEl?.value || '',
                criticality: criticalityEl?.value || '',
                owner_id: ownerEl?.value || '',
                sort_by: 'name',
                sort_order: 'ASC'
            };

            const params = new URLSearchParams({
                action: 'list',
                page: this.currentPage,
                limit: this.config.itemsPerPage,
                ...this.currentFilters
            });

            return params;
        },
        
        // Render equipment table
        renderTable: function(data) {
            console.log('Rendering equipment table with', data.length, 'items');
            
            const tbody = document.getElementById('dataTableBody');
            const emptyState = document.getElementById('emptyState');
            
            if (!tbody) {
                console.error('dataTableBody element not found!');
                return;
            }
            
            if (!data || data.length === 0) {
                tbody.innerHTML = '';
                if (emptyState) emptyState.classList.remove('d-none');
                return;
            }
            
            if (emptyState) emptyState.classList.add('d-none');
            
            const startIndex = (this.currentPage - 1) * this.config.itemsPerPage;
            
            const rows = data.map((item, index) => {
                return this.renderTableRow(item, startIndex + index + 1);
            });
            
            tbody.innerHTML = rows.join('');
            
            // Re-attach event listeners for new rows
            this.attachRowEventListeners();
        },
        
        // Render single table row
        renderTableRow: function(item, rowNumber) {
            const criticalityClass = `criticality-${item.criticality.toLowerCase()}`;
            const maintenanceIndicator = item.maintenance_due ? 'maintenance-indicator' : '';
            const isSelected = this.selectedItems.has(item.id);
            
            return `
                <tr data-id="${item.id}" class="${isSelected ? 'table-active' : ''}">
                    <td>
                        <input type="checkbox" class="form-check-input item-checkbox" 
                               value="${item.id}" ${isSelected ? 'checked' : ''}
                               onchange="CMMS.equipment.handleItemSelection(this)">
                    </td>
                    <td>
                        ${this.renderEquipmentImage(item)}
                    </td>
                    <td>
                        <span class="equipment-code">${this.escapeHtml(item.code)}</span>
                    </td>
                    <td>
                        <div class="fw-semibold">${this.escapeHtml(item.name)}</div>
                        <div class="equipment-specs text-muted small" title="${this.escapeHtml(item.specifications || '')}">
                            ${item.specifications || 'Chưa có thông số'}
                        </div>
                    </td>
                    <td>
                        ${this.renderLocationPath(item)}
                    </td>
                    <td>
                        <span class="badge bg-info">${this.escapeHtml(item.machine_type_name || 'Chưa phân loại')}</span>
                    </td>
                    <td>
                        ${this.renderOwnerInfo(item)}
                    </td>
                    <td>
                        <span class="badge ${criticalityClass}" title="Mức độ quan trọng: ${item.criticality}">
                            ${item.criticality}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${item.status_class} ${maintenanceIndicator}" 
                              style="cursor: ${CMMS.hasPermission('equipment', 'edit') ? 'pointer' : 'default'};" 
                              onclick="${CMMS.hasPermission('equipment', 'edit') ? `CMMS.equipment.toggleStatus(${item.id})` : ''}"
                              title="${CMMS.hasPermission('equipment', 'edit') ? 'Click để thay đổi trạng thái' : ''}">
                            ${item.status_text}
                        </span>
                    </td>
                    <td class="text-muted small">
                        ${item.next_maintenance || 'Chưa lên lịch'}
                    </td>
                    <td>
                        ${this.renderActionButtons(item)}
                    </td>
                </tr>
            `;
        },
        
        // Render equipment image
        renderEquipmentImage: function(item) {
            if (item.image_url) {
                return `<img src="${item.image_url}" alt="${this.escapeHtml(item.name)}" class="equipment-image" loading="lazy">`;
            } else {
                return `<div class="no-image" title="Chưa có hình ảnh"><i class="fas fa-image"></i></div>`;
            }
        },
        
        // Render location path
        renderLocationPath: function(item) {
            const locationParts = [
                item.industry_name,
                item.workshop_name,
                item.line_name,
                item.area_name
            ].filter(Boolean);
            
            if (locationParts.length === 0) {
                return '<em class="text-muted">Chưa xác định vị trí</em>';
            }
            
            return `
                <div class="equipment-location">
                    <div class="fw-medium">${this.escapeHtml(locationParts[0] || '')}</div>
                    ${locationParts.slice(1).map(part => `<div class="text-muted">${this.escapeHtml(part)}</div>`).join('')}
                </div>
            `;
        },
        
        // Render owner information
        renderOwnerInfo: function(item) {
            if (!item.owner_name && !item.backup_owner_name) {
                return '<em class="text-muted">Chưa có</em>';
            }
            
            let html = '';
            if (item.owner_name) {
                html += `<div class="fw-medium">${this.escapeHtml(item.owner_name)}</div>`;
            }
            if (item.backup_owner_name) {
                html += `<div class="text-muted small">PB: ${this.escapeHtml(item.backup_owner_name)}</div>`;
            }
            
            return html;
        },
        
        // Render action buttons
        renderActionButtons: function(item) {
            const canEdit = window.canEdit || false;
            const canDelete = window.canDelete || false;
            
            return `
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-info btn-action" 
                            onclick="CMMS.equipment.viewEquipment(${item.id})" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${canEdit ? `
                    <button type="button" class="btn btn-outline-primary btn-action" 
                            onclick="CMMS.equipment.editEquipment(${item.id})" title="Chỉnh sửa">
                        <i class="fas fa-edit"></i>
                    </button>
                    ` : ''}
                    ${canDelete ? `
                    <button type="button" class="btn btn-outline-danger btn-action" 
                            onclick="CMMS.equipment.deleteEquipment(${item.id}, '${this.escapeHtml(item.name)}')" title="Xóa">
                        <i class="fas fa-trash"></i>
                    </button>
                    ` : ''}
                </div>
            `;
        },
        
        // Attach event listeners to table rows
        attachRowEventListeners: function() {
            // Row click for selection
            document.querySelectorAll('#dataTableBody tr').forEach(row => {
                row.addEventListener('click', (e) => {
                    // Don't trigger on button clicks
                    if (e.target.closest('.btn') || e.target.closest('input')) {
                        return;
                    }
                    
                    const checkbox = row.querySelector('.item-checkbox');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        this.handleItemSelection(checkbox);
                    }
                });
            });
        },
        
        // Handle item selection
        handleItemSelection: function(checkbox) {
            const itemId = parseInt(checkbox.value);
            const row = checkbox.closest('tr');
            
            if (checkbox.checked) {
                this.selectedItems.add(itemId);
                row?.classList.add('table-active');
            } else {
                this.selectedItems.delete(itemId);
                row?.classList.remove('table-active');
            }
            
            this.updateBulkActionsButton();
            this.updateSelectAllCheckbox();
        },
        
        // Handle select all
        handleSelectAll: function(selectAll) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll;
                this.handleItemSelection(checkbox);
            });
        },
        
        // Update select all checkbox state
        updateSelectAllCheckbox: function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            
            if (selectAllCheckbox) {
                if (checkedBoxes.length === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else if (checkedBoxes.length === checkboxes.length) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            }
        },
        
        // Update bulk actions button
        updateBulkActionsButton: function() {
            const count = this.selectedItems.size;
            const bulkButton = document.querySelector('[onclick*="showBulkActions"]');
            
            if (bulkButton) {
                if (count > 0) {
                    bulkButton.innerHTML = `<i class="fas fa-tasks me-1"></i> Thao tác (${count})`;
                    bulkButton.classList.remove('btn-outline-info');
                    bulkButton.classList.add('btn-warning');
                } else {
                    bulkButton.innerHTML = `<i class="fas fa-tasks me-1"></i> Thao tác hàng loạt`;
                    bulkButton.classList.remove('btn-warning');
                    bulkButton.classList.add('btn-outline-info');
                }
            }
            
            // Update selected count in modal
            const selectedCountEl = document.getElementById('selectedCount');
            if (selectedCountEl) {
                selectedCountEl.textContent = count;
            }
        },
       } ,
      }) ;