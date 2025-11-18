
class AddBlacklistForm {
    constructor() {
        this.form = document.querySelector('.form-modern');
        this.subjectTypeSelect = document.getElementById('subject_type');
        this.subjectInput = document.getElementById('qq');
        this.subjectHelp = document.getElementById('subject-help');
        this.subjectIcon = document.getElementById('subject-icon');
        this.submitBtn = document.querySelector('button[type="submit"]');
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupFormValidation();
        this.updateSubjectPlaceholder();
    }
    
    bindEvents() {
        
        this.subjectTypeSelect.addEventListener('change', () => {
            this.updateSubjectPlaceholder();
        });

        this.form.addEventListener('submit', (e) => {
            this.handleSubmit(e);
        });

        this.setupFocusEffects();

        this.setupRealTimeValidation();
    }
    
    updateSubjectPlaceholder() {
        const selectedOption = this.subjectTypeSelect.options[this.subjectTypeSelect.selectedIndex];
        
        if (selectedOption.value) {
            
            this.subjectInput.disabled = false;
            this.subjectInput.focus();

            const placeholder = selectedOption.getAttribute('data-placeholder') || '请输入黑名单内容';
            this.subjectInput.placeholder = placeholder;

            this.subjectHelp.textContent = `请输入${selectedOption.text}的具体内容`;

            this.updateSubjectIcon(selectedOption.value);

            if (this.subjectInput.value && this.subjectInput.getAttribute('data-type') !== selectedOption.value) {
                this.subjectInput.value = '';
            }
            this.subjectInput.setAttribute('data-type', selectedOption.value);

            this.subjectInput.parentElement.classList.add('input-group-focus');
        } else {
            
            this.subjectInput.disabled = true;
            this.subjectInput.placeholder = '请先选择黑名单类型';
            this.subjectInput.value = '';
            this.subjectHelp.textContent = '请先选择黑名单类型，然后输入对应的黑名单内容';
            this.subjectInput.removeAttribute('data-type');
            this.subjectIcon.className = 'fas fa-user';
            this.subjectInput.parentElement.classList.remove('input-group-focus');
        }
    }
    
    updateSubjectIcon(typeKey) {
        const iconMap = {
            'qq': 'fab fa-qq',
            'phone': 'fas fa-phone',
            'email': 'fas fa-envelope',
            'wechat': 'fab fa-weixin',
            'website': 'fas fa-globe',
            'ip': 'fas fa-network-wired'
        };
        
        this.subjectIcon.className = iconMap[typeKey] || 'fas fa-user';
    }
    
    setupFocusEffects() {
        const inputs = this.form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.closest('.form-group').classList.add('form-group-focus');
                if (this.closest('.input-group')) {
                    this.closest('.input-group').classList.add('input-group-focus');
                }
            });
            
            input.addEventListener('blur', function() {
                this.closest('.form-group').classList.remove('form-group-focus');
                if (this.closest('.input-group')) {
                    this.closest('.input-group').classList.remove('input-group-focus');
                }
            });
        });
    }
    
    setupRealTimeValidation() {
        const inputs = this.form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                this.validateField(input);
            });
            
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
        });
    }
    
    validateField(field) {
        const value = field.value.trim();
        const isValid = field.checkValidity() && value !== '';

        field.classList.remove('is-valid', 'is-invalid');

        if (field.hasAttribute('required') && value === '') {
            
            return;
        }
        
        if (isValid) {
            field.classList.add('is-valid');
        } else {
            field.classList.add('is-invalid');
        }
    }
    
    setupFormValidation() {
        
        this.subjectInput.addEventListener('input', () => {
            const value = this.subjectInput.value.trim();
            const typeKey = this.subjectInput.getAttribute('data-type');
            
            if (value && typeKey) {
                const isValid = this.validateSubjectByType(value, typeKey);
                this.subjectInput.setCustomValidity(isValid ? '' : '输入格式不正确');
            }
        });
    }
    
    validateSubjectByType(value, typeKey) {
        switch (typeKey) {
            case 'qq':
                return /^\d{5,20}$/.test(value);
            case 'phone':
                return /^1[3-9]\d{9}$/.test(value);
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            case 'ip':
                return /^(\d{1,3}\.){3}\d{1,3}$/.test(value);
            case 'website':
                return /^https?:\/\/.+/.test(value) || /^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/.test(value);
            default:
                return value.length > 0;
        }
    }
    
    handleSubmit(e) {
        
        this.setLoadingState(true);

        const isValid = this.validateForm();
        
        if (!isValid) {
            e.preventDefault();
            this.setLoadingState(false);

            const firstInvalid = this.form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
            
            this.showMessage('请检查表单中的错误信息', 'error');
        }
    }
    
    validateForm() {
        let isValid = true;
        const inputs = this.form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!input.checkValidity() || input.value.trim() === '') {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
        });
        
        return isValid;
    }
    
    setLoadingState(loading) {
        if (loading) {
            this.submitBtn.disabled = true;
            this.submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 提交中...';
        } else {
            this.submitBtn.disabled = false;
            this.submitBtn.innerHTML = '<i class="fas fa-save"></i> 添加记录';
        }
    }
    
    showMessage(message, type = 'info') {
        
        const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
        const iconClass = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="${iconClass}"></i> ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;

        const alertContainer = document.createElement('div');
        alertContainer.innerHTML = alertHtml;
        this.form.insertBefore(alertContainer.firstElementChild, this.form.firstChild);

        setTimeout(() => {
            const alert = this.form.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 3000);
    }
}

function toggleSubjectTypeHelp() {
    const helpContent = document.getElementById('subject-type-help');
    const toggleIcon = document.getElementById('help-toggle-icon');
    
    if (helpContent.style.display === 'none') {
        
        helpContent.style.display = 'block';
        toggleIcon.style.transform = 'rotate(180deg)';
        toggleIcon.classList.remove('fa-chevron-down');
        toggleIcon.classList.add('fa-chevron-up');

        helpContent.style.opacity = '0';
        helpContent.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            helpContent.style.transition = 'all 0.3s ease';
            helpContent.style.opacity = '1';
            helpContent.style.transform = 'translateY(0)';
        }, 10);
    } else {
        
        helpContent.style.transition = 'all 0.3s ease';
        helpContent.style.opacity = '0';
        helpContent.style.transform = 'translateY(-10px)';
        
        setTimeout(() => {
            helpContent.style.display = 'none';
            toggleIcon.style.transform = 'rotate(0deg)';
            toggleIcon.classList.remove('fa-chevron-up');
            toggleIcon.classList.add('fa-chevron-down');
        }, 300);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    new AddBlacklistForm();
});