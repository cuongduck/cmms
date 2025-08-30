<?php
/**
 * BOM Import Handler
 * /modules/bom/imports/bom_import.php
 * Xử lý import BOM từ Excel
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';
require_once '../config.php';

// Check permission
requireLogin();
requirePermission('bom', 'import');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

try {
    handleBOMImport();
} catch (Exception $e) {
    errorResponse($e->getMessage());
}

function handleBOMImport() {
    global $db;
    
    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Không có file được upload hoặc có lỗi xảy ra');
    }
    
    $file = $_FILES['file'];
    $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Chỉ chấp nhận file Excel (.xls, .xlsx)');
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File quá lớn. Tối đa ' . formatFileSize(MAX_FILE_SIZE));
    }
    
    // Parse Excel file
    $bomData = parseExcelFile($file['tmp_name']);
    
    // Validate and process data
    $results = processBOMData($bomData);
    
    successResponse($results, 'Import BOM hoàn tất');
}

/**
 * Parse Excel file using simple CSV-like approach
 * For production, consider using PhpSpreadsheet library
 */
function parseExcelFile($filePath) {
    $bomData = [];
    $currentBOM = null;
    
    // For this example, we'll use a simplified CSV approach
    // In production, use PhpSpreadsheet for proper Excel parsing
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $rowIndex = 0;
        $isItemsSection = false;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowIndex++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // BOM Header detection
            if (count($data) >= 2) {
                $label = trim($data[0]);
                $value = trim($data[1]);
                
                switch ($label) {
                    case 'Mã dòng máy':
                    case 'Machine Type Code':
                        if ($currentBOM) {
                            $bomData[] = $currentBOM;
                        }
                        $currentBOM = [
                            'machine_type_code' => $value,
                            'bom_name' => '',
                            'bom_code' => '',
                            'version' => '1.0',
                            'description' => '',
                            'items' => []
                        ];
                        $isItemsSection = false;
                        break;
                        
                    case 'Tên BOM':
                    case 'BOM Name':
                        if ($currentBOM) {
                            $currentBOM['bom_name'] = $value;
                        }
                        break;
                        
                    case 'Mã BOM':
                    case 'BOM Code':
                        if ($currentBOM) {
                            $currentBOM['bom_code'] = $value;
                        }
                        break;
                        
                    case 'Phiên bản':
                    case 'Version':
                        if ($currentBOM) {
                            $currentBOM['version'] = $value;
                        }
                        break;
                        
                    case 'Mô tả':
                    case 'Description':
                        if ($currentBOM) {
                            $currentBOM['description'] = $value;
                        }
                        break;
                }
            }
            
            // Items section detection
            if ($data[0] === 'STT' || $data[0] === 'No' || 
                (isset($data[1]) && ($data[1] === 'Mã linh kiện' || $data[1] === 'Part Code'))) {
                $isItemsSection = true;
                continue;
            }
            
            // Parse items
            if ($isItemsSection && $currentBOM && count($data) >= 6) {
                if (is_numeric($data[0]) && !empty($data[1])) {
                    $currentBOM['items'][] = [
                        'part_code' => trim($data[1]),
                        'quantity' => floatval($data[3]) ?: 1,
                        'unit' => trim($data[4]) ?: 'Cái',
                        'position' => trim($data[6]) ?: '',
                        'priority' => validatePriority(trim($data[7])) ?: 'Medium',
                        'maintenance_interval' => is_numeric($data[8]) ? intval($data[8]) : null
                    ];
                }
            }
        }
        
        // Add last BOM
        if ($currentBOM) {
            $bomData[] = $currentBOM;
        }
        
        fclose($handle);
    }
    
    return $bomData;
}

/**
 * Validate priority value
 */
function validatePriority($priority) {
    $validPriorities = ['Low', 'Medium', 'High', 'Critical'];
    
    $priority = ucfirst(strtolower(trim($priority)));
    
    // Map Vietnamese to English
    $vietnameseMap = [
        'Thấp' => 'Low',
        'Trung bình' => 'Medium', 
        'Cao' => 'High',
        'Nghiêm trọng' => 'Critical'
    ];
    
    if (isset($vietnameseMap[$priority])) {
        return $vietnameseMap[$priority];
    }
    
    return in_array($priority, $validPriorities) ? $priority : 'Medium';
}

/**
 * Process BOM data and save to database
 */
