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
            <li><hr class="dropdown-divider"></li>
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

// Lấy cấu trúc thiết bị đầy đủ - sử dụng try-catch để debug
try {
    // Kiểm tra kết nối database
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }
    
    // Lấy dữ liệu đầy đủ
    $industries = $db->fetchAll("SELECT * FROM industries WHERE status = 'active' ORDER BY name");
    
    // Tạo cấu trúc đầy đủ
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
            
            // Lấy production lines cho từng workshop
            $lines = $db->fetchAll("SELECT * FROM production_lines WHERE workshop_id = ? AND status = 'active' ORDER BY name", [$workshop['id']]);
            foreach ($lines as $line) {
                $structure[$industry['id']]['workshops'][$workshop['id']]['lines'][$line['id']] = [
                    'info' => $line,
                    'areas' => []
                ];
                
                // Lấy areas cho từng line
                $areas = $db->fetchAll("SELECT * FROM areas WHERE workshop_id = ? AND status = 'active' ORDER BY name", [$workshop['id']]);
                foreach ($areas as $area) {
                    $structure[$industry['id']]['workshops'][$workshop['id']]['lines'][$line['id']]['areas'][$area['id']] = [
                        'info' => $area
                    ];
                }
            }
        }
    }
    
    // Lấy machine types và equipment groups (độc lập với cấu trúc địa lý)
    $machineTypes = $db->fetchAll("SELECT * FROM machine_types WHERE status = 'active' ORDER BY name");
    $machineTypesWithGroups = [];
    foreach ($machineTypes as $machineType) {
        $machineTypesWithGroups[$machineType['id']] = [
            'info' => $machineType,
            'groups' => []
        ];
        
        // Lấy equipment groups cho từng machine type
        $groups = $db->fetchAll("SELECT * FROM equipment_groups WHERE machine_type_id = ? AND status = 'active' ORDER BY name", [$machineType['id']]);
        foreach ($groups as $group) {
            $machineTypesWithGroups[$machineType['id']]['groups'][$group['id']] = [
                'info' => $group
            ];
        }
    }
    
} catch (Exception $e) {
    error_log("Structure error: " . $e->getMessage());
    $structure = [];
    $machineTypesWithGroups = [];
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
                    <?php if (empty($structure) && empty($machineTypesWithGroups)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-sitemap text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">Chưa có cấu trúc thiết bị</h5>
                            <p class="text-muted">Bắt đầu bằng cách thêm ngành sản xuất hoặc dòng máy</p>
                            <?php if (hasPermission('structure', 'create')): ?>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="views/industries.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> Thêm ngành
                                </a>
                                <a href="views/machine_types.php" class="btn btn-outline-primary">
                                    <i class="fas fa-cogs me-1"></i> Thêm dòng máy
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        
                        <!-- Cấu trúc địa lý (Industry > Workshop > Line > Area) -->
                        <?php if (!empty($structure)): ?>
                        <div class="tree-section">
                            <div class="tree-section-header">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-map-marked-alt me-2"></i>
                                    Cấu trúc địa lý
                                </h6>
                            </div>
                            
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
                                            <span class="badge badge-primary ms-2"><?php echo htmlspecialchars($industryData['info']['code']); ?></span>
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
                                                        <?php if (!empty($workshopData['lines'])): ?>
                                                            <?php foreach ($workshopData['lines'] as $lineId => $lineData): ?>
                                                                <div class="tree-node" data-level="2" data-type="line" data-id="<?php echo $lineId; ?>">
                                                                    <div class="tree-item">
                                                                        <div class="tree-toggle">
                                                                            <i class="fas fa-chevron-right"></i>
                                                                        </div>
                                                                        <div class="tree-icon">
                                                                            <i class="fas fa-stream text-success"></i>
                                                                        </div>
                                                                        <div class="tree-label">
                                                                            <?php echo htmlspecialchars($lineData['info']['name']); ?>
                                                                            <span class="badge badge-success ms-2"><?php echo htmlspecialchars($lineData['info']['code']); ?></span>
                                                                        </div>
                                                                        <div class="tree-actions">
                                                                            <button class="btn btn-sm btn-outline-primary" onclick="editItem('line', <?php echo $lineId; ?>)" title="Chỉnh sửa">
                                                                                <i class="fas fa-edit"></i>
                                                                            </button>
                                                                            <?php if (hasPermission('structure', 'delete')): ?>
                                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('line', <?php echo $lineId; ?>)" title="Xóa">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="tree-children">
                                                                        <?php if (!empty($lineData['areas'])): ?>
                                                                            <?php foreach ($lineData['areas'] as $areaId => $areaData): ?>
                                                                                <div class="tree-node" data-level="3" data-type="area" data-id="<?php echo $areaId; ?>">
                                                                                    <div class="tree-item">
                                                                                        <div class="tree-icon">
                                                                                            <i class="fas fa-map-marker-alt text-warning"></i>
                                                                                        </div>
                                                                                        <div class="tree-label">
                                                                                            <?php echo htmlspecialchars($areaData['info']['name']); ?>
                                                                                            <span class="badge badge-warning ms-2"><?php echo htmlspecialchars($areaData['info']['code']); ?></span>
                                                                                        </div>
                                                                                        <div class="tree-actions">
                                                                                            <button class="btn btn-sm btn-outline-primary" onclick="editItem('area', <?php echo $areaId; ?>)" title="Chỉnh sửa">
                                                                                                <i class="fas fa-edit"></i>
                                                                                            </button>
                                                                                            <?php if (hasPermission('structure', 'delete')): ?>
                                                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('area', <?php echo $areaId; ?>)" title="Xóa">
                                                                                                <i class="fas fa-trash"></i>
                                                                                            </button>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <div class="tree-node" data-level="3">
                                                                                <div class="tree-item">
                                                                                    <div class="tree-icon">
                                                                                        <i class="fas fa-info-circle text-muted"></i>
                                                                                    </div>
                                                                                    <div class="tree-label">
                                                                                        <em class="text-muted">Chưa có khu vực</em>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
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
                                                        <?php endif; ?>
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
                        </div>
                        
                        <!-- Divider -->
                        <hr class="my-4">
                        <?php endif; ?>
                        
                        <!-- Cấu trúc dòng máy (Machine Types > Equipment Groups) -->
                        <?php if (!empty($machineTypesWithGroups)): ?>
                        <div class="tree-section">
                            <div class="tree-section-header">
                                <h6 class="text-danger mb-3">
                                    <i class="fas fa-cogs me-2"></i>
                                    Cấu trúc dòng máy & cụm thiết bị
                                </h6>
                            </div>
                            
                            <?php foreach ($machineTypesWithGroups as $machineTypeId => $machineTypeData): ?>
                                <div class="tree-node" data-level="0" data-type="machine_type" data-id="<?php echo $machineTypeId; ?>">
                                    <div class="tree-item">
                                        <div class="tree-toggle">
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                        <div class="tree-icon">
                                            <i class="fas fa-cogs text-danger"></i>
                                        </div>
                                        <div class="tree-label">
                                            <strong><?php echo htmlspecialchars($machineTypeData['info']['name']); ?></strong>
                                            <span class="badge badge-danger ms-2"><?php echo htmlspecialchars($machineTypeData['info']['code']); ?></span>
                                        </div>
                                        <div class="tree-actions">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editItem('machine_type', <?php echo $machineTypeId; ?>)" title="Chỉnh sửa">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (hasPermission('structure', 'delete')): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('machine_type', <?php echo $machineTypeId; ?>)" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="tree-children">
                                        <?php if (!empty($machineTypeData['groups'])): ?>
                                            <?php foreach ($machineTypeData['groups'] as $groupId => $groupData): ?>
                                                <div class="tree-node" data-level="1" data-type="equipment_group" data-id="<?php echo $groupId; ?>">
                                                    <div class="tree-item">
                                                        <div class="tree-icon">
                                                            <i class="fas fa-layer-group text-secondary"></i>
                                                        </div>
                                                        <div class="tree-label">
                                                            <?php echo htmlspecialchars($groupData['info']['name']); ?>
                                                            <span class="badge badge-secondary ms-2"><?php echo htmlspecialchars($groupData['info']['code']); ?></span>
                                                        </div>
                                                        <div class="tree-actions">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editItem('equipment_group', <?php echo $groupId; ?>)" title="Chỉnh sửa">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <?php if (hasPermission('structure', 'delete')): ?>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('equipment_group', <?php echo $groupId; ?>)" title="Xóa">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                            <?php endif; ?>
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
                                                        <em class="text-muted">Chưa có cụm thiết bị</em>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($structure) && empty($machineTypesWithGroups)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-sitemap text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">Chưa có dữ liệu cấu trúc</h5>
                            <p class="text-muted">Hệ thống hiển thị 2 loại cấu trúc: cấu trúc địa lý và cấu trúc dòng máy</p>
                        </div>
                        <?php endif; ?>
                        
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
                            <div class="h4 mb-1 text-white"><?php echo $stats['industries']; ?></div>
                            <small class="text-white">Ngành</small>
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
                    <h6 class="text-primary mb-2">Cấu trúc địa lý:</h6>
                    <a href="views/industries.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-industry me-2"></i>Quản lý ngành
                    </a>
                    <a href="views/workshops.php" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-building me-2"></i>Quản lý xưởng
                    </a>
                    <a href="views/lines.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-stream me-2"></i>Quản lý Lines
                    </a>
                    <a href="views/areas.php" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-map-marked-alt me-2"></i>Quản lý khu vực
                    </a>
                    
                    <hr class="my-2">
                    <h6 class="text-danger mb-2">Cấu trúc dòng máy:</h6>
                    <a href="views/machine_types.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-cogs me-2"></i>Quản lý dòng máy
                    </a>
                    <a href="views/equipment_groups.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-layer-group me-2"></i>Quản lý cụm thiết bị
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('structure', 'export')): ?>
                    <hr class="my-2">
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

<!-- CSS cho tree sections -->
<style>
.tree-section {
    margin-bottom: 2rem;
}

.tree-section-header h6 {
    font-weight: 600;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border-radius: 0.5rem;
    border-left: 4px solid currentColor;
}

.tree-node[data-level="0"][data-type="industry"] > .tree-item {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border-left: 4px solid #3b82f6;
}

.tree-node[data-level="0"][data-type="machine_type"] > .tree-item {
    background: linear-gradient(135deg, #fef2f2, #fecaca);
    border-left: 4px solid #ef4444;
}

.tree-node[data-level="1"][data-type="workshop"] > .tree-item {
    background: rgba(6, 182, 212, 0.05);
    border-left: 3px solid #06b6d4;
}

.tree-node[data-level="2"][data-type="line"] > .tree-item {
    background: rgba(16, 185, 129, 0.05);
    border-left: 3px solid #10b981;
}

.tree-node[data-level="3"][data-type="area"] > .tree-item {
    background: rgba(245, 158, 11, 0.05);
    border-left: 3px solid #f59e0b;
}

.tree-node[data-level="1"][data-type="equipment_group"] > .tree-item {
    background: rgba(107, 114, 128, 0.05);
    border-left: 3px solid #6b7280;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .tree-section-header h6 {
        font-size: 0.9rem;
        padding: 0.4rem 0.8rem;
    }
}
</style>

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
    // Redirect to appropriate edit page
    const typeMap = {
        "industry": "industries",
        "workshop": "workshops", 
        "line": "lines",
        "area": "areas",
        "machine_type": "machine_types",
        "equipment_group": "equipment_groups"
    };
    
    if (typeMap[type]) {
        window.location.href = `views/${typeMap[type]}.php?edit=${id}`;
    } else {
        showNotification(`Chỉnh sửa ${type} ID: ${id} - Chức năng đang được phát triển`, "info");
    }
}

