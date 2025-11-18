/**
 * 实时预览功能模块
 * 提供查询表单的实时预览和交互反馈
 */

class RealTimePreview {
    constructor() {
        this.previewContainer = document.getElementById('queryPreview');
        this.subjectTypeSelect = document.getElementById('subject_type');
        this.subjectInput = document.getElementById('subject');
        this.inputHelper = document.getElementById('inputHelper');
        this.charCount = document.getElementById('charCount');
        
        // 防抖定时器
        this.debounceTimer = null;
        this.currentRequest = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.updatePreview();
    }
    
    bindEvents() {
        // 主体类型选择变化
        this.subjectTypeSelect.addEventListener('change', () => {
            this.onSubjectTypeChange();
            this.updatePreview();
        });
        
        // 输入内容变化
        this.subjectInput.addEventListener('input', () => {
            this.onSubjectInput();
            this.debouncedUpdatePreview();
        });
        
        // 输入框获得焦点
        this.subjectInput.addEventListener('focus', () => {
            this.onInputFocus();
        });
        
        // 输入框失去焦点
        this.subjectInput.addEventListener('blur', () => {
            this.onInputBlur();
        });
    }
    
    // 防抖更新预览
    debouncedUpdatePreview() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.updatePreview();
        }, 1500); // 1500ms防抖，防止恶意频繁查询
    }
    
    onSubjectTypeChange() {
        const selectedOption = this.subjectTypeSelect.options[this.subjectTypeSelect.selectedIndex];
        const selectedType = selectedOption.value;
        const placeholder = selectedOption.dataset.placeholder || '请输入查询内容';
        
        // 更新输入框placeholder
        this.subjectInput.placeholder = placeholder;
        
        // 更新帮助文本
        if (selectedType) {
            this.inputHelper.textContent = `请输入${selectedOption.text}，格式：${placeholder}`;
        } else {
            this.inputHelper.textContent = '根据选择的主体类型输入相应的查询内容';
        }
        
        // 清空输入框
        this.subjectInput.value = '';
        this.updateCharCount();
    }
    
    onSubjectInput() {
        this.updateCharCount();
        this.validateInput();
    }
    
    onInputFocus() {
        this.subjectInput.parentElement.classList.add('focused');
    }
    
    onInputBlur() {
        this.subjectInput.parentElement.classList.remove('focused');
    }
    
    updateCharCount() {
        const length = this.subjectInput.value.length;
        this.charCount.textContent = length;
        
        // 根据字符数量改变颜色
        if (length > 90) {
            this.charCount.style.color = 'var(--color-error)';
        } else if (length > 80) {
            this.charCount.style.color = 'var(--color-warning)';
        } else {
            this.charCount.style.color = 'var(--color-tertiary-text)';
        }
    }
    
    validateInput() {
        const subjectType = this.subjectTypeSelect.value;
        const subject = this.subjectInput.value.trim();
        
        if (!subjectType || !subject) {
            return { valid: false, message: '' };
        }
        
        let validation = { valid: true, message: '' };
        
        // 根据主体类型进行格式验证
        switch (subjectType) {
            case 'qq':
                validation = this.validateQQ(subject);
                break;
            case 'phone':
                validation = this.validatePhone(subject);
                break;
            case 'email':
                validation = this.validateEmail(subject);
                break;
            case 'wechat':
                validation = this.validateWechat(subject);
                break;
            case 'alipay':
                validation = this.validateAlipay(subject);
                break;
            case 'bank_card':
                validation = this.validateBankCard(subject);
                break;
            case 'id_card':
                validation = this.validateIdCard(subject);
                break;
            case 'domain':
                validation = this.validateDomain(subject);
                break;
            case 'ip':
                validation = this.validateIP(subject);
                break;
            default:
                validation = { valid: true, message: '' };
        }
        
        // 更新输入框状态
        this.updateInputStatus(validation);
        
        return validation;
    }
    
    validateQQ(qq) {
        const qqRegex = /^[1-9][0-9]{4,10}$/;
        if (!qqRegex.test(qq)) {
            return { valid: false, message: 'QQ号码格式不正确，应为5-11位数字' };
        }
        return { valid: true, message: 'QQ号码格式正确' };
    }
    
    validatePhone(phone) {
        const phoneRegex = /^1[3-9]\d{9}$/;
        if (!phoneRegex.test(phone)) {
            return { valid: false, message: '手机号码格式不正确' };
        }
        return { valid: true, message: '手机号码格式正确' };
    }
    
    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            return { valid: false, message: '邮箱地址格式不正确' };
        }
        return { valid: true, message: '邮箱地址格式正确' };
    }
    
    validateWechat(wechat) {
        const wechatRegex = /^[a-zA-Z][a-zA-Z0-9_-]{5,19}$/;
        if (!wechatRegex.test(wechat)) {
            return { valid: false, message: '微信号格式不正确，应以字母开头，6-20位字母数字下划线' };
        }
        return { valid: true, message: '微信号格式正确' };
    }
    
    validateAlipay(alipay) {
        // 支付宝账号可以是手机号或邮箱
        const phoneValidation = this.validatePhone(alipay);
        const emailValidation = this.validateEmail(alipay);
        
        if (!phoneValidation.valid && !emailValidation.valid) {
            return { valid: false, message: '支付宝账号应为手机号或邮箱地址' };
        }
        return { valid: true, message: '支付宝账号格式正确' };
    }
    
    validateBankCard(bankCard) {
        const bankCardRegex = /^\d{16,19}$/;
        if (!bankCardRegex.test(bankCard)) {
            return { valid: false, message: '银行卡号格式不正确，应为16-19位数字' };
        }
        return { valid: true, message: '银行卡号格式正确' };
    }
    
    validateIdCard(idCard) {
        const idCardRegex = /^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/;
        if (!idCardRegex.test(idCard)) {
            return { valid: false, message: '身份证号码格式不正确' };
        }
        return { valid: true, message: '身份证号码格式正确' };
    }
    
    validateDomain(domain) {
        const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/;
        if (!domainRegex.test(domain)) {
            return { valid: false, message: '域名格式不正确' };
        }
        return { valid: true, message: '域名格式正确' };
    }
    
    validateIP(ip) {
        const ipRegex = /^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        if (!ipRegex.test(ip)) {
            return { valid: false, message: 'IP地址格式不正确' };
        }
        return { valid: true, message: 'IP地址格式正确' };
    }
    
    updateInputStatus(validation) {
        const inputGroup = this.subjectInput.parentElement;
        
        // 移除之前的状态类
        inputGroup.classList.remove('has-error', 'has-success');
        
        if (this.subjectInput.value.trim()) {
            if (validation.valid) {
                inputGroup.classList.add('has-success');
            } else {
                inputGroup.classList.add('has-error');
            }
        }
    }
    
    updatePreview() {
        const subjectType = this.subjectTypeSelect.value;
        const subject = this.subjectInput.value.trim();
        
        // 取消之前的请求
        if (this.currentRequest) {
            this.currentRequest.abort();
            this.currentRequest = null;
        }
        
        if (!subjectType && !subject) {
            this.showEmptyPreview();
            return;
        }
        
        const validation = this.validateInput();
        
        // 如果格式不正确或内容为空，只显示格式验证
        if (!subject || !validation.valid) {
            this.showPreviewContent(subjectType, subject, validation, null);
            return;
        }
        
        // 显示加载状态
        this.showLoadingPreview(subjectType, subject, validation);
        
        // 发送AJAX请求查询数据库
        this.performQuery(subjectType, subject, validation);
    }
    
    performQuery(subjectType, subject, validation) {
        const requestData = {
            subject_type: subjectType,
            subject: subject
        };
        
        this.currentRequest = new XMLHttpRequest();
        this.currentRequest.open('POST', 'api/preview_query.php', true);
        this.currentRequest.setRequestHeader('Content-Type', 'application/json');
        
        this.currentRequest.onreadystatechange = () => {
            if (this.currentRequest.readyState === 4) {
                if (this.currentRequest.status === 200) {
                    try {
                        const response = JSON.parse(this.currentRequest.responseText);
                        this.showQueryResult(subjectType, subject, response);
                    } catch (e) {
                        this.showErrorPreview('查询结果解析失败');
                    }
                } else {
                    this.showErrorPreview('查询请求失败，请稍后重试');
                }
                this.currentRequest = null;
            }
        };
        
        this.currentRequest.onerror = () => {
            this.showErrorPreview('网络连接失败');
            this.currentRequest = null;
        };
        
        this.currentRequest.send(JSON.stringify(requestData));
    }
    
    showLoadingPreview(subjectType, subject, validation) {
        this.previewContainer.className = 'query-preview loading';
        
        const selectedOption = this.subjectTypeSelect.options[this.subjectTypeSelect.selectedIndex];
        const typeName = selectedOption.text || '未知类型';
        
        this.previewContainer.innerHTML = `
            <div class="query-result">
                <h3 class="text-heading">查询预览</h3>
                <div class="result-item">
                    <span class="result-label">查询类型：</span>
                    <span class="result-value">${this.escapeHtml(typeName)}</span>
                </div>
                <div class="result-item">
                    <span class="result-label">查询内容：</span>
                    <span class="result-value">${this.escapeHtml(subject)}</span>
                </div>
                <div class="result-item">
                    <span class="result-label">查询状态：</span>
                    <span class="result-value">
                        <i class="fas fa-spinner fa-spin"></i> 正在查询...
                    </span>
                </div>
                <div class="status-info mt-lg">
                    <i class="fas fa-info-circle"></i>
                    <strong>提示：</strong>正在数据库中查询相关记录，请稍候...
                </div>
            </div>
        `;
    }
    
    showQueryResult(subjectType, subject, response) {
        this.previewContainer.className = 'query-preview';
        
        const selectedOption = this.subjectTypeSelect.options[this.subjectTypeSelect.selectedIndex];
        const typeName = selectedOption.text || '未知类型';
        
        let resultHtml = '';
        let statusHtml = '';
        let appealButtonHtml = '';
        
        if (response.success) {
            if (response.found) {
                // 根据黑名单等级设置不同的颜色和图标
                const level = parseInt(response.data.level);
                let levelClass = '';
                let levelColor = '';
                let levelIcon = '';
                let riskLevel = '';
                
                if (level === 1) {
                    // 低风险 - 蓝色
                    levelClass = 'level-low-risk';
                    levelColor = '#1890ff';
                    levelIcon = 'fas fa-info-circle';
                    riskLevel = '低风险';
                } else if (level === 2) {
                    // 中风险 - 黄色
                    levelClass = 'level-medium-risk';
                    levelColor = '#faad14';
                    levelIcon = 'fas fa-exclamation-triangle';
                    riskLevel = '中风险';
                } else if (level === 3) {
                    // 高风险 - 红色
                    levelClass = 'level-high-risk';
                    levelColor = '#ff4d4f';
                    levelIcon = 'fas fa-exclamation-triangle';
                    riskLevel = '高风险';
                } else {
                    // 默认情况
                    levelClass = 'level-unknown';
                    levelColor = '#999999';
                    levelIcon = 'fas fa-question-circle';
                    riskLevel = '未知风险';
                }
                
                // 找到黑名单记录
                resultHtml = `
                    <div class="result-item">
                        <span class="result-label">黑名单等级：</span>
                        <span class="result-value ${levelClass}" style="color: ${levelColor}; font-weight: 600;">
                            <i class="${levelIcon}" style="margin-right: 4px;"></i>
                            ${this.escapeHtml(response.data.level)}级 (${riskLevel})
                        </span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">录入时间：</span>
                        <span class="result-value">${this.escapeHtml(response.data.date)}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">黑名单原因：</span>
                        <span class="result-value">${this.escapeHtml(response.data.note)}</span>
                    </div>
                `;
                statusHtml = `
                    <div class="status-error mt-lg">
                        <i class="${levelIcon}" style="color: ${levelColor};"></i>
                        <strong>风险警告：</strong>该主体已被录入黑名单，请停止任何交易！
                    </div>
                `;
                // 添加操作按钮
                let actionButtonsHtml = '<div class="action-buttons">';
                
                // 如果有图片，添加查看图片按钮
                if (response.data.images && response.data.images.length > 0) {
                    const validImages = response.data.images.filter(img => img.exists);
                    const imageCount = validImages.length;
                    if (imageCount > 0) {
                        // 将图片数据编码为 JSON 字符串
                        const imagesJson = JSON.stringify(validImages).replace(/"/g, '&quot;');
                        actionButtonsHtml += `
                            <button type="button" class="btn-action btn-view-images" id="viewImagesBtn" data-images="${imagesJson}">
                                <i class="fas fa-images"></i>
                                <span>查看图片</span>
                                <span class="badge">${imageCount}</span>
                            </button>
                        `;
                    }
                }
                
                // 申诉处理按钮
                actionButtonsHtml += `
                    <a href="http://wpa.qq.com/msgrd?v=3&uin=406845294&site=qq&menu=yes" 
                       target="_blank" 
                       class="btn-action btn-appeal">
                        <i class="fas fa-comment-dots"></i>
                        <span>申诉处理</span>
                    </a>
                `;
                actionButtonsHtml += '</div>';
                
                appealButtonHtml = actionButtonsHtml;
            } else {
                // 未找到黑名单记录
                statusHtml = `
                    <div class="status-success mt-lg">
                        <i class="fas fa-check-circle"></i>
                        <strong>查询结果：</strong>该主体尚未被录入黑名单，但我们不能保证交易绝对安全
                    </div>
                `;
            }
        } else {
            // 查询出错
            statusHtml = `
                <div class="status-error mt-lg">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>查询错误：</strong>${this.escapeHtml(response.error)}
                </div>
            `;
        }
        
        this.previewContainer.innerHTML = `
            <div class="query-result">
                <h3 class="text-heading">查询预览</h3>
                <div class="result-item">
                    <span class="result-label">查询类型：</span>
                    <span class="result-value">${this.escapeHtml(typeName)}</span>
                </div>
                <div class="result-item">
                    <span class="result-label">查询内容：</span>
                    <span class="result-value">${this.escapeHtml(subject)}</span>
                </div>
                ${resultHtml}
                ${statusHtml}
            </div>
            ${appealButtonHtml}
        `;
    }
    
    showErrorPreview(errorMessage) {
        this.previewContainer.className = 'query-preview error';
        this.previewContainer.innerHTML = `
            <div class="query-result">
                <div class="status-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>查询失败：</strong>${this.escapeHtml(errorMessage)}
                </div>
            </div>
        `;
    }
    
    showEmptyPreview() {
        this.previewContainer.className = 'query-preview empty';
        this.previewContainer.innerHTML = `
            <div style="text-align: center; padding: var(--spacing-huge) 0;">
                <div class="text-caption">查询结果将在此处实时显示</div>
            </div>
        `;
    }
    
    showPreviewContent(subjectType, subject, validation, queryResult = null) {
        this.previewContainer.className = 'query-preview';
        
        const selectedOption = this.subjectTypeSelect.options[this.subjectTypeSelect.selectedIndex];
        const typeName = selectedOption.text || '未知类型';
        
        let statusHtml = '';
        if (subject) {
            if (validation.valid) {
                statusHtml = `
                    <div class="status-info mt-lg">
                        <i class="fas fa-info-circle"></i>
                        <strong>格式验证：</strong>${validation.message}
                    </div>
                `;
            } else {
                statusHtml = `
                    <div class="status-error mt-lg">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>格式错误：</strong>${validation.message}
                    </div>
                `;
            }
        }
        
        this.previewContainer.innerHTML = `
            <div class="query-result">
                <h3 class="text-heading">查询预览</h3>
                <div class="result-item">
                    <span class="result-label">查询类型：</span>
                    <span class="result-value">${this.escapeHtml(typeName)}</span>
                </div>
                <div class="result-item">
                    <span class="result-label">查询内容：</span>
                    <span class="result-value">${subject ? this.escapeHtml(subject) : '<em style="color: var(--color-tertiary-text);">请输入查询内容</em>'}</span>
                </div>
                <div class="result-item">
                    <span class="result-label">输入状态：</span>
                    <span class="result-value ${validation.valid ? 'success' : 'error'}">
                        ${subject ? (validation.valid ? '格式正确' : '格式错误') : '等待输入'}
                    </span>
                </div>
                ${statusHtml}
                ${subject && validation.valid ? `
                    <div class="status-info mt-lg">
                        <i class="fas fa-search"></i>
                        <strong>提示：</strong>点击"开始查询"按钮执行查询操作
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    new RealTimePreview();
});