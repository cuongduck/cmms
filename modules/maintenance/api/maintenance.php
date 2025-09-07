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

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'workshops':
            handleGetWorkshops();
            break;
            
        case 'equipment':
            handleGetEquipment();
            break;
            
        case 'maintenance_plans':
            if ($method === 'GET') {
                handleGetMaintenancePlans();
            } elseif ($method === 'POST') {
                handleCreateMaintenancePlan();
            } elseif ($method === 'PUT') {
                handleUpdateMaintenancePlan();
            } elseif ($method === 'DELETE') {
                handleDeleteMaintenancePlan();
            }
            break;
            
        case 'executions':
            if ($method === 'GET') {
                handleGetExecutions();
            } elseif ($method === 'POST') {
                handleCreateExecution();
            } elseif ($method === 'PUT') {
                handleUpdateExecution();
            }
            break;
            
        case 'update_status':
            handleUpdateExecutionStatus();
            break;
            
        case 'checklist':
            handleUpdateChecklist();
            break;
            
        case 'parts_used':
            handleUpdatePartsUsed();
            break;
            
        case 'schedule':
            handleGetMaintenanceSchedule();
            break;
            
        case 'statistics':
            handleGetStatistics();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function handleGetWorkshops() {
    global $db;
    
    $industryId = $_GET['industry_id'] ?? '';
    
    if (empty($industryId)) {
        throw new Exception('Industry ID is required');
    }
    
    $sql = "SELECT id, name FROM workshops WHERE industry_id = ? AND status = 'active' ORDER BY name";
    $workshops = $db->fetchAll($sql, [$industryId]);
    
    echo json_encode([
        'success' => true,
        'workshops' => $workshops
    ], JSON_UNESCAPED_UNICODE);
}

function handleGetEquipment() {
    global $db;
    
    $filters = [
        'industry_id' => $_GET['industry_id'] ?? '',
        'workshop_id' => $_GET['workshop_id'] ?? '',
        'line_id' => $_GET['line_id'] ?? '',
        'maintenance_type' => $_GET['maintenance_type'] ?? ''
    ];
    
    $sql = "SELECT e.*, mt.name as machine_type_name, i.name as industry_name, 
                   w.name as workshop_name, pl.name as line_name, a.name as area_name
            FROM equipment e
            JOIN machine_types mt ON e.machine_type_id = mt.id
            JOIN industries i ON e.industry_id = i.id
            JOIN workshops w ON e.workshop_id = w.id
            LEFT JOIN production_lines pl ON e.line_id = pl.id
            LEFT JOIN areas a ON e.area_id = a.id
            WHERE e.status = 'active'";
    
    $params = [];
    
    if (!empty($filters['industry_id'])) {
        $sql .= " AND e.industry_id = ?";
        $params[] = $filters['industry_id'];
    }
    
    if (!empty($filters['workshop_id'])) {
        $sql .= " AND e.workshop_id = ?";
        $params[] = $filters['workshop_id'];
    }
    
    if (!empty($filters['line_id'])) {
        $sql .= " AND e.line_id = ?";
        $params[] = $filters['line_id'];
    }
    
    // For maintenance, only show equipment that needs maintenance
    if (!empty($filters['maintenance_type'])) {
        $sql .= " AND e.maintenance_frequency_days > 0";
    }
    
    $sql .= " ORDER BY e.code";
    
    $equipment = $db->fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'equipment' => $equipment
    ], JSON_UNESCAPED_UNICODE);
}

function handleGetMaintenancePlans() {
    global $db;
    
    requirePermission('maintenance', 'view');
    
    $equipmentId = $_GET['equipment_id'] ?? '';
    $maintenanceType = $_GET['maintenance_type'] ?? '';
    
    $sql = "SELECT mp.*, e.code as equipment_code, e.name as equipment_name,
                   u1.full_name as assigned_name, u2.full_name as backup_name
            FROM maintenance_plans mp
            JOIN equipment e ON mp.equipment_id = e.id
            LEFT JOIN users u1 ON mp.assigned_to = u1.id
            LEFT JOIN users u2 ON mp.backup_assigned_to = u2.id
            WHERE mp.status = 'active'";
    
    $params = [];
    
    if (!empty($equipmentId)) {
        $sql .= " AND mp.equipment_id = ?";
        $params[] = $equipmentId;
    }
    
    if (!empty($maintenanceType)) {
        $sql .= " AND mp.maintenance_type = ?";
        $params[] = $maintenanceType;
    }
    
    $sql .= " ORDER BY mp.next_maintenance_date";
    
    $plans = $db->fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'plans' => $plans
    ], JSON_UNESCAPED_UNICODE);
}

