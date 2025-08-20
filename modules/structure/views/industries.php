<?php
$pageTitle = 'Quản lý ngành';
$currentModule = 'structure';
$moduleCSS = 'structure';
$moduleJS = 'structure';

$breadcrumb = [
    ['title' => 'Cấu trúc thiết bị', 'url' => '../index.php'],
    ['title' => 'Quản lý ngành']
];

$pageActions = '';
if (hasPermission('structure', 'create')) {
    $pageActions = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#industryModal">
        <i class="fas fa-plus me-1"></i> Thêm ngành
    </button>';
}

require_once '../../../includes/header.php';
requirePermission('structure', 'view');

// Get parameters
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$limit = 20;

// Get industries data via API
$apiUrl = "api/industries.php?action=list&page={$page}&limit={$limit}";
if ($search) $apiUrl .= "&search=" . urlencode($search);
if ($status) $apiUrl .= "&status=" . urlencode($status);

// In a real implementation, you would call the API
// For now, we'll get data directly from database
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(code LIKE ? OR name LIKE ? OR description LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
$offset = ($page - 1) * $limit;

$countSql = "SELECT COUNT(*) as total FROM industries $whereClause";
$totalResult = $db->fetch($countSql, $params);
$total = $totalResult['total'];

$sql = "SELECT i.*, u.full_name as created_by_name,
               (SELECT COUNT(*) FROM workshops w WHERE w.industry_id = i.id AND w.status = 'active') as workshop_count,
               (SELECT COUNT(*) FROM equipment e WHERE e.industry_id = i.id AND e.status = 'active') as equipment_count
        FROM industries i
        LEFT JOIN users u ON i.created_by = u.id
        $whereClause
        ORDER BY i.name ASC
        LIMIT $limit OFFSET $offset";

$industries = $db->fetchAll($sql, $params);
$pagination = paginate($total, $page, $limit);
?>

<div class="row">
    <div class="col-12">
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   class="form-control" 
                                   name="search" 
                                   placeholder="Tìm kiếm theo mã, tên ngành..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-filter me-1"></i> Lọc
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="?" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times me-1"></i> Xóa lọc
                        </a>
                    </div>
                    <?php if (hasPermission('structure', 'export')): ?>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-success w-100" onclick="exportIndustries()">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <div class="bulk-actions mb-3" style="display: none;">
            <div class="card">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-muted">Đã chọn <span id="selectedCount">0</span> mục</span>
                        <div class="btn-group btn-group-sm">
                            <?php if (hasPermission('structure', 'edit')): ?>
                            <button type="button" class="btn btn-outline-success" onclick="bulkActivate('industry')">
                                <i class="fas fa-check me-1"></i> Kích hoạt
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="bulkDeactivate('industry')">
                                <i class="fas fa-times me-1"></i> Vô hiệu hóa
                            </button>
                            <?php endif; ?>
                            <?php if (hasPermission('structure', 'delete')): ?>
                            <button type="button" class="btn btn-outline-danger" onclick="bulkDelete('industry')">
                                <i class="fas fa-trash me-1"></i> Xóa
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Industries Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-industry me-2"></i>
                    Danh sách ngành (<?php echo number_format($total); ?>)
                </h5>
                <div class="d-flex gap-2">
                    <?php if (hasPermission('structure', 'create')): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#industryModal">
                        <i class="fas fa-plus me-1"></i> Thêm ngành
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($industries)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-industry text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">Chưa có ngành nào</h5>
                        <p class="text-muted">Bắt đầu bằng cách thêm ngành đầu tiên</p>
                        <?php if (hasPermission('structure', 'create')): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#industryModal">
                            <i class="fas fa-plus me-1"></i> Thêm ngành
                        </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                        </div>
                                    </th>
                                    <th>Mã ngành</th>
                                    <th>Tên ngành</th>
                                    <th>Mô tả</th>
                                    <th width="100">Trạng thái</th>
                                    <th width="80">Xưởng</th>
                                    <th width="80">Thiết bị</th>
                                    <th width="120">Ngày tạo</th>
                                    <th width="100">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($industries as $industry): ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input item-checkbox" 
                                                   type="checkbox" 
                                                   value="<?php echo $industry['id']; ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($industry['code']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($industry['name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?php echo htmlspecialchars(substr($industry['description'] ?? '', 0, 50)); ?>
                                            <?php if (strlen($industry['description'] ?? '') > 50): ?>...<?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatusClass($industry['status']); ?>">
                                            <?php echo getStatusText($industry['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $industry['workshop_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $industry['equipment_count']; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo formatDate($industry['created_at']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-outline-info" 
                                                    onclick="viewIndustry(<?php echo $industry['id']; ?>)"
                                                    title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if (hasPermission('structure', 'edit')): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-primary" 
                                                    onclick="editIndustry(<?php echo $industry['id']; ?>)"
                                                    title="Chỉnh sửa">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('structure', 'delete') && $industry['workshop_count'] == 0 && $industry['equipment_count'] == 0): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger" 
                                                    onclick="deleteIndustry(<?php echo $industry['id']; ?>, '<?php echo htmlspecialchars($industry['name']); ?>')"
                                                    title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <?php echo buildPaginationHtml($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($status)); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Industry Modal -->
<div class="modal fade" id="industryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="industryForm" class="structure-form" action="api/industries.php" method="POST" data-type="industry">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-industry me-2"></i>
                        <span id="modalTitle">Thêm ngành mới</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create" id="formAction">
                    <input type="hidden" name="id" value="" id="industryId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                       class="form-control" 
                                       id="industryCode" 
                                       name="code" 
                                       placeholder="Mã ngành"
                                       required
                                       maxlength="20"
                                       pattern="[A-Z0-9_]+"
                                       style="text-transform: uppercase;">
                                <label for="industryCode">Mã ngành *</label>
                                <div class="invalid-feedback">Vui lòng nhập mã ngành (chỉ chữ hoa, số, dấu gạch dưới)</div>
                                <div class="form-text">Ví dụ: MI, PHO, NEM</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="industryStatus" name="status" required>
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                                <label for="industryStatus">Trạng thái *</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" 
                               class="form-control" 
                               id="industryName" 
                               name="name" 
                               placeholder="Tên ngành"
                               required
                               maxlength="100">
                        <label for="industryName">Tên ngành *</label>
                        <div class="invalid-feedback">Vui lòng nhập tên ngành</div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <textarea class="form-control" 
                                  id="industryDescription" 
                                  name="description" 
                                  placeholder="Mô tả"
                                  style="height: 100px"
                                  maxlength="500"></textarea>
                        <label for="industryDescription">Mô tả</label>
                        <div class="form-text">Tối đa 500 ký tự</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Hủy
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        <span id="submitText">Tạo ngành</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Industry Modal -->
<div class="modal fade" id="viewIndustryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-industry me-2"></i>
                    Chi tiết ngành
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="industryDetails">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '
<script>
// Industry management functions
function editIndustry(id) {
    const modal = document.getElementById("industryModal");
    const form = document.getElementById("industryForm");
    
    // Set modal title and form action
    document.getElementById("modalTitle").textContent = "Chỉnh sửa ngành";
    document.getElementById("formAction").value = "update";
    document.getElementById("industryId").value = id;
    document.getElementById("submitText").textContent = "Cập nhật";
    
    // Load industry data
    CMMS.ajax({
        url: "api/industries.php?action=get&id=" + id,
        method: "GET",
        success: function(data) {
            if (data.success) {
                const industry = data.data.industry;
                document.getElementById("industryCode").value = industry.code;
                document.getElementById("industryName").value = industry.name;
                document.getElementById("industryDescription").value = industry.description || "";
                document.getElementById("industryStatus").value = industry.status;
            }
        }
    });
    
    new bootstrap.Modal(modal).show();
}

function viewIndustry(id) {
    const modal = document.getElementById("viewIndustryModal");
    const detailsContainer = document.getElementById("industryDetails");
    
    // Show loading
    detailsContainer.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
        </div>
    `;
    
    // Load industry details
    CMMS.ajax({
        url: "api/industries.php?action=get&id=" + id,
        method: "GET",
        success: function(data) {
            if (data.success) {
                const industry = data.data.industry;
                const stats = industry.stats;
                
                detailsContainer.innerHTML = `
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="120"><strong>Mã ngành:</strong></td>
                                    <td><span class="badge badge-primary">${industry.code}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Tên ngành:</strong></td>
                                    <td>${industry.name}</td>
                                </tr>
                                <tr>
                                    <td><strong>Mô tả:</strong></td>
                                    <td>${industry.description || "<em>Không có mô tả</em>"}</td>
                                </tr>
                                <tr>
                                    <td><strong>Trạng thái:</strong></td>
                                    <td><span class="badge ${getStatusClass(industry.status)}">${getStatusText(industry.status)}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Người tạo:</strong></td>
                                    <td>${industry.created_by_name || "N/A"}</td>
                                </tr>
                                <tr>
                                    <td><strong>Ngày tạo:</strong></td>
                                    <td>${industry.created_at_formatted}</td>
                                </tr>
                                <tr>
                                    <td><strong>Cập nhật:</strong></td>
                                    <td>${industry.updated_at_formatted}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h6>Thống kê</h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-info bg-opacity-10 rounded">
                                        <div class="h4 mb-1 text-info">${stats.workshops}</div>
                                        <small class="text-muted">Xưởng</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-success bg-opacity-10 rounded">
                                        <div class="h4 mb-1 text-success">${stats.lines}</div>
                                        <small class="text-muted">Line</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="text-center p-2 bg-secondary bg-opacity-10 rounded">
                                        <div class="h4 mb-1 text-secondary">${stats.equipment}</div>
                                        <small class="text-muted">Thiết bị</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                detailsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message}
                    </div>
                `;
            }
        },
        error: function() {
            detailsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Có lỗi xảy ra khi tải dữ liệu
                </div>
            `;
        }
    });
    
    new bootstrap.Modal(modal).show();
}

