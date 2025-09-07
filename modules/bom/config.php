<?php

// File: /modules/bom/config.php
// Cấu hình cho module BOM

defined('BASE_PATH2') or define('BASE_PATH2', dirname(__DIR__, 2));
require_once BASE_PATH2 . '/config/config.php';
require_once BASE_PATH2. '/config/database.php';
require_once BASE_PATH2 . '/config/auth.php';
require_once BASE_PATH2 . '/config/functions.php';

// BOM Module constants
define('BOM_MODULE_PATH', __DIR__);
define('BOM_UPLOAD_PATH', BASE_PATH . '/assets/uploads/bom/');
define('BOM_TEMP_PATH', BASE_PATH . '/assets/uploads/temp/');
// BOM Module Configuration
$bomConfig = [
    'module_name' => 'BOM thiết bị',
    'module_code' => 'bom',
    'permissions' => [
        'view' => 'Xem BOM',
        'create' => 'Tạo BOM',
        'edit' => 'Sửa BOM', 
        'delete' => 'Xóa BOM',
        'export' => 'Xuất BOM',
        'import' => 'Import BOM'
    ],
    'part_categories' => [
        'Cơ khí' => 'Linh kiện cơ khí',
        'Điện' => 'Linh kiện điện',
        'Điện tử' => 'Linh kiện điện tử',
        'Khí nén' => 'Thiết bị khí nén',
        'Hóa chất' => 'Hóa chất, dung dịch',
        'Cao su' => 'Linh kiện cao su',
        'Nhựa' => 'Linh kiện nhựa',
        'Kim loại' => 'Vật liệu kim loại',
        'Công cụ' => 'Dụng cụ, công cụ',
        'Khác' => 'Linh kiện khác'
    ],
    'priorities' => [
        'Low' => ['name' => 'Thấp', 'class' => 'badge-info'],
        'Medium' => ['name' => 'Trung bình', 'class' => 'badge-warning'], 
        'High' => ['name' => 'Cao', 'class' => 'badge-danger'],
        'Critical' => ['name' => 'Nghiêm trọng', 'class' => 'badge-dark']
    ],
    'units' => [
        'Cái', 'Bộ', 'Chiếc', 'Kg', 'g', 'Lít', 'ml', 'm', 'cm', 'mm', 
        'Tấm', 'Cuộn', 'Gói', 'Hộp', 'Thùng', 'Viên', 'Ống'
    ],
    'export' => [
        'formats' => ['excel', 'pdf', 'csv'],
        'templates' => [
            'bom_list' => 'Danh sách BOM',
            'bom_detail' => 'Chi tiết BOM',
            'parts_list' => 'Danh sách linh kiện',
            'stock_report' => 'Báo cáo tồn kho'
        ]
    ]
];

/**
 * BOM Helper Functions
 */

/**
 * Lấy danh sách BOM theo machine type
 */
