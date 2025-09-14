/**
 * Equipment View JavaScript - Settings Slider và Helper Functions
 * assets/js/equipment-view.js (Updated)
 */

// Settings Slider State
let settingsSliderState = {
    currentSlide: 0,
    totalSlides: 0,
    isAnimating: false,
    autoSlideInterval: null,
    touchStartX: 0,
    touchEndX: 0
};

/**
 * Initialize Settings Slider
 */
function initializeSettingsSlider() {
    const slider = document.getElementById('settingsSlider');
    if (!slider) return;
    
    const slides = slider.querySelectorAll('.settings-slide');
    settingsSliderState.totalSlides = slides.length;
    
    if (settingsSliderState.totalSlides <= 1) return;
    
    // Set initial width
    updateSliderWidth();
    
    // Add touch events for mobile
    addTouchEvents();
    
    // Add keyboard navigation
    addKeyboardNavigation();
    
    // Auto-slide every 5 seconds (optional)
    // startAutoSlide();
    
    console.log('Settings slider initialized with', settingsSliderState.totalSlides, 'slides');
}

/**
 * Update slider width based on slide count
 */
function updateSliderWidth() {
    const slider = document.getElementById('settingsSlider');
    if (!slider) return;
    
    const slideWidth = 300 + 16; // 300px + 1rem gap
    const visibleSlides = Math.floor(slider.parentElement.offsetWidth / slideWidth);
    const maxVisible = Math.min(visibleSlides, settingsSliderState.totalSlides);
    
    // Show indicators only if more slides than visible
    const indicators = document.querySelector('.slider-indicators');
    const navButtons = document.querySelectorAll('.slider-nav');
    
    if (settingsSliderState.totalSlides > maxVisible) {
        if (indicators) indicators.style.display = 'block';
        navButtons.forEach(btn => btn.style.display = 'flex');
    } else {
        if (indicators) indicators.style.display = 'none';
        navButtons.forEach(btn => btn.style.display = 'none');
    }
}

/**
 * Slide to specific position
 */
function slideSettings(direction) {
    if (settingsSliderState.isAnimating) return;
    
    const slider = document.getElementById('settingsSlider');
    if (!slider) return;
    
    settingsSliderState.isAnimating = true;
    
    if (direction === 'next') {
        settingsSliderState.currentSlide = 
            (settingsSliderState.currentSlide + 1) % settingsSliderState.totalSlides;
    } else if (direction === 'prev') {
        settingsSliderState.currentSlide = 
            settingsSliderState.currentSlide === 0 
                ? settingsSliderState.totalSlides - 1 
                : settingsSliderState.currentSlide - 1;
    }
    
    updateSliderPosition();
    updateIndicators();
    
    setTimeout(() => {
        settingsSliderState.isAnimating = false;
    }, 300);
}

/**
 * Go to specific slide
 */
function goToSlide(index) {
    if (settingsSliderState.isAnimating || 
        index < 0 || 
        index >= settingsSliderState.totalSlides ||
        index === settingsSliderState.currentSlide) return;
    
    settingsSliderState.isAnimating = true;
    settingsSliderState.currentSlide = index;
    
    updateSliderPosition();
    updateIndicators();
    
    setTimeout(() => {
        settingsSliderState.isAnimating = false;
    }, 300);
}

/**
 * Update slider transform position
 */
function updateSliderPosition() {
    const slider = document.getElementById('settingsSlider');
    if (!slider) return;
    
    const slideWidth = 316; // 300px + 16px gap
    const translateX = -(settingsSliderState.currentSlide * slideWidth);
    
    slider.style.transform = `translateX(${translateX}px)`;
}

/**
 * Update indicator dots
 */
function updateIndicators() {
    const dots = document.querySelectorAll('.indicator-dot');
    dots.forEach((dot, index) => {
        if (index === settingsSliderState.currentSlide) {
            dot.classList.add('active');
        } else {
            dot.classList.remove('active');
        }
    });
}

/**
 * Add touch events for mobile swipe
 */
function addTouchEvents() {
    const container = document.querySelector('.settings-slider-container');
    if (!container) return;
    
    container.addEventListener('touchstart', handleTouchStart, { passive: true });
    container.addEventListener('touchmove', handleTouchMove, { passive: true });
    container.addEventListener('touchend', handleTouchEnd, { passive: true });
}

