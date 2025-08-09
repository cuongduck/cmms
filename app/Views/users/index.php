<?php
echo $this->extend('layouts/main');
echo $this->section('content');
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="h3 mb-0">Quản lý người dùng</h1>
        <p class="text-muted">Danh sách tất cả người dùng trong hệ thống</p>
    </div>
    <div class="col-auto">
        <a href="<?= base_url('users/create') ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Thêm người dùng
        </a>
    </div>
</div>

<div class="card shadow">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="usersTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên đăng nhập</th>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php echo $this->endSection(); ?>

<?php echo $this->section('scripts'); ?>
<script>
let table;

$(document).ready(function() {
    // Initialize DataTable
    table = $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= base_url('users/ajax_list') ?>',
            type: 'POST'
        },
        columns: [
            { data: 0 },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5, orderable: false },
            { data: 6 },
            { data: 7, orderable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json'
        },
        responsive: true
    });
});
</script>
<?php echo $this->endSection(); ?>