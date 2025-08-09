<?php

namespace App\Controllers;

use App\Models\UserModel;

class Users extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index()
    {
        // Kiểm tra quyền admin
        if (!$this->checkPermission('admin')) {
            return redirect()->to('/dashboard')->with('error', 'Bạn không có quyền truy cập');
        }

        $data = [
            'title' => 'Quản lý người dùng - CMMS System'
        ];

        return view('users/index', $data);
    }

    public function create()
    {
        if (!$this->checkPermission('admin')) {
            return redirect()->to('/dashboard')->with('error', 'Bạn không có quyền truy cập');
        }

        $data = [
            'title' => 'Thêm người dùng - CMMS System',
            'user' => null
        ];

        return view('users/form', $data);
    }

    public function store()
    {
        if (!$this->checkPermission('admin')) {
            return $this->responseJson(['success' => false, 'message' => 'Bạn không có quyền truy cập'], 403);
        }

        $rules = [
            'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
            'confirm_password' => 'required|matches[password]',
            'full_name' => 'required|min_length[2]|max_length[100]',
            'phone' => 'permit_empty|min_length[10]|max_length[20]',
            'role' => 'required|in_list[admin,truong_ca,to_truong,nhan_vien]'
        ];

        if (!$this->validate($rules)) {
            return $this->responseJson([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $this->validator->getErrors()
            ], 400);
        }

        $data = [
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'full_name' => $this->request->getPost('full_name'),
            'phone' => $this->request->getPost('phone'),
            'role' => $this->request->getPost('role'),
            'status' => $this->request->getPost('status') ?? 'active'
        ];

        // Xử lý upload avatar nếu có
        $avatar = $this->request->getFile('avatar');
        if ($avatar && $avatar->isValid()) {
            $upload_result = $this->uploadFile($avatar, 'avatars', ['jpg', 'jpeg', 'png']);
            if ($upload_result['success']) {
                $data['avatar'] = $upload_result['file_path'];
            }
        }

        if ($this->userModel->save($data)) {
            return $this->responseJson([
                'success' => true,
                'message' => 'Thêm người dùng thành công',
                'redirect' => base_url('users')
            ]);
        }

        return $this->responseJson([
            'success' => false,
            'message' => 'Có lỗi xảy ra khi thêm người dùng'
        ], 500);
    }

    public function edit($id)
    {
        if (!$this->checkPermission('admin')) {
            return redirect()->to('/dashboard')->with('error', 'Bạn không có quyền truy cập');
        }

        $user = $this->userModel->find($id);
        if (!$user) {
            return redirect()->to('/users')->with('error', 'Người dùng không tồn tại');
        }

        $data = [
            'title' => 'Sửa người dùng - CMMS System',
            'user' => $user
        ];

        return view('users/form', $data);
    }

    public function update($id)
    {
        if (!$this->checkPermission('admin')) {
            return $this->responseJson(['success' => false, 'message' => 'Bạn không có quyền truy cập'], 403);
        }

        $user = $this->userModel->find($id);
        if (!$user) {
            return $this->responseJson(['success' => false, 'message' => 'Người dùng không tồn tại'], 404);
        }

        $rules = [
            'username' => "required|min_length[3]|max_length[50]|is_unique[users.username,id,{$id}]",
            'email' => "required|valid_email|is_unique[users.email,id,{$id}]",
            'full_name' => 'required|min_length[2]|max_length[100]',
            'phone' => 'permit_empty|min_length[10]|max_length[20]',
            'role' => 'required|in_list[admin,truong_ca,to_truong,nhan_vien]'
        ];

        // Nếu có nhập password mới thì validate
        if ($this->request->getPost('password')) {
            $rules['password'] = 'min_length[6]';
            $rules['confirm_password'] = 'matches[password]';
        }

        if (!$this->validate($rules)) {
            return $this->responseJson([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $this->validator->getErrors()
            ], 400);
        }

        $data = [
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'full_name' => $this->request->getPost('full_name'),
            'phone' => $this->request->getPost('phone'),
            'role' => $this->request->getPost('role'),
            'status' => $this->request->getPost('status') ?? 'active'
        ];

        // Nếu có password mới thì cập nhật
        if ($this->request->getPost('password')) {
            $data['password'] = $this->request->getPost('password');
        }

        // Xử lý upload avatar nếu có
        $avatar = $this->request->getFile('avatar');
        if ($avatar && $avatar->isValid()) {
            $upload_result = $this->uploadFile($avatar, 'avatars', ['jpg', 'jpeg', 'png']);
            if ($upload_result['success']) {
                $data['avatar'] = $upload_result['file_path'];
                
                // Xóa avatar cũ nếu có
                if ($user['avatar'] && file_exists(ROOTPATH . 'public/' . $user['avatar'])) {
                    unlink(ROOTPATH . 'public/' . $user['avatar']);
                }
            }
        }

        if ($this->userModel->update($id, $data)) {
            return $this->responseJson([
                'success' => true,
                'message' => 'Cập nhật người dùng thành công',
                'redirect' => base_url('users')
            ]);
        }

        return $this->responseJson([
            'success' => false,
            'message' => 'Có lỗi xảy ra khi cập nhật người dùng'
        ], 500);
    }

    public function delete($id)
    {
        if (!$this->checkPermission('admin')) {
            return $this->responseJson(['success' => false, 'message' => 'Bạn không có quyền truy cập'], 403);
        }

        // Không cho phép xóa chính mình
        if ($id == $this->user_data['id']) {
            return $this->responseJson(['success' => false, 'message' => 'Không thể xóa tài khoản của chính mình'], 400);
        }

        $user = $this->userModel->find($id);
        if (!$user) {
            return $this->responseJson(['success' => false, 'message' => 'Người dùng không tồn tại'], 404);
        }

        if ($this->userModel->delete($id)) {
            // Xóa avatar nếu có
            if ($user['avatar'] && file_exists(ROOTPATH . 'public/' . $user['avatar'])) {
                unlink(ROOTPATH . 'public/' . $user['avatar']);
            }

            return $this->responseJson([
                'success' => true,
                'message' => 'Xóa người dùng thành công'
            ]);
        }

        return $this->responseJson([
            'success' => false,
            'message' => 'Có lỗi xảy ra khi xóa người dùng'
        ], 500);
    }

    public function ajax_list()
    {
        if (!$this->checkPermission('admin')) {
            return $this->responseJson(['error' => 'Bạn không có quyền truy cập'], 403);
        }

        $start = $this->request->getPost('start') ?? 0;
        $length = $this->request->getPost('length') ?? 10;
        $search = $this->request->getPost('search')['value'] ?? '';
        $order_column_index = $this->request->getPost('order')[0]['column'] ?? 0;
        $order_dir = $this->request->getPost('order')[0]['dir'] ?? 'asc';
        
        $columns = ['id', 'username', 'full_name', 'email', 'role', 'status', 'created_at'];
        $order_column = $columns[$order_column_index] ?? 'id';

        $result = $this->userModel->getUsersPaginated($start, $length, $search, $order_column, $order_dir);

        // Format dữ liệu cho DataTables
        $data = [];
        foreach ($result['data'] as $user) {
            $status_badge = $user['status'] == 'active' 
                ? '<span class="badge bg-success">Hoạt động</span>' 
                : '<span class="badge bg-danger">Khóa</span>';

            $role_name = $this->userModel->getRoleName($user['role']);

            $actions = '
                <a href="' . base_url('users/edit/' . $user['id']) . '" class="btn btn-sm btn-warning btn-action" title="Sửa">
                    <i class="fas fa-edit"></i>
                </a>
                <button class="btn btn-sm btn-danger btn-action" onclick="confirmDelete(\'' . base_url('users/delete/' . $user['id']) . '\')" title="Xóa">
                    <i class="fas fa-trash"></i>
                </button>
            ';

            $data[] = [
                $user['id'],
                $user['username'],
                $user['full_name'],
                $user['email'],
                $role_name,
                $status_badge,
                date('d/m/Y H:i', strtotime($user['created_at'])),
                $actions
            ];
        }

        return $this->responseJson([
            'draw' => intval($this->request->getPost('draw')),
            'recordsTotal' => $result['total_records'],
            'recordsFiltered' => $result['filtered_records'],
            'data' => $data
        ]);
    }
}