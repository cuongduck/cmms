/**
 * Maintenance Module JavaScript
 * Chứa tất cả logic cho module quản lý bảo trì
 */

var MaintenanceModule = (function() {
    'use strict';
    
    // Private variables
    let currentPage = 1;
    let totalPages = 1;
    let materialOptions = [];
    
    // Private methods
    function loadEquipmentOptions() {
        return CMMS.ajax('../equipment/api.php', {
            method: 'GET',
            data: { action: 'list_simple' }
        }).then(response => {
            if (response.success) {
                let options = '<option value="">Tất cả thiết bị</option>';
                response.data.forEach(item => {
                    options += `<option value="${item.id}">${item.id_thiet_bi} - ${item.ten_thiet_bi}</option>`;
                });
                $('#filter_equipment').html(options);
                return response.data;
            } else {
                throw new Error('Failed to load equipment options');
            }
        });
    }
    
    function loadMaterialOptions() {
        return CMMS.ajax('../bom/api.php', {
            method: 'GET',
            data: { action: 'get_vat_tu' }
        }).then(response => {
            if (response.success) {
                materialOptions = response.data;
                updateMaterialSelects();
                return response.data;
            } else {
                throw new Error('Failed to load material options');
            }
        });
    }
    
    function updateMaterialSelects() {
        let options = '<option value="">Chọn vật tư</option>';
        materialOptions.forEach(item => {
            options += `<option value="${item.id}" data-gia="${item.gia}">${item.ma_item} - ${item.ten_vat_tu}</option>`;
        });
        $('select[name*="[id_vat_tu]"]').html(options);
    }
    
    function loadStatistics() {
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'statistics' }
        }).then(response => {
            if (response.success) {
                const stats = response.data;
                $('#overdue-count').text(stats.overdue || 0);
                $('#upcoming-count').text(stats.upcoming || 0);
                $('#progress-count').text(stats.in_progress || 0);
                $('#completed-count').text(stats.completed_this_month || 0);
                
                updateStatisticCards(stats);
                return stats;
            } else {
                throw new Error('Failed to load statistics');
            }
        });
    }
    
    function updateStatisticCards(stats) {
        // Animate counters
        $('.card h5').each(function() {
            const target = parseInt($(this).text());
            if (target > 0) {
                $(this).closest('.card').addClass('shadow-sm');
            }
        });
    }
    
    function loadMaintenanceData() {
        const formData = new FormData(document.getElementById('filterForm'));
        formData.append('action', 'list');
        formData.append('page', currentPage);
        
        CMMS.showLoading('#maintenanceTableBody');
        
        return CMMS.ajax('api.php', {
            method: 'POST',
            data: formData
        }).then(response => {
            CMMS.hideLoading('#maintenanceTableBody');
            
            if (response.success) {
                displayMaintenanceData(response.data);
                CMMS.displayPagination(response.pagination, 'pagination', 'MaintenanceModule.changePage');
                totalPages = response.pagination.total_pages;
                currentPage = response.pagination.current_page;
                return response.data;
            } else {
                $('#maintenanceTableBody').html('<tr><td colspan="10" class="text-center">Không có dữ liệu</td></tr>');
                throw new Error('Failed to load maintenance data');
            }
        }).catch(error => {
            CMMS.hideLoading('#maintenanceTableBody');
            $('#maintenanceTableBody').html('<tr><td colspan="10" class="text-center">Lỗi tải dữ liệu</td></tr>');
            throw error;
        });
    }
    
    function displayMaintenanceData(data) {
        let html = '';
        
        data.forEach(item => {
            const daysRemaining = CMSSHelpers.getDaysRemaining(item.ngay_bao_tri_tiep_theo);
            const isOverdue = daysRemaining < 0 && !['hoan_thanh', 'huy'].includes(item.trang_thai);
            
            html += `
                <tr class="${isOverdue ? 'table-warning' : ''}">
                    <td>
                        <input type="checkbox" class="form-check-input maintenance-checkbox" value="${item.id}">
                    </td>
                    <td>
                        <div>
                            <strong>${item.id_thiet_bi}</strong><br>
                            <small class="text-muted">${item.ten_thiet_bi}</small>
                        </div>
                    </td>
                    <td>
                        <div>
                            <strong>${item.ten_ke_hoach}</strong>
                            ${isOverdue ? '<span class="badge bg-danger ms-2">Quá hạn</span>' : ''}
                            <br>
                            <small class="text-muted">${item.mo_ta ? item.mo_ta.substring(0, 50) + '...' : ''}</small>
                        </div>
                    </td>
                    <td>
                        ${getTypeText(item.loai_bao_tri)}
                    </td>
                    <td>
                        ${item.chu_ky_ngay ? item.chu_ky_ngay + ' ngày' : '<em>Không định kỳ</em>'}
                    </td>
                    <td>
                        <div>
                            <strong>${CMMS.formatDate(item.ngay_bao_tri_tiep_theo)}</strong><br>
                            ${getDateStatus(daysRemaining)}
                        </div>
                    </td>
                    <td>
                        ${CMSSHelpers.getStatusBadge(item.trang_thai, 'maintenance')}
                    </td>
                    <td>
                        ${CMSSHelpers.getPriorityBadge(item.uu_tien)}
                    </td>
                    <td>
                        <small>${item.nguoi_thuc_hien_name || 'Chưa phân công'}</small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" onclick="MaintenanceModule.viewDetail(${item.id})" title="Xem chi tiết">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${canExecuteMaintenance(item) ? `
                            <button class="btn btn-outline-success" onclick="MaintenanceModule.executeMaintenance(${item.id})" title="Thực hiện">
                                <i class="fas fa-play"></i>
                            </button>
                            ` : ''}
                            ${CMSSHelpers.hasPermission(['admin', 'to_truong']) && item.trang_thai === 'chua_thuc_hien' ? `
                            <button class="btn btn-outline-warning" onclick="MaintenanceModule.editMaintenance(${item.id})" title="Sửa">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="MaintenanceModule.deleteMaintenance(${item.id})" title="Xóa">
                                <i class="fas fa-trash"></i>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        
        if (html === '') {
            html = '<tr><td colspan="10" class="text-center text-muted">Không có dữ liệu bảo trì</td></tr>';
        }
        
        $('#maintenanceTableBody').html(html);
    }
    
    function getTypeText(type) {
        const types = {
            'dinh_ky': '<span class="badge bg-primary">Định kỳ</span>',
            'du_phong': '<span class="badge bg-info">Dự phòng</span>',
            'sua_chua': '<span class="badge bg-warning">Sửa chữa</span>',
            'cap_cuu': '<span class="badge bg-danger">Cấp cứu</span>'
        };
        return types[type] || '<span class="badge bg-secondary">Không xác định</span>';
    }
    
    function getDateStatus(daysRemaining) {
        if (daysRemaining === null) return '';
        
        if (daysRemaining < 0) {
            return `<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Quá hạn ${Math.abs(daysRemaining)} ngày</small>`;
        } else if (daysRemaining <= 3) {
            return `<small class="text-danger"><i class="fas fa-clock me-1"></i>Còn ${daysRemaining} ngày</small>`;
        } else if (daysRemaining <= 7) {
            return `<small class="text-warning"><i class="fas fa-clock me-1"></i>Còn ${daysRemaining} ngày</small>`;
        } else {
            return `<small class="text-muted">Còn ${daysRemaining} ngày</small>`;
        }
    }
    
    function canExecuteMaintenance(item) {
        return CMSSHelpers.hasPermission(['admin', 'to_truong', 'user']) && 
               ['chua_thuc_hien', 'dang_thuc_hien'].includes(item.trang_thai);
    }
    
    function generateMaintenanceDetailHTML(data) {
        const daysRemaining = CMSSHelpers.getDaysRemaining(data.ngay_bao_tri_tiep_theo);
        
        return `
            <div class="row">
                <div class="col-md-8">
                    <h5 class="mb-3">${data.ten_ke_hoach}</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><th width="40%">Thiết bị:</th><td><strong>${data.id_thiet_bi} - ${data.ten_thiet_bi}</strong></td></tr>
                                <tr><th>Vị trí:</th><td>${data.ten_xuong} - ${data.ten_line}</td></tr>
                                <tr><th>Loại bảo trì:</th><td>${getTypeText(data.loai_bao_tri)}</td></tr>
                                <tr><th>Chu kỳ:</th><td>${data.chu_ky_ngay ? data.chu_ky_ngay + ' ngày' : 'Không định kỳ'}</td></tr>
                                <tr><th>Lần cuối:</th><td>${CMMS.formatDate(data.lan_bao_tri_cuoi) || 'Chưa thực hiện'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><th width="40%">Ngày tiếp theo:</th><td><strong>${CMMS.formatDate(data.ngay_bao_tri_tiep_theo)}</strong></td></tr>
                                <tr><th>Trạng thái:</th><td>${CMSSHelpers.getStatusBadge(data.trang_thai, 'maintenance')}</td></tr>
                                <tr><th>Ưu tiên:</th><td>${CMSSHelpers.getPriorityBadge(data.uu_tien)}</td></tr>
                                <tr><th>Người thực hiện:</th><td>${data.nguoi_thuc_hien_name || 'Chưa phân công'}</td></tr>
                                <tr><th>Người tạo:</th><td>${data.created_by_name}</td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Mô tả:</h6>
                        <p class="text-muted">${data.mo_ta || 'Không có mô tả'}</p>
                    </div>
                    
                    ${data.maintenance_history && data.maintenance_history.length > 0 ? `
                    <div class="mt-4">
                        <h6>Lịch sử bảo trì:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Ngày</th>
                                        <th>Công việc</th>
                                        <th>Người thực hiện</th>
                                        <th>Chi phí</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.maintenance_history.map(history => `
                                        <tr>
                                            <td>${CMMS.formatDate(history.ngay_thuc_hien)}</td>
                                            <td>${history.ten_cong_viec}</td>
                                            <td>${history.nguoi_thuc_hien_name}</td>
                                            <td>${CMMS.formatCurrency(history.chi_phi)}</td>
                                            <td><span class="badge ${history.trang_thai === 'hoan_thanh' ? 'bg-success' : 'bg-warning'}">${history.trang_thai === 'hoan_thanh' ? 'Hoàn thành' : 'Chưa hoàn thành'}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Thông tin thời gian</h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                ${getDateStatus(daysRemaining)}
                            </div>
                            
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar ${daysRemaining < 0 ? 'bg-danger' : (daysRemaining <= 7 ? 'bg-warning' : 'bg-success')}" 
                                     style="width: ${Math.max(0, Math.min(100, (30 - Math.abs(daysRemaining)) / 30 * 100))}%"></div>
                            </div>
                            
                            <small class="text-muted">
                                Ngày tạo: ${CMMS.formatDate(data.created_at)}<br>
                                Cập nhật: ${CMMS.formatDate(data.updated_at)}
                            </small>
                        </div>
                    </div>
                    
                    ${data.bom_items && data.bom_items.length > 0 ? `
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Vật tư cần thiết</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                ${data.bom_items.slice(0, 5).map(item => `
                                    <div class="list-group-item p-2">
                                        <div class="d-flex justify-content-between">
                                            <small><strong>${item.ma_item}</strong></small>
                                            <small>${item.so_luong} ${item.dvt}</small>
                                        </div>
                                        <small class="text-muted">${item.ten_vat_tu}</small>
                                    </div>
                                `).join('')}
                                ${data.bom_items.length > 5 ? `<small class="text-muted text-center">Và ${data.bom_items.length - 5} vật tư khác...</small>` : ''}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    function generateActionButtons(data) {
        let buttons = '';
        
        if (canExecuteMaintenance(data)) {
            buttons += `
                <button type="button" class="btn btn-success me-2" onclick="MaintenanceModule.executeMaintenance(${data.id})">
                    <i class="fas fa-play me-2"></i>Thực hiện bảo trì
                </button>
            `;
        }
        
        if (CMSSHelpers.hasPermission(['admin', 'to_truong']) && data.trang_thai === 'chua_thuc_hien') {
            buttons += `
                <button type="button" class="btn btn-warning me-2" onclick="MaintenanceModule.editMaintenance(${data.id})">
                    <i class="fas fa-edit me-2"></i>Sửa kế hoạch
                </button>
            `;
        }
        
        if (data.trang_thai === 'hoan_thanh') {
            buttons += `
                <a href="history.php?maintenance_id=${data.id}" class="btn btn-info me-2" target="_blank">
                    <i class="fas fa-history me-2"></i>Xem lịch sử
                </a>
            `;
        }
        
        return buttons;
    }
    
    // Public methods
    return {
        // Initialize module
        init: function() {
            console.log('Maintenance module initializing...');
            
            this.loadEquipmentOptions();
            this.loadMaterialOptions();
            this.loadData();
            this.loadStatistics();
            
            this.bindEvents();
        },
        
        // Bind events
        bindEvents: function() {
            // Form submission
            $('#filterForm').on('submit', (e) => {
                e.preventDefault();
                currentPage = 1;
                this.loadData();
            });
            
            // Select all checkbox
            $('#selectAll').change(function() {
                $('.maintenance-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Auto-refresh every 5 minutes
            setInterval(() => {
                if (document.visibilityState === 'visible') {
                    this.loadStatistics();
                }
            }, 300000);
        },
        
        // Load equipment options
        loadEquipmentOptions: loadEquipmentOptions,
        
        // Load material options
        loadMaterialOptions: loadMaterialOptions,
        
        // Load maintenance data
        loadData: loadMaintenanceData,
        
        // Load statistics
        loadStatistics: loadStatistics,
        
        // Change page
        changePage: function(page) {
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                this.loadData();
            }
        },
        
        // Reset filter
        resetFilter: function() {
            document.getElementById('filterForm').reset();
            currentPage = 1;
            this.loadData();
        },
        
        // Quick filter
        quickFilter: function(type) {
            const today = new Date().toISOString().split('T')[0];
            
            document.getElementById('filterForm').reset();
            
            switch (type) {
                case 'overdue':
                    $('#filter_trang_thai').val('qua_han');
                    break;
                case 'upcoming':
                    $('#filter_search').val('upcoming_7_days');
                    break;
                case 'high_priority':
                    $('#filter_uu_tien').val('khan_cap');
                    break;
                case 'this_week':
                    $('#filter_search').val('this_week');
                    break;
            }
            
            currentPage = 1;
            this.loadData();
        },
        
        // View maintenance detail
        viewDetail: function(id) {
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'detail', id: id }
            }).then(response => {
                if (response.success) {
                    $('#maintenanceDetailContent').html(generateMaintenanceDetailHTML(response.data));
                    $('#maintenanceDetailModal').modal('show');
                    
                    // Add action buttons
                    const actions = generateActionButtons(response.data);
                    $('#maintenanceActions').html(actions);
                } else {
                    CMMS.showAlert('Không thể tải chi tiết kế hoạch bảo trì', 'error');
                }
            }).catch(error => {
                console.error('View detail error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Execute maintenance
        executeMaintenance: function(id) {
            // Load maintenance data first
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'detail', id: id }
            }).then(response => {
                if (response.success) {
                    const data = response.data;
                    $('#maintenance_id').val(id);
                    $('input[name="ten_cong_viec"]').val(data.ten_ke_hoach);
                    $('textarea[name="mo_ta"]').val(data.mo_ta);
                    
                    // Pre-fill first material if available
                    if (data.bom_items && data.bom_items.length > 0) {
                        const firstMaterial = data.bom_items[0];
                        $('select[name="materials[0][id_vat_tu]"]').val(firstMaterial.id_vat_tu);
                        $('input[name="materials[0][so_luong]"]').val(firstMaterial.so_luong);
                        $('input[name="materials[0][don_gia]"]').val(firstMaterial.gia);
                    }
                    
                    $('#executeMaintenanceModal').modal('show');
                }
            }).catch(error => {
                console.error('Execute maintenance error:', error);
                CMMS.showAlert('Lỗi tải thông tin bảo trì', 'error');
            });
        },
        
        // Add material row
        addMaterialRow: function() {
            const index = $('.material-row').length;
            const newRow = `
                <div class="row material-row mt-2">
                    <div class="col-md-6">
                        <select class="form-select select2" name="materials[${index}][id_vat_tu]">
                            <option value="">Chọn vật tư</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control" name="materials[${index}][so_luong]" placeholder="Số lượng" step="0.001">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="materials[${index}][don_gia]" placeholder="Đơn giá">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="MaintenanceModule.removeMaterialRow(this)">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
            `;
            
            $('#materialUsage').append(newRow);
            updateMaterialSelects();
        },
        
        // Remove material row
        removeMaterialRow: function(button) {
            $(button).closest('.material-row').remove();
        },
        
        // Save maintenance execution
        saveMaintenanceExecution: function() {
            const formData = new FormData(document.getElementById('executeForm'));
            formData.append('action', 'execute');
            
            CMMS.ajax('api.php', {
                data: formData
            }).then(response => {
                if (response.success) {
                    CMMS.showAlert('Lưu kết quả bảo trì thành công!', 'success');
                    $('#executeMaintenanceModal').modal('hide');
                    this.loadData();
                    this.loadStatistics();
                } else {
                    CMMS.showAlert(response.message, 'error');
                }
            }).catch(error => {
                console.error('Save execution error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Edit maintenance
        editMaintenance: function(id) {
            window.location.href = `edit.php?id=${id}`;
        },
        
        // Delete maintenance
        deleteMaintenance: function(id) {
            CMMS.confirm('Bạn có chắc chắn muốn xóa kế hoạch bảo trì này?', 'Xác nhận xóa').then((result) => {
                if (result.isConfirmed) {
                    CMMS.ajax('api.php', {
                        data: { action: 'delete', id: id }
                    }).then(response => {
                        if (response.success) {
                            CMMS.showAlert('Xóa kế hoạch bảo trì thành công', 'success');
                            this.loadData();
                            this.loadStatistics();
                        } else {
                            CMMS.showAlert(response.message, 'error');
                        }
                    }).catch(error => {
                        console.error('Delete maintenance error:', error);
                        CMMS.showAlert('Lỗi kết nối', 'error');
                    });
                }
            });
        },
        
        // Export maintenance
        exportMaintenance: function() {
            const formData = new FormData(document.getElementById('filterForm'));
            CMSSHelpers.exportAsCSV(null, 'ke_hoach_bao_tri.csv', formData);
        },
        
        // Print calendar
        printCalendar: function() {
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'calendar_data' }
            }).then(response => {
                if (response.success) {
                    this.generateCalendarHTML(response.data);
                    $('#calendarModal').modal('show');
                } else {
                    CMMS.showAlert('Không thể tải dữ liệu lịch', 'error');
                }
            }).catch(error => {
                console.error('Print calendar error:', error);
                CMMS.showAlert('Lỗi tải lịch bảo trì', 'error');
            });
        },
        
        // Generate calendar HTML
        generateCalendarHTML: function(data) {
            const today = new Date();
            const currentMonth = today.getMonth();
            const currentYear = today.getFullYear();
            
            // Simple calendar implementation
            let calendarHTML = `
                <div class="calendar-header text-center mb-3">
                    <h4>Lịch bảo trì - Tháng ${currentMonth + 1}/${currentYear}</h4>
                </div>
                <div class="calendar-grid">
                    <div class="row">
                        <div class="col text-center fw-bold">CN</div>
                        <div class="col text-center fw-bold">T2</div>
                        <div class="col text-center fw-bold">T3</div>
                        <div class="col text-center fw-bold">T4</div>
                        <div class="col text-center fw-bold">T5</div>
                        <div class="col text-center fw-bold">T6</div>
                        <div class="col text-center fw-bold">T7</div>
                    </div>
            `;
            
            // Generate calendar days with maintenance data
            const firstDay = new Date(currentYear, currentMonth, 1);
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            const startingDayOfWeek = firstDay.getDay();
            
            let dayCount = 1;
            
            for (let week = 0; week < 6; week++) {
                calendarHTML += '<div class="row border-top">';
                
                for (let day = 0; day < 7; day++) {
                    let cellContent = '';
                    let cellClass = 'col border-end p-2 calendar-cell';
                    
                    if (week === 0 && day < startingDayOfWeek) {
                        cellContent = '';
                    } else if (dayCount <= lastDay.getDate()) {
                        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(dayCount).padStart(2, '0')}`;
                        const dayMaintenance = data.filter(item => item.ngay_bao_tri_tiep_theo === dateStr);
                        
                        cellContent = `<div class="fw-bold">${dayCount}</div>`;
                        
                        if (dayMaintenance.length > 0) {
                            cellClass += ' bg-light';
                            dayMaintenance.forEach(item => {
                                const priority = item.uu_tien === 'khan_cap' ? 'text-danger' : 
                                               item.uu_tien === 'cao' ? 'text-warning' : 'text-info';
                                cellContent += `<div class="maintenance-item ${priority}" style="font-size: 10px;">${item.id_thiet_bi}</div>`;
                            });
                        }
                        
                        dayCount++;
                    }
                    
                    calendarHTML += `<div class="${cellClass}" style="min-height: 80px;">${cellContent}</div>`;
                }
                
                calendarHTML += '</div>';
                
                if (dayCount > lastDay.getDate()) break;
            }
            
            calendarHTML += '</div>';
            
            $('#maintenanceCalendar').html(calendarHTML);
        }
    };
})();

// Auto-initialize when DOM is ready
$(document).ready(function() {
    // Check if we're on maintenance page before initializing
    if ($('#maintenanceTable').length || $('#maintenanceTableBody').length) {
        MaintenanceModule.init();
    }
});

// Make module globally accessible
window.MaintenanceModule = MaintenanceModule;