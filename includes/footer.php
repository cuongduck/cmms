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

<!-- Load jQuery FIRST -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
// Đợi jQuery load xong
$(document).ready(function() {
    console.log('jQuery loaded successfully');
    
    // Khởi tạo CMMS object ngay sau khi jQuery ready
    window.CMMS = {
        baseUrl: '/',
        userId: <?= $_SESSION['user_id'] ?? 'null' ?>,
        userRole: '<?= $_SESSION['user_role'] ?? '' ?>',
        
        // AJAX wrapper with error handling
        ajax: function(url, options = {}) {
            const defaults = {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            const config = { ...defaults, ...options };
            
            if (config.data && !(config.data instanceof FormData)) {
                if (config.method === 'GET') {
                    const params = new URLSearchParams(config.data);
                    url += (url.includes('?') ? '&' : '?') + params.toString();
                } else {
                    config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    config.body = new URLSearchParams(config.data);
                }
            } else if (config.data instanceof FormData) {
                config.body = config.data;
            }
            
            return fetch(url, config)
                .then(response => {
                    if (response.status === 401) {
                        window.location.href = CMMS.baseUrl + 'auth/login.php';
                        return;
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    CMMS.showAlert('Lỗi kết nối mạng', 'error');
                    throw error;
                });
        },
        
        // Show alert using SweetAlert2
        showAlert: function(message, type = 'info', title = '') {
            if (typeof Swal !== 'undefined') {
                const icons = {
                    success: 'success',
                    error: 'error',
                    warning: 'warning',
                    info: 'info'
                };
                
                Swal.fire({
                    title: title,
                    text: message,
                    icon: icons[type],
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#667eea'
                });
            } else {
                alert(message);
            }
        },
        
        // Confirm dialog
        confirm: function(message, title = 'Xác nhận') {
            if (typeof Swal !== 'undefined') {
                return Swal.fire({
                    title: title,
                    text: message,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#667eea',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Xác nhận',
                    cancelButtonText: 'Hủy'
                });
            } else {
                return Promise.resolve({ isConfirmed: confirm(message) });
            }
        },
        
        // Format date
        formatDate: function(dateString, format = 'dd/mm/yyyy') {
            if (!dateString) return '';
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            
            switch(format) {
                case 'dd/mm/yyyy':
                    return `${day}/${month}/${year}`;
                case 'yyyy-mm-dd':
                    return `${year}-${month}-${day}`;
                default:
                    return date.toLocaleDateString('vi-VN');
            }
        },
        
        // Loading overlay
        showLoading: function(target = 'body') {
            $(target).append('<div class="loading-overlay d-flex justify-content-center align-items-center"><div class="spinner-border text-primary"></div></div>');
        },
        
        hideLoading: function(target = 'body') {
            $(target).find('.loading-overlay').remove();
        }
    };
    
    console.log('CMMS object initialized:', CMMS);
    
    // Load other scripts after CMMS is ready
    loadOtherScripts();
});

function loadOtherScripts() {
    // Load Bootstrap
    const bootstrapScript = document.createElement('script');
    bootstrapScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
    bootstrapScript.onload = function() {
        console.log('Bootstrap loaded');
        loadSelect2();
    };
    document.head.appendChild(bootstrapScript);
}

function loadSelect2() {
    // Load Select2
    const select2Script = document.createElement('script');
    select2Script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
    select2Script.onload = function() {
        console.log('Select2 loaded');
        loadDataTables();
    };
    document.head.appendChild(select2Script);
}

function loadDataTables() {
    // Load DataTables
    const dtScript = document.createElement('script');
    dtScript.src = 'https://cdn.jsdelivr.net/npm/datatables.net@1.13.4/js/jquery.dataTables.min.js';
    dtScript.onload = function() {
        const dtBsScript = document.createElement('script');
        dtBsScript.src = 'https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.4/js/dataTables.bootstrap5.min.js';
        dtBsScript.onload = function() {
            console.log('DataTables loaded');
            loadSweetAlert();
        };
        document.head.appendChild(dtBsScript);
    };
    document.head.appendChild(dtScript);
}

function loadSweetAlert() {
    // Load SweetAlert2
    const swalScript = document.createElement('script');
    swalScript.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    swalScript.onload = function() {
        console.log('SweetAlert2 loaded');
        loadQRCode();
    };
    document.head.appendChild(swalScript);
}

function loadQRCode() {
    // Load QR Code
    const qrScript = document.createElement('script');
    qrScript.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    qrScript.onload = function() {
        console.log('QR Code loaded');
        initializeComponents();
    };
    document.head.appendChild(qrScript);
}

function initializeComponents() {
    console.log('Initializing components...');
    
    // Sidebar toggle for mobile
    $('#sidebarToggle').click(function() {
        $('#mainSidebar').toggleClass('show');
        $('#sidebarOverlay').toggleClass('show');
    });
    
    $('#sidebarOverlay').click(function() {
        $('#mainSidebar').removeClass('show');
        $('#sidebarOverlay').removeClass('show');
    });
    
    // Initialize Select2
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: 'Chọn...'
        });
    }
    
    // Initialize DataTables with Vietnamese
    if ($.fn.DataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                "sProcessing":     "Đang xử lý...",
                "sLengthMenu":     "Hiển thị _MENU_ mục",
                "sZeroRecords":    "Không tìm thấy dữ liệu",
                "sInfo":           "Hiển thị _START_ đến _END_ trong tổng số _TOTAL_ mục",
                "sInfoEmpty":      "Hiển thị 0 đến 0 trong tổng số 0 mục",
                "sInfoFiltered":   "(được lọc từ _MAX_ mục)",
                "sSearch":         "Tìm kiếm:",
                "oPaginate": {
                    "sFirst":    "Đầu",
                    "sPrevious": "Trước",
                    "sNext":     "Tiếp",
                    "sLast":     "Cuối"
                }
            },
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
        });
    }
    
    // Load notifications
    loadNotifications();
    
    // Auto-refresh notifications every 5 minutes
    setInterval(loadNotifications, 300000);
    
    // Trigger event that components are ready
    $(document).trigger('cmms-ready');
    console.log('All components initialized');
}

