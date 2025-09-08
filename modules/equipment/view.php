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

// Format some data - Sửa lỗi: Kiểm tra hàm tồn tại
$equipment['created_at_formatted'] = !empty($equipment['created_at']) ? formatDateTime($equipment['created_at']) : 'N/A';
$equipment['updated_at_formatted'] = !empty($equipment['updated_at']) ? formatDateTime($equipment['updated_at']) : 'N/A';
$equipment['installation_date_formatted'] = !empty($equipment['installation_date']) ? formatDate($equipment['installation_date']) : '';
$equipment['warranty_expiry_formatted'] = !empty($equipment['warranty_expiry']) ? formatDate($equipment['warranty_expiry']) : '';
$equipment['status_text'] = getStatusText($equipment['status']);
$equipment['status_class'] = getStatusClass($equipment['status']);

// Parse technical specs if JSON - Sửa lỗi: Kiểm tra field tồn tại
$technical_specs = [];
if (isset($equipment['technical_specs']) && !empty($equipment['technical_specs'])) {
    $parsed = json_decode($equipment['technical_specs'], true);
    if (is_array($parsed)) {
        $technical_specs = $parsed;
    }
}

// Parse settings images if JSON - Sửa lỗi: Kiểm tra field tồn tại
$settings_images = [];
if (isset($equipment['settings_images']) && !empty($equipment['settings_images'])) {
    $parsed = json_decode($equipment['settings_images'], true);
    if (is_array($parsed)) {
        $settings_images = $parsed;
    }
}

// Calculate next maintenance date and status
$next_maintenance = null;
$maintenance_due = false;
$days_until_maintenance = null;

if (!empty($equipment['maintenance_frequency_days']) && !empty($equipment['installation_date'])) {
    try {
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
    } catch (Exception $e) {
        // Ignore date calculation errors
        $next_maintenance = null;
        $maintenance_due = false;
    }
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
    $equipment['industry_name'] ?? null,
    $equipment['workshop_name'] ?? null,
    $equipment['line_name'] ?? null,
    $equipment['area_name'] ?? null
]);
$location_path = implode(' → ', $location_parts);

// Format image URLs - Sửa lỗi: Kiểm tra file tồn tại
$equipment_image_url = null;
$equipment_manual_url = null;

if (!empty($equipment['image_path'])) {
    $full_image_path = BASE_PATH . '/' . ltrim($equipment['image_path'], '/');
    if (file_exists($full_image_path)) {
        $equipment_image_url = APP_URL . '/' . ltrim($equipment['image_path'], '/');
    }
}

if (!empty($equipment['manual_path'])) {
    $full_manual_path = BASE_PATH . '/' . ltrim($equipment['manual_path'], '/');
    if (file_exists($full_manual_path)) {
        $equipment_manual_url = APP_URL . '/' . ltrim($equipment['manual_path'], '/');
    }
}

