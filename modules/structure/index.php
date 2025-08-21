<?php
// Đặt đường dẫn tuyệt đối
$pageTitle = 'Cấu trúc thiết bị';
$currentModule = 'structure';
$moduleCSS = 'structure';
$moduleJS = 'structure';

$breadcrumb = [
    ['title' => 'Cấu trúc thiết bị']
];

// Include config files trước
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('structure', 'view');

$pageActions = '';
if (hasPermission('structure', 'create')) {
    $pageActions = '
    <div class="btn-group" role="group">
        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-plus me-1"></i> Thêm mới
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="views/industries.php"><i class="fas fa-industry me-2"></i>Ngành</a></li>
            <li><a class="dropdown-item" href="views/workshops.php"><i class="fas fa-building me-2"></i>Xưởng</a></li>
            <li><a class="dropdown-item" href="views/lines.php"><i class="fas fa-stream me-2"></i>Line sản xuất</a></li>
            <li><a class="dropdown-item" href="views/areas.php"><i class="fas fa-map-marked me-2"></i>Khu vực</a></li>
            <li><a class="dropdown-item" href="views/machine_types.php"><i class="fas fa-cogs me-2"></i>Dòng máy</a></li>
            <li><a class="dropdown-item" href="views/equipment_groups.php"><i class="fas fa-layer-group me-2"></i>Cụm thiết bị</a></li>
        </ul>
    </div>
    <button type="button" class="btn btn-outline-primary" onclick="expandAll()">
        <i class="fas fa-expand-arrows-alt me-1"></i> Mở rộng tất cả
    </button>
    <button type="button" class="btn btn-outline-secondary" onclick="collapseAll()">
        <i class="fas fa-compress-arrows-alt me-1"></i> Thu gọn tất cả
    </button>';
}

// Include header sau khi đã có config
require_once '../../includes/header.php';

// Lấy cấu trúc thiết bị - sử dụng try-catch để debug
try {
    // Kiểm tra kết nối database
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }
    
    // Lấy dữ liệu cơ bản trước
    $industries = $db->fetchAll("SELECT * FROM industries WHERE status = 'active' ORDER BY name");
    
    // Tạo cấu trúc đơn giản
    $structure = [];
    foreach ($industries as $industry) {
        $structure[$industry['id']] = [
            'info' => $industry,
            'workshops' => []
        ];
        
        // Lấy workshops cho từng industry
        $workshops = $db->fetchAll("SELECT * FROM workshops WHERE industry_id = ? AND status = 'active' ORDER BY name", [$industry['id']]);
        foreach ($workshops as $workshop) {
            $structure[$industry['id']]['workshops'][$workshop['id']] = [
                'info' => $workshop,
                'lines' => []
            ];
        }
    }
    
} catch (Exception $e) {
    error_log("Structure error: " . $e->getMessage());
    $structure = [];
    $error_message = "Có lỗi khi tải dữ liệu: " . $e->getMessage();
}
?>

