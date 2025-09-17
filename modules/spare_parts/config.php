<?php
/**
 * Spare Parts Module Configuration
 */

defined('BASE_PATH2') or define('BASE_PATH2', dirname(__DIR__, 2));
require_once BASE_PATH2 . '/config/config.php';
require_once BASE_PATH2. '/config/database.php';
require_once BASE_PATH2 . '/config/auth.php';
require_once BASE_PATH2 . '/config/functions.php';

// Module constants
define('SPARE_PARTS_MODULE_PATH', __DIR__);

// Module configuration
$sparePartsConfig = [
    'module_name' => 'Quản lý Spare Parts',
    'module_code' => 'spare_parts',
    'permissions' => [
        'view' => 'Xem spare parts',
        'create' => 'Tạo spare parts',
        'edit' => 'Sửa spare parts', 
        'delete' => 'Xóa spare parts',
        'export' => 'Xuất danh sách',
        'purchase_request' => 'Tạo đề xuất mua hàng'
    ],
        // Hệ thống phân loại tự động theo từ khóa
    'auto_categorization' => [
        'Biến tần' => [
            'biến tần', 'inverter', 'VFD', 'frequency inverter'
        ],
        'Servo' => [
            'servo', 'driver', 'drive', 'sevo', 'secvo', 'servor', 
            'draive', 'động cơ servo', 'driver servo', 'servo motor'
        ],
        'Vật tư điện' => [
            'băng keo điện', 'relay', 'rơ le', 'rơle', 'role', 'contactor', 
            'khởi động từ', 'attomat', 'aptomat', 'cầu dao', 'mcb', 'mccb', 
            'rơle nhiệt', 'dây điện', 'dây cáp điện', 'tủ điện', 'đèn báo', 
            'nút nhấn', 'cầu chì', 'biến áp', 'transformer', 'cable', 'wire'
        ],
        'PLC' => [
            'module', 'plc', 'hmi', 'controler', 'modu', 'CPU', 
            'bộ điều khiển', 'màn hình HMI', 'controller', 'control module'
        ],
        'Van điện từ' => [
            'van điện khí', 'van dien tu', 'valve', 'van điên từ', 'solenoid', 
            'van điện từ', 'cuộn van', 'cuộn hút van khí', 'van 3/2', 
            'cuộn coil van', 'van điện', 'van khí nén', 'pneumatic valve'
        ],
        'Băng tải' => [
            'băng tải', 'bang tai', 'bang tải', 'dây băng tải', 'con lăn', 
            'roller', 'khung băng tải', 'conveyor', 'belt conveyor'
        ],
        'Dao lược' => [
            'cắt sợi', 'dao cat soi', 'dao căt soi', 'dao cặt sợi', 
            'dao lược máy', 'lưỡi dao', 'cutting blade', 'knife'
        ],
        'Điện trở' => [
            'điện trở', 'cổ góp', 'điên trở', 'biến trở', 'resistor', 
            'resistance', 'rheostat'
        ],
        'Dây belt' => [
            'curoa', 'belt', 'htd', 'dây đai răng', 'cu roa', 'dây đai', 
            'belt răng', 'dây curoa', 'timing belt', 'v-belt'
        ],
        'Bạc đạn' => [
            'bạc trượt', 'bạc đạn', 'skf', 'vòng bi', 'vóng bi', 'UCF', 
            'UCP', 'gối đỡ', 'bạc lót', 'bearing', 'ball bearing'
        ],
        'Ống' => [
            'ống inox', 'ống pvc', 'tê', 'mặt bích', 'van 2 thân', 'rắc co', 
            'ống', 'clamp', 'ống nhựa', 'mansong', 'ống dẫn', 'co nối', 
            'pipe', 'tube', 'fitting'
        ],
        'Xilanh' => [
            'xilanh', 'piston', 'cylinder', 'xi lanh', 'ben hơi', 
            'pneumatic cylinder', 'air cylinder'
        ],
        'Cảm biến' => [
            'loadcell', 'cảm biến', 'sensor', 'encoder', 'đầu dò', 
            'cảm biến nhiệt độ', 'en coder', 'encode', 'cảm biến quang', 
            'cảm biến tiệm cận', 'proximity sensor', 'temperature sensor'
        ],
        'Motor' => [
            'motor', 'mô tơ', 'động cơ', 'moto', 'động cơ điện', 
            'motor giảm tốc', 'động cơ một chiều', 'động cơ xoay chiều', 
            'electric motor', 'gear motor'
        ],
        'Dao thớt' => [
            'dao cắt chữ u', 'dao cắt máy', 'dao cắt rãnh', 'dao cắt băng', 
            'dao thớt máy', 'ngàm dán', 'lưỡi dao', 'dao cắt giấy', 
            'cutting knife', 'blade'
        ],
        'Nhông xích' => [
            'xích', 'nhông', 'bánh răng', 'puly', 'pully', 'khoá xích', 
            'bộ truyền xích', 'nhông sên dĩa', 'chain', 'sprocket', 'gear'
        ],
        'Đồng hồ' => [
            'đồng hồ nhiệt độ', 'đồng hồ đo áp suất', 'đồng hồ lưu lượng', 
            'bộ điều khiển nhiệt độ', 'temperature controller', 'pressure gauge', 
            'flow meter', 'đồng hồ điều khiển', 'áp kế', 'đồng hồ chân không', 
            'vacuum gauge', 'manometer', 'đồng hồ nước', 'đồng hồ khí', 
            'cảm biến lưu lượng', 'gauge', 'meter'
        ]
    ],
    
    'default_category' => 'Vật tư khác',
    'categories' => [
        'Biến tần' => 'Biến tần và VFD',
        'Servo' => 'Servo và Driver',
        'Vật tư điện' => 'Vật tư điện',
        'PLC' => 'PLC và HMI',
        'Van điện từ' => 'Van điện từ',
        'Băng tải' => 'Băng tải và Roller',
        'Dao lược' => 'Dao lược và Lưỡi dao',
        'Điện trở' => 'Điện trở và Biến trở',
        'Dây belt' => 'Dây belt và Curoa',
        'Bạc đạn' => 'Bạc đạn và Vòng bi',
        'Ống' => 'Ống và Phụ kiện ống',
        'Xilanh' => 'Xilanh và Piston',
        'Cảm biến' => 'Cảm biến và Encoder',
        'Motor' => 'Motor và Động cơ',
        'Dao thớt' => 'Dao thớt và Dao cắt',
        'Nhông xích' => 'Nhông xích và Bánh răng',
        'Đồng hồ' => 'Đồng hồ đo và Gauge',
        'Vật tư khác' => 'Vật tư khác'
    ],
    'priorities' => [
        'Low' => ['name' => 'Thấp', 'class' => 'badge-info'],
        'Medium' => ['name' => 'Trung bình', 'class' => 'badge-warning'], 
        'High' => ['name' => 'Cao', 'class' => 'badge-danger'],
        'Critical' => ['name' => 'Nghiêm trọng', 'class' => 'badge-dark']
    ],
    'units' => [
        'Cái', 'Bộ', 'Chiếc', 'Kg', 'g', 'Lít', 'ml', 'm', 'cm', 'mm', 
        'Tấm', 'Cuộn', 'Gói', 'Hộp', 'Thùng', 'Viên', 'Ống', 'Bao', 'Túi'
    ]
];

