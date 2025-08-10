<?php
// modules/equipment/edit.php - Sửa thiết bị
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong']);

$id = $_GET['id'] ?? 0;

// Lấy thông tin thiết bị
try {
    $stmt = $db->prepare("SELECT tb.*, x.ten_xuong, pl.ten_line, kv.ten_khu_vuc, dm.ten_dong_may
                          FROM thiet_bi tb
                          LEFT JOIN xuong x ON tb.id_xuong = x.id
                          LEFT JOIN production_line pl ON tb.id_line = pl.id
                          LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
                          LEFT JOIN dong_may dm ON tb.id_dong_may = dm.id
                          WHERE tb.id = ?");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch();
    
    if (!$equipment) {
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: index.php');
    exit();
}

$page_title = 'Sửa thiết bị: ' . $equipment['ten_thiet_bi'];
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
            <h1 class="h3 mb-0">Sửa thiết bị</h1>
            <p class="text-muted">Cập nhật thông tin thiết bị: <strong><?= htmlspecialchars($equipment['ten_thiet_bi']) ?></strong></p>
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
                        <input type="hidden" name="id" value="<?= $equipment['id'] ?>">
                        
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ID thiết bị <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="id_thiet_bi" required 
                                           value="<?= htmlspecialchars($equipment['id_thiet_bi']) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên thiết bị <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="ten_thiet_bi" required 
                                           value="<?= htmlspecialchars($equipment['ten_thiet_bi']) ?>">
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
                                    <select class="form-select select2" name="id_line" id="id_line" required>
                                        <option value="">Chọn line</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Khu vực <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_khu_vuc" id="id_khu_vuc" required>
                                        <option value="">Chọn khu vực</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Dòng máy</label>
                                    <select class="form-select select2" name="id_dong_may" id="id_dong_may">
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
                                           value="<?= htmlspecialchars($equipment['vi_tri']) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ngành</label>
                                    <input type="text" class="form-control" name="nganh" 
                                           value="<?= htmlspecialchars($equipment['nganh']) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Technical Information -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Năm sản xuất</label>
                                    <input type="number" class="form-control" name="nam_san_xuat" 
                                           min="1990" max="2030" value="<?= $equipment['nam_san_xuat'] ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Công suất</label>
                                    <input type="text" class="form-control" name="cong_suat" 
                                           value="<?= htmlspecialchars($equipment['cong_suat']) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Tình trạng</label>
                                    <select class="form-select" name="tinh_trang" required>
                                        <option value="hoat_dong" <?= $equipment['tinh_trang'] == 'hoat_dong' ? 'selected' : '' ?>>Hoạt động</option>
                                        <option value="bao_tri" <?= $equipment['tinh_trang'] == 'bao_tri' ? 'selected' : '' ?>>Bảo trì</option>
                                        <option value="hong" <?= $equipment['tinh_trang'] == 'hong' ? 'selected' : '' ?>>Hỏng</option>
                                        <option value="ngung_hoat_dong" <?= $equipment['tinh_trang'] == 'ngung_hoat_dong' ? 'selected' : '' ?>>Ngừng hoạt động</option>
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
                                            <option value="<?= $user['id'] ?>" <?= $equipment['nguoi_chu_quan'] == $user['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hình ảnh thiết bị</label>
                                    <input type="file" class="form-control" name="hinh_anh" accept="image/*">
                                    <div class="form-text">Định dạng: JPG, PNG. Tối đa 5MB. Để trống nếu không thay đổi</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Thông số kỹ thuật</label>
                            <textarea class="form-control" name="thong_so_ky_thuat" rows="3"><?= htmlspecialchars($equipment['thong_so_ky_thuat']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ghi chú</label>
                            <textarea class="form-control" name="ghi_chu" rows="2"><?= htmlspecialchars($equipment['ghi_chu']) ?></textarea>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="history.back()">
                                <i class="fas fa-times me-2"></i>Hủy
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Cập nhật thiết bị
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Preview Panel -->
        <div class="col-lg-4">
            <!-- Current Image -->
            <?php if ($equipment['hinh_anh']): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-image me-2"></i>Hình ảnh hiện tại
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="../../<?= htmlspecialchars($equipment['hinh_anh']) ?>" 
                         class="img-fluid rounded" alt="Hình ảnh thiết bị">
                </div>
            </div>
            <?php endif; ?>
            
            <!-- QR Code -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-qrcode me-2"></i>QR Code hiện tại
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div id="qrPreview">
                        <?php 
                        $qrUrl = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode($equipment['id_thiet_bi']);
                        ?>
                        <h6><?= htmlspecialchars($equipment['id_thiet_bi']) ?></h6>
                        <img src="<?= $qrUrl ?>" class="img-fluid" alt="QR Code">
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm" onclick="printQR()">
                                <i class="fas fa-print me-2"></i>In QR
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadFormData();
    
    // Form submission
    $('#equipmentForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
    
    // Cascading dropdowns
    $('#id_xuong').change(function() {
        const xuongId = $(this).val();
        loadLineOptions(xuongId);
    });
    
    $('#id_line').change(function() {
        const lineId = $(this).val();
        loadKhuVucOptions(lineId);
    });
    
    $('#id_khu_vuc').change(function() {
        const khuVucId = $(this).val();
        loadDongMayOptions(khuVucId);
    });
    
    // QR Preview update
    $('input[name="id_thiet_bi"]').on('input', function() {
        updateQRPreview($(this).val());
    });
});

function loadFormData() {
    // Load initial data
    const equipmentData = <?= json_encode($equipment) ?>;
    
    // Load xưởng first
    CMMS.ajax('api.php', {
        method: 'GET',
        data: { action: 'get_xuong' }
    }).then(response => {
        if (response.success) {
            let options = '<option value="">Chọn xưởng</option>';
            response.data.forEach(item => {
                const selected = item.id == equipmentData.id_xuong ? 'selected' : '';
                options += `<option value="${item.id}" ${selected}>${item.ten_xuong}</option>`;
            });
            $('#id_xuong').html(options);
            
            // Load lines if xuong is selected
            if (equipmentData.id_xuong) {
                loadLineOptions(equipmentData.id_xuong, equipmentData.id_line);
            }
        }
    });
}

function loadLineOptions(xuongId, selectedLineId = null) {
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
                const selected = item.id == selectedLineId ? 'selected' : '';
                options += `<option value="${item.id}" ${selected}>${item.ten_line}</option>`;
            });
            lineSelect.prop('disabled', false).html(options);
            
            // Load khu vuc if line is selected
            const equipmentData = <?= json_encode($equipment) ?>;
            if (selectedLineId && equipmentData.id_khu_vuc) {
                loadKhuVucOptions(selectedLineId, equipmentData.id_khu_vuc);
            }
        }
    });
}

