<?php
$modules = getConfig('modules');
$currentUser = getCurrentUser();
?>

<div class="position-sticky pt-3">
    <ul class="nav flex-column">
        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($currentModule === 'dashboard') ? 'active' : ''; ?>" 
               href="/index.php">
                <i class="fas fa-tachometer-alt"></i>
                Tổng quan
            </a>
        </li>
        
        <?php foreach ($modules as $moduleKey => $module): ?>
            <?php if (hasPermission($moduleKey, 'view')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentModule === $moduleKey) ? 'active' : ''; ?>" 
                       href="<?php echo $module['url']; ?>">
                        <i class="<?php echo $module['icon']; ?>"></i>
                        <?php echo $module['name']; ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Divider -->
        <li><hr class="dropdown-divider my-3 opacity-25"></li>
        
        <!-- Reports -->
        <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#reportsSubmenu" aria-expanded="false">
                <i class="fas fa-chart-bar"></i>
                Báo cáo
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse" id="reportsSubmenu">
                <ul class="nav flex-column ms-3">
                    <li class="nav-item">
                        <a class="nav-link py-2" href="<?php echo APP_URL; ?>/reports/equipment.php">
                            <i class="fas fa-circle"></i>
                            Báo cáo thiết bị
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2" href="<?php echo APP_URL; ?>/reports/maintenance.php">
                            <i class="fas fa-circle"></i>
                            Báo cáo bảo trì
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2" href="<?php echo APP_URL; ?>/reports/inventory.php">
                            <i class="fas fa-circle"></i>
                            Báo cáo tồn kho
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        <!-- Thêm vào phần Spare Parts menu -->
<?php if (hasPermission('spare_parts', 'view')): ?>
<li class="nav-item">
    <a class="nav-link <?php echo ($currentModule === 'spare_parts') ? 'active' : ''; ?>" 
       href="#" data-bs-toggle="collapse" data-bs-target="#sparePartsSubmenu" aria-expanded="false">
        <i class="fas fa-tools"></i>
        Quản lý Spare Parts
        <i class="fas fa-chevron-down ms-auto"></i>
    </a>
    <div class="collapse" id="sparePartsSubmenu">
        <ul class="nav flex-column ms-3">
            <li class="nav-item">
                <a class="nav-link py-2" href="/modules/spare_parts/">
                    <i class="fas fa-list"></i>
                    Danh sách Spare Parts
                </a>
            </li>
            <?php if (hasPermission('spare_parts', 'create')): ?>
            <li class="nav-item">
                <a class="nav-link py-2" href="/modules/spare_parts/add.php">
                    <i class="fas fa-plus"></i>
                    Thêm Spare Part
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link py-2" href="/modules/spare_parts/purchase_request.php">
                    <i class="fas fa-shopping-cart"></i>
                    Đề xuất mua hàng
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link py-2" href="/modules/spare_parts/reports/stock_shortage.php">
                    <i class="fas fa-exclamation-triangle"></i>
                    Báo cáo thiếu hàng
                </a>
            </li>
            <?php if (hasPermission('spare_parts', 'edit')): ?>
            <li class="nav-item">
                <a class="nav-link py-2" href="/modules/spare_parts/category_keywords.php">
                    <i class="fas fa-tags"></i>
                    Quản lý từ khóa
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</li>
<?php endif; ?>
<!-- Thêm vào phần menu chính -->
<?php if (hasPermission('inventory', 'view')): ?>
<li class="nav-item">
    <a class="nav-link <?php echo ($currentModule === 'transactions') ? 'active' : ''; ?>" 
       href="/modules/transactions/">
        <i class="fas fa-exchange-alt"></i>
        Giao dịch xuất kho
    </a>
</li>
<?php endif; ?>
    </ul>
    
    <!-- Quick Actions -->
    <div class="mt-4">
        <h6 class="text-light opacity-75 text-uppercase small fw-bold">Thao tác nhanh</h6>
        
        <?php if (hasPermission('equipment', 'create')): ?>
        <div class="d-grid gap-2 mt-2">
            <a href="<?php echo APP_URL; ?>/modules/equipment/add.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-plus me-1"></i>
                Thêm thiết bị
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (hasPermission('maintenance', 'create')): ?>
        <div class="d-grid gap-2 mt-2">
            <a href="<?php echo APP_URL; ?>/modules/maintenance/add.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-wrench me-1"></i>
                Tạo kế hoạch BT
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- System Info -->
    <div class="mt-4 pt-3 border-top border-light border-opacity-25">
        <small class="text-light opacity-50">
            <div>Version: <?php echo APP_VERSION; ?></div>
            <div>User: <?php echo $currentUser['username']; ?></div>
            <div>Role: <?php echo $currentUser['role']; ?></div>
        </small>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto expand active submenu
    const activeLink = document.querySelector('.nav-link.active');
    if (activeLink) {
        const parentCollapse = activeLink.closest('.collapse');
        if (parentCollapse) {
            parentCollapse.classList.add('show');
        }
    }
    
    // Handle sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }
    
    // Auto hide sidebar on mobile when clicking nav links
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            }
        });
    });
});
</script>