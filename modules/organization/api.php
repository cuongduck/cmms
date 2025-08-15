<?php
// modules/organization/api.php - API cho quản lý cấu trúc tổ chức
require_once '../../auth/check_auth.php';

// Enable output compression
if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

// Set response headers for better caching
header('Cache-Control: public, max-age=300'); // 5 minutes cache for structure data
header('Vary: Accept-Encoding');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$type = $_POST['type'] ?? $_GET['type'] ?? '';

switch ($action) {
    case 'list':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        listItems($type);
        break;
    case 'get':
        checkApiPermission(['admin', 'to_truong']);
        getItem($type);
        break;
    case 'save':
        checkApiPermission(['admin', 'to_truong']);
        saveItem($type);
        break;
    case 'delete':
        checkApiPermission(['admin', 'to_truong']);
        deleteItem($type);
        break;
    case 'get_khu_vuc_by_xuong':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getKhuVucByXuong();
        break;
    case 'get_structure_tree':
        checkApiPermission(['admin', 'to_truong', 'user', 'viewer']);
        getStructureTree();
        break;
    case 'export_structure':
        checkApiPermission(['admin', 'to_truong']);
        exportStructure();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ'], 400);
}

function listItems($type) {
    global $db;
    
    try {
        $sql = '';
        
        switch ($type) {
            case 'nganh':
                $sql = "SELECT * FROM nganh ORDER BY ten_nganh";
                break;
                
            case 'xuong':
                $sql = "SELECT x.*, n.ten_nganh 
                        FROM xuong x 
                        LEFT JOIN nganh n ON x.id_nganh = n.id 
                        ORDER BY x.ten_xuong";
                break;
                
            case 'khu_vuc':
                $sql = "SELECT kv.*, x.ten_xuong 
                        FROM khu_vuc kv 
                        LEFT JOIN xuong x ON kv.id_xuong = x.id 
                        ORDER BY kv.ten_khu_vuc";
                break;
                
            case 'line':
                $sql = "SELECT pl.*, x.ten_xuong, kv.ten_khu_vuc 
                        FROM production_line pl 
                        LEFT JOIN xuong x ON pl.id_xuong = x.id 
                        LEFT JOIN khu_vuc kv ON pl.id_khu_vuc = kv.id 
                        ORDER BY pl.ten_line";
                break;
                
            case 'dong_may':
                $sql = "SELECT dm.*, pl.ten_line 
                        FROM dong_may dm 
                        LEFT JOIN production_line pl ON dm.id_line = pl.id 
                        ORDER BY dm.ten_dong_may";
                break;
                
            case 'cum_thiet_bi':
                $sql = "SELECT ctb.*, dm.ten_dong_may 
                        FROM cum_thiet_bi ctb 
                        LEFT JOIN dong_may dm ON ctb.id_dong_may = dm.id 
                        ORDER BY ctb.ten_cum";
                break;
                
            default:
                jsonResponse(['success' => false, 'message' => 'Type không hợp lệ'], 400);
                return;
        }
        
        $stmt = $db->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse([
            'success' => true, 
            'data' => $data,
            'debug' => [
                'query_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'record_count' => count($data),
                'type' => $type
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Organization list error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải dữ liệu'], 500);
    }
}

function getItem($type) {
    global $db;
    
    try {
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Thiếu ID'], 400);
            return;
        }
        
        $table = getTableName($type);
        if (!$table) {
            jsonResponse(['success' => false, 'message' => 'Type không hợp lệ'], 400);
            return;
        }
        
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            jsonResponse(['success' => true, 'data' => $data]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy dữ liệu'], 404);
        }
        
    } catch (Exception $e) {
        error_log("Organization get item error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải dữ liệu'], 500);
    }
}

function saveItem($type) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $id = (int)($_POST['id'] ?? 0);
        $isUpdate = $id > 0;
        
        $table = getTableName($type);
        if (!$table) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Type không hợp lệ'], 400);
            return;
        }
        
        // Validate and prepare data based on type
        $data = validateAndPrepareData($type, $_POST);
        if (!$data['success']) {
            $db->rollBack();
            jsonResponse($data);
            return;
        }
        
        $fields = $data['data'];
        
        if ($isUpdate) {
            // Update
            $setClauses = [];
            $values = [];
            
            foreach ($fields as $field => $value) {
                $setClauses[] = "{$field} = ?";
                $values[] = $value;
            }
            $values[] = $id;
            
            $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            
            logActivity($_SESSION['user_id'], 'update_' . $type, "Cập nhật {$type}: ID {$id}");
            
        } else {
            // Insert
            $fieldNames = array_keys($fields);
            $placeholders = array_fill(0, count($fields), '?');
            $values = array_values($fields);
            
            $sql = "INSERT INTO {$table} (" . implode(', ', $fieldNames) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            
            $id = $db->lastInsertId();
            
            logActivity($_SESSION['user_id'], 'create_' . $type, "Tạo mới {$type}: ID {$id}");
        }
        
        $db->commit();
        
        jsonResponse(['success' => true, 'message' => $isUpdate ? 'Cập nhật thành công' : 'Tạo mới thành công']);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Organization save error: " . $e->getMessage());
        
        // Check for duplicate key error
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            jsonResponse(['success' => false, 'message' => 'Mã đã tồn tại trong hệ thống'], 400);
        } else {
            jsonResponse(['success' => false, 'message' => 'Lỗi lưu dữ liệu'], 500);
        }
    }
}

