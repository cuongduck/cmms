/**
 * Item MMB JavaScript
 * /assets/js/item-mmb.js
 */

const ItemMMB = {
    currentEditCell: null,
    originalValue: null,
    
    init: function() {
        this.bindEvents();
    },
    
    bindEvents: function() {
        // Inline editing - click vào cell để edit
        document.addEventListener('click', (e) => {
            const cell = e.target.closest('.editable-cell');
            
            // Nếu click vào cell khác, save cell đang edit
            if (this.currentEditCell && this.currentEditCell !== cell) {
                this.saveCell(this.currentEditCell);
            }
            
            // Nếu click vào cell mới, bắt đầu edit
            if (cell && !cell.classList.contains('editing')) {
                this.startEdit(cell);
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (this.currentEditCell) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.saveCell(this.currentEditCell);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.cancelEdit(this.currentEditCell);
                }
            }
        });
    },
    
    startEdit: function(cell) {
        // Check permission
        if (!cell.dataset.id || !cell.dataset.field) return;
        
        // Save current cell reference
        this.currentEditCell = cell;
        this.originalValue = cell.textContent.trim();
        
        // Get field type
        const field = cell.dataset.field;
        const value = this.originalValue;
        
        // Create input based on field type
        let input;
        if (field === 'UNIT_PRICE') {
            input = document.createElement('input');
            input.type = 'number';
            input.step = '0.01';
            input.value = value.replace(/,/g, '');
            input.className = 'edit-input text-end';
        } else if (field === 'VENDOR_ID') {
            input = document.createElement('input');
            input.type = 'number';
            input.value = value;
            input.className = 'edit-input';
        } else {
            input = document.createElement('input');
            input.type = 'text';
            input.value = value;
            input.className = 'edit-input';
        }
        
        // Replace cell content with input
        cell.classList.add('editing');
        cell.innerHTML = '';
        cell.appendChild(input);
        
        // Focus and select
        input.focus();
        input.select();
        
        // Save on blur (click outside)
        input.addEventListener('blur', () => {
            setTimeout(() => {
                if (this.currentEditCell === cell) {
                    this.saveCell(cell);
                }
            }, 200);
        });
    },
    
    saveCell: function(cell) {
        if (!cell || !cell.classList.contains('editing')) return;
        
        const input = cell.querySelector('.edit-input');
        if (!input) return;
        
        const newValue = input.value.trim();
        const id = cell.dataset.id;
        const field = cell.dataset.field;
        
        // Check if value changed
        if (newValue === this.originalValue) {
            this.cancelEdit(cell);
            return;
        }
        
        // Show loading
        CMMS.showLoading();
        
        // Send update request
        CMMS.ajax({
            url: '/modules/item_mmb/api/items.php',
            method: 'POST',
            body: new URLSearchParams({
                action: 'update_field',
                id: id,
                field: field,
                value: newValue
            }),
            success: (data) => {
                CMMS.hideLoading();
                
                if (data.success) {
                    // Update cell with formatted value
                    cell.classList.remove('editing');
                    cell.innerHTML = CMMS.escapeHtml(data.data.formatted_value);
                    
                    // Add edit icon back
                    const editIcon = document.createElement('span');
                    editIcon.className = 'edit-icons';
                    editIcon.innerHTML = '<i class="fas fa-edit text-primary"></i>';
                    cell.appendChild(editIcon);
                    
                    // Update time
                    const row = cell.closest('tr');
                    const timeCell = row.querySelector('td:nth-last-child(2)');
                    if (timeCell && data.data.time_update) {
                        timeCell.textContent = data.data.time_update;
                    }
                    
                    // Show success animation
                    cell.style.backgroundColor = '#d1fae5';
                    setTimeout(() => {
                        cell.style.backgroundColor = '';
                    }, 1000);
                    
                    CMMS.showToast(data.message, 'success');
                } else {
                    this.cancelEdit(cell);
                    CMMS.showToast(data.message, 'error');
                }
                
                this.currentEditCell = null;
                this.originalValue = null;
            },
            error: () => {
                CMMS.hideLoading();
                this.cancelEdit(cell);
                CMMS.showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
            }
        });
    },
    
    cancelEdit: function(cell) {
        if (!cell || !cell.classList.contains('editing')) return;
        
        cell.classList.remove('editing');
        cell.innerHTML = CMMS.escapeHtml(this.originalValue);
        
        // Add edit icon back
        const editIcon = document.createElement('span');
        editIcon.className = 'edit-icons';
        editIcon.innerHTML = '<i class="fas fa-edit text-primary"></i>';
        cell.appendChild(editIcon);
        
        this.currentEditCell = null;
        this.originalValue = null;
    },
    
    showAddForm: function() {
        const tbody = document.getElementById('itemsTableBody');
        
        // Remove existing add form if any
        const existingForm = tbody.querySelector('.inline-form-row');
        if (existingForm) {
            existingForm.remove();
        }
        
        // Create new row for adding
        const tr = document.createElement('tr');
        tr.className = 'inline-form-row';
        tr.innerHTML = `
            <td class="text-center">
                <i class="fas fa-plus-circle text-success"></i>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" 
                       id="new_item_code" placeholder="Mã item" required>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" 
                       id="new_item_name" placeholder="Tên item" required>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" 
                       id="new_uom" placeholder="Đơn vị">
            </td>
            <td>
                <input type="number" step="0.01" class="form-control form-control-sm" 
                       id="new_unit_price" placeholder="Đơn giá">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm" 
                       id="new_vendor_id" placeholder="Mã NCC">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" 
                       id="new_vendor_name" placeholder="Tên NCC">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-success me-1" onclick="ItemMMB.saveNewItem()">
                    <i class="fas fa-check"></i> Lưu
                </button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="ItemMMB.cancelAddForm()">
                    <i class="fas fa-times"></i> Hủy
                </button>
            </td>
        `;
        
        // Insert at the beginning
        tbody.insertBefore(tr, tbody.firstChild);
        
        // Focus on first input
        document.getElementById('new_item_code').focus();
        
        // Handle Enter key to move to next field
        tr.querySelectorAll('input').forEach((input, index, inputs) => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    } else {
                        this.saveNewItem();
                    }
                } else if (e.key === 'Escape') {
                    this.cancelAddForm();
                }
            });
        });
    },
    
    saveNewItem: function() {
        const data = {
            item_code: document.getElementById('new_item_code').value.trim(),
            item_name: document.getElementById('new_item_name').value.trim(),
            uom: document.getElementById('new_uom').value.trim(),
            unit_price: document.getElementById('new_unit_price').value,
            vendor_id: document.getElementById('new_vendor_id').value,
            vendor_name: document.getElementById('new_vendor_name').value.trim()
        };
        
        // Validation
        if (!data.item_code) {
            CMMS.showToast('Vui lòng nhập mã item', 'warning');
            document.getElementById('new_item_code').focus();
            return;
        }
        
        if (!data.item_name) {
            CMMS.showToast('Vui lòng nhập tên item', 'warning');
            document.getElementById('new_item_name').focus();
            return;
        }
        
        CMMS.showLoading();
        
        const formData = new URLSearchParams({
            action: 'create',
            ...data
        });
        
        CMMS.ajax({
            url: '/modules/item_mmb/api/items.php',
            method: 'POST',
            body: formData,
            success: (response) => {
                CMMS.hideLoading();
                
                if (response.success) {
                    CMMS.showToast(response.message, 'success');
                    
                    // Reload page to show new item
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    CMMS.showToast(response.message, 'error');
                }
            },
            error: () => {
                CMMS.hideLoading();
                CMMS.showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
            }
        });
    },
    
    cancelAddForm: function() {
        const form = document.querySelector('.inline-form-row');
        if (form) {
            form.remove();
        }
    },
    
    deleteItem: function(id) {
        if (!confirm('Bạn có chắc chắn muốn xóa item này?')) {
            return;
        }
        
        CMMS.showLoading();
        
        CMMS.ajax({
            url: '/modules/item_mmb/api/items.php',
            method: 'POST',
            body: new URLSearchParams({
                action: 'delete',
                id: id
            }),
            success: (data) => {
                CMMS.hideLoading();
                
                if (data.success) {
                    CMMS.showToast(data.message, 'success');
                    
                    // Remove row with animation
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.style.backgroundColor = '#fee2e2';
                        row.style.transition = 'all 0.3s';
                        setTimeout(() => {
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                
                                // Check if table is empty
                                const tbody = document.getElementById('itemsTableBody');
                                if (tbody.children.length === 0) {
                                    tbody.innerHTML = `
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                                <p class="text-muted">Không tìm thấy dữ liệu</p>
                                            </td>
                                        </tr>
                                    `;
                                }
                            }, 300);
                        }, 300);
                    }
                } else {
                    CMMS.showToast(data.message, 'error');
                }
            },
            error: () => {
                CMMS.hideLoading();
                CMMS.showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
            }
        });
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('itemsTable')) {
        ItemMMB.init();
    }
});