require_once '../../includes/header.php';
?>
<!-- CSS Styles - Sửa lỗi và tối ưu -->
<style>
.equipment-view-container {
    background: var(--equipment-light, #f8fafc);
    min-height: 100vh;
}

.equipment-header {
    background: linear-gradient(135deg, var(--equipment-primary, #1e3a8a), #3b82f6);
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
    cursor: pointer;
    transition: all 0.2s ease;
}

.equipment-code-badge:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.05);
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
    color: var(--equipment-dark, #374151);
    font-weight: 600;
}

.info-section-header .section-icon {
    color: var(--equipment-primary, #1e3a8a);
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
    color: var(--equipment-dark, #374151);
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
    cursor: pointer;
    transition: transform 0.2s ease;
}

.main-equipment-image:hover {
    transform: scale(1.02);
}

.no-image-placeholder {
    width: 100%;
    height: 300px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    flex-direction: column;
    gap: 1rem;
}

.no-image-placeholder i {
    font-size: 3rem;
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
    border-color: var(--equipment-primary, #1e3a8a);
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
    animation: pulse-critical 2s infinite;
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

@keyframes pulse-critical {
    0%, 100% { 
        opacity: 1;
        transform: scale(1);
    }
    50% { 
        opacity: 0.8;
        transform: scale(1.05);
    }
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
    background: linear-gradient(to bottom, var(--equipment-primary, #1e3a8a), #e5e7eb);
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
    background: var(--equipment-primary, #1e3a8a);
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: 0 0 0 3px var(--equipment-primary, #1e3a8a);
}

.timeline-item.completed::before {
    background: var(--equipment-success, #10b981);
    box-shadow: 0 0 0 3px var(--equipment-success, #10b981);
}

.timeline-item.pending::before {
    background: var(--equipment-warning, #f59e0b);
    box-shadow: 0 0 0 3px var(--equipment-warning, #f59e0b);
}

.timeline-content {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 3px solid var(--equipment-primary, #1e3a8a);
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
    color: var(--equipment-dark, #374151);
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
    border-left: 3px solid var(--equipment-primary, #1e3a8a);
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
    color: var(--equipment-dark, #374151);
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
    animation: fadeInUp 0.5s ease-out;
}

.maintenance-alert.due {
    background: linear-gradient(135deg, #fef2f2, #fecaca);
    border-color: #ef4444;
    color: #991b1b;
    animation: pulse-alert 2s infinite;
}

.maintenance-alert i {
    font-size: 1.25rem;
}

@keyframes pulse-alert {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.file-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    color: var(--equipment-primary, #1e3a8a);
    text-decoration: none;
    transition: all 0.2s ease;
}

.file-link:hover {
    background: var(--equipment-primary, #1e3a8a);
    color: white;
    transform: translateY(-1px);
    text-decoration: none;
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

/* Responsive Design */
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
    
    .main-equipment-image,
    .no-image-placeholder {
        height: 200px;
    }
    
    .timeline-item {
        padding-left: 2rem;
    }
    
    .specs-grid {
        grid-template-columns: 1fr;
    }
}

/* Print Styles */
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

<!-- Equipment View HTML Structure -->
<div class="equipment-view-container">
    <!-- Equipment Header -->
    <div class="equipment-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="equipment-code-badge" title="Click để copy mã thiết bị">
                        <?php echo htmlspecialchars($equipment['code']); ?>
                    </div>
                    <h1 class="equipment-title">
                        <?php echo htmlspecialchars($equipment['name']); ?>
                    </h1>
                    <div class="equipment-subtitle">
                        <?php echo htmlspecialchars($location_path ?: 'Chưa xác định vị trí'); ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-3">
                        <div class="status-indicator status-<?php echo $equipment['status']; ?>" 
                             title="<?php echo $equipment['status_text']; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $equipment['status_text']; ?>
                        </div>
                    </div>
                    <div class="criticality-badge criticality-<?php echo strtolower($equipment['criticality']); ?>"
                         title="Mức độ quan trọng: <?php echo $equipment['criticality']; ?>">
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
                                    <?php echo htmlspecialchars($equipment['manufacturer'] ?: 'Chưa có thông tin'); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Model</div>
                                <div class="info-value <?php echo empty($equipment['model']) ? 'empty' : ''; ?>">
                                    <?php echo htmlspecialchars($equipment['model'] ?: 'Chưa có thông tin'); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Số seri</div>
                                <div class="info-value <?php echo empty($equipment['serial_number']) ? 'empty' : ''; ?>">
                                    <?php echo htmlspecialchars($equipment['serial_number'] ?: 'Chưa có thông tin'); ?>
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
                            <div class="info-item">
                                <div class="info-label">Dòng máy</div>
                                <div class="info-value">
                                    <?php if (!empty($equipment['machine_type_name'])): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($equipment['machine_type_name']); ?></span>
                                    <?php else: ?>
                                        <span class="empty">Chưa phân loại</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Cụm thiết bị</div>
                                <div class="info-value <?php echo empty($equipment['equipment_group_name']) ? 'empty' : ''; ?>">
                                    <?php if (!empty($equipment['equipment_group_name'])): ?>
                                        <span class="badge bg-dark"><?php echo htmlspecialchars($equipment['equipment_group_name']); ?></span>
                                    <?php else: ?>
                                        Chưa phân cụm
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($equipment['location_details'])): ?>
                        <div class="mt-3">
                            <div class="info-item">
                                <div class="info-label">Vị trí chi tiết</div>
                                <div class="info-value">
                                    <div class="p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($equipment['location_details'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
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
                                    <?php if (!empty($equipment['owner_name'])): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-user-circle text-primary"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($equipment['owner_name']); ?></div>
                                                <?php if (!empty($equipment['owner_email'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($equipment['owner_email']); ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($equipment['owner_phone'])): ?>
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
                                    <?php if (!empty($equipment['backup_owner_name'])): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fas fa-user text-secondary"></i>
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($equipment['backup_owner_name']); ?></div>
                                                <?php if (!empty($equipment['backup_owner_email'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($equipment['backup_owner_email']); ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($equipment['backup_owner_phone'])): ?>
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
                                    <?php if (!empty($equipment['maintenance_frequency_days'])): ?>
                                        <?php echo $equipment['maintenance_frequency_days']; ?> ngày 
                                        (<?php echo ucfirst($equipment['maintenance_frequency_type'] ?? 'monthly'); ?>)
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
                                        <?php if ($days_until_maintenance !== null): ?>
                                            <small class="d-block text-muted">
                                                (Còn <?php echo $days_until_maintenance; ?> ngày)
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Chưa lên lịch
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Trạng thái hiện tại</div>
                                <div class="info-value">
                                    <div class="status-indicator status-<?php echo $equipment['status']; ?>" 
                                         title="Click để thay đổi trạng thái" 
                                         style="cursor: pointer;" 
                                         onclick="changeStatus()">
                                        <i class="fas fa-circle"></i>
                                        <?php echo $equipment['status_text']; ?>
                                        <?php if (hasPermission('equipment', 'edit')): ?>
                                            <i class="fas fa-edit ms-2 small"></i>
                                        <?php endif; ?>
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
                <?php if (!empty($equipment['specifications']) || !empty($technical_specs)): ?>
                <div class="info-section">
                    <div class="info-section-header">
                        <i class="fas fa-cog section-icon"></i>
                        <h5>Thông số kỹ thuật</h5>
                    </div>
                    <div class="info-section-body">
                        <?php if (!empty($equipment['specifications'])): ?>
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
                                <?php foreach (array_slice($maintenance_history, 0, 5) as $maintenance): ?>
                                    <div class="timeline-item <?php echo htmlspecialchars($maintenance['status']); ?>">
                                        <div class="timeline-content">
                                            <div class="timeline-date"><?php echo htmlspecialchars($maintenance['date']); ?></div>
                                            <div class="timeline-title"><?php echo htmlspecialchars($maintenance['type']); ?></div>
                                            <div class="timeline-description">
                                                <?php echo htmlspecialchars($maintenance['description']); ?>
                                            </div>
                                            <div class="timeline-meta">
                                                <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($maintenance['technician']); ?></span>
                                                <span><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($maintenance['duration']); ?></span>
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

                <!-- Notes -->
                <?php if (!empty($equipment['notes'])): ?>
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
                    <?php if ($equipment_image_url): ?>
                        <img src="<?php echo htmlspecialchars($equipment_image_url); ?>" 
                             alt="<?php echo htmlspecialchars($equipment['name']); ?>" 
                             class="main-equipment-image"
                             onclick="showImageModal(this.src)"
                             loading="lazy">
                        
                        <?php if (!empty($settings_images)): ?>
                        <div class="image-thumbnails">
                            <?php foreach ($settings_images as $index => $image): ?>
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     alt="Settings Image <?php echo $index + 1; ?>" 
                                     class="image-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                     onclick="showImageModal(this.src)"
                                     loading="lazy">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <i class="fas fa-image"></i>
                            <div>Chưa có hình ảnh</div>
                            <?php if (hasPermission('equipment', 'edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="uploadFiles()">
                                    <i class="fas fa-upload me-1"></i>Upload ảnh
                                </button>
                            <?php endif; ?>
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
                            
                            <?php if (hasPermission('equipment', 'edit')): ?>
                            <button type="button" class="btn btn-outline-info" onclick="changeStatus()">
                                <i class="fas fa-exchange-alt me-2"></i>Thay đổi trạng thái
                            </button>
                            <?php endif; ?>
                            
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
                            
                            <?php if (hasPermission('equipment', 'delete')): ?>
                            <hr>
                            <button type="button" class="btn btn-outline-danger" onclick="deleteEquipment()">
                                <i class="fas fa-trash me-2"></i>Xóa thiết bị
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Files & Documents -->
                <div class="info-section mt-3">
                    <div class="info-section-header">
                        <i class="fas fa-file section-icon"></i>
                        <h5>Tài liệu & File</h5>
                    </div>
                    <div class="info-section-body">
                        <?php if ($equipment_manual_url): ?>
                            <div class="mb-2">
                                <a href="<?php echo htmlspecialchars($equipment_manual_url); ?>" 
                                   target="_blank" class="file-link">
                                    <i class="fas fa-file-pdf"></i>
                                    Hướng dẫn sử dụng
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Additional files can be added here -->
                        
                        <?php if (hasPermission('equipment', 'edit')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-2" onclick="uploadFiles()">
                                <i class="fas fa-upload me-2"></i>Upload tài liệu
                            </button>
                        <?php endif; ?>
                        
                        <?php if (!$equipment_manual_url && !hasPermission('equipment', 'edit')): ?>
                            <div class="text-center py-3 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0">Chưa có tài liệu</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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
                                <p class="mb-2 text-muted small">Mã QR để truy cập nhanh thông tin thiết bị</p>
                                <button type="button" class="btn btn-sm btn-primary" onclick="generateQR()">
                                    <i class="fas fa-magic me-1"></i>Tạo mã QR
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
                                    <div class="h6 mb-1 text-success">
                                        <?php echo count(array_filter($maintenance_history, function($m) { return $m['status'] === 'completed'; })); ?>
                                    </div>
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
                                        if (!empty($equipment['installation_date'])) {
                                            try {
                                                $installDate = new DateTime($equipment['installation_date']);
                                                $today = new DateTime();
                                                $diff = $installDate->diff($today);
                                                echo $diff->days;
                                            } catch (Exception $e) {
                                                echo 'N/A';
                                            }
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
                                    <div class="h6 mb-1 text-primary">
                                        <?php echo $equipment['status'] === 'active' ? '100%' : ($equipment['status'] === 'maintenance' ? '80%' : '0%'); ?>
                                    </div>
                                    <small class="text-muted">Hiệu suất</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related Equipment (if any) -->
              
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Hình ảnh thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="" class="img-fluid" style="max-height: 70vh;">
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
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Thay đổi trạng thái thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <div class="mb-3">
                        <label for="newStatus" class="form-label">Trạng thái mới:</label>
                        <select class="form-select" id="newStatus" name="status" required>
                            <option value="active" <?php echo $equipment['status'] === 'active' ? 'selected' : ''; ?>>
                                <i class="fas fa-check-circle"></i> Hoạt động
                            </option>
                            <option value="inactive" <?php echo $equipment['status'] === 'inactive' ? 'selected' : ''; ?>>
                                <i class="fas fa-pause-circle"></i> Ngưng hoạt động
                            </option>
                            <option value="maintenance" <?php echo $equipment['status'] === 'maintenance' ? 'selected' : ''; ?>>
                                <i class="fas fa-wrench"></i> Bảo trì
                            </option>
                            <option value="broken" <?php echo $equipment['status'] === 'broken' ? 'selected' : ''; ?>>
                                <i class="fas fa-times-circle"></i> Hỏng
                            </option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="statusNote" class="form-label">Ghi chú (tùy chọn):</label>
                        <textarea class="form-control" id="statusNote" name="note" rows="3" 
                                  placeholder="Lý do thay đổi trạng thái..."></textarea>
                        <div class="form-text">Ghi chú sẽ được lưu vào lịch sử thay đổi</div>
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

<!-- File Upload Modal -->
<div class="modal fade" id="fileUploadModal" tabindex="-1" aria-labelledby="fileUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fileUploadModalLabel">Upload tài liệu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="fileUploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="fileType" class="form-label">Loại file:</label>
                        <select class="form-select" id="fileType" name="file_type" required>
                            <option value="">Chọn loại file</option>
                            <option value="image">Hình ảnh</option>
                            <option value="manual">Hướng dẫn sử dụng</option>
                            <option value="document">Tài liệu khác</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="uploadFile" class="form-label">Chọn file:</label>
                        <input type="file" class="form-control" id="uploadFile" name="upload_file" required>
                        <div class="form-text">
                            Hình ảnh: JPG, PNG, GIF (tối đa 5MB)<br>
                            Tài liệu: PDF, DOC, DOCX (tối đa 10MB)
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="fileDescription" class="form-label">Mô tả (tùy chọn):</label>
                        <input type="text" class="form-control" id="fileDescription" name="description" 
                               placeholder="Mô tả ngắn về file...">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="performFileUpload()">
                    <i class="fas fa-upload me-2"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Maintenance Schedule Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1" aria-labelledby="maintenanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="maintenanceModalLabel">Lên lịch bảo trì</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="maintenanceForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="maintenanceType" class="form-label">Loại bảo trì:</label>
                                <select class="form-select" id="maintenanceType" name="type" required>
                                    <option value="">Chọn loại bảo trì</option>
                                    <option value="preventive">Bảo trì định kỳ</option>
                                    <option value="corrective">Sửa chữa</option>
                                    <option value="inspection">Kiểm tra</option>
                                    <option value="calibration">Hiệu chuẩn</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="maintenanceDate" class="form-label">Ngày thực hiện:</label>
                                <input type="datetime-local" class="form-control" id="maintenanceDate" 
                                       name="scheduled_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="maintenanceDescription" class="form-label">Mô tả công việc:</label>
                        <textarea class="form-control" id="maintenanceDescription" name="description" 
                                  rows="4" required placeholder="Mô tả chi tiết công việc cần thực hiện..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="assignedTechnician" class="form-label">Kỹ thuật viên:</label>
                                <select class="form-select" id="assignedTechnician" name="technician_id">
                                    <option value="">Chưa phân công</option>
                                    <!-- Dynamic options will be loaded -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="estimatedDuration" class="form-label">Thời gian dự kiến (giờ):</label>
                                <input type="number" class="form-control" id="estimatedDuration" 
                                       name="estimated_duration" min="0.5" step="0.5" placeholder="2.0">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success" onclick="createMaintenanceScheduleConfirm()">
                    <i class="fas fa-calendar-plus me-2"></i>Tạo lịch bảo trì
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include Equipment View JavaScript -->
<script src="<?php echo APP_URL; ?>/assets/js/equipment-view.js?v=<?php echo time(); ?>"></script>

<!-- Pass PHP data to JavaScript -->
<script>
// Equipment data for JavaScript
window.equipmentViewData = {
    equipmentId: <?php echo $equipmentId; ?>,
    equipment: <?php echo json_encode($equipment); ?>,
    permissions: {
        canEdit: <?php echo json_encode(hasPermission('equipment', 'edit')); ?>,
        canDelete: <?php echo json_encode(hasPermission('equipment', 'delete')); ?>,
        canCreate: <?php echo json_encode(hasPermission('equipment', 'create')); ?>
    },
    urls: {
        baseUrl: '<?php echo APP_URL; ?>',
        apiUrl: '<?php echo APP_URL; ?>/modules/equipment/api/equipment.php',
        editUrl: 'edit.php?id=<?php echo $equipmentId; ?>',
        listUrl: 'index.php'
    }
};

// Initialize equipment view when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof EquipmentView !== 'undefined') {
        EquipmentView.init();
    } else {
        console.error('EquipmentView not loaded');
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
