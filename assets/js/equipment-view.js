/**
 * Equipment View JavaScript
 * File: /assets/js/equipment-view.js
 * Handles all JavaScript functionality for equipment view page
 */

const EquipmentView = {
    // Configuration and data
    data: null,
    config: {
        maxImageSize: 5 * 1024 * 1024, // 5MB
        maxDocumentSize: 10 * 1024 * 1024, // 10MB
        allowedImageTypes: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        allowedDocumentTypes: ['pdf', 'doc', 'docx']
    },
    
    // State management
    state: {
        isInitialized: false,
        currentImageIndex: 0,
        isLoading: false
    },
    
    /**
     * Initialize the equipment view
     */
    init: function() {
        if (this.state.isInitialized) return;
        
        console.log('Initializing Equipment View...');
        
        // Load data from global variable
        if (window.equipmentViewData) {
            this.data = window.equipmentViewData;
        } else {
            console.error('Equipment view data not found');
            return;
        }
        
        // Initialize components
        this.initializeEventListeners();
        this.initializeImageGallery();
        this.initializeTooltips();
        this.initializeKeyboardShortcuts();
        this.initializeCopyToClipboard();
        this.setupModalHandlers();
        
        this.state.isInitialized = true;
        console.log('Equipment View initialized successfully');
    },
    
    /**
     * Initialize event listeners
     */
    initializeEventListeners: function() {
        // Status indicator click
        const statusIndicators = document.querySelectorAll('.status-indicator[onclick]');
        statusIndicators.forEach(indicator => {
            indicator.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.data.permissions.canEdit) {
                    this.changeStatus();
                }
            });
        });
        
        // Image thumbnails
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('image-thumbnail')) {
                this.switchMainImage(e.target.src);
            }
        });
        
        // Maintenance alerts auto-hide
        this.setupMaintenanceAlerts();
    },
    
    /**
     * Initialize image gallery
     */
    initializeImageGallery: function() {
        const thumbnails = document.querySelectorAll('.image-thumbnail');
        const mainImage = document.querySelector('.main-equipment-image');
        
        if (!mainImage || thumbnails.length === 0) return;
        
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', () => {
                // Update main image
                mainImage.src = thumbnail.src;
                
                // Update active thumbnail
                thumbnails.forEach(t => t.classList.remove('active'));
                thumbnail.classList.add('active');
                
                this.state.currentImageIndex = index;
            });
        });
        
        // Add keyboard navigation for images
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            if (thumbnails.length > 1) {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.navigateImage(-1);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.navigateImage(1);
                }
            }
        });
    },
    
    /**
     * Navigate through images
     */
    navigateImage: function(direction) {
        const thumbnails = document.querySelectorAll('.image-thumbnail');
        if (thumbnails.length === 0) return;
        
        this.state.currentImageIndex += direction;
        
        if (this.state.currentImageIndex < 0) {
            this.state.currentImageIndex = thumbnails.length - 1;
        } else if (this.state.currentImageIndex >= thumbnails.length) {
            this.state.currentImageIndex = 0;
        }
        
        thumbnails[this.state.currentImageIndex].click();
    },
    
    /**
     * Switch main image
     */
    switchMainImage: function(src) {
        const mainImage = document.querySelector('.main-equipment-image');
        if (mainImage) {
            // Add loading effect
            mainImage.style.opacity = '0.5';
            
            // Create new image to preload
            const newImg = new Image();
            newImg.onload = () => {
                mainImage.src = src;
                mainImage.style.opacity = '1';
            };
            newImg.src = src;
        }
    },
    
    /**
     * Initialize tooltips
     */
    initializeTooltips: function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"], [title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            // Add title as data-bs-title for Bootstrap 5
            if (tooltipTriggerEl.title && !tooltipTriggerEl.getAttribute('data-bs-title')) {
                tooltipTriggerEl.setAttribute('data-bs-title', tooltipTriggerEl.title);
                tooltipTriggerEl.setAttribute('data-bs-toggle', 'tooltip');
            }
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Add contextual help tooltips
        this.addContextualTooltips();
    },
    
    /**
     * Add contextual help tooltips
     */
    addContextualTooltips: function() {
        const helpTooltips = [
            {
                selector: '.criticality-badge',
                title: 'Mức độ quan trọng của thiết bị ảnh hưởng đến ưu tiên bảo trì và thời gian phản hồi khi có sự cố'
            },
            {
                selector: '.status-indicator',
                title: 'Trạng thái hiện tại của thiết bị. Click để thay đổi nếu có quyền'
            },
            {
                selector: '.maintenance-alert',
                title: 'Cảnh báo bảo trì dựa trên chu kỳ bảo trì đã thiết lập'
            },
            {
                selector: '.timeline-item',
                title: 'Lịch sử bảo trì của thiết bị theo thời gian'
            }
        ];

        helpTooltips.forEach(({ selector, title }) => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                if (!element.getAttribute('title') && !element.getAttribute('data-bs-title')) {
                    element.setAttribute('data-bs-toggle', 'tooltip');
                    element.setAttribute('data-bs-placement', 'top');
                    element.setAttribute('data-bs-title', title);
                    new bootstrap.Tooltip(element);
                }
            });
        });
    },
    
    /**
     * Initialize keyboard shortcuts
     */
    initializeKeyboardShortcuts: function() {
        document.addEventListener('keydown', (e) => {
            // Skip if typing in input fields
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }
            
            if (e.ctrlKey || e.metaKey) {
                switch(e.key.toLowerCase()) {
                    case 'e':
                        e.preventDefault();
                        if (this.data.permissions.canEdit) {
                            window.location.href = this.data.urls.editUrl;
                        }
                        break;
                    case 'p':
                        e.preventDefault();
                        this.printEquipment();
                        break;
                    case 's':
                        e.preventDefault();
                        if (this.data.permissions.canEdit) {
                            this.changeStatus();
                        }
                        break;
                    case 'm':
                        e.preventDefault();
                        this.createMaintenanceSchedule();
                        break;
                    case 'q':
                        e.preventDefault();
                        this.generateQR();
                        break;
                }
            }
            
            // ESC key to close modals
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                });
            }
        });
    },
    
    /**
     * Initialize copy to clipboard functionality
     */
    initializeCopyToClipboard: function() {
        const equipmentCodeBadge = document.querySelector('.equipment-code-badge');
        if (equipmentCodeBadge) {
            equipmentCodeBadge.addEventListener('click', () => {
                const code = equipmentCodeBadge.textContent.trim();
                this.copyToClipboard(code, 'Đã copy mã thiết bị: ' + code);
                
                // Visual feedback
                equipmentCodeBadge.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    equipmentCodeBadge.style.transform = '';
                }, 150);
            });
        }
    },
    
    /**
     * Setup modal event handlers
     */
    setupModalHandlers: function() {
        // Status modal
        const statusModal = document.getElementById('statusModal');
        if (statusModal) {
            statusModal.addEventListener('shown.bs.modal', () => {
                document.getElementById('newStatus').focus();
            });
        }
        
        // File upload modal
        const fileUploadModal = document.getElementById('fileUploadModal');
        if (fileUploadModal) {
            fileUploadModal.addEventListener('shown.bs.modal', () => {
                document.getElementById('fileType').focus();
            });
        }
        
        // Maintenance modal
        const maintenanceModal = document.getElementById('maintenanceModal');
        if (maintenanceModal) {
            maintenanceModal.addEventListener('shown.bs.modal', () => {
                // Set default date to tomorrow
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(8, 0, 0, 0);
                
                const dateInput = document.getElementById('maintenanceDate');
                if (dateInput) {
                    dateInput.value = tomorrow.toISOString().slice(0, 16);
                }
                
                document.getElementById('maintenanceType').focus();
            });
        }
    },
    
    /**
     * Setup maintenance alerts
     */
    setupMaintenanceAlerts: function() {
        const alerts = document.querySelectorAll('.maintenance-alert');
        alerts.forEach(alert => {
            // Add close button if not exists
            if (!alert.querySelector('.btn-close')) {
                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'btn-close ms-auto';
                closeBtn.setAttribute('aria-label', 'Close');
                closeBtn.onclick = function() {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                };
                alert.appendChild(closeBtn);
            }
        });
    },
    
    /**
     * Show image modal
     */
    showImageModal: function(src) {
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        const modalImage = document.getElementById('modalImage');
        const downloadLink = document.getElementById('downloadImage');
        
        if (modalImage && downloadLink) {
            modalImage.src = src;
            modalImage.alt = this.data.equipment.name;
            downloadLink.href = src;
            downloadLink.download = `${this.data.equipment.code}-image.jpg`;
            
            modal.show();
        }
    },
    
    /**
     * Change equipment status
     */
    changeStatus: function() {
        if (!this.data.permissions.canEdit) {
            this.showToast('Bạn không có quyền thay đổi trạng thái thiết bị', 'error');
            return;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    },
    
    /**
     * Update equipment status
     */
    updateStatus: async function() {
        const form = document.getElementById('statusForm');
        const formData = new FormData(form);
        
        try {
            this.showLoading(true);
            
            formData.append('action', 'toggle_status');
            formData.append('id', this.data.equipmentId);
            
            const response = await fetch(this.data.urls.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast(result.message || 'Cập nhật trạng thái thành công', 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
                if (modal) modal.hide();
                
                // Refresh page to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.showToast(result.message || 'Lỗi khi cập nhật trạng thái', 'error');
            }
        } catch (error) {
            console.error('Update status error:', error);
            this.showToast('Lỗi kết nối. Vui lòng thử lại.', 'error');
        } finally {
            this.showLoading(false);
        }
    },
    
    /**
     * Generate QR Code
     */
    generateQR: function() {
        const equipmentUrl = `${window.location.origin}${window.location.pathname}?id=${this.data.equipmentId}`;
        const qrCodeUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(equipmentUrl)}`;
        
        const qrContainer = document.getElementById('qrCodeContainer');
        if (qrContainer) {
            qrContainer.innerHTML = `
                <img src="${qrCodeUrl}" alt="QR Code" style="width: 150px; height: 150px; border-radius: 0.375rem;" class="mb-2">
                <div class="mb-2">
                    <small class="text-muted">Quét để xem thiết bị</small>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="${qrCodeUrl}" download="equipment-${this.data.equipmentId}-qr.png" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download me-1"></i>Tải QR
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="EquipmentView.copyToClipboard('${equipmentUrl}', 'Đã copy link thiết bị')">
                        <i class="fas fa-link me-1"></i>Copy link
                    </button>
                </div>
            `;
            
            this.showToast('Đã tạo mã QR thành công', 'success');
        }
    },
    
    /**
     * Create maintenance schedule
     */
    createMaintenanceSchedule: function() {
        const modal = new bootstrap.Modal(document.getElementById('maintenanceModal'));
        modal.show();
    },
    
    /**
     * Confirm create maintenance schedule
     */
    createMaintenanceScheduleConfirm: function() {
        // This is a placeholder - would integrate with maintenance module
        this.showToast('Chức năng lên lịch bảo trì đang được phát triển', 'info');
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('maintenanceModal'));
        if (modal) modal.hide();
    },
    
    /**
     * View maintenance history
     */
    viewMaintenanceHistory: function() {
        this.showToast('Chức năng xem lịch sử bảo trì đang được phát triển', 'info');
    },
    
    /**
     * View full maintenance history
     */
    viewFullMaintenanceHistory: function() {
        this.showToast('Chức năng xem đầy đủ lịch sử bảo trì đang được phát triển', 'info');
    },
    
    /**
     * Print equipment information
     */
    printEquipment: function() {
        // Hide elements that shouldn't be printed
        const elementsToHide = document.querySelectorAll('.btn, .dropdown, .modal, [onclick]');
        const originalStyles = [];
        
        elementsToHide.forEach((el, index) => {
            originalStyles[index] = el.style.display;
            el.style.display = 'none';
        });
        
        // Add print-specific styles
        const printStyles = document.createElement('style');
        printStyles.innerHTML = `
            @media print {
                .equipment-view-container { background: white !important; }
                .equipment-header { background: white !important; color: black !important; }
                .info-section { box-shadow: none !important; border: 1px solid #ddd !important; }
                .maintenance-alert { display: none !important; }
                body * { color: black !important; }
                .badge { background: white !important; border: 1px solid #ddd !important; }
            }
        `;
        document.head.appendChild(printStyles);
        
        // Print
        window.print();
        
        // Restore original styles
        setTimeout(() => {
            elementsToHide.forEach((el, index) => {
                el.style.display = originalStyles[index];
            });
            document.head.removeChild(printStyles);
        }, 1000);
    },
    
    /**
     * Export equipment to PDF
     */
    exportEquipment: function() {
        this.showToast('Chức năng xuất PDF đang được phát triển', 'info');
    },
    
    /**
     * Upload files
     */
    uploadFiles: function() {
        const modal = new bootstrap.Modal(document.getElementById('fileUploadModal'));
        modal.show();
    },
    
    /**
     * Perform file upload
     */
    performFileUpload: async function() {
        const form = document.getElementById('fileUploadForm');
        const formData = new FormData(form);
        
        // Validate file
        const fileInput = document.getElementById('uploadFile');
        const file = fileInput.files[0];
        
        if (!file) {
            this.showToast('Vui lòng chọn file', 'error');
            return;
        }
        
        // Check file size and type
        const fileType = document.getElementById('fileType').value;
        if (fileType === 'image') {
            if (file.size > this.config.maxImageSize) {
                this.showToast('File ảnh quá lớn (tối đa 5MB)', 'error');
                return;
            }
            
            const extension = file.name.split('.').pop().toLowerCase();
            if (!this.config.allowedImageTypes.includes(extension)) {
                this.showToast('Định dạng ảnh không được hỗ trợ', 'error');
                return;
            }
        } else {
            if (file.size > this.config.maxDocumentSize) {
                this.showToast('File tài liệu quá lớn (tối đa 10MB)', 'error');
                return;
            }
            
            const extension = file.name.split('.').pop().toLowerCase();
            if (!this.config.allowedDocumentTypes.includes(extension)) {
                this.showToast('Định dạng tài liệu không được hỗ trợ', 'error');
                return;
            }
        }
        
        try {
            this.showLoading(true);
            
            formData.append('action', 'upload_file');
            formData.append('equipment_id', this.data.equipmentId);
            
            // This would be handled by a file upload API
            // const response = await fetch(this.data.urls.uploadUrl, {
            //     method: 'POST',
            //     body: formData
            // });
            
            // For now, show placeholder message
            this.showToast('Chức năng upload file đang được phát triển', 'info');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('fileUploadModal'));
            if (modal) modal.hide();
            
        } catch (error) {
            console.error('Upload error:', error);
            this.showToast('Lỗi khi upload file', 'error');
        } finally {
            this.showLoading(false);
        }
    },
    
    /**
     * Delete equipment
     */
    deleteEquipment: function() {
        if (!this.data.permissions.canDelete) {
            this.showToast('Bạn không có quyền xóa thiết bị', 'error');
            return;
        }
        
        const message = `Bạn có chắc chắn muốn xóa thiết bị "${this.data.equipment.name}"?\n\nHành động này sẽ xóa:\n• Tất cả thông tin thiết bị\n• Lịch sử bảo trì\n• File đính kèm\n\nVà không thể hoàn tác.`;
        
        if (confirm(message)) {
            this.performDelete();
        }
    },
    
    /**
     * Perform delete operation
     */
    performDelete: async function() {
        try {
            this.showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', this.data.equipmentId);
            
            const response = await fetch(this.data.urls.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast(result.message || 'Xóa thiết bị thành công', 'success');
                
                // Redirect to equipment list after successful deletion
                setTimeout(() => {
                    window.location.href = this.data.urls.listUrl;
                }, 2000);
            } else {
                this.showToast(result.message || 'Lỗi khi xóa thiết bị', 'error');
            }
        } catch (error) {
            console.error('Delete error:', error);
            this.showToast('Lỗi kết nối. Vui lòng thử lại.', 'error');
        } finally {
            this.showLoading(false);
        }
    },
    
    /**
     * Utility functions
     */
    
    showLoading: function(show = true) {
        if (window.CMMS && window.CMMS.showLoading) {
            if (show) {
                CMMS.showLoading();
            } else {
                CMMS.hideLoading();
            }
        } else {
            // Fallback loading implementation
            this.state.isLoading = show;
            const buttons = document.querySelectorAll('button:not([data-bs-dismiss])');
            buttons.forEach(btn => {
                btn.disabled = show;
                if (show && !btn.dataset.originalHtml) {
                    btn.dataset.originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + btn.textContent.trim();
                } else if (!show && btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                    delete btn.dataset.originalHtml;
                }
            });
        }
    },
    
    showToast: function(message, type = 'info') {
        if (window.CMMS && window.CMMS.showToast) {
            CMMS.showToast(message, type);
        } else {
            // Fallback toast implementation
            console.log(`[${type.toUpperCase()}] ${message}`);
            alert(message);
        }
    },
    
    copyToClipboard: function(text, successMessage = 'Đã copy thành công') {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                this.showToast(successMessage, 'success');
            }).catch(err => {
                console.error('Copy failed:', err);
                this.fallbackCopyToClipboard(text, successMessage);
            });
        } else {
            this.fallbackCopyToClipboard(text, successMessage);
        }
    },
    
    fallbackCopyToClipboard: function(text, successMessage) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            this.showToast(successMessage, 'success');
        } catch (err) {
            console.error('Fallback copy failed:', err);
            this.showToast('Không thể copy. Vui lòng copy thủ công.', 'error');
        }
        
        document.body.removeChild(textArea);
    }
};

// Global functions for HTML onclick attributes
function showImageModal(src) {
    EquipmentView.showImageModal(src);
}

function changeStatus() {
    EquipmentView.changeStatus();
}

function updateStatus() {
    EquipmentView.updateStatus();
}

function generateQR() {
    EquipmentView.generateQR();
}

function createMaintenanceSchedule() {
    EquipmentView.createMaintenanceSchedule();
}

function createMaintenanceScheduleConfirm() {
    EquipmentView.createMaintenanceScheduleConfirm();
}

function viewMaintenanceHistory() {
    EquipmentView.viewMaintenanceHistory();
}

function viewFullMaintenanceHistory() {
    EquipmentView.viewFullMaintenanceHistory();
}

function printEquipment() {
    EquipmentView.printEquipment();
}

function exportEquipment() {
    EquipmentView.exportEquipment();
}

function uploadFiles() {
    EquipmentView.uploadFiles();
}

function performFileUpload() {
    EquipmentView.performFileUpload();
}

function deleteEquipment() {
    EquipmentView.deleteEquipment();
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EquipmentView;
}

// Auto-initialize if jQuery/DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure all resources are loaded
    setTimeout(() => {
        if (typeof window.equipmentViewData !== 'undefined') {
            EquipmentView.init();
        }
    }, 100);
});

console.log('Equipment View JavaScript loaded successfully');