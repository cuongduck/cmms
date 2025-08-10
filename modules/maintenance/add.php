<?php
// modules/maintenance/add.php - Add Maintenance Plan
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong']);

$page_title = 'Tạo kế hoạch bảo trì';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Get equipment filter if specified
$equipment_filter = $_GET['equipment_id'] ?? '';

// Lấy danh sách users cho dropdown người thực hiện
$users_stmt = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 AND role IN ('user', 'to_truong') ORDER BY full_name");
$users = $users_stmt->fetchAll();
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Tạo kế hoạch bảo trì</h1>
            <p class="text-muted">Lập kế hoạch bảo trì định kỳ hoặc sửa chữa thiết bị</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
        </div>
    </div>

    <!-- Maintenance Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Thông tin kế hoạch bảo trì</h5>
                </div>
                <div class="card-body">
                    <form id="maintenanceForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Thiết bị <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_thiet_bi" id="id_thiet_bi" required>
                                        <option value="">Chọn thiết bị</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Loại bảo trì <span class="text-danger">*</span></label>
                                    <select class="form-select" name="loai_bao_tri" id="loai_bao_tri" required>
                                        <option value="">Chọn loại</option>
                                        <option value="dinh_ky">Định kỳ</option>
                                        <option value="du_phong">Dự phòng</option>
                                        <option value="sua_chua">Sửa chữa</option>
                                        <option value="cap_cuu">Cấp cứu</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tên kế hoạch <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ten_ke_hoach" required 
                                   placeholder="Ví dụ: Bảo trì định kỳ máy trộn bột">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả công việc</label>
                            <textarea class="form-control" name="mo_ta" rows="3" 
                                      placeholder="Mô tả chi tiết các công việc cần thực hiện..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Chu kỳ (ngày)</label>
                                    <input type="number" class="form-control" name="chu_ky_ngay" id="chu_ky_ngay"
                                           min="1" placeholder="30">
                                    <div class="form-text">Chỉ áp dụng cho bảo trì định kỳ</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Ngày thực hiện <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="ngay_bao_tri_tiep_theo" required 
                                           min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Ưu tiên <span class="text-danger">*</span></label>
                                    <select class="form-select" name="uu_tien" required>
                                        <option value="trung_binh" selected>Trung bình</option>
                                        <option value="thap">Thấp</option>
                                        <option value="cao">Cao</option>
                                        <option value="khan_cap">Khẩn cấp</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Người thực hiện</label>
                                    <select class="form-select select2" name="nguoi_thuc_hien">
                                        <option value="">Chọn người thực hiện</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="history.back()">
                                <i class="fas fa-times me-2"></i>Hủy
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Tạo kế hoạch
                            </button>
                                                </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Info Panel -->
        <div class="col-lg-4">
            <!-- Equipment Info -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Thông tin thiết bị
                    </h6>
                </div>
                <div class="card-body" id="equipmentInfo">
                    <div class="text-center text-muted">
                        <i class="fas fa-cogs fa-3x mb-3 opacity-25"></i>
                        <p>Chọn thiết bị để xem thông tin</p>
                    </div>
                </div>
            </div>
            
            <!-- Maintenance History -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>Lịch sử bảo trì
                    </h6>
                </div>
                <div class="card-body" id="maintenanceHistory">
                    <div class="text-center text-muted">
                        <i class="fas fa-clock fa-3x mb-3 opacity-25"></i>
                        <p>Chọn thiết bị để xem lịch sử</p>
                    </div>
                </div>
            </div>
            
            <!-- Tips -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Hướng dẫn
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Định kỳ:</strong> Bảo trì theo chu kỳ cố định
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Dự phòng:</strong> Bảo trì dự phòng khi cần
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Sửa chữa:</strong> Khắc phục sự cố
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Cấp cứu:</strong> Xử lý khẩn cấp
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadEquipmentOptions();
    
    // Set equipment filter if specified
    const equipmentFilter = '<?= $equipment_filter ?>';
    if (equipmentFilter) {
        setTimeout(() => {
            $('#id_thiet_bi').val(equipmentFilter).trigger('change');
        }, 1000);
    }
    
    // Form submission
    $('#maintenanceForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
    
    // Equipment change event
    $('#id_thiet_bi').change(function() {
        const equipmentId = $(this).val();
        if (equipmentId) {
            loadEquipmentInfo(equipmentId);
            loadMaintenanceHistory(equipmentId);
            suggestMaintenanceName(equipmentId);
        } else {
            resetEquipmentInfo();
        }
    });
    
    // Maintenance type change event
    $('#loai_bao_tri').change(function() {
        const type = $(this).val();
        toggleCycleField(type);
        updatePriorityBasedOnType(type);
    });
});

