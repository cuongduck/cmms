<?php
// modules/bom/index.php - BOM Management
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong', 'user', 'viewer']);

$page_title = 'Quản lý BOM thiết bị';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Get equipment filter if specified
$equipment_filter = $_GET['equipment_id'] ?? '';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quản lý BOM thiết bị</h1>
            <p class="text-muted">Danh mục vật tư và linh kiện cho từng thiết bị</p>
        </div>
        <div>
            <?php if (hasPermission(['admin', 'to_truong'])): ?>
            <button class="btn btn-success me-2" onclick="importBOM()">
                <i class="fas fa-file-import me-2"></i>Import BOM
            </button>
            <button class="btn btn-primary" onclick="addBOMItem()">
                <i class="fas fa-plus me-2"></i>Thêm vật tư
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Thiết bị</label>
                    <select class="form-select select2" id="filter_equipment" name="equipment_id">
                        <option value="">Tất cả thiết bị</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dòng máy</label>
                    <select class="form-select select2" id="filter_dong_may" name="dong_may_id">
                        <option value="">Tất cả dòng máy</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Chủng loại vật tư</label>
                    <select class="form-select" id="filter_chung_loai" name="chung_loai">
                        <option value="">Tất cả</option>
                        <option value="linh_kien">Linh kiện</option>
                        <option value="vat_tu">Vật tư</option>
                        <option value="cong_cu">Công cụ</option>
                        <option value="hoa_chat">Hóa chất</option>
                        <option value="khac">Khác</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="filter_search" name="search" placeholder="Mã, tên vật tư...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i>Lọc
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="resetFilter()">
                        <i class="fas fa-times me-2"></i>Xóa bộ lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- BOM List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách BOM thiết bị</h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportBOM()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
                <button class="btn btn-outline-info btn-sm" onclick="printBOM()">
                    <i class="fas fa-print me-2"></i>In BOM
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="bomTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Thiết bị</th>
                            <th>Dòng máy</th>
                            <th>Mã vật tư</th>
                            <th>Tên vật tư</th>
                            <th>Số lượng</th>
                            <th>ĐVT</th>
                            <th>Đơn giá</th>
                            <th>Thành tiền</th>
                            <th>Chủng loại</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="bomTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav id="pagination" class="mt-3"></nav>
        </div>
    </div>
</div>

<!-- Add/Edit BOM Modal -->
<div class="modal fade" id="bomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bomModalTitle">Thêm vật tư vào BOM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bomForm">
                    <input type="hidden" id="bom_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Thiết bị <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="equipment_id" name="id_thiet_bi" required>
                                    <option value="">Chọn thiết bị</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Dòng máy</label>
                                <select class="form-select select2" id="dong_may_id" name="id_dong_may">
                                    <option value="">Chọn dòng máy</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Vật tư <span class="text-danger">*</span></label>
                                <select class="form-select select2" id="vat_tu_id" name="id_vat_tu" required>
                                    <option value="">Chọn vật tư</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Số lượng <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="so_luong" name="so_luong" 
                                       step="0.001" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea class="form-control" id="ghi_chu" name="ghi_chu" rows="2" 
                                  placeholder="Ghi chú về vị trí lắp đặt, tần suất thay thế..."></textarea>
                    </div>

                    <!-- Vật tư info display -->
                    <div id="vatTuInfo" class="alert alert-info" style="display: none;">
                        <h6>Thông tin vật tư:</h6>
                        <div id="vatTuDetails"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveBOM()">
                    <i class="fas fa-save me-2"></i>Lưu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- BOM Detail Modal -->
