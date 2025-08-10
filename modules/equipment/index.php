<?php
// modules/equipment/index.php - Danh sách thiết bị
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong', 'user', 'viewer']);

$page_title = 'Quản lý thiết bị';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quản lý thiết bị</h1>
            <p class="text-muted">Danh sách và thông tin thiết bị trong hệ thống</p>
        </div>
        <div>
            <?php if (hasPermission(['admin', 'to_truong'])): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Thêm thiết bị
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Xưởng</label>
                    <select class="form-select select2" id="filter_xuong" name="xuong">
                        <option value="">Tất cả xưởng</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Line sản xuất</label>
                    <select class="form-select select2" id="filter_line" name="line" disabled>
                        <option value="">Tất cả line</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tình trạng</label>
                    <select class="form-select" id="filter_tinh_trang" name="tinh_trang">
                        <option value="">Tất cả</option>
                        <option value="hoat_dong">Hoạt động</option>
                        <option value="bao_tri">Bảo trì</option>
                        <option value="hong">Hỏng</option>
                        <option value="ngung_hoat_dong">Ngừng hoạt động</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="filter_search" name="search" placeholder="ID, tên thiết bị...">
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

    <!-- Equipment List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách thiết bị</h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportData()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
                <button class="btn btn-outline-info btn-sm" onclick="showQRBatch()">
                    <i class="fas fa-qrcode me-2"></i>In QR hàng loạt
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="equipmentTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>ID thiết bị</th>
                            <th>Tên thiết bị</th>
                            <th>Vị trí</th>
                            <th>Tình trạng</th>
                            <th>Người chủ quản</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="equipmentTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav id="pagination" class="mt-3"></nav>
        </div>
    </div>
</div>

<!-- Equipment Detail Modal -->
<div class="modal fade" id="equipmentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="equipmentDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mã QR thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrCodeContent"></div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="printQR()">
                        <i class="fas fa-print me-2"></i>In QR
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;

$(document).ready(function() {
    loadXuongOptions();
    loadEquipmentData();
    
    // Filter form submit
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadEquipmentData();
    });
    
    // Xuong change event
    $('#filter_xuong').change(function() {
        const xuongId = $(this).val();
        loadLineOptions(xuongId);
    });
    
    // Select all checkbox
    $('#selectAll').change(function() {
        $('.equipment-checkbox').prop('checked', $(this).prop('checked'));
    });
});

// Load xưởng options
function loadXuongOptions() {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_xuong' }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Tất cả xưởng</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.ten_xuong}</option>`;
            });
            $('#filter_xuong').html(options);
        }
    });
}

// Load line options based on xuong
function loadLineOptions(xuongId) {
    const lineSelect = $('#filter_line');
    
    if (!xuongId) {
        lineSelect.prop('disabled', true).html('<option value="">Tất cả line</option>');
        return;
    }
    
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_lines', xuong_id: xuongId }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Tất cả line</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.ten_line}</option>`;
            });
            lineSelect.prop('disabled', false).html(options);
        }
    });
}

// Load equipment data
function loadEquipmentData() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'list');
    formData.append('page', currentPage);
    
    CMMS.showLoading('#equipmentTableBody');
    
    CMMS.ajax('api.php', {
        method: 'POST',
        data: formData
    }).then(response => {
        CMMS.hideLoading('#equipmentTableBody');
        
        if (response.success) {
            displayEquipmentData(response.data);
            displayPagination(response.pagination);
        } else {
            $('#equipmentTableBody').html('<tr><td colspan="7" class="text-center">Không có dữ liệu</td></tr>');
        }
    });
}

// Display equipment data
function displayEquipmentData(data) {
    let html = '';
    
    data.forEach(item => {
        const statusClass = getStatusClass(item.tinh_trang);
        const statusText = getStatusText(item.tinh_trang);
        
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
                        ${item.ten_xuong}<br>
                        ${item.ten_line}<br>
                        <span class="text-muted">${item.vi_tri || ''}</span>
                    </small>
                </td>
                <td>
                    <span class="badge ${statusClass}">${statusText}</span>
                </td>
                <td>
                    <small>${item.chu_quan_name || 'Chưa phân công'}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="viewDetail(${item.id})" title="Xem chi tiết">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="showQR('${item.id_thiet_bi}')" title="QR Code">
                            <i class="fas fa-qrcode"></i>
                        </button>
                        ${hasEditPermission() ? `
                        <button class="btn btn-outline-warning" onclick="editEquipment(${item.id})" title="Sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteEquipment(${item.id})" title="Xóa">
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

// Helper functions
function getStatusClass(status) {
    const classes = {
        'hoat_dong': 'bg-success',
        'bao_tri': 'bg-warning',
        'hong': 'bg-danger',
        'ngung_hoat_dong': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'hoat_dong': 'Hoạt động',
        'bao_tri': 'Bảo trì',
        'hong': 'Hỏng',
        'ngung_hoat_dong': 'Ngừng hoạt động'
    };
    return texts[status] || 'Không xác định';
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
        
        // Previous button
        if (pagination.has_previous) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Trước</a></li>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
        }
        
        // Next button
        if (pagination.has_next) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Tiếp</a></li>`;
        }
        
        html += '</ul>';
    }
    
    $('#pagination').html(html);
}

// Change page
function changePage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        loadEquipmentData();
    }
}