function handleTouchStart(e) {
    settingsSliderState.touchStartX = e.touches[0].clientX;
}

function handleTouchMove(e) {
    if (!settingsSliderState.touchStartX) return;
    settingsSliderState.touchEndX = e.touches[0].clientX;
}

function handleTouchEnd(e) {
    if (!settingsSliderState.touchStartX || !settingsSliderState.touchEndX) return;
    
    const diff = settingsSliderState.touchStartX - settingsSliderState.touchEndX;
    const threshold = 50; // Minimum swipe distance
    
    if (Math.abs(diff) > threshold) {
        if (diff > 0) {
            slideSettings('next');
        } else {
            slideSettings('prev');
        }
    }
    
    // Reset touch positions
    settingsSliderState.touchStartX = 0;
    settingsSliderState.touchEndX = 0;
}

/**
 * Add keyboard navigation
 */
function addKeyboardNavigation() {
    document.addEventListener('keydown', function(e) {
        // Only work when not typing in inputs
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        const container = document.querySelector('.settings-slider-container');
        if (!container || !isElementInViewport(container)) return;
        
        switch(e.key) {
            case 'ArrowLeft':
                e.preventDefault();
                slideSettings('prev');
                break;
            case 'ArrowRight':
                e.preventDefault();
                slideSettings('next');
                break;
            case ' ': // Spacebar
                e.preventDefault();
                slideSettings('next');
                break;
        }
    });
}

/**
 * Auto-slide functionality (optional)
 */
function startAutoSlide() {
    if (settingsSliderState.totalSlides <= 1) return;
    
    settingsSliderState.autoSlideInterval = setInterval(() => {
        if (!settingsSliderState.isAnimating) {
            slideSettings('next');
        }
    }, 5000);
}

function stopAutoSlide() {
    if (settingsSliderState.autoSlideInterval) {
        clearInterval(settingsSliderState.autoSlideInterval);
        settingsSliderState.autoSlideInterval = null;
    }
}

/**
 * Image Viewer Functions
 */
function showImageViewer(index) {
    const images = window.equipmentViewData.settingsImages;
    if (!images || !images[index]) return;
    
    const image = images[index];
    
    // Create modal if doesn't exist
    let modal = document.getElementById('imageViewerModal');
    if (!modal) {
        modal = createImageViewerModal();
        document.body.appendChild(modal);
    }
    
    // Update modal content
    document.getElementById('viewerImage').src = image.image_url;
    document.getElementById('viewerImageTitle').textContent = image.title || 'Hình ảnh thông số';
    document.getElementById('viewerImageDescription').textContent = image.description || '';
    document.getElementById('downloadImageBtn').href = image.image_url;
    document.getElementById('downloadImageBtn').download = 
        (image.title || 'settings_image').replace(/[^a-z0-9]/gi, '_') + '.jpg';
    
    // Show modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // Add navigation if multiple images
    if (images.length > 1) {
        addImageNavigation(index, images);
    }
}

/**
 * Create image viewer modal
 */
function createImageViewerModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'imageViewerModal';
    modal.tabIndex = -1;
    
    modal.innerHTML = `
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewerImageTitle">Hình ảnh</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="viewerImage" src="" alt="" class="img-fluid" style="max-height: 80vh;">
                    <div id="viewerImageDescription" class="p-3 text-muted"></div>
                </div>
                <div class="modal-footer">
                    <div id="imageNavigation" class="me-auto d-none">
                        <button type="button" class="btn btn-outline-secondary" onclick="navigateImage(-1)">
                            <i class="fas fa-chevron-left me-1"></i>Trước
                        </button>
                        <span id="imageCounter" class="mx-3 text-muted"></span>
                        <button type="button" class="btn btn-outline-secondary" onclick="navigateImage(1)">
                            Sau<i class="fas fa-chevron-right ms-1"></i>
                        </button>
                    </div>
                    <a id="downloadImageBtn" href="" download class="btn btn-success">
                        <i class="fas fa-download me-1"></i>Tải xuống
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    `;
    
    return modal;
}

