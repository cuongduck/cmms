<?php
/**
 * Parts Import Handler
 * /modules/bom/imports/parts_import.php
 * Xử lý import danh sách linh kiện từ Excel
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
    handlePartsImport();
} catch (Exception $e) {
    errorResponse($e->getMessage());
}

function handlePartsImport() {
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
    $partsData = parsePartsExcelFile($file['tmp_name']);
    
    // Validate and process data
    $results = processPartsData($partsData);
    
    successResponse($results, 'Import linh kiện hoàn tất');
}

/**
 * Parse Excel file for parts data
 */
function parsePartsExcelFile($filePath) {
    $partsData = [];
    $headerRow = null;
    $headerMapping = [];
    
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        $rowIndex = 0;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowIndex++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Detect header row
            if (!$headerRow) {
                $headerRow = $data;
                $headerMapping = mapHeaders($headerRow);
                continue;
            }
            
            // Parse data row
            if (count($data) >= count($headerMapping)) {
                $partData = [];
                
                foreach ($headerMapping as $index => $field) {
                    $value = isset($data[$index]) ? trim($data[$index]) : '';
                    $partData[$field] = $value;
                }
                
                // Skip rows without part code
                if (!empty($partData['part_code'])) {
                    $partsData[] = $partData;
                }
            }
        }
        
        fclose($handle);
    }
    
    return $partsData;
}

/**
 * Map Excel headers to database fields
 */
function mapHeaders($headerRow) {
    $mapping = [];
    
    $fieldMap = [
        // Vietnamese headers
        'Mã linh kiện' => 'part_code',
        'Tên linh kiện' => 'part_name', 
        'Mô tả' => 'description',
        'Đơn vị' => 'unit',
        'Danh mục' => 'category',
        'Thông số' => 'specifications',
        'Nhà sản xuất' => 'manufacturer',
        'Mã NCC' => 'supplier_code',
        'Tên NCC' => 'supplier_name',
        'Đơn giá' => 'unit_price',
        'Tồn tối thiểu' => 'min_stock',
        'Tồn tối đa' => 'max_stock',
        'Thời gian giao hàng' => 'lead_time',
        'Ghi chú' => 'notes',
        
        // English headers as fallback
        'Part Code' => 'part_code',
        'Part Name' => 'part_name',
        'Description' => 'description',
        'Unit' => 'unit',
        'Category' => 'category',
        'Specifications' => 'specifications',
        'Manufacturer' => 'manufacturer',
        'Supplier Code' => 'supplier_code',
        'Supplier Name' => 'supplier_name',
        'Unit Price' => 'unit_price',
        'Min Stock' => 'min_stock',
        'Max Stock' => 'max_stock',
        'Lead Time' => 'lead_time',
        'Notes' => 'notes'
    ];
    
    foreach ($headerRow as $index => $header) {
        $header = trim($header);
        if (isset($fieldMap[$header])) {
            $mapping[$index] = $fieldMap[$header];
        }
    }
    
    return $mapping;
}

/**
 * Process parts data
 */
