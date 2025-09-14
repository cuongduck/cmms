<?php
/**
 * Equipment Files API - Fixed Version (No duplicate functions)
 * modules/equipment/api/equipment_files.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json; charset=utf-8');

try {
    // Include required files
    require_once '../../../config/config.php';
    require_once '../../../config/database.php';
    require_once '../../../config/auth.php';
    require_once '../../../config/functions.php';

    // Check login
    requireLogin();

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_REQUEST['action'] ?? '';

    // Handle requests
    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        default:
            throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleGet($action) {
    switch ($action) {
        case 'list':
            getEquipmentFilesList();
            break;
        case 'get':
            getSingleFile();
            break;
        default:
            throw new Exception('Invalid GET action: ' . $action);
    }
}

function handlePost($action) {
    switch ($action) {
        case 'upload':
            uploadNewFile();
            break;
        case 'delete':
            deleteExistingFile();
            break;
        case 'update':
            updateFileInfo();
            break;
        default:
            throw new Exception('Invalid POST action: ' . $action);
    }
}

function getEquipmentFilesList() {
    global $db;
    
    // Check permissions
    if (!hasPermission('equipment', 'view')) {
        throw new Exception('Không có quyền xem files');
    }
    
    $equipmentId = (int)($_GET['equipment_id'] ?? 0);
    if (!$equipmentId) {
        throw new Exception('Thiếu Equipment ID');
    }
    
    $sql = "SELECT ef.*, u.full_name as uploaded_by_name
            FROM equipment_files ef
            LEFT JOIN users u ON ef.uploaded_by = u.id
            WHERE ef.equipment_id = ? AND ef.is_active = 1
            ORDER BY ef.file_type, ef.created_at DESC";
    
    $files = $db->fetchAll($sql, [$equipmentId]);
    
    // Format file data
    foreach ($files as &$file) {
        $file['created_at_formatted'] = formatDateTime($file['created_at']);
        $file['file_size_formatted'] = formatFileSize($file['file_size'] ?? 0);
        $file['file_url'] = APP_URL . '/' . ltrim($file['file_path'], '/');
        
        // Check if file exists
        $fullPath = BASE_PATH . '/' . ltrim($file['file_path'], '/');
        $file['file_exists'] = file_exists($fullPath);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $files,
        'count' => count($files)
    ], JSON_UNESCAPED_UNICODE);
}

function getSingleFile() {
    global $db;
    
    if (!hasPermission('equipment', 'view')) {
        throw new Exception('Không có quyền xem file');
    }
    
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('Thiếu File ID');
    }
    
    $sql = "SELECT ef.*, u.full_name as uploaded_by_name
            FROM equipment_files ef
            LEFT JOIN users u ON ef.uploaded_by = u.id
            WHERE ef.id = ?";
    
    $file = $db->fetch($sql, [$id]);
    
    if (!$file) {
        throw new Exception('File không tồn tại');
    }
    
    $file['created_at_formatted'] = formatDateTime($file['created_at']);
    $file['file_size_formatted'] = formatFileSize($file['file_size'] ?? 0);
    $file['file_url'] = APP_URL . '/' . ltrim($file['file_path'], '/');
    
    // Check if file exists
    $fullPath = BASE_PATH . '/' . ltrim($file['file_path'], '/');
    $file['file_exists'] = file_exists($fullPath);
    
    echo json_encode([
        'success' => true,
        'data' => $file
    ], JSON_UNESCAPED_UNICODE);
}

function uploadNewFile() {
    global $db;
    
    if (!hasPermission('equipment', 'edit')) {
        throw new Exception('Không có quyền upload file');
    }
    
    $equipmentId = (int)($_POST['equipment_id'] ?? 0);
    $displayName = trim($_POST['display_name'] ?? '');
    $fileType = $_POST['file_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $isActive = (int)($_POST['is_active'] ?? 1);
    
    // Validation
    if (!$equipmentId || !$displayName || !$fileType) {
        throw new Exception('Thông tin không đầy đủ');
    }
    
    // Check equipment exists
    $equipment = $db->fetch("SELECT id FROM equipment WHERE id = ?", [$equipmentId]);
    if (!$equipment) {
        throw new Exception('Thiết bị không tồn tại');
    }
    
    // Check file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = $_FILES['file']['error'] ?? 'unknown';
        throw new Exception('Lỗi upload file. Error code: ' . $error);
    }
    
    $file = $_FILES['file'];
    
    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File quá lớn. Tối đa 10MB');
    }
    
    // Validate file extension
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'dwg'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Loại file không được hỗ trợ: .' . $fileExtension);
    }
    
    try {
        $db->beginTransaction();
        
        // Create upload directory
        $uploadDir = 'uploads/equipment/files/';
        $fullUploadDir = BASE_PATH . '/' . $uploadDir;
        
        if (!is_dir($fullUploadDir)) {
            if (!mkdir($fullUploadDir, 0755, true)) {
                throw new Exception('Không thể tạo thư mục upload');
            }
        }
        
        // Generate unique filename
        $uniqueId = uniqid() . '_' . time();
        $newFileName = $uniqueId . '.' . $fileExtension;
        $filePath = $uploadDir . $newFileName;
        $fullFilePath = BASE_PATH . '/' . $filePath;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $fullFilePath)) {
            throw new Exception('Không thể lưu file');
        }
        
        // Get file size and MIME type
        $fileSize = filesize($fullFilePath);
        $mimeType = getFileMimeType($fileExtension);
        
        // Insert to database
        $sql = "INSERT INTO equipment_files 
                (equipment_id, file_name, file_path, file_type, file_size, mime_type, 
                 description, version, is_active, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $currentUser = getCurrentUser();
        $userId = $currentUser['id'] ?? 1;
        
        $db->execute($sql, [
            $equipmentId,
            $displayName,
            $filePath,
            $fileType,
            $fileSize,
            $mimeType,
            $description,
            $version,
            $isActive,
            $userId
        ]);
        
        $fileId = $db->lastInsertId();
        
        // Log activity if function exists
        if (function_exists('logActivity')) {
            logActivity('create', 'equipment_file', 
                       "Upload file: {$displayName} for equipment ID {$equipmentId}");
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Upload file thành công',
            'data' => [
                'id' => $fileId,
                'file_url' => APP_URL . '/' . $filePath,
                'file_name' => $displayName,
                'file_size' => formatFileSize($fileSize)
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        
        // Clean up uploaded file
        if (isset($fullFilePath) && file_exists($fullFilePath)) {
            unlink($fullFilePath);
        }
        
        throw $e;
    }
}

function deleteExistingFile() {
    global $db;
    
    if (!hasPermission('equipment', 'delete')) {
        throw new Exception('Không có quyền xóa file');
    }
    
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('Thiếu File ID');
    }
    
    // Get file info
    $file = $db->fetch("SELECT * FROM equipment_files WHERE id = ?", [$id]);
    if (!$file) {
        throw new Exception('File không tồn tại');
    }
    
    try {
        $db->beginTransaction();
        
        // Delete physical file
        $fullPath = BASE_PATH . '/' . ltrim($file['file_path'], '/');
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        
        // Delete from database
        $db->execute("DELETE FROM equipment_files WHERE id = ?", [$id]);
        
        // Log activity if function exists
        if (function_exists('logActivity')) {
            logActivity('delete', 'equipment_file', 
                       "Delete file: {$file['file_name']} (ID: {$id})");
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Xóa file thành công'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function updateFileInfo() {
    global $db;
    
    if (!hasPermission('equipment', 'edit')) {
        throw new Exception('Không có quyền cập nhật file');
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $displayName = trim($_POST['display_name'] ?? '');
    $fileType = $_POST['file_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $isActive = (int)($_POST['is_active'] ?? 1);
    
    if (!$id || !$displayName || !$fileType) {
        throw new Exception('Thông tin không đầy đủ');
    }
    
    // Check if file exists
    $existingFile = $db->fetch("SELECT id FROM equipment_files WHERE id = ?", [$id]);
    if (!$existingFile) {
        throw new Exception('File không tồn tại');
    }
    
    try {
        $db->beginTransaction();
        
        $sql = "UPDATE equipment_files 
                SET file_name = ?, file_type = ?, description = ?, version = ?, 
                    is_active = ?, updated_at = NOW()
                WHERE id = ?";
        
        $db->execute($sql, [
            $displayName,
            $fileType,
            $description,
            $version,
            $isActive,
            $id
        ]);
        
        // Log activity if function exists
        if (function_exists('logActivity')) {
            logActivity('update', 'equipment_file', 
                       "Update file info: {$displayName} (ID: {$id})");
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật thông tin file thành công'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Helper function for MIME type (renamed to avoid conflicts)
 */
function getFileMimeType($extension) {
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'dwg' => 'application/dwg'
    ];
    
    return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
}
?>