function deleteItem($type) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Thiếu ID'], 400);
            return;
        }
        
        $table = getTableName($type);
        if (!$table) {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Type không hợp lệ'], 400);
            return;
        }
        
        // Check for dependencies before deletion
        $canDelete = checkDependencies($type, $id);
        if (!$canDelete['success']) {
            $db->rollBack();
            jsonResponse($canDelete);
            return;
        }
        
        // Delete the item
        $stmt = $db->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            $db->commit();
            logActivity($_SESSION['user_id'], 'delete_' . $type, "Xóa {$type}: ID {$id}");
            jsonResponse(['success' => true, 'message' => 'Xóa thành công']);
        } else {
            $db->rollBack();
            jsonResponse(['success' => false, 'message' => 'Không tìm thấy dữ liệu để xóa'], 404);
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Organization delete error: " . $e->getMessage());
        
        // Check for foreign key constraint error
        if (strpos($e->getMessage(), 'foreign key constraint') !== false || 
            strpos($e->getMessage(), 'Cannot delete') !== false) {
            jsonResponse(['success' => false, 'message' => 'Không thể xóa vì có dữ liệu liên quan'], 400);
        } else {
            jsonResponse(['success' => false, 'message' => 'Lỗi xóa dữ liệu'], 500);
        }
    }
}