function loadKhuVucOptions(lineId, selectedKhuVucId = null) {
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
                const selected = item.id == selectedKhuVucId ? 'selected' : '';
                options += `<option value="${item.id}" ${selected}>${item.ten_khu_vuc}</option>`;
            });
            khuVucSelect.prop('disabled', false).html(options);
            
            // Load dong may if khu vuc is selected
            const equipmentData = <?= json_encode($equipment) ?>;
            if (selectedKhuVucId && equipmentData.id_dong_may) {
                loadDongMayOptions(selectedKhuVucId, equipmentData.id_dong_may);
            }
        }
    });
}

function loadDongMayOptions(khuVucId, selectedDongMayId = null) {
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
                const selected = item.id == selectedDongMayId ? 'selected' : '';
                options += `<option value="${item.id}" ${selected}>${item.ten_dong_may}</option>`;
            });
            dongMaySelect.prop('disabled', false).html(options);
        }
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
            <button class="btn btn-outline-primary btn-sm" onclick="printQR()">
                <i class="fas fa-print me-2"></i>In QR
            </button>
        </div>
    `);
}

function printQR() {
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
}

function submitForm() {
    const formData = new FormData(document.getElementById('equipmentForm'));
    formData.append('action', 'update');
    
    CMMS.ajax('api.php', {
        data: formData
    }).then(response => {
        if (response.success) {
            CMMS.showAlert('Cập nhật thiết bị thành công!', 'success');
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