<?php
// modules/maintenance/history.php - Maintenance History
require_once '../../config/database.php';
require_once '../../config/functions.php';

$page_title = 'Lịch sử bảo trì';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$equipment_filter = $_GET['equipment_id'] ?? '';
$maintenance_filter = $_GET['maintenance_id'] ?? '';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Lịch sử bảo trì</h1>
            <p class="text-muted">Theo dõi các hoạt động bảo trì đã thực hiện</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Thiết bị</label>
                    <select class="form-select select2" id="filter_equipment" name="equipment_id">
                        <option value="">Tất cả thiết bị</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Loại bảo trì</label>
                    <select class="form-select" name="loai_bao_tri">
                        <option value="">Tất cả</option>
                        <option value="dinh_ky">Định kỳ</option>
                        <option value="du_phong">Dự phòng</option>
                        <option value="sua_chua">Sửa chữa</option>
                        <option value="cap_cuu">Cấp cứu</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="trang_thai">
                        <option value="">Tất cả</option>
                        <option value="hoan_thanh">Hoàn thành</option>
                        <option value="chua_hoan_thanh">Chưa hoàn thành</option>
                        <option value="loi">Có lỗi</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" name="from_date" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" name="to_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- History List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Lịch sử bảo trì</h5>
            <div>
                <button class="btn btn-outline-success btn-sm" onclick="exportHistory()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="historyTable">
                    <thead>
                        <tr>
                            <th>Ngày thực hiện</th>
                            <th>Thiết bị</th>
                            <th>Công việc</th>
                            <th>Loại</th>
                            <th>Người thực hiện</th>
                            <th>Chi phí</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav id="pagination" class="mt-3"></nav>
        </div>
    </div>
</div>

<!-- History Detail Modal -->
<div class="modal fade" id="historyDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết bảo trì</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;

$(document).ready(function() {
    loadEquipmentOptions();
    loadHistoryData();
    
    // Set filters if specified
    const equipmentFilter = '<?= $equipment_filter ?>';
    const maintenanceFilter = '<?= $maintenance_filter ?>';
    
    if (equipmentFilter) {
        setTimeout(() => {
            $('#filter_equipment').val(equipmentFilter).trigger('change');
        }, 1000);
    }
    
    // Form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadHistoryData();
    });
});

// Load equipment options
function loadEquipmentOptions() {
    CMMS.ajax('../equipment/api.php', {
        method: 'GET',
        data: { action: 'list_simple' }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Tất cả thiết bị</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.id_thiet_bi} - ${item.ten_thiet_bi}</option>`;
            });
            $('#filter_equipment').html(options);
            
            // Set filter if specified
            const equipmentFilter = '<?= $equipment_filter ?>';
            if (equipmentFilter) {
                $('#filter_equipment').val(equipmentFilter).trigger('change');
            }
        }
    });
}

// Load history data
function loadHistoryData() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'history');
    formData.append('page', currentPage);
    
    CMMS.showLoading('#historyTableBody');
    
    CMMS.ajax('api.php', {
        method: 'POST',
        data: formData
    }).then(response => {
        CMMS.hideLoading('#historyTableBody');
        
        if (response.success) {
            displayHistoryData(response.data);
            displayPagination(response.pagination);
        } else {
            $('#historyTableBody').html('<tr><td colspan="8" class="text-center">Không có dữ liệu</td></tr>');
        }
    });
}

// Display history data
function displayHistoryData(data) {
    let html = '';
    
    data.forEach(item => {
        const statusClass = item.trang_thai === 'hoan_thanh' ? 'bg-success' : 
                          item.trang_thai === 'loi' ? 'bg-danger' : 'bg-warning';
        const statusText = item.trang_thai === 'hoan_thanh' ? 'Hoàn thành' : 
                          item.trang_thai === 'loi' ? 'Có lỗi' : 'Chưa hoàn thành';
        
        html += `
            <tr>
                <td>
                    <strong>${formatDate(item.ngay_thuc_hien)}</strong><br>
                    <small class="text-muted">${item.gio_bat_dau} - ${item.gio_ket_thuc || 'N/A'}</small>
                </td>
                <td>
                    <div>
                        <strong>${item.id_thiet_bi}</strong><br>
                        <small class="text-muted">${item.ten_thiet_bi}</small>
                    </div>
                </td>
                <td>
                    <div>
                        <strong>${item.ten_cong_viec}</strong><br>
                        <small class="text-muted">${item.mo_ta ? item.mo_ta.substring(0, 50) + '...' : ''}</small>
                    </div>
                </td>
                <td>
                    <span class="badge ${getTypeClass(item.loai_bao_tri)}">${getTypeText(item.loai_bao_tri)}</span>
                </td>
                <td>${item.nguoi_thuc_hien_name}</td>
                <td class="text-end">
                    <strong>${formatCurrency(item.chi_phi)}</strong>
                </td>
                <td>
                    <span class="badge ${statusClass}">${statusText}</span>
                </td>
                <td>
                    <button class="btn btn-outline-info btn-sm" onclick="viewHistoryDetail(${item.id})" title="Xem chi tiết">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    if (html === '') {
        html = '<tr><td colspan="8" class="text-center text-muted">Không có dữ liệu lịch sử bảo trì</td></tr>';
    }
    
    $('#historyTableBody').html(html);
}

// Helper functions (reuse from main maintenance file)
function getTypeClass(type) {
    const classes = {
        'dinh_ky': 'bg-primary',
        'du_phong': 'bg-info',
        'sua_chua': 'bg-warning',
        'cap_cuu': 'bg-danger'
    };
    return classes[type] || 'bg-secondary';
}

function getTypeText(type) {
    const texts = {
        'dinh_ky': 'Định kỳ',
        'du_phong': 'Dự phòng',
        'sua_chua': 'Sửa chữa',
        'cap_cuu': 'Cấp cứu'
    };
    return texts[type] || 'Không xác định';
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

function formatCurrency(amount) {
    if (!amount) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', { 
        style: 'currency', 
        currency: 'VND' 
    }).format(amount);
}

