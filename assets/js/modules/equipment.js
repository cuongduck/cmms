/**
 * Equipment Module JavaScript - Performance Optimized Version
 * File: assets/js/modules/equipment.js
 */

var EquipmentModule = (function() {
    'use strict';
    
    // Private variables
    let currentPage = 1;
    let totalPages = 1;
    let isLoading = false;
    let loadingTimeout = null;
    let searchTimeout = null;
    let cache = {
        xuong: null,
        khu_vuc: {},
        lines: {}
    };
    
    // Private methods
    function loadXuongOptions() {
        // Use cache if available
        if (cache.xuong) {
            populateXuongSelect(cache.xuong);
            return Promise.resolve(cache.xuong);
        }
        
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_xuong' }
        }).then(response => {
            if (response && response.success) {
                cache.xuong = response.data; // Cache result
                populateXuongSelect(response.data);
                return response.data;
            } else {
                console.error('Load xuong failed:', response);
                throw new Error('Failed to load xuong options');
            }
        });
    }
    
    function populateXuongSelect(data) {
        let options = '<option value="">Tất cả xưởng</option>';
        data.forEach(item => {
            options += `<option value="${item.id}">${item.ten_xuong}</option>`;
        });
        $('#filter_xuong').html(options);
    }
    
    function loadKhuVucOptions(xuongId) {
        const khuVucSelect = $('#filter_khu_vuc');
        
        if (!xuongId) {
            khuVucSelect.prop('disabled', true).html('<option value="">Tất cả khu vực</option>');
            $('#filter_line').prop('disabled', true).html('<option value="">Tất cả line</option>');
            return Promise.resolve([]);
        }
        
        // Use cache if available
        if (cache.khu_vuc[xuongId]) {
            populateKhuVucSelect(cache.khu_vuc[xuongId]);
            return Promise.resolve(cache.khu_vuc[xuongId]);
        }
        
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_khu_vuc', xuong_id: xuongId }
        }).then(response => {
            if (response && response.success) {
                cache.khu_vuc[xuongId] = response.data; // Cache result
                populateKhuVucSelect(response.data);
                return response.data;
            } else {
                console.error('Load khu vuc failed:', response);
                throw new Error('Failed to load khu vuc options');
            }
        });
    }
    
    function populateKhuVucSelect(data) {
        let options = '<option value="">Tất cả khu vực</option>';
        data.forEach(item => {
            options += `<option value="${item.id}">${item.ten_khu_vuc}</option>`;
        });
        $('#filter_khu_vuc').prop('disabled', false).html(options);
    }
    
    function loadLineOptions(xuongId) {
        const lineSelect = $('#filter_line');
        
        if (!xuongId) {
            lineSelect.prop('disabled', true).html('<option value="">Tất cả line</option>');
            return Promise.resolve([]);
        }
        
        // Use cache if available
        if (cache.lines[xuongId]) {
            populateLineSelect(cache.lines[xuongId]);
            return Promise.resolve(cache.lines[xuongId]);
        }
        
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_lines', xuong_id: xuongId }
        }).then(response => {
            if (response && response.success) {
                cache.lines[xuongId] = response.data; // Cache result
                populateLineSelect(response.data);
                return response.data;
            } else {
                console.error('Load lines failed:', response);
                throw new Error('Failed to load line options');
            }
        });
    }
    
    function populateLineSelect(data) {
        let options = '<option value="">Tất cả line</option>';
        data.forEach(item => {
            options += `<option value="${item.id}">${item.ten_line}</option>`;
        });
        $('#filter_line').prop('disabled', false).html(options);
    }
    
    function loadEquipmentData() {
        if (isLoading) return Promise.resolve([]);
        
        // Clear any existing timeout
        if (loadingTimeout) {
            clearTimeout(loadingTimeout);
        }
        
        isLoading = true;
        
        const formData = new FormData(document.getElementById('filterForm'));
        formData.append('action', 'list');
        formData.append('page', currentPage);
        
        console.time('Equipment Load');
        
        // Show loading with delay to prevent flashing
        loadingTimeout = setTimeout(() => {
            if (isLoading) {
                showLoadingState();
            }
        }, 200);
        
        return CMMS.ajax('api.php', {
            method: 'POST',
            data: formData,
            timeout: 10000 // 10 second timeout
        }).then(response => {
            clearTimeout(loadingTimeout);
            hideLoadingState();
            isLoading = false;
            console.timeEnd('Equipment Load');
            
            if (response && response.success) {
                displayEquipmentData(response.data);
                CMMS.displayPagination(response.pagination, 'pagination', 'EquipmentModule.changePage');
                totalPages = response.pagination.total_pages;
                currentPage = response.pagination.current_page;
                
                // Show debug info if available
                if (response.debug) {
                    console.log('Query time:', response.debug.query_time.toFixed(3) + 's');
                    console.log('Total records:', response.debug.total_records);
                }
                
                return response.data;
            } else {
                console.error('Equipment error:', response);
                showErrorState(response ? response.message : 'Không có response');
                throw new Error('Failed to load equipment data');
            }
        }).catch(error => {
            clearTimeout(loadingTimeout);
            hideLoadingState();
            isLoading = false;
            console.timeEnd('Equipment Load');
            console.error('Equipment AJAX error:', error);
            showErrorState('Lỗi kết nối API');
            throw error;
        });
    }
    
    function showLoadingState() {
        $('#equipmentTableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="text-muted">Đang tải dữ liệu thiết bị...</span>
                    </div>
                </td>
            </tr>
        `);
    }
    
    function hideLoadingState() {
        // Loading will be replaced by data or error
    }
    
    function showErrorState(message) {
        $('#equipmentTableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${message}
                        <br>
                        <button class="btn btn-outline-primary btn-sm mt-2" onclick="EquipmentModule.loadData()">
                            <i class="fas fa-sync me-1"></i>Thử lại
                        </button>
                    </div>
                </td>
            </tr>
        `);
    }
    
    function displayEquipmentData(data) {
        if (!data || data.length === 0) {
            $('#equipmentTableBody').html(`
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-search me-2"></i>
                            Không tìm thấy thiết bị nào
                            <br>
                            <small>Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</small>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }
        
        let html = '';
        
        data.forEach(item => {
            // Build location string efficiently
            let locationParts = [];
            if (item.ten_xuong) locationParts.push(item.ten_xuong);
            if (item.ten_line) locationParts.push(item.ten_line);
            if (item.ten_khu_vuc) locationParts.push(item.ten_khu_vuc);
            
            const locationStr = locationParts.join(' - ');
            const dongMayStr = item.ten_dong_may ? ` - ${item.ten_dong_may}` : '';
            const cumStr = item.ten_cum ? `<br><span class="badge bg-info">${item.ten_cum}</span>` : '';
            
            html += `
                <tr class="equipment-row" data-id="${item.id}">
                    <td>
                        <input type="checkbox" class="form-check-input equipment-checkbox" value="${item.id}">
                    </td>
                    <td>
                        <div class="equipment-id">${item.id_thiet_bi}</div>
                    </td>
                    <td>
                        <div class="equipment-name">${item.ten_thiet_bi}</div>
                        ${item.nganh ? `<small class="equipment-industry">${item.nganh}</small>` : ''}
                    </td>
                    <td>
                        <div class="location-hierarchy">
                            ${locationStr}${dongMayStr}
                            ${cumStr}
                        </div>
                    </td>
                    <td>
                        ${CMSSHelpers.getStatusBadge(item.tinh_trang, 'equipment')}
                    </td>
                    <td>
                        <small>${item.chu_quan_name || '<span class="text-muted">Chưa phân công</span>'}</small>
                    </td>
                    <td>
                        <small class="text-muted">${item.nam_san_xuat || '-'}</small>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm action-buttons">
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
        
        // Add subtle animation
        $('.equipment-row').hide().fadeIn(300);
    }
    
    function generateDetailHTML(data) {
        // Build full location string
        let fullLocation = '';
        if (data.ten_xuong) fullLocation += data.ten_xuong;
        if (data.ten_line) fullLocation += (fullLocation ? ' - ' : '') + data.ten_line;
        if (data.ten_khu_vuc) fullLocation += (fullLocation ? ' - ' : '') + data.ten_khu_vuc;
        if (data.ten_dong_may) fullLocation += (fullLocation ? ' - ' : '') + data.ten_dong_may;
        if (data.ten_cum) fullLocation += (fullLocation ? ' - ' : '') + data.ten_cum;
        
        return `
            <div class="row">
                <div class="col-md-8">
                    <table class="table table-borderless">
                        <tr><th width="30%">ID thiết bị:</th><td><strong>${data.id_thiet_bi}</strong></td></tr>
                        <tr><th>Tên thiết bị:</th><td>${data.ten_thiet_bi}</td></tr>
                        <tr><th>Vị trí đầy đủ:</th><td>${fullLocation || 'Chưa xác định'}</td></tr>
                        <tr><th>Xưởng:</th><td>${data.ten_xuong || ''}</td></tr>
                        <tr><th>Khu vực:</th><td>${data.ten_khu_vuc || ''}</td></tr>
                        <tr><th>Line sản xuất:</th><td>${data.ten_line || ''}</td></tr>
                        <tr><th>Dòng máy:</th><td>${data.ten_dong_may || ''}</td></tr>
                        <tr><th>Cụm thiết bị:</th><td>${data.ten_cum || ''}</td></tr>
                        <tr><th>Ngành:</th><td>${data.nganh || ''}</td></tr>
                        <tr><th>Năm sản xuất:</th><td>${data.nam_san_xuat || ''}</td></tr>
                        <tr><th>Công suất:</th><td>${data.cong_suat || ''}</td></tr>
                        <tr><th>Tình trạng:</th><td>${CMSSHelpers.getStatusBadge(data.tinh_trang, 'equipment')}</td></tr>
                        <tr><th>Người chủ quản:</th><td>${data.chu_quan_name || 'Chưa phân công'}</td></tr>
                        <tr><th>Thông số kỹ thuật:</th><td>${data.thong_so_ky_thuat || ''}</td></tr>
                        <tr><th>Ghi chú:</th><td>${data.ghi_chu || ''}</td></tr>
                        <tr><th>Ngày tạo:</th><td>${CMMS.formatDate(data.created_at)}</td></tr>
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
    
    function debounce(func, wait) {
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(searchTimeout);
                func(...args);
            };
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(later, wait);
        };
    }
    
    // Public methods
    return {
        // Initialize module
        init: function() {
            console.log('Equipment module initializing...');
            
            this.loadXuongOptions().then(() => {
                // Auto load equipment data after xuong options loaded
                this.loadData();
            });
            
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
            
            // Xuong change event - load khu vuc and lines
            $('#filter_xuong').on('change', function() {
                const xuongId = $(this).val();
                if (xuongId) {
                    // Load both khu_vuc and lines in parallel
                    Promise.all([
                        loadKhuVucOptions(xuongId),
                        loadLineOptions(xuongId)
                    ]).then(() => {
                        // Auto-filter when xuong changes
                        currentPage = 1;
                        EquipmentModule.loadData();
                    });
                } else {
                    $('#filter_khu_vuc').prop('disabled', true).html('<option value="">Tất cả khu vực</option>');
                    $('#filter_line').prop('disabled', true).html('<option value="">Tất cả line</option>');
                    currentPage = 1;
                    EquipmentModule.loadData();
                }
            });
            
            // Other filter changes
            $('#filter_khu_vuc, #filter_line, #filter_tinh_trang').on('change', function() {
                currentPage = 1;
                EquipmentModule.loadData();
            });
            
            // Select all checkbox
            $('#selectAll').on('change', function() {
                $('.equipment-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Optimized search with debounce
            $('#filter_search').on('input', debounce(() => {
                currentPage = 1;
                this.loadData();
            }, 800)); // Increased delay for better performance
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
            $('#filter_khu_vuc').prop('disabled', true).html('<option value="">Tất cả khu vực</option>');
            $('#filter_line').prop('disabled', true).html('<option value="">Tất cả line</option>');
            currentPage = 1;
            this.loadData();
        },
        
        // View equipment detail
        viewDetail: function(id) {
            // Show loading in modal first
            $('#equipmentDetailContent').html(`
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2">Đang tải chi tiết...</div>
                </div>
            `);
            $('#equipmentDetailModal').modal('show');
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'detail', id: id },
                timeout: 5000
            }).then(response => {
                if (response && response.success) {
                    $('#equipmentDetailContent').html(generateDetailHTML(response.data));
                } else {
                    $('#equipmentDetailContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Không thể tải chi tiết thiết bị
                        </div>
                    `);
                }
            }).catch(error => {
                console.error('View detail error:', error);
                $('#equipmentDetailContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Lỗi kết nối. Vui lòng thử lại.
                    </div>
                `);
            });
        },
        
        // Show QR code
        showQR: function(equipmentId) {
            const qrUrl = `https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=${encodeURIComponent(equipmentId)}`;
            
            $('#qrCodeContent').html(`
                <h6>Thiết bị: ${equipmentId}</h6>
                <img src="${qrUrl}" class="img-fluid" alt="QR Code" style="max-width: 200px;">
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
                    // Show loading
                    CMMS.showLoading('body');
                    
                    CMMS.ajax('api.php', {
                        data: { action: 'delete', id: id },
                        timeout: 10000
                    }).then(response => {
                        CMMS.hideLoading('body');
                        
                        if (response && response.success) {
                            CMMS.showAlert('Xóa thiết bị thành công', 'success');
                            this.loadData();
                        } else {
                            CMMS.showAlert(response ? response.message : 'Lỗi xóa thiết bị', 'error');
                        }
                    }).catch(error => {
                        CMMS.hideLoading('body');
                        console.error('Delete equipment error:', error);
                        CMMS.showAlert('Lỗi kết nối', 'error');
                    });
                }
            });
        },
        
        // Export data
        exportData: function() {
            const formData = new FormData(document.getElementById('filterForm'));
            
            // Show loading notification
            CMMS.showAlert('Đang chuẩn bị file xuất...', 'info');
            
            try {
                CMSSHelpers.exportAsCSV(null, 'danh_sach_thiet_bi.csv', formData);
            } catch (error) {
                console.error('Export error:', error);
                CMMS.showAlert('Lỗi xuất dữ liệu', 'error');
            }
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
            
            // Show batch QR modal
            $('#batchQRModal').modal('show');
        },
        
        // Print QR
        printQR: function() {
            const printContent = document.getElementById('qrCodeContent').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
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
            
            // Reset dependent dropdowns
            $('#filter_khu_vuc').prop('disabled', true).html('<option value="">Tất cả khu vực</option>');
            $('#filter_line').prop('disabled', true).html('<option value="">Tất cả line</option>');
            
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
        },
        
        // Clear cache (useful for development)
        clearCache: function() {
            cache = {
                xuong: null,
                khu_vuc: {},
                lines: {}
            };
            console.log('Cache cleared');
        },
        
        // Refresh data (force reload)
        refresh: function() {
            this.clearCache();
            this.loadData();
        },
        
        // Get current state
        getState: function() {
            return {
                currentPage,
                totalPages,
                isLoading,
                cacheSize: {
                    xuong: cache.xuong ? cache.xuong.length : 0,
                    khu_vuc: Object.keys(cache.khu_vuc).length,
                    lines: Object.keys(cache.lines).length
                }
            };
        }
    };
})();

// Auto-initialize when DOM is ready
$(document).ready(function() {
    // Check if we're on equipment index page before initializing
    if ($('#equipmentTable').length && window.location.pathname.includes('/equipment/index.php')) {
        // Add loading indicator during initialization
        $('#equipmentTableBody').html(`
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Khởi tạo module thiết bị...</div>
                </td>
            </tr>
        `);
        
        // Initialize with slight delay to ensure DOM is fully ready
        setTimeout(() => {
            EquipmentModule.init();
        }, 100);
    }
});

// Make module globally accessible
window.EquipmentModule = EquipmentModule;

// Performance monitoring (optional)
if (typeof console.time !== 'undefined') {
    window.equipmentPerformance = {
        lastLoadTime: 0,
        averageLoadTime: 0,
        loadCount: 0,
        
        recordLoad: function(time) {
            this.lastLoadTime = time;
            this.loadCount++;
            this.averageLoadTime = ((this.averageLoadTime * (this.loadCount - 1)) + time) / this.loadCount;
        },
        
        getStats: function() {
            return {
                last: this.lastLoadTime.toFixed(2) + 'ms',
                average: this.averageLoadTime.toFixed(2) + 'ms',
                count: this.loadCount
            };
        }
    };
}