function processPartsData($partsData) {
    global $db;
    
    $updateExisting = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
    $results = [
        'total' => count($partsData),
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'warnings' => []
    ];
    
    $db->beginTransaction();
    
    try {
        foreach ($partsData as $index => $partData) {
            $rowNumber = $index + 2; // Account for header row
            $result = processPartRow($partData, $rowNumber, $updateExisting);
            
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $result['message'];
            }
            
            if (!empty($result['warnings'])) {
                $results['warnings'] = array_merge($results['warnings'], $result['warnings']);
            }
        }
        
        $db->commit();
        
        // Log activity
        logActivity('import_parts', 'bom', "Imported {$results['success']} parts, {$results['failed']} failed");
        
        return $results;
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Process single part row
 */
function processPartRow($partData, $rowNumber, $updateExisting) {
    global $db;
    
    $result = [
        'success' => false,
        'message' => '',
        'warnings' => []
    ];
    
    $cleanData = cleanPartData($partData);
    
    // Validate required fields
    if (empty($cleanData['part_code'])) {
        $result['message'] = "Dòng $rowNumber: Mã linh kiện không được để trống";
        return $result;
    }
    
    if (empty($cleanData['part_name'])) {
        $result['message'] = "Dòng $rowNumber: Tên linh kiện không được để trống";
        return $result;
    }
    
    // Check for existing part
    $existingPart = $db->fetch(
        "SELECT id FROM parts WHERE part_code = ?",
        [$cleanData['part_code']]
    );
    
    try {
        if ($existingPart && $updateExisting) {
            // Update existing part
            $result = updatePart($existingPart['id'], $cleanData, $rowNumber);
        } elseif ($existingPart) {
            $result['message'] = "Dòng $rowNumber: Mã linh kiện {$cleanData['part_code']} đã tồn tại";
        } else {
            // Insert new part
            $result = insertPart($cleanData, $rowNumber);
        }
    } catch (Exception $e) {
        $result['message'] = "Dòng $rowNumber: Lỗi - " . $e->getMessage();
    }
    
    return $result;
}

/**
 * Insert new part
 */
function insertPart($cleanData, $rowNumber) {
    global $db;
    
    $result = [
        'success' => false,
        'message' => '',
        'warnings' => []
    ];
    
    $sql = "INSERT INTO parts 
            (part_code, part_name, description, unit, category, specifications, 
             manufacturer, supplier_code, supplier_name, unit_price, min_stock, 
             max_stock, lead_time, notes, created_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    
    $params = [
        $cleanData['part_code'],
        $cleanData['part_name'],
        $cleanData['description'],
        $cleanData['unit'],
        $cleanData['category'],
        $cleanData['specifications'],
        $cleanData['manufacturer'],
        $cleanData['supplier_code'],
        $cleanData['supplier_name'],
        $cleanData['unit_price'],
        $cleanData['min_stock'],
        $cleanData['max_stock'],
        $cleanData['lead_time'],
        $cleanData['notes'],
        $_SESSION['user_id']
    ];
    
    try {
        $db->execute($sql, $params);
        $partId = $db->lastInsertId();
        
        // Handle supplier
        if (!empty($cleanData['supplier_code']) || !empty($cleanData['supplier_name'])) {
            $existingSupplier = $db->fetch(
                "SELECT id FROM part_suppliers WHERE part_id = ? AND supplier_code = ?",
                [$partId, $cleanData['supplier_code']]
            );
            
            if ($existingSupplier) {
                // Update existing supplier
                $db->execute(
                    "UPDATE part_suppliers SET supplier_name = ?, unit_price = ? WHERE id = ?",
                    [$cleanData['supplier_name'], $cleanData['unit_price'], $existingSupplier['id']]
                );
            } else {
                // Add new supplier
                try {
                    $db->execute(
                        "INSERT INTO part_suppliers (part_id, supplier_code, supplier_name, unit_price, is_preferred) 
                         VALUES (?, ?, ?, ?, 0)",
                        [$partId, $cleanData['supplier_code'], $cleanData['supplier_name'], $cleanData['unit_price']]
                    );
                } catch (Exception $e) {
                    $result['warnings'][] = "Dòng $rowNumber: Không thể thêm nhà cung cấp - " . $e->getMessage();
                }
            }
        }
        
        $result['success'] = true;
        $result['message'] = "Dòng $rowNumber: Thêm mới thành công ({$cleanData['part_code']})";
        
    } catch (Exception $e) {
        $result['message'] = "Dòng $rowNumber: Không thể thêm mới - " . $e->getMessage();
    }
    
    return $result;
}

/**
 * Update existing part
 */
function updatePart($partId, $cleanData, $rowNumber) {
    global $db;
    
    $result = [
        'success' => false,
        'message' => '',
        'warnings' => []
    ];
    
    $sql = "UPDATE parts SET 
            part_name = ?, description = ?, unit = ?, category = ?, specifications = ?, 
            manufacturer = ?, supplier_code = ?, supplier_name = ?, unit_price = ?, 
            min_stock = ?, max_stock = ?, lead_time = ?, notes = ?, updated_at = NOW()
            WHERE id = ?";
    
    $params = [
        $cleanData['part_name'],
        $cleanData['description'],
        $cleanData['unit'],
        $cleanData['category'],
        $cleanData['specifications'],
        $cleanData['manufacturer'],
        $cleanData['supplier_code'],
        $cleanData['supplier_name'],
        $cleanData['unit_price'],
        $cleanData['min_stock'],
        $cleanData['max_stock'],
        $cleanData['lead_time'],
        $cleanData['notes'],
        $partId
    ];
    
    try {
        $db->execute($sql, $params);
        
        // Handle supplier
        if (!empty($cleanData['supplier_code']) || !empty($cleanData['supplier_name'])) {
            $existingSupplier = $db->fetch(
                "SELECT id FROM part_suppliers WHERE part_id = ? AND supplier_code = ?",
                [$partId, $cleanData['supplier_code']]
            );
            
            if ($existingSupplier) {
                // Update existing supplier
                $db->execute(
                    "UPDATE part_suppliers SET supplier_name = ?, unit_price = ? WHERE id = ?",
                    [$cleanData['supplier_name'], $cleanData['unit_price'], $existingSupplier['id']]
                );
            } else {
                // Add new supplier
                try {
                    $db->execute(
                        "INSERT INTO part_suppliers (part_id, supplier_code, supplier_name, unit_price, is_preferred) 
                         VALUES (?, ?, ?, ?, 0)",
                        [$partId, $cleanData['supplier_code'], $cleanData['supplier_name'], $cleanData['unit_price']]
                    );
                } catch (Exception $e) {
                    $result['warnings'][] = "Dòng $rowNumber: Không thể thêm nhà cung cấp - " . $e->getMessage();
                }
            }
        }
        
        $result['success'] = true;
        $result['message'] = "Dòng $rowNumber: Cập nhật thành công ({$cleanData['part_code']})";
        
    } catch (Exception $e) {
        $result['message'] = "Dòng $rowNumber: Không thể cập nhật - " . $e->getMessage();
    }
    
    return $result;
}

/**
 * Clean and validate part data
 */
function cleanPartData($partData) {
    $cleanData = [
        'part_code' => strtoupper(trim($partData['part_code'] ?? '')),
        'part_name' => trim($partData['part_name'] ?? ''),
        'description' => trim($partData['description'] ?? ''),
        'unit' => trim($partData['unit'] ?? '') ?: 'Cái',
        'category' => trim($partData['category'] ?? ''),
        'specifications' => trim($partData['specifications'] ?? ''),
        'manufacturer' => trim($partData['manufacturer'] ?? ''),
        'supplier_code' => trim($partData['supplier_code'] ?? ''),
        'supplier_name' => trim($partData['supplier_name'] ?? ''),
        'unit_price' => parseNumeric($partData['unit_price'] ?? 0),
        'min_stock' => parseNumeric($partData['min_stock'] ?? 0),
        'max_stock' => parseNumeric($partData['max_stock'] ?? 0),
        'lead_time' => intval($partData['lead_time'] ?? 0),
        'notes' => trim($partData['notes'] ?? '')
    ];
    
    // Validate category
    $validCategories = array_keys($GLOBALS['bomConfig']['part_categories']);
    if (!empty($cleanData['category']) && !in_array($cleanData['category'], $validCategories)) {
        // Try to map Vietnamese category names
        $categoryMap = [
            'Cơ khí' => 'Cơ khí',
            'Điện' => 'Điện',
            'Điện tử' => 'Điện tử',
            'Khí nén' => 'Khí nén',
            'Hóa chất' => 'Hóa chất',
            'Cao su' => 'Cao su',
            'Nhựa' => 'Nhựa',
            'Kim loại' => 'Kim loại',
            'Công cụ' => 'Công cụ'
        ];
        
        $cleanData['category'] = $categoryMap[$cleanData['category']] ?? $cleanData['category'];
    }
    
    // Validate unit
    $validUnits = $GLOBALS['bomConfig']['units'];
    if (!empty($cleanData['unit']) && !in_array($cleanData['unit'], $validUnits)) {
        $cleanData['unit'] = 'Cái'; // Default unit
    }
    
    return $cleanData;
}

/**
 * Parse numeric value from string
 */
function parseNumeric($value) {
    if (is_numeric($value)) {
        return floatval($value);
    }
    
    // Remove common non-numeric characters
    $value = str_replace([',', ' ', '\n', '₫'], '', $value);
    
    return is_numeric($value) ? floatval($value) : 0;
}
?>