/**
 * Add image navigation in viewer
 */
let currentViewerIndex = 0;

function addImageNavigation(index, images) {
    currentViewerIndex = index;
    const navigation = document.getElementById('imageNavigation');
    const counter = document.getElementById('imageCounter');
    
    if (navigation && counter) {
        navigation.classList.remove('d-none');
        counter.textContent = `${index + 1} / ${images.length}`;
    }
}

function navigateImage(direction) {
    const images = window.equipmentViewData.settingsImages;
    if (!images || images.length <= 1) return;
    
    currentViewerIndex += direction;
    
    if (currentViewerIndex < 0) {
        currentViewerIndex = images.length - 1;
    } else if (currentViewerIndex >= images.length) {
        currentViewerIndex = 0;
    }
    
    showImageViewer(currentViewerIndex);
}

/**
 * Settings Upload Modal
 */
function showSettingsUploadModal() {
    if (!window.equipmentViewData.permissions.canEdit) {
        showNotification('Bạn không có quyền thêm hình ảnh', 'warning');
        return;
    }
    
    // Create or show upload modal
    let modal = document.getElementById('settingsUploadModal');
    if (!modal) {
        modal = createSettingsUploadModal();
        document.body.appendChild(modal);
    }
    
    // Reset form
    document.getElementById('settingsUploadForm').reset();
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

/**
 * Create settings upload modal
 */
function createSettingsUploadModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'settingsUploadModal';
    modal.tabIndex = -1;
    
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-images me-2"></i>Thêm hình ảnh thông số
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="settingsUploadForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Tiêu đề:</label>
                                    <input type="text" class="form-control" name="title" 
                                           placeholder="VD: Bảng điều khiển chính" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Danh mục:</label>
                                    <select class="form-select" name="category">
                                        <option value="general">Tổng quát</option>
                                        <option value="electrical">Điện</option>
                                        <option value="mechanical">Cơ khí</option>
                                        <option value="software">Phần mềm</option>
                                        <option value="safety">An toàn</option>
                                        <option value="maintenance">Bảo trì</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả:</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Mô tả chi tiết về hình ảnh..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Chọn hình ảnh:</label>
                            <div class="upload-area" onclick="document.getElementById('imageFile').click()">
                                <input type="file" id="imageFile" name="image" accept="image/*" 
                                       class="d-none" onchange="previewImage(this)" required>
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                </div>
                                <div>
                                    <strong>Click để chọn hình ảnh</strong><br>
                                    hoặc kéo thả file vào đây
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    Chấp nhận: JPG, PNG, GIF, WEBP (tối đa 5MB)
                                </small>
                            </div>
                            <div id="imagePreview" class="mt-3 d-none">
                                <img id="previewImg" src="" alt="Preview" 
                                     style="max-width: 200px; max-height: 150px; object-fit: cover; border-radius: 8px;">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-primary" onclick="uploadSettingsImage()">
                        <i class="fas fa-save me-1"></i>Lưu hình ảnh
                    </button>
                </div>
            </div>
        </div>
    `;
    
    return modal;
}

/**
 * Preview uploaded image
 */
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.classList.remove('d-none');
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.classList.add('d-none');
    }
}

/**
 * Upload settings image
 */
async function uploadSettingsImage() {
    const form = document.getElementById('settingsUploadForm');
    const formData = new FormData(form);
    
    // Validation
    const title = formData.get('title').trim();
    const imageFile = formData.get('image');
    
    if (!title) {
        showNotification('Vui lòng nhập tiêu đề', 'warning');
        return;
    }
    
    if (!imageFile || imageFile.size === 0) {
        showNotification('Vui lòng chọn hình ảnh', 'warning');
        return;
    }
    
    if (imageFile.size > 5 * 1024 * 1024) {
        showNotification('File quá lớn. Tối đa 5MB', 'warning');
        return;
    }
    
    // Add equipment ID and action
    formData.append('action', 'upload');
    formData.append('equipment_id', window.equipmentViewData.equipmentId);
    
    try {
        showLoading(true);
        
        const response = await fetch(window.equipmentViewData.urls.settingsApi, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message || 'Upload thành công', 'success');
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('settingsUploadModal')).hide();
            
            // Reload page to show new image
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(result.message || 'Upload thất bại', 'error');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showNotification('Lỗi khi upload: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Utility Functions
 */

// Check if element is in viewport
function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
    document.querySelectorAll('.toast-notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `toast-notification alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060;
        min-width: 300px;
        max-width: 400px;
        animation: slideInRight 0.3s ease;
    `;
    
    const icons = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    
    notification.innerHTML = `
        <i class="${icons[type] || icons.info} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 150);
        }
    }, 5000);
}