function handleGetExecutions() {
    global $db;
    
    requirePermission('maintenance', 'view');
    
    $equipmentId = $_GET['equipment_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    
    $sql = "SELECT me.*, e.code as equipment_code, e.name as equipment_name,
                   mp.plan_code, mp.plan_name,
                   u.full_name as assigned_name
            FROM maintenance_executions me
            JOIN equipment e ON me.equipment_id = e.id
            LEFT JOIN maintenance_plans mp ON me.plan_id = mp.id
            LEFT JOIN users u ON me.assigned_to = u.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($equipmentId)) {
        $sql .= " AND me.equipment_id = ?";
        $params[] = $equipmentId;
    }
    
    if (!empty($status)) {
        $sql .= " AND me.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY me.scheduled_date DESC LIMIT ?";
    $params[] = $limit;
    
    $executions = $db->fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'executions' => $executions
    ], JSON_UNESCAPED_UNICODE);
}

function handleUpdateExecutionStatus() {
    global $db;
    
    requirePermission('maintenance', 'edit');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['execution_id']) || empty($input['status'])) {
        throw new Exception('Execution ID and status are required');
    }
    
    $executionId = $input['execution_id'];
    $newStatus = $input['status'];
    $comments = $input['comments'] ?? '';
    
    // Validate status
    $validStatuses = ['planned', 'in_progress', 'completed', 'cancelled', 'on_hold'];
    if (!in_array($newStatus, $validStatuses)) {
        throw new Exception('Invalid status');
    }
    
    // Get current execution
    $execution = $db->fetch("SELECT * FROM maintenance_executions WHERE id = ?", [$executionId]);
    if (!$execution) {
        throw new Exception('Execution not found');
    }
    
    $oldStatus = $execution['status'];
    
    $db->beginTransaction();
    
    try {
        // Update execution status
        $updates = ['status' => $newStatus];
        
        if ($newStatus === 'in_progress' && empty($execution['started_at'])) {
            $updates['started_at'] = date('Y-m-d H:i:s');
        }
        
        if ($newStatus === 'completed' && empty($execution['completed_at'])) {
            $updates['completed_at'] = date('Y-m-d H:i:s');
            $updates['completion_percentage'] = 100;
            
            // Calculate actual duration
            if ($execution['started_at']) {
                $start = new DateTime($execution['started_at']);
                $end = new DateTime();
                $duration = $end->getTimestamp() - $start->getTimestamp();
                $updates['actual_duration'] = round($duration / 60); // Convert to minutes
            }
        }
        
        $setParts = [];
        $params = [];
        foreach ($updates as $field => $value) {
            $setParts[] = "{$field} = ?";
            $params[] = $value;
        }
        $params[] = $executionId;
        
        $sql = "UPDATE maintenance_executions SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
        $db->execute($sql, $params);
        
        // Create history record
        $sql = "INSERT INTO maintenance_history (execution_id, action, status_from, status_to, comments, performed_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $db->execute($sql, [
            $executionId,
            'status_changed',
            $oldStatus,
            $newStatus,
            $comments,
            getCurrentUser()['id']
        ]);
        
        // If completed and linked to plan, update next maintenance date
        if ($newStatus === 'completed' && $execution['plan_id']) {
            $plan = $db->fetch("SELECT * FROM maintenance_plans WHERE id = ?", [$execution['plan_id']]);
            if ($plan) {
                $nextDate = new DateTime();
                $nextDate->add(new DateInterval('P' . $plan['frequency_days'] . 'D'));
                
                $sql = "UPDATE maintenance_plans SET 
                        last_maintenance_date = CURDATE(),
                        next_maintenance_date = ?
                        WHERE id = ?";
                $db->execute($sql, [$nextDate->format('Y-m-d'), $plan['id']]);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật trạng thái thành công'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleUpdateChecklist() {
    global $db;
    
    requirePermission('maintenance', 'edit');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['execution_id']) || !isset($input['checklist'])) {
        throw new Exception('Execution ID and checklist data are required');
    }
    
    $executionId = $input['execution_id'];
    $checklist = $input['checklist'];
    
    // Validate execution exists
    $execution = $db->fetch("SELECT id FROM maintenance_executions WHERE id = ?", [$executionId]);
    if (!$execution) {
        throw new Exception('Execution not found');
    }
    
    // Calculate completion percentage
    $totalItems = count($checklist);
    $completedItems = 0;
    
    foreach ($checklist as $item) {
        if ($item['completed'] ?? false) {
            $completedItems++;
        }
    }
    
    $completionPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 2) : 0;
    
    // Update checklist and completion percentage
    $sql = "UPDATE maintenance_executions SET 
            checklist_data = ?, 
            completion_percentage = ?,
            updated_at = NOW()
            WHERE id = ?";
    
    $db->execute($sql, [
        json_encode($checklist, JSON_UNESCAPED_UNICODE),
        $completionPercentage,
        $executionId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật checklist thành công',
        'completion_percentage' => $completionPercentage
    ], JSON_UNESCAPED_UNICODE);
}

