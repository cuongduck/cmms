<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Require login
try {
    requireLogin();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check permissions
if (!hasPermission('maintenance', 'view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $industryId = $_GET['industry_id'] ?? '';
    
    if (empty($industryId)) {
        throw new Exception('Industry ID is required');
    }
    
    // Validate industry exists
    $industry = $db->fetch("SELECT id FROM industries WHERE id = ? AND status = 'active'", [$industryId]);
    if (!$industry) {
        throw new Exception('Industry not found');
    }
    
    // Get workshops for the industry
    $sql = "SELECT id, name, code, description 
            FROM workshops 
            WHERE industry_id = ? AND status = 'active' 
            ORDER BY name";
    
    $workshops = $db->fetchAll($sql, [$industryId]);
    
    echo json_encode([
        'success' => true,
        'workshops' => $workshops,
        'total' => count($workshops)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>