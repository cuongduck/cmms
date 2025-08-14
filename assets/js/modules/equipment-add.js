/**
 * Equipment Add Module JavaScript - Updated for new database structure
 * Chứa logic cho trang thêm thiết bị mới
 * File: assets/js/modules/equipment-add.js
 */

var EquipmentAddModule = (function() {
    'use strict';
    
    // Private variables
    let isLoading = false;
    
    // Private methods
    function loadXuongOptions() {
        return new Promise((resolve, reject) => {
            console.log('Loading xuong options...');
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_xuong' }
            }).then(response => {
                console.log('Xuong response:', response);
                
                if (response && response.success) {
                    let options = '<option value="">Chọn xưởng</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}">${item.ten_xuong}</option>`;
                    });
                    $('#id_xuong').html(options);
                    
                    console.log('Xuong options loaded');
                    resolve(response.data);
                } else {
                    console.error('Failed to load xuong:', response);
                    reject(new Error('Failed to load xuong'));
                }
            }).catch(error => {
                console.error('Xuong API error:', error);
                reject(error);
            });
        });
    }
    
    function loadKhuVucOptions(xuongId) {
        return new Promise((resolve, reject) => {
            const khuVucSelect = $('#id_khu_vuc');
            
            if (!xuongId) {
                khuVucSelect.prop('disabled', true).html('<option value="">Chọn khu vực</option>');
                resolve([]);
                return;
            }
            
            console.log('Loading khu vuc options for xuong:', xuongId);
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_khu_vuc', xuong_id: xuongId }
            }).then(response => {
                console.log('Khu vuc response:', response);
                
                if (response && response.success) {
                    let options = '<option value="">Chọn khu vực</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}">${item.ten_khu_vuc}</option>`;
                    });
                    khuVucSelect.prop('disabled', false).html(options);
                    
                    console.log('Khu vuc options loaded');
                    resolve(response.data);
                } else {
                    console.error('Failed to load khu vuc:', response);
                    reject(new Error('Failed to load khu vuc'));
                }
            }).catch(error => {
                console.error('Khu vuc API error:', error);
                reject(error);
            });
        });
    }
    
    function loadLineOptions(xuongId) {
        return new Promise((resolve, reject) => {
            const lineSelect = $('#id_line');
            
            if (!xuongId) {
                lineSelect.prop('disabled', true).html('<option value="">Chọn line</option>');
                resolve([]);
                return;
            }
            
            console.log('Loading line options for xuong:', xuongId);
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_lines', xuong_id: xuongId }
            }).then(response => {
                console.log('Line response:', response);
                
                if (response && response.success) {
                    let options = '<option value="">Chọn line</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}">${item.ten_line}</option>`;
                    });
                    lineSelect.prop('disabled', false).html(options);
                    
                    console.log('Line options loaded');
                    resolve(response.data);
                } else {
                    console.error('Failed to load lines:', response);
                    reject(new Error('Failed to load lines'));
                }
            }).catch(error => {
                console.error('Line API error:', error);
                reject(error);
            });
        });
    }
    
    function loadDongMayOptions(lineId) {
        return new Promise((resolve, reject) => {
            const dongMaySelect = $('#id_dong_may');
            
            if (!lineId) {
                dongMaySelect.prop('disabled', true).html('<option value="">Chọn dòng máy</option>');
                resolve([]);
                return;
            }
            
            console.log('Loading dong may options for line:', lineId);
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_dong_may', line_id: lineId }
            }).then(response => {
                console.log('Dong may response:', response);
                
                if (response && response.success) {
                    let options = '<option value="">Chọn dòng máy</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}">${item.ten_dong_may}</option>`;
                    });
                    dongMaySelect.prop('disabled', false).html(options);
                    
                    console.log('Dong may options loaded');
                    resolve(response.data);
                } else {
                    console.error('Failed to load dong may:', response);
                    reject(new Error('Failed to load dong may'));
                }
            }).catch(error => {
                console.error('Dong may API error:', error);
                reject(error);
            });
        });
    }
    
    function loadCumThietBiOptions(dongMayId) {
        return new Promise((resolve, reject) => {
            const cumThietBiSelect = $('#id_cum_thiet_bi');
            
            if (!dongMayId) {
                cumThietBiSelect.prop('disabled', true).html('<option value="">Chọn cụm thiết bị</option>');
                resolve([]);
                return;
            }
            
            console.log('Loading cum thiet bi options for dong may:', dongMayId);
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_cum_thiet_bi', dong_may_id: dongMayId }
            }).then(response => {
                console.log('Cum thiet bi response:', response);
                
                if (response && response.success) {
                    let options = '<option value="">Chọn cụm thiết bị</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}">${item.ten_cum}</option>`;
                    });
                    cumThietBiSelect.prop('disabled', false).html(options);
                    
                    console.log('Cum thiet bi options loaded');
                    resolve(response.data);
                } else {
                    console.error('Failed to load cum thiet bi:', response);
                    reject(new Error('Failed to load cum thiet bi'));
                }
            }).catch(error => {
                console.error('Cum thiet bi API error:', error);
                reject(error);
            });
        });
    }
    
    function resetDownstreamDropdowns(dropdownIds) {
        dropdownIds.forEach(id => {
            const dropdown = $(`#${id}`);
            let defaultText = 'Chọn...';
            
            switch (id) {
                case 'id_khu_vuc':
                    defaultText = 'Chọn khu vực';
                    break;
                case 'id_line':
                    defaultText = 'Chọn line';
                    break;
                case 'id_dong_may':
                    defaultText = 'Chọn dòng máy';
                    break;
                case 'id_cum_thiet_bi':
                    defaultText = 'Chọn cụm thiết bị';
                    break;
            }
            
            dropdown.prop('disabled', true).html(`<option value="">${defaultText}</option>`);
        });
    }
    
    function updateQRPreview(equipmentId) {
        if (!equipmentId.trim()) {
            $('#qrPreview').html(`
                <div class="text-muted">
                    <i class="fas fa-qrcode fa-3x mb-3 opacity-50"></i>
                    <p>Nhập ID thiết bị để xem QR Code</p>
                </div>
            `);
            return;
        }
        
        const qrUrl = `https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=${encodeURIComponent(equipmentId)}`;
        
        $('#qrPreview').html(`
            <h6>${equipmentId}</h6>
            <img src="${qrUrl}" class="img-fluid" alt="QR Code">
            <p class="mt-2 text-muted small">QR Code sẽ được tạo tự động</p>
        `);
    }
    
    function validateForm() {
        const requiredFields = ['id_thiet_bi', 'ten_thiet_bi', 'id_xuong', 'id_khu_vuc'];
        let isValid = true;
        let firstInvalidField = null;
        
        requiredFields.forEach(fieldName => {
            const field = $(`[name="${fieldName}"]`);
            const value = field.val();
            
            if (!value || value.trim() === '') {
                field.addClass('is-invalid');
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
                isValid = false;
            } else {
                field.removeClass('is-invalid');
            }
        });
        
        if (!isValid && firstInvalidField) {
            firstInvalidField.focus();
            CMMS.showAlert('Vui lòng nhập đầy đủ thông tin bắt buộc', 'warning');
        }
        
        return isValid;
    }
    
    function handleXuongChange() {
        const xuongId = $('#id_xuong').val();
        resetDownstreamDropdowns(['id_khu_vuc', 'id_line', 'id_dong_may', 'id_cum_thiet_bi']);
        
        if (xuongId) {
            // Load both khu vuc and line for the selected xuong
            Promise.all([
                loadKhuVucOptions(xuongId),
                loadLineOptions(xuongId)
            ]).catch(error => {
                console.error('Error loading xuong dependent data:', error);
                CMMS.showAlert('Lỗi tải dữ liệu', 'error');
            });
        }
    }
    
    function handleKhuVucChange() {
        // Khu vuc doesn't directly affect line, but we can trigger validation
        const khuVucId = $('#id_khu_vuc').val();
        // Remove validation error if user selected something
        if (khuVucId) {
            $('#id_khu_vuc').removeClass('is-invalid');
        }
    }
    
    function handleLineChange() {
        const lineId = $('#id_line').val();
        resetDownstreamDropdowns(['id_dong_may', 'id_cum_thiet_bi']);
        
        if (lineId) {
            loadDongMayOptions(lineId);
        }
    }
    
    function handleDongMayChange() {
        const dongMayId = $('#id_dong_may').val();
        resetDownstreamDropdowns(['id_cum_thiet_bi']);
        
        if (dongMayId) {
            loadCumThietBiOptions(dongMayId);
        }
    }
    
    function handleFormSubmit(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        const formData = new FormData(document.getElementById('equipmentForm'));
        formData.append('action', 'create');
        
        // Show loading
        CMMS.showLoading('body');
        
        CMMS.ajax('api.php', {
            data: formData
        }).then(response => {
            CMMS.hideLoading('body');
            
            if (response && response.success) {
                CMMS.showAlert('Tạo thiết bị thành công!', 'success');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                CMMS.showAlert(response ? response.message : 'Lỗi tạo thiết bị', 'error');
            }
        }).catch(error => {
            CMMS.hideLoading('body');
            console.error('Submit error:', error);
            CMMS.showAlert('Lỗi kết nối', 'error');
        });
    }
    
    function handleEquipmentIdChange() {
        const equipmentId = $('input[name="id_thiet_bi"]').val();
        updateQRPreview(equipmentId);
        
        // Remove validation error if user is typing
        $('input[name="id_thiet_bi"]').removeClass('is-invalid');
    }
    
    function handleFieldChange() {
        // Remove validation error when user starts typing
        $(this).removeClass('is-invalid');
    }
    
    // Public methods
    return {
        // Initialize module
        init: function() {
            console.log('Equipment Add module initializing...');
            
            // Load initial data
            this.loadInitialData();
            
            // Bind events
            this.bindEvents();
        },
        
        // Load initial data
        loadInitialData: function() {
            loadXuongOptions().catch(error => {
                console.error('Failed to load initial data:', error);
                CMMS.showAlert('Lỗi tải dữ liệu ban đầu', 'error');
            });
        },
        
        // Bind events
        bindEvents: function() {
            // Form submission
            $('#equipmentForm').on('submit', handleFormSubmit);
            
            // Cascading dropdowns - Updated hierarchy
            $('#id_xuong').change(handleXuongChange);
            $('#id_khu_vuc').change(handleKhuVucChange);
            $('#id_line').change(handleLineChange);
            $('#id_dong_may').change(handleDongMayChange);
            
            // QR Preview update
            $('input[name="id_thiet_bi"]').on('input', handleEquipmentIdChange);
            
            // Remove validation errors on input
            $('input[required], select[required]').on('input change', handleFieldChange);
        },
        
        // Reset form
        resetForm: function() {
            document.getElementById('equipmentForm').reset();
            resetDownstreamDropdowns(['id_khu_vuc', 'id_line', 'id_dong_may', 'id_cum_thiet_bi']);
            updateQRPreview('');
            
            // Remove all validation errors
            $('.is-invalid').removeClass('is-invalid');
        },
        
        // Check if equipment ID exists
        checkEquipmentIdExists: function(equipmentId) {
            return CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'search_by_id', equipment_id: equipmentId }
            }).then(response => {
                return response && response.success;
            }).catch(() => {
                return false;
            });
        },
        
        // Validate equipment ID uniqueness
        validateEquipmentId: async function(equipmentId) {
            if (!equipmentId || equipmentId.trim() === '') {
                return { valid: false, message: 'ID thiết bị không được để trống' };
            }
            
            const exists = await this.checkEquipmentIdExists(equipmentId);
            if (exists) {
                return { valid: false, message: 'ID thiết bị đã tồn tại trong hệ thống' };
            }
            
            return { valid: true, message: 'ID thiết bị hợp lệ' };
        },
        
        // Manual cascade loading for testing
        loadKhuVucForXuong: function(xuongId) {
            return loadKhuVucOptions(xuongId);
        },
        
        loadLineForXuong: function(xuongId) {
            return loadLineOptions(xuongId);
        },
        
        loadDongMayForLine: function(lineId) {
            return loadDongMayOptions(lineId);
        },
        
        loadCumThietBiForDongMay: function(dongMayId) {
            return loadCumThietBiOptions(dongMayId);
        }
    };
})();

// Auto-initialize when DOM is ready
function initEquipmentAddModule() {
    // Check if jQuery is available
    if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
        console.log('jQuery not ready, retrying...');
        setTimeout(initEquipmentAddModule, 100);
        return;
    }
    
    // Check if we're on equipment add page
    if ($('#equipmentForm').length && window.location.pathname.includes('/equipment/add.php')) {
        EquipmentAddModule.init();
    }
}

// Try multiple initialization methods
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEquipmentAddModule);
} else {
    initEquipmentAddModule();
}

// Also try with jQuery when available
if (typeof $ !== 'undefined') {
    $(document).ready(initEquipmentAddModule);
}

// Make module globally accessible
window.EquipmentAddModule = EquipmentAddModule;