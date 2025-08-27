/**
 * Equipment Add Form JavaScript
 * File: /assets/js/equipment-add.js
 */

const EquipmentAddForm = {
    // Configuration
    config: {
        autoSaveInterval: 30000, // 30 seconds
        maxFileSize: 5 * 1024 * 1024, // 5MB - will be set from PHP
        allowedImageTypes: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        allowedDocTypes: ['pdf', 'doc', 'docx'],
        formSelector: '#equipmentForm',
        previewSelector: '#previewContent'
    },
    
    // Form state
    state: {
        isDirty: false,
        autoSaveTimer: null,
        validationErrors: {},
        dependentData: {
            workshops: [],
            lines: [],
            areas: [],
            equipmentGroups: []
        }
    },
    
    // Initialize form
    init: function() {
        console.log('Initializing equipment add form...');
        
        // Load dependent data from DOM
        this.loadDependentData();
        
        // Initialize components
        this.initializeEventListeners();
        this.initializeCharacterCounters();
        this.initializeValidation();
        this.initializeDragAndDrop();
        this.initializeAutoSave();
        
        // Update UI
        this.updateProgress();
        this.updatePreview();
        
        // Call updates to filter if there are pre-selected values
        const industryId = document.getElementById('industryId');
        if (industryId && industryId.value) this.updateWorkshops();
        
        const workshopId = document.getElementById('workshopId');
        if (workshopId && workshopId.value) {
            this.updateLines();
            this.updateAreas();
        }
        
        const machineTypeId = document.getElementById('machineTypeId');
        if (machineTypeId && machineTypeId.value) this.updateEquipmentGroups();
        
        console.log('Equipment add form initialized successfully');
    },
    
    // Load dependent data from DOM
    loadDependentData: function() {
        const workshopSelect = document.getElementById('workshopId');
        if (workshopSelect) {
            this.state.dependentData.workshops = Array.from(workshopSelect.options).map(option => ({
                id: option.value,
                name: option.text,
                industryId: option.getAttribute('data-industry')
            })).filter(item => item.id);
            console.log('Loaded workshops:', this.state.dependentData.workshops); // Thêm log để kiểm tra
        }
        
        const lineSelect = document.getElementById('lineId');
        if (lineSelect) {
            this.state.dependentData.lines = Array.from(lineSelect.options).map(option => ({
                id: option.value,
                name: option.text,
                workshopId: option.getAttribute('data-workshop')
            })).filter(item => item.id);
        }
        
        const areaSelect = document.getElementById('areaId');
        if (areaSelect) {
            this.state.dependentData.areas = Array.from(areaSelect.options).map(option => ({
                id: option.value,
                name: option.text,
                workshopId: option.getAttribute('data-workshop') // SỬA: Dùng data-workshop thay vì data-line
            })).filter(item => item.id);
        }
        
        const groupSelect = document.getElementById('equipmentGroupId');
        if (groupSelect) {
            this.state.dependentData.equipmentGroups = Array.from(groupSelect.options).map(option => ({
                id: option.value,
                name: option.text,
                machineTypeId: option.getAttribute('data-machine-type')
            })).filter(item => item.id);
        }
    },
    
    // Initialize event listeners
    initializeEventListeners: function() {
        const form = document.querySelector(this.config.formSelector);
        if (!form) return;
        
        form.addEventListener('input', (e) => {
            this.state.isDirty = true;
            this.updateProgress();
            this.updatePreview();
            this.scheduleAutoSave();
            this.validateField(e.target);
        });
        
        form.addEventListener('change', (e) => {
            this.state.isDirty = true;
            this.updateProgress();
            this.updatePreview();
            this.scheduleAutoSave();
            this.handleDependentDropdownChange(e);
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (this.state.isDirty) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc chắn muốn rời khỏi trang?';
                return e.returnValue;
            }
        });
        
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    },
    
    // Handle dependent dropdown changes
    handleDependentDropdownChange: function(e) {
        switch(e.target.id) {
            case 'industryId':
                this.updateWorkshops();
                break;
            case 'workshopId':
                this.updateLines();
                this.updateAreas(); // SỬA: Gọi updateAreas khi workshop thay đổi
                break;
            case 'lineId':
                // Không gọi updateAreas vì area phụ thuộc workshop, không phụ thuộc line
                break;
            case 'machineTypeId':
                this.updateEquipmentGroups();
                break;
        }
    },
    
    // Update workshops based on selected industry
