<?php
/**
 * Settings Images API
 * modules/equipment/api/settings_images.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';

requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    errorResponse($e->getMessage());
}

function handleGet() {
    global $action;
    
    switch ($action) {
        case 'list':
            getSettingsImages();
            break;
        case 'get':
            getSettingsImage();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePost() {
    global $action;
    
    switch ($action) {
        case 'upload':
            uploadSettingsImage();
            break;
        case 'update':
            updateSettingsImage();
            break;
        case 'delete':
            deleteSettingsImage();
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function getSettingsImages() {
    global $db;
    
    requirePermission('equipment', 'view');
    
    $equipmentId = (int)($_GET['equipment_id'] ?? 0);
    if (!$equipmentId) {
        throw new Exception('Equipment ID is required');
    }
    
    $sql = "
        SELECT si.*, u.full_name as created_by_name
        FROM equipment_settings_images si
        LEFT JOIN users u ON si.created_by = u.id
        WHERE si.equipment_id = ?
        ORDER BY si.category, si.sort_order, si.created_at DESC
    ";
    
    $images = $db->fetchAll($sql, [$equipmentId]);
    
    // Format image URLs
    foreach ($images as &$image) {
        $image['image_url'] = APP_URL . '/' . ltrim($image['image_path'], '/');
        $image['created_at_formatted'] = formatDateTime($image['created_at']);
    }
    
    successResponse($images);
}

function getSettingsImage() {
    global $db;
    
    requirePermission('equipment', 'view');
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('Image ID is required');
    }
    
    $sql = "
        SELECT si.*, u.full_name as created_by_name
        FROM equipment_settings_images si
        LEFT JOIN users u ON si.created_by = u.id
        WHERE si.id = ?
    ";
    
    $image = $db->fetch($sql, [$id]);
    
    if (!$image) {
        throw new Exception('Image not found');
    }
    
    $image['image_url'] = APP_URL . '/' . ltrim($image['image_path'], '/');
    $image['created_at_formatted'] = formatDateTime($image['created_at']);
    
    successResponse($image);
}

function uploadSettingsImage() {
    global $db;
    
    requirePermission('equipment', 'edit');
    
    $equipmentId = (int)($_POST['equipment_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    
    if (!$equipmentId) {
        throw new Exception('Equipment ID is required');
    }
    
    // Verify equipment exists
    $equipment = $db->fetch("SELECT id FROM equipment WHERE id = ?", [$equipmentId]);
    if (!$equipment) {
        throw new Exception('Equipment not found');
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No image uploaded or upload error');
    }
    
    try {
        $db->beginTransaction();
        
        // Upload image
        $imageUpload = uploadFile(
            $_FILES['image'],
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'uploads/equipment/settings/',
            5 * 1024 * 1024 // 5MB
        );
        
        if (!$imageUpload['success']) {
            throw new Exception('Upload failed: ' . $imageUpload['message']);
        }
        
        // Resize image
        if (function_exists('resizeImage')) {
            resizeImage($imageUpload['path'], $imageUpload['path'], 1200, 900, 90);
        }
        
        // Get next sort order
        $sortOrder = $db->fetch(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
             FROM equipment_settings_images 
             WHERE equipment_id = ? AND category = ?", 
            [$equipmentId, $category]
        )['next_order'];
        
        // Insert record
        $sql = "
            INSERT INTO equipment_settings_images 
            (equipment_id, image_path, title, description, category, sort_order, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $db->execute($sql, [
            $equipmentId,
            $imageUpload['relative_path'],
            $title,
            $description,
            $category,
            $sortOrder,
            getCurrentUser()['id']
        ]);
        
        $imageId = $db->lastInsertId();
        
        // Log activity
        logActivity('create', 'equipment_settings_image', 
                   "Upload settings image: {$title} for equipment ID {$equipmentId}");
        
        $db->commit();
        
        successResponse([
            'id' => $imageId,
            'image_url' => $imageUpload['url'],
            'message' => 'Upload thành công'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        
        // Clean up uploaded file
        if (isset($imageUpload) && $imageUpload['success']) {
            if (file_exists($imageUpload['path'])) {
                unlink($imageUpload['path']);
            }
        }
        
        throw $e;
    }
}

function updateSettingsImage() {
    global $db;
    
    requirePermission('equipment', 'edit');
    
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    
    if (!$id) {
        throw new Exception('Image ID is required');
    }
    
    $sql = "
        UPDATE equipment_settings_images 
        SET title = ?, description = ?, category = ?, updated_at = NOW()
        WHERE id = ?
    ";
    
    $db->execute($sql, [$title, $description, $category, $id]);
    
    logActivity('update', 'equipment_settings_image', "Update settings image ID: {$id}");
    
    successResponse([], 'Cập nhật thành công');
}

function deleteSettingsImage() {
    global $db;
    
    requirePermission('equipment', 'delete');
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('Image ID is required');
    }
    
    // Get image info
    $image = $db->fetch("SELECT * FROM equipment_settings_images WHERE id = ?", [$id]);
    if (!$image) {
        throw new Exception('Image not found');
    }
    
    try {
        $db->beginTransaction();
        
        // Delete file
        $filePath = BASE_PATH . '/' . ltrim($image['image_path'], '/');
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete record
        $db->execute("DELETE FROM equipment_settings_images WHERE id = ?", [$id]);
        
        logActivity('delete', 'equipment_settings_image', 
                   "Delete settings image: {$image['title']} (ID: {$id})");
        
        $db->commit();
        
        successResponse([], 'Xóa thành công');
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>