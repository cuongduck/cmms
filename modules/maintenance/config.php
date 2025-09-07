<?php
/**
 * Maintenance Module Configuration
 * CMMS Maintenance Management Configuration
 */

// Module Information
define('MAINTENANCE_MODULE_NAME', 'Quản lý bảo trì');
define('MAINTENANCE_MODULE_VERSION', '1.0.0');
define('MAINTENANCE_MODULE_DESCRIPTION', 'Module quản lý bảo trì thiết bị với 3 loại: PM, BREAKDOWN, CLIT');

// Maintenance Types
$maintenanceTypes = [
    'PM' => [
        'name' => 'Bảo trì kế hoạch',
        'description' => 'Bảo trì định kỳ theo kế hoạch',
        'color' => '#06b6d4',
        'icon' => 'fas fa-calendar-check',
        'requires_plan' => true
    ],
    'BREAKDOWN' => [
        'name' => 'Bảo trì sự cố',
        'description' => 'Bảo trì khắc phục sự cố',
        'color' => '#ef4444',
        'icon' => 'fas fa-exclamation-triangle',
        'requires_plan' => false
    ],
    'CLIT' => [
        'name' => 'Hiệu chuẩn thiết bị',
        'description' => 'Hiệu chuẩn và kiểm định thiết bị',
        'color' => '#f59e0b',
        'icon' => 'fas fa-balance-scale',
        'requires_plan' => true
    ]
];

// Execution Status
$executionStatuses = [
    'planned' => [
        'name' => 'Đã lên kế hoạch',
        'color' => '#3b82f6',
        'icon' => 'fas fa-calendar-plus',
        'description' => 'Lệnh đã được tạo và lên kế hoạch'
    ],
    'in_progress' => [
        'name' => 'Đang thực hiện',
        'color' => '#f59e0b',
        'icon' => 'fas fa-cog fa-spin',
        'description' => 'Đang trong quá trình thực hiện'
    ],
    'completed' => [
        'name' => 'Hoàn thành',
        'color' => '#10b981',
        'icon' => 'fas fa-check-circle',
        'description' => 'Đã hoàn thành thành công'
    ],
    'cancelled' => [
        'name' => 'Đã hủy',
        'color' => '#ef4444',
        'icon' => 'fas fa-times-circle',
        'description' => 'Lệnh đã bị hủy bỏ'
    ],
    'on_hold' => [
        'name' => 'Tạm hoãn',
        'color' => '#6b7280',
        'icon' => 'fas fa-pause-circle',
        'description' => 'Tạm thời dừng thực hiện'
    ]
];

// Priority Levels
$priorityLevels = [
    'Low' => [
        'name' => 'Thấp',
        'color' => '#10b981',
        'urgency_days' => 30,
        'description' => 'Có thể trì hoãn nếu cần'
    ],
    'Medium' => [
        'name' => 'Trung bình',
        'color' => '#f59e0b',
        'urgency_days' => 7,
        'description' => 'Thực hiện theo kế hoạch'
    ],
    'High' => [
        'name' => 'Cao',
        'color' => '#ef4444',
        'urgency_days' => 3,
        'description' => 'Cần thực hiện sớm'
    ],
    'Critical' => [
        'name' => 'Nghiêm trọng',
        'color' => '#7c2d12',
        'urgency_days' => 1,
        'description' => 'Thực hiện ngay lập tức'
    ]
];

// Default Settings
$maintenanceSettings = [
    'default_duration' => 60, // minutes
    'default_priority' => 'Medium',
    'auto_assign' => false,
    'require_checklist' => true,
    'require_parts_tracking' => true,
    'auto_generate_next_date' => true,
    'notification_before_days' => [1, 3, 7], // Notify before X days
    'overdue_notification_interval' => 24, // hours
    'cost_tracking_enabled' => true,
    'attachment_max_size' => 10 * 1024 * 1024, // 10MB
    'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']
];

// Frequency Types
$frequencyTypes = [
    'daily' => [
        'name' => 'Hàng ngày',
        'days' => 1,
        'description' => 'Thực hiện hàng ngày'
    ],
    'weekly' => [
        'name' => 'Hàng tuần',
        'days' => 7,
        'description' => 'Thực hiện hàng tuần'
    ],
    'monthly' => [
        'name' => 'Hàng tháng',
        'days' => 30,
        'description' => 'Thực hiện hàng tháng'
    ],
    'quarterly' => [
        'name' => 'Hàng quý',
        'days' => 90,
        'description' => 'Thực hiện hàng quý'
    ],
    'yearly' => [
        'name' => 'Hàng năm',
        'days' => 365,
        'description' => 'Thực hiện hàng năm'
    ],
    'custom' => [
        'name' => 'Tùy chỉnh',
        'days' => null,
        'description' => 'Tần suất tùy chỉnh'
    ]
];

