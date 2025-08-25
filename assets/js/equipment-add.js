/**
 * Equipment Add Form JavaScript - Part 1: Core Functions
 * File: /assets/js/equipment-add.js
 */

// Equipment form management object
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
        this.initializeValidation();
        this.initializeDragAndDrop();
        this.initializeAutoSave();
        this.initializeCharacterCounters();
        
        // Update UI
        this.updateProgress();
        this.updatePreview();
        this.updateDependentDropdowns();
        
        console.log('Equipment add form initialized successfully');
    },
    
    // Load dependent data from DOM
    loadDependentData: function() {
        // Extract data from option elements for dependent dropdowns
        const workshopSelect = document.getElementById('workshopId');
        if (workshopSelect) {
            this.state.dependentData.workshops = Array.from(workshopSelect.options).map(option => ({
                id: option.value,
                name: option.text,
                industryId: option.getAttribute('data-industry')
            })).filter(item => item.id);
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
                lineId: option.getAttribute('data-workshop')
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
        
        // Form input listeners
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
            
            // Handle dependent dropdowns
            this.handleDependentDropdownChange(e.target);
        });
        
        // Prevent accidental navigation
        window.addEventListener('beforeunload', (e) => {
            if (this.state.isDirty) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc chắn muốn rời khỏi trang?';
                return e.returnValue;
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    },
    
    // Handle dependent dropdown changes
    handleDependentDropdownChange: function(element) {
    switch(element.id) {
        case 'industryId':
            this.updateWorkshops();
            break;
        case 'workshopId':
            this.updateLines();
            //this.updateAreas(); // Giữ nguyên
            break;
        case 'lineId':
            // Không làm gì
            break;
        case 'machineTypeId':
            this.updateEquipmentGroups();
            break;
    }
},
    // Update dependent dropdowns
    updateDependentDropdowns: function() {
        this.updateWorkshops();
        this.updateLines();
      // this.updateAreas();
        this.updateEquipmentGroups();
    },
    
    // Update workshops based on selected industry
    updateWorkshops: function() {
        const industrySelect = document.getElementById('industryId');
        const workshopSelect = document.getElementById('workshopId');
        
        if (!industrySelect || !workshopSelect) return;
        
        const selectedIndustryId = industrySelect.value;
        
        // Clear dependent selects
        this.clearSelect(workshopSelect, '-- Chọn xưởng --');
        this.clearSelect(document.getElementById('lineId'), '-- Chọn line (tùy chọn) --');
        //this.clearSelect(document.getElementById('areaId'), '-- Chọn khu vực (tùy chọn) --');
        
        // Filter and populate workshops
        const availableWorkshops = this.state.dependentData.workshops.filter(workshop => 
            !selectedIndustryId || workshop.industryId === selectedIndustryId
        );
        
        this.populateSelect(workshopSelect, availableWorkshops);
        
        console.log('Updated workshops for industry:', selectedIndustryId);
    },
    
    // Update lines based on selected workshop
    updateLines: function() {
        const workshopSelect = document.getElementById('workshopId');
        const lineSelect = document.getElementById('lineId');
        
        if (!workshopSelect || !lineSelect) return;
        
        const selectedWorkshopId = workshopSelect.value;
        
        // Clear dependent selects
        this.clearSelect(lineSelect, '-- Chọn line (tùy chọn) --');
        //this.clearSelect(document.getElementById('areaId'), '-- Chọn khu vực (tùy chọn) --');
        
        // Filter and populate lines
        const availableLines = this.state.dependentData.lines.filter(line => 
            !selectedWorkshopId || line.workshopId === selectedWorkshopId
        );
        
        this.populateSelect(lineSelect, availableLines);
        
        console.log('Updated lines for workshop:', selectedWorkshopId);
    },
    
    // Update areas based on selected line
// Update areas based on selected workshop (NOT line)
   // Update areas based on selected workshop (giống như updateEquipmentGroups)
// Sửa updateAreas() thành:


    // Update equipment groups based on selected machine type
    updateEquipmentGroups: function() {
        const machineTypeSelect = document.getElementById('machineTypeId');
        const equipmentGroupSelect = document.getElementById('equipmentGroupId');
        
        if (!machineTypeSelect || !equipmentGroupSelect) return;
        
        const selectedMachineTypeId = machineTypeSelect.value;
        
        // Clear group select
        this.clearSelect(equipmentGroupSelect, '-- Chọn cụm thiết bị (tùy chọn) --');
        
        // Filter and populate equipment groups
        const availableGroups = this.state.dependentData.equipmentGroups.filter(group => 
            !selectedMachineTypeId || group.machineTypeId === selectedMachineTypeId
        );
        
        this.populateSelect(equipmentGroupSelect, availableGroups);
        
        console.log('Updated equipment groups for machine type:', selectedMachineTypeId);
    },
    
    // Helper function to clear select options
    clearSelect: function(selectElement, defaultText = '-- Chọn --') {
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
        // Only process if not typing in an input
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
        
        // Escape key
        if (e.key === 'Escape') {
            this.clearValidationErrors();
        }
    }
};

