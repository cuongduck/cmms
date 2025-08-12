/**
 * BOM Module JavaScript
 * Chứa tất cả logic cho module quản lý BOM thiết bị
 */

var BOMModule = (function() {
    'use strict';
    
    // Private variables
    let currentPage = 1;
    let totalPages = 1;
    let editingBOMId = null;
    let equipmentOptions = [];
    let materialOptions = [];
    
    // Private methods
    function loadEquipmentOptions() {
        return CMMS.ajax('../equipment/api.php', {
            method: 'GET',
            data: { action: 'list_simple' }
        }).then(response => {
            if (response && response.success) {
                console.log('Equipment loaded:', response.data);
                equipmentOptions = response.data;
                
                let options = '<option value="">Tất cả thiết bị</option>';
                let modalOptions = '<option value="">Chọn thiết bị</option>';
                
                response.data.forEach(item => {
                    options += `<option value="${item.id}">${item.id_thiet_bi} - ${item.ten_thiet_bi}</option>`;
                    modalOptions += `<option value="${item.id}">${item.id_thiet_bi} - ${item.ten_thiet_bi}</option>`;
                });
                
                $('#filter_equipment').html(options);
                $('#equipment_id').html(modalOptions);
                
                // Update import modal select
                $('select[name="equipment_id"]').html(modalOptions);
                
                return response.data;
            } else {
                console.error('Equipment load failed:', response);
                CMMS.showAlert('Không thể tải danh sách thiết bị', 'error');
                throw new Error('Failed to load equipment options');
            }
        }).catch(error => {
            console.error('Equipment AJAX error:', error);
            CMMS.showAlert('Lỗi kết nối khi tải thiết bị', 'error');
            throw error;
        });
    }
    
    function loadDongMayOptions(equipmentId) {
        const dongMaySelect = $('#dong_may_id');
        
        if (!equipmentId) {
            dongMaySelect.html('<option value="">Chọn dòng máy</option>');
            return Promise.resolve([]);
        }
        
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_dong_may_by_equipment', equipment_id: equipmentId }
        }).then(response => {
            if (response && response.success) {
                let options = '<option value="">Chọn dòng máy</option>';
                response.data.forEach(item => {
                    options += `<option value="${item.id}">${item.ten_dong_may}</option>`;
                });
                dongMaySelect.html(options);
                return response.data;
            } else {
                console.error('Load dong may failed:', response);
                throw new Error('Failed to load dong may options');
            }
        }).catch(error => {
            console.error('Load dong may error:', error);
            throw error;
        });
    }
    
    function loadVatTuOptions() {
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_vat_tu' }
        }).then(response => {
            if (response && response.success) {
                materialOptions = response.data;
                let options = '<option value="">Chọn vật tư</option>';
                response.data.forEach(item => {
                    options += `<option value="${item.id}" data-gia="${item.gia}" data-dvt="${item.dvt}" data-chung-loai="${item.chung_loai}">
                        ${item.ma_item} - ${item.ten_vat_tu}
                    </option>`;
                });
                $('#vat_tu_id').html(options);
                return response.data;
            } else {
                console.error('Load vat tu failed:', response);
                throw new Error('Failed to load vat tu options');
            }
        }).catch(error => {
            console.error('Load vat tu error:', error);
            throw error;
        });
    }
    
    function showVatTuInfo(vatTuId) {
        if (!vatTuId) {
            $('#vatTuInfo').hide();
            return;
        }
        
        const selectedOption = $(`#vat_tu_id option[value="${vatTuId}"]`);
        const gia = selectedOption.data('gia');
        const dvt = selectedOption.data('dvt');
        const chungLoai = selectedOption.data('chung-loai');
        
        $('#vatTuDetails').html(`
            <div class="row">
                <div class="col-md-4"><strong>Đơn vị:</strong> ${dvt}</div>
                <div class="col-md-4"><strong>Đơn giá:</strong> ${CMMS.formatCurrency(gia)}</div>
                <div class="col-md-4"><strong>Chủng loại:</strong> ${getChungLoaiText(chungLoai)}</div>
            </div>
        `);
        
        $('#vatTuInfo').show();
    }
    
    function loadBOMData() {
        const formData = new FormData(document.getElementById('filterForm'));
        formData.append('action', 'list');
        formData.append('page', currentPage);
        
        console.log('Loading BOM data, page:', currentPage);
        CMMS.showLoading('#bomTableBody');
        
        return CMMS.ajax('api.php', {
            method: 'POST',
            data: formData
        }).then(response => {
            console.log('BOM response:', response);
            CMMS.hideLoading('#bomTableBody');
            
            if (response && response.success) {
                displayBOMData(response.data);
                CMMS.displayPagination(response.pagination, 'pagination', 'BOMModule.changePage');
                totalPages = response.pagination.total_pages;
                currentPage = response.pagination.current_page;
                return response.data;
            } else {
                console.error('BOM error:', response);
                $('#bomTableBody').html('<tr><td colspan="11" class="text-center text-danger">Lỗi tải dữ liệu: ' + (response ? response.message : 'Không có response') + '</td></tr>');
                throw new Error('Failed to load BOM data');
            }
        }).catch(error => {
            console.error('BOM AJAX error:', error);
            CMMS.hideLoading('#bomTableBody');
            $('#bomTableBody').html('<tr><td colspan="11" class="text-center text-danger">Lỗi kết nối API</td></tr>');
            throw error;
        });
    }
    
    function displayBOMData(data) {
        console.log('Displaying BOM data:', data);
        
        if (!data || data.length === 0) {
            $('#bomTableBody').html('<tr><td colspan="11" class="text-center text-muted">Không có dữ liệu BOM</td></tr>');
            return;
        }
        
        let html = '';
        let groupedData = {};
        
        // Group by equipment
        data.forEach(item => {
            const key = `${item.id_thiet_bi_actual || item.id_thiet_bi}-${item.ten_thiet_bi}`;
            if (!groupedData[key]) {
                groupedData[key] = {
                    equipment: item,
                    items: []
                };
            }
            groupedData[key].items.push(item);
        });
        
        // Display grouped data
        Object.keys(groupedData).forEach(equipmentKey => {
            const group = groupedData[equipmentKey];
            const equipment = group.equipment;
            const items = group.items;
            
            // Equipment header row
            html += `
                <tr class="table-primary">
                    <td colspan="11">
                        <strong><i class="fas fa-cogs me-2"></i>${equipment.id_thiet_bi} - ${equipment.ten_thiet_bi}</strong>
                        <span class="float-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="BOMModule.viewEquipmentBOM(${equipment.id_thiet_bi_actual || equipment.id_thiet_bi})">
                                <i class="fas fa-eye me-1"></i>Xem chi tiết
                            </button>
                        </span>
                    </td>
                </tr>
            `;
            
            // BOM items
            items.forEach(item => {
                const thanhTien = (item.so_luong * item.gia) || 0;
                
                html += `
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input bom-checkbox" value="${item.id}">
                        </td>
                        <td></td>
                        <td>${item.ten_dong_may || '<em>Chung</em>'}</td>
                        <td><code>${item.ma_item}</code></td>
                        <td>${item.ten_vat_tu}</td>
                        <td class="text-end">${CMMS.formatNumber(item.so_luong)}</td>
                        <td>${item.dvt}</td>
                        <td class="text-end">${CMMS.formatCurrency(item.gia)}</td>
                        <td class="text-end"><strong>${CMMS.formatCurrency(thanhTien)}</strong></td>
                        <td><span class="badge ${getChungLoaiBadge(item.chung_loai)}">${getChungLoaiText(item.chung_loai)}</span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                ${CMSSHelpers.hasPermission(['admin', 'to_truong']) ? `
                                <button class="btn btn-outline-warning" onclick="BOMModule.editBOM(${item.id})" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="BOMModule.deleteBOM(${item.id})" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });
        });
        
        $('#bomTableBody').html(html);
    }
    
    function getChungLoaiText(chungLoai) {
        const texts = {
            'linh_kien': 'Linh kiện',
            'vat_tu': 'Vật tư',
            'cong_cu': 'Công cụ',
            'hoa_chat': 'Hóa chất',
            'khac': 'Khác'
        };
        return texts[chungLoai] || 'Không xác định';
    }
    
    function getChungLoaiBadge(chungLoai) {
        const badges = {
            'linh_kien': 'bg-primary',
            'vat_tu': 'bg-success',
            'cong_cu': 'bg-warning',
            'hoa_chat': 'bg-danger',
            'khac': 'bg-secondary'
        };
        return badges[chungLoai] || 'bg-secondary';
    }
    
    function generateBOMDetailHTML(data) {
        if (!data.bom_items || data.bom_items.length === 0) {
            return '<div class="text-center text-muted py-4"><i class="fas fa-list-alt fa-3x mb-3"></i><p>Thiết bị này chưa có BOM</p></div>';
        }
        
        let totalValue = 0;
        let html = `
            <div class="row mb-4">
                <div class="col-md-8">
                    <h5>${data.equipment.id_thiet_bi} - ${data.equipment.ten_thiet_bi}</h5>
                    <p class="text-muted">
                        ${data.equipment.ten_xuong || ''} - ${data.equipment.ten_line || ''}<br>
                        ${data.equipment.vi_tri || ''}
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <small class="text-muted">Ngày xuất: ${new Date().toLocaleDateString('vi-VN')}</small>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>STT</th>
                            <th>Mã vật tư</th>
                            <th>Tên vật tư</th>
                            <th>Dòng máy</th>
                            <th>Số lượng</th>
                            <th>ĐVT</th>
                            <th>Đơn giá</th>
                            <th>Thành tiền</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.bom_items.forEach((item, index) => {
            const thanhTien = (item.so_luong * item.gia) || 0;
            totalValue += thanhTien;
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><code>${item.ma_item}</code></td>
                    <td>${item.ten_vat_tu}</td>
                    <td>${item.ten_dong_may || '<em>Chung</em>'}</td>
                    <td class="text-end">${CMMS.formatNumber(item.so_luong)}</td>
                    <td>${item.dvt}</td>
                    <td class="text-end">${CMMS.formatCurrency(item.gia)}</td>
                    <td class="text-end">${CMMS.formatCurrency(thanhTien)}</td>
                    <td>${item.ghi_chu || ''}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="7" class="text-end">Tổng giá trị BOM:</th>
                            <th class="text-end">${CMMS.formatCurrency(totalValue)}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <h6>Thống kê theo chủng loại:</h6>
                    <div id="bomStats"></div>
                </div>
                <div class="col-md-6">
                    <h6>Ghi chú:</h6>
                    <ul class="list-unstyled">
                        <li><small><i class="fas fa-info-circle text-info me-2"></i>BOM này bao gồm tất cả vật tư cần thiết cho thiết bị</small></li>
                        <li><small><i class="fas fa-exclamation-triangle text-warning me-2"></i>Giá có thể thay đổi theo thời gian</small></li>
                        <li><small><i class="fas fa-check text-success me-2"></i>Cập nhật lần cuối: ${new Date().toLocaleDateString('vi-VN')}</small></li>
                    </ul>
                </div>
            </div>
        `;
        
        // Calculate stats
        setTimeout(() => {
            const stats = {};
            data.bom_items.forEach(item => {
                const chungLoai = item.chung_loai;
                if (!stats[chungLoai]) {
                    stats[chungLoai] = { count: 0, value: 0 };
                }
                stats[chungLoai].count++;
                stats[chungLoai].value += (item.so_luong * item.gia) || 0;
            });
            
            let statsHtml = '<div class="row">';
            Object.keys(stats).forEach(key => {
                const stat = stats[key];
                statsHtml += `
                    <div class="col-6 mb-2">
                        <div class="card card-body p-2">
                            <small class="text-muted">${getChungLoaiText(key)}</small><br>
                            <strong>${stat.count} items - ${CMMS.formatCurrency(stat.value)}</strong>
                        </div>
                    </div>
                `;
            });
            statsHtml += '</div>';
            
            $('#bomStats').html(statsHtml);
        }, 100);
        
        return html;
    }
    
    // Public methods
    return {
        // Initialize module
        init: function() {
            console.log('BOM module initializing...');
            
            this.loadEquipmentOptions();
            this.loadVatTuOptions();
            
            // Auto load BOM data
            setTimeout(() => {
                this.loadData();
            }, 1000);
            
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
            
            // Equipment change event for modal
            $('#equipment_id').on('change', function() {
                const equipmentId = $(this).val();
                loadDongMayOptions(equipmentId);
            });
            
            // Vat tu change event
            $('#vat_tu_id').on('change', function() {
                showVatTuInfo($(this).val());
            });
            
            // Select all checkbox
            $('#selectAll').on('change', function() {
                $('.bom-checkbox').prop('checked', $(this).prop('checked'));
            });
        },
        
        // Load equipment options
        loadEquipmentOptions: loadEquipmentOptions,
        
        // Load vat tu options
        loadVatTuOptions: loadVatTuOptions,
        
        // Load BOM data
        loadData: loadBOMData,
        
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
        
        // Add BOM item
        addBOMItem: function() {
            editingBOMId = null;
            $('#bomModalTitle').text('Thêm vật tư vào BOM');
            $('#bomForm')[0].reset();
            $('#vatTuInfo').hide();
            $('#bomModal').modal('show');
        },
        
        // Edit BOM
        editBOM: function(id) {
            editingBOMId = id;
            $('#bomModalTitle').text('Sửa vật tư trong BOM');
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'detail', id: id }
            }).then(response => {
                if (response && response.success) {
                    const data = response.data;
                    $('#bom_id').val(data.id);
                    $('#equipment_id').val(data.id_thiet_bi).trigger('change');
                    
                    // Load dong may options first, then set value
                    setTimeout(() => {
                        $('#dong_may_id').val(data.id_dong_may);
                    }, 500);
                    
                    $('#vat_tu_id').val(data.id_vat_tu).trigger('change');
                    $('#so_luong').val(data.so_luong);
                    $('#ghi_chu').val(data.ghi_chu);
                    
                    $('#bomModal').modal('show');
                } else {
                    CMMS.showAlert('Không thể tải thông tin BOM', 'error');
                }
            }).catch(error => {
                console.error('Edit BOM error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Save BOM
        saveBOM: function() {
            const formData = new FormData(document.getElementById('bomForm'));
            formData.append('action', editingBOMId ? 'update' : 'create');
            
            CMMS.ajax('api.php', {
                data: formData
            }).then(response => {
                if (response && response.success) {
                    CMMS.showAlert(response.message, 'success');
                    $('#bomModal').modal('hide');
                    this.loadData();
                } else {
                    CMMS.showAlert(response ? response.message : 'Lỗi lưu BOM', 'error');
                }
            }).catch(error => {
                console.error('Save BOM error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Delete BOM
        deleteBOM: function(id) {
            CMMS.confirm('Bạn có chắc chắn muốn xóa vật tư này khỏi BOM?', 'Xác nhận xóa').then((result) => {
                if (result.isConfirmed) {
                    CMMS.ajax('api.php', {
                        data: { action: 'delete', id: id }
                    }).then(response => {
                        if (response && response.success) {
                            CMMS.showAlert('Xóa vật tư thành công', 'success');
                            this.loadData();
                        } else {
                            CMMS.showAlert(response ? response.message : 'Lỗi xóa BOM', 'error');
                        }
                    }).catch(error => {
                        console.error('Delete BOM error:', error);
                        CMMS.showAlert('Lỗi kết nối', 'error');
                    });
                }
            });
        },
        
        // View equipment BOM detail
        viewEquipmentBOM: function(equipmentId) {
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'equipment_bom_detail', equipment_id: equipmentId }
            }).then(response => {
                if (response && response.success) {
                    $('#bomDetailContent').html(generateBOMDetailHTML(response.data));
                    $('#bomDetailModal').modal('show');
                } else {
                    CMMS.showAlert('Không thể tải chi tiết BOM', 'error');
                }
            }).catch(error => {
                console.error('View BOM detail error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Import BOM
        importBOM: function() {
            this.loadEquipmentOptions();
            $('#importBOMModal').modal('show');
        },
        
        // Process import
        processImport: function() {
            const formData = new FormData(document.getElementById('importForm'));
            formData.append('action', 'import');
            
            CMMS.ajax('api.php', {
                data: formData
            }).then(response => {
                if (response && response.success) {
                    let message = `Import thành công! ${response.data.imported_count} dòng đã được thêm.`;
                    if (response.warnings && response.warnings.length > 0) {
                        message += '\n\nCảnh báo:\n' + response.warnings.join('\n');
                    }
                    CMMS.showAlert(message, 'success');
                    $('#importBOMModal').modal('hide');
                    this.loadData();
                } else {
                    CMMS.showAlert(response ? response.message : 'Lỗi import BOM', 'error');
                }
            }).catch(error => {
                console.error('Import BOM error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Export BOM
        exportBOM: function() {
            const formData = new FormData(document.getElementById('filterForm'));
            CMSSHelpers.exportAsCSV(null, 'bom_thiet_bi.csv', formData);
        },
        
        // Print BOM
        printBOM: function() {
            const selectedIds = $('.bom-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                CMMS.showAlert('Vui lòng chọn ít nhất một item để in', 'warning');
                return;
            }
            
            const printUrl = `print.php?ids=${selectedIds.join(',')}`;
            window.open(printUrl, '_blank');
        },
        
        // Print detail BOM
        printDetailBOM: function() {
            const printContent = document.getElementById('bomDetailContent').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div style="padding: 20px;">
                    <style>
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .text-end { text-align: right; }
                        .text-center { text-align: center; }
                        @media print {
                            .btn { display: none; }
                        }
                    </style>
                    ${printContent}
                </div>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        }
    };
})();

// Auto-initialize when DOM is ready
$(document).ready(function() {
    // Check if we're on BOM page before initializing
    if ($('#bomTable').length || $('#bomTableBody').length) {
        BOMModule.init();
    }
});

// Make module globally accessible
window.BOMModule = BOMModule;