// Default Checklists by Equipment Type
$defaultChecklists = [
    'PM' => [
        'general' => [
            'name' => 'Bảo trì định kỳ chung',
            'items' => [
                ['description' => 'Kiểm tra tình trạng bề ngoài thiết bị', 'required' => true],
                ['description' => 'Làm sạch thiết bị', 'required' => true],
                ['description' => 'Kiểm tra các bu lông, ốc vít', 'required' => true],
                ['description' => 'Bôi trơn các bộ phận chuyển động', 'required' => false],
                ['description' => 'Kiểm tra hệ thống điện', 'required' => true],
                ['description' => 'Kiểm tra các thiết bị an toàn', 'required' => true],
                ['description' => 'Ghi nhận các bất thường', 'required' => false]
            ]
        ],
        'electrical' => [
            'name' => 'Bảo trì thiết bị điện',
            'items' => [
                ['description' => 'Kiểm tra cách điện', 'required' => true],
                ['description' => 'Đo điện trở cách điện', 'required' => true],
                ['description' => 'Kiểm tra tiếp địa', 'required' => true],
                ['description' => 'Làm sạch tủ điện', 'required' => true],
                ['description' => 'Kiểm tra CB, contactor', 'required' => true],
                ['description' => 'Kiểm tra dây dẫn', 'required' => true]
            ]
        ],
        'mechanical' => [
            'name' => 'Bảo trì thiết bị cơ khí',
            'items' => [
                ['description' => 'Kiểm tra độ rung', 'required' => true],
                ['description' => 'Kiểm tra nhiệt độ bearing', 'required' => true],
                ['description' => 'Thay dầu bôi trơn', 'required' => false],
                ['description' => 'Kiểm tra căng dây đai', 'required' => true],
                ['description' => 'Kiểm tra khớp nối', 'required' => true]
            ]
        ]
    ],
    'CLIT' => [
        'general' => [
            'name' => 'Hiệu chuẩn chung',
            'items' => [
                ['description' => 'Chuẩn bị thiết bị chuẩn', 'required' => true],
                ['description' => 'Kiểm tra điều kiện môi trường', 'required' => true],
                ['description' => 'Thực hiện hiệu chuẩn', 'required' => true],
                ['description' => 'Ghi nhận kết quả', 'required' => true],
                ['description' => 'Đánh giá sai số', 'required' => true],
                ['description' => 'Cập nhật tem hiệu chuẩn', 'required' => true]
            ]
        ]
    ]
];

// Report Templates
$reportTemplates = [
    'daily_maintenance' => [
        'name' => 'Báo cáo bảo trì hàng ngày',
        'fields' => ['equipment_code', 'maintenance_type', 'status', 'assigned_to', 'completion_percentage']
    ],
    'monthly_summary' => [
        'name' => 'Tổng kết bảo trì hàng tháng',
        'fields' => ['total_completed', 'total_cost', 'avg_duration', 'overdue_count']
    ],
    'equipment_history' => [
        'name' => 'Lịch sử bảo trì thiết bị',
        'fields' => ['execution_date', 'maintenance_type', 'duration', 'cost', 'issues_found']
    ]
];

// Notification Templates
$notificationTemplates = [
    'maintenance_due' => [
        'subject' => 'Thông báo: Bảo trì thiết bị đến hạn',
        'template' => 'Thiết bị {equipment_code} - {equipment_name} cần bảo trì vào ngày {due_date}'
    ],
    'maintenance_overdue' => [
        'subject' => 'Cảnh báo: Bảo trì thiết bị quá hạn',
        'template' => 'Thiết bị {equipment_code} - {equipment_name} đã quá hạn bảo trì {days_overdue} ngày'
    ],
    'execution_completed' => [
        'subject' => 'Thông báo: Hoàn thành bảo trì thiết bị',
        'template' => 'Đã hoàn thành bảo trì thiết bị {equipment_code} - {equipment_name} lúc {completion_time}'
    ]
];

// Helper Functions
function getMaintenanceTypeInfo($type) {
    global $maintenanceTypes;
    return $maintenanceTypes[$type] ?? null;
}

