</main>

<!-- Footer -->
<footer class="text-center py-3 mt-5" style="margin-left: <?= isset($_SESSION['user_id']) ? 'var(--sidebar-width)' : '0' ?>;">
    <div class="container-fluid">
        <small class="text-muted">
            © 2025 CMMS - Hệ thống quản lý bảo trì. 
            Phiên bản 1.0 | 
            Phát triển bởi <?= $_SESSION['full_name'] ?? 'Team' ?>
        </small>
    </div>
</footer>

<!-- Load jQuery FIRST - Critical! -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Load other dependencies AFTER jQuery -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- Load CMMS Core AFTER jQuery -->
<script src="/assets/js/common/cmms-core.js"></script>

<script>
// Wait for jQuery to be fully loaded before initializing
function initializeCMMS() {
    if (typeof $ === 'undefined' || typeof jQuery === 'undefined') {
        console.log('Waiting for jQuery...');
        setTimeout(initializeCMMS, 100);
        return;
    }
    
    console.log('jQuery loaded, initializing CMMS...');
    
    // Initialize CMMS Core
    CMMS.init({
        baseUrl: '/',
        userId: <?= $_SESSION['user_id'] ?? 'null' ?>,
        userRole: '<?= $_SESSION['user_role'] ?? '' ?>'
    });
    
    console.log('CMMS Core initialized with user:', CMMS.userId, 'role:', CMMS.userRole);
    
    // Load notifications
    loadNotifications();
    
    // Auto-refresh notifications every 5 minutes
    setInterval(loadNotifications, 300000);
    
    // Trigger event that components are ready
    $(document).trigger('cmms-ready');
    
    // Load module-specific JS after CMMS is ready
    setTimeout(loadModuleJS, 500);
}

// Start initialization
initializeCMMS();

// Load notifications
function loadNotifications() {
    if (typeof CMMS === 'undefined') return;
    
    CMMS.ajax(CMMS.baseUrl + 'api/notifications.php', {
        method: 'GET'
    }).then(response => {
        if (response && response.success) {
            updateNotificationUI(response.data);
        }
    }).catch(error => {
        console.log('Notification load failed:', error);
    });
}

function updateNotificationUI(notifications) {
    const count = notifications.length;
    $('#notificationCount').text(count);
    
    const listHtml = notifications.length > 0 
        ? notifications.map(notif => `
            <li><a class="dropdown-item" href="${notif.link || '#'}">
                <div class="fw-bold">${notif.title}</div>
                <small class="text-muted">${notif.message}</small>
            </a></li>
        `).join('')
        : '<li><span class="dropdown-item-text text-muted">Không có thông báo mới</span></li>';
    
    $('#notificationList').html(listHtml);
}

// Load module-specific JavaScript based on current page
function loadModuleJS() {
    const currentPath = window.location.pathname;
    const currentFile = currentPath.split('/').pop().split('.')[0]; // Get filename without extension
    
    console.log('Loading modules for path:', currentPath, 'file:', currentFile);
    
    // Check which module we're in and load appropriate JS
    if (currentPath.includes('/modules/equipment/')) {
        if (currentFile === 'edit') {
            loadScript('/assets/js/modules/equipment-edit.js');
        } else if (currentFile === 'add') {
            loadScript('/assets/js/modules/equipment-add.js');
        } else {
            loadScript('/assets/js/modules/equipment.js');
        }
    } else if (currentPath.includes('/modules/maintenance/')) {
        if (currentFile === 'edit') {
            loadScript('/assets/js/modules/maintenance-edit.js');
        } else if (currentFile === 'add') {
            loadScript('/assets/js/modules/maintenance-add.js');
        } else {
            loadScript('/assets/js/modules/maintenance.js');
        }
    } else if (currentPath.includes('/modules/bom/')) {
        if (currentFile === 'edit') {
            loadScript('/assets/js/modules/bom-edit.js');
        } else if (currentFile === 'add') {
            loadScript('/assets/js/modules/bom-add.js');
        } else {
            loadScript('/assets/js/modules/boms.js');
        }
    } else if (currentPath.includes('/modules/tasks/')) {
        if (currentFile === 'edit') {
            loadScript('/assets/js/modules/tasks-edit.js');
        } else if (currentFile === 'add') {
            loadScript('/assets/js/modules/tasks-add.js');
        } else {
            loadScript('/assets/js/modules/tasks.js');
        }
    } else if (currentPath.includes('/modules/qr-scanner/')) {
        loadScript('/assets/js/modules/qr-scanner.js');
    } else if (currentPath.includes('/modules/inventory/')) {
        if (currentFile === 'edit') {
            loadScript('/assets/js/modules/inventory-edit.js');
        } else if (currentFile === 'add') {
            loadScript('/assets/js/modules/inventory-add.js');
        } else {
            loadScript('/assets/js/modules/inventory.js');
        }
    } else if (currentPath.includes('/modules/calibration/')) {
        if (currentFile === 'edit') {
            loadScript('/assets/js/modules/calibration-edit.js');
        } else if (currentFile === 'add') {
            loadScript('/assets/js/modules/calibration-add.js');
        } else {
            loadScript('/assets/js/modules/calibration.js');
        }
    } else if (currentPath.includes('/modules/users/')) {
        if (currentFile === 'edit') {
            loadScript('/assets/js/modules/users-edit.js');
        } else if (currentFile === 'add') {
            loadScript('/assets/js/modules/users-add.js');
        } else {
            loadScript('/assets/js/modules/users.js');
        }
    } else if (currentPath.includes('/modules/reports/')) {
        loadScript('/assets/js/modules/reports.js');
    }
}

function loadScript(src) {
    // Check if script is already loaded
    if (document.querySelector(`script[src="${src}"]`)) {
        return;
    }
    
    const script = document.createElement('script');
    script.src = src;
    script.onload = function() {
        console.log(`Module script loaded: ${src}`);
    };
    script.onerror = function() {
        console.warn(`Failed to load module script: ${src}`);
    };
    document.head.appendChild(script);
}

// Debug functions (keep these for development)
window.debugCMMS = function() {
    console.log('CMMS Debug Info:');
    console.log('User ID:', CMMS.userId);
    console.log('User Role:', CMMS.userRole);
    console.log('Base URL:', CMMS.baseUrl);
    console.log('jQuery version:', $.fn.jquery);
    console.log('jQuery available:', typeof $ !== 'undefined');
};

window.testEquipmentAPI = function() {
    console.log('Testing Equipment API...');
    CMMS.ajax('/modules/equipment/api.php', {
        method: 'GET',
        data: { action: 'list_simple' }
    }).then(response => {
        console.log('Equipment API Response:', response);
    }).catch(error => {
        console.error('Equipment API Error:', error);
    });
};
</script>

</body>
</html>