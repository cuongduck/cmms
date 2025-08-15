/**
 * Organization Structure Management Module JavaScript
 * Chứa logic cho trang quản lý cấu trúc tổ chức
 * File: assets/js/modules/organization.js
 */

var OrganizationModule = (function() {
    'use strict';
    
    // Private variables
    let currentType = 'nganh';
    let isLoading = false;
    let loadingTimeout = null;
    let cache = {
        nganh: null,
        xuong: null,
        khu_vuc: null,
        line: null,
        dong_may: null,
        cum_thiet_bi: null
    };
    
    // Private methods
    function loadTabData(type) {
        if (isLoading) return Promise.resolve([]);
        
        console.log('Loading data for type:', type);
        
        // Use cache if available
        if (cache[type]) {
            displayTableData(type, cache[type]);
            return Promise.resolve(cache[type]);
        }
        
        isLoading = true;
        currentType = type;
        
        // Show loading with delay to prevent flashing
        if (loadingTimeout) {
            clearTimeout(loadingTimeout);
        }
        
        loadingTimeout = setTimeout(() => {
            if (isLoading) {
                showLoadingState(type);
            }
        }, 200);
        
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'list', type: type }
        }).then(response => {
            clearTimeout(loadingTimeout);
            isLoading = false;
            
            if (response && response.success) {
                cache[type] = response.data; // Cache the result
                displayTableData(type, response.data);
                return response.data;
            } else {
                console.error('Failed to load data:', response);
                showErrorState(type, response?.message || 'Lỗi tải dữ liệu');
                throw new Error('Failed to load data');
            }
        }).catch(error => {
            clearTimeout(loadingTimeout);
            isLoading = false;
            console.error('Error loading data:', error);
            showErrorState(type, 'Lỗi kết nối API');
            throw error;
        });
    }
    
    function showLoadingState(type) {
        const tableBody = document.getElementById(getTableBodyId(type));
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-3">
                        <div class="d-flex justify-content-center align-items-center">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            <span class="text-muted">Đang tải dữ liệu ${getTypeDisplayName(type)}...</span>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
    
    function showErrorState(type, message) {
        const tableBody = document.getElementById(getTableBodyId(type));
        if (tableBody) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-3">
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${message}
                            <br>
                            <button class="btn btn-outline-primary btn-sm mt-2" onclick="OrganizationModule.reloadTab('${type}')">
                                <i class="fas fa-sync me-1"></i>Thử lại
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
    
    function displayTableData(type, data) {
        const tableBody = document.getElementById(getTableBodyId(type));
        if (!tableBody) return;
        
        let html = '';
        
        if (!data || data.length === 0) {
            html = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-inbox me-2"></i>
                            Chưa có ${getTypeDisplayName(type)} nào
                            <br>
                            <button class="btn btn-outline-primary btn-sm mt-2" onclick="OrganizationModule.showAddModal('${type}')">
                                <i class="fas fa-plus me-1"></i>Thêm ${getTypeDisplayName(type)} đầu tiên
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            data.forEach(item => {
                html += generateTableRow(type, item);
            });
        }
        
        tableBody.innerHTML = html;
        
        // Add subtle animation
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(10px)';
            setTimeout(() => {
                row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 50);
        });
    }
    
    function generateTableRow(type, item) {
        switch(type) {
            case 'nganh':
                return `
                    <tr class="org-row" data-id="${item.id}">
                        <td><code class="org-code">${item.ma_nganh}</code></td>
                        <td><strong>${item.ten_nganh}</strong></td>
                        <td class="text-muted">${item.mo_ta || '<em>Chưa có mô tả</em>'}</td>
                        <td><small class="text-muted">${CMMS.formatDate(item.created_at)}</small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" onclick="OrganizationModule.editItem('nganh', ${item.id})" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="OrganizationModule.deleteItem('nganh', ${item.id}, '${item.ten_nganh}')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            case 'xuong':
                return `
                    <tr class="org-row" data-id="${item.id}">
                        <td><code class="org-code">${item.ma_xuong}</code></td>
                        <td><strong>${item.ten_xuong}</strong></td>
                        <td><span class="badge bg-primary">${item.ten_nganh || 'N/A'}</span></td>
                        <td class="text-muted">${item.mo_ta || '<em>Chưa có mô tả</em>'}</td>
                        <td><small class="text-muted">${CMMS.formatDate(item.created_at)}</small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" onclick="OrganizationModule.editItem('xuong', ${item.id})" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="OrganizationModule.deleteItem('xuong', ${item.id}, '${item.ten_xuong}')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            case 'khu_vuc':
                return `
                    <tr class="org-row" data-id="${item.id}">
                        <td><code class="org-code">${item.ma_khu_vuc}</code></td>
                        <td><strong>${item.ten_khu_vuc}</strong></td>
                        <td><span class="badge bg-success">${item.ten_xuong || 'N/A'}</span></td>
                        <td><span class="badge bg-info">${item.loai_khu_vuc == 'cong_nghe' ? 'Công nghệ' : 'Đóng gói'}</span></td>
                        <td class="text-muted">${item.mo_ta || '<em>Chưa có mô tả</em>'}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" onclick="OrganizationModule.editItem('khu_vuc', ${item.id})" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="OrganizationModule.deleteItem('khu_vuc', ${item.id}, '${item.ten_khu_vuc}')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            case 'line':
                return `
                    <tr class="org-row" data-id="${item.id}">
                        <td><code class="org-code">${item.ma_line}</code></td>
                        <td><strong>${item.ten_line}</strong></td>
                        <td><span class="badge bg-success">${item.ten_xuong || 'N/A'}</span></td>
                        <td><span class="badge bg-info">${item.ten_khu_vuc || 'N/A'}</span></td>
                        <td class="text-muted">${item.mo_ta || '<em>Chưa có mô tả</em>'}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" onclick="OrganizationModule.editItem('line', ${item.id})" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="OrganizationModule.deleteItem('line', ${item.id}, '${item.ten_line}')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            case 'dong_may':
                return `
                    <tr class="org-row" data-id="${item.id}">
                        <td><code class="org-code">${item.ma_dong_may}</code></td>
                        <td><strong>${item.ten_dong_may}</strong></td>
                        <td><span class="badge bg-warning text-dark">${item.ten_line || 'N/A'}</span></td>
                        <td class="text-muted">${item.mo_ta || '<em>Chưa có mô tả</em>'}</td>
                        <td><small class="text-muted">${CMMS.formatDate(item.created_at)}</small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" onclick="OrganizationModule.editItem('dong_may', ${item.id})" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="OrganizationModule.deleteItem('dong_may', ${item.id}, '${item.ten_dong_may}')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            case 'cum_thiet_bi':
                return `
                    <tr class="org-row" data-id="${item.id}">
                        <td><code class="org-code">${item.ma_cum}</code></td>
                        <td><strong>${item.ten_cum}</strong></td>
                        <td><span class="badge bg-purple text-white">${item.ten_dong_may || 'N/A'}</span></td>
                        <td class="text-muted">${item.mo_ta || '<em>Chưa có mô tả</em>'}</td>
                        <td><small class="text-muted">${CMMS.formatDate(item.created_at)}</small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning" onclick="OrganizationModule.editItem('cum_thiet_bi', ${item.id})" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="OrganizationModule.deleteItem('cum_thiet_bi', ${item.id}, '${item.ten_cum}')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            default:
                return '';
        }
    }
    
    function getTableBodyId(type) {
        const ids = {
            'nganh': 'nganhTableBody',
            'xuong': 'xuongTableBody',
            'khu_vuc': 'khuVucTableBody',
            'line': 'lineTableBody',
            'dong_may': 'dongMayTableBody',
            'cum_thiet_bi': 'cumThietBiTableBody'
        };
        return ids[type];
    }
    
    function getTypeDisplayName(type) {
        const names = {
            'nganh': 'ngành',
            'xuong': 'xưởng', 
            'khu_vuc': 'khu vực',
            'line': 'line sản xuất',
            'dong_may': 'dòng máy',
            'cum_thiet_bi': 'cụm thiết bị'
        };
        return names[type] || type;
    }
    
    function getFormHTML(type, data = null) {
        const isEdit = data !== null;
        
        switch(type) {
            case 'nganh':
                return `
                    ${isEdit ? `<input type="hidden" name="id" value="${data.id}">` : ''}
                    <div class="mb-3">
                        <label class="form-label">Mã ngành <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ma_nganh" required 
                               value="${data?.ma_nganh || ''}" placeholder="Ví dụ: MI, PHO, NEM">
                        <div class="form-text">Mã ngắn gọn, duy nhất để nhận diện ngành</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên ngành <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_nganh" required 
                               value="${data?.ten_nganh || ''}" placeholder="Tên đầy đủ của ngành">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3" 
                                  placeholder="Mô tả chi tiết về ngành sản xuất">${data?.mo_ta || ''}</textarea>
                    </div>
                `;
                
            case 'xuong':
                return `
                    ${isEdit ? `<input type="hidden" name="id" value="${data.id}">` : ''}
                    <div class="mb-3">
                        <label class="form-label">Thuộc ngành <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_nganh" id="parent_select" required>
                            <option value="">Chọn ngành</option>
                        </select>
                        <div class="form-text">Xưởng này thuộc ngành sản xuất nào</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mã xưởng <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ma_xuong" required 
                               value="${data?.ma_xuong || ''}" placeholder="Ví dụ: F2, F3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên xưởng <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_xuong" required 
                               value="${data?.ten_xuong || ''}" placeholder="Tên đầy đủ của xưởng">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3" 
                                  placeholder="Mô tả chi tiết về xưởng sản xuất">${data?.mo_ta || ''}</textarea>
                    </div>
                `;
                
            case 'khu_vuc':
                return `
                    ${isEdit ? `<input type="hidden" name="id" value="${data.id}">` : ''}
                    <div class="mb-3">
                        <label class="form-label">Thuộc xưởng <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_xuong" id="parent_select" required>
                            <option value="">Chọn xưởng</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mã khu vực <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ma_khu_vuc" required 
                               value="${data?.ma_khu_vuc || ''}" placeholder="Ví dụ: CN_F2, DG_F2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên khu vực <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_khu_vuc" required 
                               value="${data?.ten_khu_vuc || ''}" placeholder="Tên đầy đủ của khu vực">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Loại khu vực <span class="text-danger">*</span></label>
                        <select class="form-select" name="loai_khu_vuc" required>
                            <option value="">Chọn loại</option>
                            <option value="cong_nghe" ${data?.loai_khu_vuc === 'cong_nghe' ? 'selected' : ''}>Công nghệ</option>
                            <option value="dong_goi" ${data?.loai_khu_vuc === 'dong_goi' ? 'selected' : ''}>Đóng gói</option>
                        </select>
                        <div class="form-text">Công nghệ: sản xuất chính, Đóng gói: đóng gói sản phẩm</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3" 
                                  placeholder="Mô tả chi tiết về khu vực">${data?.mo_ta || ''}</textarea>
                    </div>
                `;
                
            case 'line':
                return `
                    ${isEdit ? `<input type="hidden" name="id" value="${data.id}">` : ''}
                    <div class="mb-3">
                        <label class="form-label">Thuộc xưởng <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_xuong" id="xuong_select" required>
                            <option value="">Chọn xưởng</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Thuộc khu vực <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_khu_vuc" id="khu_vuc_select" required disabled>
                            <option value="">Chọn khu vực</option>
                        </select>
                        <div class="form-text">Chọn xưởng trước để hiện danh sách khu vực</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mã line <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ma_line" required 
                               value="${data?.ma_line || ''}" placeholder="Ví dụ: L1, L2, PHO1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên line <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_line" required 
                               value="${data?.ten_line || ''}" placeholder="Tên đầy đủ của line sản xuất">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3" 
                                  placeholder="Mô tả chi tiết về line sản xuất">${data?.mo_ta || ''}</textarea>
                    </div>
                `;
                
            case 'dong_may':
                return `
                    ${isEdit ? `<input type="hidden" name="id" value="${data.id}">` : ''}
                    <div class="mb-3">
                        <label class="form-label">Thuộc line <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_line" id="parent_select" required>
                            <option value="">Chọn line</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mã dòng máy <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ma_dong_may" required 
                               value="${data?.ma_dong_may || ''}" placeholder="Ví dụ: OMORI_L1, CHAO_CHIEN_L1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên dòng máy <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_dong_may" required 
                               value="${data?.ten_dong_may || ''}" placeholder="Tên đầy đủ của dòng máy">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3" 
                                  placeholder="Mô tả chi tiết về dòng máy">${data?.mo_ta || ''}</textarea>
                    </div>
                `;
                
            case 'cum_thiet_bi':
                return `
                    ${isEdit ? `<input type="hidden" name="id" value="${data.id}">` : ''}
                    <div class="mb-3">
                        <label class="form-label">Thuộc dòng máy <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_dong_may" id="parent_select" required>
                            <option value="">Chọn dòng máy</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mã cụm <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ma_cum" required 
                               value="${data?.ma_cum || ''}" placeholder="Ví dụ: LO_CAN_THO_L1, OMORI_CHINH_L1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên cụm <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ten_cum" required 
                               value="${data?.ten_cum || ''}" placeholder="Tên đầy đủ của cụm thiết bị">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="mo_ta" rows="3" 
                                  placeholder="Mô tả chi tiết về cụm thiết bị">${data?.mo_ta || ''}</textarea>
                    </div>
                `;
                
            default:
                return '<p class="text-danger">Form không được hỗ trợ</p>';
        }
    }
    
    function loadParentDropdowns(type, data = null) {
        const parentSelects = {
            'xuong': 'nganh',
            'khu_vuc': 'xuong', 
            'line': ['xuong', 'khu_vuc'],
            'dong_may': 'line',
            'cum_thiet_bi': 'dong_may'
        };
        
        const parentTypes = parentSelects[type];
        if (!parentTypes) return;
        
        if (Array.isArray(parentTypes)) {
            // Handle line case with xuong and khu_vuc
            loadDropdownData('xuong', 'xuong_select', data?.id_xuong);
            
            // Setup cascade for line
            $('#xuong_select').on('change', () => {
                const xuongId = $('#xuong_select').val();
                if (xuongId) {
                    loadKhuVucForXuong(xuongId, data?.id_khu_vuc);
                } else {
                    $('#khu_vuc_select').prop('disabled', true).html('<option value="">Chọn khu vực</option>');
                }
            });
            
            // If editing, trigger the cascade
            if (data?.id_xuong) {
                setTimeout(() => {
                    $('#xuong_select').trigger('change');
                }, 100);
            }
            
        } else {
            // Single parent
            loadDropdownData(parentTypes, 'parent_select', data ? data[`id_${parentTypes}`] : null);
        }
    }
    
    function loadDropdownData(parentType, selectId, selectedValue = null) {
        // Use cache if available
        if (cache[parentType]) {
            populateDropdown(selectId, parentType, cache[parentType], selectedValue);
            return Promise.resolve(cache[parentType]);
        }
        
        return CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'list', type: parentType }
        }).then(response => {
            if (response && response.success) {
                cache[parentType] = response.data; // Cache the result
                populateDropdown(selectId, parentType, response.data, selectedValue);
                return response.data;
            }
        }).catch(error => {
            console.error(`Error loading ${parentType} data:`, error);
        });
    }
    
    function populateDropdown(selectId, parentType, data, selectedValue = null) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        let options = `<option value="">Chọn ${getTypeDisplayName(parentType)}</option>`;
        
        data.forEach(item => {
            const selected = selectedValue && item.id == selectedValue ? 'selected' : '';
            const nameField = parentType === 'nganh' ? 'ten_nganh' : 
                            parentType === 'xuong' ? 'ten_xuong' :
                            parentType === 'line' ? 'ten_line' : 'ten_' + parentType;
            options += `<option value="${item.id}" ${selected}>${item[nameField]}</option>`;
        });
        
        select.innerHTML = options;
    }
    
    function loadKhuVucForXuong(xuongId, selectedValue = null) {
        CMMS.ajax('api.php', {
            method: 'GET',
            data: { action: 'get_khu_vuc_by_xuong', xuong_id: xuongId }
        }).then(response => {
            if (response && response.success) {
                const select = document.getElementById('khu_vuc_select');
                let options = '<option value="">Chọn khu vực</option>';
                
                response.data.forEach(item => {
                    const selected = selectedValue && item.id == selectedValue ? 'selected' : '';
                    options += `<option value="${item.id}" ${selected}>${item.ten_khu_vuc}</option>`;
                });
                
                select.innerHTML = options;
                select.disabled = false;
            }
        }).catch(error => {
            console.error('Error loading khu vuc:', error);
        });
    }
    
    // Public methods
    return {
        // Initialize module
        init: function() {
            console.log('Organization module initializing...');
            
            // Load initial data for active tab
            this.loadTabData('nganh');
            
        // Initialize module
        init: function() {
            console.log('Organization module initializing...');
            
            // Load initial data for active tab
            this.loadTabData('nganh');
            
            // Bind events
            this.bindEvents();
        },
        
        // Bind events
        bindEvents: function() {
            // Tab change events
            $('#managementTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', (e) => {
                const target = e.target.getAttribute('data-bs-target').replace('#', '').replace('-panel', '');
                currentType = target.replace('-', '_');
                this.loadTabData(currentType);
            });
            
            // Form submit
            $('#addEditForm').on('submit', (e) => {
                e.preventDefault();
                this.saveItem();
            });
        },
        
        // Load tab data
        loadTabData: loadTabData,
        
        // Show add modal
        showAddModal: function(type) {
            currentType = type;
            document.getElementById('modalTitle').innerHTML = `
                <i class="fas fa-plus me-2"></i>Thêm ${getTypeDisplayName(type)}
            `;
            document.getElementById('modalBody').innerHTML = getFormHTML(type);
            
            // Load parent data if needed
            loadParentDropdowns(type);
            
            // Reset form
            document.getElementById('addEditForm').reset();
            
            // Show modal
            new bootstrap.Modal(document.getElementById('addEditModal')).show();
        },
        
        // Edit item
        editItem: function(type, id) {
            currentType = type;
            document.getElementById('modalTitle').innerHTML = `
                <i class="fas fa-edit me-2"></i>Sửa ${getTypeDisplayName(type)}
            `;
            
            // Show loading in modal
            document.getElementById('modalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2">Đang tải dữ liệu...</div>
                </div>
            `;
            
            // Show modal first
            new bootstrap.Modal(document.getElementById('addEditModal')).show();
            
            // Load item data
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get', type: type, id: id }
            }).then(response => {
                if (response && response.success) {
                    document.getElementById('modalBody').innerHTML = getFormHTML(type, response.data);
                    loadParentDropdowns(type, response.data);
                } else {
                    document.getElementById('modalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Không thể tải dữ liệu: ${response?.message || 'Lỗi không xác định'}
                        </div>
                    `;
                }
            }).catch(error => {
                console.error('Edit item error:', error);
                document.getElementById('modalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Lỗi kết nối. Vui lòng thử lại.
                    </div>
                `;
            });
        },
        
        // Delete item
        deleteItem: function(type, id, name) {
            CMMS.confirm(
                `Bạn có chắc chắn muốn xóa "${name}"?\n\nLưu ý: Việc xóa có thể ảnh hưởng đến dữ liệu liên quan.`, 
                'Xác nhận xóa'
            ).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    CMMS.showLoading('body');
                    
                    CMMS.ajax('api.php', {
                        data: { action: 'delete', type: type, id: id }
                    }).then(response => {
                        CMMS.hideLoading('body');
                        
                        if (response && response.success) {
                            CMMS.showAlert('Xóa thành công', 'success');
                            
                            // Clear cache and reload data
                            cache[type] = null;
                            this.loadTabData(type);
                        } else {
                            CMMS.showAlert(response?.message || 'Lỗi xóa dữ liệu', 'error');
                        }
                    }).catch(error => {
                        CMMS.hideLoading('body');
                        console.error('Delete item error:', error);
                        CMMS.showAlert('Lỗi kết nối', 'error');
                    });
                }
            });
        },
        
        // Save item
        saveItem: function() {
            const formData = new FormData(document.getElementById('addEditForm'));
            formData.append('action', 'save');
            formData.append('type', currentType);
            
            // Show loading on submit button
            const submitBtn = document.querySelector('#addEditModal .btn-primary');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...';
            submitBtn.disabled = true;
            
            CMMS.ajax('api.php', {
                data: formData
            }).then(response => {
                // Restore button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (response && response.success) {
                    CMMS.showAlert('Lưu thành công', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addEditModal')).hide();
                    
                    // Clear cache and reload data
                    cache[currentType] = null;
                    // Also clear related caches
                    this.clearRelatedCaches(currentType);
                    this.loadTabData(currentType);
                } else {
                    CMMS.showAlert(response?.message || 'Lỗi lưu dữ liệu', 'error');
                }
            }).catch(error => {
                // Restore button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                console.error('Save item error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        },
        
        // Show structure tree
        showStructureTree: function() {
            // Show modal with loading
            document.getElementById('structureTreeContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <div class="mt-2">Đang tải cây cấu trúc...</div>
                </div>
            `;
            
            new bootstrap.Modal(document.getElementById('structureTreeModal')).show();
            
            // Load and display organization structure tree
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_structure_tree' }
            }).then(response => {
                if (response && response.success) {
                    document.getElementById('structureTreeContent').innerHTML = this.generateTreeHTML(response.data);
                } else {
                    document.getElementById('structureTreeContent').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Không thể tải cây cấu trúc: ${response?.message || 'Lỗi không xác định'}
                        </div>
                    `;
                }
            }).catch(error => {
                console.error('Structure tree error:', error);
                document.getElementById('structureTreeContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Lỗi tải cây cấu trúc. Vui lòng thử lại.
                    </div>
                `;
            });
        },
        
        // Generate tree HTML
        generateTreeHTML: function(data) {
            if (!data || data.length === 0) {
                return `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Chưa có dữ liệu cấu trúc tổ chức. Hãy thêm ngành, xưởng và các thành phần khác.
                    </div>
                `;
            }
            
            let html = '<div class="structure-tree">';
            
            data.forEach(nganh => {
                html += `
                    <div class="tree-item level-0">
                        <i class="fas fa-industry tree-icon"></i>
                        <strong>${nganh.ten_nganh}</strong>
                        <small class="text-muted ms-2">(${nganh.ma_nganh})</small>
                    </div>
                `;
                
                if (nganh.xuong && nganh.xuong.length > 0) {
                    html += '<div class="tree-node">';
                    nganh.xuong.forEach(xuong => {
                        html += `
                            <div class="tree-item level-1">
                                <i class="fas fa-building tree-icon"></i>
                                <strong>${xuong.ten_xuong}</strong>
                                <small class="text-muted ms-2">(${xuong.ma_xuong})</small>
                            </div>
                        `;
                        
                        if (xuong.khu_vuc && xuong.khu_vuc.length > 0) {
                            html += '<div class="tree-node">';
                            xuong.khu_vuc.forEach(kv => {
                                html += `
                                    <div class="tree-item level-2">
                                        <i class="fas fa-map-marked-alt tree-icon"></i>
                                        <strong>${kv.ten_khu_vuc}</strong>
                                        <span class="badge badge-sm bg-info ms-2">${kv.loai_khu_vuc === 'cong_nghe' ? 'Công nghệ' : 'Đóng gói'}</span>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }
                        
                        if (xuong.lines && xuong.lines.length > 0) {
                            html += '<div class="tree-node">';
                            xuong.lines.forEach(line => {
                                html += `
                                    <div class="tree-item level-3">
                                        <i class="fas fa-stream tree-icon"></i>
                                        <strong>${line.ten_line}</strong>
                                        <small class="text-muted ms-2">(${line.ma_line})</small>
                                    </div>
                                `;
                                
                                if (line.dong_may && line.dong_may.length > 0) {
                                    html += '<div class="tree-node">';
                                    line.dong_may.forEach(dm => {
                                        html += `
                                            <div class="tree-item level-4">
                                                <i class="fas fa-cogs tree-icon"></i>
                                                <strong>${dm.ten_dong_may}</strong>
                                                <small class="text-muted ms-2">(${dm.ma_dong_may})</small>
                                            </div>
                                        `;
                                        
                                        if (dm.cum_thiet_bi && dm.cum_thiet_bi.length > 0) {
                                            html += '<div class="tree-node">';
                                            dm.cum_thiet_bi.forEach(ctb => {
                                                html += `
                                                    <div class="tree-item level-5">
                                                        <i class="fas fa-layer-group tree-icon"></i>
                                                        <strong>${ctb.ten_cum}</strong>
                                                        <small class="text-muted ms-2">(${ctb.ma_cum})</small>
                                                    </div>
                                                `;
                                            });
                                            html += '</div>';
                                        }
                                    });
                                    html += '</div>';
                                }
                            });
                            html += '</div>';
                        }
                    });
                    html += '</div>';
                } else {
                    html += `
                        <div class="tree-node">
                            <div class="text-muted fst-italic">
                                <i class="fas fa-info-circle me-2"></i>
                                Chưa có xưởng nào trong ngành này
                            </div>
                        </div>
                    `;
                }
            });
            
            html += '</div>';
            return html;
        },
        
        // Export structure
        exportStructure: function() {
            CMMS.showAlert('Đang chuẩn bị file xuất...', 'info');
            
            try {
                // Create a form to submit for export
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'api.php';
                form.style.display = 'none';
                
                // Add action parameter
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'export_structure';
                form.appendChild(actionInput);
                
                // Add to DOM and submit
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
                
            } catch (error) {
                console.error('Export error:', error);
                CMMS.showAlert('Lỗi xuất dữ liệu', 'error');
            }
        },
        
        // Set active tab
        setActiveTab: function(type) {
            const tabButton = document.getElementById(type.replace('_', '-') + '-tab');
            if (tabButton) {
                const tab = new bootstrap.Tab(tabButton);
                tab.show();
            }
        },
        
        // Reload tab
        reloadTab: function(type) {
            cache[type] = null;
            this.loadTabData(type);
        },
        
        // Clear related caches when data changes
        clearRelatedCaches: function(changedType) {
            switch(changedType) {
                case 'nganh':
                    // When nganh changes, clear xuong cache
                    cache.xuong = null;
                    break;
                case 'xuong':
                    // When xuong changes, clear khu_vuc and line caches
                    cache.khu_vuc = null;
                    cache.line = null;
                    break;
                case 'line':
                    // When line changes, clear dong_may cache
                    cache.dong_may = null;
                    break;
                case 'dong_may':
                    // When dong_may changes, clear cum_thiet_bi cache
                    cache.cum_thiet_bi = null;
                    break;
            }
        },
        
        // Refresh all data
        refreshAll: function() {
            cache = {
                nganh: null,
                xuong: null,
                khu_vuc: null,
                line: null,
                dong_may: null,
                cum_thiet_bi: null
            };
            this.loadTabData(currentType);
        },
        
        // Get current state for debugging
        getState: function() {
            return {
                currentType,
                isLoading,
                cacheStatus: Object.keys(cache).reduce((acc, key) => {
                    acc[key] = cache[key] ? `${cache[key].length} items` : 'empty';
                    return acc;
                }, {})
            };
        }
    };
})();

// Auto-initialize when DOM is ready
function initOrganizationModule() {
    // Check if jQuery is available
    if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
        console.log('jQuery not ready, retrying...');
        setTimeout(initOrganizationModule, 100);
        return;
    }
    
    // Check if we're on organization index page
    if (window.location.pathname.includes('/organization/index.php')) {
        OrganizationModule.init();
    }
}

// Try multiple initialization methods
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOrganizationModule);
} else {
    initOrganizationModule();
}

// Also try with jQuery when available
if (typeof $ !== 'undefined') {
    $(document).ready(initOrganizationModule);
}
// Make module globally accessible
window.OrganizationModule = OrganizationModule;