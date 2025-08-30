/**
 * Equipment Edit Form JavaScript
 * File: /assets/js/equipment-edit.js
 * Mô tả: Quản lý form chỉnh sửa thiết bị với các chức năng như tự động lưu, xử lý dropdown phụ thuộc, và xem trước dữ liệu.
 */

const EquipmentEditForm = {
    // Cấu hình
    config: {
        autoSaveInterval: 30000, // 30 giây
        maxFileSize: 5 * 1024 * 1024, // 5MB
        allowedImageTypes: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        allowedDocTypes: ['pdf', 'doc', 'docx'],
        formSelector: '#equipmentForm',
        previewSelector: '#previewContent',
        isEditMode: true
    },

    // Trạng thái form
    state: {
        isDirty: false,
        autoSaveTimer: null,
        validationErrors: {},
        originalData: {},
        dependentData: {
            workshops: [],
            lines: [],
            areas: [],
            equipmentGroups: []
        }
    },

    // Khởi tạo form
    init: function() {
        console.log('Khởi tạo form chỉnh sửa thiết bị...');

        // Lưu dữ liệu form ban đầu
        this.storeOriginalData();

        // Tải dữ liệu phụ thuộc từ DOM
        this.loadDependentData();

        // Khởi tạo các sự kiện và thành phần
        this.initializeEventListeners();
        this.initializeCharacterCounters();

        // Cập nhật giao diện
        this.updatePreview();
        this.initializeDependentDropdowns();

        console.log('Form chỉnh sửa thiết bị được khởi tạo thành công');
    },

    // Lưu dữ liệu form ban đầu để so sánh
    storeOriginalData: function() {
        const form = document.querySelector(this.config.formSelector);
        if (form) {
            const formData = new FormData(form);
            this.state.originalData = {};
            formData.forEach((value, key) => {
                this.state.originalData[key] = value;
            });
        }
    },

    // Tải dữ liệu phụ thuộc từ DOM
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

        const equipmentGroupSelect = document.getElementById('equipmentGroupId');
        if (equipmentGroupSelect) {
            this.state.dependentData.equipmentGroups = Array.from(equipmentGroupSelect.options).map(option => ({
                id: option.value,
                name: option.text,
                machineTypeId: option.getAttribute('data-machine-type')
            })).filter(item => item.id);
        }
    },

    // Khởi tạo các sự kiện
    initializeEventListeners: function() {
        const form = document.querySelector(this.config.formSelector);
        if (!form) return;

        // Theo dõi thay đổi form
        form.addEventListener('input', (e) => {
            this.state.isDirty = true;
            this.updatePreview();
            this.scheduleAutoSave();
        });

        form.addEventListener('change', (e) => {
            this.state.isDirty = true;
            this.updatePreview();
            this.handleDependentDropdownChange(e);
        });

        // Ngăn rời trang khi có thay đổi chưa lưu
        window.addEventListener('beforeunload', (e) => {
            if (this.state.isDirty && this.hasChanges()) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc chắn muốn rời khỏi trang?';
                return e.returnValue;
            }
        });

        // Phím tắt
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    },

    // Kiểm tra xem form có thay đổi không
    hasChanges: function() {
        const currentData = this.getFormData();

        for (let key in currentData) {
            if (currentData[key] !== this.state.originalData[key]) {
                return true;
            }
        }

        const imageFile = document.getElementById('imageFile');
        const manualFile = document.getElementById('manualFile');

        return (imageFile && imageFile.files.length > 0) || 
               (manualFile && manualFile.files.length > 0);
    },

    // Xử lý thay đổi dropdown phụ thuộc
    handleDependentDropdownChange: function(e) {
        switch (e.target.id) {
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

    // Khởi tạo dropdown phụ thuộc với giá trị hiện tại
    initializeDependentDropdowns: function() {
        const industryId = document.getElementById('industryId')?.value;
        if (industryId) {
            this.updateWorkshops();
            const workshopId = document.getElementById('workshopId')?.value;
            if (workshopId) {
                this.updateLines();
                this.updateAreas();
            }
        }

        const machineTypeId = document.getElementById('machineTypeId')?.value;
        if (machineTypeId) {
            this.updateEquipmentGroups();
        }
    },

    // Cập nhật dropdown xưởng dựa trên ngành
    updateWorkshops: function() {
        const industrySelect = document.getElementById('industryId');
        const workshopSelect = document.getElementById('workshopId');

        if (!industrySelect || !workshopSelect) return;

        const selectedIndustryId = industrySelect.value;
        const currentWorkshopId = workshopSelect.value;

        this.clearSelect(document.getElementById('lineId'), 'Chọn line sản xuất');
        this.clearSelect(document.getElementById('areaId'), 'Chọn khu vực');

        const availableWorkshops = this.state.dependentData.workshops.filter(workshop => 
            !selectedIndustryId || workshop.industryId === selectedIndustryId
        );

        const isCurrentWorkshopValid = availableWorkshops.some(w => w.id === currentWorkshopId);
        if (!isCurrentWorkshopValid && currentWorkshopId) {
            workshopSelect.value = '';
        }

        this.filterSelectOptions(workshopSelect, availableWorkshops, 'industryId', selectedIndustryId);
    },

    // Cập nhật dropdown line dựa trên xưởng
    updateLines: function() {
        const workshopSelect = document.getElementById('workshopId');
        const lineSelect = document.getElementById('lineId');

        if (!workshopSelect || !lineSelect) return;

        const selectedWorkshopId = workshopSelect.value;
        const currentLineId = lineSelect.value;

        const availableLines = this.state.dependentData.lines.filter(line => 
            !selectedWorkshopId || line.workshopId === selectedWorkshopId
        );

        const isCurrentLineValid = availableLines.some(l => l.id === currentLineId);
        if (!isCurrentLineValid && currentLineId) {
            lineSelect.value = '';
        }

        this.filterSelectOptions(lineSelect, availableLines, 'workshopId', selectedWorkshopId);
    },

    // Cập nhật dropdown khu vực dựa trên xưởng
    updateAreas: function() {
        const workshopSelect = document.getElementById('workshopId');
        const areaSelect = document.getElementById('areaId');

        if (!workshopSelect || !areaSelect) return;

        const selectedWorkshopId = workshopSelect.value;
        const currentAreaId = areaSelect.value;

        const availableAreas = this.state.dependentData.areas.filter(area => 
            !selectedWorkshopId || area.workshopId === selectedWorkshopId
        );

        const isCurrentAreaValid = availableAreas.some(a => a.id === currentAreaId);
        if (!isCurrentAreaValid && currentAreaId) {
            areaSelect.value = '';
        }

        this.filterSelectOptions(areaSelect, availableAreas, 'workshopId', selectedWorkshopId);
    },

    // Cập nhật dropdown nhóm thiết bị dựa trên loại máy
    updateEquipmentGroups: function() {
        const machineTypeSelect = document.getElementById('machineTypeId');
        const equipmentGroupSelect = document.getElementById('equipmentGroupId');

        if (!machineTypeSelect || !equipmentGroupSelect) return;

        const selectedMachineTypeId = machineTypeSelect.value;
        const currentEquipmentGroupId = equipmentGroupSelect.value;

        const availableGroups = this.state.dependentData.equipmentGroups.filter(group => 
            !selectedMachineTypeId || group.machineTypeId === selectedMachineTypeId
        );

        const isCurrentGroupValid = availableGroups.some(g => g.id === currentEquipmentGroupId);
        if (!isCurrentGroupValid && currentEquipmentGroupId) {
            equipmentGroupSelect.value = '';
        }

        this.filterSelectOptions(equipmentGroupSelect, availableGroups, 'machineTypeId', selectedMachineTypeId);
    },

    // Lọc các tùy chọn dropdown dựa trên giá trị cha
    filterSelectOptions: function(selectElement, availableItems, dataAttribute, parentValue) {
        if (!selectElement) return;

        const options = selectElement.querySelectorAll(`option[data-${dataAttribute.replace(/([A-Z])/g, '-$1').toLowerCase()}]`);
        options.forEach(option => {
            const attributeValue = option.getAttribute(`data-${dataAttribute.replace(/([A-Z])/g, '-$1').toLowerCase()}`);
            option.style.display = (!parentValue || attributeValue === parentValue) ? '' : 'none';
        });
    },

    // Xóa các tùy chọn dropdown (giữ giá trị hiện tại trong chế độ chỉnh sửa)
    clearSelect: function(selectElement, defaultText = 'Chọn') {
        if (!selectElement) return;
        console.log('Lọc tùy chọn cho', selectElement.id);
    },

    // Xử lý phím tắt
    handleKeyboardShortcuts: function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }

        if (e.ctrlKey || e.metaKey) {
            switch (e.key.toLowerCase()) {
                case 's':
                    e.preventDefault();
                    this.saveEquipment();
                    break;
                case 'd':
                    e.preventDefault();
                    this.saveDraft();
                    break;
            }
        }

        if (e.key === 'Escape') {
            this.clearValidationErrors();
        }
    },

    // Xử lý chọn file
    handleFileSelect: function(input, type) {
        const file = input.files[0];
        if (!file) return;

        console.log('File được chọn:', file.name, 'Loại:', type);

        // Kiểm tra kích thước file
        const maxSize = type === 'image' ? this.config.maxFileSize : 10 * 1024 * 1024;
        if (file.size > maxSize) {
            this.showError(`File quá lớn. Kích thước tối đa: ${this.formatFileSize(maxSize)}`);
            input.value = '';
            return;
        }

        // Kiểm tra loại file
        const allowedTypes = type === 'image' ? this.config.allowedImageTypes : this.config.allowedDocTypes;
        const extension = file.name.split('.').pop().toLowerCase();

        if (!allowedTypes.includes(extension)) {
            this.showError(`Loại file không được phép. Chấp nhận: ${allowedTypes.join(', ')}`);
            input.value = '';
            return;
        }

        // Hiển thị xem trước
        this.showFilePreview(file, type);

        // Đánh dấu form có thay đổi
        this.state.isDirty = true;
    },

    // Hiển thị xem trước file
    showFilePreview: function(file, type) {
        const previewId = type + 'Preview';
        const preview = document.getElementById(previewId);

        if (preview) {
            preview.classList.remove('d-none');

            const iconClass = type === 'image' ? 'fa-image' : 'fa-file-pdf';
            const sizeText = this.formatFileSize(file.size);

            preview.innerHTML = `
                <div class="mt-2 p-3 bg-success bg-opacity-10 border border-success rounded">
                    <div class="d-flex align-items-center">
                        <i class="fas ${iconClass} text-success me-2"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold text-success">File mới đã chọn:</div>
                            <div class="small text-muted">${file.name} (${sizeText})</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFilePreview('${type}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        }
    },

    // Khởi tạo bộ đếm ký tự
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

    // Định dạng kích thước file
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    // Cập nhật bảng xem trước
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
                preview.textContent = value || 'Chưa nhập';
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

    // Cập nhật xem trước cho dropdown
    updateSelectPreview: function(selectId, previewId) {
        const select = document.getElementById(selectId);
        const preview = document.getElementById(previewId);

        if (select && preview) {
            const selectedOption = select.options[select.selectedIndex];
            preview.textContent = selectedOption && select.selectedIndex !== 0 ? selectedOption.text : 'Chưa chọn';
            preview.classList.toggle('empty', !selectedOption || select.selectedIndex === 0);
        }
    },

    // Lấy dữ liệu form
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

    // Lưu thiết bị
    saveEquipment: function() {
        document.getElementById('formAction').value = 'save';
        this.submitForm();
    },

    // Lưu nháp
    saveDraft: function() {
        document.getElementById('formAction').value = 'draft';
        this.submitForm();
    },

    // Gửi form
    submitForm: function() {
        const form = document.querySelector(this.config.formSelector);
        if (!form) return;

        this.showLoading(true);
        this.state.isDirty = false;
        form.submit();
    },

    // Xem trước thiết bị
    previewEquipment: function() {
        const modal = new bootstrap.Modal(document.getElementById('fullPreviewModal'));
        const previewContent = this.generateFullPreview();
        document.getElementById('fullPreviewContent').innerHTML = previewContent;
        modal.show();
    },

    // Lấy văn bản được chọn từ dropdown
    getSelectedText: function(selectId) {
        const select = document.getElementById(selectId);
        if (!select || select.selectedIndex === 0) {
            return '<em class="text-muted">Chưa chọn</em>';
        }
        return select.options[select.selectedIndex].text;
    },

    // Tạo nội dung xem trước đầy đủ cho modal
    generateFullPreview: function() {
        const formData = this.getFormData();

        return `
            <div class="row">
                <div class="col-md-8">
                    <h5 class="mb-3">Thông tin thiết bị (đã cập nhật)</h5>
                    <table class="table table-bordered">
                        <tr><th width="30%">Tên thiết bị</th><td>${formData.name || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                        <tr><th>Mã thiết bị</th><td>${formData.code || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                        <tr><th>Nhà sản xuất</th><td>${formData.manufacturer || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                        <tr><th>Model</th><td>${formData.model || '<em class="text-muted">Chưa nhập</em>'}</td></tr>
                    </table>
                    
                    <h6 class="mt-4 mb-3">Vị trí & Phân loại</h6>
                    <table class="table table-bordered">
                        <tr><th width="30%">Ngành</th><td>${this.getSelectedText('industryId')}</td></tr>
                        <tr><th>Xưởng</th><td>${this.getSelectedText('workshopId')}</td></tr>
                        <tr><th>Dòng máy</th><td>${this.getSelectedText('machineTypeId')}</td></tr>
                        <tr><th>Trạng thái</th><td><span class="badge bg-success">${this.getSelectedText('status')}</span></td></tr>
                    </table>
                </div>
            </div>
        `;
    },

    // Hiển thị loading
    showLoading: function(show = true) {
        if (window.CMMS && window.CMMS.showLoading) {
            window.CMMS.showLoading(show);
        }
    },

    // Hiển thị lỗi
    showError: function(message) {
        if (window.CMMS && window.CMMS.showToast) {
            window.CMMS.showToast(message, 'error');
        }
    },

    // Lên lịch tự động lưu
    scheduleAutoSave: function() {
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout);
        }

        this.autoSaveTimeout = setTimeout(() => {
            this.autoSave();
        }, 5000);
    },

    // Tự động lưu nháp
    autoSave: function() {
        if (this.state.isDirty && this.hasChanges()) {
            console.log('Đang tự động lưu nháp...');
            const formData = this.getFormData();
            localStorage.setItem('equipment_edit_draft_' + window.equipmentConfig?.equipmentId, JSON.stringify(formData));
        }
    },

    // Xóa lỗi xác thực
    clearValidationErrors: function() {
        console.log('Xóa lỗi xác thực');
    },

    // Dọn dẹp
    destroy: function() {
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout);
        }
        console.log('EquipmentEditForm đã được hủy');
    }
};

// Các hàm toàn cục cho callback HTML
function saveEquipment() {
    EquipmentEditForm.saveEquipment();
}

function saveDraft() {
    EquipmentEditForm.saveDraft();
}

function previewEquipment() {
    EquipmentEditForm.previewEquipment();
}

function handleFileSelect(input, type) {
    EquipmentEditForm.handleFileSelect(input, type);
}

function clearFilePreview(type) {
    const input = document.getElementById(type + 'File');
    const preview = document.getElementById(type + 'Preview');

    if (input) input.value = '';
    if (preview) {
        preview.classList.add('d-none');
        preview.innerHTML = '';
    }
    EquipmentEditForm.state.isDirty = true;
}

function removeCurrentImage() {
    if (confirm('Bạn có chắc chắn muốn xóa hình ảnh hiện tại?')) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_current_image';
        hiddenInput.value = '1';
        document.getElementById('equipmentForm').appendChild(hiddenInput);

        const currentFile = document.querySelector('.current-file');
        if (currentFile && currentFile.querySelector('img')) {
            currentFile.style.display = 'none';
        }
        EquipmentEditForm.state.isDirty = true;
    }
}

function removeCurrentManual() {
    if (confirm('Bạn có chắc chắn muốn xóa tài liệu hiện tại?')) {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_current_manual';
        hiddenInput.value = '1';
        document.getElementById('equipmentForm').appendChild(hiddenInput);

        const currentFiles = document.querySelectorAll('.current-file');
        currentFiles.forEach(file => {
            if (file.querySelector('.fa-file-pdf')) {
                file.style.display = 'none';
            }
        });
        EquipmentEditForm.state.isDirty = true;
    }
}

// Xử lý sự kiện DOM Content Loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM đã tải, đang khởi tạo form chỉnh sửa thiết bị...');

    const equipmentForm = document.getElementById('equipmentForm');
    if (!equipmentForm) {
        console.log('Không tìm thấy form thiết bị, bỏ qua khởi tạo');
        return;
    }

    if (window.equipmentConfig) {
        Object.assign(EquipmentEditForm.config, window.equipmentConfig);
    }

    EquipmentEditForm.init();
});

// Hiển thị đối tượng chính để debug
window.EquipmentEditForm = EquipmentEditForm;

console.log('JavaScript Form Chỉnh sửa Thiết bị được tải thành công');