/**
 * Lấy danh sách spare parts với thông tin tồn kho
 */
function getSpareParts($filters = []) {
    global $db;
    
    $sql = "SELECT sp.*, 
                   COALESCE(oh.Onhand, 0) as current_stock,
                   COALESCE(oh.UOM, sp.unit) as stock_unit,
                   COALESCE(oh.OH_Value, 0) as stock_value,
                   COALESCE(oh.Price, sp.standard_cost) as current_price,
                   u1.full_name as manager_name,
                   u2.full_name as backup_manager_name,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point THEN 'Reorder'
                       WHEN COALESCE(oh.Onhand, 0) < sp.min_stock THEN 'Low'
                       WHEN COALESCE(oh.Onhand, 0) = 0 THEN 'Out of Stock'
                       ELSE 'OK'
                   END as stock_status,
                   CASE 
                       WHEN COALESCE(oh.Onhand, 0) <= sp.reorder_point THEN GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock)
                       ELSE 0
                   END as suggested_order_qty
            FROM spare_parts sp
            LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
            LEFT JOIN users u1 ON sp.manager_user_id = u1.id
            LEFT JOIN users u2 ON sp.backup_manager_user_id = u2.id
            WHERE sp.is_active = 1";
    
    $params = [];
    
    if (!empty($filters['category'])) {
        $sql .= " AND sp.category = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['manager'])) {
        $sql .= " AND (sp.manager_user_id = ? OR sp.backup_manager_user_id = ?)";
        $params = array_merge($params, [$filters['manager'], $filters['manager']]);
    }
    
    if (!empty($filters['stock_status'])) {
        if ($filters['stock_status'] === 'low') {
            $sql .= " HAVING stock_status IN ('Low', 'Out of Stock')";
        } elseif ($filters['stock_status'] === 'reorder') {
            $sql .= " HAVING stock_status = 'Reorder'";
        }
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (sp.item_code LIKE ? OR sp.item_name LIKE ? OR sp.description LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$search, $search, $search]);
    }
    
    $sql .= " ORDER BY 
               CASE stock_status 
                   WHEN 'Out of Stock' THEN 1 
                   WHEN 'Reorder' THEN 2 
                   WHEN 'Low' THEN 3 
                   ELSE 4 
               END, 
               sp.is_critical DESC, sp.item_code";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Lấy danh sách cần đặt hàng
 */
