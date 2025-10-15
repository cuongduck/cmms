<?php
/**
 * Spare Parts API Handler - FIXED VERSION
 * /modules/spare_parts/api/spare_parts.php
 */

// Tắt display errors, chỉ log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Clean output buffer ngay từ đầu
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/auth.php';
require_once '../../../config/functions.php';
require_once '../config.php';

// Check login
try {
    requireLogin();
} catch (Exception $e) {
    errorResponse('Authentication required', 401);
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList();
            break;
        
        case 'details':
            handleGetDetails();
            break;
        
        case 'save':
            handleSave();
            break;
        
        case 'update':
            handleUpdate();
            break;
        
        case 'delete':
            handleDelete();
            break;
        
        case 'check_stock':
            handleCheckStock();
            break;
        
        case 'get_reorder_list':
            handleGetReorderList();
            break;
        case 'detect_category':
            handleDetectCategory();
            break;   
        case 'reclassify':
            handleReclassify();
            break;
        case 'search_item_mmb':
            handleSearchItemMMB();
            break;
        case 'export_template':
            exportTemplate();
            break;
    
        case 'import_excel':
            handleImportExcel();
            break;
       
        case 'budget_details':
        $budgetDetails = $db->fetchAll("
            SELECT 
                sp.category,
                SUM(sp.estimated_annual_usage * COALESCE(oh.Price, sp.standard_cost)) as annual_budget
            FROM spare_parts sp
            LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
            WHERE sp.estimated_annual_usage > 0
            GROUP BY sp.category
            ORDER BY annual_budget DESC
        ");
        
        echo json_encode([
            'success' => true,
            'data' => $budgetDetails
        ], JSON_UNESCAPED_UNICODE);
        exit;    
        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
    error_log("Spare Parts API Error: " . $e->getMessage());
    errorResponse($e->getMessage());
}
function handleSearchItemMMB() {
    global $db;
    
    $itemCode = trim($_GET['item_code'] ?? '');
    if (empty($itemCode)) {
        successResponse([]);
        return;
    }
    
    $sql = "SELECT ITEM_CODE, ITEM_NAME, UOM, UNIT_PRICE, VENDOR_ID, VENDOR_NAME
            FROM item_mmb
            WHERE ITEM_CODE LIKE ?
            ORDER BY ITEM_CODE
            LIMIT 10";
    
    $items = $db->fetchAll($sql, [$itemCode . '%']);
    
    successResponse($items);
}
function handleList() {
    $filters = [
        'category' => $_GET['category'] ?? '',
        'manager' => $_GET['manager'] ?? '',
        'stock_status' => $_GET['stock_status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'page' => max(1, intval($_GET['page'] ?? 1)),
        'limit' => min(100, max(10, intval($_GET['limit'] ?? 20)))
    ];
    
    $spareParts = getSpareParts($filters);
    
    // Simple pagination (can be enhanced)
    $offset = ($filters['page'] - 1) * $filters['limit'];
    $paginatedParts = array_slice($spareParts, $offset, $filters['limit']);
    
    $pagination = [
        'current_page' => $filters['page'],
        'total_pages' => ceil(count($spareParts) / $filters['limit']),
        'per_page' => $filters['limit'],
        'total_items' => count($spareParts)
    ];
    
    successResponse(['parts' => $paginatedParts, 'pagination' => $pagination]);
}

function handleGetDetails() {
    global $db;
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
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
                   END as stock_status
            FROM spare_parts sp
            LEFT JOIN onhand oh ON sp.item_code = oh.ItemCode
            LEFT JOIN users u1 ON sp.manager_user_id = u1.id
                        WHERE sp.id = ?";
    
    $part = $db->fetch($sql, [$id]);
    
    if (!$part) {
        throw new Exception('Spare part not found');
    }
    
    successResponse($part);
}

function handleSave() {
    requirePermission('spare_parts', 'create');
    global $db;
    
    $data = [
        'item_code' => trim($_POST['item_code']),
        'item_name' => trim($_POST['item_name']),
        'unit' => trim($_POST['unit']),
        'min_stock' => floatval($_POST['min_stock'] ?? 0),
        'max_stock' => floatval($_POST['max_stock'] ?? 0),
        'estimated_annual_usage' => intval($_POST['estimated_annual_usage'] ?? 0),
        'reorder_point' => floatval($_POST['reorder_point'] ?? 0),
        'standard_cost' => floatval($_POST['standard_cost'] ?? 0),
        'manager_user_id' => !empty($_POST['manager_user_id']) ? intval($_POST['manager_user_id']) : null,
        'supplier_code' => trim($_POST['supplier_code'] ?? ''),
        'supplier_name' => trim($_POST['supplier_name'] ?? ''),
        'lead_time_days' => intval($_POST['lead_time_days'] ?? 0),
        'storage_location' => trim($_POST['storage_location'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'specifications' => trim($_POST['specifications'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'is_critical' => isset($_POST['is_critical']) ? 1 : 0,
        'created_by' => $_SESSION['user_id']
    ];
    
    $sql = "INSERT INTO spare_parts 
            (item_code, item_name, unit, min_stock, max_stock, 
             estimated_annual_usage, reorder_point, standard_cost,
             manager_user_id, supplier_code, supplier_name, lead_time_days, 
             storage_location, description, specifications, notes, 
             is_critical, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $data['item_code'], 
        $data['item_name'], 
        $data['unit'],
        $data['min_stock'], 
        $data['max_stock'], 
        $data['estimated_annual_usage'],
        $data['reorder_point'], 
        $data['standard_cost'],
        $data['manager_user_id'], 
        $data['supplier_code'], 
        $data['supplier_name'],
        $data['lead_time_days'], 
        $data['storage_location'],
        $data['description'], 
        $data['specifications'], 
        $data['notes'],
        $data['is_critical'],
        $data['created_by']
    ];
    
    $db->execute($sql, $params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã thêm spare part thành công',
        'data' => ['id' => $db->lastInsertId()]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleUpdate() {
    requirePermission('spare_parts', 'edit');
    global $db;
    
    $id = intval($_POST['id']);
    
    $sql = "UPDATE spare_parts SET 
            item_code = ?, item_name = ?, unit = ?,
            min_stock = ?, max_stock = ?, estimated_annual_usage = ?,
            reorder_point = ?, standard_cost = ?,
            manager_user_id = ?, supplier_code = ?, supplier_name = ?, 
            lead_time_days = ?, storage_location = ?, 
            description = ?, specifications = ?, notes = ?, 
            is_critical = ?, updated_at = NOW()
            WHERE id = ?";
    
    $params = [
        trim($_POST['item_code']),
        trim($_POST['item_name']),
        trim($_POST['unit']),
        floatval($_POST['min_stock'] ?? 0),
        floatval($_POST['max_stock'] ?? 0),
        intval($_POST['estimated_annual_usage'] ?? 0),
        floatval($_POST['reorder_point'] ?? 0),
        floatval($_POST['standard_cost'] ?? 0),
        !empty($_POST['manager_user_id']) ? intval($_POST['manager_user_id']) : null,
        trim($_POST['supplier_code'] ?? ''),
        trim($_POST['supplier_name'] ?? ''),
        intval($_POST['lead_time_days'] ?? 0),
        trim($_POST['storage_location'] ?? ''),
        trim($_POST['description'] ?? ''),
        trim($_POST['specifications'] ?? ''),
        trim($_POST['notes'] ?? ''),
        isset($_POST['is_critical']) ? 1 : 0,
        $id
    ];
    
    $db->execute($sql, $params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Đã cập nhật thành công'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function handleDelete() {
    // Clean output buffer
    if (ob_get_length()) ob_clean();
    
    try {
        requirePermission('spare_parts', 'delete');
        global $db;
        
        $id = intval($_POST['id'] ?? 0);
        
        if (!$id) {
            throw new Exception('ID không hợp lệ');
        }
        
        // KIỂM TRA QUYỀN - FIX: Dùng user_role
        $part = $db->fetch("SELECT manager_user_id, item_code FROM spare_parts WHERE id = ?", [$id]);
        
        if (!$part) {
            throw new Exception('Không tìm thấy spare part');
        }
        
        // Fix: Sử dụng user_role thay vì role
        $userRole = $_SESSION['user_role'] ?? '';
        $userId = $_SESSION['user_id'] ?? 0;
        
        if ($userRole !== 'Admin' && $part['manager_user_id'] != $userId) {
            throw new Exception('Bạn không có quyền xóa spare part này');
        }
        
        // Xóa
        $db->execute("DELETE FROM spare_parts WHERE id = ?", [$id]);
        
        // Log activity
        logActivity('delete_spare_part', 'spare_parts', "Deleted spare part: {$part['item_code']} (ID: {$id})");
        
        // Trả về JSON
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Đã xóa thành công'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("Delete error: " . $e->getMessage());
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

function handleCheckStock() {
    global $db;
    
    $itemCode = trim($_GET['item_code'] ?? '');
    if (!$itemCode) {
        throw new Exception('Item code is required');
    }
    
    $sql = "SELECT ItemCode, Itemname, 
                   COALESCE(Onhand, 0) as Onhand, 
                   UOM, 
                   COALESCE(Price, 0) as Price, 
                   COALESCE(OH_Value, 0) as OH_Value 
            FROM onhand 
            WHERE ItemCode = ?";
    
    $stock = $db->fetch($sql, [$itemCode]);
    
    // FIX: Nếu không tìm thấy, trả về object với giá trị 0 thay vì null
    if (!$stock) {
        $stock = [
            'ItemCode' => $itemCode,
            'Itemname' => null,
            'Onhand' => 0,
            'UOM' => null,
            'Price' => 0,
            'OH_Value' => 0
        ];
    }
    
    successResponse($stock);
}

function handleGetReorderList() {
    $reorderList = getReorderList();
    successResponse($reorderList);
}
function handleDetectCategory() {
    $itemName = trim($_GET['item_name'] ?? '');
    if (empty($itemName)) {
        throw new Exception('Item name is required');
    }
    
    $category = autoDetectCategory($itemName);
    $confidence = calculateCategoryConfidence($itemName, $category);
    
    successResponse([
        'category' => $category,
        'confidence' => $confidence,
        'suggestions' => getSimilarCategories($itemName)
    ]);
}

function calculateCategoryConfidence($itemName, $detectedCategory) {
    global $sparePartsConfig;
    
    $itemName = strtolower(trim($itemName));
    $keywords = $sparePartsConfig['auto_categorization'][$detectedCategory] ?? [];
    
    $maxScore = 0;
    $totalPossibleScore = strlen($itemName);
    
    foreach ($keywords as $keyword) {
        $keyword = strtolower(trim($keyword));
        if (strpos($itemName, $keyword) !== false) {
            $score = strlen($keyword);
            if (strpos($itemName, $keyword) === 0) $score += 5;
            if ($itemName === $keyword) $score += 10;
            $maxScore = max($maxScore, $score);
        }
    }
    
    return min(100, round(($maxScore / max($totalPossibleScore, 1)) * 100));
}

function getSimilarCategories($itemName, $limit = 3) {
    global $sparePartsConfig;
    
    $itemName = strtolower(trim($itemName));
    $categoryScores = [];
    
    foreach ($sparePartsConfig['auto_categorization'] as $category => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($itemName, strtolower($keyword)) !== false) {
                $score += strlen($keyword);
            }
        }
        if ($score > 0) {
            $categoryScores[$category] = $score;
        }
    }
    
    arsort($categoryScores);
    return array_slice(array_keys($categoryScores), 0, $limit);
}
function handleReclassify() {
    requirePermission('spare_parts', 'edit');
    global $db;
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID is required');
    }
    
    // Lấy thông tin part hiện tại
    $part = $db->fetch("SELECT item_name, category FROM spare_parts WHERE id = ?", [$id]);
    if (!$part) {
        throw new Exception('Spare part not found');
    }
    
    // Phân loại lại
    $newCategory = autoDetectCategory($part['item_name']);
    
    // Cập nhật
    $db->execute("UPDATE spare_parts SET category = ?, updated_at = NOW() WHERE id = ?", [$newCategory, $id]);
    
    // Log activity
    logActivity('reclassify_spare_part', 'spare_parts', "Reclassified part ID {$id}: '{$part['category']}' → '{$newCategory}'");
    
    successResponse([
        'old_category' => $part['category'],
        'new_category' => $newCategory,
        'confidence' => calculateCategoryConfidence($part['item_name'], $newCategory)
    ], "Phân loại lại thành công: {$newCategory}");
}

/**
 * Xuất template Excel
 */
function exportTemplate() {
    global $sparePartsConfig;
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="spare_parts_template_' . date('YmdHis') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    
    echo '<Styles>' . "\n";
    echo '<Style ss:ID="header">' . "\n";
    echo '<Font ss:Bold="1" ss:Color="#FFFFFF"/>' . "\n";
    echo '<Interior ss:Color="#4472C4" ss:Pattern="Solid"/>' . "\n";
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
    echo '</Style>' . "\n";
    echo '<Style ss:ID="example">' . "\n";
    echo '<Interior ss:Color="#E7E6E6" ss:Pattern="Solid"/>' . "\n";
    echo '</Style>' . "\n";
    echo '</Styles>' . "\n";
    
    echo '<Worksheet ss:Name="Spare Parts Template">' . "\n";
    echo '<Table>' . "\n";
    
    $widths = [120, 200, 80, 80, 80, 100, 80, 100, 150, 150, 100, 80, 120, 200, 150, 150, 80];
    foreach ($widths as $width) {
        echo '<Column ss:Width="' . $width . '"/>' . "\n";
    }
    
    // Header - THÊM CỘT NGƯỜI QUẢN LÝ
    $headers = [
        'Mã vật tư (*)',
        'Tên vật tư (*)',
        'Đơn vị (*)',
        'Tồn Min',
        'Tồn Max',
        'Dự kiến sử dụng/năm',
        'Điểm đặt hàng',
        'Đơn giá',
        'Mã NCC',
        'Tên NCC',
        'Người quản lý (username)', // THÊM
        'Lead time (ngày)',
        'Vị trí kho',
        'Mô tả',
        'Thông số KT',
        'Ghi chú',
        'Quan trọng (1/0)'
    ];
    
    echo '<Row ss:Height="30">' . "\n";
    foreach ($headers as $header) {
        echo '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
    
    // Example row - THÊM USERNAME
    $example = [
        'VKH0001',
        'Biến tần 5.5KW',
        'Cái',
        '2',
        '10',
        '12',
        '3',
        '15000000',
        'NCC001',
        'Công ty ABC',
        'admin', // USERNAME NGƯỜI QUẢN LÝ
        '15',
        'Kho A-01',
        'Biến tần cho máy trộn',
        '380V, 5.5KW',
        'Kiểm tra định kỳ 6 tháng',
        '1'
    ];
    
    echo '<Row ss:StyleID="example">' . "\n";
    foreach ($example as $value) {
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($value) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
    
    for ($i = 0; $i < 5; $i++) {
        echo '<Row>' . "\n";
        foreach ($headers as $h) {
            echo '<Cell><Data ss:Type="String"></Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>';
    
    exit;
}


/**
 * Import Excel
 */
function handleImportExcel() {
    try {
        requirePermission('spare_parts', 'create');
        global $db;
        
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Vui lòng chọn file Excel');
        }
        
        $file = $_FILES['excel_file']['tmp_name'];
        $updateExisting = isset($_POST['update_existing']) && $_POST['update_existing'] === 'on';
        
        require_once '../../../vendor/phpoffice/autoload.php';
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $errorDetails = [];
        
        $db->execute("START TRANSACTION");
        
        array_shift($rows);
        
        foreach ($rows as $index => $row) {
            try {
                if (empty($row[0])) continue;
                
                $itemCode = trim($row[0]);
                $itemName = trim($row[1]);
                $unit = trim($row[2] ?? 'Cái');
                
                // LẤY USER ID TỪ USERNAME
                $managerUsername = trim($row[10] ?? '');
                $managerId = null;
                if (!empty($managerUsername)) {
                    $manager = $db->fetch("SELECT id FROM users WHERE username = ?", [$managerUsername]);
                    if ($manager) {
                        $managerId = $manager['id'];
                    } else {
                        $errorDetails[] = "Dòng " . ($index + 2) . ": Không tìm thấy user '$managerUsername'";
                    }
                }
                
                if (empty($itemCode) || empty($itemName)) {
                    $errors++;
                    $errorDetails[] = "Dòng " . ($index + 2) . ": Thiếu mã hoặc tên vật tư";
                    continue;
                }
                
                $existing = $db->fetch("SELECT id FROM spare_parts WHERE item_code = ?", [$itemCode]);
                
                if ($existing) {
                    if ($updateExisting) {
                        $db->execute("UPDATE spare_parts SET 
                            item_name = ?, unit = ?,
                            min_stock = ?, max_stock = ?, estimated_annual_usage = ?,
                            reorder_point = ?, standard_cost = ?,
                            supplier_code = ?, supplier_name = ?, 
                            manager_user_id = ?, lead_time_days = ?,
                            storage_location = ?, description = ?, specifications = ?,
                            notes = ?, is_critical = ?, updated_at = NOW()
                            WHERE item_code = ?",
                            [
                                $itemName, $unit,
                                floatval($row[3] ?? 0), floatval($row[4] ?? 0), intval($row[5] ?? 0),
                                floatval($row[6] ?? 0), floatval($row[7] ?? 0),
                                $row[8] ?? null, $row[9] ?? null,
                                $managerId, intval($row[11] ?? 0),
                                $row[12] ?? null, $row[13] ?? null, $row[14] ?? null,
                                $row[15] ?? null, intval($row[16] ?? 0),
                                $itemCode
                            ]
                        );
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $db->execute("INSERT INTO spare_parts 
                        (item_code, item_name, unit, min_stock, max_stock, 
                         estimated_annual_usage, reorder_point, standard_cost,
                         supplier_code, supplier_name, manager_user_id, lead_time_days, storage_location,
                         description, specifications, notes, is_critical, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $itemCode, $itemName, $unit,
                            floatval($row[3] ?? 0), floatval($row[4] ?? 0), intval($row[5] ?? 0),
                            floatval($row[6] ?? 0), floatval($row[7] ?? 0),
                            $row[8] ?? null, $row[9] ?? null,
                            $managerId, intval($row[11] ?? 0),
                            $row[12] ?? null, $row[13] ?? null, $row[14] ?? null,
                            $row[15] ?? null, intval($row[16] ?? 0),
                            $_SESSION['user_id']
                        ]
                    );
                    $inserted++;
                }
            } catch (Exception $e) {
                error_log("Import row error: " . $e->getMessage());
                $errors++;
                $errorDetails[] = "Dòng " . ($index + 2) . ": " . $e->getMessage();
            }
        }
        
        $db->execute("COMMIT");
        
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Import thành công',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'error_details' => array_slice($errorDetails, 0, 5)
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        try {
            $db->execute("ROLLBACK");
        } catch (Exception $rollbackError) {}
        
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}
?>