// Global functions that will be called from HTML
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
/**
 * Equipment Add Form JavaScript - Part 2: Validation & Progress
 * File: /assets/js/equipment-add.js (continued)
 */

// Continue EquipmentAddForm object - Part 2
Object.assign(EquipmentAddForm, {
    
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
                updateCounter(); // Initial update
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
        
        // Update select fields
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
            const value = selectedOption ? selectedOption.text : '';
            
            preview.textContent = value || 'Chưa chọn';
            preview.classList.toggle('empty', !value || select.selectedIndex === 0);
        }
    },
    
    // Form validation
    initializeValidation: function() {
        const form = document.querySelector(this.config.formSelector);
        
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                if (this.validateForm()) {
                    this.submitForm();
                }
            });
        }
    },
    
    validateField: function(field) {
        const fieldId = field.id;
        let isValid = true;
        let errorMessage = '';
        
        // Clear previous validation
        field.classList.remove('is-invalid');
        const feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) feedback.textContent = '';
        
        // Validation rules
        switch (fieldId) {
            case 'equipmentName':
                if (!field.value.trim()) {
                    isValid = false;
                    errorMessage = 'Tên thiết bị không được trống';
                } else if (field.value.length < 2) {
                    isValid = false;
                    errorMessage = 'Tên thiết bị phải có ít nhất 2 ký tự';
                } else if (field.value.length > 200) {
                    isValid = false;
                    errorMessage = 'Tên thiết bị không được quá 200 ký tự';
                }
                break;
                
            case 'equipmentCode':
                if (field.value && !/^[A-Z0-9_-]+$/.test(field.value)) {
                    isValid = false;
                    errorMessage = 'Mã thiết bị chỉ được chứa chữ hoa, số, dấu gạch ngang và gạch dưới';
                } else if (field.value && field.value.length > 30) {
                    isValid = false;
                    errorMessage = 'Mã thiết bị không được quá 30 ký tự';
                }
                break;
                
            case 'industryId':
            case 'workshopId':
            case 'machineTypeId':
                if (!field.value) {
                    isValid = false;
                    errorMessage = 'Vui lòng chọn một tùy chọn';
                }
                break;
                
            case 'manufactureYear':
                if (field.value) {
                    const year = parseInt(field.value);
                    const currentYear = new Date().getFullYear();
                    if (year < 1900 || year > currentYear + 1) {
                        isValid = false;
                        errorMessage = `Năm sản xuất phải từ 1900 đến ${currentYear + 1}`;
                    }
                }
                break;
                
            case 'maintenanceFrequencyDays':
                if (field.value && (parseInt(field.value) < 1 || parseInt(field.value) > 365)) {
                    isValid = false;
                    errorMessage = 'Chu kỳ bảo trì phải từ 1 đến 365 ngày';
                }
                break;
                
            case 'manufacturer':
            case 'model':
            case 'serialNumber':
                if (field.value && field.value.length > 100) {
                    isValid = false;
                    errorMessage = 'Trường này không được quá 100 ký tự';
                }
                break;
                
            case 'locationDetails':
                if (field.value && field.value.length > 200) {
                    isValid = false;
                    errorMessage = 'Vị trí chi tiết không được quá 200 ký tự';
                }
                break;
                
            case 'specifications':
                if (field.value && field.value.length > 2000) {
                    isValid = false;
                    errorMessage = 'Thông số kỹ thuật không được quá 2000 ký tự';
                }
                break;
                
            case 'notes':
                if (field.value && field.value.length > 1000) {
                    isValid = false;
                    errorMessage = 'Ghi chú không được quá 1000 ký tự';
                }
                break;
        }
        
        // Show validation result
        if (!isValid) {
            field.classList.add('is-invalid');
            if (feedback) feedback.textContent = errorMessage;
            this.state.validationErrors[fieldId] = errorMessage;
        } else {
            delete this.state.validationErrors[fieldId];
        }
        
        return isValid;
    },
    
    validateForm: function() {
        const form = document.querySelector(this.config.formSelector);
        const inputs = form.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        // Validate all required fields
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        // Validate optional fields that have values
        const optionalFields = form.querySelectorAll('input:not([required]), select:not([required]), textarea');
        optionalFields.forEach(field => {
            if (field.value) {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            }
        });
        
        // Check for any existing validation errors
        if (Object.keys(this.state.validationErrors).length > 0) {
            isValid = false;
        }
        
        if (!isValid) {
            this.showValidationSummary();
            
            // Scroll to first error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
        
        return isValid;
    },
    
    showValidationSummary: function() {
        const errorCount = Object.keys(this.state.validationErrors).length;
        
        if (errorCount > 0) {
            // Show toast with error count
            if (window.CMMS && window.CMMS.showToast) {
                CMMS.showToast(`Vui lòng sửa ${errorCount} lỗi trong form`, 'error');
            } else {
                alert(`Vui lòng kiểm tra và sửa ${errorCount} lỗi trong form`);
            }
        }
    },
    
    clearValidationErrors: function() {
        const form = document.querySelector(this.config.formSelector);
        
        // Clear validation states
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        
        // Clear error state
        this.state.validationErrors = {};
        
        console.log('Validation errors cleared');
    },
    
    // Auto-save functionality
    initializeAutoSave: function() {
        this.state.autoSaveTimer = setInterval(() => {
            if (this.state.isDirty) {
                this.saveDraft(true); // Silent auto-save
            }
        }, this.config.autoSaveInterval);
    },
    
    scheduleAutoSave: function() {
        clearTimeout(this.autoSaveTimeout);
        this.autoSaveTimeout = setTimeout(() => {
            this.saveDraft(true);
        }, 5000); // Auto-save after 5 seconds of inactivity
    },
    
    // Form data collection
    getFormData: function() {
        const form = document.querySelector(this.config.formSelector);
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    },
    
    // Load draft data
    loadDraft: function() {
        try {
            const savedDraft = localStorage.getItem('equipment_draft');
            if (savedDraft) {
                const draftData = JSON.parse(savedDraft);
                
                // Populate form fields
                Object.entries(draftData).forEach(([key, value]) => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field && value) {
                        field.value = value;
                        
                        // Trigger change event for dependent dropdowns
                        if (field.tagName === 'SELECT') {
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                });
                
                this.updateProgress();
                this.updatePreview();
                this.updateDependentDropdowns();
                
                // Show notification
                if (window.CMMS && window.CMMS.showToast) {
                    CMMS.showToast('Đã tải dữ liệu nháp từ lần trước', 'info');
                }
                
                return true;
            }
        } catch (error) {
            console.error('Error loading draft:', error);
        }
        
        return false;
    },
    
    // Clear draft
    clearDraft: function() {
        localStorage.removeItem('equipment_draft');
        console.log('Draft cleared');
    }
});

