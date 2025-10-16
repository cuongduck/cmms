<?php
/**
 * Category Keywords API Handler - FULL VERSION with EDIT & DELETE
 * /modules/spare_parts/api/category_keywords.php
 */

// Clean output buffer
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';
require_once '../config.php';

// Check login and permission
try {
    requireLogin();
    requirePermission('spare_parts', 'edit');
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required'], JSON_UNESCAPED_UNICODE);
    exit;
}

// XỬ LÝ ACTION - Đọc từ cả GET và POST body
$action = '';

// 1. Kiểm tra GET/POST trước
if (isset($_REQUEST['action'])) {
    $action = trim($_REQUEST['action']);
}

// 2. Nếu không có, đọc từ JSON body (cho save_all)
if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action'])) {
        $action = trim($input['action']);
    }
}

// Debug log
error_log("Category Keywords API - Action: '$action', Method: " . $_SERVER['REQUEST_METHOD']);

try {
    switch ($action) {
        case 'save_all':
            handleSaveAll();
            break;
        
        case 'get_keywords':
            handleGetKeywords();
            break;
        
        case 'add_keyword':
            handleAddKeyword();
            break;
        
        case 'remove_keyword':
            handleRemoveKeyword();
            break;
        
        case 'bulk_update':
            handleBulkUpdate();
            break;
        
        case 'add_category':
            handleAddCategory();
            break;
        
        case 'edit_category':
            handleEditCategory();
            break;
        
        case 'delete_category':
            handleDeleteCategory();
            break;
        
        default:
            throw new Exception('Invalid action: ' . ($action ?: '(empty)'));
    }
} catch (Exception $e) {
    error_log("Category Keywords API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lưu tất cả keywords của tất cả categories
 */
function handleSaveAll() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $categories = $input['categories'] ?? [];
    
    if (empty($categories)) {
        throw new Exception('No categories data provided');
    }
    
    $db->execute("START TRANSACTION");
    
    try {
        $currentUser = getCurrentUser();
        
        foreach ($categories as $category => $keywords) {
            // Xóa từ khóa cũ
            $db->execute("DELETE FROM category_keywords WHERE category = ?", [$category]);
            
            // Thêm từ khóa mới
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    $db->execute("
                        INSERT INTO category_keywords (category, keyword, created_by) 
                        VALUES (?, ?, ?)
                    ", [$category, $keyword, $currentUser['id']]);
                }
            }
        }
        
        // Log activity
        if (function_exists('logActivity')) {
            logActivity('update_category_keywords', 'spare_parts', 'Updated category keywords for all categories');
        }
        
        $db->execute("COMMIT");
        
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật từ khóa thành công'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->execute("ROLLBACK");
        throw $e;
    }
    
    exit;
}

/**
 * Lấy danh sách keywords
 */