function getKhuVucByXuong() {
    global $db;
    
    try {
        $xuong_id = (int)($_GET['xuong_id'] ?? 0);
        
        if (!$xuong_id) {
            jsonResponse(['success' => false, 'message' => 'Thiếu xuong_id'], 400);
            return;
        }
        
        $stmt = $db->prepare("SELECT id, ma_khu_vuc, ten_khu_vuc, loai_khu_vuc 
                             FROM khu_vuc 
                             WHERE id_xuong = ? 
                             ORDER BY loai_khu_vuc, ten_khu_vuc");
        $stmt->execute([$xuong_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['success' => true, 'data' => $data]);
        
    } catch (Exception $e) {
        error_log("Get khu vuc by xuong error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải danh sách khu vực'], 500);
    }
}

function getStructureTree() {
    global $db;
    
    try {
        // Build hierarchical structure using optimized query
        $sql = "SELECT 
                    n.id as nganh_id, n.ma_nganh, n.ten_nganh,
                    x.id as xuong_id, x.ma_xuong, x.ten_xuong,
                    kv.id as khu_vuc_id, kv.ma_khu_vuc, kv.ten_khu_vuc, kv.loai_khu_vuc,
                    pl.id as line_id, pl.ma_line, pl.ten_line,
                    dm.id as dong_may_id, dm.ma_dong_may, dm.ten_dong_may,
                    ctb.id as cum_id, ctb.ma_cum, ctb.ten_cum
                FROM nganh n
                LEFT JOIN xuong x ON n.id = x.id_nganh
                LEFT JOIN khu_vuc kv ON x.id = kv.id_xuong
                LEFT JOIN production_line pl ON x.id = pl.id_xuong
                LEFT JOIN dong_may dm ON pl.id = dm.id_line
                LEFT JOIN cum_thiet_bi ctb ON dm.id = ctb.id_dong_may
                ORDER BY n.ten_nganh, x.ten_xuong, kv.ten_khu_vuc, pl.ten_line, dm.ten_dong_may, ctb.ten_cum";
        
        $stmt = $db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group data hierarchically
        $tree = [];
        
        foreach ($results as $row) {
            $nganh_id = $row['nganh_id'];
            
            // Initialize nganh if not exists
            if (!isset($tree[$nganh_id])) {
                $tree[$nganh_id] = [
                    'id' => $row['nganh_id'],
                    'ma_nganh' => $row['ma_nganh'],
                    'ten_nganh' => $row['ten_nganh'],
                    'xuong' => []
                ];
            }
            
            if ($row['xuong_id']) {
                $xuong_id = $row['xuong_id'];
                
                // Initialize xuong if not exists
                if (!isset($tree[$nganh_id]['xuong'][$xuong_id])) {
                    $tree[$nganh_id]['xuong'][$xuong_id] = [
                        'id' => $row['xuong_id'],
                        'ma_xuong' => $row['ma_xuong'],
                        'ten_xuong' => $row['ten_xuong'],
                        'khu_vuc' => [],
                        'lines' => []
                    ];
                }
                
                // Add khu_vuc
                if ($row['khu_vuc_id']) {
                    $kv_id = $row['khu_vuc_id'];
                    if (!isset($tree[$nganh_id]['xuong'][$xuong_id]['khu_vuc'][$kv_id])) {
                        $tree[$nganh_id]['xuong'][$xuong_id]['khu_vuc'][$kv_id] = [
                            'id' => $row['khu_vuc_id'],
                            'ma_khu_vuc' => $row['ma_khu_vuc'],
                            'ten_khu_vuc' => $row['ten_khu_vuc'],
                            'loai_khu_vuc' => $row['loai_khu_vuc']
                        ];
                    }
                }
                
                // Add line
                if ($row['line_id']) {
                    $line_id = $row['line_id'];
                    if (!isset($tree[$nganh_id]['xuong'][$xuong_id]['lines'][$line_id])) {
                        $tree[$nganh_id]['xuong'][$xuong_id]['lines'][$line_id] = [
                            'id' => $row['line_id'],
                            'ma_line' => $row['ma_line'],
                            'ten_line' => $row['ten_line'],
                            'dong_may' => []
                        ];
                    }
                    
                    // Add dong_may
                    if ($row['dong_may_id']) {
                        $dm_id = $row['dong_may_id'];
                        if (!isset($tree[$nganh_id]['xuong'][$xuong_id]['lines'][$line_id]['dong_may'][$dm_id])) {
                            $tree[$nganh_id]['xuong'][$xuong_id]['lines'][$line_id]['dong_may'][$dm_id] = [
                                'id' => $row['dong_may_id'],
                                'ma_dong_may' => $row['ma_dong_may'],
                                'ten_dong_may' => $row['ten_dong_may'],
                                'cum_thiet_bi' => []
                            ];
                        }
                        
                        // Add cum_thiet_bi
                        if ($row['cum_id']) {
                            $cum_id = $row['cum_id'];
                            $tree[$nganh_id]['xuong'][$xuong_id]['lines'][$line_id]['dong_may'][$dm_id]['cum_thiet_bi'][$cum_id] = [
                                'id' => $row['cum_id'],
                                'ma_cum' => $row['ma_cum'],
                                'ten_cum' => $row['ten_cum']
                            ];
                        }
                    }
                }
            }
        }
        
        // Convert associative arrays to indexed arrays
        $formatted_tree = [];
        foreach ($tree as $nganh) {
            $formatted_nganh = $nganh;
            $formatted_nganh['xuong'] = array_values($nganh['xuong']);
            
            foreach ($formatted_nganh['xuong'] as &$xuong) {
                $xuong['khu_vuc'] = array_values($xuong['khu_vuc']);
                $xuong['lines'] = array_values($xuong['lines']);
                
                foreach ($xuong['lines'] as &$line) {
                    $line['dong_may'] = array_values($line['dong_may']);
                    
                    foreach ($line['dong_may'] as &$dong_may) {
                        $dong_may['cum_thiet_bi'] = array_values($dong_may['cum_thiet_bi']);
                    }
                }
            }
            
            $formatted_tree[] = $formatted_nganh;
        }
        
        jsonResponse(['success' => true, 'data' => $formatted_tree]);
        
    } catch (Exception $e) {
        error_log("Get structure tree error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Lỗi tải cây cấu trúc'], 500);
    }
}

function exportStructure() {
    global $db;
    
    try {
        // Get complete structure data for export
        $sql = "SELECT 
                    n.ma_nganh as 'Mã ngành', n.ten_nganh as 'Tên ngành',
                    x.ma_xuong as 'Mã xưởng', x.ten_xuong as 'Tên xưởng',
                    kv.ma_khu_vuc as 'Mã khu vực', kv.ten_khu_vuc as 'Tên khu vực',
                    kv.loai_khu_vuc as 'Loại khu vực',
                    pl.ma_line as 'Mã line', pl.ten_line as 'Tên line',
                    dm.ma_dong_may as 'Mã dòng máy', dm.ten_dong_may as 'Tên dòng máy',
                    ctb.ma_cum as 'Mã cụm TB', ctb.ten_cum as 'Tên cụm thiết bị'
                FROM nganh n
                LEFT JOIN xuong x ON n.id = x.id_nganh
                LEFT JOIN khu_vuc kv ON x.id = kv.id_xuong
                LEFT JOIN production_line pl ON x.id = pl.id_xuong
                LEFT JOIN dong_may dm ON pl.id = dm.id_line
                LEFT JOIN cum_thiet_bi ctb ON dm.id = ctb.id_dong_may
                ORDER BY n.ten_nganh, x.ten_xuong, kv.ten_khu_vuc, pl.ten_line, dm.ten_dong_may, ctb.ten_cum";
        
        $stmt = $db->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create CSV
        $filename = 'cau_truc_to_chuc_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Add data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit();
        
    } catch (Exception $e) {
        error_log("Export structure error: " . $e->getMessage());
        header('Content-Type: application/json');
        jsonResponse(['success' => false, 'message' => 'Lỗi xuất dữ liệu'], 500);
    }
}

// Helper functions
function getTableName($type) {
    $tables = [
        'nganh' => 'nganh',
        'xuong' => 'xuong',
        'khu_vuc' => 'khu_vuc',
        'line' => 'production_line',
        'dong_may' => 'dong_may',
        'cum_thiet_bi' => 'cum_thiet_bi'
    ];
    
    return $tables[$type] ?? null;
}

function validateAndPrepareData($type, $postData) {
    $requiredFields = [];
    $data = [];
    
    switch ($type) {
        case 'nganh':
            $requiredFields = ['ma_nganh', 'ten_nganh'];
            $data = [
                'ma_nganh' => cleanInput($postData['ma_nganh']),
                'ten_nganh' => cleanInput($postData['ten_nganh']),
                'mo_ta' => cleanInput($postData['mo_ta'] ?? '')
            ];
            break;
            
        case 'xuong':
            $requiredFields = ['id_nganh', 'ma_xuong', 'ten_xuong'];
            $data = [
                'id_nganh' => (int)$postData['id_nganh'],
                'ma_xuong' => cleanInput($postData['ma_xuong']),
                'ten_xuong' => cleanInput($postData['ten_xuong']),
                'mo_ta' => cleanInput($postData['mo_ta'] ?? '')
            ];
            break;
            
        case 'khu_vuc':
            $requiredFields = ['id_xuong', 'ma_khu_vuc', 'ten_khu_vuc', 'loai_khu_vuc'];
            $data = [
                'id_xuong' => (int)$postData['id_xuong'],
                'ma_khu_vuc' => cleanInput($postData['ma_khu_vuc']),
                'ten_khu_vuc' => cleanInput($postData['ten_khu_vuc']),
                'loai_khu_vuc' => $postData['loai_khu_vuc'],
                'mo_ta' => cleanInput($postData['mo_ta'] ?? '')
            ];
            break;
            
        case 'line':
            $requiredFields = ['id_xuong', 'id_khu_vuc', 'ma_line', 'ten_line'];
            $data = [
                'id_xuong' => (int)$postData['id_xuong'],
                'id_khu_vuc' => (int)$postData['id_khu_vuc'],
                'ma_line' => cleanInput($postData['ma_line']),
                'ten_line' => cleanInput($postData['ten_line']),
                'mo_ta' => cleanInput($postData['mo_ta'] ?? '')
            ];
            break;
            
        case 'dong_may':
            $requiredFields = ['id_line', 'ma_dong_may', 'ten_dong_may'];
            $data = [
                'id_line' => (int)$postData['id_line'],
                'ma_dong_may' => cleanInput($postData['ma_dong_may']),
                'ten_dong_may' => cleanInput($postData['ten_dong_may']),
                'mo_ta' => cleanInput($postData['mo_ta'] ?? '')
            ];
            break;
            
        case 'cum_thiet_bi':
            $requiredFields = ['id_dong_may', 'ma_cum', 'ten_cum'];
            $data = [
                'id_dong_may' => (int)$postData['id_dong_may'],
                'ma_cum' => cleanInput($postData['ma_cum']),
                'ten_cum' => cleanInput($postData['ten_cum']),
                'mo_ta' => cleanInput($postData['mo_ta'] ?? '')
            ];
            break;
            
        default:
            return ['success' => false, 'message' => 'Type không hợp lệ'];
    }
    
    // Validate required fields
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Trường {$field} là bắt buộc"];
        }
    }
    
    // Additional validation for specific types
    if ($type === 'khu_vuc' && !in_array($data['loai_khu_vuc'], ['cong_nghe', 'dong_goi'])) {
        return ['success' => false, 'message' => 'Loại khu vực không hợp lệ'];
    }
    
    return ['success' => true, 'data' => $data];
}

function checkDependencies($type, $id) {
    global $db;
    
    try {
        $dependencies = [];
        
        switch ($type) {
            case 'nganh':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM xuong WHERE id_nganh = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'xưởng';
                }
                break;
                
            case 'xuong':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM khu_vuc WHERE id_xuong = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'khu vực';
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM production_line WHERE id_xuong = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'line sản xuất';
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM thiet_bi WHERE id_xuong = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'thiết bị';
                }
                break;
                
            case 'khu_vuc':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM production_line WHERE id_khu_vuc = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'line sản xuất';
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM thiet_bi WHERE id_khu_vuc = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'thiết bị';
                }
                break;
                
            case 'line':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM dong_may WHERE id_line = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'dòng máy';
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM thiet_bi WHERE id_line = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'thiết bị';
                }
                break;
                
            case 'dong_may':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM cum_thiet_bi WHERE id_dong_may = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'cụm thiết bị';
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM thiet_bi WHERE id_dong_may = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'thiết bị';
                }
                break;
                
            case 'cum_thiet_bi':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM thiet_bi WHERE id_cum_thiet_bi = ?");
                $stmt->execute([$id]);
                if ($stmt->fetch()['count'] > 0) {
                    $dependencies[] = 'thiết bị';
                }
                break;
        }
        
        if (!empty($dependencies)) {
            return [
                'success' => false, 
                'message' => 'Không thể xóa vì còn có ' . implode(', ', $dependencies) . ' liên quan'
            ];
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Check dependencies error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi kiểm tra ràng buộc dữ liệu'];
    }
}

// Flush output buffer
if (ob_get_level()) {
    ob_end_flush();
}
?>