function deleteItem(type, id) {
    if (confirm("Bạn có chắc chắn muốn xóa " + type + " này?\\nHành động này không thể hoàn tác.")) {
        // Here you would implement actual deletion
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

// Search functionality
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

// Add CSS for search highlight and improved styling
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
    
    .tree-section:not(:last-child) {
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 1rem;
    }
`;
document.head.appendChild(style);
</script>';

// Show info about dual structure system
$pageScripts .= '
<script>
// Add info tooltip about the dual structure system
setTimeout(() => {
    if (document.querySelector(".tree-section")) {
        const infoHint = document.createElement("div");
        infoHint.className = "position-fixed bg-info text-white p-3 rounded shadow";
        infoHint.style.cssText = "bottom: 20px; left: 20px; font-size: 0.8rem; z-index: 1000; max-width: 300px;";
        infoHint.innerHTML = `
            <div class="d-flex align-items-start">
                <i class="fas fa-info-circle me-2 mt-1"></i>
                <div>
                    <strong>Hệ thống cấu trúc kép:</strong><br>
                    • <em>Cấu trúc địa lý:</em> Ngành → Xưởng → Line → Khu vực<br>
                    • <em>Cấu trúc dòng máy:</em> Dòng máy → Cụm thiết bị<br>
                    <small class="opacity-75">Thiết bị có thể thuộc cả hai loại cấu trúc</small>
                </div>
                <button type="button" class="btn-close btn-close-white ms-2" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        document.body.appendChild(infoHint);
        
        // Auto remove after 15 seconds
        setTimeout(() => {
            if (infoHint.parentElement) {
                infoHint.style.opacity = "0";
                setTimeout(() => {
                    if (infoHint.parentElement) {
                        infoHint.parentElement.removeChild(infoHint);
                    }
                }, 300);
            }
        }, 15000);
    }
}, 3000);
</script>';

require_once '../../includes/footer.php';
?>