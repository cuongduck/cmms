<?php
echo $this->extend('layouts/main');
echo $this->section('content');
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="h3 mb-0"><?= $user ? 'Sửa người dùng' : 'Thêm người dùng' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url('users') ?>">Quản lý người dùng</a></li>
                <li class="breadcrumb-item active"><?= $user ? 'Sửa' : 'Thêm' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?= $user ? 'Thông tin người dùng' : 'Thêm người dùng mới' ?>
                </h6>
            </div>
            <div class="card-body">
                <form id="userForm" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= $user['username'] ?? '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= $user['email'] ?? '' ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    Mật khẩu <?= $user ? '' : '<span class="text-danger">*</span>' ?>
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       <?= $user ? '' : 'required' ?>>
                                <?php if ($user): ?>
                                    <div class="form-text">Để trống nếu không muốn thay đổi mật khẩu</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?= $user['full_name'] ?? '' ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?= $user['phone'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Vai trò <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Chọn vai trò</option>
                                    <option value="admin" <?= (($user['role'] ?? '') == 'admin') ? 'selected' : '' ?>>Quản trị viên</option>
                                    <option value="truong_ca" <?= (($user['role'] ?? '') == 'truong_ca') ? 'selected' : '' ?>>Trưởng ca</option>
                                    <option value="to_truong" <?= (($user['role'] ?? '') == 'to_truong') ? 'selected' : '' ?>>Tổ trưởng</option>
                                    <option value="nhan_vien" <?= (($user['role'] ?? '') == 'nhan_vien') ? 'selected' : '' ?>>Nhân viên</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Trạng thái</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?= (($user['status'] ?? 'active') == 'active') ? 'selected' : '' ?>>Hoạt động</option>
                                    <option value="inactive" <?= (($user['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>Khóa</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="avatar" class="form-label">Avatar</label>
                        <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                        <div class="form-text">Chấp nhận file ảnh: JPG, JPEG, PNG</div>
                        
                        <?php if ($user && $user['avatar']): ?>
                            <div class="mt-2">
                                <img src="<?= base_url($user['avatar']) ?>" alt="Avatar" class="img-thumbnail" style="max-width: 100px;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?= $user ? 'Cập nhật' : 'Thêm mới' ?>
                        </button>
                        <a href="<?= base_url('users') ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Hủy
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Hướng dẫn</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Lưu ý:</h6>
                    <ul class="mb-0">
                        <li>Tên đăng nhập phải duy nhất</li>
                        <li>Email phải duy nhất và hợp lệ</li>
                        <li>Mật khẩu tối thiểu 6 ký tự</li>
                        <li>Số điện thoại không bắt buộc</li>
                        <li>Avatar chỉ chấp nhận file ảnh</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <h6><i class="fas fa-shield-alt me-2"></i>Phân quyền:</h6>
                    <ul class="mb-0">
                        <li><strong>Admin:</strong> Toàn quyền hệ thống</li>
                        <li><strong>Trưởng ca:</strong> Quản lý ca làm việc</li>
                        <li><strong>Tổ trưởng:</strong> Quản lý bảo trì</li>
                        <li><strong>Nhân viên:</strong> Thực hiện công việc</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
$(document).ready(function() {
    $('#userForm').submit(function(e) {
        e.preventDefault();
        
        // Validate password confirmation
        const password = $('#password').val();
        const confirmPassword = $('#confirm_password').val();
        
        if (password && password !== confirmPassword) {
            Swal.fire('Lỗi!', 'Mật khẩu xác nhận không khớp', 'error');
            return false;
        }

        const formData = new FormData(this);
        const isEdit = <?= $user ? 'true' : 'false' ?>;
        const url = isEdit ? 
            '<?= base_url('users/update/' . ($user['id'] ?? '')) ?>' : 
            '<?= base_url('users/store') ?>';

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Thành công!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }
                    });
                } else {
                    let errorMessage = response.message;
                    if (response.errors) {
                        errorMessage += '<br><ul>';
                        for (let field in response.errors) {
                            errorMessage += '<li>' + response.errors[field] + '</li>';
                        }
                        errorMessage += '</ul>';
                    }
                    
                    Swal.fire({
                        title: 'Lỗi!',
                        html: errorMessage,
                        icon: 'error'
                    });
                }
            },
            error: function(xhr) {
                Swal.fire('Lỗi!', 'Có lỗi xảy ra khi xử lý yêu cầu', 'error');
                console.error(xhr.responseText);
            }
        });
    });

    // Preview avatar
    $('#avatar').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Remove existing preview
                $('.avatar-preview').remove();
                
                // Add new preview
                const preview = $('<div class="avatar-preview mt-2"><img src="' + e.target.result + '" alt="Preview" class="img-thumbnail" style="max-width: 100px;"></div>');
                $('#avatar').after(preview);
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
<?php echo $this->endSection(); ?>