function processBOMData($bomData) {
    global $db;
    
    if (empty($bomData)) {
        throw new Exception('Không tìm thấy dữ liệu BOM trong file');
    }
    
    $results = [
        'total_boms' => count($bomData),
        'successful' => 0,
        'failed' => 0,
        'errors' => [],
        'warnings' => []
    ];
    
    $db->beginTransaction();
    
    try {
        foreach ($bomData as $index => $bomInfo) {
            try {
                $bomResult = processSingleBOM($bomInfo, $index + 1);
                
                if ($bomResult['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = $bomResult['message'];
                }
                
                if (!empty($bomResult['warnings'])) {
                    $results['warnings'] = array_merge($results['warnings'], $bomResult['warnings']);
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "BOM " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        if ($results['failed'] === 0) {
            $db->commit();
        } else {
            // Nếu có lỗi, rollback và báo cáo
            $db->rollback();
            if ($results['failed'] === $results['total_boms']) {
                throw new Exception('Tất cả BOM đều import thất bại. Dữ liệu không được lưu.');
            }
        }
        
        // Log activity
        logActivity('import_bom', 'bom', "Imported {$results['successful']}/{$results['total_boms']} BOMs");
        
        return $results;
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Process single BOM
 */
function processSingleBOM($bomInfo, $bomIndex) {
    global $db;
    
    $result = [
        'success' => false,
        'message' => '',
        'warnings' => []
    ];
    
    // Validate required fields
    if (empty($bomInfo['machine_type_code'])) {
        $result['message'] = "BOM $bomIndex: Thiếu mã dòng máy";
        return $result;
    }
    
    if (empty($bomInfo['bom_name'])) {
        $result['message'] = "BOM $bomIndex: Thiếu tên BOM";
        return $result;
    }
    
    // Get machine type ID
    $sql = "SELECT id FROM machine_types WHERE code = ? AND status = 'active'";
    $machineType = $db->fetch($sql, [$bomInfo['machine_type_code']]);
    
    if (!$machineType) {
        $result['message'] = "BOM $bomIndex: Không tìm thấy dòng máy với mã {$bomInfo['machine_type_code']}";
        return $result;
    }
    
    // Generate BOM code if empty
    if (empty($bomInfo['bom_code'])) {
        $bomInfo['bom_code'] = generateBOMCode($machineType['id']);
    }
    
    // Check for duplicate BOM code
    $updateExisting = $_POST['update_existing'] ?? false;
    
    $existingBOM = $db->fetch("SELECT id FROM machine_bom WHERE bom_code = ?", [$bomInfo['bom_code']]);
    
    if ($existingBOM) {
        if (!$updateExisting) {
            $result['message'] = "BOM $bomIndex: Mã BOM {$bomInfo['bom_code']} đã tồn tại";
            return $result;
        } else {
            // Update existing BOM
            return updateExistingBOM($existingBOM['id'], $bomInfo, $machineType['id'], $bomIndex);
        }
    }
    
    // Create new BOM
    $sql = "INSERT INTO machine_bom (machine_type_id, bom_name, bom_code, version, description, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $params = [
        $machineType['id'],
        $bomInfo['bom_name'],
        $bomInfo['bom_code'],
        $bomInfo['version'],
        $bomInfo['description'],
        getCurrentUser()['id']
    ];
    
    $db->execute($sql, $params);
    $bomId = $db->lastInsertId();
    
    // Process BOM items
    $itemResult = processBOMItems($bomId, $bomInfo['items'], $bomIndex);
    
    $result['success'] = true;
    $result['message'] = "BOM $bomIndex: Tạo thành công ({$bomInfo['bom_code']})";
    $result['warnings'] = $itemResult['warnings'];
    
    return $result;
}

/**
 * Update existing BOM
 */
function updateExistingBOM($bomId, $bomInfo, $machineTypeId, $bomIndex) {
    global $db;
    
    $result = [
        'success' => false,
        'message' => '',
        'warnings' => []
    ];
    
    // Update BOM info
    $sql = "UPDATE machine_bom 
            SET machine_type_id = ?, bom_name = ?, version = ?, description = ?, updated_at = NOW() 
            WHERE id = ?";
    
    $params = [
        $machineTypeId,
        $bomInfo['bom_name'],
        $bomInfo['version'],
        $bomInfo['description'],
        $bomId
    ];
    
    $db->execute($sql, $params);
    
    // Delete existing items
    $db->execute("DELETE FROM bom_items WHERE bom_id = ?", [$bomId]);
    
    // Add new items
    $itemResult = processBOMItems($bomId, $bomInfo['items'], $bomIndex);
    
    $result['success'] = true;
    $result['message'] = "BOM $bomIndex: Cập nhật thành công ({$bomInfo['bom_code']})";
    $result['warnings'] = $itemResult['warnings'];
    
    return $result;
}

/**
 * Process BOM items
 */
function processBOMItems($bomId, $items, $bomIndex) {
    global $db;
    
    $result = [
        'added' => 0,
        'skipped' => 0,
        'warnings' => []
    ];
    
    if (empty($items)) {
        $result['warnings'][] = "BOM $bomIndex: Không có linh kiện nào được import";
        return $result;
    }
    
    foreach ($items as $itemIndex => $item) {
        try {
            if (empty($item['part_code'])) {
                $result['skipped']++;
                continue;
            }
            
            // Find part by code
            $sql = "SELECT id FROM parts WHERE part_code = ?";
            $part = $db->fetch($sql, [$item['part_code']]);
            
            if (!$part) {
                $result['warnings'][] = "BOM $bomIndex, item " . ($itemIndex + 1) . ": Không tìm thấy linh kiện {$item['part_code']}";
                $result['skipped']++;
                continue;
            }
            
            // Insert BOM item
            $sql = "INSERT INTO bom_items (bom_id, part_id, quantity, unit, position, priority, maintenance_interval) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $bomId,
                $part['id'],
                $item['quantity'],
                $item['unit'],
                $item['position'],
                $item['priority'],
                $item['maintenance_interval']
            ];
            
            $db->execute($sql, $params);
            $result['added']++;
            
        } catch (Exception $e) {
            $result['warnings'][] = "BOM $bomIndex, item " . ($itemIndex + 1) . ": " . $e->getMessage();
            $result['skipped']++;
        }
    }
    
    return $result;
}
?>