function handleUpdatePartsUsed() {
    global $db;
    
    requirePermission('maintenance', 'edit');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['execution_id']) || !isset($input['parts_used'])) {
        throw new Exception('Execution ID and parts data are required');
    }
    
    $executionId = $input['execution_id'];
    $partsUsed = $input['parts_used'];
    $laborCost = floatval($input['labor_cost'] ?? 0);
    
    // Calculate total parts cost
    $partsCost = 0;
    foreach ($partsUsed as $part) {
        $partsCost += floatval($part['quantity'] ?? 0) * floatval($part['unit_price'] ?? 0);
    }
    
    $totalCost = $partsCost + $laborCost;
    
    // Update parts and costs
    $sql = "UPDATE maintenance_executions SET 
            parts_used = ?, 
            labor_cost = ?,
            parts_cost = ?,
            total_cost = ?,
            updated_at = NOW()
            WHERE id = ?";
    
    $db->execute($sql, [
        json_encode($partsUsed, JSON_UNESCAPED_UNICODE),
        $laborCost,
        $partsCost,
        $totalCost,
        $executionId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật phụ tùng thành công',
        'parts_cost' => $partsCost,
        'total_cost' => $totalCost
    ], JSON_UNESCAPED_UNICODE);
}

function handleGetMaintenanceSchedule() {
    global $db;
    
    requirePermission('maintenance', 'view');
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    // Get planned maintenances
    $sql = "SELECT mp.*, e.code as equipment_code, e.name as equipment_name,
                   i.name as industry_name, w.name as workshop_name
            FROM maintenance_plans mp
            JOIN equipment e ON mp.equipment_id = e.id
            JOIN industries i ON e.industry_id = i.id
            JOIN workshops w ON e.workshop_id = w.id
            WHERE mp.status = 'active' 
            AND mp.next_maintenance_date BETWEEN ? AND ?
            ORDER BY mp.next_maintenance_date";
    
    $plannedMaintenances = $db->fetchAll($sql, [$startDate, $endDate]);
    
    // Get scheduled executions
    $sql = "SELECT me.*, e.code as equipment_code, e.name as equipment_name,
                   mp.plan_code, u.full_name as assigned_name
            FROM maintenance_executions me
            JOIN equipment e ON me.equipment_id = e.id
            LEFT JOIN maintenance_plans mp ON me.plan_id = mp.id
            LEFT JOIN users u ON me.assigned_to = u.id
            WHERE me.scheduled_date BETWEEN ? AND ?
            AND me.status IN ('planned', 'in_progress', 'on_hold')
            ORDER BY me.scheduled_date";
    
    $scheduledExecutions = $db->fetchAll($sql, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    
    echo json_encode([
        'success' => true,
        'planned_maintenances' => $plannedMaintenances,
        'scheduled_executions' => $scheduledExecutions
    ], JSON_UNESCAPED_UNICODE);
}

function handleGetStatistics() {
    global $db;
    
    requirePermission('maintenance', 'view');
    
    // Get overview statistics
    $overviewStats = $db->fetch("
        SELECT 
            COUNT(DISTINCT e.id) as total_equipment,
            COUNT(DISTINCT mp.id) as total_plans,
            COUNT(DISTINCT CASE WHEN me.status = 'completed' AND me.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN me.id END) as completed_this_month,
            COUNT(DISTINCT CASE WHEN mp.next_maintenance_date < CURDATE() THEN mp.id END) as overdue_plans
        FROM equipment e
        LEFT JOIN maintenance_plans mp ON e.id = mp.equipment_id AND mp.status = 'active'
        LEFT JOIN maintenance_executions me ON e.id = me.equipment_id
        WHERE e.maintenance_frequency_days > 0
    ");
    
    // Get maintenance type distribution
    $typeStats = $db->fetchAll("
        SELECT 
            maintenance_type,
            COUNT(*) as count
        FROM maintenance_plans
        WHERE status = 'active'
        GROUP BY maintenance_type
    ");
    
    // Get monthly completion trend
    $monthlyStats = $db->fetchAll("
        SELECT 
            DATE_FORMAT(completed_at, '%Y-%m') as month,
            COUNT(*) as completed_count,
            AVG(actual_duration) as avg_duration,
            SUM(total_cost) as total_cost
        FROM maintenance_executions
        WHERE status = 'completed' 
        AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(completed_at, '%Y-%m')
        ORDER BY month
    ");
    
    echo json_encode([
        'success' => true,
        'overview' => $overviewStats,
        'type_distribution' => $typeStats,
        'monthly_trend' => $monthlyStats
    ], JSON_UNESCAPED_UNICODE);
}

function handleCreateMaintenancePlan() {
    global $db;
    
    requirePermission('maintenance', 'create');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $required = ['equipment_id', 'plan_name', 'maintenance_type', 'frequency_days'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field {$field} is required");
        }
    }
    
    // Check if equipment already has active plan of this type
    $existingPlan = $db->fetch(
        "SELECT id FROM maintenance_plans 
         WHERE equipment_id = ? AND maintenance_type = ? AND status = 'active'",
        [$input['equipment_id'], $input['maintenance_type']]
    );
    
    if ($existingPlan) {
        throw new Exception('Equipment already has active maintenance plan of this type');
    }
    
    $db->beginTransaction();
    
    try {
        // Generate plan code
        $prefix = strtoupper($input['maintenance_type']);
        $sql = "SELECT MAX(CAST(SUBSTRING(plan_code, 4) AS UNSIGNED)) as max_seq 
                FROM maintenance_plans 
                WHERE plan_code LIKE ?";
        $result = $db->fetch($sql, [$prefix . '%']);
        $sequence = ($result['max_seq'] ?? 0) + 1;
        $planCode = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        
        // Calculate next maintenance date
        $nextDate = new DateTime();
        $nextDate->add(new DateInterval('P' . $input['frequency_days'] . 'D'));
        
        // Insert maintenance plan
        $sql = "INSERT INTO maintenance_plans (
            equipment_id, plan_code, plan_name, maintenance_type, 
            frequency_days, frequency_type, next_maintenance_date,
            estimated_duration, description, assigned_to, backup_assigned_to,
            priority, safety_requirements, required_skills, checklist, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $input['equipment_id'],
            $planCode,
            $input['plan_name'],
            $input['maintenance_type'],
            $input['frequency_days'],
            $input['frequency_type'] ?? 'custom',
            $nextDate->format('Y-m-d'),
            $input['estimated_duration'] ?? 60,
            $input['description'] ?? null,
            $input['assigned_to'] ?? null,
            $input['backup_assigned_to'] ?? null,
            $input['priority'] ?? 'Medium',
            $input['safety_requirements'] ?? null,
            $input['required_skills'] ?? null,
            !empty($input['checklist']) ? json_encode($input['checklist'], JSON_UNESCAPED_UNICODE) : null,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $planId = $db->lastInsertId();
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Created maintenance plan successfully',
            'plan_id' => $planId,
            'plan_code' => $planCode
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleCreateExecution() {
    global $db;
    
    requirePermission('maintenance', 'create');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $required = ['equipment_id', 'execution_type', 'title'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Field {$field} is required");
        }
    }
    
    $db->beginTransaction();
    
    try {
        // Generate execution code
        $prefix = strtoupper($input['execution_type']) . 'E';
        $sql = "SELECT MAX(CAST(SUBSTRING(execution_code, 4) AS UNSIGNED)) as max_seq 
                FROM maintenance_executions 
                WHERE execution_code LIKE ?";
        $result = $db->fetch($sql, [$prefix . '%']);
        $sequence = ($result['max_seq'] ?? 0) + 1;
        $executionCode = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        
        // Insert maintenance execution
        $sql = "INSERT INTO maintenance_executions (
            plan_id, equipment_id, execution_code, execution_type, title, description,
            scheduled_date, estimated_duration, assigned_to, team_members, priority, 
            notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $input['plan_id'] ?? null,
            $input['equipment_id'],
            $executionCode,
            $input['execution_type'],
            $input['title'],
            $input['description'] ?? null,
            $input['scheduled_date'] ?? date('Y-m-d H:i:s'),
            $input['estimated_duration'] ?? 60,
            $input['assigned_to'] ?? null,
            !empty($input['team_members']) ? json_encode($input['team_members']) : null,
            $input['priority'] ?? 'Medium',
            $input['notes'] ?? null,
            getCurrentUser()['id']
        ];
        
        $db->execute($sql, $params);
        $executionId = $db->lastInsertId();
        
        // Create history record
        $sql = "INSERT INTO maintenance_history (execution_id, action, comments, performed_by) 
                VALUES (?, 'created', ?, ?)";
        $db->execute($sql, [$executionId, 'Created maintenance execution', getCurrentUser()['id']]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Created maintenance execution successfully',
            'execution_id' => $executionId,
            'execution_code' => $executionCode
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>