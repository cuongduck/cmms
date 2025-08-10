<?php
// modules/qr-scanner/index.php - QR Scanner Module
require_once '../../config/database.php';
require_once '../../config/functions.php';

$page_title = 'Quét mã QR';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quét mã QR thiết bị</h1>
            <p class="text-muted">Sử dụng camera để quét mã QR và xem thông tin thiết bị</p>
        </div>
    </div>

    <div class="row">
        <!-- QR Scanner -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-camera me-2"></i>Camera Scanner
                    </h5>
                    <div>
                        <button class="btn btn-success btn-sm" id="startScanBtn">
                            <i class="fas fa-play me-2"></i>Bắt đầu quét
                        </button>
                        <button class="btn btn-danger btn-sm" id="stopScanBtn" style="display: none;">
                            <i class="fas fa-stop me-2"></i>Dừng quét
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
                    <div id="qr-reader-results" class="mt-3"></div>
                </div>
            </div>

            <!-- Manual Input -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-keyboard me-2"></i>Nhập thủ công
                    </h6>
                </div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="text" class="form-control" id="manualInput" 
                               placeholder="Nhập ID thiết bị hoặc dán từ clipboard">
                        <button class="btn btn-outline-primary" onclick="searchEquipment()">
                            <i class="fas fa-search me-2"></i>Tìm kiếm
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scan History -->
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>Lịch sử quét
                    </h6>
                    <button class="btn btn-outline-secondary btn-sm" onclick="clearHistory()">
                        <i class="fas fa-trash me-2"></i>Xóa lịch sử
                    </button>
                </div>
                <div class="card-body">
                    <div id="scanHistory">
                        <p class="text-muted text-center">Chưa có lịch sử quét</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Equipment Information -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Thông tin thiết bị
                    </h5>
                </div>
                <div class="card-body" id="equipmentInfo">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-qrcode fa-5x mb-3 opacity-25"></i>
                        <h5>Quét mã QR hoặc nhập ID thiết bị</h5>
                        <p>Thông tin thiết bị sẽ hiển thị ở đây</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3" id="quickActions" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>Thao tác nhanh
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="viewMaintenanceHistory()">
                            <i class="fas fa-history me-2"></i>Xem lịch sử bảo trì
                        </button>
                        <button class="btn btn-outline-success" onclick="createMaintenancePlan()">
                            <i class="fas fa-calendar-plus me-2"></i>Tạo kế hoạch bảo trì
                        </button>
                        <button class="btn btn-outline-warning" onclick="reportIssue()">
                            <i class="fas fa-exclamation-triangle me-2"></i>Báo cáo sự cố
                        </button>
                        <button class="btn btn-outline-info" onclick="viewBOM()">
                            <i class="fas fa-list-alt me-2"></i>Xem BOM thiết bị
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Equipment Detail Modal -->
<div class="modal fade" id="equipmentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết thiết bị</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="equipmentDetailContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
let html5QrcodeScanner = null;
let currentEquipmentId = null;
let scanHistory = [];

$(document).ready(function() {
    loadScanHistory();
    
    // Manual input enter key
    $('#manualInput').on('keypress', function(e) {
        if (e.which === 13) {
            searchEquipment();
        }
    });
    
    // Start scan button
    $('#startScanBtn').click(function() {
        startScanning();
    });
    
    // Stop scan button
    $('#stopScanBtn').click(function() {
        stopScanning();
    });
});

function startScanning() {
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };
    
    html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", config, false);
    
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    
    $('#startScanBtn').hide();
    $('#stopScanBtn').show();
    
    // Add scanning indicator
    $('#qr-reader-results').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Đang quét... Đưa mã QR vào khung hình</div>');
}

function stopScanning() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear();
        html5QrcodeScanner = null;
    }
    
    $('#startScanBtn').show();
    $('#stopScanBtn').hide();
    $('#qr-reader-results').html('');
}

function onScanSuccess(decodedText, decodedResult) {
    // Stop scanning after successful scan
    stopScanning();
    
    // Show success message
    $('#qr-reader-results').html(`
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>Quét thành công: <strong>${decodedText}</strong>
        </div>
    `);
    
    // Search for equipment
    searchEquipmentById(decodedText);
    
    // Add to history
    addToScanHistory(decodedText);
}

function onScanFailure(error) {
    // Ignore scan failures, they happen frequently
    console.log(`QR scan error: ${error}`);
}

function searchEquipment() {
    const equipmentId = $('#manualInput').val().trim();
    if (!equipmentId) {
        CMMS.showAlert('Vui lòng nhập ID thiết bị', 'warning');
        return;
    }
    
    searchEquipmentById(equipmentId);
    addToScanHistory(equipmentId);
}

