/**
 * Equipment Edit Module JavaScript - Updated for new database structure
 * Chứa logic cho trang sửa thiết bị
 * File: assets/js/modules/equipment-edit.js
 */

var EquipmentEditModule = (function() {
    'use strict';
    
    // Private variables
    let equipmentData = null;
    let isLoading = false;
    
    // Private methods
    async function loadFormData() {
        if (isLoading) return;
        isLoading = true;
        
        try {
            console.log('Loading form data...');
            
            if (!equipmentData) {
                console.error('Equipment data not available');
                return;
            }
            
            console.log('Equipment data:', equipmentData);
            
            // Step 1: Load Xưởng first
            await loadXuongOptions(equipmentData.id_xuong);
            
            // Step 2: Load Khu vuc and Line if xuong exists
            if (equipmentData.id_xuong) {
                await Promise.all([
                    loadKhuVucOptions(equipmentData.id_xuong, equipmentData.id_khu_vuc),
                    loadLineOptions(equipmentData.id_xuong, equipmentData.id_line)
                ]);
            }
            
            // Step 3: Load Dong may if line exists
            if (equipmentData.id_line) {
                await loadDongMayOptions(equipmentData.id_line, equipmentData.id_dong_may);
            }
            
            // Step 4: Load Cum thiet bi if dong may exists
            if (equipmentData.id_dong_may) {
                await loadCumThietBiOptions(equipmentData.id_dong_may, equipmentData.id_cum_thiet_bi);
            }
            
            console.log('Form data loaded successfully');
            
        } catch (error) {
            console.error('Error loading form data:', error);
            CMMS.showAlert('Lỗi tải dữ liệu form', 'error');
        } finally {
            isLoading = false;
        }
    }
    
    function loadXuongOptions(selectedXuongId = null) {
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
                        const selected = item.id == selectedXuongId ? 'selected' : '';
                        options += `<option value="${item.id}" ${selected}>${item.ten_xuong}</option>`;
                    });
                    $('#id_xuong').html(options);
                    
                    console.log('Xuong loaded, selected:', selectedXuongId);
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
    
    function loadKhuVucOptions(xuongId, selectedKhuVucId = null) {
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
                        const selected = item.id == selectedKhuVucId ? 'selected' : '';
                        options += `<option value="${item.id}" ${selected}>${item.ten_khu_vuc}</option>`;
                    });
                    khuVucSelect.prop('disabled', false).html(options);
                    
                    console.log('Khu vuc loaded, selected:', selectedKhuVucId);
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
    
    function loadLineOptions(xuongId, selectedLineId = null) {
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
                        const selected = item.id == selectedLineId ? 'selected' : '';
                        options += `<option value="${item.id}" ${selected}>${item.ten_line}</option>`;
                    });
                    lineSelect.prop('disabled', false).html(options);
                    
                    console.log('Line loaded, selected:', selectedLineId);
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
    
    function loadDongMayOptions(lineId, selectedDongMayId = null) {
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
                        const selected = item.id == selectedDongMayId ? 'selected' : '';
                        options += `<option value="${item.id}" ${selected}>${item.ten_dong_may}</option>`;
                    });
                    dongMaySelect.prop('disabled', false).html(options);
                    
                    console.log('Dong may loaded, selected:', selectedDongMayId);
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
    
    function loadCumThietBiOptions(dongMayId, selectedCumThietBiId = null) {
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
                        const selected = item.id == selectedCumThietBiId ? 'selected' : '';
                        options += `<option value="${item.id}" ${selected}>${item.ten_cum}</option>`;
                    });
                    cumThietBiSelect.prop('disabled', false).html(options);
                    
                    console.log('Cum thiet bi loaded, selected:', selectedCumThietBiId);
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
            
            $(`#${id}`).prop('disabled', true).html(`<option value="">${defaultText}</option>`);
        });
    }
    
    function updateQRPreview(equipmentId) {
        if (!equipmentId.trim()) {
            return;
        }
        
        const qrUrl = `https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=${encodeURIComponent(equipmentId)}`;
        
        $('#qrPreview').html(`
            <h6>${equipmentId}</h6>
            <img src="${qrUrl}" class="img-fluid" alt="QR Code">
            <div class="mt-3">
                <button class="btn btn-outline-primary btn-sm" onclick="EquipmentEditModule.printQR()">
                    <i class="fas fa-print me-2"></i>In QR
                </button>
            </div>
        `);
    }
    
    function handleXuongChange() {
        const xuongId = $('#id_xuong').val();
        if (xuongId) {
            // Reset and reload dependent dropdowns
            resetDownstreamDropdowns(['id_khu_vuc', 'id_line', 'id_dong_may', 'id_cum_thiet_bi']);
            
            Promise.all([
                loadKhuVucOptions(xuongId),
                loadLineOptions(xuongId)
            ]).catch(error => {
                console.error('Error loading xuong dependent data:', error);
                CMMS.showAlert('Lỗi tải dữ liệu', 'error');
            });
        } else {
            resetDownstreamDropdowns(['id_khu_vuc', 'id_line', 'id_dong_may', 'id_cum_thiet_bi']);
        }
    }
    
    function handleLineChange() {
        const lineId = $('#id_line').val();
        if (lineId) {
            resetDownstreamDropdowns(['id_dong_may', 'id_cum_thiet_bi']);
            loadDongMayOptions(lineId);
        } else {
            resetDownstreamDropdowns(['id_dong_may', 'id_cum_thiet_bi']);
        }
    }
    
    function handleDongMayChange() {
        const dongMayId = $('#id_dong_may').val();
        if (dongMayId) {
            resetDownstreamDropdowns(['id_cum_thiet_bi']);
            loadCumThietBiOptions(dongMayId);
        } else {
            resetDownstreamDropdowns(['id_cum_thiet_bi']);
        }
    }
    
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(document.getElementById('equipmentForm'));
        formData.append('action', 'update');
        
        // Show loading
        CMMS.showLoading('body');
        
        CMMS.ajax('api.php', {
            data: formData
        }).then(response => {
            CMMS.hideLoading('body');
            
            if (response && response.success) {
                CMMS.showAlert('Cập nhật thiết bị thành công!', 'success');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                CMMS.showAlert(response ? response.message : 'Lỗi cập nhật thiết bị', 'error');
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
    }
    
    // Public methods
    return {
        // Initialize module
        init: function(data) {
            console.log('Equipment Edit module initializing...');
            
            // Store equipment data
            equipmentData = data;
            
            // Load form data
            this.loadFormData();
            
            // Bind events
            this.bindEvents();
        },
        
        // Load form data
        loadFormData: loadFormData,
        
        // Bind events
        bindEvents: function() {
            // Form submission
            $('#equipmentForm').on('submit', handleFormSubmit);
            
            // Cascading dropdowns - only for manual changes
            $('#id_xuong').change(handleXuongChange);
            $('#id_line').change(handleLineChange);
            $('#id_dong_may').change(handleDongMayChange);
            
            // QR Preview update
            $('input[name="id_thiet_bi"]').on('input', handleEquipmentIdChange);
        },
        
        // Print QR Code
        printQR: function() {
            const printContent = document.getElementById('qrPreview').innerHTML;
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
        
        // Reload form data
        reloadFormData: function() {
            loadFormData();
        },
        
        // Update equipment data
        setEquipmentData: function(data) {
            equipmentData = data;
            loadFormData();
        },
        
        // Get current equipment data
        getEquipmentData: function() {
            return equipmentData;
        },
        
        // Manual cascade loading for testing
        loadKhuVucForXuong: function(xuongId, selectedId = null) {
            return loadKhuVucOptions(xuongId, selectedId);
        },
        
        loadLineForXuong: function(xuongId, selectedId = null) {
            return loadLineOptions(xuongId, selectedId);
        },
        
        loadDongMayForLine: function(lineId, selectedId = null) {
            return loadDongMayOptions(lineId, selectedId);
        },
        
        loadCumThietBiForDongMay: function(dongMayId, selectedId = null) {
            return loadCumThietBiOptions(dongMayId, selectedId);
        }
    };
})();

// Auto-initialize when DOM is ready
function initEquipmentEditModule() {
    // Check if jQuery is available
    if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
        console.log('jQuery not ready, retrying...');
        setTimeout(initEquipmentEditModule, 100);
        return;
    }
    
    // Check if we're on equipment edit page
    if ($('#equipmentForm').length && window.EQUIPMENT_DATA) {
        EquipmentEditModule.init(window.EQUIPMENT_DATA);
    }
}

// Try multiple initialization methods
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEquipmentEditModule);
} else {
    initEquipmentEditModule();
}

// Also try with jQuery when available
if (typeof $ !== 'undefined') {
    $(document).ready(initEquipmentEditModule);
}

// Make module globally accessible
window.EquipmentEditModule = EquipmentEditModule;