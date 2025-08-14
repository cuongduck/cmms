<?php
// modules/equipment/edit.php - Sửa thiết bị - Updated for new DB structure
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong']);

$id = $_GET['id'] ?? 0;

// Lấy thông tin thiết bị với đầy đủ thông tin hierarchy
try {
    $stmt = $db->prepare("
        SELECT tb.*, 
               x.ten_xuong, x.ma_xuong,
               pl.ten_line, pl.ma_line,
               kv.ten_khu_vuc, kv.ma_khu_vuc, kv.loai_khu_vuc,
               dm.ten_dong_may, dm.ma_dong_may,
               ctb.ten_cum, ctb.ma_cum
        FROM thiet_bi tb
        LEFT JOIN xuong x ON tb.id_xuong = x.id
        LEFT JOIN production_line pl ON tb.id_line = pl.id
        LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
        LEFT JOIN dong_may dm ON tb.id_dong_may = dm.id
        LEFT JOIN cum_thiet_bi ctb ON tb.id_cum_thiet_bi = ctb.id
        WHERE tb.id = ?
    ");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch();
    
    if (!$equipment) {
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    error_log("Equipment edit error: " . $e->getMessage());
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
            <p class="text-muted">
                Cập nhật thông tin thiết bị: <strong><?= htmlspecialchars($equipment['ten_thiet_bi']) ?></strong>
                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($equipment['id_thiet_bi']) ?></span>
            </p>
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
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Thông tin thiết bị
                    </h5>
                </div>
                <div class="card-body">
                    <form id="equipmentForm" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= $equipment['id'] ?>">
                        
                        <!-- Basic Information Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Thông tin cơ bản
                                </h6>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ID thiết bị <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="id_thiet_bi" required 
                                           value="<?= htmlspecialchars($equipment['id_thiet_bi']) ?>">
                                    <div class="form-text">ID phải duy nhất trong hệ thống</div>
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

                        <!-- Location Information Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-map-marker-alt me-2"></i>Vị trí lắp đặt
                                </h6>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Xưởng <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_xuong" id="id_xuong" required>
                                        <option value="">Chọn xưởng</option>
                                    </select>
                                    <div class="form-text">Hiện tại: <?= htmlspecialchars($equipment['ten_xuong']) ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Khu vực <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_khu_vuc" id="id_khu_vuc" required>
                                        <option value="">Chọn khu vực</option>
                                    </select>
                                    <div class="form-text">Hiện tại: <?= htmlspecialchars($equipment['ten_khu_vuc']) ?> (<?= ucfirst($equipment['loai_khu_vuc']) ?>)</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Line sản xuất</label>
                                    <select class="form-select select2" name="id_line" id="id_line">
                                        <option value="">Chọn line</option>
                                    </select>
                                    <div class="form-text">Hiện tại: <?= htmlspecialchars($equipment['ten_line'] ?: 'Chưa chọn') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment Hierarchy Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-sitemap me-2"></i>Phân cấp thiết bị (Tùy chọn)
                                </h6>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Dòng máy</label>
                                    <select class="form-select select2" name="id_dong_may" id="id_dong_may">
                                        <option value="">Chọn dòng máy</option>
                                    </select>
                                    <div class="form-text">Hiện tại: <?= htmlspecialchars($equipment['ten_dong_may'] ?: 'Chưa chọn') ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cụm thiết bị</label>
                                    <select class="form-select select2" name="id_cum_thiet_bi" id="id_cum_thiet_bi">
                                        <option value="">Chọn cụm thiết bị</option>
                                    </select>
                                    <div class="form-text">Hiện tại: <?= htmlspecialchars($equipment['ten_cum'] ?: 'Chưa chọn') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Information Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-cog me-2"></i>Thông số kỹ thuật
                                </h6>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Ngành sản xuất</label>
                                    <select class="form-select" name="nganh">
                                        <option value="">Chọn ngành</option>
                                        <option value="Mì" <?= $equipment['nganh'] == 'Mì' ? 'selected' : '' ?>>Mì ăn liền</option>
                                        <option value="Phở" <?= $equipment['nganh'] == 'Phở' ? 'selected' : '' ?>>Phở gói</option>
                                        <option value="Nêm" <?= $equipment['nganh'] == 'Nêm' ? 'selected' : '' ?>>Nêm gia vị</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Năm sản xuất</label>
                                    <input type="number" class="form-control" name="nam_san_xuat" 
                                           min="1990" max="2030" value="<?= $equipment['nam_san_xuat'] ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Công suất</label>
                                    <input type="text" class="form-control" name="cong_suat" 
                                           value="<?= htmlspecialchars($equipment['cong_suat']) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Tình trạng <span class="text-danger">*</span></label>
                                    <select class="form-select" name="tinh_trang" required>
                                        <option value="hoat_dong" <?= $equipment['tinh_trang'] == 'hoat_dong' ? 'selected' : '' ?>>Hoạt động</option>
                                        <option value="bao_tri" <?= $equipment['tinh_trang'] == 'bao_tri' ? 'selected' : '' ?>>Bảo trì</option>
                                        <option value="hong" <?= $equipment['tinh_trang'] == 'hong' ? 'selected' : '' ?>>Hỏng</option>
                                        <option value="ngung_hoat_dong" <?= $equipment['tinh_trang'] == 'ngung_hoat_dong' ? 'selected' : '' ?>>Ngừng hoạt động</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Management Information Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-user-cog me-2"></i>Thông tin quản lý
                                </h6>
                            </div>
                            
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

                        <!-- Additional Information Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-file-alt me-2"></i>Thông tin bổ sung
                                </h6>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Thông số kỹ thuật chi tiết</label>
                                    <textarea class="form-control" name="thong_so_ky_thuat" rows="3"><?= htmlspecialchars($equipment['thong_so_ky_thuat']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea class="form-control" name="ghi_chu" rows="2"><?= htmlspecialchars($equipment['ghi_chu']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Ngày tạo: <?= formatDate($equipment['created_at'], 'd/m/Y H:i') ?>
                                        </small>
                                        <?php if ($equipment['updated_at'] && $equipment['updated_at'] != $equipment['created_at']): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-edit me-1"></i>
                                            Cập nhật: <?= formatDate($equipment['updated_at'], 'd/m/Y H:i') ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary me-2" onclick="history.back()">
                                            <i class="fas fa-times me-2"></i>Hủy
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Cập nhật thiết bị
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Right Sidebar -->
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
                         class="img-fluid rounded" alt="Hình ảnh thiết bị" style="max-height: 200px;">
                </div>
            </div>
            <?php endif; ?>
            
            <!-- QR Code -->
            <div class="card mb-3">
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
                            <button class="btn btn-outline-primary btn-sm" onclick="EquipmentEditModule.printQR()">
                                <i class="fas fa-print me-2"></i>In QR
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Current Location Hierarchy -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-sitemap me-2"></i>Cấu trúc hiện tại
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="mb-2">
                            <strong class="text-primary">Xưởng:</strong> 
                            <?= htmlspecialchars($equipment['ten_xuong']) ?>
                            <span class="text-muted">(<?= htmlspecialchars($equipment['ma_xuong']) ?>)</span>
                        </div>
                        
                        <div class="mb-2">
                            <strong class="text-info">Khu vực:</strong> 
                            <?= htmlspecialchars($equipment['ten_khu_vuc']) ?>
                            <span class="badge bg-secondary ms-1"><?= ucfirst($equipment['loai_khu_vuc']) ?></span>
                        </div>
                        
                        <?php if ($equipment['ten_line']): ?>
                        <div class="mb-2">
                            <strong class="text-success">Line:</strong> 
                            <?= htmlspecialchars($equipment['ten_line']) ?>
                            <span class="text-muted">(<?= htmlspecialchars($equipment['ma_line']) ?>)</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($equipment['ten_dong_may']): ?>
                        <div class="mb-2">
                            <strong class="text-warning">Dòng máy:</strong> 
                            <?= htmlspecialchars($equipment['ten_dong_may']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($equipment['ten_cum']): ?>
                        <div class="mb-2">
                            <strong class="text-dark">Cụm thiết bị:</strong> 
                            <?= htmlspecialchars($equipment['ten_cum']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Help Card -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Hướng dẫn
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            ID thiết bị phải duy nhất trong hệ thống
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Thay đổi vị trí sẽ reset các cấp dưới
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Hình ảnh mới sẽ thay thế hình cũ
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            QR Code sẽ được cập nhật tự động
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load Equipment Edit Module -->
<script>
// Pass equipment data to JavaScript
window.EQUIPMENT_DATA = <?= json_encode($equipment) ?>;

// Debug: log equipment data
console.log('Equipment data passed to JS:', window.EQUIPMENT_DATA);
</script>

<?php require_once '../../includes/footer.php'; ?>