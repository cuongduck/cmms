<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'username', 'email', 'password', 'full_name', 'phone', 
        'role', 'status', 'avatar'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'password' => 'required|min_length[6]',
        'full_name' => 'required|min_length[2]|max_length[100]',
        'phone' => 'permit_empty|min_length[10]|max_length[20]',
        'role' => 'required|in_list[admin,truong_ca,to_truong,nhan_vien]'
    ];

    protected $validationMessages = [
        'username' => [
            'required' => 'Tên đăng nhập là bắt buộc',
            'min_length' => 'Tên đăng nhập phải có ít nhất 3 ký tự',
            'is_unique' => 'Tên đăng nhập đã tồn tại'
        ],
        'email' => [
            'required' => 'Email là bắt buộc',
            'valid_email' => 'Email không hợp lệ',
            'is_unique' => 'Email đã tồn tại'
        ],
        'password' => [
            'required' => 'Mật khẩu là bắt buộc',
            'min_length' => 'Mật khẩu phải có ít nhất 6 ký tự'
        ],
        'full_name' => [
            'required' => 'Họ tên là bắt buộc',
            'min_length' => 'Họ tên phải có ít nhất 2 ký tự'
        ],
        'role' => [
            'required' => 'Vai trò là bắt buộc',
            'in_list' => 'Vai trò không hợp lệ'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    /**
     * Hash password trước khi lưu
     */
    protected function hashPassword(array $data)
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_DEFAULT);
        }
        return $data;
    }

    /**
     * Verify password
     */
    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Lấy user theo username hoặc email
     */
    public function getUserByLogin($login)
    {
        return $this->where('username', $login)
                   ->orWhere('email', $login)
                   ->where('status', 'active')
                   ->first();
    }

    /**
     * Lấy danh sách user với phân trang
     */
    public function getUsersPaginated($start = 0, $length = 10, $search = '', $order_column = 'id', $order_dir = 'asc')
    {
        $builder = $this->builder();
        
        if (!empty($search)) {
            $builder->groupStart()
                   ->like('username', $search)
                   ->orLike('full_name', $search)
                   ->orLike('email', $search)
                   ->groupEnd();
        }

        $total_records = $builder->countAllResults(false);
        
        $data = $builder->orderBy($order_column, $order_dir)
                       ->limit($length, $start)
                       ->get()
                       ->getResultArray();

        return [
            'data' => $data,
            'total_records' => $total_records,
            'filtered_records' => $total_records
        ];
    }

    /**
     * Lấy tên role tiếng Việt
     */
    public function getRoleName($role)
    {
        $roles = [
            'admin' => 'Quản trị viên',
            'truong_ca' => 'Trưởng ca',
            'to_truong' => 'Tổ trưởng',
            'nhan_vien' => 'Nhân viên'
        ];

        return $roles[$role] ?? 'Không xác định';
    }
}