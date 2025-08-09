<?php
// app/Controllers/Api.php
namespace App\Controllers;

class Api extends BaseController
{
    /**
     * Lấy danh sách xưởng
     */
    public function getXuong()
    {
        $db = \Config\Database::connect();
        $xuong = $db->table('xuong')
                   ->where('status', 'active')
                   ->orderBy('ma_xuong')
                   ->get()
                   ->getResultArray();

        return $this->responseJson([
            'success' => true,
            'data' => $xuong
        ]);
    }

    /**
     * Lấy danh sách line theo xưởng
     */
    public function getLineByXuong($id_xuong)
    {
        $db = \Config\Database::connect();
        $lines = $db->table('line_san_xuat')
                   ->where('id_xuong', $id_xuong)
                   ->where('status', 'active')
                   ->orderBy('ma_line')
                   ->get()
                   ->getResultArray();

        return $this->responseJson([
            'success' => true,
            'data' => $lines
        ]);
    }

    /**
     * Lấy danh sách khu vực theo line
     */
    public function getKhuVucByLine($id_line)
    {
        $db = \Config\Database::connect();
        $khu_vuc = $db->table('khu_vuc')
                     ->where('id_line', $id_line)
                     ->orderBy('thu_tu')
                     ->get()
                     ->getResultArray();

        return $this->responseJson([
            'success' => true,
            'data' => $khu_vuc
        ]);
    }

    /**
     * Lấy danh sách dòng máy theo khu vực
     */
    public function getDongMayByKhuVuc($id_khu_vuc)
    {
        $db = \Config\Database::connect();
        $dong_may = $db->table('dong_may')
                      ->where('id_khu_vuc', $id_khu_vuc)
                      ->orderBy('ten_dong_may')
                      ->get()
                      ->getResultArray();

        return $this->responseJson([
            'success' => true,
            'data' => $dong_may
        ]);
    }

    /**
     * Lấy thông tin thiết bị theo QR code
     */
    public function getEquipmentByQR()
    {
        $qr_code = $this->request->getGet('qr');
        
        if (!$qr_code) {
            return $this->responseJson([
                'success' => false,
                'message' => 'Mã QR không hợp lệ'
            ], 400);
        }

        $db = \Config\Database::connect();
        $equipment = $db->table('thiet_bi tb')
                       ->select('tb.*, x.ten_xuong, l.ten_line, kv.ten_khu_vuc, dm.ten_dong_may, u.full_name as nguoi_chu_quan_name')
                       ->join('xuong x', 'x.id = tb.id_xuong', 'left')
                       ->join('line_san_xuat l', 'l.id = tb.id_line', 'left')
                       ->join('khu_vuc kv', 'kv.id = tb.id_khu_vuc', 'left')
                       ->join('dong_may dm', 'dm.id = tb.id_dong_may', 'left')
                       ->join('users u', 'u.id = tb.nguoi_chu_quan', 'left')
                       ->where('tb.id_thiet_bi', $qr_code)
                       ->get()
                       ->getRowArray();

        if (!$equipment) {
            return $this->responseJson([
                'success' => false,
                'message' => 'Không tìm thấy thiết bị'
            ], 404);
        }

        return $this->responseJson([
            'success' => true,
            'data' => $equipment
        ]);
    }

    /**
     * Tìm kiếm vật tư
     */
    public function searchVatTu()
    {
        $search = $this->request->getGet('q');
        $limit = $this->request->getGet('limit') ?? 20;

        $db = \Config\Database::connect();
        $builder = $db->table('vat_tu vt')
                     ->select('vt.*, lvt.ten_loai, tk.so_luong_ton')
                     ->join('loai_vat_tu lvt', 'lvt.id = vt.id_loai_vat_tu', 'left')
                     ->join('ton_kho tk', 'tk.id_vat_tu = vt.id', 'left')
                     ->where('vt.status', 'active');

        if ($search) {
            $builder->groupStart()
                   ->like('vt.ma_vat_tu', $search)
                   ->orLike('vt.ten_vat_tu', $search)
                   ->orLike('vt.hang_sx', $search)
                   ->groupEnd();
        }

        $vat_tu = $builder->limit($limit)
                         ->orderBy('vt.ten_vat_tu')
                         ->get()
                         ->getResultArray();

        return $this->responseJson([
            'success' => true,
            'data' => $vat_tu
        ]);
    }

    /**
     * Lấy thống kê dashboard
     */
    public function getDashboardStats()
    {
        $db = \Config\Database::connect();
        
        $stats = [
            'total_equipment' => $db->table('thiet_bi')->countAll(),
            'equipment_by_status' => $db->table('thiet_bi')
                                      ->select('tinh_trang, COUNT(*) as count')
                                      ->groupBy('tinh_trang')
                                      ->get()
                                      ->getResultArray(),
            'users_by_role' => $db->table('users')
                                 ->select('role, COUNT(*) as count')
                                 ->where('status', 'active')
                                 ->groupBy('role')
                                 ->get()
                                 ->getResultArray(),
            'low_stock_items' => $db->table('ton_kho tk')
                                   ->select('COUNT(*) as count')
                                   ->join('vat_tu vt', 'vt.id = tk.id_vat_tu')
                                   ->where('tk.so_luong_ton <=', 'vt.ton_kho_min', false)
                                   ->get()
                                   ->getRow()
                                   ->count ?? 0
        ];

        return $this->responseJson([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Upload file via AJAX
     */
    public function uploadFile()
    {
        $file = $this->request->getFile('file');
        $path = $this->request->getPost('path') ?? 'uploads';
        $allowed_types = explode(',', $this->request->getPost('allowed_types') ?? 'jpg,jpeg,png,pdf');

        if (!$file || !$file->isValid()) {
            return $this->responseJson([
                'success' => false,
                'message' => 'File không hợp lệ'
            ], 400);
        }

        $result = $this->uploadFile($file, $path, $allowed_types);
        
        return $this->responseJson($result);
    }

    /**
     * Xóa file
     */
    public function deleteFile()
    {
        $file_path = $this->request->getPost('file_path');
        
        if (!$file_path) {
            return $this->responseJson([
                'success' => false,
                'message' => 'Đường dẫn file không hợp lệ'
            ], 400);
        }

        $full_path = ROOTPATH . 'public/' . $file_path;
        
        if (file_exists($full_path)) {
            if (unlink($full_path)) {
                return $this->responseJson([
                    'success' => true,
                    'message' => 'Xóa file thành công'
                ]);
            }
        }

        return $this->responseJson([
            'success' => false,
            'message' => 'Không thể xóa file'
        ], 500);
    }

    /**
     * Kiểm tra trạng thái hệ thống
     */
    public function systemStatus()
    {
        $db = \Config\Database::connect();
        
        try {
            // Test database connection
            $db->query('SELECT 1');
            $db_status = 'connected';
        } catch (\Exception $e) {
            $db_status = 'error: ' . $e->getMessage();
        }

        $status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $db_status,
            'php_version' => PHP_VERSION,
            'codeigniter_version' => \CodeIgniter\CodeIgniter::CI_VERSION,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'disk_free' => round(disk_free_space('.') / 1024 / 1024 / 1024, 2) . ' GB'
        ];

        return $this->responseJson([
            'success' => true,
            'data' => $status
        ]);
    }
}