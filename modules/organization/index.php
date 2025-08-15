<?php
// modules/organization/index.php - Quản lý cấu trúc tổ chức
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong']);

$page_title = 'Quản lý cấu trúc tổ chức';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Lấy thống kê
try {
    $stats = [
        'nganh' => $db->query("SELECT COUNT(*) as count FROM nganh")->fetch()['count'],
        'xuong' => $db->query("SELECT COUNT(*) as count FROM xuong")->fetch()['count'], 
        'khu_vuc' => $db->query("SELECT COUNT(*) as count FROM khu_vuc")->fetch()['count'],
        'lines' => $db->query("SELECT COUNT(*) as count FROM production_line")->fetch()['count'],
        'dong_may' => $db->query("SELECT COUNT(*) as count FROM dong_may")->fetch()['count'],
        'cum_thiet_bi' => $db->query("SELECT COUNT(*) as count FROM cum_thiet_bi")->fetch()['count']
    ];
} catch (Exception $e) {
    $stats = array_fill_keys(['nganh', 'xuong', 'khu_vuc', 'lines', 'dong_may', 'cum_thiet_bi'], 0);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quản lý cấu trúc tổ chức</h1>
            <p class="text-muted">Quản lý ngành, xưởng, khu vực, line, dòng máy và cụm thiết bị</p>
        </div>
        <div>
            <button class="btn btn-outline-info" onclick="OrganizationModule.showStructureTree()">
                <i class="fas fa-sitemap me-2"></i>Xem cây cấu trúc
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-industry fa-2x mb-2"></i>
                    <h4 class="mb-0"><?= $stats['nganh'] ?></h4>
                    <small>Ngành</small>
                </div>
                <div class="card-footer bg-primary bg-opacity-75 text-center">
                    <button class="btn btn-link text-white p-0" onclick="OrganizationModule.setActiveTab('nganh')">
                        <small>Quản lý <i class="fas fa-arrow-right ms-1"></i></small>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-building fa-2x mb-2"></i>
                    <h4 class="mb-0"><?= $stats['xuong'] ?></h4>
                    <small>Xưởng</small>
                </div>
                <div class="card-footer bg-success bg-opacity-75 text-center">
                    <button class="btn btn-link text-white p-0" onclick="OrganizationModule.setActiveTab('xuong')">
                        <small>Quản lý <i class="fas fa-arrow-right ms-1"></i></small>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-map-marked-alt fa-2x mb-2"></i>
                    <h4 class="mb-0"><?= $stats['khu_vuc'] ?></h4>
                    <small>Khu vực</small>
                </div>
                <div class="card-footer bg-info bg-opacity-75 text-center">
                    <button class="btn btn-link text-white p-0" onclick="OrganizationModule.setActiveTab('khu_vuc')">
                        <small>Quản lý <i class="fas fa-arrow-right ms-1"></i></small>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body text-center">
                    <i class="fas fa-stream fa-2x mb-2"></i>
                    <h4 class="mb-0"><?= $stats['lines'] ?></h4>
                    <small>Line sản xuất</small>
                </div>
                <div class="card-footer bg-warning bg-opacity-75 text-center">
                    <button class="btn btn-link text-dark p-0" onclick="OrganizationModule.setActiveTab('line')">
                        <small>Quản lý <i class="fas fa-arrow-right ms-1"></i></small>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-purple text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-cogs fa-2x mb-2"></i>
                    <h4 class="mb-0"><?= $stats['dong_may'] ?></h4>
                    <small>Dòng máy</small>
                </div>
                <div class="card-footer bg-purple bg-opacity-75 text-center">
                    <button class="btn btn-link text-white p-0" onclick="OrganizationModule.setActiveTab('dong_may')">
                        <small>Quản lý <i class="fas fa-arrow-right ms-1"></i></small>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-layer-group fa-2x mb-2"></i>
                    <h4 class="mb-0"><?= $stats['cum_thiet_bi'] ?></h4>
                    <small>Cụm thiết bị</small>
                </div>
                <div class="card-footer bg-dark bg-opacity-75 text-center">
                    <button class="btn btn-link text-white p-0" onclick="OrganizationModule.setActiveTab('cum_thiet_bi')">
                        <small>Quản lý <i class="fas fa-arrow-right ms-1"></i></small>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Management Tabs -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="managementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="nganh-tab" data-bs-toggle="tab" data-bs-target="#nganh-panel" type="button" role="tab">
                        <i class="fas fa-industry me-2"></i>Ngành
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="xuong-tab" data-bs-toggle="tab" data-bs-target="#xuong-panel" type="button" role="tab">
                        <i class="fas fa-building me-2"></i>Xưởng
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="khu-vuc-tab" data-bs-toggle="tab" data-bs-target="#khu-vuc-panel" type="button" role="tab">
                        <i class="fas fa-map-marked-alt me-2"></i>Khu vực
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="line-tab" data-bs-toggle="tab" data-bs-target="#line-panel" type="button" role="tab">
                        <i class="fas fa-stream me-2"></i>Line
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="dong-may-tab" data-bs-toggle="tab" data-bs-target="#dong-may-panel" type="button" role="tab">
                        <i class="fas fa-cogs me-2"></i>Dòng máy
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="cum-thiet-bi-tab" data-bs-toggle="tab" data-bs-target="#cum-thiet-bi-panel" type="button" role="tab">
                        <i class="fas fa-layer-group me-2"></i>Cụm thiết bị
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="managementTabsContent">
                
                <!-- Ngành Tab -->
                <div class="tab-pane fade show active" id="nganh-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-industry me-2 text-primary"></i>Quản lý ngành sản xuất
                        </h5>
                        <button class="btn btn-primary btn-sm" onclick="OrganizationModule.showAddModal('nganh')">
                            <i class="fas fa-plus me-2"></i>Thêm ngành
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="nganhTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Mã ngành</th>
                                    <th>Tên ngành</th>
                                    <th>Mô tả</th>
                                    <th width="120">Ngày tạo</th>
                                    <th width="120">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="nganhTableBody">
                                <tr><td colspan="5" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                    Đang tải...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Xưởng Tab -->
                <div class="tab-pane fade" id="xuong-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2 text-success"></i>Quản lý xưởng sản xuất
                        </h5>
                        <button class="btn btn-success btn-sm" onclick="OrganizationModule.showAddModal('xuong')">
                            <i class="fas fa-plus me-2"></i>Thêm xưởng
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="xuongTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Mã xưởng</th>
                                    <th>Tên xưởng</th>
                                    <th width="150">Thuộc ngành</th>
                                    <th>Mô tả</th>
                                    <th width="120">Ngày tạo</th>
                                    <th width="120">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="xuongTableBody">
                                <tr><td colspan="6" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-success me-2"></div>
                                    Đang tải...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Khu vực Tab -->
                <div class="tab-pane fade" id="khu-vuc-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-map-marked-alt me-2 text-info"></i>Quản lý khu vực
                        </h5>
                        <button class="btn btn-info btn-sm" onclick="OrganizationModule.showAddModal('khu_vuc')">
                            <i class="fas fa-plus me-2"></i>Thêm khu vực
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="khuVucTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Mã khu vực</th>
                                    <th>Tên khu vực</th>
                                    <th width="150">Thuộc xưởng</th>
                                    <th width="120">Loại khu vực</th>
                                    <th>Mô tả</th>
                                    <th width="120">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="khuVucTableBody">
                                <tr><td colspan="6" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-info me-2"></div>
                                    Đang tải...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Line Tab -->
                <div class="tab-pane fade" id="line-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-stream me-2 text-warning"></i>Quản lý line sản xuất
                        </h5>
                        <button class="btn btn-warning btn-sm" onclick="OrganizationModule.showAddModal('line')">
                            <i class="fas fa-plus me-2"></i>Thêm line
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="lineTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Mã line</th>
                                    <th>Tên line</th>
                                    <th width="150">Thuộc xưởng</th>
                                    <th width="150">Thuộc khu vực</th>
                                    <th>Mô tả</th>
                                    <th width="120">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="lineTableBody">
                                <tr><td colspan="6" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-warning me-2"></div>
                                    Đang tải...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Dòng máy Tab -->
                <div class="tab-pane fade" id="dong-may-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs me-2 text-purple"></i>Quản lý dòng máy
                        </h5>
                        <button class="btn btn-purple btn-sm" onclick="OrganizationModule.showAddModal('dong_may')">
                            <i class="fas fa-plus me-2"></i>Thêm dòng máy
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="dongMayTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="150">Mã dòng máy</th>
                                    <th>Tên dòng máy</th>
                                    <th width="200">Thuộc line</th>
                                    <th>Mô tả</th>
                                    <th width="120">Ngày tạo</th>
                                    <th width="120">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="dongMayTableBody">
                                <tr><td colspan="6" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-purple me-2"></div>
                                    Đang tải...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cụm thiết bị Tab -->
                <div class="tab-pane fade" id="cum-thiet-bi-panel" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-layer-group me-2 text-dark"></i>Quản lý cụm thiết bị
                        </h5>
                        <button class="btn btn-dark btn-sm" onclick="OrganizationModule.showAddModal('cum_thiet_bi')">
                            <i class="fas fa-plus me-2"></i>Thêm cụm thiết bị
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="cumThietBiTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="120">Mã cụm</th>
                                    <th>Tên cụm</th>
                                    <th width="200">Thuộc dòng máy</th>
                                    <th>Mô tả</th>
                                    <th width="120">Ngày tạo</th>
                                    <th width="120">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="cumThietBiTableBody">
                                <tr><td colspan="6" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-dark me-2"></div>
                                    Đang tải...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Thêm mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addEditForm">
                <div class="modal-body" id="modalBody">
                    <!-- Form content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Structure Tree Modal -->
<div class="modal fade" id="structureTreeModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sitemap me-2"></i>Cây cấu trúc tổ chức
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="structureTreeContent" style="min-height: 400px;">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                        <div class="mt-2">Đang tải cây cấu trúc...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-primary" onclick="OrganizationModule.exportStructure()">
                    <i class="fas fa-download me-2"></i>Xuất cấu trúc
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom colors for cards */
.bg-purple {
    background-color: #6f42c1 !important;
}

.btn-purple {
    background-color: #6f42c1;
    border-color: #6f42c1;
    color: white;
}

.btn-purple:hover {
    background-color: #5d389e;
    border-color: #5d389e;
    color: white;
}

.text-purple {
    color: #6f42c1 !important;
}

/* Tab styling */
.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 0.25rem;
    border: none;
}

