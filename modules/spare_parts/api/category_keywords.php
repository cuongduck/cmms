<?php
/**
 * Category Keywords API Handler
 * /modules/spare_parts/api/category_keywords.php
 */

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
    errorResponse('Authentication required', 401);
}

$action = $_REQUEST['action'] ?? '';

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
        
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Category Keywords API Error: " . $e->getMessage());
    errorResponse($e->getMessage());
}

function handleSaveAll() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $categories = $input['categories'] ?? [];
    
    if (empty($categories)) {
        throw new Exception('No categories data provided');
    }
    
    $db->beginTransaction();
    
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
        logActivity('update_category_keywords', 'spare_parts', 'Updated category keywords for all categories');
        
        $db->commit();
        
        successResponse([], 'Cập nhật từ khóa thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

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
    
    successResponse($keywordList);
}

function handleAddKeyword() {
    global $db;
    
    $category = trim($_POST['category'] ?? '');
    $keyword = trim($_POST['keyword'] ?? '');
    
    if (empty($category) || empty($keyword)) {
        throw new Exception('Category and keyword are required');
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
    
    logActivity('add_category_keyword', 'spare_parts', "Added keyword '$keyword' to category '$category'");
    
    successResponse(['id' => $db->lastInsertId()], 'Thêm từ khóa thành công');
}

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
    
    logActivity('remove_category_keyword', 'spare_parts', "Removed keyword '$keyword' from category '$category'");
    
    successResponse([], 'Xóa từ khóa thành công');
}

function handleBulkUpdate() {
    global $db;
    
    $updates = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($updates)) {
        throw new Exception('Invalid updates data');
    }
    
    $db->beginTransaction();
    
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
        
        logActivity('bulk_update_category_keywords', 'spare_parts', 'Bulk updated category keywords');
        
        $db->commit();
        
        successResponse([], 'Cập nhật hàng loạt thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>