<div class="modal fade" id="bomDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết BOM thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bomDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="printDetailBOM()">
                    <i class="fas fa-print me-2"></i>In BOM
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import BOM Modal -->
<div class="modal fade" id="importBOMModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import BOM từ Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">File Excel</label>
                        <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls" required>
                        <div class="form-text">
                            Định dạng: Excel (.xlsx, .xls). 
                            <a href="api.php?action=download_template" target="_blank">Tải template mẫu</a>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Thiết bị áp dụng</label>
                        <select class="form-select select2" name="equipment_id" required>
                            <option value="">Chọn thiết bị</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="replace_existing" id="replaceExisting">
                        <label class="form-check-label" for="replaceExisting">
                            Thay thế BOM hiện tại (nếu có)
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="processImport()">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Đợi jQuery và CMMS sẵn sàng
(function() {
    function initBOM() {
        console.log('Initializing BOM module...');
        
        if (typeof $ === 'undefined') {
            console.log('jQuery not ready, retrying...');
            setTimeout(initBOM, 100);
            return;
        }
        
        if (typeof CMMS === 'undefined') {
            console.log('CMMS not ready, retrying...');
            setTimeout(initBOM, 100);
            return;
        }
        
        console.log('BOM module loaded successfully');
        
        // Module variables
        let currentPage = 1;
        let totalPages = 1;
        let editingBOMId = null;
        
        // Initialize when ready
        $(document).ready(function() {
            loadEquipmentOptions();
            loadVatTuOptions();
            
            // Set equipment filter if specified
            const equipmentFilter = '<?= $equipment_filter ?>';
            if (equipmentFilter) {
                setTimeout(function() {
                    $('#filter_equipment').val(equipmentFilter).trigger('change');
                    loadBOMData();
                }, 1000);
            }
            
            // Form submission
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                currentPage = 1;
                loadBOMData();
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
        });
        
        // Load equipment options
        function loadEquipmentOptions() {
            CMMS.ajax('../equipment/api.php', {
                method: 'GET',
                data: { action: 'list_simple' }
            }).then(response => {
                if (response && response.success) {
                    console.log('Equipment loaded:', response.data);
                    
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
                    
                    // Set filter if specified
                    const equipmentFilter = '<?= $equipment_filter ?>';
                    if (equipmentFilter) {
                        $('#filter_equipment').val(equipmentFilter).trigger('change');
                    }
                } else {
                    console.error('Equipment load failed:', response);
                    CMMS.showAlert('Không thể tải danh sách thiết bị', 'error');
                }
            }).catch(error => {
                console.error('Equipment AJAX error:', error);
                CMMS.showAlert('Lỗi kết nối khi tải thiết bị', 'error');
            });
        }
        
        // Load dong may options
        function loadDongMayOptions(equipmentId) {
            const dongMaySelect = $('#dong_may_id');
            
            if (!equipmentId) {
                dongMaySelect.html('<option value="">Chọn dòng máy</option>');
                return;
            }
            
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_dong_may_by_equipment', equipment_id: equipmentId }
            }).then(response => {
                if (response && response.success) {
                    let options = '<option value="">Chọn dòng máy</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}">${item.ten_dong_may}</option>`;
                    });
                    dongMaySelect.html(options);
                } else {
                    console.error('Load dong may failed:', response);
                }
            }).catch(error => {
                console.error('Load dong may error:', error);
            });
        }
        
        // Load vat tu options
        function loadVatTuOptions() {
            CMMS.ajax('api.php', {
                method: 'GET',
                data: { action: 'get_vat_tu' }
            }).then(response => {
                if (response && response.success) {
                    let options = '<option value="">Chọn vật tư</option>';
                    response.data.forEach(item => {
                        options += `<option value="${item.id}" data-gia="${item.gia}" data-dvt="${item.dvt}" data-chung-loai="${item.chung_loai}">
                            ${item.ma_item} - ${item.ten_vat_tu}
                        </option>`;
                    });
                    $('#vat_tu_id').html(options);
                } else {
                    console.error('Load vat tu failed:', response);
                }
            }).catch(error => {
                console.error('Load vat tu error:', error);
            });
        }
        
        // Show vat tu info
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
                    <div class="col-md-4"><strong>Đơn giá:</strong> ${formatCurrency(gia)}</div>
                    <div class="col-md-4"><strong>Chủng loại:</strong> ${getChungLoaiText(chungLoai)}</div>
                </div>
            `);
            
            $('#vatTuInfo').show();
        }
        
        // Load BOM data
        function loadBOMData() {
            const formData = new FormData(document.getElementById('filterForm'));
            formData.append('action', 'list');
            formData.append('page', currentPage);
            
            console.log('Loading BOM data, page:', currentPage);
            CMMS.showLoading('#bomTableBody');
            
            CMMS.ajax('api.php', {
                method: 'POST',
                data: formData
            }).then(response => {
                console.log('BOM response:', response);
                CMMS.hideLoading('#bomTableBody');
                
                if (response && response.success) {
                    displayBOMData(response.data);
                    displayPagination(response.pagination);
                } else {
                    console.error('BOM error:', response);
                    $('#bomTableBody').html('<tr><td colspan="11" class="text-center text-danger">Lỗi tải dữ liệu: ' + (response ? response.message : 'Không có response') + '</td></tr>');
                }
            }).catch(error => {
                console.error('BOM AJAX error:', error);
                CMMS.hideLoading('#bomTableBody');
                $('#bomTableBody').html('<tr><td colspan="11" class="text-center text-danger">Lỗi kết nối API</td></tr>');
            });
        }
        
        // Display BOM data
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
                                <button class="btn btn-sm btn-outline-primary" onclick="viewEquipmentBOM(${equipment.id_thiet_bi_actual || equipment.id_thiet_bi})">
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
                            <td class="text-end">${formatNumber(item.so_luong)}</td>
                            <td>${item.dvt}</td>
                            <td class="text-end">${formatCurrency(item.gia)}</td>
                            <td class="text-end"><strong>${formatCurrency(thanhTien)}</strong></td>
                            <td><span class="badge ${getChungLoaiBadge(item.chung_loai)}">${getChungLoaiText(item.chung_loai)}</span></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    ${hasEditPermission() ? `
                                    <button class="btn btn-outline-warning" onclick="editBOM(${item.id})" title="Sửa">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteBOM(${item.id})" title="Xóa">
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
        
        // Helper functions
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
        
        function formatCurrency(amount) {
            if (!amount) return '0 ₫';
            return new Intl.NumberFormat('vi-VN', { 
                style: 'currency', 
                currency: 'VND' 
            }).format(amount);
        }
        
        function formatNumber(number) {
            if (!number) return '0';
            return new Intl.NumberFormat('vi-VN', { 
                minimumFractionDigits: 0,
                maximumFractionDigits: 3 
            }).format(number);
        }
        
        function hasEditPermission() {
            return ['admin', 'to_truong'].includes(CMMS.userRole);
        }
        
        // Display pagination
        function displayPagination(pagination) {
            totalPages = pagination.total_pages;
            currentPage = pagination.current_page;
            
            let html = '';
            
            if (totalPages > 1) {
                html += '<ul class="pagination justify-content-center">';
                
                if (pagination.has_previous) {
                    html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Trước</a></li>`;
                }
                
                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(totalPages, currentPage + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    const activeClass = i === currentPage ? 'active' : '';
                    html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
                }
                
                if (pagination.has_next) {
                    html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Tiếp</a></li>`;
                }
                
                html += '</ul>';
            }
            
            $('#pagination').html(html);
        }
        
        // Generate BOM detail HTML
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
                        <td class="text-end">${formatNumber(item.so_luong)}</td>
                        <td>${item.dvt}</td>
                        <td class="text-end">${formatCurrency(item.gia)}</td>
                        <td class="text-end">${formatCurrency(thanhTien)}</td>
                        <td>${item.ghi_chu || ''}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="7" class="text-end">Tổng giá trị BOM:</th>
                                <th class="text-end">${formatCurrency(totalValue)}</th>
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
                                <strong>${stat.count} items - ${formatCurrency(stat.value)}</strong>
                            </div>
                        </div>
                    `;
                });
                statsHtml += '</div>';
                
                $('#bomStats').html(statsHtml);
            }, 100);
            
            return html;
        }
        
        // Global functions (accessible from onclick)
        window.changePage = function(page) {
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                loadBOMData();
            }
        };
        
        window.resetFilter = function() {
            document.getElementById('filterForm').reset();
            currentPage = 1;
            loadBOMData();
        };
        
        window.addBOMItem = function() {
            editingBOMId = null;
            $('#bomModalTitle').text('Thêm vật tư vào BOM');
            $('#bomForm')[0].reset();
            $('#vatTuInfo').hide();
            $('#bomModal').modal('show');
        };
        
        window.editBOM = function(id) {
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
        };
        
        window.saveBOM = function() {
            const formData = new FormData(document.getElementById('bomForm'));
            formData.append('action', editingBOMId ? 'update' : 'create');
            
            CMMS.ajax('api.php', {
                data: formData
            }).then(response => {
                if (response && response.success) {
                    CMMS.showAlert(response.message, 'success');
                    $('#bomModal').modal('hide');
                    loadBOMData();
                } else {
                    CMMS.showAlert(response ? response.message : 'Lỗi lưu BOM', 'error');
                }
            }).catch(error => {
                console.error('Save BOM error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        };
        
        window.deleteBOM = function(id) {
            CMMS.confirm('Bạn có chắc chắn muốn xóa vật tư này khỏi BOM?', 'Xác nhận xóa').then((result) => {
                if (result.isConfirmed) {
                    CMMS.ajax('api.php', {
                        data: { action: 'delete', id: id }
                    }).then(response => {
                        if (response && response.success) {
                            CMMS.showAlert('Xóa vật tư thành công', 'success');
                            loadBOMData();
                        } else {
                            CMMS.showAlert(response ? response.message : 'Lỗi xóa BOM', 'error');
                        }
                    }).catch(error => {
                        console.error('Delete BOM error:', error);
                        CMMS.showAlert('Lỗi kết nối', 'error');
                    });
                }
            });
        };
        
        window.viewEquipmentBOM = function(equipmentId) {
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
        };
        
        window.importBOM = function() {
            loadEquipmentOptions();
            $('#importBOMModal').modal('show');
        };
        
        window.processImport = function() {
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
                    loadBOMData();
                } else {
                    CMMS.showAlert(response ? response.message : 'Lỗi import BOM', 'error');
                }
            }).catch(error => {
                console.error('Import BOM error:', error);
                CMMS.showAlert('Lỗi kết nối', 'error');
            });
        };
        
        window.exportBOM = function() {
            const formData = new FormData(document.getElementById('filterForm'));
            formData.append('action', 'export');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api.php';
            
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        };
        
        window.printBOM = function() {
            const selectedIds = $('.bom-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                CMMS.showAlert('Vui lòng chọn ít nhất một item để in', 'warning');
                return;
            }
            
            const printUrl = `print.php?ids=${selectedIds.join(',')}`;
            window.open(printUrl, '_blank');
        };
        
        window.printDetailBOM = function() {
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
        };
    }
    
    // Start initialization
    initBOM();
})();
</script>

<?php require_once '../../includes/footer.php'; ?>