// Load equipment options
function loadEquipmentOptions() {
    CMMS.ajax('../equipment/api.php', {
        method: 'GET',
        data: { action: 'list_simple' }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Chọn thiết bị</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}" data-code="${item.id_thiet_bi}">${item.id_thiet_bi} - ${item.ten_thiet_bi}</option>`;
            });
            $('#id_thiet_bi').html(options);
            
            // Set filter if specified
            const equipmentFilter = '<?= $equipment_filter ?>';
            if (equipmentFilter) {
                $('#id_thiet_bi').val(equipmentFilter).trigger('change');
            }
        }
    });
}

// Load equipment info
function loadEquipmentInfo(equipmentId) {
    CMMS.ajax('../equipment/api.php', {
        method: 'GET',
        data: { action: 'detail', id: equipmentId }
    }).then(response => {
        if (response.success) {
            displayEquipmentInfo(response.data);
        }
    });
}

// Display equipment info
function displayEquipmentInfo(data) {
    const statusClass = getStatusClass(data.tinh_trang);
    const statusText = getStatusText(data.tinh_trang);
    
    $('#equipmentInfo').html(`
        <div class="text-center mb-3">
            ${data.hinh_anh ? 
                `<img src="../../${data.hinh_anh}" class="img-fluid rounded" style="max-height: 150px;">` : 
                '<div class="bg-light rounded p-3"><i class="fas fa-cogs fa-3x text-muted"></i></div>'
            }
        </div>
        
        <table class="table table-borderless table-sm">
            <tr><th>ID:</th><td><strong>${data.id_thiet_bi}</strong></td></tr>
            <tr><th>Tên:</th><td>${data.ten_thiet_bi}</td></tr>
            <tr><th>Vị trí:</th><td>${data.ten_xuong} - ${data.ten_line}</td></tr>
            <tr><th>Trạng thái:</th><td><span class="badge ${statusClass}">${statusText}</span></td></tr>
            <tr><th>Năm SX:</th><td>${data.nam_san_xuat || 'N/A'}</td></tr>
            <tr><th>Công suất:</th><td>${data.cong_suat || 'N/A'}</td></tr>
        </table>
    `);
}

// Load maintenance history
function loadMaintenanceHistory(equipmentId) {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'equipment_history', equipment_id: equipmentId }
    }).then(response => {
        if (response.success && response.data.length > 0) {
            displayMaintenanceHistory(response.data);
        } else {
            $('#maintenanceHistory').html(`
                <div class="text-center text-muted">
                    <i class="fas fa-clock fa-2x mb-2 opacity-50"></i>
                    <p>Chưa có lịch sử bảo trì</p>
                </div>
            `);
        }
    });
}