// View history detail
function viewHistoryDetail(id) {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'history_detail', id: id }
    }).then(response => {
        if (response.success) {
            $('#historyDetailContent').html(generateHistoryDetailHTML(response.data));
            $('#historyDetailModal').modal('show');
        } else {
            CMMS.showAlert('Không thể tải chi tiết lịch sử bảo trì', 'error');
        }
    });
}

// Generate history detail HTML
function generateHistoryDetailHTML(data) {
    let materialsHtml = '';
    if (data.vat_tu_su_dung) {
        try {
            const materials = JSON.parse(data.vat_tu_su_dung);
            if (materials.length > 0) {
                materialsHtml = `
                    <h6 class="mt-4">Vật tư đã sử dụng:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Vật tư</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr>
                            </thead>
                            <tbody>
                                ${materials.map(item => `
                                    <tr>
                                        <td>${item.ten_vat_tu || item.id_vat_tu}</td>
                                        <td>${item.so_luong}</td>
                                        <td>${formatCurrency(item.don_gia)}</td>
                                        <td>${formatCurrency(item.so_luong * item.don_gia)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
        } catch (e) {
            // Ignore JSON parse errors
        }
    }
    
    let imagesHtml = '';
    if (data.hinh_anh) {
        const images = data.hinh_anh.split(',');
        imagesHtml = `
            <h6 class="mt-4">Hình ảnh:</h6>
            <div class="row">
                ${images.map(img => `
                    <div class="col-md-3 mb-2">
                        <img src="../../${img}" class="img-fluid rounded" onclick="showImageModal('../../${img}')">
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    return `
        <div class="row">
            <div class="col-md-8">
                <h5 class="mb-3">${data.ten_cong_viec}</h5>
                
                <table class="table table-borderless">
                    <tr><th width="30%">Thiết bị:</th><td><strong>${data.id_thiet_bi} - ${data.ten_thiet_bi}</strong></td></tr>
                    <tr><th>Ngày thực hiện:</th><td>${formatDate(data.ngay_thuc_hien)}</td></tr>
                    <tr><th>Thời gian:</th><td>${data.gio_bat_dau} - ${data.gio_ket_thuc || 'N/A'}</td></tr>
                    <tr><th>Loại bảo trì:</th><td><span class="badge ${getTypeClass(data.loai_bao_tri)}">${getTypeText(data.loai_bao_tri)}</span></td></tr>
                    <tr><th>Người thực hiện:</th><td>${data.nguoi_thuc_hien_name}</td></tr>
                    <tr><th>Chi phí:</th><td><strong class="text-success">${formatCurrency(data.chi_phi)}</strong></td></tr>
                    <tr><th>Trạng thái:</th><td><span class="badge ${data.trang_thai === 'hoan_thanh' ? 'bg-success' : (data.trang_thai === 'loi' ? 'bg-danger' : 'bg-warning')}">${data.trang_thai === 'hoan_thanh' ? 'Hoàn thành' : (data.trang_thai === 'loi' ? 'Có lỗi' : 'Chưa hoàn thành')}</span></td></tr>
                </table>
                
                ${data.mo_ta ? `
                <div class="mt-3">
                    <h6>Mô tả công việc:</h6>
                    <p class="text-muted">${data.mo_ta}</p>
                </div>
                ` : ''}
                
                ${data.ket_qua ? `
                <div class="mt-3">
                    <h6>Kết quả:</h6>
                    <p class="text-muted">${data.ket_qua}</p>
                </div>
                ` : ''}
                
                ${data.ghi_chu ? `
                <div class="mt-3">
                    <h6>Ghi chú:</h6>
                    <p class="text-muted">${data.ghi_chu}</p>
                </div>
                ` : ''}
                
                ${materialsHtml}
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Thông tin bổ sung</h6>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            Ngày tạo: ${formatDate(data.created_at)}<br>
                            ${data.id_ke_hoach ? `Thuộc kế hoạch: #${data.id_ke_hoach}` : 'Bảo trì đột xuất'}
                        </small>
                    </div>
                </div>
                
                ${imagesHtml}
            </div>
        </div>
    `;
}

// Display pagination for history
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

// Change page
function changePage(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        loadHistoryData();
    }
}

// Export history
function exportHistory() {
    const formData = new FormData(document.getElementById('filterForm'));
    formData.append('action', 'export_history');
    
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

// Show image modal
function showImageModal(imageUrl) {
    Swal.fire({
        imageUrl: imageUrl,
        imageAlt: 'Hình ảnh bảo trì',
        showCloseButton: true,
        showConfirmButton: false,
        width: 'auto',
        padding: '1rem'
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>