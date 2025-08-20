<?php
/**
 * Common Functions
 * CMMS System Helper Functions
 */

require_once 'database.php';

/**
 * Lấy cấu trúc thiết bị
 */
function getEquipmentStructure($industryId = null, $workshopId = null, $lineId = null, $areaId = null) {
    global $db;
    
    $structure = [];
    
    // Lấy danh sách ngành
    $sql = "SELECT * FROM industries WHERE status = 'active' ORDER BY name";
    $industries = $db->fetchAll($sql);
    
    foreach ($industries as $industry) {
        if ($industryId && $industry['id'] != $industryId) continue;
        
        $structure[$industry['id']] = [
            'info' => $industry,
            'workshops' => []
        ];
        
        // Lấy xưởng theo ngành
        $sql = "SELECT * FROM workshops WHERE industry_id = ? AND status = 'active' ORDER BY name";
        $workshops = $db->fetchAll($sql, [$industry['id']]);
        
        foreach ($workshops as $workshop) {
            if ($workshopId && $workshop['id'] != $workshopId) continue;
            
            $structure[$industry['id']]['workshops'][$workshop['id']] = [
                'info' => $workshop,
                'lines' => []
            ];
            
            // Lấy line theo xưởng
            $sql = "SELECT * FROM production_lines WHERE workshop_id = ? AND status = 'active' ORDER BY name";
            $lines = $db->fetchAll($sql, [$workshop['id']]);
            
            foreach ($lines as $line) {
                if ($lineId && $line['id'] != $lineId) continue;
                
                $structure[$industry['id']]['workshops'][$workshop['id']]['lines'][$line['id']] = [
                    'info' => $line,
                    'areas' => []
                ];
                
                // Lấy khu vực theo line
                $sql = "SELECT * FROM areas WHERE line_id = ? AND status = 'active' ORDER BY name";
                $areas = $db->fetchAll($sql, [$line['id']]);
                
                foreach ($areas as $area) {
                    if ($areaId && $area['id'] != $areaId) continue;
                    
                    $structure[$industry['id']]['workshops'][$workshop['id']]['lines'][$line['id']]['areas'][$area['id']] = [
                        'info' => $area,
                        'machine_types' => []
                    ];
                }
            }
        }
    }
    
    return $structure;
}

/**
 * Lấy danh sách dòng máy theo cấu trúc
 */
