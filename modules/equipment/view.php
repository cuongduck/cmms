<?php
/**
 * Equipment View Details - modules/equipment/view.php
 * Refactored for better maintainability
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền và lấy ID
requirePermission('equipment', 'view');
$equipmentId = (int)($_GET['id'] ?? 0);

if (!$equipmentId) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Class để xử lý equipment data
class EquipmentViewHandler {
    private $db;
    private $equipmentId;
    private $equipment;
    
    public function __construct($db, $equipmentId) {
        $this->db = $db;
        $this->equipmentId = $equipmentId;
        $this->loadEquipmentData();
    }
    
    private function loadEquipmentData() {
        $sql = "SELECT e.*, 
                       i.name as industry_name, i.code as industry_code,
                       w.name as workshop_name, w.code as workshop_code,
                       pl.name as line_name, pl.code as line_code,
                       a.name as area_name, a.code as area_code,
                       mt.name as machine_type_name, mt.code as machine_type_code,
                       eg.name as equipment_group_name,
                       u1.full_name as owner_name, u1.email as owner_email, u1.phone as owner_phone,
                       u2.full_name as backup_owner_name, u2.email as backup_owner_email, u2.phone as backup_owner_phone,
                       u3.full_name as created_by_name
                FROM equipment e
                LEFT JOIN industries i ON e.industry_id = i.id
                LEFT JOIN workshops w ON e.workshop_id = w.id
                LEFT JOIN production_lines pl ON e.line_id = pl.id
                LEFT JOIN areas a ON e.area_id = a.id
                LEFT JOIN machine_types mt ON e.machine_type_id = mt.id
                LEFT JOIN equipment_groups eg ON e.equipment_group_id = eg.id
                LEFT JOIN users u1 ON e.owner_user_id = u1.id
                LEFT JOIN users u2 ON e.backup_owner_user_id = u2.id
                LEFT JOIN users u3 ON e.created_by = u3.id
                WHERE e.id = ?";
        
        $this->equipment = $this->db->fetch($sql, [$this->equipmentId]);
        
        if (!$this->equipment) {
            header('HTTP/1.0 404 Not Found');
            include '../../errors/404.php';
            exit;
        }
        
        $this->formatEquipmentData();
    }
    
    private function formatEquipmentData() {
        // Format dates
        $this->equipment['created_at_formatted'] = formatDateTime($this->equipment['created_at'] ?? '');
        $this->equipment['updated_at_formatted'] = formatDateTime($this->equipment['updated_at'] ?? '');
        $this->equipment['installation_date_formatted'] = formatDate($this->equipment['installation_date'] ?? '');
        $this->equipment['warranty_expiry_formatted'] = formatDate($this->equipment['warranty_expiry'] ?? '');
        
        // Status
        $this->equipment['status_text'] = getStatusText($this->equipment['status']);
        $this->equipment['status_class'] = getStatusClass($this->equipment['status']);
        
        // Location path
        $locationParts = array_filter([
            $this->equipment['industry_name'] ?? null,
            $this->equipment['workshop_name'] ?? null,
            $this->equipment['line_name'] ?? null,
            $this->equipment['area_name'] ?? null
        ]);
        $this->equipment['location_path'] = implode(' → ', $locationParts);
        
        // Calculate maintenance
        $this->calculateMaintenanceInfo();
        
        // Format image URLs
        $this->formatImageUrls();
    }
    
    private function calculateMaintenanceInfo() {
        $this->equipment['next_maintenance'] = null;
        $this->equipment['maintenance_due'] = false;
        $this->equipment['days_until_maintenance'] = null;
        
        if (!empty($this->equipment['maintenance_frequency_days']) && !empty($this->equipment['installation_date'])) {
            try {
                $installDate = new DateTime($this->equipment['installation_date']);
                $nextMaintenance = clone $installDate;
                $nextMaintenance->add(new DateInterval('P' . $this->equipment['maintenance_frequency_days'] . 'D'));
                
                $today = new DateTime();
                while ($nextMaintenance <= $today) {
                    $nextMaintenance->add(new DateInterval('P' . $this->equipment['maintenance_frequency_days'] . 'D'));
                }
                
                $this->equipment['next_maintenance'] = $nextMaintenance->format('d/m/Y');
                $this->equipment['days_until_maintenance'] = $today->diff($nextMaintenance)->days;
                $this->equipment['maintenance_due'] = $this->equipment['days_until_maintenance'] <= 7;
            } catch (Exception $e) {
                // Ignore calculation errors
            }
        }
    }
    
    private function formatImageUrls() {
        $this->equipment['image_url'] = null;
        $this->equipment['manual_url'] = null;
        
        if (!empty($this->equipment['image_path'])) {
            $fullPath = BASE_PATH . '/' . ltrim($this->equipment['image_path'], '/');
            if (file_exists($fullPath)) {
                $this->equipment['image_url'] = APP_URL . '/' . ltrim($this->equipment['image_path'], '/');
            }
        }
        
        if (!empty($this->equipment['manual_path'])) {
            $fullPath = BASE_PATH . '/' . ltrim($this->equipment['manual_path'], '/');
            if (file_exists($fullPath)) {
                $this->equipment['manual_url'] = APP_URL . '/' . ltrim($this->equipment['manual_path'], '/');
            }
        }
    }
    
    public function getSettingsImages() {
        $sql = "SELECT id, image_path, title, description, category, sort_order, created_at,
                       (SELECT full_name FROM users WHERE id = equipment_settings_images.created_by) as created_by_name
                FROM equipment_settings_images 
                WHERE equipment_id = ? 
                ORDER BY category, sort_order, created_at DESC";
        
        $images = $this->db->fetchAll($sql, [$this->equipmentId]);
        
        // Format and validate images
        $validImages = [];
        foreach ($images as $image) {
            $relativePath = ltrim($image['image_path'], '/');
            $fullPath = BASE_PATH . '/' . $relativePath;
            
            if (file_exists($fullPath)) {
                $image['image_url'] = APP_URL . '/' . $relativePath;
                $image['created_at_formatted'] = formatDateTime($image['created_at']);
                $validImages[] = $image;
            }
        }
        
        return $validImages;
    }
    
    public function getMaintenanceHistory() {
        // Mock data for now - replace with actual query
        return [
            [
                'date' => '15/08/2024',
                'type' => 'Bảo trì định kỳ',
                'description' => 'Kiểm tra tổng quát, thay dầu máy',
                'technician' => 'Nguyễn Văn A',
                'status' => 'completed',
                'duration' => '2 giờ'
            ]
        ];
    }
    
    public function getEquipment() {
        return $this->equipment;
    }
    public function getEquipmentFiles() {
    $sql = "SELECT ef.*, u.full_name as uploaded_by_name
            FROM equipment_files ef
            LEFT JOIN users u ON ef.uploaded_by = u.id
            WHERE ef.equipment_id = ? AND ef.is_active = 1
            ORDER BY ef.file_type, ef.created_at DESC";
    
    $files = $this->db->fetchAll($sql, [$this->equipmentId]);
    
    // Format file data
    foreach ($files as &$file) {
        $file['created_at_formatted'] = formatDateTime($file['created_at']);
        $file['file_size_formatted'] = $this->formatFileSize($file['file_size']);
        $file['file_url'] = APP_URL . '/' . ltrim($file['file_path'], '/');
        
        // Check if file exists
        $fullPath = BASE_PATH . '/' . ltrim($file['file_path'], '/');
        $file['file_exists'] = file_exists($fullPath);
        
        // Get file icon
        $file['file_icon'] = $this->getFileIcon($file['mime_type'], $file['file_type']);
    }
    
    return $files;
}

private function formatFileSize($bytes) {
    if (!$bytes) return 'N/A';
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

private function getFileIcon($mimeType, $fileType) {
    // Icon based on file type
    $icons = [
        'manual' => 'fas fa-book text-primary',
        'document' => 'fas fa-file-alt text-info', 
        'certificate' => 'fas fa-certificate text-warning',
        'drawing' => 'fas fa-drafting-compass text-success',
        'other' => 'fas fa-file text-secondary'
    ];
    
    if (isset($icons[$fileType])) {
        return $icons[$fileType];
    }
    
    // Icon based on MIME type
    if (strpos($mimeType, 'pdf') !== false) {
        return 'fas fa-file-pdf text-danger';
    } elseif (strpos($mimeType, 'word') !== false) {
        return 'fas fa-file-word text-primary';
    } elseif (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) {
        return 'fas fa-file-excel text-success';
    } elseif (strpos($mimeType, 'image') !== false) {
        return 'fas fa-file-image text-info';
    } else {
        return 'fas fa-file text-secondary';
    }
}
}

// Initialize handler
$handler = new EquipmentViewHandler($db, $equipmentId);
$equipment = $handler->getEquipment();
$settingsImages = $handler->getSettingsImages();
$maintenanceHistory = $handler->getMaintenanceHistory();
$equipmentFiles = $handler->getEquipmentFiles();

// Set page variables
$pageTitle = 'Chi tiết: ' . $equipment['name'];
$currentModule = 'equipment';
$moduleCSS = 'equipment-view';

$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => 'Chi tiết thiết bị']
];

// Build page actions
$pageActions = buildPageActions($equipmentId);

function buildPageActions($equipmentId) {
    $actions = '';
    
    if (hasPermission('equipment', 'edit')) {
        $actions .= '<a href="edit.php?id=' . $equipmentId . '" class="btn btn-primary">
            <i class="fas fa-edit me-1"></i> Chỉnh sửa
        </a> ';
    }
    
    $actions .= '
    <div class="btn-group">
        <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-tools me-1"></i> Thao tác
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" onclick="printEquipment()">
                <i class="fas fa-print me-2"></i>In thông tin
            </a></li>
            <li><a class="dropdown-item" href="#" onclick="generateQR()">
                <i class="fas fa-qrcode me-2"></i>Tạo mã QR
            </a></li>';
            
    if (hasPermission('equipment', 'delete')) {
        $actions .= '
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="deleteEquipment()">
                <i class="fas fa-trash me-2"></i>Xóa thiết bị
            </a></li>';
    }
    
    $actions .= '</ul></div>';
    
    return $actions;
}

require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/<?= $moduleCSS ?>.css?v=<?= time() ?>">
<div class="equipment-view-container">
    <!-- Equipment Header -->
    <div class="equipment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="equipment-code-badge" onclick="copyEquipmentCode()" title="Click để copy mã">
                        <?= htmlspecialchars($equipment['code']) ?>
                    </div>
                    <h1 class="equipment-title"><?= htmlspecialchars($equipment['name']) ?></h1>
                    <div class="equipment-subtitle"><?= htmlspecialchars($equipment['location_path'] ?: 'Chưa xác định vị trí') ?></div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-3">
                        <div class="status-indicator status-<?= $equipment['status'] ?>" 
                             onclick="<?= hasPermission('equipment', 'edit') ? 'changeStatus()' : '' ?>"
                             title="<?= $equipment['status_text'] ?>">
                            <i class="fas fa-circle"></i>
                            <?= $equipment['status_text'] ?>
                        </div>
                    </div>
                    <div class="criticality-badge criticality-<?= strtolower($equipment['criticality']) ?>">
                        <?= $equipment['criticality'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Maintenance Alert -->
        <?php if ($equipment['maintenance_due'] ?? false): ?>
            <div class="alert alert-warning d-flex align-items-center mb-4 fade-in">
                <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                <div>
                    <strong>Cảnh báo bảo trì!</strong>
                    Thiết bị cần được bảo trì trong <strong><?= $equipment['days_until_maintenance'] ?> ngày</strong>
                    (<?= $equipment['next_maintenance'] ?>)
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Basic Information -->
                <?= renderInfoSection('Thông tin cơ bản', 'fas fa-info-circle', [
                    'Nhà sản xuất' => $equipment['manufacturer'] ?: 'Chưa có thông tin',
                    'Model' => $equipment['model'] ?: 'Chưa có thông tin',
                    'Số seri' => $equipment['serial_number'] ?: 'Chưa có thông tin',
                    'Năm sản xuất' => $equipment['manufacture_year'] ?: 'Chưa có thông tin',
                    'Dòng máy' => $equipment['machine_type_name'] ?: 'Chưa có thông tin',
                    'Cụm thiết bị' => $equipment['equipment_group_name'] ?: 'Chưa có thông tin',                    
                    'Ngày lắp đặt' => $equipment['installation_date_formatted'] ?: 'Chưa có thông tin',
                    'Bảo hành đến' => $equipment['warranty_expiry_formatted'] ?: 'Chưa có thông tin'
                ]) ?>

                <!-- Management Information -->
                <?= renderManagementInfo($equipment) ?>

                <!-- Settings Images Slider - CHÍNH SỬA -->
                <?php if (!empty($settingsImages)): ?>
                <div class="info-section settings-images-section slide-up">
                    <div class="info-section-header">
                        <i class="fas fa-images section-icon"></i>
                        <h5>Hình ảnh thông số cài đặt</h5>
                        <span class="badge bg-primary ms-2"><?= count($settingsImages) ?></span>
                        <?php if (hasPermission('equipment', 'edit')): ?>
                        <div class="ms-auto">
                            <button class="btn btn-sm btn-outline-primary" onclick="showSettingsUploadModal()">
                                <i class="fas fa-plus me-1"></i>Thêm ảnh
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="settings-slider-container">
                        <div class="settings-slider" id="settingsSlider">
                            <?php foreach ($settingsImages as $index => $image): ?>
                                <div class="settings-slide" data-index="<?= $index ?>">
                                    <img src="<?= htmlspecialchars($image['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($image['title']) ?>"
                                         onclick="showImageViewer(<?= $index ?>)"
                                         loading="lazy">
                                    
                                    <div class="slide-content">
                                        <div class="slide-title">
                                            <?= htmlspecialchars($image['title'] ?: 'Không có tiêu đề') ?>
                                        </div>
                                        <?php if ($image['description']): ?>
                                            <div class="slide-description">
                                                <?= htmlspecialchars($image['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="slide-meta">
                                            <span class="badge bg-secondary me-2">
                                                <?= getCategoryName($image['category'] ?? 'general') ?>
                                            </span>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?= $image['created_at_formatted'] ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Navigation Buttons -->
                        <?php if (count($settingsImages) > 1): ?>
                            <button class="slider-nav prev" onclick="slideSettings('prev')" title="Trước">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="slider-nav next" onclick="slideSettings('next')" title="Sau">  
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Indicators -->
                    <?php if (count($settingsImages) > 1): ?>
                        <div class="slider-indicators">
                            <?php for ($i = 0; $i < count($settingsImages); $i++): ?>
                                <span class="indicator-dot<?= $i === 0 ? ' active' : '' ?>" 
                                      onclick="goToSlide(<?= $i ?>)" 
                                      data-index="<?= $i ?>"></span>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Technical Specifications -->
                <?php if (!empty($equipment['specifications'])): ?>
                <?= renderInfoSection('Thông số kỹ thuật', 'fas fa-cog', [], $equipment['specifications']) ?>
                <?php endif; ?>
                <!-- Notes -->
                <?php if (!empty($equipment['notes'])): ?>
                <?= renderInfoSection('Ghi chú', 'fas fa-sticky-note', [], $equipment['notes']) ?>
                <?php endif; ?>
                
                <!-- Maintenance History -->
                <?= renderMaintenanceHistory($maintenanceHistory) ?>


            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Equipment Image -->
                <?= renderEquipmentImage($equipment) ?>

                <!-- Quick Actions -->
                <?= renderQuickActions($equipmentId) ?>

                <!-- Files & Documents -->
                <?= renderDocuments($equipmentFiles) ?>

                <!-- QR Code -->
                <?= renderQRSection() ?>

                <!-- Statistics -->
                <?= renderStatistics($equipment, $maintenanceHistory) ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?= renderModals() ?>

<!-- JavaScript -->
<script src="<?= APP_URL ?>/assets/js/equipment-view.js?v=<?= time() ?>"></script>
<script>
// Equipment data cho JavaScript
window.equipmentViewData = {
    equipmentId: <?= $equipmentId ?>,
    equipment: <?= json_encode($equipment, JSON_UNESCAPED_UNICODE) ?>,
    settingsImages: <?= json_encode($settingsImages, JSON_UNESCAPED_UNICODE) ?>,
    equipmentFiles: <?= json_encode($equipmentFiles, JSON_UNESCAPED_UNICODE) ?>, // Thêm dòng này
    permissions: {
        canEdit: <?= json_encode(hasPermission('equipment', 'edit')) ?>,
        canDelete: <?= json_encode(hasPermission('equipment', 'delete')) ?>
    },
    urls: {
        baseUrl: '<?= APP_URL ?>',
        apiUrl: '<?= APP_URL ?>/modules/equipment/api/equipment.php',
        settingsApi: '<?= APP_URL ?>/modules/equipment/api/settings_images.php',
        filesApi: '<?= APP_URL ?>/modules/equipment/api/equipment_files.php' // Thêm dòng này
    }
};


// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (typeof EquipmentView !== 'undefined') {
        EquipmentView.init();
    }
    initializeSettingsSlider();
});
</script>

<?php
// Helper Functions
function renderInfoSection($title, $icon, $data = [], $content = '') {
    ob_start();
    ?>
    <div class="info-section fade-in">
        <div class="info-section-header">
            <i class="<?= $icon ?> section-icon"></i>
            <h5><?= $title ?></h5>
        </div>
        <div class="info-section-body">
            <?php if (!empty($data)): ?>
                <div class="info-grid">
                    <?php foreach ($data as $label => $value): ?>
                        <div class="info-item">
                            <div class="info-label"><?= $label ?></div>
                            <div class="info-value <?= empty($value) || $value === 'Chưa có thông tin' ? 'empty' : '' ?>">
                                <?= htmlspecialchars($value) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($content): ?>
                <div class="p-3 bg-light rounded">
                    <?= nl2br(htmlspecialchars($content)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function renderManagementInfo($equipment) {
    ob_start();
    ?>
    <div class="info-section fade-in">
        <div class="info-section-header">
            <i class="fas fa-users section-icon"></i>
            <h5>Thông tin quản lý</h5>
        </div>
        <div class="info-section-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Người quản lý chính</div>
                    <div class="info-value <?= empty($equipment['owner_name']) ? 'empty' : '' ?>">
                        <?php if ($equipment['owner_name']): ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-user-circle text-primary"></i>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($equipment['owner_name']) ?></div>
                                    <?php if ($equipment['owner_email']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($equipment['owner_email']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            Chưa phân công
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Bộ phận sử dụng</div>
                    <div class="info-value <?= empty($equipment['backup_owner_name']) ? 'empty' : '' ?>">
                        <?php if ($equipment['backup_owner_name']): ?>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-user text-secondary"></i>
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($equipment['backup_owner_name']) ?></div>
                                    <?php if ($equipment['backup_owner_email']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($equipment['backup_owner_email']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            Chưa phân công
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function getCategoryName($category) {
    $categories = [
        'general' => 'Tổng quát',
        'electrical' => 'Điện',  
        'mechanical' => 'Cơ khí',
        'software' => 'Thông số cài đặt',
        'safety' => 'An toàn',
        'maintenance' => 'Bảo trì'
    ];
    return $categories[$category] ?? $category;
}
function renderEquipmentImage($equipment) {
    ob_start();
    ?>
    <div class="info-section equipment-image-section fade-in">
        <?php if ($equipment['image_url']): ?>
            <img src="<?= htmlspecialchars($equipment['image_url']) ?>" 
                 alt="<?= htmlspecialchars($equipment['name']) ?>" 
                 class="main-equipment-image"
                 onclick="showImageModal(this.src)"
                 loading="lazy">
        <?php else: ?>
            <div class="no-image-placeholder">
                <i class="fas fa-image fa-3x text-muted mb-3"></i>
                <div class="text-muted">Chưa có hình ảnh</div>
 
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function renderQuickActions($equipmentId) {
    ob_start();
    ?>
    <div class="info-section fade-in">
        <div class="info-section-header">
            <i class="fas fa-bolt section-icon"></i>
            <h5>Thao tác nhanh</h5>
        </div>
        <div class="info-section-body">
            <div class="d-grid gap-2">
                <?php if (hasPermission('equipment', 'edit')): ?>
                <a href="edit.php?id=<?= $equipmentId ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i>Chỉnh sửa thiết bị
                </a>
                <button type="button" class="btn btn-outline-info" onclick="showSettingsUploadModal()">
                    <i class="fas fa-images me-2"></i>Thêm ảnh thông số
                </button>
                 <?php endif; ?>
                
                <button type="button" class="btn btn-outline-success" onclick="createMaintenanceSchedule()">
                    <i class="fas fa-calendar-plus me-2"></i>Lên lịch bảo trì
                </button>

                

                
                <?php if (hasPermission('equipment', 'delete')): ?>
                <hr class="my-2">
                <button type="button" class="btn btn-outline-danger" onclick="deleteEquipment()">
                    <i class="fas fa-trash me-2"></i>Xóa thiết bị
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


// Cập nhật function renderDocuments trong view.php

function renderDocuments($equipmentFiles) {
    ob_start();
    ?>
    <div class="info-section fade-in">
        <div class="info-section-header">
            <i class="fas fa-folder section-icon"></i>
            <h5>Tài liệu & File</h5>
            <?php if (!empty($equipmentFiles)): ?>
                <span class="badge bg-primary ms-2"><?= count($equipmentFiles) ?></span>
            <?php endif; ?>
            <?php if (hasPermission('equipment', 'edit')): ?>
            <div class="ms-auto">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="showFileUploadModal()">
                    <i class="fas fa-plus me-1"></i>Thêm file
                </button>
            </div>
            <?php endif; ?>
        </div>
        <div class="info-section-body">
            <?php if (!empty($equipmentFiles)): ?>
                <div class="files-list">
                    <?php 
                    // Nhóm files theo loại
                    $filesByType = [];
                    foreach ($equipmentFiles as $file) {
                        $filesByType[$file['file_type']][] = $file;
                    }
                    
                    $typeNames = [
                        'manual' => 'Hướng dẫn sử dụng',
                        'document' => 'Tài liệu',
                        'certificate' => 'Chứng nhận',
                        'drawing' => 'Bản vẽ kỹ thuật',
                        'other' => 'Khác'
                    ];
                    ?>
                    
                    <?php foreach ($filesByType as $type => $files): ?>
                        <div class="file-type-group mb-4">
                            <h6 class="text-muted text-uppercase small fw-bold mb-2 d-flex align-items-center">
                                <span class="file-type-badge type-<?= $type ?> me-2">
                                    <?= $typeNames[$type] ?? $type ?>
                                </span>
                                <span class="text-muted">(<?= count($files) ?>)</span>
                            </h6>
                            
                            <?php foreach ($files as $file): ?>
                                <div class="file-item mb-2">
                                    <?php if ($file['file_exists']): ?>
                                        <div class="file-link" onclick="viewFile('<?= htmlspecialchars($file['file_url']) ?>', '<?= htmlspecialchars($file['file_name']) ?>')">
                                            <div class="file-info">
                                                <i class="<?= getFileIcon($file['mime_type'], $file['file_type']) ?> me-2"></i>
                                                <div class="file-details">
                                                    <div class="file-name fw-medium">
                                                        <?= htmlspecialchars($file['file_name']) ?>
                                                        <?php if ($file['version'] && $file['version'] !== '1.0'): ?>
                                                            <small class="badge bg-secondary ms-1">v<?= htmlspecialchars($file['version']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($file['description']): ?>
                                                        <div class="file-description text-muted small">
                                                            <?= htmlspecialchars($file['description']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="file-meta text-muted small">
                                                        <?= $file['file_size_formatted'] ?> • 
                                                        <?= $file['created_at_formatted'] ?>
                                                        <?php if ($file['uploaded_by_name']): ?>
                                                            • <?= htmlspecialchars($file['uploaded_by_name']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="file-actions d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="event.stopPropagation(); downloadFile('<?= htmlspecialchars($file['file_url']) ?>', '<?= htmlspecialchars($file['file_name']) ?>')" 
                                                        title="Tải xuống">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <?php if (hasPermission('equipment', 'delete')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="event.stopPropagation(); deleteEquipmentFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['file_name']) ?>')" 
                                                        title="Xóa">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="file-link file-missing">
                                            <div class="file-info">
                                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                                <div class="file-details">
                                                    <div class="file-name"><?= htmlspecialchars($file['file_name']) ?></div>
                                                    <div class="text-warning small">File không tìm thấy</div>
                                                </div>
                                            </div>
                                            <?php if (hasPermission('equipment', 'delete')): ?>
                                            <div class="file-actions">
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteEquipmentFile(<?= $file['id'] ?>, '<?= htmlspecialchars($file['file_name']) ?>')" 
                                                        title="Xóa record">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                    <p class="mb-2">Chưa có tài liệu</p>
                    <?php if (hasPermission('equipment', 'edit')): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="showFileUploadModal()">
                            <i class="fas fa-upload me-2"></i>Upload tài liệu đầu tiên
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function getFileIcon($mimeType, $fileType) {
    // Icon based on file type first
    $icons = [
        'manual' => 'fas fa-book text-primary',
        'document' => 'fas fa-file-alt text-info', 
        'certificate' => 'fas fa-certificate text-warning',
        'drawing' => 'fas fa-drafting-compass text-success',
        'other' => 'fas fa-file text-secondary'
    ];
    
    if (isset($icons[$fileType])) {
        return $icons[$fileType];
    }
    
    // Icon based on MIME type
    if (strpos($mimeType, 'pdf') !== false) {
        return 'fas fa-file-pdf text-danger';
    } elseif (strpos($mimeType, 'word') !== false) {
        return 'fas fa-file-word text-primary';
    } elseif (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) {
        return 'fas fa-file-excel text-success';
    } elseif (strpos($mimeType, 'image') !== false) {
        return 'fas fa-file-image text-info';
    } else {
        return 'fas fa-file text-secondary';
    }
}
function renderQRSection() {
    ob_start();
    ?>
    <div class="info-section fade-in">
        <div class="info-section-header">
            <i class="fas fa-qrcode section-icon"></i>
            <h5>Mã QR</h5>
        </div>
        <div class="info-section-body">
            <div class="qr-code-section text-center">
                <div id="qrCodeContainer">
                    <div class="qr-placeholder mb-3">
                        <i class="fas fa-qrcode fa-3x text-muted"></i>
                    </div>
                    <p class="text-muted small mb-3">Mã QR để truy cập nhanh thông tin thiết bị</p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="generateQR()">
                        <i class="fas fa-magic me-1"></i>Tạo mã QR
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function renderStatistics($equipment, $maintenanceHistory) {
    $completedMaintenance = count(array_filter($maintenanceHistory, function($m) { 
        return $m['status'] === 'completed'; 
    }));
    
    $operatingDays = 'N/A';
    if (!empty($equipment['installation_date'])) {
        try {
            $installDate = new DateTime($equipment['installation_date']);
            $today = new DateTime();
            $operatingDays = $installDate->diff($today)->days;
        } catch (Exception $e) {
            $operatingDays = 'N/A';
        }
    }
    
    $efficiency = $equipment['status'] === 'active' ? '100%' : 
                 ($equipment['status'] === 'maintenance' ? '80%' : '0%');
    
    ob_start();
    ?>
    <div class="info-section fade-in">
        <div class="info-section-header">
            <i class="fas fa-chart-bar section-icon"></i>
            <h5>Thống kê</h5>
        </div>
        <div class="info-section-body">
            <div class="row g-2 text-center">
                <div class="col-6">
                    <div class="stat-box stat-success">
                        <div class="stat-number"><?= $completedMaintenance ?></div>
                        <small class="stat-label">Bảo trì hoàn thành</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-box stat-warning">
                        <div class="stat-number">0</div>
                        <small class="stat-label">Sự cố</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-box stat-info">
                        <div class="stat-number"><?= $operatingDays ?></div>
                        <small class="stat-label">Ngày hoạt động</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-box stat-primary">
                        <div class="stat-number"><?= $efficiency ?></div>
                        <small class="stat-label">Hiệu suất</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function renderMaintenanceHistory($maintenanceHistory) {
    ob_start();
    ?>
    <div class="info-section fade-in">
        <div class="info-section-header">
            <i class="fas fa-history section-icon"></i>
            <h5>Lịch sử bảo trì</h5>
            <div class="ms-auto">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewFullMaintenanceHistory()">
                    <i class="fas fa-eye me-1"></i>Xem đầy đủ
                </button>
            </div>
        </div>
        <div class="info-section-body">
            <?php if (!empty($maintenanceHistory)): ?>
                <div class="maintenance-timeline">
                    <?php foreach (array_slice($maintenanceHistory, 0, 5) as $maintenance): ?>
                        <div class="timeline-item <?= htmlspecialchars($maintenance['status']) ?>">
                            <div class="timeline-content">
                                <div class="timeline-date"><?= htmlspecialchars($maintenance['date']) ?></div>
                                <div class="timeline-title"><?= htmlspecialchars($maintenance['type']) ?></div>
                                <div class="timeline-description">
                                    <?= htmlspecialchars($maintenance['description']) ?>
                                </div>
                                <div class="timeline-meta">
                                    <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($maintenance['technician']) ?></span>
                                    <span><i class="fas fa-clock me-1"></i><?= htmlspecialchars($maintenance['duration']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-tools fa-3x mb-3 opacity-25"></i>
                    <p>Chưa có lịch sử bảo trì</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="createMaintenanceSchedule()">
                        <i class="fas fa-plus me-1"></i>Tạo lịch bảo trì đầu tiên
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function renderModals() {
    ob_start();
    ?>
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hình ảnh thiết bị</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="" class="img-fluid" style="max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <a id="downloadImage" href="" download class="btn btn-primary">
                        <i class="fas fa-download me-1"></i>Tải xuống
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
require_once '../../includes/footer.php';
?>