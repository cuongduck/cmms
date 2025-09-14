/**
 * Equipment Add Form JavaScript - Modified Implementation
 * File: /assets/js/equipment-add.js
 *
 * Modified to remove all document/manual file handling logic.
 */

const EquipmentAddForm = {
    // Configuration
    config: {
        autoSaveInterval: 30000, // 30 seconds
        maxFileSize: 5 * 1024 * 1024, // 5MB
        allowedImageTypes: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        formSelector: '#equipmentForm',
        previewSelector: '#previewContent'
    },
    
    // Form state
    state: {
        isDirty: false,
        autoSaveTimer: null,
        autoSaveTimeout: null,
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
                workshopId: option.getAttribute('data-workshop')
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
                this.updateAreas();
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
        
        if (!industrySelect || !workshopSelect) return;
        
        const selectedIndustryId = industrySelect.value;
        
        // Clear dependent selects
        this.clearSelect(workshopSelect, 'Chọn xưởng');
        this.clearSelect(document.getElementById('lineId'), 'Chọn line sản xuất');
        this.clearSelect(document.getElementById('areaId'), 'Chọn khu vực');
        
        // Filter and populate workshops
        const availableWorkshops = this.state.dependentData.workshops.filter(workshop => 
            !selectedIndustryId || workshop.industryId === selectedIndustryId
        );
        
        this.populateSelect(workshopSelect, availableWorkshops);
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
            { id: 'notes', counterId: 'notesCounter', maxLength: 1000 },
            { id: 'locationDetails', counterId: 'locationDetailsCounter', maxLength: 500 }
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
    
    // Initialize validation
    initializeValidation: function() {
        const form = document.querySelector(this.config.formSelector);
        if (!form) return;
        
        // Add validation to required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
            
            field.addEventListener('input', () => {
                if (field.classList.contains('is-invalid')) {
                    this.validateField(field);
                }
            });
        });
        
        // Equipment code validation
        const codeField = document.getElementById('equipmentCode');
        if (codeField) {
            codeField.addEventListener('input', () => {
                const value = codeField.value.trim();
                if (value && !/^[A-Z0-9_-]+$/.test(value)) {
                    this.setFieldError(codeField, 'Mã thiết bị chỉ được chứa chữ hoa, số, dấu gạch ngang và gạch dưới');
                } else {
                    this.clearFieldError(codeField);
                }
            });
        }
        
        // Year validation
        const yearField = document.getElementById('manufactureYear');
        if (yearField) {
            yearField.addEventListener('input', () => {
                const value = parseInt(yearField.value);
                const currentYear = new Date().getFullYear();
                if (value && (value < 1900 || value > currentYear + 1)) {
                    this.setFieldError(yearField, `Năm sản xuất phải từ 1900 đến ${currentYear + 1}`);
                } else {
                    this.clearFieldError(yearField);
                }
            });
        }
    },
    
    // Validate Field
    validateField: function(field) {
        if (!field) return true;
        
        // Clear previous validation
        this.clearFieldError(field);
        
        // Check required
        if (field.hasAttribute('required') && !field.value.trim()) {
            this.setFieldError(field, 'Trường này là bắt buộc');
            return false;
        }
        
        // Check pattern
        if (field.pattern && field.value && !new RegExp(field.pattern).test(field.value)) {
            this.setFieldError(field, 'Định dạng không hợp lệ');
            return false;
        }
        
        // Check min/max length
        if (field.minLength && field.value.length < field.minLength) {
            this.setFieldError(field, `Tối thiểu ${field.minLength} ký tự`);
            return false;
        }
        
        if (field.maxLength && field.value.length > field.maxLength) {
            this.setFieldError(field, `Tối đa ${field.maxLength} ký tự`);
            return false;
        }
        
        return true;
    },
    
    // Set Field Error
    setFieldError: function(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        let feedback = field.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    },
    
    // Clear Field Error
    clearFieldError: function(field) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
        }
    },
    
    // Clear All Validation Errors
    clearValidationErrors: function() {
        const form = document.querySelector(this.config.formSelector);
        if (!form) return;
        
        form.querySelectorAll('.is-invalid').forEach(field => {
            this.clearFieldError(field);
        });
        
        form.classList.remove('was-validated');
    },
    
    // Initialize Drag and Drop
    initializeDragAndDrop: function() {
        const imageUpload = document.querySelector('[onclick*="imageFile"]');
        
        if (imageUpload) {
            this.setupDragAndDrop(imageUpload, document.getElementById('imageFile'));
        }
    },
    
    // Setup Drag and Drop for element
    setupDragAndDrop: function(dropZone, fileInput) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    },
    
    // Initialize Auto Save
    initializeAutoSave: function() {
        // Load existing draft
        this.loadDraft();
        
        // Set up auto-save timer
        if (this.state.autoSaveTimer) {
            clearInterval(this.state.autoSaveTimer);
        }
        
        this.state.autoSaveTimer = setInterval(() => {
            if (this.state.isDirty) {
                this.autoSave();
            }
        }, this.config.autoSaveInterval);
    },
    
    // Schedule Auto Save
    scheduleAutoSave: function() {
        if (this.state.autoSaveTimeout) {
            clearTimeout(this.state.autoSaveTimeout);
        }
        
        this.state.autoSaveTimeout = setTimeout(() => {
            this.autoSave();
        }, 5000); // Auto save after 5 seconds of inactivity
    },
    
    // Auto Save Function
    autoSave: function() {
        if (!this.state.isDirty) return;
        
        const formData = this.getFormData();
        if (Object.keys(formData).length === 0) return;

        const autoSaveKey = 'equipment_autosave_new';
        try {
            localStorage.setItem(autoSaveKey, JSON.stringify({
                data: formData,
                timestamp: Date.now()
            }));
            console.log('Auto-saved form data');
        } catch (error) {
            console.error('Failed to auto-save:', error);
        }
    },
    
    // Load Draft Function
    loadDraft: function() {
        const autoSaveKey = 'equipment_autosave_new';
        try {
            const saved = localStorage.getItem(autoSaveKey);
            if (saved) {
                const parsedData = JSON.parse(saved);
                const data = parsedData.data;
                
                // Check if data is not too old (24 hours)
                const age = Date.now() - parsedData.timestamp;
                if (age > 24 * 60 * 60 * 1000) {
                    localStorage.removeItem(autoSaveKey);
                    return;
                }
                
                // Ask user if they want to restore
                if (confirm('Có dữ liệu đã lưu tự động. Bạn có muốn khôi phục không?')) {
                    this.restoreFormData(data);
                    this.showInfo('Đã khôi phục dữ liệu tự động');
                }
            }
        } catch (error) {
            console.error('Failed to load draft:', error);
        }
    },
    
    // Restore Form Data
    restoreFormData: function(data) {
        Object.keys(data).forEach(key => {
            const field = document.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = !!data[key];
                } else if (field.type === 'radio') {
                    if (field.value === data[key]) {
                        field.checked = true;
                    }
                } else {
                    field.value = data[key] || '';
                }
                
                // Trigger events
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    },
    
    // Save Equipment Function
    saveEquipment: function() {
        console.log('Saving equipment...');
        
        const form = document.querySelector(this.config.formSelector);
        if (!form) {
            console.error('Form not found');
            return;
        }

        // Validate form
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            this.showError('Vui lòng điền đầy đủ thông tin bắt buộc');
            
            // Focus on first invalid field
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        // Set action to save
        const actionInput = document.getElementById('formAction');
        if (actionInput) {
            actionInput.value = 'save';
        }

        // Show loading
        this.showLoading(true);

        // Submit form
        try {
            form.submit();
        } catch (error) {
            console.error('Error submitting form:', error);
            this.showError('Có lỗi xảy ra khi lưu thiết bị');
            this.showLoading(false);
        }
    },
    
    // Save Draft Function
    saveDraft: function() {
        console.log('Saving draft...');
        
        const form = document.querySelector(this.config.formSelector);
        if (!form) {
            console.error('Form not found');
            return;
        }

        // Set action to draft
        const actionInput = document.getElementById('formAction');
        if (actionInput) {
            actionInput.value = 'draft';
        }

        // Show loading
        this.showLoading(true);

        // Submit form
        try {
            form.submit();
        } catch (error) {
            console.error('Error saving draft:', error);
            this.showError('Có lỗi xảy ra khi lưu nháp');
            this.showLoading(false);
        }
    },
    
    // Preview Equipment Function
    previewEquipment: function() {
        const modal = new bootstrap.Modal(document.getElementById('fullPreviewModal'));
        const previewContent = this.generateFullPreview();
        document.getElementById('fullPreviewContent').innerHTML = previewContent;
        modal.show();
    },
    
    // Generate Full Preview
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
                    <h6 class="mb-3">Hình ảnh</h6>
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
        
        if (imageInput?.files.length > 0) {
            html += `<div class="mb-3"><strong>Hình ảnh:</strong> ${imageInput.files[0].name}</div>`;
        }
        
        if (!html) {
            html = '<em class="text-muted">Chưa có file đính kèm</em>';
        }
        
        return html;
    },
    
    // Handle File Select
    handleFileSelect: function(input, type) {
        console.log('File selected:', type, input.files[0]);
        
        // Only proceed if type is 'image'
        if (!input.files || input.files.length === 0 || type !== 'image') {
            this.clearFilePreview(type);
            return;
        }
        
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB for images

        // Validate file size
        if (file.size > maxSize) {
            this.showError(`File quá lớn. Tối đa ${this.formatFileSize(maxSize)}`);
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!allowedTypes.includes(file.type)) {
            this.showError(`Loại file không được phép. Chỉ chấp nhận: ${allowedTypes.join(', ')}`);
            input.value = '';
            return;
        }
        
        // Show preview
        this.showFilePreview(file, type);
        this.state.isDirty = true;
        this.updateProgress();
    },
    
    // Show File Preview
    showFilePreview: function(file, type) {
        const previewId = type + 'Preview';
        const preview = document.getElementById(previewId);
        
        if (!preview) return;
        
        preview.classList.remove('d-none');
        
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.innerHTML = `
                <div class="d-flex align-items-center justify-content-between">
                    <img src="${e.target.result}" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                    <div class="ms-3">
                        <div class="fw-semibold">${file.name}</div>
                        <small class="text-muted">${this.formatFileSize(file.size)}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="EquipmentAddForm.clearFilePreview('${type}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    },
    
    // Clear File Preview
    clearFilePreview: function(type) {
        const fileInput = document.getElementById(type + 'File');
        const preview = document.getElementById(type + 'Preview');
        
        if (fileInput) {
            fileInput.value = '';
        }
        
        if (preview) {
            preview.classList.add('d-none');
            preview.innerHTML = '';
        }
        
        this.state.isDirty = true;
        this.updateProgress();
    },
    
    // Format File Size
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },
    
    // Reset Form
    resetForm: function() {
        if (!confirm('Bạn có chắc chắn muốn reset form? Tất cả dữ liệu sẽ bị mất.')) {
            return;
        }
        
        const form = document.querySelector(this.config.formSelector);
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
            
            // Clear custom validation
            this.clearValidationErrors();
            
            // Clear file previews
            this.clearFilePreview('image');
            
            // Clear auto-save
            localStorage.removeItem('equipment_autosave_new');
            
            // Reset state
            this.state.isDirty = false;
            
            // Update UI
            this.updateProgress();
            this.updatePreview();
            
            this.showInfo('Form đã được reset');
        }
    },
    
    // Get Form Data
    getFormData: function() {
        const form = document.querySelector(this.config.formSelector);
        if (!form) return {};
        
        const formData = new FormData(form);
        const data = {};
        
        // Convert FormData to regular object, skipping file inputs
        for (let [key, value] of formData.entries()) {
            if (key !== 'image') {
                data[key] = value;
            }
        }
        
        return data;
    },
    
    // UI helper methods
    showLoading: function(show = true) {
        if (window.CMMS && window.CMMS.showLoading) {
            if (show) {
                CMMS.showLoading();
            } else {
                CMMS.hideLoading();
            }
        } else {
            const buttons = document.querySelectorAll('button[type="button"]');
            buttons.forEach(btn => {
                btn.disabled = show;
                if (show && !btn.dataset.originalHtml) {
                    btn.dataset.originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + btn.textContent;
                } else if (!show && btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                    delete btn.dataset.originalHtml;
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
        
        if (this.state.autoSaveTimeout) {
            clearTimeout(this.state.autoSaveTimeout);
        }
        
        console.log('EquipmentAddForm destroyed');
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
    
    // Apply configuration from window if available
    if (window.equipmentConfig) {
        Object.assign(EquipmentAddForm.config, window.equipmentConfig);
    }
    
    // Initialize the form
    EquipmentAddForm.init();
    
    // Check if there's existing form data to load draft
    const hasFormData = equipmentForm.querySelector('input[value]:not([value=""])') !== null;
    if (!hasFormData) {
        EquipmentAddForm.loadDraft();
    }
    
    // Setup form submission handlers
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