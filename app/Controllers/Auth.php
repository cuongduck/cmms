<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
        // Nếu đã đăng nhập thì chuyển về dashboard
        if ($this->session->get('user_id')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }

    public function loginProcess()
    {
        $rules = [
            'login' => 'required',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                           ->withInput()
                           ->with('errors', $this->validator->getErrors());
        }

        $login = $this->request->getPost('login');
        $password = $this->request->getPost('password');

        // Tìm user theo username hoặc email
        $user = $this->userModel->getUserByLogin($login);

        if (!$user) {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Tên đăng nhập hoặc email không tồn tại');
        }

        // Kiểm tra password
        if (!$this->userModel->verifyPassword($password, $user['password'])) {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Mật khẩu không đúng');
        }

        // Kiểm tra trạng thái tài khoản
        if ($user['status'] !== 'active') {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Tài khoản đã bị khóa');
        }

        // Lưu thông tin vào session
        $sessionData = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'logged_in' => true
        ];

        $this->session->set($sessionData);

        return redirect()->to('/dashboard')
                        ->with('success', 'Đăng nhập thành công');
    }

    public function logout()
    {
        $this->session->destroy();
        return redirect()->to('/auth/login')
                        ->with('success', 'Đăng xuất thành công');
    }
}