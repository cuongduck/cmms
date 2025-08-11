/**
 * CMMS Core JavaScript Library
 * Chứa các function chung cho toàn bộ hệ thống
 */

// Core CMMS object
window.CMMS = {
    baseUrl: '/',
    userId: null,
    userRole: '',
    
    // Initialize CMMS
    init: function(config = {}) {
        this.baseUrl = config.baseUrl || '/';
        this.userId = config.userId || null;
        this.userRole = config.userRole || '';
        
        console.log('CMMS Core initialized');
    },
    
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
                    window.location.href = this.baseUrl + 'auth/login.php';
                    return;
                }
                return response.json();
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                this.showAlert('Lỗi kết nối mạng', 'error');
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
    
    // Format currency
    formatCurrency: function(amount) {
        if (!amount) return '0 ₫';
        return new Intl.NumberFormat('vi-VN', { 
            style: 'currency', 
            currency: 'VND' 
        }).format(amount);
    },
    
    // Format number
    formatNumber: function(number) {
        if (!number) return '0';
        return new Intl.NumberFormat('vi-VN', { 
            minimumFractionDigits: 0,
            maximumFractionDigits: 3 
        }).format(number);
    },
    
    // Loading overlay
    showLoading: function(target = 'body') {
        $(target).append('<div class="loading-overlay d-flex justify-content-center align-items-center"><div class="spinner-border text-primary"></div></div>');
    },
    
    hideLoading: function(target = 'body') {
        $(target).find('.loading-overlay').remove();
    },
    
    // Common pagination
    displayPagination: function(pagination, containerId = 'pagination', callback = 'changePage') {
        const totalPages = pagination.total_pages;
        const currentPage = pagination.current_page;
        
        let html = '';
        
        if (totalPages > 1) {
            html += '<ul class="pagination justify-content-center">';
            
            if (pagination.has_previous) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="${callback}(${currentPage - 1})">Trước</a></li>`;
            }
            
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="${callback}(${i})">${i}</a></li>`;
            }
            
            if (pagination.has_next) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="${callback}(${currentPage + 1})">Tiếp</a></li>`;
            }
            
            html += '</ul>';
        }
        
        $(`#${containerId}`).html(html);
    }
};

// Common helper functions
window.CMSSHelpers = {
    // Check user permissions
    hasPermission: function(roles) {
        if (!Array.isArray(roles)) {
            roles = [roles];
        }
        return roles.includes(CMMS.userRole) || CMMS.userRole === 'admin';
    },
    
    // Get days remaining
    getDaysRemaining: function(targetDate) {
        if (!targetDate) return null;
        
        const today = new Date();
        const target = new Date(targetDate);
        const diffTime = target - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        return diffDays;
    },
    
    // Get status badge HTML
    getStatusBadge: function(status, type = 'default') {
        const statusConfig = {
            equipment: {
                'hoat_dong': { class: 'bg-success', text: 'Hoạt động' },
                'bao_tri': { class: 'bg-warning', text: 'Bảo trì' },
                'hong': { class: 'bg-danger', text: 'Hỏng' },
                'ngung_hoat_dong': { class: 'bg-secondary', text: 'Ngừng hoạt động' }
            },
            maintenance: {
                'chua_thuc_hien': { class: 'bg-secondary', text: 'Chưa thực hiện' },
                'dang_thuc_hien': { class: 'bg-info', text: 'Đang thực hiện' },
                'hoan_thanh': { class: 'bg-success', text: 'Hoàn thành' },
                'qua_han': { class: 'bg-danger', text: 'Quá hạn' }
            },
            task: {
                'moi_tao': { class: 'bg-secondary', text: 'Mới tạo' },
                'da_giao': { class: 'bg-warning', text: 'Đã giao' },
                'dang_thuc_hien': { class: 'bg-primary', text: 'Đang thực hiện' },
                'cho_xac_nhan': { class: 'bg-info', text: 'Chờ xác nhận' },
                'hoan_thanh': { class: 'bg-success', text: 'Hoàn thành' },
                'huy': { class: 'bg-danger', text: 'Hủy' }
            }
        };
        
        const config = statusConfig[type] && statusConfig[type][status] 
            ? statusConfig[type][status]
            : { class: 'bg-secondary', text: 'Không xác định' };
            
        return `<span class="badge ${config.class}">${config.text}</span>`;
    },
    
    // Get priority badge HTML
    getPriorityBadge: function(priority) {
        const priorities = {
            'khan_cap': { class: 'bg-danger', text: 'Khẩn cấp' },
            'cao': { class: 'bg-warning', text: 'Cao' },
            'trung_binh': { class: 'bg-info', text: 'Trung bình' },
            'thap': { class: 'bg-secondary', text: 'Thấp' }
        };
        
        const config = priorities[priority] || { class: 'bg-secondary', text: 'Không xác định' };
        return `<span class="badge ${config.class}">${config.text}</span>`;
    },
    
    // Export data as CSV
    exportAsCSV: function(data, filename, formData = null) {
        if (formData) {
            // Use form submission for server-side export
            formData.append('action', 'export');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api.php';
            
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        } else {
            // Client-side export
            const csvContent = "data:text/csv;charset=utf-8," + data;
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    // Initialize common components
    initializeCommonComponents();
});

function initializeCommonComponents() {
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
                "sProcessing": "Đang xử lý...",
                "sLengthMenu": "Hiển thị _MENU_ mục",
                "sZeroRecords": "Không tìm thấy dữ liệu",
                "sInfo": "Hiển thị _START_ đến _END_ trong tổng số _TOTAL_ mục",
                "sInfoEmpty": "Hiển thị 0 đến 0 trong tổng số 0 mục",
                "sInfoFiltered": "(được lọc từ _MAX_ mục)",
                "sSearch": "Tìm kiếm:",
                "oPaginate": {
                    "sFirst": "Đầu",
                    "sPrevious": "Trước",
                    "sNext": "Tiếp",
                    "sLast": "Cuối"
                }
            },
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
        });
    }
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