// Display maintenance history
function displayMaintenanceHistory(data) {
    let html = '<div class="timeline">';
    
    data.slice(0, 3).forEach(item => {
        html += `
            <div class="timeline-item mb-3">
                <div class="timeline-marker bg-${item.trang_thai === 'hoan_thanh' ? 'success' : 'warning'}"></div>
                <div class="timeline-content">
                    <h6 class="mb-1">${item.ten_cong_viec}</h6>
                    <small class="text-muted">
                        ${formatDate(item.ngay_thuc_hien)} - ${item.nguoi_thuc_hien_name}
                    </small>
                    <br>
                    <span class="badge ${item.trang_thai === 'hoan_thanh' ? 'bg-success' : 'bg-warning'}">
                        ${item.trang_thai === 'hoan_thanh' ? 'Hoàn thành' : 'Chưa hoàn thành'}
                    </span>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    if (data.length > 3) {
        html += `<small class="text-muted">Và ${data.length - 3} lần bảo trì khác...</small>`;
    }
    
    $('#maintenanceHistory').html(html);
}

// Suggest maintenance name based on equipment and type
function suggestMaintenanceName(equipmentId) {
    const equipmentOption = $(`#id_thiet_bi option[value="${equipmentId}"]`);
    const equipmentCode = equipmentOption.data('code');
    const equipmentName = equipmentOption.text().split(' - ')[1];
    
    // Update maintenance name suggestion when type changes
    $('#loai_bao_tri').off('change.suggest').on('change.suggest', function() {
        const type = $(this).val();
        const currentName = $('input[name="ten_ke_hoach"]').val();
        
        if (!currentName) { // Only suggest if field is empty
            let suggestedName = '';
            switch (type) {
                case 'dinh_ky':
                    suggestedName = `Bảo trì định kỳ ${equipmentName} (${equipmentCode})`;
                    break;
                case 'du_phong':
                    suggestedName = `Bảo trì dự phòng ${equipmentName} (${equipmentCode})`;
                    break;
                case 'sua_chua':
                    suggestedName = `Sửa chữa ${equipmentName} (${equipmentCode})`;
                    break;
                case 'cap_cuu':
                    suggestedName = `Sửa chữa khẩn cấp ${equipmentName} (${equipmentCode})`;
                    break;
            }
            $('input[name="ten_ke_hoach"]').val(suggestedName);
        }
    });
}

// Toggle cycle field based on maintenance type
function toggleCycleField(type) {
    const cycleField = $('#chu_ky_ngay');
    if (type === 'dinh_ky') {
        cycleField.prop('disabled', false).attr('required', true);
        cycleField.closest('.mb-3').find('.form-text').text('Nhập chu kỳ bảo trì (ngày)');
    } else {
        cycleField.prop('disabled', true).removeAttr('required').val('');
        cycleField.closest('.mb-3').find('.form-text').text('Chỉ áp dụng cho bảo trì định kỳ');
    }
}

// Update priority based on maintenance type
function updatePriorityBasedOnType(type) {
    const priorityField = $('select[name="uu_tien"]');
    const currentPriority = priorityField.val();
    
    // Only auto-update if current priority is default
    if (currentPriority === 'trung_binh') {
        switch (type) {
            case 'cap_cuu':
                priorityField.val('khan_cap');
                break;
            case 'sua_chua':
                priorityField.val('cao');
                break;
            case 'dinh_ky':
            case 'du_phong':
                priorityField.val('trung_binh');
                break;
        }
    }
}

// Reset equipment info
function resetEquipmentInfo() {
    $('#equipmentInfo').html(`
        <div class="text-center text-muted">
            <i class="fas fa-cogs fa-3x mb-3 opacity-25"></i>
            <p>Chọn thiết bị để xem thông tin</p>
        </div>
    `);
    
    $('#maintenanceHistory').html(`
        <div class="text-center text-muted">
            <i class="fas fa-clock fa-3x mb-3 opacity-25"></i>
            <p>Chọn thiết bị để xem lịch sử</p>
        </div>
    `);
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

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

// Submit form
function submitForm() {
    const formData = new FormData(document.getElementById('maintenanceForm'));
    formData.append('action', 'create');
    
    CMMS.ajax('api.php', {
        data: formData
    }).then(response => {
        if (response.success) {
            CMMS.showAlert('Tạo kế hoạch bảo trì thành công!', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1500);
        } else {
            CMMS.showAlert(response.message, 'error');
        }
    });
}
</script>

<style>
/* Timeline styles for maintenance history */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 8px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #e9ecef;
}

.timeline-content {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #28a745;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
    