function getMachineTypes($industryId = null, $workshopId = null, $lineId = null, $areaId = null) {
    global $db;
    
    $sql = "SELECT mt.*, i.name as industry_name, w.name as workshop_name, 
                   pl.name as line_name, a.name as area_name
            FROM machine_types mt
            JOIN industries i ON mt.industry_id = i.id
            JOIN workshops w ON mt.workshop_id = w.id
            LEFT JOIN production_lines pl ON mt.line_id = pl.id
            LEFT JOIN areas a ON mt.area_id = a.id
            WHERE mt.status = 'active'";
    
    $params = [];
    
    if ($industryId) {
        $sql .= " AND mt.industry_id = ?";
        $params[] = $industryId;
    }
    
    if ($workshopId) {
        $sql .= " AND mt.workshop_id = ?";
        $params[] = $workshopId;
    }
    
    if ($lineId) {
        $sql .= " AND mt.line_id = ?";
        $params[] = $lineId;
    }
    
    if ($areaId) {
        $sql .= " AND mt.area_id = ?";
        $params[] = $areaId;
    }
    
    $sql .= " ORDER BY i.name, w.name, pl.name, a.name, mt.name";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Lấy cụm thiết bị theo dòng máy
 */
function getEquipmentGroups($machineTypeId) {
    global $db;
    
    $sql = "SELECT * FROM equipment_groups 
            WHERE machine_type_id = ? AND status = 'active' 
            ORDER BY name";
    
    return $db->fetchAll($sql, [$machineTypeId]);
}

/**
 * Lấy breadcrumb cho cấu trúc thiết bị
 */
function getStructureBreadcrumb($industryId = null, $workshopId = null, $lineId = null, $areaId = null, $machineTypeId = null, $equipmentGroupId = null) {
    global $db;
    
    $breadcrumb = [];
    
    if ($industryId) {
        $sql = "SELECT name FROM industries WHERE id = ?";
        $industry = $db->fetch($sql, [$industryId]);
        if ($industry) {
            $breadcrumb[] = $industry['name'];
        }
    }
    
    if ($workshopId) {
        $sql = "SELECT name FROM workshops WHERE id = ?";
        $workshop = $db->fetch($sql, [$workshopId]);
        if ($workshop) {
            $breadcrumb[] = $workshop['name'];
        }
    }
    
    if ($lineId) {
        $sql = "SELECT name FROM production_lines WHERE id = ?";
        $line = $db->fetch($sql, [$lineId]);
        if ($line) {
            $breadcrumb[] = $line['name'];
        }
    }
    
    if ($areaId) {
        $sql = "SELECT name FROM areas WHERE id = ?";
        $area = $db->fetch($sql, [$areaId]);
        if ($area) {
            $breadcrumb[] = $area['name'];
        }
    }
    
    if ($machineTypeId) {
        $sql = "SELECT name FROM machine_types WHERE id = ?";
        $machineType = $db->fetch($sql, [$machineTypeId]);
        if ($machineType) {
            $breadcrumb[] = $machineType['name'];
        }
    }
    
    if ($equipmentGroupId) {
        $sql = "SELECT name FROM equipment_groups WHERE id = ?";
        $equipmentGroup = $db->fetch($sql, [$equipmentGroupId]);
        if ($equipmentGroup) {
            $breadcrumb[] = $equipmentGroup['name'];
        }
    }
    
    return implode(' → ', $breadcrumb);
}

/**
 * Validate cấu trúc thiết bị
 */
function validateEquipmentStructure($data) {
    global $db;
    
    $errors = [];
    
    // Kiểm tra ngành
    if (empty($data['industry_id'])) {
        $errors[] = 'Vui lòng chọn ngành';
    } else {
        $sql = "SELECT id FROM industries WHERE id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['industry_id']])) {
            $errors[] = 'Ngành không hợp lệ';
        }
    }
    
    // Kiểm tra xưởng
    if (empty($data['workshop_id'])) {
        $errors[] = 'Vui lòng chọn xưởng';
    } else {
        $sql = "SELECT id FROM workshops WHERE id = ? AND industry_id = ? AND status = 'active'";
        if (!$db->fetch($sql, [$data['workshop_id'], $data['industry_id']])) {
            $errors[] = 'Xưởng không hợp lệ';
        }
    }
    
    // Đối với ngành nêm rau, line và area có thể null
    $sql = "SELECT code FROM industries WHERE id = ?";
    $industry = $db->fetch($sql, [$data['industry_id']]);
    
    if ($industry && $industry['code'] !== 'NEM') {
        // Kiểm tra line (bắt buộc cho mì và phở)
        if (empty($data['line_id'])) {
            $errors[] = 'Vui lòng chọn line sản xuất';
        } else {
            $sql = "SELECT id FROM production_lines WHERE id = ? AND workshop_id = ? AND status = 'active'";
            if (!$db->fetch($sql, [$data['line_id'], $data['workshop_id']])) {
                $errors[] = 'Line sản xuất không hợp lệ';
            }
        }
        
        // Kiểm tra area (bắt buộc cho mì và phở)
        if (empty($data['area_id'])) {
            $errors[] = 'Vui lòng chọn khu vực';
        } else {
            $sql = "SELECT id FROM areas WHERE id = ? AND line_id = ? AND status = 'active'";
            if (!$db->fetch($sql, [$data['area_id'], $data['line_id']])) {
                $errors[] = 'Khu vực không hợp lệ';
            }
        }
    }
    
    return $errors;
}

/**
 * Tạo mã thiết bị tự động
 */
function generateEquipmentCode($industryCode, $workshopCode, $lineCode = '', $areaCode = '', $sequence = null) {
    global $db;
    
    $prefix = $industryCode . $workshopCode;
    
    if ($lineCode) {
        $prefix .= $lineCode;
    }
    
    if ($areaCode) {
        $prefix .= $areaCode;
    }
    
    if ($sequence === null) {
        // Tìm sequence number tiếp theo
        $sql = "SELECT MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) as max_seq 
                FROM equipment 
                WHERE code LIKE ?";
        
        $prefixLength = strlen($prefix) + 1;
        $pattern = $prefix . '%';
        
        $result = $db->fetch($sql, [$prefixLength, $pattern]);
        $sequence = ($result['max_seq'] ?? 0) + 1;
    }
    
    return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
}

/**
 * Upload file
 */
function uploadFile($file, $allowedTypes, $uploadPath, $maxSize = null) {
    if (!$maxSize) {
        $maxSize = getConfig('upload.max_size');
    }
    
    // Kiểm tra lỗi upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Lỗi upload file'];
    }
    
    // Kiểm tra kích thước
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File quá lớn. Tối đa ' . formatFileSize($maxSize)];
    }
    
    // Kiểm tra loại file
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Loại file không được phép'];
    }
    
    // Tạo tên file mới
    $fileName = uniqid() . '.' . $extension;
    $fullPath = $uploadPath . $fileName;
    
    // Tạo thư mục nếu chưa tồn tại
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return [
            'success' => true,
            'filename' => $fileName,
            'path' => $fullPath,
            'url' => str_replace(BASE_PATH, '', $fullPath)
        ];
    } else {
        return ['success' => false, 'message' => 'Không thể upload file'];
    }
}