// Load notifications
function loadNotifications() {
    if (typeof CMMS !== 'undefined') {
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

// Change password function
function changePassword() {
    if (typeof Swal === 'undefined') {
        alert('SweetAlert2 chưa được load');
        return;
    }
    
    Swal.fire({
        title: 'Đổi mật khẩu',
        html: `
            <div class="mb-3">
                <input type="password" class="form-control" id="currentPassword" placeholder="Mật khẩu hiện tại">
            </div>
            <div class="mb-3">
                <input type="password" class="form-control" id="newPassword" placeholder="Mật khẩu mới">
            </div>
            <div class="mb-3">
                <input type="password" class="form-control" id="confirmPassword" placeholder="Xác nhận mật khẩu mới">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Cập nhật',
        cancelButtonText: 'Hủy',
        preConfirm: () => {
            const current = document.getElementById('currentPassword').value;
            const newPass = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmPassword').value;
            
            if (!current || !newPass || !confirm) {
                Swal.showValidationMessage('Vui lòng nhập đầy đủ thông tin');
                return false;
            }
            
            if (newPass !== confirm) {
                Swal.showValidationMessage('Mật khẩu xác nhận không khớp');
                return false;
            }
            
            if (newPass.length < 6) {
                Swal.showValidationMessage('Mật khẩu mới phải có ít nhất 6 ký tự');
                return false;
            }
            
            return { current, newPass };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            CMMS.ajax(CMMS.baseUrl + 'api/change-password.php', {
                data: {
                    current_password: result.value.current,
                    new_password: result.value.newPass
                }
            }).then(response => {
                if (response && response.success) {
                    CMMS.showAlert('Đổi mật khẩu thành công', 'success');
                } else {
                    CMMS.showAlert(response ? response.message : 'Lỗi đổi mật khẩu', 'error');
                }
            });
        }
    });
}

// Debug functions
window.debugCMMS = function() {
    console.log('CMMS Debug Info:');
    console.log('User ID:', CMMS.userId);
    console.log('User Role:', CMMS.userRole);
    console.log('Base URL:', CMMS.baseUrl);
    console.log('jQuery version:', $.fn.jquery);
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

window.testBOMAPI = function() {
    console.log('Testing BOM API...');
    CMMS.ajax('/modules/bom/api.php', {
        method: 'POST',
        data: { action: 'list', page: 1 }
    }).then(response => {
        console.log('BOM API Response:', response);
    }).catch(error => {
        console.error('BOM API Error:', error);
    });
};
</script>

</body>
</html>