updateWorkshops: function() {
    const industrySelect = document.getElementById('industryId');
    const workshopSelect = document.getElementById('workshopId');
    
    if (!industrySelect || !workshopSelect) {
        console.log('Không tìm thấy industry hoặc workshop select');
        return;
    }
    
    const selectedIndustryId = industrySelect.value;
    console.log('Selected industry ID:', selectedIndustryId);
    
    // Clear dependent selects
    this.clearSelect(workshopSelect, 'Chọn xưởng');
    this.clearSelect(document.getElementById('lineId'), 'Chọn line sản xuất');
    this.clearSelect(document.getElementById('areaId'), 'Chọn khu vực');
    
    // Filter and populate workshops
    const availableWorkshops = this.state.dependentData.workshops.filter(workshop => 
        !selectedIndustryId || workshop.industryId === selectedIndustryId
    );
    
    this.populateSelect(workshopSelect, availableWorkshops);
    
    console.log('Updated workshops for industry:', selectedIndustryId, 'Found workshops:', availableWorkshops);
},
    
    // Update lines based on selected workshop
    updateLines: function() {
        const workshopSelect = document.getElementById('workshopId');
        const lineSelect = document.getElementById('lineId');
        
        if (!workshopSelect || !lineSelect) return;
        
        const selectedWorkshopId = workshopSelect.value;
        
        // Clear line select
        this.clearSelect(lineSelect, 'Chọn line sản xuất');
        
        // Filter and populate lines
        const availableLines = this.state.dependentData.lines.filter(line => 
            !selectedWorkshopId || line.workshopId === selectedWorkshopId
        );
        
        this.populateSelect(lineSelect, availableLines);
        
        console.log('Updated lines for workshop:', selectedWorkshopId);
    },
    
    // Update areas based on selected workshop
    updateAreas: function() {
        const workshopSelect = document.getElementById('workshopId');
        const areaSelect = document.getElementById('areaId');
        
        if (!workshopSelect || !areaSelect) return;
        
        const selectedWorkshopId = workshopSelect.value;
        
        // Clear area select
        this.clearSelect(areaSelect, 'Chọn khu vực');
        
        // Filter and populate areas
        const availableAreas = this.state.dependentData.areas.filter(area => 
            !selectedWorkshopId || area.workshopId === selectedWorkshopId
        );
        
        this.populateSelect(areaSelect, availableAreas);
        
        console.log('Updated areas for workshop:', selectedWorkshopId);
    },
    
    // Update equipment groups based on selected machine type
    updateEquipmentGroups: function() {
        const machineTypeSelect = document.getElementById('machineTypeId');
        const equipmentGroupSelect = document.getElementById('equipmentGroupId');
        
        if (!machineTypeSelect || !equipmentGroupSelect) return;
        
        const selectedMachineTypeId = machineTypeSelect.value;
        
        // Clear group select
        this.clearSelect(equipmentGroupSelect, 'Chọn cụm thiết bị');
        
        // Filter and populate equipment groups
        const availableGroups = this.state.dependentData.equipmentGroups.filter(group => 
            !selectedMachineTypeId || group.machineTypeId === selectedMachineTypeId
        );
        
        this.populateSelect(equipmentGroupSelect, availableGroups);
        
        console.log('Updated equipment groups for machine type:', selectedMachineTypeId);
    },
    
    // Helper function to clear select options
    clearSelect: function(selectElement, defaultText = 'Chọn') {
        if (!selectElement) return;
        
        const currentValue = selectElement.value;
        selectElement.innerHTML = `<option value="">${defaultText}</option>`;
        
        // Trigger change event if value changed
        if (currentValue !== '') {
            selectElement.dispatchEvent(new Event('change', { bubbles: true }));
        }
    },
    
    // Helper function to populate select options
    populateSelect: function(selectElement, items) {
        if (!selectElement || !items) return;
        
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            
            // Copy data attributes if needed
            if (item.industryId) option.setAttribute('data-industry', item.industryId);
            if (item.workshopId) option.setAttribute('data-workshop', item.workshopId);
            if (item.lineId) option.setAttribute('data-line', item.lineId);
            if (item.machineTypeId) option.setAttribute('data-machine-type', item.machineTypeId);
            
            selectElement.appendChild(option);
        });
    },
    
    // Handle keyboard shortcuts
    handleKeyboardShortcuts: function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }
        
        if (e.ctrlKey || e.metaKey) {
            switch(e.key.toLowerCase()) {
                case 's':
                    e.preventDefault();
                    this.saveEquipment();
                    break;
                case 'd':
                    e.preventDefault();
                    this.saveDraft();
                    break;
                case 'r':
                    e.preventDefault();
                    this.resetForm();
                    break;
            }
        }
        
        if (e.key === 'Escape') {
            this.clearValidationErrors();
        }
    },
    
    // Initialize character counters
    initializeCharacterCounters: function() {
        const textareas = [
            { id: 'specifications', counterId: 'specificationsCounter', maxLength: 2000 },
            { id: 'notes', counterId: 'notesCounter', maxLength: 1000 }
        ];
        
        textareas.forEach(({ id, counterId, maxLength }) => {
            const textarea = document.getElementById(id);
            const counter = document.getElementById(counterId);
            
            if (textarea && counter) {
                const updateCounter = () => {
                    const length = textarea.value.length;
                    counter.textContent = length;
                    counter.className = 'character-counter';
                    if (length > maxLength * 0.9) {
                        counter.classList.add('danger');
                    } else if (length > maxLength * 0.8) {
                        counter.classList.add('warning');
                    }
                };
                
                textarea.addEventListener('input', updateCounter);
                updateCounter();
            }
        });
    },
    
    // Update progress bar
    updateProgress: function() {
        const requiredFields = [
            'equipmentName', 'industryId', 'workshopId', 'machineTypeId', 'criticality', 'status'
        ];
        
        const filledFields = requiredFields.filter(id => {
            const field = document.getElementById(id);
            return field && field.value.trim() !== '';
        });
        
        const progress = Math.round((filledFields.length / requiredFields.length) * 100);
        
        const progressFill = document.getElementById('progressFill');
        const progressPercent = document.getElementById('progressPercent');
        
        if (progressFill && progressPercent) {
            progressFill.style.width = progress + '%';
            progressPercent.textContent = progress;
        }
    },
    
    // Update preview panel
    updatePreview: function() {
        const fields = {
            'equipmentName': 'previewName',
            'equipmentCode': 'previewCode',
            'manufacturer': 'previewManufacturer',
            'model': 'previewModel'
        };
        
        Object.entries(fields).forEach(([inputId, previewId]) => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            
            if (input && preview) {
                const value = input.value.trim();
                preview.textContent = value || (inputId === 'equipmentCode' ? 'Tự động tạo' : 'Chưa nhập');
                preview.classList.toggle('empty', !value);
            }
        });
        
        this.updateSelectPreview('industryId', 'previewIndustry');
        this.updateSelectPreview('workshopId', 'previewWorkshop');
        this.updateSelectPreview('machineTypeId', 'previewMachineType');
        this.updateSelectPreview('criticality', 'previewCriticality');
        this.updateSelectPreview('status', 'previewStatus');
        this.updateSelectPreview('ownerUserId', 'previewOwner');
    },
    
    updateSelectPreview: function(selectId, previewId) {
        const select = document.getElementById(selectId);
        const preview = document.getElementById(previewId);
        
        if (select && preview) {
            const selectedOption = select.options[select.selectedIndex];
            preview.textContent = selectedOption && select.selectedIndex !== 0 ? selectedOption.text : 'Chưa chọn';
            preview.classList.toggle('empty', !selectedOption || select.selectedIndex === 0);
        }
    },
    
    generateFullPreview: function() {
        const formData = this.getFormData();
        
        return `
            <div class="row">
                <div class="col-md-8">
                    <h5 class="mb-3">Thông tin thiết bị</h5>
                    <table class="table table-bordered">
                        <tr><th width="30%">Tên thiết bị</th><td>${formData.name || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                        <tr><th>Mã thiết bị</th><td>${formData.code || '<em class="text-muted">Tự động tạo</em>'}</td></tr>
                        <tr><th>Nhà sản xuất</th><td>${formData.manufacturer || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                        <tr><th>Model</th><td>${formData.model || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                        <tr><th>Số seri</th><td>${formData.serial_number || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                        <tr><th>Năm sản xuất</th><td>${formData.manufacture_year || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                    </table>
                    
                    <h6 class="mt-4 mb-3">Vị trí & Phân loại</h6>
                    <table class="table table-bordered">
                        <tr><th width="30%">Ngành</th><td>${this.getSelectedText('industryId')}</td></tr>
                        <tr><th>Xưởng</th><td>${this.getSelectedText('workshopId')}</td></tr>
                        <tr><th>Line sản xuất</th><td>${this.getSelectedText('lineId')}</td></tr>
                        <tr><th>Khu vực</th><td>${this.getSelectedText('areaId')}</td></tr>
                        <tr><th>Dòng máy</th><td>${this.getSelectedText('machineTypeId')}</td></tr>
                        <tr><th>Cụm thiết bị</th><td>${this.getSelectedText('equipmentGroupId')}</td></tr>
                    </table>
                    
                    <h6 class="mt-4 mb-3">Quản lý & Trạng thái</h6>
                    <table class="table table-bordered">
                        <tr><th width="30%">Người quản lý chính</th><td>${this.getSelectedText('ownerUserId')}</td></tr>
                        <tr><th>Người quản lý phụ</th><td>${this.getSelectedText('backupOwnerUserId')}</td></tr>
                        <tr><th>Mức độ quan trọng</th><td><span class="badge bg-info">${this.getSelectedText('criticality')}</span></td></tr>
                        <tr><th>Trạng thái</th><td><span class="badge bg-success">${this.getSelectedText('status')}</span></td></tr>
                    </table>
                    
                    ${formData.specifications ? `
                    <h6 class="mt-4 mb-3">Thông số kỹ thuật</h6>
                    <div class="p-3 bg-light rounded">${formData.specifications}</div>
                    ` : ''}
                    
                    ${formData.notes ? `
                    <h6 class="mt-4 mb-3">Ghi chú</h6>
                    <div class="p-3 bg-light rounded">${formData.notes}</div>
                    ` : ''}
                </div>
                <div class="col-md-4">
                    <h6 class="mb-3">Hình ảnh & Tài liệu</h6>
                    <div id="previewFiles">
                        ${this.generateFilePreview()}
                    </div>
                </div>
            </div>
        `;
    },
    
    getSelectedText: function(selectId) {
        const select = document.getElementById(selectId);
        if (!select || select.selectedIndex === 0) {
            return '<em class="text-muted">Chưa chọn</em>';
        }
        return select.options[select.selectedIndex].text;
    },
    
    generateFilePreview: function() {
        let html = '';
        
        const imageInput = document.getElementById('imageFile');
        const manualInput = document.getElementById('manualFile');
        
        if (imageInput?.files.length > 0) {
            html += `<div class="mb-3"><strong>Hình ảnh:</strong> ${imageInput.files[0].name}</div>`;
        }
        
        if (manualInput?.files.length > 0) {
            html += `<div class="mb-3"><strong>Tài liệu:</strong> ${manualInput.files[0].name}</div>`;
        }
        
        if (!html) {
            html = '<em class="text-muted">Chưa có file đính kèm</em>';
        }
        
        return html;
    },
    
    // UI helper methods
    showLoading: function(show = true) {
        if (window.CMMS && window.CMMS.showLoading) {
            CMMS.showLoading(show);
        } else {
            const buttons = document.querySelectorAll('button[type="button"]');
            buttons.forEach(btn => {
                btn.disabled = show;
                if (show) {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + btn.textContent;
                }
            });
        }
    },
    
    showSuccess: function(message) {
        if (window.CMMS && window.CMMS.showToast) {
            CMMS.showToast(message, 'success');
        } else {
            alert(message);
        }
    },
    
    showError: function(message) {
        if (window.CMMS && window.CMMS.showToast) {
            CMMS.showToast(message, 'error');
        } else {
            alert(message);
        }
    },
    
    showInfo: function(message) {
        if (window.CMMS && window.CMMS.showToast) {
            CMMS.showToast(message, 'info');
        } else {
            alert(message);
        }
    },
    
    // Cleanup
    destroy: function() {
        if (this.state.autoSaveTimer) {
            clearInterval(this.state.autoSaveTimer);
        }
        
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout);
        }
        
        console.log('EquipmentAddForm destroyed');
    },
    
    // Placeholder for missing methods (to avoid undefined errors)
    initializeValidation: function() {
        console.log('Placeholder: initializeValidation not implemented');
    },
    
    initializeDragAndDrop: function() {
        console.log('Placeholder: initializeDragAndDrop not implemented');
    },
    
    initializeAutoSave: function() {
        console.log('Placeholder: initializeAutoSave not implemented');
    },
    
    validateField: function(field) {
        console.log('Placeholder: validateField not implemented', field);
    },
    
    scheduleAutoSave: function() {
        console.log('Placeholder: scheduleAutoSave not implemented');
    },
    
    clearValidationErrors: function() {
        console.log('Placeholder: clearValidationErrors not implemented');
    },
    
    getFormData: function() {
        const form = document.querySelector(this.config.formSelector);
        if (!form) return {};
        
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        return data;
    },
    
    saveEquipment: function() {
        console.log('Placeholder: saveEquipment not implemented');
    },
    
    saveDraft: function() {
        console.log('Placeholder: saveDraft not implemented');
    },
    
    previewEquipment: function() {
        console.log('Placeholder: previewEquipment not implemented');
        const modal = new bootstrap.Modal(document.getElementById('fullPreviewModal'));
        const previewContent = this.generateFullPreview();
        document.getElementById('fullPreviewContent').innerHTML = previewContent;
        modal.show();
    },
    
    resetForm: function() {
        console.log('Placeholder: resetForm not implemented');
    },
    
    handleFileSelect: function(input, type) {
        console.log('Placeholder: handleFileSelect not implemented', input, type);
    },
    
    clearFilePreview: function(type) {
        console.log('Placeholder: clearFilePreview not implemented', type);
    },
    
    loadDraft: function() {
        console.log('Placeholder: loadDraft not implemented');
    }
};