function searchEquipmentById(equipmentId) {
    // Show loading
    $('#equipmentInfo').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
            <p class="mt-3">Đang tìm kiếm thiết bị: <strong>${equipmentId}</strong></p>
        </div>
    `);
    
    // Search equipment
    CMMS.ajax('../equipment/api.php', {
        method: 'GET',
        data: { action: 'search_by_id', equipment_id: equipmentId }
    }).then(response => {
        if (response.success && response.data) {
            displayEquipmentInfo(response.data);
            currentEquipmentId = response.data.id;
            $('#quickActions').show();
        } else {
            displayNotFound(equipmentId);
            $('#quickActions').hide();
        }
    }).catch(error => {
        displayError(equipmentId);
        $('#quickActions').hide();
    });
}

function displayEquipmentInfo(equipment) {
    const statusClass = getStatusClass(equipment.tinh_trang);
    const statusText = getStatusText(equipment.tinh_trang);
    
    $('#equipmentInfo').html(`
        <div class="row">
            <div class="col-md-8">
                <h5 class="mb-3">${equipment.ten_thiet_bi}</h5>
                <table class="table table-borderless table-sm">
                    <tr>
                        <th width="30%">ID thiết bị:</th>
                        <td><strong class="text-primary">${equipment.id_thiet_bi}</strong></td>
                    </tr>
                    <tr>
                        <th>Vị trí:</th>
                        <td>${equipment.ten_xuong} - ${equipment.ten_line} - ${equipment.vi_tri || ''}</td>
                    </tr>
                    <tr>
                        <th>Ngành:</th>
                        <td>${equipment.nganh || 'Không xác định'}</td>
                    </tr>
                    <tr>
                        <th>Tình trạng:</th>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                    </tr>
                    <tr>
                        <th>Người chủ quản:</th>
                        <td>${equipment.chu_quan_name || 'Chưa phân công'}</td>
                    </tr>
                    <tr>
                        <th>Năm sản xuất:</th>
                        <td>${equipment.nam_san_xuat || 'Không xác định'}</td>
                    </tr>
                    <tr>
                        <th>Công suất:</th>
                        <td>${equipment.cong_suat || 'Không xác định'}</td>
                    </tr>
                </table>
                
                <div class="mt-3">
                    <button class="btn btn-primary btn-sm" onclick="viewFullDetail(${equipment.id})">
                        <i class="fas fa-eye me-2"></i>Xem chi tiết đầy đủ
                    </button>
                </div>
            </div>
            <div class="col-md-4 text-center">
                ${equipment.hinh_anh ? 
                    `<img src="../../${equipment.hinh_anh}" class="img-fluid rounded shadow" alt="Hình ảnh thiết bị" style="max-height: 200px;">` : 
                    '<div class="bg-light rounded p-4"><i class="fas fa-image fa-3x text-muted"></i><br><small class="text-muted">Không có hình ảnh</small></div>'
                }
                
                <div class="mt-3">
                    <small class="text-muted">Lần quét: ${new Date().toLocaleString('vi-VN')}</small>
                </div>
            </div>
        </div>
    `);
}

function displayNotFound(equipmentId) {
    $('#equipmentInfo').html(`
        <div class="text-center py-5">
            <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
            <h5>Không tìm thấy thiết bị</h5>
            <p class="text-muted">ID thiết bị "<strong>${equipmentId}</strong>" không tồn tại trong hệ thống</p>
            <div class="mt-3">
                <button class="btn btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Thử lại
                </button>
                <a href="../equipment/add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Thêm thiết bị mới
                </a>
            </div>
        </div>
    `);
}

function displayError(equipmentId) {
    $('#equipmentInfo').html(`
        <div class="text-center py-5">
            <i class="fas fa-times-circle fa-4x text-danger mb-3"></i>
            <h5>Lỗi kết nối</h5>
            <p class="text-muted">Không thể tìm kiếm thiết bị "${equipmentId}". Vui lòng thử lại</p>
            <div class="mt-3">
                <button class="btn btn-outline-primary" onclick="searchEquipmentById('${equipmentId}')">
                    <i class="fas fa-sync-alt me-2"></i>Thử lại
                </button>
            </div>
        </div>
    `);
}

function getStatusClass(status) {
    const classes = {
        'hoat_dong': 'bg-success',
        'bao_tri': 'bg-warning',
        'hong': 'bg-danger',
        'ngung_hoat_dong': 'bg-secondary'
    };
    return classes[status] || 'bg-secondary';
}

function getStatusText(status) {
    const texts = {
        'hoat_dong': 'Hoạt động',
        'bao_tri': 'Bảo trì',
        'hong': 'Hỏng',
        'ngung_hoat_dong': 'Ngừng hoạt động'
    };
    return texts[status] || 'Không xác định';
}

function addToScanHistory(equipmentId) {
    const now = new Date();
    const historyItem = {
        id: equipmentId,
        timestamp: now.getTime(),
        timeString: now.toLocaleString('vi-VN')
    };
    
    // Remove if already exists
    scanHistory = scanHistory.filter(item => item.id !== equipmentId);
    
    // Add to beginning
    scanHistory.unshift(historyItem);
    
    // Keep only last 10 items
    if (scanHistory.length > 10) {
        scanHistory = scanHistory.slice(0, 10);
    }
    
    // Save to localStorage
    localStorage.setItem('qr_scan_history', JSON.stringify(scanHistory));
    
    // Update display
    displayScanHistory();
}

function loadScanHistory() {
    const saved = localStorage.getItem('qr_scan_history');
    if (saved) {
        scanHistory = JSON.parse(saved);
        displayScanHistory();
    }
}

function displayScanHistory() {
    if (scanHistory.length === 0) {
        $('#scanHistory').html('<p class="text-muted text-center">Chưa có lịch sử quét</p>');
        return;
    }
    
    let html = '<div class="list-group list-group-flush">';
    scanHistory.forEach(item => {
        html += `
            <div class="list-group-item list-group-item-action p-2" onclick="searchEquipmentById('${item.id}')">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="text-primary">${item.id}</strong><br>
                        <small class="text-muted">${item.timeString}</small>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    $('#scanHistory').html(html);
}

