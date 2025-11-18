/**
 * 内联帮助系统
 * 提供智能的上下文帮助和操作指导
 */

class InlineHelpSystem {
    constructor() {
        this.helpData = {
            subject_types: {
                'qq': {
                    name: 'QQ号码',
                    format: '5-11位数字',
                    example: '123456789',
                    tips: [
                        '不包含字母或特殊字符',
                        '首位不能为0',
                        '支持大小写不敏感查询'
                    ],
                    validation: 'QQ号码必须是5-11位数字，首位不能为0'
                },
                'phone': {
                    name: '手机号码',
                    format: '11位数字，1开头',
                    example: '13812345678',
                    tips: [
                        '支持中国大陆手机号码',
                        '第二位数字为3-9',
                        '自动验证运营商号段'
                    ],
                    validation: '手机号码必须是11位数字，以1开头，第二位为3-9'
                },
                'email': {
                    name: '邮箱地址',
                    format: '标准邮箱格式',
                    example: 'user@example.com',
                    tips: [
                        '支持国内外邮箱服务商',
                        '自动验证邮箱格式',
                        '大小写不敏感'
                    ],
                    validation: '邮箱地址必须包含@符号和有效的域名'
                },
                'wechat': {
                    name: '微信号',
                    format: '6-20位字母数字下划线',
                    example: 'wechat_123',
                    tips: [
                        '必须以字母开头',
                        '可包含字母、数字、下划线、减号',
                        '长度6-20位字符'
                    ],
                    validation: '微信号必须以字母开头，6-20位字母数字下划线减号'
                },
                'alipay': {
                    name: '支付宝账号',
                    format: '手机号或邮箱',
                    example: '13812345678 或 user@example.com',
                    tips: [
                        '支持手机号码登录',
                        '支持邮箱地址登录',
                        '自动识别账号类型'
                    ],
                    validation: '支付宝账号必须是有效的手机号码或邮箱地址'
                },
                'bank_card': {
                    name: '银行卡号',
                    format: '16-19位数字',
                    example: '6222021234567890',
                    tips: [
                        '支持储蓄卡和信用卡',
                        '自动验证卡号格式',
                        '不验证卡号真实性'
                    ],
                    validation: '银行卡号必须是16-19位数字'
                },
                'id_card': {
                    name: '身份证号码',
                    format: '18位身份证号',
                    example: '110101199001011234',
                    tips: [
                        '支持18位二代身份证',
                        '自动验证格式和校验位',
                        '末位可以是数字或X'
                    ],
                    validation: '身份证号码必须是18位，符合国家标准格式'
                },
                'domain': {
                    name: '域名',
                    format: '标准域名格式',
                    example: 'example.com',
                    tips: [
                        '支持国际域名和中文域名',
                        '自动验证域名格式',
                        '不验证域名是否存在'
                    ],
                    validation: '域名必须符合国际标准格式'
                },
                'ip': {
                    name: 'IP地址',
                    format: 'IPv4地址格式',
                    example: '192.168.1.1',
                    tips: [
                        '支持IPv4地址格式',
                        '每段数字0-255',
                        '自动验证地址有效性'
                    ],
                    validation: 'IP地址必须是有效的IPv4格式'
                }
            },
            
            querySteps: [
                {
                    step: 1,
                    title: '选择主体类型',
                    description: '从下拉菜单中选择您要查询的主体类型',
                    tips: '不同类型有不同的格式要求，选择正确的类型很重要'
                },
                {
                    step: 2,
                    title: '输入查询内容',
                    description: '根据选择的类型输入相应的查询内容',
                    tips: '系统会实时验证输入格式，确保查询的准确性'
                },
                {
                    step: 3,
                    title: '查看实时预览',
                    description: '右侧预览区会显示查询配置和格式验证结果',
                    tips: '绿色表示格式正确，红色表示需要修正'
                },
                {
                    step: 4,
                    title: '执行查询',
                    description: '确认信息无误后点击"开始查询"按钮',
                    tips: '查询结果会立即显示在预览区域'
                }
            ]
        };
        
        this.currentStep = 0;
        this.init();
    }
    