// Show/hide loading overlay
function showLoading(show = true) {
    let overlay = document.getElementById('loadingOverlay');
    
    if (show && !overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(2px);
        `;
        overlay.innerHTML = `
            <div class="spinner-border text-light" style="width: 3rem; height: 3rem;"></div>
        `;
        document.body.appendChild(overlay);
    } else if (!show && overlay) {
        overlay.remove();
    }
}

// Copy to clipboard
function copyEquipmentCode() {
    const code = window.equipmentViewData.equipment.code;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(() => {
            showNotification(`Đã copy mã thiết bị: ${code}`, 'success');
        });
    } else {
        // Fallback
        const textArea = document.createElement('textarea');
        textArea.value = code;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification(`Đã copy mã thiết bị: ${code}`, 'success');
    }
}

// Print equipment
function printEquipment() {
    window.print();
}

// Generate QR code
// Thêm vào file assets/js/equipment-view.js

/**
 * Generate QR Code - SỬA CHỨC NĂNG
 */
function generateQR() {
    const equipment = window.equipmentViewData.equipment;
    const equipmentUrl = `${window.location.origin}${window.location.pathname}?id=${window.equipmentViewData.equipmentId}`;
    
    // Tạo QR code bằng API miễn phí
    const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&format=png&data=${encodeURIComponent(equipmentUrl)}`;
    
    const qrContainer = document.getElementById('qrCodeContainer');
    if (qrContainer) {
        // Hiển thị loading
        qrContainer.innerHTML = `
            <div class="d-flex justify-content-center mb-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Đang tạo QR...</span>
                </div>
            </div>
            <p class="text-muted small">Đang tạo mã QR...</p>
        `;
        
        // Tạo image element để kiểm tra QR load thành công
        const qrImage = new Image();
        qrImage.onload = function() {
            qrContainer.innerHTML = `
                <div class="qr-code-display mb-3">
                    <img src="${qrApiUrl}" alt="QR Code - ${equipment.name}" 
                         style="width: 200px; height: 200px; border: 1px solid #e5e7eb; border-radius: 8px;">
                </div>
                <div class="qr-info mb-3">
                    <small class="text-muted d-block">Thiết bị: <strong>${equipment.name}</strong></small>
                    <small class="text-muted d-block">Mã: <strong>${equipment.code}</strong></small>
                    <small class="text-muted d-block">Quét để xem chi tiết thiết bị</small>
                </div>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="${qrApiUrl}" download="QR_${equipment.code}.png" class="btn btn-sm btn-primary">
                        <i class="fas fa-download me-1"></i>Tải QR
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            onclick="copyQRLink()">
                        <i class="fas fa-link me-1"></i>Copy Link
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            onclick="printQR()">
                        <i class="fas fa-print me-1"></i>In QR
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" 
                            onclick="showQRFullscreen()">
                        <i class="fas fa-expand me-1"></i>Phóng to
                    </button>
                </div>
            `;
            
            showNotification('Đã tạo mã QR thành công', 'success');
        };
        
        qrImage.onerror = function() {
            qrContainer.innerHTML = `
                <div class="qr-placeholder mb-3">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                </div>
                <p class="text-muted small mb-3">Không thể tạo mã QR</p>
                <button type="button" class="btn btn-primary btn-sm" onclick="generateQR()">
                    <i class="fas fa-redo me-1"></i>Thử lại
                </button>
            `;
            showNotification('Lỗi khi tạo mã QR', 'error');
        };
        
        qrImage.src = qrApiUrl;
    }
}

/**
 * Copy QR link to clipboard
 */
function copyQRLink() {
    const equipmentUrl = `${window.location.origin}${window.location.pathname}?id=${window.equipmentViewData.equipmentId}`;
    copyToClipboard(equipmentUrl, 'Đã copy link thiết bị vào clipboard');
}

/**
 * Print QR code
 */
function printQR() {
    const equipment = window.equipmentViewData.equipment;
    const qrImg = document.querySelector('#qrCodeContainer img');
    
    if (!qrImg) {
        showNotification('Vui lòng tạo QR code trước khi in', 'warning');
        return;
    }
    
    // Tạo cửa sổ in mới
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR Code - ${equipment.name}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    margin: 20px;
                    background: white;
                }
                .qr-print-container {
                    border: 2px solid #333;
                    padding: 20px;
                    display: inline-block;
                    margin: 20px auto;
                }
                .qr-image {
                    width: 250px;
                    height: 250px;
                    margin: 10px;
                }
                .equipment-info {
                    margin-top: 15px;
                    font-size: 14px;
                }
                .equipment-name {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .equipment-code {
                    font-size: 16px;
                    color: #666;
                    margin-bottom: 10px;
                }
                @media print {
                    body { margin: 0; }
                    .qr-print-container { border: 2px solid #000; }
                }
            </style>
        </head>
        <body>
            <div class="qr-print-container">
                <img src="${qrImg.src}" alt="QR Code" class="qr-image">
                <div class="equipment-info">
                    <div class="equipment-name">${equipment.name}</div>
                    <div class="equipment-code">Mã: ${equipment.code}</div>
                    <div>Ngày in: ${new Date().toLocaleDateString('vi-VN')}</div>
                    <div>Quét QR để xem chi tiết thiết bị</div>
                </div>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Đợi load xong rồi in
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

/**
 * Show QR fullscreen
 */
function showQRFullscreen() {
    const qrImg = document.querySelector('#qrCodeContainer img');
    
    if (!qrImg) {
        showNotification('Vui lòng tạo QR code trước', 'warning');
        return;
    }
    
    // Tạo modal fullscreen
    let modal = document.getElementById('qrFullscreenModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'qrFullscreenModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-qrcode me-2"></i>Mã QR - ${window.equipmentViewData.equipment.name}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="fullscreenQRImage" src="${qrImg.src}" alt="QR Code" 
                             style="max-width: 100%; height: auto; max-height: 60vh;">
                        <div class="mt-3">
                            <p class="text-muted">Quét mã QR để truy cập thông tin thiết bị</p>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-primary" onclick="copyQRLink()">
                            <i class="fas fa-link me-1"></i>Copy Link
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="printQR()">
                            <i class="fas fa-print me-1"></i>In QR
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    } else {
        document.getElementById('fullscreenQRImage').src = qrImg.src;
    }
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

// Change status
function changeStatus() {
    showNotification('Chức năng thay đổi trạng thái đang được phát triển', 'info');
}

// Delete equipment
function deleteEquipment() {
    if (confirm('Bạn có chắc chắn muốn xóa thiết bị này?')) {
        showNotification('Chức năng xóa đang được phát triển', 'info');
    }
}

// Window resize handler
window.addEventListener('resize', function() {
    updateSliderWidth();
});

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initializeSettingsSlider();
});
// Thêm vào assets/js/equipment-view.js

/**
 * File Upload Functions
 */
let selectedFile = null;

function showFileUploadModal() {
    if (!window.equipmentViewData.permissions.canEdit) {
        showNotification('Bạn không có quyền upload file', 'warning');
        return;
    }
    
    let modal = document.getElementById('fileUploadModal');
    if (!modal) {
        modal = createFileUploadModal();
        document.body.appendChild(modal);
    }
    
    // Reset form
    document.getElementById('fileUploadForm').reset();
    selectedFile = null;
    updateFilePreview();
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

function createFileUploadModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fade upload-modal';
    modal.id = 'fileUploadModal';
    modal.tabIndex = -1;
    
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>Upload tài liệu
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="fileUploadForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Tên file:</label>
                                    <input type="text" class="form-control" name="display_name" 
                                           placeholder="VD: Hướng dẫn vận hành máy..." required>
                                    <div class="form-text">Tên hiển thị cho file (có thể khác tên file gốc)</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Loại file:</label>
                                    <select class="form-select" name="file_type" required>
                                        <option value="">Chọn loại</option>
                                        <option value="manual">Hướng dẫn sử dụng</option>
                                        <option value="document">Tài liệu</option>
                                        <option value="certificate">Chứng nhận</option>
                                        <option value="drawing">Bản vẽ kỹ thuật</option>
                                        <option value="other">Khác</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả:</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Mô tả ngắn về nội dung file..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phiên bản:</label>
                                    <input type="text" class="form-control" name="version" 
                                           placeholder="1.0" value="1.0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Trạng thái:</label>
                                    <select class="form-select" name="is_active">
                                        <option value="1">Hoạt động</option>
                                        <option value="0">Lưu trữ</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Chọn file:</label>
                            <div class="upload-area" onclick="document.getElementById('uploadFileInput').click()">
                                <input type="file" id="uploadFileInput" name="file" class="d-none" 
                                       onchange="handleFileSelect(this)" 
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.dwg">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt fa-2x"></i>
                                </div>
                                <div>
                                    <strong>Click để chọn file</strong><br>
                                    hoặc kéo thả file vào đây
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    Chấp nhận: PDF, DOC, DOCX, XLS, XLSX, TXT, JPG, PNG, DWG<br>
                                    Tối đa: 10MB
                                </small>
                            </div>
                            
                            <div id="filePreview" class="mt-3 d-none">
                                <div class="file-preview">
                                    <div class="file-preview-icon">
                                        <i id="previewIcon" class="fas fa-file"></i>
                                    </div>
                                    <div class="file-preview-info">
                                        <div id="previewName" class="file-preview-name"></div>
                                        <div id="previewSize" class="file-preview-size"></div>
                                    </div>
                                    <div class="file-preview-remove" onclick="removeSelectedFile()">
                                        <i class="fas fa-times"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-primary" onclick="uploadFile()">
                        <i class="fas fa-upload me-1"></i>Upload file
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Add drag and drop events
    const uploadArea = modal.querySelector('.upload-area');
    uploadArea.addEventListener('dragover', handleDragOver);
    uploadArea.addEventListener('dragleave', handleDragLeave);
    uploadArea.addEventListener('drop', handleDrop);
    
    return modal;
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        if (validateFile(file)) {
            selectedFile = file;
            updateFilePreview();
            
            // Auto-fill display name if empty
            const displayNameInput = document.querySelector('input[name="display_name"]');
            if (!displayNameInput.value.trim()) {
                const fileName = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
                displayNameInput.value = fileName;
            }
        } else {
            input.value = '';
            selectedFile = null;
            updateFilePreview();
        }
    }
}

function validateFile(file) {
    // Check file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
        showNotification('File quá lớn. Tối đa 10MB', 'warning');
        return false;
    }
    
    // Check file type
    const allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/dwg',
        'application/autocad'
    ];
    
    const fileExtension = file.name.split('.').pop().toLowerCase();
    const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'dwg'];
    
    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
        showNotification('Loại file không được hỗ trợ', 'warning');
        return false;
    }
    
    return true;
}

