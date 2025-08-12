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
            <button class="btn btn-success me-2" onclick="BOMModule.importBOM()">
                <i class="fas fa-file-import me-2"></i>Import BOM
            </button>
            <button class="btn btn-primary" onclick="BOMModule.addBOMItem()">
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
                    <button type="button" class="btn btn-outline-secondary" onclick="BOMModule.resetFilter()">
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
                <button class="btn btn-outline-success btn-sm" onclick="BOMModule.exportBOM()">
                    <i class="fas fa-file-excel me-2"></i>Xuất Excel
                </button>
                <button class="btn btn-outline-info btn-sm" onclick="BOMModule.printBOM()">
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
                <button type="button" class="btn btn-primary" onclick="BOMModule.saveBOM()">
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
                <button type="button" class="btn btn-primary" onclick="BOMModule.printDetailBOM()">
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
                <button type="button" class="btn btn-primary" onclick="BOMModule.processImport()">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>