    init() {
        this.createHelpElements();
        this.bindEvents();
        this.updateStepGuide();
    }
    
    createHelpElements() {
        // 创建步骤指导元素
        const stepGuideHtml = `
            <div class="help-section" id="stepGuide">
                <h3 class="text-heading">
                    <i class="fas fa-route"></i>
                    操作步骤
                </h3>
                <div class="step-guide-container" id="stepGuideContainer">
                    <!-- 步骤内容将动态生成 -->
                </div>
            </div>
        `;
        
        // 创建动态提示元素
        const dynamicTipsHtml = `
            <div class="help-section" id="dynamicTips">
                <h3 class="text-heading">
                    <i class="fas fa-lightbulb"></i>
                    智能提示
                </h3>
                <div class="dynamic-tips-container" id="dynamicTipsContainer">
                    <div class="tip-item">
                        <i class="fas fa-info-circle"></i>
                        <span>选择主体类型开始查询</span>
                    </div>
                </div>
            </div>
        `;
        
        // 插入到帮助区域
        const helpSections = document.querySelectorAll('.help-section');
        if (helpSections.length > 0) {
            helpSections[0].insertAdjacentHTML('beforebegin', stepGuideHtml);
            helpSections[0].insertAdjacentHTML('beforebegin', dynamicTipsHtml);
        }
    }
    
    bindEvents() {
        // 监听主体类型选择
        const subjectTypeSelect = document.getElementById('subject_type');
        if (subjectTypeSelect) {
            subjectTypeSelect.addEventListener('change', () => {
                this.onSubjectTypeChange();
            });
        }
        
        // 监听输入内容变化
        const subjectInput = document.getElementById('subject');
        if (subjectInput) {
            subjectInput.addEventListener('input', () => {
                this.onSubjectInput();
            });
            
            subjectInput.addEventListener('focus', () => {
                this.onInputFocus();
            });
        }
        
        // 监听表单提交
        const queryForm = document.getElementById('queryForm');
        if (queryForm) {
            queryForm.addEventListener('submit', () => {
                this.onFormSubmit();
            });
        }
    }
    
    onSubjectTypeChange() {
        const subjectTypeSelect = document.getElementById('subject_type');
        const selectedType = subjectTypeSelect.value;
        
        if (selectedType) {
            this.currentStep = 1;
            this.showTypeSpecificHelp(selectedType);
        } else {
            this.currentStep = 0;
            this.showDefaultHelp();
        }
        
        this.updateStepGuide();
    }
    
    onSubjectInput() {
        const subjectInput = document.getElementById('subject');
        const subjectTypeSelect = document.getElementById('subject_type');
        
        if (subjectInput.value.trim() && subjectTypeSelect.value) {
            this.currentStep = 2;
            this.showInputHelp();
        } else if (subjectTypeSelect.value) {
            this.currentStep = 1;
            this.showTypeSpecificHelp(subjectTypeSelect.value);
        }
        
        this.updateStepGuide();
    }
    
    onInputFocus() {
        const subjectTypeSelect = document.getElementById('subject_type');
        if (subjectTypeSelect.value) {
            this.showInputFocusHelp(subjectTypeSelect.value);
        }
    }
    
    onFormSubmit() {
        this.currentStep = 3;
        this.showSubmitHelp();
        this.updateStepGuide();
    }
    
    showDefaultHelp() {
        const container = document.getElementById('dynamicTipsContainer');
        if (container) {
            container.innerHTML = `
                <div class="tip-item">
                    <i class="fas fa-info-circle"></i>
                    <span>请先选择要查询的主体类型</span>
                </div>
                <div class="tip-item">
                    <i class="fas fa-list"></i>
                    <span>系统支持多种主体类型查询</span>
                </div>
            `;
        }
    }
    