// Reset filter
function resetFilter() {
    document.getElementById('filterForm').reset();
    $('#filter_line').prop('disabled', true).html('<option value="">Tất cả line</option>');
    currentPage = 1;
    loadEquipmentData();
}

// View equipment detail
function viewDetail(id) {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'detail', id: id }
    }).then(response => {
        if (response.success) {
            $('#equipmentDetailContent').html(generateDetailHTML(response.data));
            $('#equipmentDetailModal').modal('show');
        } else {
            CMMS.showAlert(response.message, 'error');
        }
    });
}

// Generate detail HTML
function generateDetailHTML(data) {
    return `
        <div class="row">
            <div class="col-md-8">
                <table class="table table-borderless">
                    <tr><th width="30%">ID thiết bị:</th><td><strong>${data.id_thiet_bi}</strong></td></tr>
                    <tr><th>Tên thiết bị:</th><td>${data.ten_thiet_bi}</td></tr>
                    <tr><th>Vị trí:</th><td>${data.ten_xuong} - ${data.ten_line} - ${data.vi_tri || ''}</td></tr>
                    <tr><th>Ngành:</th><td>${data.nganh || ''}</td></tr>
                    <tr><th>Năm sản xuất:</th><td>${data.nam_san_xuat || ''}</td></tr>
                    <tr><th>Công suất:</th><td>${data.cong_suat || ''}</td></tr>
                    <tr><th>Tình trạng:</th><td><span class="badge ${getStatusClass(data.tinh_trang)}">${getStatusText(data.tinh_trang)}</span></td></tr>
                    <tr><th>Người chủ quản:</th><td>${data.chu_quan_name || 'Chưa phân công'}</td></tr>
                    <tr><th>Thông số kỹ thuật:</th><td>${data.thong_so_ky_thuat || ''}</td></tr>
                    <tr><th>Ghi chú:</th><td>${data.ghi_chu || ''}</td></tr>
                </table>
            </div>
            <div class="col-md-4">
                ${data.hinh_anh ? `<img src="${data.hinh_anh}" class="img-fluid rounded" alt="Hình ảnh thiết bị">` : '<div class="text-center text-muted"><i class="fas fa-image fa-3x"></i><br>Không có hình ảnh</div>'}
                
                <div class="mt-3 text-center">
                    <button class="btn btn-primary btn-sm" onclick="showQR('${data.id_thiet_bi}')">
                        <i class="fas fa-qrcode me-2"></i>Xem QR Code
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Show QR Code
function showQR(equipmentId) {
    const qrUrl = `https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=${encodeURIComponent(equipmentId)}`;
    
    $('#qrCodeContent').html(`
        <h6>Thiết bị: ${equipmentId}</h6>
        <img src="${qrUrl}" class="img-fluid" alt="QR Code">
        <p class="mt-2 text-muted">Quét mã này để xem thông tin thiết bị</p>
    `);
    
    $('#qrCodeModal').modal('show');
}

// Print QR
function printQR() {
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
}

// Edit equipment
function editEquipment(id) {
    window.location.href = `edit.php?id=${id}`;
}

// Delete equipment
function deleteEquipment(id) {
    CMMS.confirm('Bạn có chắc chắn muốn xóa thiết bị này?', 'Xác nhận xóa').then((result) => {
        if (result.isConfirmed) {
            CMMS.ajax('api.php', {
                data: { action: 'delete', id: id }
            }).then(response => {
                if (response.success) {
                    CMMS.showAlert('Xóa thiết bị thành công', 'success');
                    loadEquipmentData();
                } else {
                    CMMS.showAlert(response.message, 'error');
                }
            });
        }
    });
}

// Export data
function exportData() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'export');
    
    // Create download link
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
}

// Show QR Batch modal
function showQRBatch() {
    const selectedIds = $('.equipment-checkbox:checked').map(function() {
        return $(this).val();
    }).get();
    
    if (selectedIds.length === 0) {
        CMMS.showAlert('Vui lòng chọn ít nhất một thiết bị', 'warning');
        return;
    }
    
    // Generate batch QR codes
    let qrContent = '<div class="row">';
    selectedIds.forEach(id => {
        // Get equipment data for each selected item
        // This would need additional AJAX call to get equipment details
        qrContent += `<div class="col-md-3 mb-3 text-center">Loading...</div>`;
    });
    qrContent += '</div>';
    
    $('#qrCodeContent').html(qrContent);
    $('#qrCodeModal').modal('show');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