function getReorderList() {
    global $db;
    
    $sql = "SELECT sp.*, 
                   COALESCE(oh.Onhand, 0) as current_stock,
                   COALESCE(oh.OH_Value, 0) as stock_value,
                   u1.full_name as manager_name,
                   GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock) as suggested_qty,
                   (GREATEST(sp.max_stock - COALESCE(oh.Onhand, 0), sp.min_stock) * sp.standard_cost) as estimated_cost
            FROM spare_parts sp
            LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
            LEFT JOIN users u1 ON sp.manager_user_id = u1.id
            WHERE sp.is_active = 1 
            AND COALESCE(oh.Onhand, 0) <= sp.reorder_point
            ORDER BY sp.is_critical DESC, 
                     CASE WHEN COALESCE(oh.Onhand, 0) = 0 THEN 1 ELSE 2 END,
                     sp.item_code";
    
    return $db->fetchAll($sql);
}

/**
 * Tạo mã đề xuất mua hàng
 */
function generatePurchaseRequestCode() {
    global $db;
    
    $prefix = 'PR' . date('Ym');
    
    $sql = "SELECT MAX(CAST(SUBSTRING(request_code, ?) AS UNSIGNED)) as max_seq 
            FROM purchase_requests 
            WHERE request_code LIKE ?";
    
    $prefixLength = strlen($prefix) + 1;
    $pattern = $prefix . '%';
    
    $result = $db->fetch($sql, [$prefixLength, $pattern]);
    $sequence = ($result['max_seq'] ?? 0) + 1;
    
    return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Format trạng thái tồn kho
 */
function getStockStatusClass($status) {
    $classes = [
        'OK' => 'badge-success',
        'Low' => 'badge-warning',
        'Reorder' => 'badge-info', 
        'Out of Stock' => 'badge-danger'
    ];
    
    return $classes[$status] ?? 'badge-secondary';
}

function getStockStatusText($status) {
    $texts = [
        'OK' => 'Đủ hàng',
        'Low' => 'Sắp hết',
        'Reorder' => 'Cần đặt hàng',
        'Out of Stock' => 'Hết hàng'
    ];
    
    return $texts[$status] ?? $status;
}
/**
 * Tự động phân loại danh mục dựa trên tên item
 */
function autoDetectCategory($itemName) {
    global $sparePartsConfig;
    
    $itemName = strtolower(trim($itemName));
    $categories = $sparePartsConfig['auto_categorization'];
    
    // Tính điểm match cho mỗi category
    $categoryScores = [];
    
    foreach ($categories as $category => $keywords) {
        $score = 0;
        
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            
            // Kiểm tra match chính xác
            if (strpos($itemName, $keyword) !== false) {
                // Tính điểm dựa trên độ dài keyword (từ khóa dài hơn = chính xác hơn)
                $keywordLength = strlen($keyword);
                $score += $keywordLength;
                
                // Bonus nếu match từ đầu hoặc cuối
                if (strpos($itemName, $keyword) === 0) {
                    $score += 5; // Bonus cho match từ đầu
                }
                
                // Bonus nếu match toàn bộ từ
                if ($itemName === $keyword) {
                    $score += 10;
                }
            }
        }
        
        if ($score > 0) {
            $categoryScores[$category] = $score;
        }
    }
    
    // Trả về category có điểm cao nhất
    if (!empty($categoryScores)) {
        arsort($categoryScores);
        return array_key_first($categoryScores);
    }
    
    return $sparePartsConfig['default_category'];
}

/**
 * Lấy danh sách từ khóa của một category
 */
function getCategoryKeywords($category) {
    global $sparePartsConfig;
    return $sparePartsConfig['auto_categorization'][$category] ?? [];
}

/**
 * Cập nhật từ khóa cho category
 */
function updateCategoryKeywords($category, $keywords) {
    global $db, $sparePartsConfig;
    
    // Lưu vào database (tạo bảng category_keywords)
    try {
        $db->execute("DELETE FROM category_keywords WHERE category = ?", [$category]);
        
        foreach ($keywords as $keyword) {
            $db->execute("
                INSERT INTO category_keywords (category, keyword, created_at) 
                VALUES (?, ?, NOW())
            ", [$category, trim($keyword)]);
        }
        
        return ['success' => true, 'message' => 'Cập nhật từ khóa thành công'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Load từ khóa từ database (override config)
 */
function loadCategoryKeywordsFromDB() {
    global $db, $sparePartsConfig;
    
    try {
        $keywords = $db->fetchAll("SELECT category, keyword FROM category_keywords ORDER BY category, keyword");
        
        $categoryKeywords = [];
        foreach ($keywords as $row) {
            $categoryKeywords[$row['category']][] = $row['keyword'];
        }
        
        // Merge với config mặc định
        foreach ($categoryKeywords as $category => $keywordList) {
            $sparePartsConfig['auto_categorization'][$category] = $keywordList;
        }
    } catch (Exception $e) {
        // Nếu chưa có bảng hoặc lỗi, sử dụng config mặc định
        error_log("Cannot load category keywords from DB: " . $e->getMessage());
    }
}

// Load keywords từ DB khi khởi tạo
loadCategoryKeywordsFromDB();
?>