/**
 * Xóa file
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

/**
 * Format số
 */
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals, ',', '.');
}

/**
 * Lấy trạng thái CSS class
 */
function getStatusClass($status) {
    $classes = [
        'active' => 'badge-success',
        'inactive' => 'badge-secondary',
        'maintenance' => 'badge-warning',
        'broken' => 'badge-danger',
        'Low' => 'badge-info',
        'Medium' => 'badge-warning',
        'High' => 'badge-danger',
        'Critical' => 'badge-dark'
    ];
    
    return $classes[$status] ?? 'badge-secondary';
}

/**
 * Lấy text trạng thái
 */
function getStatusText($status) {
    $texts = [
        'active' => 'Hoạt động',
        'inactive' => 'Không hoạt động', 
        'maintenance' => 'Bảo trì',
        'broken' => 'Hỏng',
        'Low' => 'Thấp',
        'Medium' => 'Trung bình',
        'High' => 'Cao',
        'Critical' => 'Nghiêm trọng'
    ];
    
    return $texts[$status] ?? $status;
}

/**
 * Tạo select options
 */
function buildSelectOptions($items, $valueField = 'id', $textField = 'name', $selectedValue = null) {
    $options = '';
    foreach ($items as $item) {
        $selected = ($item[$valueField] == $selectedValue) ? 'selected' : '';
        $options .= "<option value='{$item[$valueField]}' {$selected}>{$item[$textField]}</option>";
    }
    return $options;
}

/**
 * Pagination
 */
function paginate($totalItems, $currentPage = 1, $perPage = 20, $url = '') {
    $totalPages = ceil($totalItems / $perPage);
    $currentPage = max(1, min($totalPages, $currentPage));
    
    $pagination = [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'per_page' => $perPage,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage - 1,
        'next_page' => $currentPage + 1,
        'offset' => ($currentPage - 1) * $perPage
    ];
    
    return $pagination;
}

/**
 * Build pagination HTML
 */
function buildPaginationHtml($pagination, $url = '') {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm justify-content-center">';
    
    // Previous
    if ($pagination['has_previous']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $pagination['previous_page'] . '">‹</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">‹</span></li>';
    }
    
    // Pages
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next
    if ($pagination['has_next']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?page=' . $pagination['next_page'] . '">›</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">›</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Log hoạt động
 */
function logActivity($action, $module, $details = '', $userId = null) {
    global $db;
    
    if (!$userId) {
        $user = getCurrentUser();
        $userId = $user['id'] ?? null;
    }
    
    $sql = "INSERT INTO activity_logs (user_id, action, module, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $userId,
        $action,
        $module,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    
    try {
        $db->execute($sql, $params);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Export Excel
 */
function exportToExcel($data, $headers, $filename = 'export') {
    $filename = $filename . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Tạo file Excel đơn giản (có thể dùng thư viện PhpSpreadsheet)
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, $headers);
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Kiểm tra mã code đã tồn tại
 */
function isCodeExists($table, $code, $excludeId = null, $additionalWhere = []) {
    global $db;
    
    $sql = "SELECT id FROM {$table} WHERE code = ?";
    $params = [$code];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    foreach ($additionalWhere as $field => $value) {
        $sql .= " AND {$field} = ?";
        $params[] = $value;
    }
    
    return $db->fetch($sql, $params) !== false;
}

/**
 * Tạo audit trail
 */
function createAuditTrail($table, $recordId, $action, $oldData = null, $newData = null) {
    global $db;
    
    $user = getCurrentUser();
    
    $sql = "INSERT INTO audit_trails (table_name, record_id, action, old_data, new_data, user_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $table,
        $recordId,
        $action,
        $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
        $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
        $user['id'] ?? null
    ];
    
    try {
        $db->execute($sql, $params);
    } catch (Exception $e) {
        error_log("Failed to create audit trail: " . $e->getMessage());
    }
}

/**
 * Format currency VND
 */
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function isValidPhone($phone) {
    $pattern = '/^(\+84|84|0)(3[2-9]|5[689]|7[06-9]|8[1-689]|9[0-46-9])[0-9]{7}$/';
    return preg_match($pattern, $phone);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Clean filename
 */
function cleanFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return trim($filename, '_');
}

/**
 * Get file MIME type
 */
function getMimeType($filePath) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    return $mime;
}

/**
 * Resize image
 */
function resizeImage($source, $destination, $maxWidth = 800, $maxHeight = 600, $quality = 85) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) return false;
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio < 1) {
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    // Create image resource
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) return false;
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save image
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $destination, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $destination, 9);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $destination);
            break;
    }
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}
?>