    showTypeSpecificHelp(selectedType) {
        const helpInfo = this.helpData.subject_types[selectedType];
        if (!helpInfo) return;
        
        const container = document.getElementById('dynamicTipsContainer');
        if (container) {
            const tipsHtml = helpInfo.tips.map(tip => `
                <div class="tip-item">
                    <i class="fas fa-check-circle"></i>
                    <span>${tip}</span>
                </div>
            `).join('');
            
            container.innerHTML = `
                <div class="tip-item highlight">
                    <i class="fas fa-tag"></i>
                    <span><strong>${helpInfo.name}</strong> - ${helpInfo.format}</span>
                </div>
                <div class="tip-item">
                    <i class="fas fa-example"></i>
                    <span>示例：${helpInfo.example}</span>
                </div>
                ${tipsHtml}
            `;
        }
    }
    
    showInputHelp() {
        const container = document.getElementById('dynamicTipsContainer');
        if (container) {
            container.innerHTML = `
                <div class="tip-item success">
                    <i class="fas fa-keyboard"></i>
                    <span>正在输入查询内容...</span>
                </div>
                <div class="tip-item">
                    <i class="fas fa-eye"></i>
                    <span>右侧预览区显示实时验证结果</span>
                </div>
                <div class="tip-item">
                    <i class="fas fa-search"></i>
                    <span>格式正确后即可执行查询</span>
                </div>
            `;
        }
    }
    
    showInputFocusHelp(selectedType) {
        const helpInfo = this.helpData.subject_types[selectedType];
        if (!helpInfo) return;
        
        // 显示格式提示气泡
        this.showFormatTooltip(helpInfo);
    }
    
    showSubmitHelp() {
        const container = document.getElementById('dynamicTipsContainer');
        if (container) {
            container.innerHTML = `
                <div class="tip-item processing">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>正在执行查询...</span>
                </div>
                <div class="tip-item">
                    <i class="fas fa-clock"></i>
                    <span>查询结果即将显示</span>
                </div>
            `;
        }
    }
    
    showFormatTooltip(helpInfo) {
        const subjectInput = document.getElementById('subject');
        if (!subjectInput) return;
        
        // 移除已存在的提示
        const existingTooltip = document.querySelector('.format-tooltip');
        if (existingTooltip) {
            existingTooltip.remove();
        }
        
        // 创建新的提示
        const tooltip = document.createElement('div');
        tooltip.className = 'format-tooltip';
        tooltip.innerHTML = `
            <div class="tooltip-content">
                <div class="tooltip-title">${helpInfo.name}格式要求</div>
                <div class="tooltip-format">${helpInfo.format}</div>
                <div class="tooltip-example">示例：${helpInfo.example}</div>
            </div>
        `;
        
        // 定位并显示提示
        const inputRect = subjectInput.getBoundingClientRect();
        tooltip.style.position = 'fixed';
        tooltip.style.top = (inputRect.bottom + 5) + 'px';
        tooltip.style.left = inputRect.left + 'px';
        tooltip.style.zIndex = '1000';
        
        document.body.appendChild(tooltip);
        
        // 3秒后自动隐藏
        setTimeout(() => {
            if (tooltip.parentNode) {
                tooltip.remove();
            }
        }, 3000);
    }
    
    updateStepGuide() {
        const container = document.getElementById('stepGuideContainer');
        if (!container) return;
        
        const stepsHtml = this.helpData.querySteps.map((step, index) => {
            const isActive = index === this.currentStep;
            const isCompleted = index < this.currentStep;
            
            return `
                <div class="step-item ${isActive ? 'active' : ''} ${isCompleted ? 'completed' : ''}">
                    <div class="step-number">
                        ${isCompleted ? '<i class="fas fa-check"></i>' : step.step}
                    </div>
                    <div class="step-content">
                        <div class="step-title">${step.title}</div>
                        <div class="step-description">${step.description}</div>
                        ${isActive ? `<div class="step-tips">${step.tips}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        container.innerHTML = stepsHtml;
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    new InlineHelpSystem();
});