// Additional validation helper functions
const ValidationHelpers = {
    isValidEmail: function(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    isValidCode: function(code) {
        const codeRegex = /^[A-Z0-9_-]+$/;
        return codeRegex.test(code);
    },
    
    isValidYear: function(year) {
        const currentYear = new Date().getFullYear();
        return year >= 1900 && year <= currentYear + 1;
    },
    
    isValidDate: function(dateString) {
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date);
    },
    
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
};
/**
 * Equipment Add Form JavaScript - Part 3: File Handling & Form Actions
 * File: /assets/js/equipment-add.js (continued)
 */

// Continue EquipmentAddForm object - Part 3
Object.assign(EquipmentAddForm, {
    
    // Initialize drag and drop functionality
    initializeDragAndDrop: function() {
        const uploadSections = document.querySelectorAll('.file-upload-section');
        
        uploadSections.forEach(section => {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                section.addEventListener(eventName, this.preventDefaults, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                section.addEventListener(eventName, () => {
                    section.classList.add('drag-over');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                section.addEventListener(eventName, () => {
                    section.classList.remove('drag-over');
                }, false);
            });
            
            section.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                const input = section.querySelector('input[type="file"]');
                
                if (files.length > 0 && input) {
                    input.files = files;
                    this.handleFileSelect(input, input.name);
                }
            }, false);
        });
    },
    
    preventDefaults: function(e) {
        e.preventDefault();
        e.stopPropagation();
    },
    
    // Handle file selection
    handleFileSelect: function(input, type) {
        const file = input.files[0];
        if (!file) return;
        
        console.log('File selected:', file.name, 'Type:', type);
        
        // Validate file
        if (!this.validateFile(file, type)) {
            input.value = '';
            return;
        }
        
        // Show preview
        this.showFilePreview(file, type);
        
        // Mark form as dirty
        this.state.isDirty = true;
        
        // Show upload progress (if needed)
        this.showUploadProgress(type, 100); // Simulate immediate completion for client-side
    },
    
    // Validate uploaded file
    validateFile: function(file, type) {
        const maxSize = this.config.maxFileSize;
        const allowedTypes = type === 'image' ? 
            this.config.allowedImageTypes : 
            this.config.allowedDocTypes;
        
        // Check file size
        if (file.size > maxSize) {
            const maxSizeFormatted = ValidationHelpers.formatFileSize(maxSize);
            this.showError(`File quá lớn. Kích thước tối đa: ${maxSizeFormatted}`);
            return false;
        }
        
        // Check file type
        const extension = file.name.split('.').pop().toLowerCase();
        if (!allowedTypes.includes(extension)) {
            this.showError(`Loại file không được phép. Chấp nhận: ${allowedTypes.join(', ').toUpperCase()}`);
            return false;
        }
        
        // Check for potentially malicious files
        if (this.isSuspiciousFile(file)) {
            this.showError('File có vẻ không an toàn');
            return false;
        }
        
        return true;
    },
    
    // Check for suspicious files
    isSuspiciousFile: function(file) {
        const suspiciousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js'];
        const extension = file.name.split('.').pop().toLowerCase();
        return suspiciousExtensions.includes(extension);
    },
    
    // Show file preview
    showFilePreview: function(file, type) {
        const previewId = type + 'Preview';
        const preview = document.getElementById(previewId);
        
        if (!preview) return;
        
        preview.classList.remove('d-none');
        
        if (type === 'image') {
            this.showImagePreview(file, preview);
        } else {
            this.showDocumentPreview(file, preview);
        }
    },
    
    // Show image preview
    showImagePreview: function(file, preview) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="d-flex align-items-start">
                    <img src="${e.target.result}" alt="Preview" 
                         style="max-width: 200px; max-height: 150px; border-radius: 0.375rem; object-fit: cover;">
                    <div class="ms-3 flex-grow-1">
                        <div class="fw-semibold">${file.name}</div>
                        <small class="text-muted">${ValidationHelpers.formatFileSize(file.size)}</small>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFilePreview('image')">
                                <i class="fas fa-times me-1"></i>Xóa
                            </button>
                        </div>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    },
    
    // Show document preview
    showDocumentPreview: function(file, preview) {
        const extension = file.name.split('.').pop().toLowerCase();
        let icon = 'fas fa-file';
        
        switch(extension) {
            case 'pdf':
                icon = 'fas fa-file-pdf text-danger';
                break;
            case 'doc':
            case 'docx':
                icon = 'fas fa-file-word text-primary';
                break;
            default:
                icon = 'fas fa-file text-secondary';
        }
        
        preview.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="${icon} me-3" style="font-size: 2rem;"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${file.name}</div>
                    <small class="text-muted">${ValidationHelpers.formatFileSize(file.size)}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFilePreview('manual')">
                    <i class="fas fa-times me-1"></i>Xóa
                </button>
            </div>
        `;
    },
    
    // Show upload progress
    showUploadProgress: function(type, progress) {
        const progressContainer = document.getElementById(type + 'Progress');
        const progressBar = progressContainer?.querySelector('.progress-bar');
        
        if (progressContainer && progressBar) {
            progressContainer.style.display = 'block';
            progressBar.style.width = progress + '%';
            
            if (progress >= 100) {
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                }, 1000);
            }
        }
    },
    
    // Clear file preview
    clearFilePreview: function(type) {
        const input = document.getElementById(type + 'File');
        const preview = document.getElementById(type + 'Preview');
        
        if (input) input.value = '';
        if (preview) {
            preview.classList.add('d-none');
            preview.innerHTML = '';
        }
        
        this.state.isDirty = true;
        console.log('File preview cleared for type:', type);
    },
    
    // Form submission methods
    saveEquipment: function() {
        console.log('Attempting to save equipment...');
        
        document.getElementById('formAction').value = 'save';
        
        if (this.validateForm()) {
            this.showLoading(true);
            this.submitForm();
        }
    },
    
    saveDraft: function(silent = false) {
        console.log('Saving draft...', silent ? '(silent)' : '');
        
        // Save to localStorage as backup
        const formData = this.getFormData();
        localStorage.setItem('equipment_draft', JSON.stringify(formData));
        
        if (!silent) {
            document.getElementById('formAction').value = 'draft';
            this.showLoading(true);
            this.submitForm();
        } else {
            this.state.isDirty = false;
            console.log('Auto-saved draft to localStorage');
        }
    },
    
    submitForm: function() {
        const form = document.querySelector(this.config.formSelector);
        if (form) {
            // Clear validation errors before submit
            this.clearValidationErrors();
            
            // Submit form
            form.submit();
        }
    },
    
    resetForm: function() {
        if (confirm('Bạn có chắc chắn muốn reset form? Tất cả dữ liệu đã nhập sẽ bị mất.')) {
            const form = document.querySelector(this.config.formSelector);
            form.reset();
            
            this.state.isDirty = false;
            this.state.validationErrors = {};
            
            // Clear validation states
            this.clearValidationErrors();
            
            // Clear file previews
            document.querySelectorAll('.file-preview').forEach(preview => {
                preview.classList.add('d-none');
                preview.innerHTML = '';
            });
            
            // Update UI
            this.updateProgress();
            this.updatePreview();
            this.updateDependentDropdowns();
            
            // Clear localStorage
            this.clearDraft();
            
            this.showSuccess('Form đã được reset');
        }
    },
    
    // Preview functionality
    previewEquipment: function() {
        const modal = new bootstrap.Modal(document.getElementById('fullPreviewModal'));
        
        // Generate preview content
        const previewContent = this.generateFullPreview();
        document.getElementById('fullPreviewContent').innerHTML = previewContent;
        
        modal.show();
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
            // Fallback loading indicator
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
        // Clear timers
        if (this.state.autoSaveTimer) {
            clearInterval(this.state.autoSaveTimer);
        }
        
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout);
        }
        
        console.log('EquipmentAddForm destroyed');
    }
});

// Global functions for HTML callbacks
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
/**
 * Equipment Add Form JavaScript - Part 4: Initialization & DOM Ready
 * File: /assets/js/equipment-add.js (final part)
 */

// DOM Content Loaded Event Handler
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing equipment add form...');
    
    // Check if we're on the add equipment page
    const equipmentForm = document.getElementById('equipmentForm');
    if (!equipmentForm) {
        console.log('Equipment form not found, skipping initialization');
        return;
    }
    
    // Set configuration from PHP if available
    if (window.equipmentConfig) {
        Object.assign(EquipmentAddForm.config, window.equipmentConfig);
    }
    
    // Initialize the form
    EquipmentAddForm.init();
    
    // Try to load draft data if no form data exists
    const hasFormData = equipmentForm.querySelector('input[value]:not([value=""])') !== null;
    if (!hasFormData) {
        EquipmentAddForm.loadDraft();
    }
    
    // Setup form submission handling
    setupFormSubmission();
    
    // Setup page unload handling
    setupPageUnload();
    
    console.log('Equipment add form initialization completed');
});

// Setup form submission with loading states
function setupFormSubmission() {
    const form = document.getElementById('equipmentForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        // Don't prevent default here, let the form submit naturally
        // but show loading state
        
        const submitButtons = form.querySelectorAll('button[type="submit"], button[onclick*="save"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
            
            // Store original HTML for potential restore
            btn.dataset.originalHtml = originalHtml;
        });
        
        // Show page loading overlay if available
        EquipmentAddForm.showLoading(true);
    });
}

// Setup page unload handling
function setupPageUnload() {
    window.addEventListener('beforeunload', function(e) {
        // Auto-save before leaving if there are unsaved changes
        if (EquipmentAddForm.state.isDirty) {
            EquipmentAddForm.saveDraft(true);
        }
    });
    
    // Cleanup on page unload
    window.addEventListener('unload', function() {
        EquipmentAddForm.destroy();
    });
}

// Global error handling for the form
window.addEventListener('error', function(e) {
    if (e.filename && e.filename.includes('equipment-add')) {
        console.error('Equipment Add Form Error:', e.error);
        
        // Show user-friendly error message
        if (window.CMMS && window.CMMS.showToast) {
            CMMS.showToast('Đã xảy ra lỗi trong form. Vui lòng thử lại.', 'error');
        }
        
        // Reset loading states
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
    
    // Add utility functions to CMMS
    Object.assign(window.CMMS, {
        formatFileSize: ValidationHelpers.formatFileSize,
        
        // Equipment code generator helper (if needed on client side)
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
        
        // Field dependency helper
        setupFieldDependency: function(parentSelector, childSelector, dataAttr) {
            const parent = document.querySelector(parentSelector);
            const child = document.querySelector(childSelector);
            
            if (!parent || !child) return;
            
            parent.addEventListener('change', function() {
                const parentValue = this.value;
                const childOptions = child.querySelectorAll(`option[${dataAttr}]`);
                
                // Reset child
                child.value = '';
                
                // Show/hide options
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
        validation: ValidationHelpers,
        
        // Debug functions
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

// Auto-focus first input when page loads
setTimeout(function() {
    const firstInput = document.querySelector('#equipmentForm input:not([type="hidden"]):not([readonly]):not([disabled])');
    if (firstInput) {
        firstInput.focus();
    }
}, 100);

// Expose main object to global scope for debugging
window.EquipmentAddForm = EquipmentAddForm;

console.log('Equipment Add Form JavaScript loaded successfully');

/* 
 * Equipment Add Form JavaScript Module Complete
 * 
 * Usage:
 * 1. Include this file in your HTML: <script src="/assets/js/equipment-add.js"></script>
 * 2. Ensure Bootstrap and any dependencies are loaded first
 * 3. The form will auto-initialize when DOM is ready
 * 
 * Global Functions Available:
 * - saveEquipment()
 * - saveDraft()
 * - previewEquipment()
 * - resetForm()
 * - updateWorkshops()
 * - updateLines()
 * - updateAreas()
 * - updateEquipmentGroups()
 * - handleFileSelect(input, type)
 * - clearFilePreview(type)
 * 
 * Configuration:
 * Set window.equipmentConfig before DOM ready to override defaults
 * 
 * Example:
 * window.equipmentConfig = {
 *     maxFileSize: 10 * 1024 * 1024, // 10MB
 *     autoSaveInterval: 60000 // 1 minute
 * };
 */