// Global functions for HTML callbacks
function updateWorkshops() {
    EquipmentAddForm.updateWorkshops();
}

function updateLines() {
    EquipmentAddForm.updateLines();
}

function updateAreas() {
    EquipmentAddForm.updateAreas();
}

function updateEquipmentGroups() {
    EquipmentAddForm.updateEquipmentGroups();
}

function saveEquipment() {
    EquipmentAddForm.saveEquipment();
}

function saveDraft() {
    EquipmentAddForm.saveDraft();
}

function previewEquipment() {
    EquipmentAddForm.previewEquipment();
}

function resetForm() {
    EquipmentAddForm.resetForm();
}

function handleFileSelect(input, type) {
    EquipmentAddForm.handleFileSelect(input, type);
}

function clearFilePreview(type) {
    EquipmentAddForm.clearFilePreview(type);
}

// DOM Content Loaded Event Handler
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing equipment add form...');
    
    const equipmentForm = document.getElementById('equipmentForm');
    if (!equipmentForm) {
        console.log('Equipment form not found, skipping initialization');
        return;
    }
    
    if (window.equipmentConfig) {
        Object.assign(EquipmentAddForm.config, window.equipmentConfig);
    }
    
    EquipmentAddForm.init();
    
    const hasFormData = equipmentForm.querySelector('input[value]:not([value=""])') !== null;
    if (!hasFormData) {
        EquipmentAddForm.loadDraft();
    }
    
    setupFormSubmission();
    setupPageUnload();
    
    console.log('Equipment add form initialization completed');
});

