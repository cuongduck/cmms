<?php
// app/Controllers/BaseController.php
namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $request;
    protected $helpers = ['form', 'url', 'session'];
    protected $session;
    protected $user_data;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->session = session();
        
        // Lấy thông tin user hiện tại nếu đã đăng nhập
        if ($this->session->get('user_id')) {
            $userModel = new \App\Models\UserModel();
            $this->user_data = $userModel->find($this->session->get('user_id'));
        }
    }

    /**
     * Kiểm tra quyền truy cập
     */
    protected function checkPermission($required_role = null)
    {
        if (!$this->user_data) {
            return false;
        }

        if ($required_role) {
            $role_hierarchy = [
                'admin' => 4,
                'truong_ca' => 3,
                'to_truong' => 2,
                'nhan_vien' => 1
            ];

            $user_level = $role_hierarchy[$this->user_data['role']] ?? 0;
            $required_level = $role_hierarchy[$required_role] ?? 0;

            return $user_level >= $required_level;
        }

        return true;
    }

    /**
     * Response JSON cho AJAX
     */
    protected function responseJson($data = [], $status = 200)
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON($data);
    }

    /**
     * Upload file helper
     */
    protected function uploadFile($file, $path = 'uploads', $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'])
    {
        if (!$file->isValid()) {
            return ['success' => false, 'message' => 'File không hợp lệ'];
        }

        if (!in_array($file->getExtension(), $allowed_types)) {
            return ['success' => false, 'message' => 'Loại file không được phép'];
        }

        $newName = $file->getRandomName();
        $upload_path = ROOTPATH . 'public/assets/' . $path;

        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0755, true);
        }

        if ($file->move($upload_path, $newName)) {
            return [
                'success' => true,
                'file_name' => $newName,
                'file_path' => 'assets/' . $path . '/' . $newName
            ];
        }

        return ['success' => false, 'message' => 'Lỗi upload file'];
    }
}