/**
 * Equipment Module JavaScript - Updated Version
 * Chứa tất cả logic cho module quản lý thiết bị (index page)
 * File: assets/js/modules/equipment.js
 */

var EquipmentModule = (function() {
    'use strict';
    
    // Private variables
    let currentPage = 1;
    let totalPages = 1;
    let isLoading = false;
    
    // Private methods
    function loadXuongOptions() {
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_xuong' }
        }).then(response => {
            if (response && response.success) {
                let options = '<option value="">Tất cả xưởng</option>';
                response.data.forEach(item => {
                    options += `<option value="${item.id}">${item.ten_xuong}</option>`;
                });
                $('#filter_xuong').html(options);
                return response.data;
            } else {
                console.error('Load xuong failed:', response);
                throw new Error('Failed to load xuong options');
            }
        });
    }
    
    function loadLineOptions(xuongId) {
        const lineSelect = $('#filter_line');
        
        if (!xuongId) {
            lineSelect.prop('disabled', true).html('<option value="">Tất cả line</option>');
            return Promise.resolve([]);
        }
        
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_lines', xuong_id: xuongId }
        }).then(response => {
            if (response && response.success) {
                let options = '<option value="">Tất cả line</option>';
                response.data.forEach(item => {
                    options += `<option value="${item.id}">${item.ten_line}</option>`;
                });
                lineSelect.prop('disabled', false).html(options);
                return response.data;
            } else {
                console.error('Load lines failed:', response);
                throw new Error('Failed to load line options');
            }
        });
    }
    
    function loadEquipmentData() {
        if (isLoading) return Promise.resolve([]);
        isLoading = true;
        
        const formData = new FormData(document.getElementById('filterForm'));
        formData.append('action', 'list');
        formData.append('page', currentPage);
        
        console.log('Loading equipment data, page:', currentPage);
        CMMS.showLoading('#equipmentTableBody');
        
        return CMMS.ajax('api.php', {
            method: 'POST',
            data: formData
        }).then(response => {
            console.log('Equipment response:', response);
            CMMS.hideLoading('#equipmentTableBody');
            isLoading = false;
            
            if (response && response.success) {
                displayEquipmentData(response.data);
                CMMS.displayPagination(response.pagination, 'pagination', 'EquipmentModule.changePage');
                totalPages = response.pagination.total_pages;
                currentPage = response.pagination.current_page;
                return response.data;
            } else {
                console.error('Equipment error:', response);
                $('#equipmentTableBody').html('<tr><td colspan="7" class="text-center text-danger">Lỗi tải dữ liệu: ' + (response ? response.message : 'Không có response') + '</td></tr>');
                throw new Error('Failed to load equipment data');
            }
        }).catch(error => {
            console.error('Equipment AJAX error:', error);
            CMMS.hideLoading('#equipmentTableBody');
            isLoading = false;
            $('#equipmentTableBody').html('<tr><td colspan="7" class="text-center text-danger">Lỗi kết nối API</td></tr>');
            throw error;
        });
    }
    
    function displayEquipmentData(data) {
        if (!data || data.length === 0) {
            $('#equipmentTableBody').html('<tr><td colspan="7" class="text-center text-muted">Không có dữ liệu thiết bị</td></tr>');
            return;
        }
        
        let html = '';
        
        data.forEach(item => {
            html += `
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input equipment-checkbox" value="${item.id}">
                    </td>
                    <td>
                        <strong>${item.id_thiet_bi}</strong>
                    </td>
                    <td>
                        <div>
                            <strong>${item.ten_thiet_bi}</strong><br>
                            <small class="text-muted">${item.nganh || ''}</small>
                        </div>
                    </td>
                    <td>
                        <small>
                            ${item.ten_xuong || ''}<br>
                            ${item.ten_line || ''}<br>
                            <span class="text-muted">${item.vi_tri || ''}</span>
                        </small>
                    </td>
                    <td>
                        ${CMSSHelpers.getStatusBadge(item.tinh_trang, 'equipment')}
                    </td>
                    <td>
                        <small>${item.chu_quan_name || 'Chưa phân công'}</small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" onclick="EquipmentModule.viewDetail(${item.id})" title="Xem chi tiết">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="EquipmentModule.showQR('${item.id_thiet_bi}')" title="QR Code">
                                <i class="fas fa-qrcode"></i>
                            </button>
                            ${CMSSHelpers.hasPermission(['admin', 'to_truong']) ? `
                            <button class="btn btn-outline-warning" onclick="EquipmentModule.editEquipment(${item.id})" title="Sửa">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="EquipmentModule.deleteEquipment(${item.id})" title="Xóa">
                                <i class="fas fa-trash"></i>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
        
        $('#equipmentTableBody').html(html);
    }
    
    function generateDetailHTML(data) {
        return `
            <div class="row">
                <div class="col-md-8">
                    <table class="table table-borderless">
                        <tr><th width="30%">ID thiết bị:</th><td><strong>${data.id_thiet_bi}</strong></td></tr>
                        <tr><th>Tên thiết bị:</th><td>${data.ten_thiet_bi}</td></tr>
                        <tr><th>Vị trí:</th><td>${data.ten_xuong || ''} - ${data.ten_line || ''} - ${data.vi_tri || ''}</td></tr>
                        <tr><th>Ngành:</th><td>${data.nganh || ''}</td></tr>
                        <tr><th>Năm sản xuất:</th><td>${data.nam_san_xuat || ''}</td></tr>
                        <tr><th>Công suất:</th><td>${data.cong_suat || ''}</td></tr>
                        <tr><th>Tình trạng:</th><td>${CMSSHelpers.getStatusBadge(data.tinh_trang, 'equipment')}</td></tr>
                        <tr><th>Người chủ quản:</th><td>${data.chu_quan_name || 'Chưa phân công'}</td></tr>
                        <tr><th>Thông số kỹ thuật:</th><td>${data.thong_so_ky_thuat || ''}</td></tr>
                        <tr><th>Ghi chú:</th><td>${data.ghi_chu || ''}</td></tr>
                    </table>
                </div>
                <div class="col-md-4">
                    ${data.hinh_anh ? `<img src="${data.hinh_anh}" class="img-fluid rounded" alt="Hình ảnh thiết bị">` : '<div class="text-center text-muted"><i class="fas fa-image fa-3x"></i><br>Không có hình ảnh</div>'}
                    
                    <div class="mt-3 text-center">
                        <button class="btn btn-primary btn-sm" onclick="EquipmentModule.showQR('${data.id_thiet_bi}')">
                            <i class="fas fa-qrcode me-2"></i>Xem QR Code
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Public methods
    return {
        // Initialize module
        init: function() {
            console.log('Equipment module initializing...');
            
            this.loadXuongOptions();
            
            // Auto load equipment data after xuong options loaded
            setTimeout(() => {
                this.loadData();
            }, 1000);
            
            this.bindEvents();
        },
        
        // Bind events
        bindEvents: function() {
            // Filter form submit
            $('#filterForm').on('submit', (e) => {
                e.preventDefault();
                currentPage = 1;
                this.loadData();
            });
            
            // Xuong change event
            $('#filter_xuong').on('change', function() {
                const xuongId = $(this).val();
                loadLineOptions(xuongId);
            });
            
            // Select all checkbox
            $('#selectAll').on('change', function() {
                $('.equipment-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Auto-search on input
            $('#filter_search').on('input', debounce(() => {
                currentPage = 1;
                this.loadData();
            }, 500));
        },
        
        // Load xuong options
        loadXuongOptions: loadXuongOptions,
        
        // Load equipment data
        loadData: loadEquipmentData,
        
        // Change page
        changePage: function(page) {
            if (page >= 1 && page <= totalPages && !isLoading) {
                currentPage = page;
                this.loadData();
            }
        },
        
        // Reset filter
        resetFilter: function() {
            document.getElementById('filterForm').reset();
            $('#filter_line').prop('disabled', true).html('<option value="">Tất cả line</option>');
            currentPage = 1;
            this.loadData();
        },
        
        // View equipment detail
        viewDetail: function(id) {
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'detail', id: id }
            }).then(response => {
                if (response && response.success) {
                    $('#equipmentDetailContent').html(generateDetailHTML(response.data));
                    $('#equipmentDetailModal').modal('show');
                } else {
                    CMMS.showAlert('Không thể tải chi tiết thiết bị', 'error');
                }
            }).catch(error => {
                console.error('View detail error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Show QR code
        showQR: function(equipmentId) {
            const qrUrl = `https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=${encodeURIComponent(equipmentId)}`;
            
            $('#qrCodeContent').html(`
                <h6>Thiết bị: ${equipmentId}</h6>
                <img src="${qrUrl}" class="img-fluid" alt="QR Code">
                <p class="mt-2 text-muted">Quét mã này để xem thông tin thiết bị</p>
            `);
            
            $('#qrCodeModal').modal('show');
        },
        
        // Edit equipment
        editEquipment: function(id) {
            window.location.href = `edit.php?id=${id}`;
        },
        
        // Delete equipment
        deleteEquipment: function(id) {
            CMMS.confirm('Bạn có chắc chắn muốn xóa thiết bị này?', 'Xác nhận xóa').then((result) => {
                if (result.isConfirmed) {
                    CMMS.ajax('api.php', {
                        data: { action: 'delete', id: id }
                    }).then(response => {
                        if (response && response.success) {
                            CMMS.showAlert('Xóa thiết bị thành công', 'success');
                            this.loadData();
                        } else {
                            CMMS.showAlert(response ? response.message : 'Lỗi xóa thiết bị', 'error');
                        }
                    }).catch(error => {
                        console.error('Delete equipment error:', error);
                        CMMS.showAlert('Lỗi kết nối', 'error');
                    });
                }
            });
        },
        
        // Export data
        exportData: function() {
            const formData = new FormData(document.getElementById('filterForm'));
            CMSSHelpers.exportAsCSV(null, 'danh_sach_thiet_bi.csv', formData);
        },
        
        // Batch QR generation
        showQRBatch: function() {
            const selectedIds = $('.equipment-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                CMMS.showAlert('Vui lòng chọn ít nhất một thiết bị', 'warning');
                return;
            }
            
            // Implementation for batch QR generation
            CMMS.showAlert('Chức năng in QR hàng loạt đang được phát triển', 'info');
        },
        
        // Print QR
        printQR: function() {
            const printContent = document.getElementById('qrCodeContent').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    ${printContent}
                </div>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        },
        
        // Quick filter functions
        quickFilter: function(type) {
            document.getElementById('filterForm').reset();
            
            switch (type) {
                case 'active':
                    $('#filter_tinh_trang').val('hoat_dong');
                    break;
                case 'maintenance':
                    $('#filter_tinh_trang').val('bao_tri');
                    break;
                case 'broken':
                    $('#filter_tinh_trang').val('hong');
                    break;
                case 'inactive':
                    $('#filter_tinh_trang').val('ngung_hoat_dong');
                    break;
            }
            
            currentPage = 1;
            this.loadData();
        }
    };
})();

// Utility function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Auto-initialize when DOM is ready
$(document).ready(function() {
    // Check if we're on equipment index page before initializing
    if ($('#equipmentTable').length && window.location.pathname.includes('/equipment/index.php')) {
        EquipmentModule.init();
    }
});

// Make module globally accessible
window.EquipmentModule = EquipmentModule;