// Setup form submission with loading states
function setupFormSubmission() {
    const form = document.getElementById('equipmentForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        const submitButtons = form.querySelectorAll('button[type="submit"], button[onclick*="save"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            btn.dataset.originalHtml = originalHtml;
        });
        
        EquipmentAddForm.showLoading(true);
    });
}

// Setup page unload handling
function setupPageUnload() {
    window.addEventListener('beforeunload', function(e) {
        if (EquipmentAddForm.state.isDirty) {
            EquipmentAddForm.saveDraft(true);
        }
    });
    
    window.addEventListener('unload', function() {
        EquipmentAddForm.destroy();
    });
}

// Global error handling
window.addEventListener('error', function(e) {
    if (e.filename && e.filename.includes('equipment-add')) {
        console.error('Equipment Add Form Error:', e.error);
        if (window.CMMS && window.CMMS.showToast) {
            CMMS.showToast('Đã xảy ra lỗi trong form. Vui lòng thử lại.', 'error');
        }
        
        const buttons = document.querySelectorAll('button[disabled]');
        buttons.forEach(btn => {
            btn.disabled = false;
            if (btn.dataset.originalHtml) {
                btn.innerHTML = btn.dataset.originalHtml;
                delete btn.dataset.originalHtml;
            }
        });
        
        EquipmentAddForm.showLoading(false);
    }
});

