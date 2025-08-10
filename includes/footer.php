<?php
// includes/footer.php
?>
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

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
// Global CMMS JavaScript
const CMMS = {
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
            config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            config.body = new URLSearchParams(config.data);
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
            });
    },
    
    // Show alert using SweetAlert2
    showAlert: function(message, type = 'info', title = '') {
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
    },
    
    // Confirm dialog
    confirm: function(message, title = 'Xác nhận') {
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
        $(target).append('<div class="loading-overlay"><div class="spinner-border text-primary"></div></div>');
    },
    
    hideLoading: function(target = 'body') {
        $(target).find('.loading-overlay').remove();
    }
};

// Document ready
$(document).ready(function() {
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
    $('.select2').select2({
        theme: 'bootstrap-5',
        placeholder: 'Chọn...'
    });
    
    // Initialize DataTables with Vietnamese
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
    
    // Load notifications
    loadNotifications();
    
    // Auto-refresh notifications every 5 minutes
    setInterval(loadNotifications, 300000);
});

// Load notifications
function loadNotifications() {
    CMMS.ajax(CMMS.baseUrl + 'api/notifications.php', {
        method: 'GET'
    }).then(response => {
        if (response && response.success) {
            updateNotificationUI(response.data);
        }
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

// Change password function
function changePassword() {
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
                if (response.success) {
                    CMMS.showAlert('Đổi mật khẩu thành công', 'success');
                } else {
                    CMMS.showAlert(response.message, 'error');
                }
            });
        }
    });
}
</script>

</body>
</html>