function getExecutionStatusInfo($status) {
    global $executionStatuses;
    return $executionStatuses[$status] ?? null;
}

function getPriorityInfo($priority) {
    global $priorityLevels;
    return $priorityLevels[$priority] ?? null;
}

function getFrequencyInfo($type) {
    global $frequencyTypes;
    return $frequencyTypes[$type] ?? null;
}

function getMaintenanceSetting($key, $default = null) {
    global $maintenanceSettings;
    return $maintenanceSettings[$key] ?? $default;
}

function getDefaultChecklist($maintenanceType, $equipmentCategory = 'general') {
    global $defaultChecklists;
    return $defaultChecklists[$maintenanceType][$equipmentCategory] ?? 
           $defaultChecklists[$maintenanceType]['general'] ?? [];
}

function calculateNextMaintenanceDate($lastDate, $frequencyDays) {
    $date = new DateTime($lastDate);
    $date->add(new DateInterval('P' . $frequencyDays . 'D'));
    return $date->format('Y-m-d');
}

function getUrgencyStatus($nextMaintenanceDate) {
    if (!$nextMaintenanceDate) {
        return 'no_plan';
    }
    
    $today = new DateTime();
    $nextDate = new DateTime($nextMaintenanceDate);
    $diff = $today->diff($nextDate);
    
    if ($nextDate < $today) {
        return 'overdue';
    } elseif ($diff->days == 0) {
        return 'today';
    } elseif ($diff->days <= 7) {
        return 'this_week';
    } elseif ($diff->days <= 30) {
        return 'this_month';
    } else {
        return 'future';
    }
}

function formatMaintenanceCode($type, $sequence) {
    return strtoupper($type) . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

function formatExecutionCode($type, $sequence) {
    return strtoupper($type) . 'E' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

function validateMaintenanceData($data, $type = 'plan') {
    $errors = [];
    
    if ($type === 'plan') {
        if (empty($data['equipment_id'])) {
            $errors[] = 'Equipment ID is required';
        }
        if (empty($data['plan_name'])) {
            $errors[] = 'Plan name is required';
        }
        if (empty($data['maintenance_type']) || !isset($GLOBALS['maintenanceTypes'][$data['maintenance_type']])) {
            $errors[] = 'Valid maintenance type is required';
        }
        if (empty($data['frequency_days']) || $data['frequency_days'] <= 0) {
            $errors[] = 'Frequency days must be greater than 0';
        }
    } elseif ($type === 'execution') {
        if (empty($data['equipment_id'])) {
            $errors[] = 'Equipment ID is required';
        }
        if (empty($data['execution_type']) || !isset($GLOBALS['maintenanceTypes'][$data['execution_type']])) {
            $errors[] = 'Valid execution type is required';
        }
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }
    }
    
    return $errors;
}

function getMaintenancePermissions($userRole) {
    $permissions = [
        'Admin' => [
            'view' => true,
            'create' => true,
            'edit' => true,
            'delete' => true,
            'assign' => true,
            'approve' => true,
            'export' => true
        ],
        'Supervisor' => [
            'view' => true,
            'create' => true,
            'edit' => true,
            'delete' => false,
            'assign' => true,
            'approve' => false,
            'export' => true
        ],
        'Production Manager' => [
            'view' => true,
            'create' => true,
            'edit' => true,
            'delete' => true,
            'assign' => true,
            'approve' => true,
            'export' => true
        ],
        'User' => [
            'view' => true,
            'create' => false,
            'edit' => false,
            'delete' => false,
            'assign' => false,
            'approve' => false,
            'export' => false
        ]
    ];
    
    return $permissions[$userRole] ?? $permissions['User'];
}

// Export configurations for JavaScript
function getMaintenanceConfigForJS() {
    global $maintenanceTypes, $executionStatuses, $priorityLevels, $frequencyTypes;
    
    return [
        'maintenanceTypes' => $maintenanceTypes,
        'executionStatuses' => $executionStatuses,
        'priorityLevels' => $priorityLevels,
        'frequencyTypes' => $frequencyTypes,
        'settings' => [
            'defaultDuration' => getMaintenanceSetting('default_duration'),
            'defaultPriority' => getMaintenanceSetting('default_priority'),
            'maxFileSize' => getMaintenanceSetting('attachment_max_size'),
            'allowedFileTypes' => getMaintenanceSetting('allowed_file_types')
        ]
    ];
}
?>