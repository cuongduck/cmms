<?php
/**
 * Equipment View Details - modules/equipment/view.php
 * Display detailed information about a specific equipment
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('equipment', 'view');

// Get equipment ID from URL
$equipmentId = (int)($_GET['id'] ?? 0);

if (!$equipmentId) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Get equipment details with all related information
$sql = "
    SELECT 
        e.*,
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
    
    WHERE e.id = ?
";

$equipment = $db->fetch($sql, [$equipmentId]);

if (!$equipment) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Set page variables
$pageTitle = 'Chi tiết thiết bị: ' . $equipment['name'];
$currentModule = 'equipment';
$moduleCSS = 'equipment';

$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => 'Chi tiết thiết bị']
];

// Page actions
$pageActions = '';
if (hasPermission('equipment', 'edit')) {
    $pageActions .= '<a href="edit.php?id=' . $equipmentId . '" class="btn btn-primary">
        <i class="fas fa-edit me-1"></i> Chỉnh sửa
    </a> ';
}

if (hasPermission('equipment', 'create')) {
    $pageActions .= '<a href="add.php" class="btn btn-outline-success">
        <i class="fas fa-plus me-1"></i> Thêm thiết bị mới
    </a> ';
}

$pageActions .= '
<div class="btn-group">
    <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-tools me-1"></i> Thao tác
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="printEquipment()">
            <i class="fas fa-print me-2"></i>In thông tin
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="exportEquipment()">
            <i class="fas fa-download me-2"></i>Xuất PDF
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="generateQR()">
            <i class="fas fa-qrcode me-2"></i>Tạo mã QR
        </a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="createMaintenanceSchedule()">
            <i class="fas fa-calendar-plus me-2"></i>Lên lịch bảo trì
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="viewMaintenanceHistory()">
            <i class="fas fa-history me-2"></i>Lịch sử bảo trì
        </a></li>';

if (hasPermission('equipment', 'delete')) {
    $pageActions .= '
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" onclick="deleteEquipment()">
            <i class="fas fa-trash me-2"></i>Xóa thiết bị
        </a></li>';
}

$pageActions .= '</ul></div>';

// Format some data
$equipment['created_at_formatted'] = formatDateTime($equipment['created_at']);
$equipment['updated_at_formatted'] = formatDateTime($equipment['updated_at']);
$equipment['installation_date_formatted'] = formatDate($equipment['installation_date']);
$equipment['warranty_expiry_formatted'] = formatDate($equipment['warranty_expiry']);
$equipment['status_text'] = getStatusText($equipment['status']);
$equipment['status_class'] = getStatusClass($equipment['status']);

// Parse technical specs if JSON
$technical_specs = [];
if ($equipment['technical_specs']) {
    $parsed = json_decode($equipment['technical_specs'], true);
    if ($parsed) {
        $technical_specs = $parsed;
    }
}

// Parse settings images if JSON
$settings_images = [];
if ($equipment['settings_images']) {
    $parsed = json_decode($equipment['settings_images'], true);
    if ($parsed) {
        $settings_images = $parsed;
    }
}

// Calculate next maintenance date and status
$next_maintenance = null;
$maintenance_due = false;
$days_until_maintenance = null;

if ($equipment['maintenance_frequency_days'] && $equipment['installation_date']) {
    $installDate = new DateTime($equipment['installation_date']);
    $nextMaintenance = clone $installDate;
    $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    
    // Keep adding maintenance intervals until we get a future date
    $today = new DateTime();
    while ($nextMaintenance <= $today) {
        $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    }
    
    $next_maintenance = $nextMaintenance->format('d/m/Y');
    $days_until_maintenance = $today->diff($nextMaintenance)->days;
    $maintenance_due = $days_until_maintenance <= 7;
}

// Get recent maintenance history (mock data for now)
$maintenance_history = [
    [
        'date' => '15/08/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Kiểm tra tổng quát, thay dầu máy, làm sạch bộ lọc',
        'technician' => 'Nguyễn Văn A',
        'status' => 'completed',
        'duration' => '2 giờ'
    ],
    [
        'date' => '15/05/2024',
        'type' => 'Sửa chữa',
        'description' => 'Thay thế bearing bị mòn',
        'technician' => 'Trần Văn B',
        'status' => 'completed',
        'duration' => '4 giờ'
    ],
    [
        'date' => '15/02/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Bảo trì định kỳ quý I',
        'technician' => 'Lê Văn C',
        'status' => 'completed',
        'duration' => '3 giờ'
    ]
];

// Build location path
$location_parts = array_filter([
    $equipment['industry_name'],
    $equipment['workshop_name'],
    $equipment['line_name'],
    $equipment['area_name']
]);
$location_path = implode(' → ', $location_parts);

require_once '../../includes/header.php';
?>

<style>
.equipment-view-container {
    background: var(--equipment-light);
    min-height: 100vh;
}

.equipment-header {
    background: linear-gradient(135deg, var(--equipment-primary), #3b82f6);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.equipment-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: translate(50px, -50px);
}

.equipment-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 150px;
    height: 150px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    transform: translate(-50px, 50px);
}

.equipment-header .container {
    position: relative;
    z-index: 1;
}

.equipment-code-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: inline-block;
    margin-bottom: 1rem;
}

.equipment-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.equipment-subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.info-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    overflow: hidden;
}

.info-section-header {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-section-header h5 {
    margin: 0;
    color: var(--equipment-dark);
    font-weight: 600;
}

.info-section-header .section-icon {
    color: var(--equipment-primary);
}

.info-section-body {
    padding: 1.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.info-value {
    color: var(--equipment-dark);
    font-weight: 500;
    font-size: 1rem;
}

.info-value.empty {
    color: #9ca3af;
    font-style: italic;
}

.equipment-image-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    position: sticky;
    top: 100px;
}

.main-equipment-image {
    width: 100%;
    height: 300px;
    object-fit: cover;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

.image-thumbnails {
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.image-thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 0.375rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
}

.image-thumbnail:hover,
.image-thumbnail.active {
    border-color: var(--equipment-primary);
    transform: scale(1.05);
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.9rem;
}

.status-active {
    background: #dcfdf4;
    color: #065f46;
    border: 1px solid #10b981;
}

.status-maintenance {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.status-broken {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.status-inactive {
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #9ca3af;
}

.criticality-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.criticality-critical {
    background: linear-gradient(135deg, #7f1d1d, #dc2626);
    color: white;
    box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
}

.criticality-high {
    background: linear-gradient(135deg, #ef4444, #f87171);
    color: white;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}

.criticality-medium {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: white;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.criticality-low {
    background: linear-gradient(135deg, #10b981, #6ee7b7);
    color: white;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}

.maintenance-timeline {
    position: relative;
}

.maintenance-timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--equipment-primary), #e5e7eb);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding-left: 3rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0.5rem;
    width: 1rem;
    height: 1rem;
    background: var(--equipment-primary);
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: 0 0 0 3px var(--equipment-primary);
}

.timeline-item.completed::before {
    background: var(--equipment-success);
    box-shadow: 0 0 0 3px var(--equipment-success);
}

.timeline-item.pending::before {
    background: var(--equipment-warning);
    box-shadow: 0 0 0 3px var(--equipment-warning);
}

.timeline-content {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid var(--equipment-primary);
}

.timeline-date {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.timeline-title {
    font-weight: 600;
    color: var(--equipment-dark);
    margin: 0.25rem 0;
}

.timeline-description {
    color: #6b7280;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.timeline-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #9ca3af;
}

.specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.spec-card {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid var(--equipment-primary);
}

.spec-label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.spec-value {
    color: var(--equipment-dark);
    font-weight: 500;
}

.maintenance-alert {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #f59e0b;
    color: #92400e;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintenance-alert.due {
    background: linear-gradient(135deg, #fef2f2, #fecaca);
    border-color: #ef4444;
    color: #991b1b;
}

.maintenance-alert i {
    font-size: 1.25rem;
}

.qr-code-section {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.qr-code-placeholder {
    width: 120px;
    height: 120px;
    background: #f3f4f6;
    border: 2px dashed #d1d5db;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    color: #9ca3af;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    color: var(--equipment-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.file-link:hover {
    background: var(--equipment-primary);
    color: white;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .equipment-view-container {
        padding: 0.5rem;
    }
    
    .equipment-header {
        padding: 1.5rem 0;
        margin-bottom: 1rem;
    }
    
    .equipment-title {
        font-size: 1.5rem;
    }
    
    .info-section-body {
        padding: 1rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .equipment-image-section {
        position: static;
        margin-top: 1rem;
    }
    
    .main-equipment-image {
        height: 200px;
    }
    
    .timeline-item {
        padding-left: 2rem;
    }
    
    .specs-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .equipment-view-container {
        background: white !important;
    }
    
    .equipment-header {
        background: white !important;
        color: black !important;
        border: 1px solid #ddd !important;
    }
    
    .info-section,
    .equipment-image-section {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .btn,
    .dropdown,
    .maintenance-alert {
        display: none !important;
    }
}
</style>

<div class="equipment-view-container">
    <!-- Equipment Header -->
    <div class="equipment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="equipment-code-badge">
                        <?php echo htmlspecialchars($equipment['code']); ?>
                    </div>
                    <h1 class="equipment-title">
                        <?php echo htmlspecialchars($equipment['name']); ?>
                    </h1>
                    <div class="equipment-subtitle">
                        <?php echo htmlspecialchars($location_path); ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-3">
                        <div class="status-indicator status-<?php echo $equipment['status']; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $equipment['status_text']; ?>
                        </div>
                    </div>
                    <div class="criticality-badge criticality-<?php echo strtolower($equipment['criticality']); ?>">
                        <?php echo $equipment['criticality']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Maintenance Alert -->
        <?php if ($maintenance_due): ?>
            <div class="maintenance-alert <?php echo $days_until_maintenance <= 3 ? 'due' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Cảnh báo bảo trì!</strong>
                    Thiết bị cần được bảo trì trong 
                    <strong><?php echo $days_until_maintenance; ?> ngày</strong> 
                    (<?php echo $next_maintenance; ?>)
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-info-circle section-icon"></i>
                        <h5>Thông tin cơ bản</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Nhà sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['manufacturer']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['manufacturer'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Model</div>
                                <div class="info-value <?php echo empty($equipment['model']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['model'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Số seri</div>
                                <div class="info-value <?php echo empty($equipment['serial_number']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['serial_number'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Năm sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['manufacture_year']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['manufacture_year'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Ngày lắp đặt</div>
                                <div class="info-value <?php echo empty($equipment['installation_date']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['installation_date_formatted'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bảo hành đến</div>
                                <div class="info-value <?php echo empty($equipment['warranty_expiry']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['warranty_expiry_formatted'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
</div>
                    </div>
                </div>

                <!-- Location & Classification -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-map-marked-alt section-icon"></i>
                        <h5>Vị trí & Phân loại</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Ngành sản xuất</div>
                                <div class="info-value">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($equipment['industry_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Xưởng sản xuất</div>
                                <div class="info-value">
                                    <span class="badge bg-info"><?php echo htmlspecialchars($equipment['workshop_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Line sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['line_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['line_name']): ?>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($equipment['line_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân line
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Khu vực</div>
                                <div class="info-value <?php echo empty($equipment['area_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['area_name']): ?>
                                        <span class="badge bg-warning"><?php echo htmlspecialchars($equipment['area_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân khu vực
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Dòng máy</div>
                                <div class="info-value">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['machine_type_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cụm thiết bị</div>
                                <div class="info-value <?php echo empty($equipment['equipment_group_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['equipment_group_name']): ?>
                                        <span class="badge bg-dark"><?php echo htmlspecialch<?php
/**
 * Equipment View Details - modules/equipment/view.php
 * Display detailed information about a specific equipment
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('equipment', 'view');

// Get equipment ID from URL
$equipmentId = (int)($_GET['id'] ?? 0);

if (!$equipmentId) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Get equipment details with all related information
$sql = "
    SELECT 
        e.*,
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
    
    WHERE e.id = ?
";

$equipment = $db->fetch($sql, [$equipmentId]);

if (!$equipment) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Set page variables
$pageTitle = 'Chi tiết thiết bị: ' . $equipment['name'];
$currentModule = 'equipment';
$moduleCSS = 'equipment';

$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => 'Chi tiết thiết bị']
];

// Page actions
$pageActions = '';
if (hasPermission('equipment', 'edit')) {
    $pageActions .= '<a href="edit.php?id=' . $equipmentId . '" class="btn btn-primary">
        <i class="fas fa-edit me-1"></i> Chỉnh sửa
    </a> ';
}

if (hasPermission('equipment', 'create')) {
    $pageActions .= '<a href="add.php" class="btn btn-outline-success">
        <i class="fas fa-plus me-1"></i> Thêm thiết bị mới
    </a> ';
}

$pageActions .= '
<div class="btn-group">
    <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-tools me-1"></i> Thao tác
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="printEquipment()">
            <i class="fas fa-print me-2"></i>In thông tin
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="exportEquipment()">
            <i class="fas fa-download me-2"></i>Xuất PDF
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="generateQR()">
            <i class="fas fa-qrcode me-2"></i>Tạo mã QR
        </a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="createMaintenanceSchedule()">
            <i class="fas fa-calendar-plus me-2"></i>Lên lịch bảo trì
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="viewMaintenanceHistory()">
            <i class="fas fa-history me-2"></i>Lịch sử bảo trì
        </a></li>';

if (hasPermission('equipment', 'delete')) {
    $pageActions .= '
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" onclick="deleteEquipment()">
            <i class="fas fa-trash me-2"></i>Xóa thiết bị
        </a></li>';
}

$pageActions .= '</ul></div>';

// Format some data
$equipment['created_at_formatted'] = formatDateTime($equipment['created_at']);
$equipment['updated_at_formatted'] = formatDateTime($equipment['updated_at']);
$equipment['installation_date_formatted'] = formatDate($equipment['installation_date']);
$equipment['warranty_expiry_formatted'] = formatDate($equipment['warranty_expiry']);
$equipment['status_text'] = getStatusText($equipment['status']);
$equipment['status_class'] = getStatusClass($equipment['status']);

// Parse technical specs if JSON
$technical_specs = [];
if ($equipment['technical_specs']) {
    $parsed = json_decode($equipment['technical_specs'], true);
    if ($parsed) {
        $technical_specs = $parsed;
    }
}

// Parse settings images if JSON
$settings_images = [];
if ($equipment['settings_images']) {
    $parsed = json_decode($equipment['settings_images'], true);
    if ($parsed) {
        $settings_images = $parsed;
    }
}

// Calculate next maintenance date and status
$next_maintenance = null;
$maintenance_due = false;
$days_until_maintenance = null;

if ($equipment['maintenance_frequency_days'] && $equipment['installation_date']) {
    $installDate = new DateTime($equipment['installation_date']);
    $nextMaintenance = clone $installDate;
    $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    
    // Keep adding maintenance intervals until we get a future date
    $today = new DateTime();
    while ($nextMaintenance <= $today) {
        $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    }
    
    $next_maintenance = $nextMaintenance->format('d/m/Y');
    $days_until_maintenance = $today->diff($nextMaintenance)->days;
    $maintenance_due = $days_until_maintenance <= 7;
}

// Get recent maintenance history (mock data for now)
$maintenance_history = [
    [
        'date' => '15/08/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Kiểm tra tổng quát, thay dầu máy, làm sạch bộ lọc',
        'technician' => 'Nguyễn Văn A',
        'status' => 'completed',
        'duration' => '2 giờ'
    ],
    [
        'date' => '15/05/2024',
        'type' => 'Sửa chữa',
        'description' => 'Thay thế bearing bị mòn',
        'technician' => 'Trần Văn B',
        'status' => 'completed',
        'duration' => '4 giờ'
    ],
    [
        'date' => '15/02/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Bảo trì định kỳ quý I',
        'technician' => 'Lê Văn C',
        'status' => 'completed',
        'duration' => '3 giờ'
    ]
];

// Build location path
$location_parts = array_filter([
    $equipment['industry_name'],
    $equipment['workshop_name'],
    $equipment['line_name'],
    $equipment['area_name']
]);
$location_path = implode(' → ', $location_parts);

require_once '../../includes/header.php';
?>

<style>
.equipment-view-container {
    background: var(--equipment-light);
    min-height: 100vh;
}

.equipment-header {
    background: linear-gradient(135deg, var(--equipment-primary), #3b82f6);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.equipment-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: translate(50px, -50px);
}

.equipment-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 150px;
    height: 150px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    transform: translate(-50px, 50px);
}

.equipment-header .container {
    position: relative;
    z-index: 1;
}

.equipment-code-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: inline-block;
    margin-bottom: 1rem;
}

.equipment-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.equipment-subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.info-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    overflow: hidden;
}

.info-section-header {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-section-header h5 {
    margin: 0;
    color: var(--equipment-dark);
    font-weight: 600;
}

.info-section-header .section-icon {
    color: var(--equipment-primary);
}

.info-section-body {
    padding: 1.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.info-value {
    color: var(--equipment-dark);
    font-weight: 500;
    font-size: 1rem;
}

.info-value.empty {
    color: #9ca3af;
    font-style: italic;
}

.equipment-image-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    position: sticky;
    top: 100px;
}

.main-equipment-image {
    width: 100%;
    height: 300px;
    object-fit: cover;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

.image-thumbnails {
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.image-thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 0.375rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
}

.image-thumbnail:hover,
.image-thumbnail.active {
    border-color: var(--equipment-primary);
    transform: scale(1.05);
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.9rem;
}

.status-active {
    background: #dcfdf4;
    color: #065f46;
    border: 1px solid #10b981;
}

.status-maintenance {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.status-broken {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.status-inactive {
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #9ca3af;
}

.criticality-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.criticality-critical {
    background: linear-gradient(135deg, #7f1d1d, #dc2626);
    color: white;
    box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
}

.criticality-high {
    background: linear-gradient(135deg, #ef4444, #f87171);
    color: white;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}

.criticality-medium {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: white;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.criticality-low {
    background: linear-gradient(135deg, #10b981, #6ee7b7);
    color: white;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}

.maintenance-timeline {
    position: relative;
}

.maintenance-timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--equipment-primary), #e5e7eb);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding-left: 3rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0.5rem;
    width: 1rem;
    height: 1rem;
    background: var(--equipment-primary);
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: 0 0 0 3px var(--equipment-primary);
}

.timeline-item.completed::before {
    background: var(--equipment-success);
    box-shadow: 0 0 0 3px var(--equipment-success);
}

.timeline-item.pending::before {
    background: var(--equipment-warning);
    box-shadow: 0 0 0 3px var(--equipment-warning);
}

.timeline-content {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid var(--equipment-primary);
}

.timeline-date {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.timeline-title {
    font-weight: 600;
    color: var(--equipment-dark);
    margin: 0.25rem 0;
}

.timeline-description {
    color: #6b7280;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.timeline-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #9ca3af;
}

.specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.spec-card {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid var(--equipment-primary);
}

.spec-label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.spec-value {
    color: var(--equipment-dark);
    font-weight: 500;
}

.maintenance-alert {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #f59e0b;
    color: #92400e;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintenance-alert.due {
    background: linear-gradient(135deg, #fef2f2, #fecaca);
    border-color: #ef4444;
    color: #991b1b;
}

.maintenance-alert i {
    font-size: 1.25rem;
}

.qr-code-section {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.qr-code-placeholder {
    width: 120px;
    height: 120px;
    background: #f3f4f6;
    border: 2px dashed #d1d5db;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    color: #9ca3af;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    color: var(--equipment-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.file-link:hover {
    background: var(--equipment-primary);
    color: white;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .equipment-view-container {
        padding: 0.5rem;
    }
    
    .equipment-header {
        padding: 1.5rem 0;
        margin-bottom: 1rem;
    }
    
    .equipment-title {
        font-size: 1.5rem;
    }
    
    .info-section-body {
        padding: 1rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .equipment-image-section {
        position: static;
        margin-top: 1rem;
    }
    
    .main-equipment-image {
        height: 200px;
    }
    
    .timeline-item {
        padding-left: 2rem;
    }
    
    .specs-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .equipment-view-container {
        background: white !important;
    }
    
    .equipment-header {
        background: white !important;
        color: black !important;
        border: 1px solid #ddd !important;
    }
    
    .info-section,
    .equipment-image-section {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .btn,
    .dropdown,
    .maintenance-alert {
        display: none !important;
    }
}
</style>

<div class="equipment-view-container">
    <!-- Equipment Header -->
    <div class="equipment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="equipment-code-badge">
                        <?php echo htmlspecialchars($equipment['code']); ?>
                    </div>
                    <h1 class="equipment-title">
                        <?php echo htmlspecialchars($equipment['name']); ?>
                    </h1>
                    <div class="equipment-subtitle">
                        <?php echo htmlspecialchars($location_path); ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-3">
                        <div class="status-indicator status-<?php echo $equipment['status']; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $equipment['status_text']; ?>
                        </div>
                    </div>
                    <div class="criticality-badge criticality-<?php echo strtolower($equipment['criticality']); ?>">
                        <?php echo $equipment['criticality']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Maintenance Alert -->
        <?php if ($maintenance_due): ?>
            <div class="maintenance-alert <?php echo $days_until_maintenance <= 3 ? 'due' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Cảnh báo bảo trì!</strong>
                    Thiết bị cần được bảo trì trong 
                    <strong><?php echo $days_until_maintenance; ?> ngày</strong> 
                    (<?php echo $next_maintenance; ?>)
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-info-circle section-icon"></i>
                        <h5>Thông tin cơ bản</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Nhà sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['manufacturer']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['manufacturer'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Model</div>
                                <div class="info-value <?php echo empty($equipment['model']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['model'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Số seri</div>
                                <div class="info-value <?php echo empty($equipment['serial_number']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['serial_number'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Năm sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['manufacture_year']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['manufacture_year'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Ngày lắp đặt</div>
                                <div class="info-value <?php echo empty($equipment['installation_date']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['installation_date_formatted'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bảo hành đến</div>
                                <div class="info-value <?php echo empty($equipment['warranty_expiry']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['warranty_expiry_formatted'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
<div class="info-item">
                                <div class="info-label">Cụm thiết bị</div>
                                <div class="info-value <?php echo empty($equipment['equipment_group_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['equipment_group_name']): ?>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($equipment['equipment_group_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân cụm
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Vị trí chi tiết</div>
                                <div class="info-value <?php echo empty($equipment['location_details']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['location_details'] ?: 'Chưa có thông tin chi tiết'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Information -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-users section-icon"></i>
                        <h5>Thông tin quản lý</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Người quản lý chính</div>
                                <div class="info-value <?php echo empty($equipment['owner_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['owner_name']): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-user-circle text-primary"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($equipment['owner_name']); ?></div>
                                                <?php if ($equipment['owner_email']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($equipment['owner_email']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($equipment['owner_phone']): ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($equipment['owner_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        Chưa phân công
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Người quản lý phụ</div>
                                <div class="info-value <?php echo empty($equipment['backup_owner_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['backup_owner_name']): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-user text-secondary"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($equipment['backup_owner_name']); ?></div>
                                                <?php if ($equipment['backup_owner_email']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($equipment['backup_owner_email']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($equipment['backup_owner_phone']): ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($equipment['backup_owner_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        Chưa phân công
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Người tạo</div>
                                <div class="info-value">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-user-plus text-success"></i>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($equipment['created_by_name'] ?: 'Không xác định'); ?></div>
                                            <small class="text-muted"><?php echo $equipment['created_at_formatted']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cập nhật cuối</div>
                                <div class="info-value">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-clock text-info"></i>
                                        <small class="text-muted"><?php echo $equipment['updated_at_formatted']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Information -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-wrench section-icon"></i>
                        <h5>Thông tin bảo trì</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Chu kỳ bảo trì</div>
                                <div class="info-value <?php echo empty($equipment['maintenance_frequency_days']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['maintenance_frequency_days']): ?>
                                        <?php echo $equipment['maintenance_frequency_days']; ?> ngày 
                                        (<?php echo ucfirst($equipment['maintenance_frequency_type']); ?>)
                                    <?php else: ?>
                                        Chưa thiết lập
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bảo trì tiếp theo</div>
                                <div class="info-value <?php echo empty($next_maintenance) ? 'empty' : ''; ?>">
                                    <?php if ($next_maintenance): ?>
                                        <span class="<?php echo $maintenance_due ? 'text-danger fw-bold' : 'text-success'; ?>">
                                            <?php echo $next_maintenance; ?>
                                            <?php if ($maintenance_due): ?>
                                                <i class="fas fa-exclamation-triangle ms-1"></i>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        Chưa lên lịch
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Trạng thái hiện tại</div>
                                <div class="info-value">
                                    <div class="status-indicator status-<?php echo $equipment['status']; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo $equipment['status_text']; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Mức độ quan trọng</div>
                                <div class="info-value">
                                    <div class="criticality-badge criticality-<?php echo strtolower($equipment['criticality']); ?>">
                                        <?php echo $equipment['criticality']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Technical Specifications -->
                <?php if ($equipment['specifications'] || !empty($technical_specs)): ?>
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-cog section-icon"></i>
                        <h5>Thông số kỹ thuật</h5>
                    </div>
                    <div class="info-section-body">
                        <?php if ($equipment['specifications']): ?>
                            <div class="mb-3">
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($equipment['specifications'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($technical_specs)): ?>
                            <div class="specs-grid">
                                <?php foreach ($technical_specs as $key => $value): ?>
                                    <div class="spec-card">
                                        <div class="spec-label"><?php echo htmlspecialchars($key); ?></div>
                                        <div class="spec-value"><?php echo htmlspecialchars($value); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Maintenance History -->
                <div class="info-section">
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
                        <?php if (!empty($maintenance_history)): ?>
                            <div class="maintenance-timeline">
                                <?php foreach ($maintenance_history as $maintenance): ?>
                                    <div class="timeline-item <?php echo $maintenance['status']; ?>">
                                        <div class="timeline-content">
                                            <div class="timeline-date"><?php echo $maintenance['date']; ?></div>
                                            <div class="timeline-title"><?php echo htmlspecialchars($maintenance['type']); ?></div>
                                            <div class="timeline-description">
                                                <?php echo htmlspecialchars($maintenance['description']); ?>
                                            </div>
                                            <div class="timeline-meta">
                                                <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($maintenance['technician']); ?></span>
                                                <span><i class="fas fa-clock me-1"></i><?php echo $maintenance['duration']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-tools fa-3x mb-3 opacity-25"></i>
                                <p>Chưa có lịch sử bảo trì</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($equipment['notes']): ?>
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-sticky-note section-icon"></i>
                        <h5>Ghi chú</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($equipment['notes'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Equipment Image -->
                <div class="equipment-image-section">
                    <?php if ($equipment['image_path'] && file_exists($equipment['image_path'])): ?>
                        <img src="<?php echo str_replace(BASE_PATH, APP_URL, $equipment['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($equipment['name']); ?>" 
                             class="main-equipment-image"
                             onclick="showImageModal(this.src)">
                        
                        <?php if (!empty($settings_images)): ?>
                        <div class="image-thumbnails">
                            <?php foreach ($settings_images as $image): ?>
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     alt="Settings Image" 
                                     class="image-thumbnail"
                                     onclick="showImageModal(this.src)">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="main-equipment-image">
                            <div class="text-center">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <div>Chưa có hình ảnh</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-bolt section-icon"></i>
                        <h5>Thao tác nhanh</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="d-grid gap-2">
                            <?php if (hasPermission('equipment', 'edit')): ?>
                            <a href="edit.php?id=<?php echo $equipmentId; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Chỉnh sửa thiết bị
                            </a>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-outline-success" onclick="createMaintenanceSchedule()">
                                <i class="fas fa-calendar-plus me-2"></i>Lên lịch bảo trì
                            </button>
                            
                            <button type="button" class="btn btn-outline-info" onclick="changeStatus()">
                                <i class="fas fa-exchange-alt me-2"></i>Thay đổi trạng thái
                            </button>
                            
                            <button type="button" class="btn btn-outline-warning" onclick="generateQR()">
                                <i class="fas fa-qrcode me-2"></i>Tạo mã QR
                            </button>
                            
                            <hr>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="printEquipment()">
                                <i class="fas fa-print me-2"></i>In thông tin
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="exportEquipment()">
                                <i class="fas fa-download me-2"></i>Xuất PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Files & Documents -->
                <?php if ($equipment['manual_path'] || !empty($settings_images)): ?>
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-file section-icon"></i>
                        <h5>Tài liệu & File</h5>
                    </div>
                    <div class="info-section-body">
                        <?php if ($equipment['manual_path'] && file_exists($equipment['manual_path'])): ?>
                            <div class="mb-2">
                                <a href="<?php echo str_replace(BASE_PATH, APP_URL, $equipment['manual_path']); ?>" 
                                   target="_blank" class="file-link">
                                    <i class="fas fa-file-pdf"></i>
                                    Hướng dẫn sử dụng
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('equipment', 'edit')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="uploadFiles()">
                                <i class="fas fa-upload me-2"></i>Upload tài liệu
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- QR Code -->
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-qrcode section-icon"></i>
                        <h5>Mã QR</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="qr-code-section">
                            <div id="qrCodeContainer">
                                <div class="qr-code-placeholder">
                                    <i class="fas fa-qrcode fa-2x"></i>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" onclick="generateQR()">
                                    Tạo mã QR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipment Statistics -->
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-chart-bar section-icon"></i>
                        <h5>Thống kê</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="p-2 bg-success bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-success">0</div>
                                    <small class="text-muted">Bảo trì hoàn thành</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-warning bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-warning">0</div>
                                    <small class="text-muted">Sự cố</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-info bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-info">
                                        <?php 
                                        if ($equipment['installation_date']) {
                                            $installDate = new DateTime($equipment['installation_date']);
                                            $today = new DateTime();
                                            $diff = $installDate->diff($today);
                                            echo $diff->days;
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                    <small class="text-muted">Ngày hoạt động</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-primary bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-primary">98%</div>
                                    <small class="text-muted">Hiệu suất</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hình ảnh thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-fluid">
            </div>
            <div class="modal-footer">
                <a id="downloadImage" href="" download class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Tải xuống
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thay đổi trạng thái thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <div class="mb-3">
                        <label for="newStatus" class="form-label">Trạng thái mới:</label>
                        <select class="form-select" id="newStatus" name="status" required>
                            <option value="active" <?php echo $equipment['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="inactive" <?php echo $equipment['status'] === 'inactive' ? 'selected' : ''; ?>>Ngưng hoạt động</option>
                            <option value="maintenance" <?php echo $equipment['status'] === 'maintenance' ? 'selected' : ''; ?>>Bảo trì</option>
                            <option value="broken" <?php echo $equipment['status'] === 'broken' ? 'selected' : ''; ?>>Hỏng</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="statusNote" class="form-label">Ghi chú (tùy chọn):</label>
                        <textarea class="form-control" id="statusNote" name="note" rows="3" 
                                  placeholder="Lý do thay đổi trạng thái..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="updateStatus()">
                    <i class="fas fa-save me-2"></i>Cập nhật
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Equipment View JavaScript
const EquipmentView = {
    equipmentId: <?php echo $equipmentId; ?>,
    equipmentData: <?php echo json_encode($equipment); ?>,
    
    init: function() {
        console.log('Equipment view initialized for ID:', this.equipmentId);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize image gallery
        this.initializeImageGallery();
    },
    
    initializeImageGallery: function() {
        const thumbnails = document.querySelectorAll('.image-thumbnail');
        const mainImage = document.querySelector('.main-equipment-image');
        
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', function() {
                // Update main image
                if (mainImage) {
                    mainImage.src = this.src;
                }
                
                // Update active thumbnail
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
};

// Global functions
function showImageModal(src) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    const modalImage = document.getElementById('modalImage');
    const downloadLink = document.getElementById('downloadImage');
    
    modalImage.src = src;
    modalImage.alt = EquipmentView.equipmentData.name;
    downloadLink.href = src;
    
    modal.show();
}

function changeStatus() {
    if (!<?php echo json_encode(hasPermission('equipment', 'edit')); ?>) {
        CMMS.showToast('Bạn không có quyền thay đổi trạng thái thiết bị', 'error');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

async function updateStatus() {
    const form = document.getElementById('statusForm');
    const formData = new FormData(form);
    
    try {
        CMMS.showLoading();
        
        formData.append('action', 'update_status');
        formData.append('id', EquipmentView.equipmentId);
        
        const response = await fetch('../api/equipment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            CMMS.showToast(result.message, 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
            modal.hide();
            
            // Refresh page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            CMMS.showToast(result.message || 'Lỗi khi cập nhật trạng thái', 'error');
        }
    } catch (error) {
        console.error('Update status error:', error);
        CMMS.showToast('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        CMMS.hideLoading();
    }
}

function generateQR() {
    const equipmentUrl = `${window.location.origin}${window.location.pathname}?id=${EquipmentView.equipmentId}`;
    const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(equipmentUrl)}`;
    
    const qrContainer = document.getElementById('qrCodeContainer');
    qrContainer.innerHTML = `
        <img src="${qrCodeUrl}" alt="QR Code" style="width: 150px; height: 150px; border-radius: 0.375rem;">
        <div class="mt-2">
            <small class="text-muted">Quét để xem thiết bị</small>
        </div>
        <div class="mt-2">
            <a href="${qrCodeUrl}" download="equipment-${EquipmentView.equipmentId}-qr.png" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-download me-1"></i>Tải QR
            </a>
        </div>
    `;
    
    CMMS.showToast('Đã tạo mã QR thành công', 'success');
}

function createMaintenanceSchedule() {
    CMMS.showToast('Chức năng lên lịch bảo trì đang được phát triển', 'info');
    // TODO: Implement maintenance scheduling
}

function viewMaintenanceHistory() {
    CMMS.showToast('Chức năng xem lịch sử bảo trì đang được phát triển', 'info');
    // TODO: Implement maintenance history modal
}

function viewFullMaintenanceHistory() {
    CMMS.showToast('Chức năng xem đầy đủ lịch sử bảo trì đang được phát triển', 'info');
    // TODO: Implement full maintenance history page
}

function printEquipment() {
    // Hide print-unfriendly elements
    const elementsToHide = document.querySelectorAll('.btn, .dropdown, .maintenance-alert');
    elementsToHide.forEach(el => el.style.display = 'none');
    
    // Print
    window.print();
    
    // Restore hidden elements
    setTimeout(() => {
        elementsToHide.forEach(el => el.style.display = '');
    }, 1000);
}

function exportEquipment() {
    CMMS.showToast('Chức năng xuất PDF đang được phát triển', 'info');
    // TODO: Implement PDF export
}

function uploadFiles() {
    CMMS.showToast('Chức năng upload file đang được phát triển', 'info');
    // TODO: Implement file upload modal
}

function deleteEquipment() {
    if (!<?php echo json_encode(hasPermission('equipment', 'delete')); ?>) {
        CMMS.showToast('Bạn không có quyền xóa thiết bị', 'error');
        return;
    }
    
    const message = `Bạn có chắc chắn muốn xóa thiết bị "${EquipmentView.equipmentData.name}"?\n\nHành động này sẽ xóa:\n• Tất cả thông tin thiết bị\n• Lịch sử bảo trì\n• File đính kèm\n\nVà không thể hoàn tác.`;
    
    if (confirm(message)) {
        performDelete();
    }
}

async function performDelete() {
    try {
        CMMS.showLoading();
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', EquipmentView.equipmentId);
        
        const response = await fetch('../api/equipment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            CMMS.showToast(result.message, 'success');
            
            // Redirect to equipment list after successful deletion
            setTimeout(() => {
                window.location.href = 'index                        </div>
                    </div>
                </div>

                <!-- Location & Classification -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-map-marked-alt section-icon"></i>
                        <h5>Vị trí & Phân loại</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Ngành sản xuất</div>
                                <div class="info-value">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($equipment['industry_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Xưởng sản xuất</div>
                                <div class="info-value">
                                    <span class="badge bg-info"><?php echo htmlspecialchars($equipment['workshop_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Line sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['line_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['line_name']): ?>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($equipment['line_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân line
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Khu vực</div>
                                <div class="info-value <?php echo empty($equipment['area_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['area_name']): ?>
                                        <span class="badge bg-warning"><?php echo htmlspecialchars($equipment['area_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân khu vực
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Dòng máy</div>
                                <div class="info-value">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['machine_type_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cụm thiết bị</div>
                                <div class="info-value <?php echo empty($equipment['equipment_group_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['equipment_group_name']): ?>
                                        <span class="badge bg-dark"><?php echo htmlspecialch<?php
/**
 * Equipment View Details - modules/equipment/view.php
 * Display detailed information about a specific equipment
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('equipment', 'view');

// Get equipment ID from URL
$equipmentId = (int)($_GET['id'] ?? 0);

if (!$equipmentId) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Get equipment details with all related information
$sql = "
    SELECT 
        e.*,
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
    
    WHERE e.id = ?
";

$equipment = $db->fetch($sql, [$equipmentId]);

if (!$equipment) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Set page variables
$pageTitle = 'Chi tiết thiết bị: ' . $equipment['name'];
$currentModule = 'equipment';
$moduleCSS = 'equipment';

$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => 'Chi tiết thiết bị']
];

// Page actions
$pageActions = '';
if (hasPermission('equipment', 'edit')) {
    $pageActions .= '<a href="edit.php?id=' . $equipmentId . '" class="btn btn-primary">
        <i class="fas fa-edit me-1"></i> Chỉnh sửa
    </a> ';
}

if (hasPermission('equipment', 'create')) {
    $pageActions .= '<a href="add.php" class="btn btn-outline-success">
        <i class="fas fa-plus me-1"></i> Thêm thiết bị mới
    </a> ';
}

$pageActions .= '
<div class="btn-group">
    <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-tools me-1"></i> Thao tác
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="printEquipment()">
            <i class="fas fa-print me-2"></i>In thông tin
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="exportEquipment()">
            <i class="fas fa-download me-2"></i>Xuất PDF
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="generateQR()">
            <i class="fas fa-qrcode me-2"></i>Tạo mã QR
        </a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="createMaintenanceSchedule()">
            <i class="fas fa-calendar-plus me-2"></i>Lên lịch bảo trì
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="viewMaintenanceHistory()">
            <i class="fas fa-history me-2"></i>Lịch sử bảo trì
        </a></li>';

if (hasPermission('equipment', 'delete')) {
    $pageActions .= '
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" onclick="deleteEquipment()">
            <i class="fas fa-trash me-2"></i>Xóa thiết bị
        </a></li>';
}

$pageActions .= '</ul></div>';

// Format some data
$equipment['created_at_formatted'] = formatDateTime($equipment['created_at']);
$equipment['updated_at_formatted'] = formatDateTime($equipment['updated_at']);
$equipment['installation_date_formatted'] = formatDate($equipment['installation_date']);
$equipment['warranty_expiry_formatted'] = formatDate($equipment['warranty_expiry']);
$equipment['status_text'] = getStatusText($equipment['status']);
$equipment['status_class'] = getStatusClass($equipment['status']);

// Parse technical specs if JSON
$technical_specs = [];
if ($equipment['technical_specs']) {
    $parsed = json_decode($equipment['technical_specs'], true);
    if ($parsed) {
        $technical_specs = $parsed;
    }
}

// Parse settings images if JSON
$settings_images = [];
if ($equipment['settings_images']) {
    $parsed = json_decode($equipment['settings_images'], true);
    if ($parsed) {
        $settings_images = $parsed;
    }
}

// Calculate next maintenance date and status
$next_maintenance = null;
$maintenance_due = false;
$days_until_maintenance = null;

if ($equipment['maintenance_frequency_days'] && $equipment['installation_date']) {
    $installDate = new DateTime($equipment['installation_date']);
    $nextMaintenance = clone $installDate;
    $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    
    // Keep adding maintenance intervals until we get a future date
    $today = new DateTime();
    while ($nextMaintenance <= $today) {
        $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    }
    
    $next_maintenance = $nextMaintenance->format('d/m/Y');
    $days_until_maintenance = $today->diff($nextMaintenance)->days;
    $maintenance_due = $days_until_maintenance <= 7;
}

// Get recent maintenance history (mock data for now)
$maintenance_history = [
    [
        'date' => '15/08/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Kiểm tra tổng quát, thay dầu máy, làm sạch bộ lọc',
        'technician' => 'Nguyễn Văn A',
        'status' => 'completed',
        'duration' => '2 giờ'
    ],
    [
        'date' => '15/05/2024',
        'type' => 'Sửa chữa',
        'description' => 'Thay thế bearing bị mòn',
        'technician' => 'Trần Văn B',
        'status' => 'completed',
        'duration' => '4 giờ'
    ],
    [
        'date' => '15/02/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Bảo trì định kỳ quý I',
        'technician' => 'Lê Văn C',
        'status' => 'completed',
        'duration' => '3 giờ'
    ]
];

// Build location path
$location_parts = array_filter([
    $equipment['industry_name'],
    $equipment['workshop_name'],
    $equipment['line_name'],
    $equipment['area_name']
]);
$location_path = implode(' → ', $location_parts);

require_once '../../includes/header.php';
?>

<style>
.equipment-view-container {
    background: var(--equipment-light);
    min-height: 100vh;
}

.equipment-header {
    background: linear-gradient(135deg, var(--equipment-primary), #3b82f6);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.equipment-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: translate(50px, -50px);
}

.equipment-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 150px;
    height: 150px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    transform: translate(-50px, 50px);
}

.equipment-header .container {
    position: relative;
    z-index: 1;
}

.equipment-code-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: inline-block;
    margin-bottom: 1rem;
}

.equipment-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.equipment-subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.info-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    overflow: hidden;
}

.info-section-header {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-section-header h5 {
    margin: 0;
    color: var(--equipment-dark);
    font-weight: 600;
}

.info-section-header .section-icon {
    color: var(--equipment-primary);
}

.info-section-body {
    padding: 1.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.info-value {
    color: var(--equipment-dark);
    font-weight: 500;
    font-size: 1rem;
}

.info-value.empty {
    color: #9ca3af;
    font-style: italic;
}

.equipment-image-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    position: sticky;
    top: 100px;
}

.main-equipment-image {
    width: 100%;
    height: 300px;
    object-fit: cover;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

.image-thumbnails {
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.image-thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 0.375rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
}

.image-thumbnail:hover,
.image-thumbnail.active {
    border-color: var(--equipment-primary);
    transform: scale(1.05);
}

.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    font-size: 0.9rem;
}

.status-active {
    background: #dcfdf4;
    color: #065f46;
    border: 1px solid #10b981;
}

.status-maintenance {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.status-broken {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.status-inactive {
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #9ca3af;
}

.criticality-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.criticality-critical {
    background: linear-gradient(135deg, #7f1d1d, #dc2626);
    color: white;
    box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
}

.criticality-high {
    background: linear-gradient(135deg, #ef4444, #f87171);
    color: white;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}

.criticality-medium {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: white;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

.criticality-low {
    background: linear-gradient(135deg, #10b981, #6ee7b7);
    color: white;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}

.maintenance-timeline {
    position: relative;
}

.maintenance-timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--equipment-primary), #e5e7eb);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding-left: 3rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0.5rem;
    width: 1rem;
    height: 1rem;
    background: var(--equipment-primary);
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: 0 0 0 3px var(--equipment-primary);
}

.timeline-item.completed::before {
    background: var(--equipment-success);
    box-shadow: 0 0 0 3px var(--equipment-success);
}

.timeline-item.pending::before {
    background: var(--equipment-warning);
    box-shadow: 0 0 0 3px var(--equipment-warning);
}

.timeline-content {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid var(--equipment-primary);
}

.timeline-date {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.timeline-title {
    font-weight: 600;
    color: var(--equipment-dark);
    margin: 0.25rem 0;
}

.timeline-description {
    color: #6b7280;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.timeline-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: #9ca3af;
}

.specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.spec-card {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid var(--equipment-primary);
}

.spec-label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.25rem;
    font-weight: 600;
}

.spec-value {
    color: var(--equipment-dark);
    font-weight: 500;
}

.maintenance-alert {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #f59e0b;
    color: #92400e;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.maintenance-alert.due {
    background: linear-gradient(135deg, #fef2f2, #fecaca);
    border-color: #ef4444;
    color: #991b1b;
}

.maintenance-alert i {
    font-size: 1.25rem;
}

.qr-code-section {
    background: white;
    border-radius: 0.5rem;
    padding: 1rem;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.qr-code-placeholder {
    width: 120px;
    height: 120px;
    background: #f3f4f6;
    border: 2px dashed #d1d5db;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    color: #9ca3af;
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    color: var(--equipment-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.file-link:hover {
    background: var(--equipment-primary);
    color: white;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .equipment-view-container {
        padding: 0.5rem;
    }
    
    .equipment-header {
        padding: 1.5rem 0;
        margin-bottom: 1rem;
    }
    
    .equipment-title {
        font-size: 1.5rem;
    }
    
    .info-section-body {
        padding: 1rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .equipment-image-section {
        position: static;
        margin-top: 1rem;
    }
    
    .main-equipment-image {
        height: 200px;
    }
    
    .timeline-item {
        padding-left: 2rem;
    }
    
    .specs-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .equipment-view-container {
        background: white !important;
    }
    
    .equipment-header {
        background: white !important;
        color: black !important;
        border: 1px solid #ddd !important;
    }
    
    .info-section,
    .equipment-image-section {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .btn,
    .dropdown,
    .maintenance-alert {
        display: none !important;
    }
}
</style>

<div class="equipment-view-container">
    <!-- Equipment Header -->
    <div class="equipment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="equipment-code-badge">
                        <?php echo htmlspecialchars($equipment['code']); ?>
                    </div>
                    <h1 class="equipment-title">
                        <?php echo htmlspecialchars($equipment['name']); ?>
                    </h1>
                    <div class="equipment-subtitle">
                        <?php echo htmlspecialchars($location_path); ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-3">
                        <div class="status-indicator status-<?php echo $equipment['status']; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $equipment['status_text']; ?>
                        </div>
                    </div>
                    <div class="criticality-badge criticality-<?php echo strtolower($equipment['criticality']); ?>">
                        <?php echo $equipment['criticality']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Maintenance Alert -->
        <?php if ($maintenance_due): ?>
            <div class="maintenance-alert <?php echo $days_until_maintenance <= 3 ? 'due' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Cảnh báo bảo trì!</strong>
                    Thiết bị cần được bảo trì trong 
                    <strong><?php echo $days_until_maintenance; ?> ngày</strong> 
                    (<?php echo $next_maintenance; ?>)
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Basic Information -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-info-circle section-icon"></i>
                        <h5>Thông tin cơ bản</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Nhà sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['manufacturer']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['manufacturer'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Model</div>
                                <div class="info-value <?php echo empty($equipment['model']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['model'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Số seri</div>
                                <div class="info-value <?php echo empty($equipment['serial_number']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['serial_number'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Năm sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['manufacture_year']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['manufacture_year'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Ngày lắp đặt</div>
                                <div class="info-value <?php echo empty($equipment['installation_date']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['installation_date_formatted'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bảo hành đến</div>
                                <div class="info-value <?php echo empty($equipment['warranty_expiry']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['warranty_expiry_formatted'] ?: 'Chưa có thông tin'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                            <div class="info-item">
                                <div class="info-label">Cụm thiết bị</div>
                                <div class="info-value <?php echo empty($equipment['equipment_group_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['equipment_group_name']): ?>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($equipment['equipment_group_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân cụm
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Vị trí chi tiết</div>
                                <div class="info-value <?php echo empty($equipment['location_details']) ? 'empty' : ''; ?>">
                                    <?php echo $equipment['location_details'] ?: 'Chưa có thông tin chi tiết'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Information -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-users section-icon"></i>
                        <h5>Thông tin quản lý</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Người quản lý chính</div>
                                <div class="info-value <?php echo empty($equipment['owner_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['owner_name']): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-user-circle text-primary"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($equipment['owner_name']); ?></div>
                                                <?php if ($equipment['owner_email']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($equipment['owner_email']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($equipment['owner_phone']): ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($equipment['owner_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        Chưa phân công
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Người quản lý phụ</div>
                                <div class="info-value <?php echo empty($equipment['backup_owner_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['backup_owner_name']): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-user text-secondary"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($equipment['backup_owner_name']); ?></div>
                                                <?php if ($equipment['backup_owner_email']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($equipment['backup_owner_email']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($equipment['backup_owner_phone']): ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($equipment['backup_owner_phone']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        Chưa phân công
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Người tạo</div>
                                <div class="info-value">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-user-plus text-success"></i>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($equipment['created_by_name'] ?: 'Không xác định'); ?></div>
                                            <small class="text-muted"><?php echo $equipment['created_at_formatted']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cập nhật cuối</div>
                                <div class="info-value">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-clock text-info"></i>
                                        <small class="text-muted"><?php echo $equipment['updated_at_formatted']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Information -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-wrench section-icon"></i>
                        <h5>Thông tin bảo trì</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Chu kỳ bảo trì</div>
                                <div class="info-value <?php echo empty($equipment['maintenance_frequency_days']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['maintenance_frequency_days']): ?>
                                        <?php echo $equipment['maintenance_frequency_days']; ?> ngày 
                                        (<?php echo ucfirst($equipment['maintenance_frequency_type']); ?>)
                                    <?php else: ?>
                                        Chưa thiết lập
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bảo trì tiếp theo</div>
                                <div class="info-value <?php echo empty($next_maintenance) ? 'empty' : ''; ?>">
                                    <?php if ($next_maintenance): ?>
                                        <span class="<?php echo $maintenance_due ? 'text-danger fw-bold' : 'text-success'; ?>">
                                            <?php echo $next_maintenance; ?>
                                            <?php if ($maintenance_due): ?>
                                                <i class="fas fa-exclamation-triangle ms-1"></i>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        Chưa lên lịch
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Trạng thái hiện tại</div>
                                <div class="info-value">
                                    <div class="status-indicator status-<?php echo $equipment['status']; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo $equipment['status_text']; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Mức độ quan trọng</div>
                                <div class="info-value">
                                    <div class="criticality-badge criticality-<?php echo strtolower($equipment['criticality']); ?>">
                                        <?php echo $equipment['criticality']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Technical Specifications -->
                <?php if ($equipment['specifications'] || !empty($technical_specs)): ?>
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-cog section-icon"></i>
                        <h5>Thông số kỹ thuật</h5>
                    </div>
                    <div class="info-section-body">
                        <?php if ($equipment['specifications']): ?>
                            <div class="mb-3">
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($equipment['specifications'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($technical_specs)): ?>
                            <div class="specs-grid">
                                <?php foreach ($technical_specs as $key => $value): ?>
                                    <div class="spec-card">
                                        <div class="spec-label"><?php echo htmlspecialchars($key); ?></div>
                                        <div class="spec-value"><?php echo htmlspecialchars($value); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Maintenance History -->
                <div class="info-section">
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
                        <?php if (!empty($maintenance_history)): ?>
                            <div class="maintenance-timeline">
                                <?php foreach ($maintenance_history as $maintenance): ?>
                                    <div class="timeline-item <?php echo $maintenance['status']; ?>">
                                        <div class="timeline-content">
                                            <div class="timeline-date"><?php echo $maintenance['date']; ?></div>
                                            <div class="timeline-title"><?php echo htmlspecialchars($maintenance['type']); ?></div>
                                            <div class="timeline-description">
                                                <?php echo htmlspecialchars($maintenance['description']); ?>
                                            </div>
                                            <div class="timeline-meta">
                                                <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($maintenance['technician']); ?></span>
                                                <span><i class="fas fa-clock me-1"></i><?php echo $maintenance['duration']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-tools fa-3x mb-3 opacity-25"></i>
                                <p>Chưa có lịch sử bảo trì</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notes -->
                <?php if ($equipment['notes']): ?>
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-sticky-note section-icon"></i>
                        <h5>Ghi chú</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($equipment['notes'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Equipment Image -->
                <div class="equipment-image-section">
                    <?php if ($equipment['image_path'] && file_exists($equipment['image_path'])): ?>
                        <img src="<?php echo str_replace(BASE_PATH, APP_URL, $equipment['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($equipment['name']); ?>" 
                             class="main-equipment-image"
                             onclick="showImageModal(this.src)">
                        
                        <?php if (!empty($settings_images)): ?>
                        <div class="image-thumbnails">
                            <?php foreach ($settings_images as $image): ?>
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     alt="Settings Image" 
                                     class="image-thumbnail"
                                     onclick="showImageModal(this.src)">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="main-equipment-image">
                            <div class="text-center">
                                <i class="fas fa-image fa-3x mb-2"></i>
                                <div>Chưa có hình ảnh</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-bolt section-icon"></i>
                        <h5>Thao tác nhanh</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="d-grid gap-2">
                            <?php if (hasPermission('equipment', 'edit')): ?>
                            <a href="edit.php?id=<?php echo $equipmentId; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>Chỉnh sửa thiết bị
                            </a>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-outline-success" onclick="createMaintenanceSchedule()">
                                <i class="fas fa-calendar-plus me-2"></i>Lên lịch bảo trì
                            </button>
                            
                            <button type="button" class="btn btn-outline-info" onclick="changeStatus()">
                                <i class="fas fa-exchange-alt me-2"></i>Thay đổi trạng thái
                            </button>
                            
                            <button type="button" class="btn btn-outline-warning" onclick="generateQR()">
                                <i class="fas fa-qrcode me-2"></i>Tạo mã QR
                            </button>
                            
                            <hr>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="printEquipment()">
                                <i class="fas fa-print me-2"></i>In thông tin
                            </button>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="exportEquipment()">
                                <i class="fas fa-download me-2"></i>Xuất PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Files & Documents -->
                <?php if ($equipment['manual_path'] || !empty($settings_images)): ?>
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-file section-icon"></i>
                        <h5>Tài liệu & File</h5>
                    </div>
                    <div class="info-section-body">
                        <?php if ($equipment['manual_path'] && file_exists($equipment['manual_path'])): ?>
                            <div class="mb-2">
                                <a href="<?php echo str_replace(BASE_PATH, APP_URL, $equipment['manual_path']); ?>" 
                                   target="_blank" class="file-link">
                                    <i class="fas fa-file-pdf"></i>
                                    Hướng dẫn sử dụng
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('equipment', 'edit')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="uploadFiles()">
                                <i class="fas fa-upload me-2"></i>Upload tài liệu
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- QR Code -->
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-qrcode section-icon"></i>
                        <h5>Mã QR</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="qr-code-section">
                            <div id="qrCodeContainer">
                                <div class="qr-code-placeholder">
                                    <i class="fas fa-qrcode fa-2x"></i>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" onclick="generateQR()">
                                    Tạo mã QR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipment Statistics -->
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-chart-bar section-icon"></i>
                        <h5>Thống kê</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <div class="p-2 bg-success bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-success">0</div>
                                    <small class="text-muted">Bảo trì hoàn thành</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-warning bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-warning">0</div>
                                    <small class="text-muted">Sự cố</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-info bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-info">
                                        <?php 
                                        if ($equipment['installation_date']) {
                                            $installDate = new DateTime($equipment['installation_date']);
                                            $today = new DateTime();
                                            $diff = $installDate->diff($today);
                                            echo $diff->days;
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                    <small class="text-muted">Ngày hoạt động</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-primary bg-opacity-10 rounded">
                                    <div class="h6 mb-1 text-primary">98%</div>
                                    <small class="text-muted">Hiệu suất</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hình ảnh thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-fluid">
            </div>
            <div class="modal-footer">
                <a id="downloadImage" href="" download class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Tải xuống
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thay đổi trạng thái thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <div class="mb-3">
                        <label for="newStatus" class="form-label">Trạng thái mới:</label>
                        <select class="form-select" id="newStatus" name="status" required>
                            <option value="active" <?php echo $equipment['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="inactive" <?php echo $equipment['status'] === 'inactive' ? 'selected' : ''; ?>>Ngưng hoạt động</option>
                            <option value="maintenance" <?php echo $equipment['status'] === 'maintenance' ? 'selected' : ''; ?>>Bảo trì</option>
                            <option value="broken" <?php echo $equipment['status'] === 'broken' ? 'selected' : ''; ?>>Hỏng</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="statusNote" class="form-label">Ghi chú (tùy chọn):</label>
                        <textarea class="form-control" id="statusNote" name="note" rows="3" 
                                  placeholder="Lý do thay đổi trạng thái..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="updateStatus()">
                    <i class="fas fa-save me-2"></i>Cập nhật
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Equipment View JavaScript
const EquipmentView = {
    equipmentId: <?php echo $equipmentId; ?>,
    equipmentData: <?php echo json_encode($equipment); ?>,
    
    init: function() {
        console.log('Equipment view initialized for ID:', this.equipmentId);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize image gallery
        this.initializeImageGallery();
    },
    
    initializeImageGallery: function() {
        const thumbnails = document.querySelectorAll('.image-thumbnail');
        const mainImage = document.querySelector('.main-equipment-image');
        
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', function() {
                // Update main image
                if (mainImage) {
                    mainImage.src = this.src;
                }
                
                // Update active thumbnail
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
};

// Global functions
function showImageModal(src) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    const modalImage = document.getElementById('modalImage');
    const downloadLink = document.getElementById('downloadImage');
    
    modalImage.src = src;
    modalImage.alt = EquipmentView.equipmentData.name;
    downloadLink.href = src;
    
    modal.show();
}

function changeStatus() {
    if (!<?php echo json_encode(hasPermission('equipment', 'edit')); ?>) {
        CMMS.showToast('Bạn không có quyền thay đổi trạng thái thiết bị', 'error');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

async function updateStatus() {
    const form = document.getElementById('statusForm');
    const formData = new FormData(form);
    
    try {
        CMMS.showLoading();
        
        formData.append('action', 'update_status');
        formData.append('id', EquipmentView.equipmentId);
        
        const response = await fetch('../api/equipment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            CMMS.showToast(result.message, 'success');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
            modal.hide();
            
            // Refresh page to show updated status
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            CMMS.showToast(result.message || 'Lỗi khi cập nhật trạng thái', 'error');
        }
    } catch (error) {
        console.error('Update status error:', error);
        CMMS.showToast('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        CMMS.hideLoading();
    }
}

function generateQR() {
    const equipmentUrl = `${window.location.origin}${window.location.pathname}?id=${EquipmentView.equipmentId}`;
    const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(equipmentUrl)}`;
    
    const qrContainer = document.getElementById('qrCodeContainer');
    qrContainer.innerHTML = `
        <img src="${qrCodeUrl}" alt="QR Code" style="width: 150px; height: 150px; border-radius: 0.375rem;">
        <div class="mt-2">
            <small class="text-muted">Quét để xem thiết bị</small>
        </div>
        <div class="mt-2">
            <a href="${qrCodeUrl}" download="equipment-${EquipmentView.equipmentId}-qr.png" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-download me-1"></i>Tải QR
            </a>
        </div>
    `;
    
    CMMS.showToast('Đã tạo mã QR thành công', 'success');
}

function createMaintenanceSchedule() {
    CMMS.showToast('Chức năng lên lịch bảo trì đang được phát triển', 'info');
    // TODO: Implement maintenance scheduling
}

function viewMaintenanceHistory() {
    CMMS.showToast('Chức năng xem lịch sử bảo trì đang được phát triển', 'info');
    // TODO: Implement maintenance history modal
}

function viewFullMaintenanceHistory() {
    CMMS.showToast('Chức năng xem đầy đủ lịch sử bảo trì đang được phát triển', 'info');
    // TODO: Implement full maintenance history page
}

function printEquipment() {
    // Hide print-unfriendly elements
    const elementsToHide = document.querySelectorAll('.btn, .dropdown, .maintenance-alert');
    elementsToHide.forEach(el => el.style.display = 'none');
    
    // Print
    window.print();
    
    // Restore hidden elements
    setTimeout(() => {
        elementsToHide.forEach(el => el.style.display = '');
    }, 1000);
}

function exportEquipment() {
    CMMS.showToast('Chức năng xuất PDF đang được phát triển', 'info');
    // TODO: Implement PDF export
}

function uploadFiles() {
    CMMS.showToast('Chức năng upload file đang được phát triển', 'info');
    // TODO: Implement file upload modal
}

function deleteEquipment() {
    if (!<?php echo json_encode(hasPermission('equipment', 'delete')); ?>) {
        CMMS.showToast('Bạn không có quyền xóa thiết bị', 'error');
        return;
    }
    
    const message = `Bạn có chắc chắn muốn xóa thiết bị "${EquipmentView.equipmentData.name}"?\n\nHành động này sẽ xóa:\n• Tất cả thông tin thiết bị\n• Lịch sử bảo trì\n• File đính kèm\n\nVà không thể hoàn tác.`;
    
    if (confirm(message)) {
        performDelete();
    }
}

async function performDelete() {
    try {
        CMMS.showLoading();
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', EquipmentView.equipmentId);
        
        const response = await fetch('../api/equipment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            CMMS.showToast(result.message, 'success');
            
            // Redirect to equipment list after successful deletion
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        } else {
            CMMS.showToast(result.message || 'Lỗi khi xóa thiết bị', 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        CMMS.showToast('Lỗi kết nối. Vui lòng thử lại.', 'error');
    } finally {
        CMMS.hideLoading();
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    EquipmentView.init();
    
    // Add smooth scroll behavior to internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Auto-hide alerts after some time
    const alerts = document.querySelectorAll('.maintenance-alert');
    alerts.forEach(alert => {
        // Add close button if not exists
        if (!alert.querySelector('.btn-close')) {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn-close ms-auto';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.onclick = function() {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            };
            alert.appendChild(closeBtn);
        }
    });
    
    // Enhance image loading with lazy loading
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return;
    }
    
    if (e.ctrlKey || e.metaKey) {
        switch(e.key.toLowerCase()) {
            case 'e':
                e.preventDefault();
                if (<?php echo json_encode(hasPermission('equipment', 'edit')); ?>) {
                    window.location.href = `edit.php?id=${EquipmentView.equipmentId}`;
                }
                break;
            case 'p':
                e.preventDefault();
                printEquipment();
                break;
            case 's':
                e.preventDefault();
                changeStatus();
                break;
            case 'm':
                e.preventDefault();
                createMaintenanceSchedule();
                break;
        }
    }
    
    // ESC key to close modals
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        });
    }
});

// Add contextual help tooltips
const helpTooltips = [
    {
        selector: '.criticality-badge',
        title: 'Mức độ quan trọng của thiết bị ảnh hưởng đến ưu tiên bảo trì và thời gian phản hồi khi có sự cố'
    },
    {
        selector: '.status-indicator',
        title: 'Trạng thái hiện tại của thiết bị. Click để thay đổi nếu có quyền'
    },
    {
        selector: '.maintenance-alert',
        title: 'Cảnh báo bảo trì dựa trên chu kỳ bảo trì đã thiết lập'
    }
];

helpTooltips.forEach(({ selector, title }) => {
    const elements = document.querySelectorAll(selector);
    elements.forEach(element => {
        element.setAttribute('data-bs-toggle', 'tooltip');
        element.setAttribute('data-bs-placement', 'top');
        element.setAttribute('title', title);
        new bootstrap.Tooltip(element);
    });
});

// Add click-to-copy functionality for equipment code
document.addEventListener('DOMContentLoaded', function() {
    const equipmentCodeBadge = document.querySelector('.equipment-code-badge');
    if (equipmentCodeBadge) {
        equipmentCodeBadge.style.cursor = 'pointer';
        equipmentCodeBadge.title = 'Click để copy mã thiết bị';
        
        equipmentCodeBadge.addEventListener('click', function() {
            const code = this.textContent.trim();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    CMMS.showToast(`Đã copy mã thiết bị: ${code}`, 'success');
                    
                    // Visual feedback
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    CMMS.showToast(`Đã copy mã thiết bị: ${code}`, 'success');
                } catch (err) {
                    CMMS.showToast('Không thể copy mã thiết bị', 'error');
                }
                document.body.removeChild(textArea);
            }
        });
    }
});

// Performance monitoring
let performanceMetrics = {
    pageLoadTime: 0,
    renderTime: 0
};

window.addEventListener('load', function() {
    performanceMetrics.pageLoadTime = performance.now();
    console.log(`Equipment view page loaded in ${performanceMetrics.pageLoadTime.toFixed(2)}ms`);
});

// Add search functionality within the page content
function initPageSearch() {
    // Add search box in the header if needed
    const headerActions = document.querySelector('.btn-toolbar');
    if (headerActions) {
        const searchBox = document.createElement('div');
        searchBox.className = 'input-group input-group-sm me-2';
        searchBox.style.width = '200px';
        searchBox.innerHTML = `
            <span class="input-group-text">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" class="form-control" placeholder="Tìm trong trang..." id="pageSearch">
        `;
        
        headerActions.insertBefore(searchBox, headerActions.firstChild);
        
        // Add search functionality
        const searchInput = document.getElementById('pageSearch');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const sections = document.querySelectorAll('.info-section');
            
            sections.forEach(section => {
                const text = section.textContent.toLowerCase();
                const shouldShow = !searchTerm || text.includes(searchTerm);
                
                section.style.display = shouldShow ? '' : 'none';
                
                if (shouldShow && searchTerm) {
                    // Highlight matching text
                    highlightText(section, searchTerm);
                } else {
                    // Remove highlights
                    removeHighlights(section);
                }
            });
        });
    }
}

function highlightText(element, searchTerm) {
    // Simple text highlighting implementation
    const walker = document.createTreeWalker(
        element,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );
    
    const textNodes = [];
    let node;
    
    while (node = walker.nextNode()) {
        textNodes.push(node);
    }
    
    textNodes.forEach(textNode => {
        const text = textNode.textContent;
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        
        if (regex.test(text)) {
            const highlightedText = text.replace(regex, '<mark>$1</mark>');
            const wrapper = document.createElement('span');
            wrapper.innerHTML = highlightedText;
            textNode.parentNode.replaceChild(wrapper, textNode);
        }
    });
}

function removeHighlights(element) {
    const highlights = element.querySelectorAll('mark');
    highlights.forEach(highlight => {
        highlight.replaceWith(highlight.textContent);
    });
}

// Add equipment comparison functionality (for future use)
function addToComparison() {
    const comparisonList = JSON.parse(localStorage.getItem('equipmentComparison') || '[]');
    
    if (!comparisonList.includes(EquipmentView.equipmentId)) {
        comparisonList.push(EquipmentView.equipmentId);
        localStorage.setItem('equipmentComparison', JSON.stringify(comparisonList));
        
        CMMS.showToast(`Đã thêm "${EquipmentView.equipmentData.name}" vào danh sách so sánh`, 'success');
        
        // Update comparison indicator
        updateComparisonIndicator();
    } else {
        CMMS.showToast('Thiết bị đã có trong danh sách so sánh', 'info');
    }
}

function updateComparisonIndicator() {
    const comparisonList = JSON.parse(localStorage.getItem('equipmentComparison') || '[]');
    
    if (comparisonList.length > 0) {
        // Add comparison indicator to header if not exists
        if (!document.getElementById('comparisonIndicator')) {
            const indicator = document.createElement('div');
            indicator.id = 'comparisonIndicator';
            indicator.className = 'position-fixed bottom-0 end-0 m-3 p-3 bg-primary text-white rounded shadow';
            indicator.style.zIndex = '1050';
            indicator.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-balance-scale"></i>
                    <span>So sánh (${comparisonList.length})</span>
                    <button type="button" class="btn btn-sm btn-light" onclick="viewComparison()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(indicator);
        }
    }
}

function viewComparison() {
    const comparisonList = JSON.parse(localStorage.getItem('equipmentComparison') || '[]');
    if (comparisonList.length > 0) {
        window.open(`comparison.php?ids=${comparisonList.join(',')}`, '_blank');
    }
}

// Initialize additional features
setTimeout(() => {
    initPageSearch();
    updateComparisonIndicator();
}, 1000);

console.log('Equipment view JavaScript loaded successfully');
</script>

<?php require_once '../../includes/footer.php'; ?>                        </div>
                    </div>
                </div>

                <!-- Location & Classification -->
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-map-marked-alt section-icon"></i>
                        <h5>Vị trí & Phân loại</h5>
                    </div>
                    <div class="info-section-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Ngành sản xuất</div>
                                <div class="info-value">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($equipment['industry_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Xưởng sản xuất</div>
                                <div class="info-value">
                                    <span class="badge bg-info"><?php echo htmlspecialchars($equipment['workshop_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Line sản xuất</div>
                                <div class="info-value <?php echo empty($equipment['line_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['line_name']): ?>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($equipment['line_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân line
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Khu vực</div>
                                <div class="info-value <?php echo empty($equipment['area_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['area_name']): ?>
                                        <span class="badge bg-warning"><?php echo htmlspecialchars($equipment['area_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân khu vực
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Dòng máy</div>
                                <div class="info-value">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($equipment['machine_type_name']); ?></span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cụm thiết bị</div>
                                <div class="info-value <?php echo empty($equipment['equipment_group_name']) ? 'empty' : ''; ?>">
                                    <?php if ($equipment['equipment_group_name']): ?>
                                        <span class="badge bg-dark"><?php echo htmlspecialch<?php
/**
 * Equipment View Details - modules/equipment/view.php
 * Display detailed information about a specific equipment
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission('equipment', 'view');

// Get equipment ID from URL
$equipmentId = (int)($_GET['id'] ?? 0);

if (!$equipmentId) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Get equipment details with all related information
$sql = "
    SELECT 
        e.*,
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
    
    WHERE e.id = ?
";

$equipment = $db->fetch($sql, [$equipmentId]);

if (!$equipment) {
    header('HTTP/1.0 404 Not Found');
    include '../../errors/404.php';
    exit;
}

// Set page variables
$pageTitle = 'Chi tiết thiết bị: ' . $equipment['name'];
$currentModule = 'equipment';
$moduleCSS = 'equipment';

$breadcrumb = [
    ['title' => 'Quản lý thiết bị', 'url' => '/modules/equipment/'],
    ['title' => 'Chi tiết thiết bị']
];

// Page actions
$pageActions = '';
if (hasPermission('equipment', 'edit')) {
    $pageActions .= '<a href="edit.php?id=' . $equipmentId . '" class="btn btn-primary">
        <i class="fas fa-edit me-1"></i> Chỉnh sửa
    </a> ';
}

if (hasPermission('equipment', 'create')) {
    $pageActions .= '<a href="add.php" class="btn btn-outline-success">
        <i class="fas fa-plus me-1"></i> Thêm thiết bị mới
    </a> ';
}

$pageActions .= '
<div class="btn-group">
    <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-tools me-1"></i> Thao tác
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" onclick="printEquipment()">
            <i class="fas fa-print me-2"></i>In thông tin
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="exportEquipment()">
            <i class="fas fa-download me-2"></i>Xuất PDF
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="generateQR()">
            <i class="fas fa-qrcode me-2"></i>Tạo mã QR
        </a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="createMaintenanceSchedule()">
            <i class="fas fa-calendar-plus me-2"></i>Lên lịch bảo trì
        </a></li>
        <li><a class="dropdown-item" href="#" onclick="viewMaintenanceHistory()">
            <i class="fas fa-history me-2"></i>Lịch sử bảo trì
        </a></li>';

if (hasPermission('equipment', 'delete')) {
    $pageActions .= '
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#" onclick="deleteEquipment()">
            <i class="fas fa-trash me-2"></i>Xóa thiết bị
        </a></li>';
}

$pageActions .= '</ul></div>';

// Format some data
$equipment['created_at_formatted'] = formatDateTime($equipment['created_at']);
$equipment['updated_at_formatted'] = formatDateTime($equipment['updated_at']);
$equipment['installation_date_formatted'] = formatDate($equipment['installation_date']);
$equipment['warranty_expiry_formatted'] = formatDate($equipment['warranty_expiry']);
$equipment['status_text'] = getStatusText($equipment['status']);
$equipment['status_class'] = getStatusClass($equipment['status']);

// Parse technical specs if JSON
$technical_specs = [];
if ($equipment['technical_specs']) {
    $parsed = json_decode($equipment['technical_specs'], true);
    if ($parsed) {
        $technical_specs = $parsed;
    }
}

// Parse settings images if JSON
$settings_images = [];
if ($equipment['settings_images']) {
    $parsed = json_decode($equipment['settings_images'], true);
    if ($parsed) {
        $settings_images = $parsed;
    }
}

// Calculate next maintenance date and status
$next_maintenance = null;
$maintenance_due = false;
$days_until_maintenance = null;

if ($equipment['maintenance_frequency_days'] && $equipment['installation_date']) {
    $installDate = new DateTime($equipment['installation_date']);
    $nextMaintenance = clone $installDate;
    $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    
    // Keep adding maintenance intervals until we get a future date
    $today = new DateTime();
    while ($nextMaintenance <= $today) {
        $nextMaintenance->add(new DateInterval('P' . $equipment['maintenance_frequency_days'] . 'D'));
    }
    
    $next_maintenance = $nextMaintenance->format('d/m/Y');
    $days_until_maintenance = $today->diff($nextMaintenance)->days;
    $maintenance_due = $days_until_maintenance <= 7;
}

// Get recent maintenance history (mock data for now)
$maintenance_history = [
    [
        'date' => '15/08/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Kiểm tra tổng quát, thay dầu máy, làm sạch bộ lọc',
        'technician' => 'Nguyễn Văn A',
        'status' => 'completed',
        'duration' => '2 giờ'
    ],
    [
        'date' => '15/05/2024',
        'type' => 'Sửa chữa',
        'description' => 'Thay thế bearing bị mòn',
        'technician' => 'Trần Văn B',
        'status' => 'completed',
        'duration' => '4 giờ'
    ],
    [
        'date' => '15/02/2024',
        'type' => 'Bảo trì định kỳ',
        'description' => 'Bảo trì định kỳ quý I',
        'technician' => 'Lê Văn C',
        'status' => 'completed',
        'duration' => '3 giờ'
    ]
];

// Build location path
$location_parts = array_filter([
    $equipment['industry_name'],
    $equipment['workshop_name'],
    $equipment['line_name'],
    $equipment['area_name']
]);
$location_path = implode(' → ', $location_parts);

require_once '../../includes/header.php';
?>                