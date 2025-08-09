<?php
// app/Controllers/Dashboard.php
namespace App\Controllers;

use App\Models\UserModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $data = [
            'title' => 'Dashboard - CMMS System',
            'total_users' => 0,
            'total_equipment' => 0,
            'pending_maintenance' => 0,
            'low_stock_items' => 0
        ];

        // Lấy thống kê cơ bản
        $userModel = new UserModel();
        $data['total_users'] = $userModel->countAll();

        // TODO: Thêm các thống kê khác khi có model thiết bị
        
        return view('dashboard/index', $data);
    }
}