// Extend CMMS object if available
if (window.CMMS) {
    window.CMMS.equipmentAdd = EquipmentAddForm;
    
    Object.assign(window.CMMS, {
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        generateEquipmentCode: function(industryCode, workshopCode, lineCode, areaCode) {
            const parts = [
                industryCode || 'EQ',
                workshopCode || '',
                lineCode || '',
                areaCode || ''
            ].filter(Boolean);
            
            const baseCode = parts.join('-');
            const timestamp = Date.now().toString().slice(-4);
            return `${baseCode}-${timestamp}`.toUpperCase();
        },
        
        setupFieldDependency: function(parentSelector, childSelector, dataAttr) {
            const parent = document.querySelector(parentSelector);
            const child = document.querySelector(childSelector);
            
            if (!parent || !child) return;
            
            parent.addEventListener('change', function() {
                const parentValue = this.value;
                const childOptions = child.querySelectorAll(`option[${dataAttr}]`);
                
                child.value = '';
                childOptions.forEach(option => {
                    const attrValue = option.getAttribute(dataAttr);
                    option.style.display = (!parentValue || attrValue === parentValue) ? '' : 'none';
                });
            });
        }
    });
}

// Development helpers (only in development mode)
if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
    window.equipmentAddDebug = {
        form: EquipmentAddForm,
        
        fillSampleData: function() {
            const sampleData = {
                name: 'Máy ép phun nhựa ABC-123',
                manufacturer: 'Honda',
                model: 'HM-2000',
                serial_number: 'HM2000-2024-001',
                manufacture_year: '2024',
                specifications: 'Công suất: 100HP\nTốc độ: 1800 rpm\nÁp suất: 150 bar',
                location_details: 'Tầng 1, khu vực sản xuất chính',
                notes: 'Thiết bị mới, cần kiểm tra định kỳ hàng tuần'
            };
            
            Object.entries(sampleData).forEach(([key, value]) => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            
            console.log('Sample data filled');
        },
        
        validateAll: function() {
            const form = document.getElementById('equipmentForm');
            const fields = form.querySelectorAll('input, select, textarea');
            
            fields.forEach(field => {
                EquipmentAddForm.validateField(field);
            });
            
            console.log('All fields validated');
        },
        
        showState: function() {
            console.log('Form State:', EquipmentAddForm.state);
            console.log('Form Data:', EquipmentAddForm.getFormData());
        }
    };
    
    console.log('Equipment Add Debug helpers available:', window.equipmentAddDebug);
}

// Auto-focus first input
setTimeout(function() {
    const firstInput = document.querySelector('#equipmentForm input:not([type="hidden"]):not([readonly]):not([disabled])');
    if (firstInput) {
        firstInput.focus();
    }
}, 100);

// Expose main object for debugging
window.EquipmentAddForm = EquipmentAddForm;

console.log('Equipment Add Form JavaScript loaded successfully');