function updateFilePreview() {
    const preview = document.getElementById('filePreview');
    
    if (selectedFile) {
        const icon = getFileIconByName(selectedFile.name);
        const size = formatFileSize(selectedFile.size);
        
        document.getElementById('previewIcon').className = icon;
        document.getElementById('previewName').textContent = selectedFile.name;
        document.getElementById('previewSize').textContent = size;
        
        preview.classList.remove('d-none');
    } else {
        preview.classList.add('d-none');
    }
}

function removeSelectedFile() {
    selectedFile = null;
    document.getElementById('uploadFileInput').value = '';
    updateFilePreview();
}

function getFileIconByName(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
    
    const iconMap = {
        'pdf': 'fas fa-file-pdf text-danger',
        'doc': 'fas fa-file-word text-primary',
        'docx': 'fas fa-file-word text-primary',
        'xls': 'fas fa-file-excel text-success',
        'xlsx': 'fas fa-file-excel text-success',
        'txt': 'fas fa-file-alt text-secondary',
        'jpg': 'fas fa-file-image text-info',
        'jpeg': 'fas fa-file-image text-info',
        'png': 'fas fa-file-image text-info',
        'dwg': 'fas fa-drafting-compass text-primary'
    };
    
    return iconMap[ext] || 'fas fa-file text-secondary';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Drag and drop handlers
function handleDragOver(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

function handleDragLeave(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const fileInput = document.getElementById('uploadFileInput');
        fileInput.files = files;
        handleFileSelect(fileInput);
    }
}

async function uploadFile() {
    if (!selectedFile) {
        showNotification('Vui lòng chọn file', 'warning');
        return;
    }
    
    const form = document.getElementById('fileUploadForm');
    const formData = new FormData(form);
    
    // Validation
    const displayName = formData.get('display_name').trim();
    const fileType = formData.get('file_type');
    
    if (!displayName) {
        showNotification('Vui lòng nhập tên file', 'warning');
        return;
    }
    
    if (!fileType) {
        showNotification('Vui lòng chọn loại file', 'warning');
        return;
    }
    
    // Add additional data
    formData.append('action', 'upload');
    formData.append('equipment_id', window.equipmentViewData.equipmentId);
    formData.append('file', selectedFile);
    
    try {
        showLoading(true);
        
        const response = await fetch('api/equipment_files.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message || 'Upload thành công', 'success');
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('fileUploadModal')).hide();
            
            // Reload page to show new file
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(result.message || 'Upload thất bại', 'error');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showNotification('Lỗi khi upload file: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}
// Thêm vào assets/js/equipment-view.js

/**
 * Delete file function
 */
function deleteEquipmentFile(fileId, fileName) {
    if (!confirm(`Bạn có chắc chắn muốn xóa file "${fileName}"?\nHành động này không thể hoàn tác.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', fileId);
    
    showLoading(true);
    
    fetch(window.equipmentViewData.urls.filesApi, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(result => {
        if (result.success) {
            showNotification(result.message || 'Xóa file thành công', 'success');
            
            // Reload page to update file list
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(result.message || 'Không thể xóa file', 'error');
        }
    })
    .catch(error => {
        console.error('Delete file error:', error);
        showNotification('Lỗi khi xóa file: ' + error.message, 'error');
    })
    .finally(() => {
        showLoading(false);
    });
}

/**
 * View file in new tab
 */
function viewFile(fileUrl, fileName) {
    // Open in new tab
    window.open(fileUrl, '_blank');
    
    // Log view activity (optional)
    console.log(`Viewed file: ${fileName}`);
}

/**
 * Download file
 */
function downloadFile(fileUrl, fileName) {
    // Create temporary link and click it
    const link = document.createElement('a');
    link.href = fileUrl;
    link.download = fileName;
    link.style.display = 'none';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification(`Đang tải file: ${fileName}`, 'info');
}

/**
 * Enhanced functions for existing features
 */

// Update the existing uploadFiles function name to avoid conflicts
function uploadDocument() {
    showFileUploadModal();
}

function uploadImage() {
    showNotification('Chức năng upload ảnh thiết bị đang được phát triển', 'info');
}

function createMaintenanceSchedule() {
    showNotification('Chức năng lên lịch bảo trì đang được phát triển', 'info');
}

function viewFullMaintenanceHistory() {
    showNotification('Chức năng xem lịch sử bảo trì đang được phát triển', 'info');
}

function exportEquipment() {
    showNotification('Chức năng xuất PDF đang được phát triển', 'info');
}

/**
 * Show image modal for main equipment image
 */
function showImageModal(src) {
    let modal = document.getElementById('mainImageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'mainImageModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Hình ảnh thiết bị</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center p-0">
                        <img id="mainModalImage" src="" alt="" class="img-fluid" style="max-height: 80vh;">
                    </div>
                    <div class="modal-footer">
                        <a id="downloadMainImage" href="" download class="btn btn-primary">
                            <i class="fas fa-download me-1"></i>Tải xuống
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Update modal content
    document.getElementById('mainModalImage').src = src;
    document.getElementById('downloadMainImage').href = src;
    document.getElementById('downloadMainImage').download = `${window.equipmentViewData.equipment.name}_image.jpg`;
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}