function getBOMList($machineTypeId = null, $filters = []) {
    global $db;
    
    $sql = "SELECT mb.*, mt.name as machine_type_name, mt.code as machine_type_code,
                   u.full_name as created_by_name,
                   COUNT(bi.id) as total_items,
                   SUM(bi.quantity * p.unit_price) as total_cost
            FROM machine_bom mb
            JOIN machine_types mt ON mb.machine_type_id = mt.id  
            LEFT JOIN users u ON mb.created_by = u.id
            LEFT JOIN bom_items bi ON mb.id = bi.bom_id
            LEFT JOIN parts p ON bi.part_id = p.id
            WHERE 1=1";
    
    $params = [];
    
    if ($machineTypeId) {
        $sql .= " AND mb.machine_type_id = ?";
        $params[] = $machineTypeId;
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (mb.bom_name LIKE ? OR mb.bom_code LIKE ? OR mt.name LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    $sql .= " GROUP BY mb.id ORDER BY mb.created_at DESC";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Lấy chi tiết BOM
 */

function getBOMDetails($bomId) {
    global $db;
    
    // Lấy thông tin BOM
    $sql = "SELECT mb.*, mt.name as machine_type_name, mt.code as machine_type_code,
                   u.full_name as created_by_name
            FROM machine_bom mb
            JOIN machine_types mt ON mb.machine_type_id = mt.id
            LEFT JOIN users u ON mb.created_by = u.id  
            WHERE mb.id = ?";
    
    $bom = $db->fetch($sql, [$bomId]);
    if (!$bom) return null;
    
    // Lấy danh sách items trong BOM
    $sql = "SELECT bi.*, p.part_code, p.part_name, p.description as part_description,
                   p.unit_price, p.min_stock, p.supplier_name,
                   (bi.quantity * p.unit_price) as total_cost,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   COALESCE(oh.UOM, bi.unit) as stock_unit,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM bom_items bi
            JOIN parts p ON bi.part_id = p.id
            LEFT JOIN onhand oh ON p.part_code = oh.ItemCode
            WHERE bi.bom_id = ?
            ORDER BY bi.priority DESC, p.part_name";
    
    $bom['items'] = $db->fetchAll($sql, [$bomId]);
    
    return $bom;
}


/**
 * Lấy danh sách linh kiện
 */
function getPartsList($filters = []) {
    global $db;
    
    $sql = "SELECT p.*, 
                   COUNT(bi.id) as usage_count,
                   COALESCE(oh.Onhand, 0) as stock_quantity,
                   COALESCE(oh.UOM, p.unit) as stock_unit,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) < p.min_stock THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status
            FROM parts p
            LEFT JOIN bom_items bi ON p.id = bi.part_id
            LEFT JOIN part_inventory_mapping pim ON p.id = pim.part_id
            LEFT JOIN onhand oh ON pim.item_code = oh.ItemCode
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['category'])) {
        $sql .= " AND p.category = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (p.part_code LIKE ? OR p.part_name LIKE ? OR p.description LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    if (!empty($filters['stock_status'])) {
        if ($filters['stock_status'] === 'low') {
            $sql .= " HAVING stock_status = 'Low'";
        } elseif ($filters['stock_status'] === 'out') {
            $sql .= " HAVING stock_status = 'Out of Stock'";
        }
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.part_code";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Tạo mã BOM tự động
 */
function generateBOMCode($machineTypeId) {
    global $db;
    
    // Lấy thông tin machine type
    $sql = "SELECT code FROM machine_types WHERE id = ?";
    $machineType = $db->fetch($sql, [$machineTypeId]);
    
    if (!$machineType) return null;
    
    $prefix = 'BOM_' . $machineType['code'];
    
    // Tìm số sequence tiếp theo
    $sql = "SELECT MAX(CAST(SUBSTRING(bom_code, ?) AS UNSIGNED)) as max_seq 
            FROM machine_bom 
            WHERE bom_code LIKE ?";
    
    $prefixLength = strlen($prefix) + 2; // +2 for '_V'
    $pattern = $prefix . '_V%';
    
    $result = $db->fetch($sql, [$prefixLength, $pattern]);
    $sequence = ($result['max_seq'] ?? 0) + 1;
    
    return $prefix . '_V' . $sequence;
}

/**
 * Validate BOM data
 */
function validateBOMData($data) {
    $errors = [];
    
    if (empty($data['machine_type_id'])) {
        $errors[] = 'Vui lòng chọn dòng máy';
    }
    
    if (empty($data['bom_name'])) {
        $errors[] = 'Vui lòng nhập tên BOM';
    }
    
    if (empty($data['items']) || !is_array($data['items'])) {
        $errors[] = 'BOM phải có ít nhất 1 linh kiện';
    } else {
        foreach ($data['items'] as $index => $item) {
            if (empty($item['part_id'])) {
                $errors[] = "Dòng " . ($index + 1) . ": Vui lòng chọn linh kiện";
            }
            
            if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                $errors[] = "Dòng " . ($index + 1) . ": Số lượng phải lớn hơn 0";
            }
        }
    }
    
    return $errors;
}

/**
 * Tính tổng chi phí BOM
 */
function calculateBOMCost($bomId) {
    global $db;
    
    $sql = "SELECT SUM(bi.quantity * p.unit_price) as total_cost
            FROM bom_items bi
            JOIN parts p ON bi.part_id = p.id
            WHERE bi.bom_id = ?";
    
    $result = $db->fetch($sql, [$bomId]);
    return $result['total_cost'] ?? 0;
}

/**
 * Format giá tiền VND
 */
function formatVND($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Lấy màu sắc cho trạng thái tồn kho
 */
function getStockStatusClass($status) {
    $classes = [
        'OK' => 'badge-success',
        'Low' => 'badge-warning', 
        'Out of Stock' => 'badge-danger'
    ];
    
    return $classes[$status] ?? 'badge-secondary';
}

/**
 * Format trạng thái tồn kho
 */
function getStockStatusText($status) {
    $texts = [
        'OK' => 'Đủ hàng',
        'Low' => 'Sắp hết', 
        'Out of Stock' => 'Hết hàng'
    ];
    
    return $texts[$status] ?? $status;
}
?>