function handleGetKeywords() {
    global $db;
    
    $category = $_GET['category'] ?? '';
    
    if ($category) {
        $keywords = $db->fetchAll(
            "SELECT keyword FROM category_keywords WHERE category = ? ORDER BY keyword",
            [$category]
        );
        $keywordList = array_column($keywords, 'keyword');
    } else {
        $keywords = $db->fetchAll("SELECT category, keyword FROM category_keywords ORDER BY category, keyword");
        $keywordList = [];
        foreach ($keywords as $row) {
            $keywordList[$row['category']][] = $row['keyword'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $keywordList
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Thêm keyword vào category
 */
function handleAddKeyword() {
    global $db;
    
    $category = trim($_POST['category'] ?? '');
    $keyword = trim($_POST['keyword'] ?? '');
    
    if (empty($category) || empty($keyword)) {
        throw new Exception('Category và keyword không được để trống');
    }
    
    // Check if keyword already exists
    $existing = $db->fetch(
        "SELECT id FROM category_keywords WHERE category = ? AND keyword = ?",
        [$category, $keyword]
    );
    
    if ($existing) {
        throw new Exception('Từ khóa đã tồn tại trong danh mục này');
    }
    
    $db->execute("
        INSERT INTO category_keywords (category, keyword, created_by) 
        VALUES (?, ?, ?)
    ", [$category, $keyword, getCurrentUser()['id']]);
    
    if (function_exists('logActivity')) {
        logActivity('add_category_keyword', 'spare_parts', "Added keyword '$keyword' to category '$category'");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Thêm từ khóa thành công',
        'data' => ['id' => $db->lastInsertId()]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Xóa keyword
 */
function handleRemoveKeyword() {
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $keyword = trim($_POST['keyword'] ?? '');
    
    if ($id) {
        $db->execute("DELETE FROM category_keywords WHERE id = ?", [$id]);
    } elseif ($category && $keyword) {
        $db->execute("DELETE FROM category_keywords WHERE category = ? AND keyword = ?", [$category, $keyword]);
    } else {
        throw new Exception('ID or category+keyword required');
    }
    
    if (function_exists('logActivity')) {
        logActivity('remove_category_keyword', 'spare_parts', "Removed keyword '$keyword' from category '$category'");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Xóa từ khóa thành công'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Bulk update keywords
 */
function handleBulkUpdate() {
    global $db;
    
    $updates = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($updates)) {
        throw new Exception('Invalid updates data');
    }
    
    $db->execute("START TRANSACTION");
    
    try {
        foreach ($updates as $update) {
            $action = $update['action'] ?? '';
            $category = $update['category'] ?? '';
            $keyword = $update['keyword'] ?? '';
            
            switch ($action) {
                case 'add':
                    if (!empty($category) && !empty($keyword)) {
                        $existing = $db->fetch(
                            "SELECT id FROM category_keywords WHERE category = ? AND keyword = ?",
                            [$category, $keyword]
                        );
                        
                        if (!$existing) {
                            $db->execute("
                                INSERT INTO category_keywords (category, keyword, created_by) 
                                VALUES (?, ?, ?)
                            ", [$category, $keyword, getCurrentUser()['id']]);
                        }
                    }
                    break;
                
                case 'remove':
                    if (!empty($category) && !empty($keyword)) {
                        $db->execute(
                            "DELETE FROM category_keywords WHERE category = ? AND keyword = ?",
                            [$category, $keyword]
                        );
                    }
                    break;
            }
        }
        
        if (function_exists('logActivity')) {
            logActivity('bulk_update_category_keywords', 'spare_parts', 'Bulk updated category keywords');
        }
        
        $db->execute("COMMIT");
        
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật hàng loạt thành công'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->execute("ROLLBACK");
        throw $e;
    }
    
    exit;
}

/**
 * Thêm danh mục mới
 */
function handleAddCategory() {
    global $db;
    
    $category = trim($_POST['category'] ?? '');
    $firstKeyword = trim($_POST['first_keyword'] ?? '');
    
    if (empty($category)) {
        throw new Exception('Tên danh mục không được để trống');
    }
    
    // Kiểm tra trùng
    $existing = $db->fetch(
        "SELECT COUNT(*) as count FROM category_keywords WHERE category = ?",
        [$category]
    );
    
    if ($existing['count'] > 0) {
        throw new Exception('Danh mục đã tồn tại');
    }
    
    // Nếu không có keyword, dùng tên danh mục (lowercase, bỏ dấu)
    if (empty($firstKeyword)) {
        $firstKeyword = removeVietnameseTones(strtolower($category));
    }
    
    // Tạo keyword đầu tiên
    $db->execute("
        INSERT INTO category_keywords (category, keyword, created_by) 
        VALUES (?, ?, ?)
    ", [$category, $firstKeyword, getCurrentUser()['id']]);
    
    if (function_exists('logActivity')) {
        logActivity('add_category', 'spare_parts', "Added new category: $category with keyword: $firstKeyword");
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Thêm danh mục '$category' thành công",
        'data' => [
            'category' => $category,
            'keyword' => $firstKeyword
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sửa tên danh mục
 */
function handleEditCategory() {
    global $db;
    
    $oldCategory = trim($_POST['old_category'] ?? '');
    $newCategory = trim($_POST['new_category'] ?? '');
    
    if (empty($oldCategory) || empty($newCategory)) {
        throw new Exception('Tên danh mục cũ và mới không được để trống');
    }
    
    if ($oldCategory === $newCategory) {
        throw new Exception('Tên danh mục mới phải khác tên cũ');
    }
    
    // Kiểm tra tên mới có trùng không
    $existing = $db->fetch(
        "SELECT COUNT(*) as count FROM category_keywords WHERE category = ?",
        [$newCategory]
    );
    
    if ($existing['count'] > 0) {
        throw new Exception('Tên danh mục mới đã tồn tại');
    }
    
    $db->execute("START TRANSACTION");
    
    try {
        // Cập nhật tất cả keywords của category cũ sang category mới
        $updated = $db->execute(
            "UPDATE category_keywords SET category = ? WHERE category = ?",
            [$newCategory, $oldCategory]
        );
        
        if ($updated === false) {
            throw new Exception('Không thể cập nhật danh mục');
        }
        
        if (function_exists('logActivity')) {
            logActivity('edit_category', 'spare_parts', "Renamed category from '$oldCategory' to '$newCategory'");
        }
        
        $db->execute("COMMIT");
        
        echo json_encode([
            'success' => true,
            'message' => "Đổi tên danh mục thành công",
            'data' => [
                'old_category' => $oldCategory,
                'new_category' => $newCategory
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->execute("ROLLBACK");
        throw $e;
    }
    
    exit;
}

/**
 * Xóa danh mục (xóa tất cả keywords của category đó)
 */
function handleDeleteCategory() {
    global $db;
    
    $category = trim($_POST['category'] ?? '');
    
    if (empty($category)) {
        throw new Exception('Tên danh mục không được để trống');
    }
    
    // Đếm số keywords sẽ bị xóa
    $count = $db->fetch(
        "SELECT COUNT(*) as count FROM category_keywords WHERE category = ?",
        [$category]
    );
    
    if ($count['count'] == 0) {
        throw new Exception('Danh mục không tồn tại');
    }
    
    $db->execute(
        "DELETE FROM category_keywords WHERE category = ?",
        [$category]
    );
    
    if (function_exists('logActivity')) {
        logActivity('delete_category', 'spare_parts', "Deleted category '$category' with {$count['count']} keywords");
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Đã xóa danh mục '$category' và {$count['count']} từ khóa",
        'data' => [
            'category' => $category,
            'deleted_count' => $count['count']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    exit;
}

/**
 * Hàm bỏ dấu tiếng Việt
 */
function removeVietnameseTones($str) {
    $vietnamese = array(
        'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
        'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
        'ì', 'í', 'ị', 'ỉ', 'ĩ',
        'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
        'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
        'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
        'đ',
        'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
        'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
        'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
        'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
        'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
        'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ',
        'Đ'
    );
    
    $latin = array(
        'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
        'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
        'i', 'i', 'i', 'i', 'i',
        'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
        'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
        'y', 'y', 'y', 'y', 'y',
        'd',
        'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A',
        'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
        'I', 'I', 'I', 'I', 'I',
        'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O',
        'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U',
        'Y', 'Y', 'Y', 'Y', 'Y',
        'D'
    );
    
    return str_replace($vietnamese, $latin, $str);
}
?>