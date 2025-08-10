<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_module = basename(dirname($_SERVER['PHP_SELF']));

function isActiveMenu($page, $module = '') {
    global $current_page, $current_module;
    if ($module) {
        return ($current_module === $module) ? 'active' : '';
    }
    return ($current_page === $page) ? 'active' : '';
}
?>

<!-- Sidebar -->
<aside class="main-sidebar" id="mainSidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="/" class="<?= isActiveMenu('index') ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <?php if (hasPermission(['admin', 'to_truong'])): ?>
        <li>
            <a href="/modules/equipment/" class="<?= isActiveMenu('', 'equipment') ?>">
                <i class="fas fa-cogs"></i>
                <span>Quản lý thiết bị</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission(['admin', 'to_truong'])): ?>
        <li>
            <a href="/modules/bom/" class="<?= isActiveMenu('', 'bom') ?>">
                <i class="fas fa-list-alt"></i>
                <span>BOM thiết bị</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="/modules/maintenance/" class="<?= isActiveMenu('', 'maintenance') ?>">
                <i class="fas fa-wrench"></i>
                <span>Kế hoạch bảo trì</span>
            </a>
        </li>
        
        <li>
            <a href="/modules/maintenance/history.php" class="<?= isActiveMenu('history', 'maintenance') ?>">
                <i class="fas fa-history"></i>
                <span>Lịch sử bảo trì</span>
            </a>
        </li>
        
        <?php if (hasPermission(['admin', 'to_truong'])): ?>
        <li>
            <a href="/modules/inventory/" class="<?= isActiveMenu('', 'inventory') ?>">
                <i class="fas fa-boxes"></i>
                <span>Tồn kho vật tư</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="/modules/calibration/" class="<?= isActiveMenu('', 'calibration') ?>">
                <i class="fas fa-balance-scale"></i>
                <span>Hiệu chuẩn thiết bị</span>
            </a>
        </li>
        
        <li>
            <a href="/modules/tasks/" class="<?= isActiveMenu('', 'tasks') ?>">
                <i class="fas fa-tasks"></i>
                <span>Quản lý công việc</span>
            </a>
        </li>
        
        <?php if (hasPermission(['admin', 'truong_ca'])): ?>
        <li>
            <a href="/modules/tasks/requests.php" class="<?= isActiveMenu('requests', 'tasks') ?>">
                <i class="fas fa-paper-plane"></i>
                <span>Yêu cầu công việc</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="/modules/qr-scanner/" class="<?= isActiveMenu('', 'qr-scanner') ?>">
                <i class="fas fa-qrcode"></i>
                <span>Quét mã QR</span>
            </a>
        </li>
        
        <?php if (hasPermission('admin')): ?>
        <li>
            <a href="/modules/users/" class="<?= isActiveMenu('', 'users') ?>">
                <i class="fas fa-users"></i>
                <span>Quản lý người dùng</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="/modules/reports/" class="<?= isActiveMenu('', 'reports') ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Báo cáo</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">