function deleteIndustry(id, name) {
    CMMS.confirm(`Bạn có chắc chắn muốn xóa ngành "${name}"?\\nHành động này không thể hoàn tác.`, function() {
        CMMS.ajax({
            url: "api/industries.php",
            method: "POST",
            body: `action=delete&id=${id}`,
            success: function(data) {
                if (data.success) {
                    CMMS.showToast(data.message, "success");
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    CMMS.showToast(data.message, "error");
                }
            }
        });
    });
}

function exportIndustries() {
    const params = new URLSearchParams(window.location.search);
    let url = "api/export.php?type=industries";
    
    if (params.get("search")) url += "&search=" + encodeURIComponent(params.get("search"));
    if (params.get("status")) url += "&status=" + encodeURIComponent(params.get("status"));
    
    window.open(url, "_blank");
}

// Helper functions
function getStatusClass(status) {
    const classes = {
        "active": "badge-success",
        "inactive": "badge-secondary"
    };
    return classes[status] || "badge-secondary";
}

function getStatusText(status) {
    const texts = {
        "active": "Hoạt động",
        "inactive": "Không hoạt động"
    };
    return texts[status] || status;
}

// Reset form when modal is hidden
document.getElementById("industryModal").addEventListener("hidden.bs.modal", function() {
    const form = document.getElementById("industryForm");
    form.reset();
    form.classList.remove("was-validated");
    
    // Reset to create mode
    document.getElementById("modalTitle").textContent = "Thêm ngành mới";
    document.getElementById("formAction").value = "create";
    document.getElementById("industryId").value = "";
    document.getElementById("submitText").textContent = "Tạo ngành";
    
    // Clear any error states
    form.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));
});

// Auto uppercase code input
document.getElementById("industryCode").addEventListener("input", function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, "");
});

// Update selected count
document.addEventListener("change", function(e) {
    if (e.target.classList.contains("item-checkbox")) {
        const checkedBoxes = document.querySelectorAll(".item-checkbox:checked");
        const selectedCount = document.getElementById("selectedCount");
        const bulkActions = document.querySelector(".bulk-actions");
        
        if (selectedCount) selectedCount.textContent = checkedBoxes.length;
        if (bulkActions) {
            bulkActions.style.display = checkedBoxes.length > 0 ? "block" : "none";
        }
    }
});

// Select all functionality
document.getElementById("selectAll").addEventListener("change", function() {
    const checkboxes = document.querySelectorAll(".item-checkbox");
    checkboxes.forEach(cb => cb.checked = this.checked);
    
    const event = new Event("change", { bubbles: true });
    checkboxes.forEach(cb => cb.dispatchEvent(event));
});
</script>';

require_once '../../../includes/footer.php';
?>