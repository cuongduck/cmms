<?php
// modules/equipment/add.php - Thêm thiết bị mới
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong']);

$page_title = 'Thêm thiết bị mới';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Lấy danh sách users cho dropdown người chủ quản
$users_stmt = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
$users = $users_stmt->fetchAll();
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Thêm thiết bị mới</h1>
            <p class="text-muted">Nhập thông tin thiết bị vào hệ thống</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
        </div>
    </div>

    <!-- Equipment Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Thông tin thiết bị</h5>
                </div>
                <div class="card-body">
                    <form id="equipmentForm" enctype="multipart/form-data">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ID thiết bị <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="id_thiet_bi" required 
                                           placeholder="Ví dụ: TB001">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên thiết bị <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="ten_thiet_bi" required 
                                           placeholder="Tên thiết bị">
                                </div>
                            </div>
                        </div>

                        <!-- Location Information -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Xưởng <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_xuong" id="id_xuong" required>
                                        <option value="">Chọn xưởng</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Line sản xuất <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_line" id="id_line" required disabled>
                                        <option value="">Chọn line</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Khu vực <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_khu_vuc" id="id_khu_vuc" required disabled>
                                        <option value="">Chọn khu vực</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Dòng máy</label>
                                    <select class="form-select select2" name="id_dong_may" id="id_dong_may" disabled>
                                        <option value="">Chọn dòng máy</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vị trí cụ thể</label>
                                    <input type="text" class="form-control" name="vi_tri" 
                                           placeholder="Vị trí cụ thể trong line">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ngành</label>
                                    <input type="text" class="form-control" name="nganh" 
                                           placeholder="Ngành sản xuất">
                                </div>
                            </div>
                        </div>

                        <!-- Technical Information -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Năm sản xuất</label>
                                    <input type="number" class="form-control" name="nam_san_xuat" 
                                           min="1990" max="2030" placeholder="2023">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Công suất</label>
                                    <input type="text" class="form-control" name="cong_suat" 
                                           placeholder="Ví dụ: 5.5kW">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tình trạng</label>
                                    <select class="form-select" name="tinh_trang" required>
                                        <option value="hoat_dong" selected>Hoạt động</option>
                                        <option value="bao_tri">Bảo trì</option>
                                        <option value="hong">Hỏng</option>
                                        <option value="ngung_hoat_dong">Ngừng hoạt động</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Người chủ quản</label>
                                    <select class="form-select select2" name="nguoi_chu_quan">
                                        <option value="">Chọn người chủ quản</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hình ảnh thiết bị</label>
                                    <input type="file" class="form-control" name="hinh_anh" accept="image/*">
                                    <div class="form-text">Định dạng: JPG, PNG. Tối đa 5MB</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Thông số kỹ thuật</label>
                            <textarea class="form-control" name="thong_so_ky_thuat" rows="3" 
                                      placeholder="Mô tả thông số kỹ thuật của thiết bị"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ghi chú</label>
                            <textarea class="form-control" name="ghi_chu" rows="2" 
                                      placeholder="Ghi chú thêm về thiết bị"></textarea>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="history.back()">
                                <i class="fas fa-times me-2"></i>Hủy
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Lưu thiết bị
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Preview Panel -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-eye me-2"></i>Xem trước QR Code
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div id="qrPreview">
                        <div class="text-muted">
                            <i class="fas fa-qrcode fa-3x mb-3 opacity-50"></i>
                            <p>Nhập ID thiết bị để xem QR Code</p>
                        </div>
                    </div>
                </div>
            </div>
            
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
                            ID thiết bị phải duy nhất trong hệ thống
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Chọn đúng vị trí để dễ dàng quản lý
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Hình ảnh giúp nhận diện thiết bị nhanh hơn
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            QR Code sẽ được tạo tự động
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadXuongOptions();
    
    // Form submission
    $('#equipmentForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
    
    // Cascading dropdowns
    $('#id_xuong').change(function() {
        const xuongId = $(this).val();
        loadLineOptions(xuongId);
        resetDownstreamDropdowns(['id_line', 'id_khu_vuc', 'id_dong_may']);
    });
    
    $('#id_line').change(function() {
        const lineId = $(this).val();
        loadKhuVucOptions(lineId);
        resetDownstreamDropdowns(['id_khu_vuc', 'id_dong_may']);
    });
    
    $('#id_khu_vuc').change(function() {
        const khuVucId = $(this).val();
        loadDongMayOptions(khuVucId);
        resetDownstreamDropdowns(['id_dong_may']);
    });
    
    // QR Preview
    $('input[name="id_thiet_bi"]').on('input', function() {
        updateQRPreview($(this).val());
    });
});

function loadXuongOptions() {
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_xuong' }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Chọn xưởng</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.ten_xuong}</option>`;
            });
            $('#id_xuong').html(options);
        }
    });
}

function loadLineOptions(xuongId) {
    const lineSelect = $('#id_line');
    
    if (!xuongId) {
        lineSelect.prop('disabled', true);
        return;
    }
    
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_lines', xuong_id: xuongId }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Chọn line</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.ten_line}</option>`;
            });
            lineSelect.prop('disabled', false).html(options);
        }
    });
}

function loadKhuVucOptions(lineId) {
    const khuVucSelect = $('#id_khu_vuc');
    
    if (!lineId) {
        khuVucSelect.prop('disabled', true);
        return;
    }
    
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_khu_vuc', line_id: lineId }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Chọn khu vực</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.ten_khu_vuc}</option>`;
            });
            khuVucSelect.prop('disabled', false).html(options);
        }
    });
}

function loadDongMayOptions(khuVucId) {
    const dongMaySelect = $('#id_dong_may');
    
    if (!khuVucId) {
        dongMaySelect.prop('disabled', true);
        return;
    }
    
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_dong_may', khu_vuc_id: khuVucId }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Chọn dòng máy</option>';
            response.data.forEach(item => {
                options += `<option value="${item.id}">${item.ten_dong_may}</option>`;
            });
            dongMaySelect.prop('disabled', false).html(options);
        }
    });
}

function resetDownstreamDropdowns(dropdownIds) {
    dropdownIds.forEach(id => {
        $(`#${id}`).prop('disabled', true).html('<option value="">Chọn...</option>');
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

function submitForm() {
    const formData = new FormData(document.getElementById('equipmentForm'));
    formData.append('action', 'create');
    
    CMMS.ajax('api.php', {
        data: formData
    }).then(response => {
        if (response.success) {
            CMMS.showAlert('Tạo thiết bị thành công!', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1500);
        } else {
            CMMS.showAlert(response.message, 'error');
        }
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>