function clearHistory() {
    CMMS.confirm('Bạn có chắc chắn muốn xóa lịch sử quét?').then((result) => {
        if (result.isConfirmed) {
            scanHistory = [];
            localStorage.removeItem('qr_scan_history');
            displayScanHistory();
            CMMS.showAlert('Đã xóa lịch sử quét', 'success');
        }
    });
}

function viewFullDetail(equipmentId) {
    CMMS.ajax('../equipment/api.php', {
        method: 'GET',
        data: { action: 'detail', id: equipmentId }
    }).then(response => {
        if (response.success) {
            $('#equipmentDetailContent').html(generateDetailHTML(response.data));
            $('#equipmentDetailModal').modal('show');
        } else {
            CMMS.showAlert('Không thể tải thông tin chi tiết', 'error');
        }
    });
}

function generateDetailHTML(data) {
    return `
        <div class="row">
            <div class="col-md-8">
                <table class="table table-borderless">
                    <tr><th width="30%">ID thiết bị:</th><td><strong>${data.id_thiet_bi}</strong></td></tr>
                    <tr><th>Tên thiết bị:</th><td>${data.ten_thiet_bi}</td></tr>
                    <tr><th>Vị trí:</th><td>${data.ten_xuong} - ${data.ten_line} - ${data.vi_tri || ''}</td></tr>
                    <tr><th>Khu vực:</th><td>${data.ten_khu_vuc || ''}</td></tr>
                    <tr><th>Dòng máy:</th><td>${data.ten_dong_may || ''}</td></tr>
                    <tr><th>Ngành:</th><td>${data.nganh || ''}</td></tr>
                    <tr><th>Năm sản xuất:</th><td>${data.nam_san_xuat || ''}</td></tr>
                    <tr><th>Công suất:</th><td>${data.cong_suat || ''}</td></tr>
                    <tr><th>Tình trạng:</th><td><span class="badge ${getStatusClass(data.tinh_trang)}">${getStatusText(data.tinh_trang)}</span></td></tr>
                    <tr><th>Người chủ quản:</th><td>${data.chu_quan_name || 'Chưa phân công'}</td></tr>
                    <tr><th>Thông số kỹ thuật:</th><td>${data.thong_so_ky_thuat || ''}</td></tr>
                    <tr><th>Ghi chú:</th><td>${data.ghi_chu || ''}</td></tr>
                    <tr><th>Ngày tạo:</th><td>${CMMS.formatDate(data.created_at)}</td></tr>
                </table>
            </div>
            <div class="col-md-4 text-center">
                ${data.hinh_anh ? 
                    `<img src="../../${data.hinh_anh}" class="img-fluid rounded" alt="Hình ảnh thiết bị">` : 
                    '<div class="text-center text-muted"><i class="fas fa-image fa-3x"></i><br>Không có hình ảnh</div>'
                }
            </div>
        </div>
    `;
}

// Quick Actions
function viewMaintenanceHistory() {
    if (!currentEquipmentId) return;
    window.open(`../maintenance/history.php?equipment_id=${currentEquipmentId}`, '_blank');
}

function createMaintenancePlan() {
    if (!currentEquipmentId) return;
    window.open(`../maintenance/add.php?equipment_id=${currentEquipmentId}`, '_blank');
}

function reportIssue() {
    if (!currentEquipmentId) return;
    window.open(`../tasks/add.php?equipment_id=${currentEquipmentId}&type=sua_chua_gap`, '_blank');
}

function viewBOM() {
    if (!currentEquipmentId) return;
    window.open(`../bom/index.php?equipment_id=${currentEquipmentId}`, '_blank');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
