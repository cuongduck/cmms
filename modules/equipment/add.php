<?php
// modules/equipment/add.php - Thêm thiết bị mới - Updated for new DB structure
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
                    <h5 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Thông tin thiết bị
                    </h5>
                </div>
                <div class="card-body">
                    <form id="equipmentForm" enctype="multipart/form-data">
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
                                           placeholder="Ví dụ: OMORI_1.1">
                                    <div class="invalid-feedback">
                                        Vui lòng nhập ID thiết bị
                                    </div>
                                    <div class="form-text">ID phải duy nhất trong hệ thống</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tên thiết bị <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="ten_thiet_bi" required 
                                           placeholder="Tên thiết bị">
                                    <div class="invalid-feedback">
                                        Vui lòng nhập tên thiết bị
                                    </div>
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
                                    <div class="invalid-feedback">
                                        Vui lòng chọn xưởng
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Khu vực <span class="text-danger">*</span></label>
                                    <select class="form-select select2" name="id_khu_vuc" id="id_khu_vuc" required disabled>
                                        <option value="">Chọn khu vực</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Vui lòng chọn khu vực
                                    </div>
                                    <div class="form-text">Công nghệ hoặc Đóng gói</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Line sản xuất</label>
                                    <select class="form-select select2" name="id_line" id="id_line" disabled>
                                        <option value="">Chọn line</option>
                                    </select>
                                    <div class="form-text">Line sản xuất trong xưởng</div>
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
                                    <select class="form-select select2" name="id_dong_may" id="id_dong_may" disabled>
                                        <option value="">Chọn dòng máy</option>
                                    </select>
                                    <div class="form-text">Ví dụ: Omori, Chảo chiên, Lô cán...</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cụm thiết bị</label>
                                    <select class="form-select select2" name="id_cum_thiet_bi" id="id_cum_thiet_bi" disabled>
                                        <option value="">Chọn cụm thiết bị</option>
                                    </select>
                                    <div class="form-text">Cụm con trong dòng máy</div>
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
                                        <option value="Mì">Mì ăn liền</option>
                                        <option value="Phở">Phở gói</option>
                                        <option value="Nêm">Nêm gia vị</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Năm sản xuất</label>
                                    <input type="number" class="form-control" name="nam_san_xuat" 
                                           min="1990" max="2030" placeholder="2023">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Công suất</label>
                                    <input type="text" class="form-control" name="cong_suat" 
                                           placeholder="Ví dụ: 8kW, 120 gói/phút">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Tình trạng <span class="text-danger">*</span></label>
                                    <select class="form-select" name="tinh_trang" required>
                                        <option value="hoat_dong" selected>Hoạt động</option>
                                        <option value="bao_tri">Bảo trì</option>
                                        <option value="hong">Hỏng</option>
                                        <option value="ngung_hoat_dong">Ngừng hoạt động</option>
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
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Người phụ trách thiết bị này</div>
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
                                    <textarea class="form-control" name="thong_so_ky_thuat" rows="3" 
                                              placeholder="Mô tả chi tiết thông số kỹ thuật, tốc độ, nhiệt độ, dung tích..."></textarea>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea class="form-control" name="ghi_chu" rows="2" 
                                              placeholder="Ghi chú thêm về thiết bị, lưu ý đặc biệt..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary" onclick="EquipmentAddModule.resetForm()">
                                            <i class="fas fa-undo me-2"></i>Đặt lại
                                        </button>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary me-2" onclick="history.back()">
                                            <i class="fas fa-times me-2"></i>Hủy
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Lưu thiết bị
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
            <!-- QR Preview Card -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-qrcode me-2"></i>Xem trước QR Code
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
            
            <!-- Progress Card -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>Tiến độ nhập liệu
                    </h6>
                </div>
                <div class="card-body">
                    <div class="progress mb-2" style="height: 10px;">
                        <div class="progress-bar bg-success" id="formProgress" role="progressbar" 
                             style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted" id="progressText">0% hoàn thành</small>
                    
                    <div class="mt-3">
                        <div class="checklist">
                            <div class="form-check form-check-sm" id="check-basic">
                                <input class="form-check-input" type="checkbox" disabled>
                                <label class="form-check-label text-muted">
                                    Thông tin cơ bản
                                </label>
                            </div>
                            <div class="form-check form-check-sm" id="check-location">
                                <input class="form-check-input" type="checkbox" disabled>
                                <label class="form-check-label text-muted">
                                    Vị trí lắp đặt
                                </label>
                            </div>
                            <div class="form-check form-check-sm" id="check-technical">
                                <input class="form-check-input" type="checkbox" disabled>
                                <label class="form-check-label text-muted">
                                    Thông số kỹ thuật
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Help Card -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Hướng dẫn
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small">
                        <strong>Cấu trúc phân cấp mới:</strong><br>
                        Xưởng → Khu vực → Line → Dòng máy → Cụm thiết bị
                    </div>
                    
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Bắt buộc:</strong> ID, Tên, Xưởng, Khu vực
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-info text-info me-2"></i>
                            <strong>Khu vực:</strong> Công nghệ hoặc Đóng gói
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-info text-info me-2"></i>
                            <strong>Line:</strong> Dây chuyền sản xuất cụ thể
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Dòng máy và Cụm là tùy chọn
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-camera text-secondary me-2"></i>
                            Hình ảnh giúp nhận diện thiết bị
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Equipment Structure Example -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-tree me-2"></i>Ví dụ cấu trúc
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="text-primary fw-bold">Xưởng F2</div>
                        <div class="ms-2">
                            <div class="text-info">├── Khu công nghệ</div>
                            <div class="ms-3">
                                <div>├── Line 1</div>
                                <div class="ms-3">
                                    <div class="text-warning">├── Dòng Omori</div>
                                    <div class="ms-3">
                                        <div class="text-success">└── Omori chính</div>
                                        <div class="ms-4 text-muted">└── OMORI_1.1</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.form-section {
    border-left: 3px solid #667eea;
    padding-left: 15px;
    margin-bottom: 2rem;
}