.nav-tabs .nav-link:hover {
    border: none;
    color: #495057;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 0.25rem;
}

/* Table styling */
.table th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
    background: #f8f9fa;
}

.table td {
    vertical-align: middle;
    font-size: 0.875rem;
}

/* Card hover effects */
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

/* Modal styling */
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
}

.modal-header .btn-close {
    filter: invert(1);
}

/* Structure tree styling */
.structure-tree {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
}

.tree-node {
    margin-left: 20px;
    border-left: 2px solid #e9ecef;
    padding-left: 10px;
    margin-bottom: 10px;
}

.tree-item {
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 5px;
    border-left: 4px solid;
    transition: all 0.3s ease;
}

.tree-item:hover {
    background: #e9ecef;
    transform: translateX(2px);
}

.tree-item.level-0 { border-left-color: #007bff; }
.tree-item.level-1 { border-left-color: #28a745; }
.tree-item.level-2 { border-left-color: #17a2b8; }
.tree-item.level-3 { border-left-color: #ffc107; }
.tree-item.level-4 { border-left-color: #6f42c1; }
.tree-item.level-5 { border-left-color: #343a40; }

.tree-icon {
    margin-right: 8px;
    width: 16px;
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .nav-tabs {
        flex-direction: column;
    }
    
    .nav-tabs .nav-item {
        margin-bottom: 0.25rem;
    }
    
    .card-body .btn-group {
        flex-direction: column;
    }
}

/* Loading states */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Button improvements */
.btn-sm {
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
}

.card-footer .btn-link {
    font-size: 0.8rem;
    text-decoration: none;
}

.card-footer .btn-link:hover {
    text-decoration: underline;
}
</style>

<script>
// Debug: log that we're on organization page
console.log('Organization index page loaded');

// Initialize Organization Module when DOM is ready
function initOrganizationModule() {
    if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
        console.log('jQuery not ready, retrying...');
        setTimeout(initOrganizationModule, 100);
        return;
    }
    
    // Check if we're on organization index page
    if (window.location.pathname.includes('/organization/') && typeof OrganizationModule !== 'undefined') {
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
</script>

<?php require_once '../../includes/footer.php'; ?>