<div class="row">
    <!-- Tree Structure -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Cây cấu trúc thiết bị</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="refreshStructure()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="exportStructure()">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <div id="structureTree" class="structure-tree">
                    <?php if (empty($structure)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-sitemap text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">Chưa có cấu trúc thiết bị</h5>
                            <p class="text-muted">Bắt đầu bằng cách thêm ngành sản xuất</p>
                            <?php if (hasPermission('structure', 'create')): ?>
                            <a href="views/industries.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i> Thêm ngành
                            </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($structure as $industryId => $industryData): ?>
                            <div class="tree-node" data-level="0" data-type="industry" data-id="<?php echo $industryId; ?>">
                                <div class="tree-item">
                                    <div class="tree-toggle">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                    <div class="tree-icon">
                                        <i class="fas fa-industry text-primary"></i>
                                    </div>
                                    <div class="tree-label">
                                        <strong><?php echo htmlspecialchars($industryData['info']['name']); ?></strong>
                                        <span class="badge badge-secondary ms-2"><?php echo htmlspecialchars($industryData['info']['code']); ?></span>
                                    </div>
                                    <div class="tree-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editItem('industry', <?php echo $industryId; ?>)" title="Chỉnh sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (hasPermission('structure', 'delete')): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('industry', <?php echo $industryId; ?>)" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="tree-children">
                                    <?php if (!empty($industryData['workshops'])): ?>
                                        <?php foreach ($industryData['workshops'] as $workshopId => $workshopData): ?>
                                            <div class="tree-node" data-level="1" data-type="workshop" data-id="<?php echo $workshopId; ?>">
                                                <div class="tree-item">
                                                    <div class="tree-toggle">
                                                        <i class="fas fa-chevron-right"></i>
                                                    </div>
                                                    <div class="tree-icon">
                                                        <i class="fas fa-building text-info"></i>
                                                    </div>
                                                    <div class="tree-label">
                                                        <?php echo htmlspecialchars($workshopData['info']['name']); ?>
                                                        <span class="badge badge-info ms-2"><?php echo htmlspecialchars($workshopData['info']['code']); ?></span>
                                                    </div>
                                                    <div class="tree-actions">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editItem('workshop', <?php echo $workshopId; ?>)" title="Chỉnh sửa">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (hasPermission('structure', 'delete')): ?>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('workshop', <?php echo $workshopId; ?>)" title="Xóa">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="tree-children">
                                                    <div class="tree-node" data-level="2">
                                                        <div class="tree-item">
                                                            <div class="tree-icon">
                                                                <i class="fas fa-info-circle text-muted"></i>
                                                            </div>
                                                            <div class="tree-label">
                                                                <em class="text-muted">Chưa có line sản xuất</em>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="tree-node" data-level="1">
                                            <div class="tree-item">
                                                <div class="tree-icon">
                                                    <i class="fas fa-info-circle text-muted"></i>
                                                </div>
                                                <div class="tree-label">
                                                    <em class="text-muted">Chưa có xưởng</em>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats & Actions -->
    <div class="col-lg-4">
        <!-- Stats -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Thống kê cấu trúc</h6>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stats = [
                        'industries' => $db->fetch("SELECT COUNT(*) as count FROM industries WHERE status = 'active'")['count'],
                        'workshops' => $db->fetch("SELECT COUNT(*) as count FROM workshops WHERE status = 'active'")['count'],
                        'lines' => $db->fetch("SELECT COUNT(*) as count FROM production_lines WHERE status = 'active'")['count'],
                        'areas' => $db->fetch("SELECT COUNT(*) as count FROM areas WHERE status = 'active'")['count'],
                        'machine_types' => $db->fetch("SELECT COUNT(*) as count FROM machine_types WHERE status = 'active'")['count'],
                        'equipment_groups' => $db->fetch("SELECT COUNT(*) as count FROM equipment_groups WHERE status = 'active'")['count']
                    ];
                } catch (Exception $e) {
                    $stats = ['industries' => 0, 'workshops' => 0, 'lines' => 0, 'areas' => 0, 'machine_types' => 0, 'equipment_groups' => 0];
                }
                ?>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="text-center p-2 bg-primary bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-primary"><?php echo $stats['industries']; ?></div>
                            <small class="text-muted">Ngành</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2 bg-info bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-info"><?php echo $stats['workshops']; ?></div>
                            <small class="text-muted">Xưởng</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2 bg-success bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-success"><?php echo $stats['lines']; ?></div>
                            <small class="text-muted">Line</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2 bg-warning bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-warning"><?php echo $stats['areas']; ?></div>
                            <small class="text-muted">Khu vực</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2 bg-danger bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-danger"><?php echo $stats['machine_types']; ?></div>
                            <small class="text-muted">Dòng máy</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-2 bg-secondary bg-opacity-10 rounded">
                            <div class="h4 mb-1 text-secondary"><?php echo $stats['equipment_groups']; ?></div>
                            <small class="text-muted">Cụm TB</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Thao tác nhanh</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (hasPermission('structure', 'create')): ?>
                    <a href="views/industries.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-industry me-2"></i>Quản lý ngành
                    </a>
                    <a href="views/workshops.php" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-building me-2"></i>Quản lý xưởng
                    </a>
                    <a href="views/lines.php" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-building me-2"></i>Quản lý Lines
                    </a>
                    <a href="views/machine_types.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-cogs me-2"></i>Quản lý dòng máy
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('structure', 'export')): ?>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="exportStructure()">
                        <i class="fas fa-download me-2"></i>Xuất Excel
                    </button>
                    <?php endif; ?>
                    
                    <a href="/modules/equipment/" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-right me-2"></i>Đến quản lý thiết bị
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '
<script>
// Enhanced tree functionality with animations
document.addEventListener("DOMContentLoaded", function() {
    // Tree toggle functionality with smooth animations
    document.addEventListener("click", function(e) {
        if (e.target.closest(".tree-toggle")) {
            e.preventDefault();
            const node = e.target.closest(".tree-node");
            const children = node.querySelector(".tree-children");
            const toggle = node.querySelector(".tree-toggle i");
            
            if (node.classList.contains("expanded")) {
                // Collapse
                if (children) {
                    children.style.maxHeight = children.scrollHeight + "px";
                    children.offsetHeight; // Force reflow
                    children.style.maxHeight = "0";
                    children.style.opacity = "0";
                }
                
                node.classList.remove("expanded");
                if (toggle) {
                    toggle.style.transform = "rotate(0deg)";
                    toggle.style.color = "#64748b";
                }
                
                setTimeout(() => {
                    if (children) children.style.display = "none";
                }, 300);
            } else {
                // Expand
                node.classList.add("expanded");
                if (toggle) {
                    toggle.style.transform = "rotate(90deg)";
                    toggle.style.color = "#1e3a8a";
                }
                
                if (children) {
                    children.style.display = "block";
                    children.style.maxHeight = "0";
                    children.style.opacity = "0";
                    children.offsetHeight; // Force reflow
                    children.style.maxHeight = children.scrollHeight + "px";
                    children.style.opacity = "1";
                    
                    setTimeout(() => {
                        children.style.maxHeight = "none";
                    }, 300);
                }
            }
            
            saveTreeState();
        }
    });
    
    // Load saved tree state
    loadTreeState();
    
    // Auto-expand first level for better UX
    setTimeout(() => {
        const firstLevelNodes = document.querySelectorAll(\'.tree-node[data-level="0"]\');
        firstLevelNodes.forEach(node => {
            if (!node.classList.contains("expanded")) {
                const toggle = node.querySelector(".tree-toggle");
                if (toggle) toggle.click();
            }
        });
    }, 500);
});

// Global functions with improved UX
function expandAll() {
    const nodes = document.querySelectorAll(".tree-node");
    nodes.forEach((node, index) => {
        setTimeout(() => {
            if (!node.classList.contains("expanded")) {
                const toggle = node.querySelector(".tree-toggle");
                if (toggle) toggle.click();
            }
        }, index * 50); // Stagger animation
    });
}

function collapseAll() {
    const nodes = Array.from(document.querySelectorAll(".tree-node.expanded")).reverse();
    nodes.forEach((node, index) => {
        setTimeout(() => {
            const toggle = node.querySelector(".tree-toggle");
            if (toggle) toggle.click();
        }, index * 30);
    });
}

function refreshStructure() {
    // Add loading animation
    const tree = document.getElementById("structureTree");
    if (tree) {
        tree.style.opacity = "0.5";
        tree.style.transform = "scale(0.98)";
    }
    
    setTimeout(() => {
        window.location.reload();
    }, 300);
}

function exportStructure() {
    showNotification("Chức năng xuất Excel đang được phát triển", "info");
}

function editItem(type, id) {
    showNotification(`Chỉnh sửa ${type} ID: ${id} - Chức năng đang được phát triển`, "info");
}

function deleteItem(type, id) {
    if (confirm("Bạn có chắc chắn muốn xóa " + type + " này?\\nHành động này không thể hoàn tác.")) {
        showNotification(`Xóa ${type} ID: ${id} - Chức năng đang được phát triển`, "warning");
    }
}

// Tree state persistence
function saveTreeState() {
    const expandedNodes = [];
    document.querySelectorAll(".tree-node.expanded").forEach(node => {
        const type = node.dataset.type;
        const id = node.dataset.id;
        if (type && id) {
            expandedNodes.push(`${type}-${id}`);
        }
    });
    localStorage.setItem("cmms_structure_expanded", JSON.stringify(expandedNodes));
}

function loadTreeState() {
    const saved = localStorage.getItem("cmms_structure_expanded");
    if (!saved) return;
    
    try {
        const expandedNodes = JSON.parse(saved);
        expandedNodes.forEach(nodeKey => {
            const [type, id] = nodeKey.split("-");
            const node = document.querySelector(`[data-type="${type}"][data-id="${id}"]`);
            if (node && !node.classList.contains("expanded")) {
                setTimeout(() => {
                    const toggle = node.querySelector(".tree-toggle");
                    if (toggle) toggle.click();
                }, 100);
            }
        });
    } catch (e) {
        console.error("Error loading tree state:", e);
    }
}

// Notification system
function showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 1055;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    `;
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.remove("show");
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.parentElement.removeChild(notification);
                }
            }, 150);
        }
    }, 5000);
}

// Search functionality (bonus feature)
function searchTree(query) {
    const nodes = document.querySelectorAll(".tree-node");
    const searchTerm = query.toLowerCase().trim();
    
    if (!searchTerm) {
        nodes.forEach(node => {
            node.style.display = "";
            node.classList.remove("search-highlight");
        });
        return;
    }
    
    let hasMatches = false;
    nodes.forEach(node => {
        const label = node.querySelector(".tree-label");
        const text = label ? label.textContent.toLowerCase() : "";
        const isMatch = text.includes(searchTerm);
        
        if (isMatch) {
            node.style.display = "";
            node.classList.add("search-highlight");
            hasMatches = true;
            
            // Expand parent nodes
            let parent = node.parentElement.closest(".tree-node");
            while (parent) {
                if (!parent.classList.contains("expanded")) {
                    const toggle = parent.querySelector(".tree-toggle");
                    if (toggle) toggle.click();
                }
                parent = parent.parentElement.closest(".tree-node");
            }
        } else {
            node.style.display = "none";
            node.classList.remove("search-highlight");
        }
    });
    
    if (!hasMatches) {
        showNotification("Không tìm thấy kết quả phù hợp", "info");
    }
}

// Keyboard shortcuts
document.addEventListener("keydown", function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case "e":
                e.preventDefault();
                expandAll();
                break;
            case "c":
                e.preventDefault();
                collapseAll();
                break;
            case "r":
                e.preventDefault();
                refreshStructure();
                break;
        }
    }
});

// Add CSS for search highlight
const style = document.createElement("style");
style.textContent = `
    .search-highlight .tree-item {
        background-color: #fef3c7 !important;
        border-left: 3px solid #f59e0b !important;
    }
    
    .tree-children {
        transition: max-height 0.3s ease, opacity 0.3s ease;
        overflow: hidden;
    }
`;
document.head.appendChild(style);
</script>';

// Show keyboard shortcuts hint
$pageScripts .= '
<script>
// Add keyboard shortcuts tooltip
setTimeout(() => {
    const hint = document.createElement("div");
    hint.className = "position-fixed bg-dark text-white p-2 rounded";
    hint.style.cssText = "bottom: 20px; left: 20px; font-size: 0.75rem; z-index: 1000; opacity: 0.8;";
    hint.innerHTML = "💡 Phím tắt: Ctrl+E (mở rộng), Ctrl+C (thu gọn), Ctrl+R (làm mới)";
    document.body.appendChild(hint);
    
    setTimeout(() => {
        if (hint.parentElement) {
            hint.style.opacity = "0";
            setTimeout(() => {
                if (hint.parentElement) {
                    hint.parentElement.removeChild(hint);
                }
            }, 300);
        }
    }, 8000);
}, 2000);
</script>';

require_once '../../includes/footer.php';
?>