.form-check-sm .form-check-input {
    margin-top: 0.1rem;
}

.form-check-sm .form-check-label {
    font-size: 0.875rem;
}

.checklist .form-check {
    margin-bottom: 0.5rem;
}

.progress {
    background-color: #e9ecef;
    border-radius: 0.5rem;
}

.alert-info {
    border-left: 4px solid #0dcaf0;
}

.card-header h6 {
    font-weight: 600;
}

.tree-item {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    line-height: 1.2;
}
</style>

<!-- Load Equipment Add Module -->
<script>
// Debug: log that we're on add page
console.log('Equipment add page loaded');

// Form progress tracking - will be initialized after jQuery loads
function initFormProgress() {
    if (typeof $ === 'undefined') {
        setTimeout(initFormProgress, 100);
        return;
    }
    
    function updateFormProgress() {
        let completedSections = 0;
        const totalSections = 3;
        
        // Check basic info (ID + Name)
        const basicComplete = $('input[name="id_thiet_bi"]').val().trim() !== '' && 
                             $('input[name="ten_thiet_bi"]').val().trim() !== '';
        if (basicComplete) {
            completedSections++;
            $('#check-basic input').prop('checked', true);
            $('#check-basic label').removeClass('text-muted').addClass('text-success');
        } else {
            $('#check-basic input').prop('checked', false);
            $('#check-basic label').removeClass('text-success').addClass('text-muted');
        }
        
        // Check location info (Xuong + Khu vuc)
        const locationComplete = $('#id_xuong').val() !== '' && 
                                $('#id_khu_vuc').val() !== '';
        if (locationComplete) {
            completedSections++;
            $('#check-location input').prop('checked', true);
            $('#check-location label').removeClass('text-muted').addClass('text-success');
        } else {
            $('#check-location input').prop('checked', false);
            $('#check-location label').removeClass('text-success').addClass('text-muted');
        }
        
        // Check technical info (at least status selected)
        const technicalComplete = $('select[name="tinh_trang"]').val() !== '';
        if (technicalComplete) {
            completedSections++;
            $('#check-technical input').prop('checked', true);
            $('#check-technical label').removeClass('text-muted').addClass('text-success');
        } else {
            $('#check-technical input').prop('checked', false);
            $('#check-technical label').removeClass('text-success').addClass('text-muted');
        }
        
        // Update progress bar
        const progress = Math.round((completedSections / totalSections) * 100);
        $('#formProgress').css('width', progress + '%').attr('aria-valuenow', progress);
        $('#progressText').text(progress + '% hoàn thành');
        
        // Change color based on progress
        $('#formProgress').removeClass('bg-danger bg-warning bg-success');
        if (progress < 33) {
            $('#formProgress').addClass('bg-danger');
        } else if (progress < 66) {
            $('#formProgress').addClass('bg-warning');
        } else {
            $('#formProgress').addClass('bg-success');
        }
    }
    
    // Update progress on form changes
    $('#equipmentForm input, #equipmentForm select').on('input change', function() {
        setTimeout(updateFormProgress, 100);
    });
    
    // Initial progress update
    updateFormProgress();
}

// Initialize form progress when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFormProgress);
} else {
    initFormProgress();
}
</script>

<?php require_once '../../includes/footer.php'; ?>