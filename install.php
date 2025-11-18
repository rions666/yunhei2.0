<?php
/**
 * 系统安装引导页面
 * 引导用户完成系统安装
 */

// 开启错误显示（仅在安装时）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 启动session用于安装流程验证
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 引入安装检测
require_once __DIR__ . '/install_check.php';

// 辅助函数：生成绝对URL，支持IP和域名访问
function get_install_url($step = null) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base_path = ($script_path === '/' || $script_path === '\\') ? '' : rtrim($script_path, '/');
    $url = $protocol . $host . $base_path . '/install.php';
    if ($step !== null) {
        $url .= '?step=' . intval($step);
    }
    return $url;
}

// 处理安装步骤（必须在检查安装状态之前获取）
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// 安装流程安全验证
// 定义安装流程token的session key
define('INSTALL_TOKEN_KEY', 'install_flow_token');
define('INSTALL_STEP_KEY', 'install_current_step');

// 初始化安装流程验证
$install_token_valid = false;
$install_step_valid = false;

// 步骤4需要特殊验证：必须从步骤3正常跳转过来
if ($step == 4) {
    // 检查是否有有效的安装token（由步骤3生成）
    if (isset($_SESSION[INSTALL_TOKEN_KEY]) && isset($_SESSION[INSTALL_STEP_KEY])) {
        $token = $_SESSION[INSTALL_TOKEN_KEY];
        $stored_step = $_SESSION[INSTALL_STEP_KEY];
        
        // 验证token格式和时间戳（token有效期5分钟）
        if (is_string($token) && strlen($token) >= 32 && $stored_step == 3) {
            // 提取token中的时间戳
            $token_parts = explode('_', $token);
            if (count($token_parts) >= 2) {
                $token_time = intval(end($token_parts));
                $current_time = time();
                
                // 验证token是否在有效期内（5分钟 = 300秒）
                if (($current_time - $token_time) <= 300) {
                    $install_token_valid = true;
                    $install_step_valid = true;
                    
                    // 验证成功后立即清除token，防止重复使用
                    unset($_SESSION[INSTALL_TOKEN_KEY]);
                    unset($_SESSION[INSTALL_STEP_KEY]);
                }
            }
        }
    }
    
    // 如果没有有效的token，不允许访问步骤4
    if (!$install_token_valid) {
        // 记录未授权访问尝试
        error_log('Install security: Unauthorized access to step 4 from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // 清除可能存在的无效token
        unset($_SESSION[INSTALL_TOKEN_KEY]);
        unset($_SESSION[INSTALL_STEP_KEY]);
    }
}

// 如果已安装，且不是步骤4（完成安装页面），显示提示信息
// 步骤4允许显示，但需要token验证
$is_already_installed = is_installed();
$should_show_installed_message = $is_already_installed && $step != 4;

$error = '';
$success = '';

// 步骤1: 环境检测
// 步骤2: 数据库配置
// 步骤3: 执行安装
// 步骤4: 完成安装

// 检查环境要求
function check_environment() {
    $checks = [
        'php_version' => [
            'name' => 'PHP版本',
            'required' => '7.0.0',
            'current' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.0.0', '>=')
        ],
        'mysqli' => [
            'name' => 'MySQLi扩展',
            'required' => '已安装',
            'current' => extension_loaded('mysqli') ? '已安装' : '未安装',
            'status' => extension_loaded('mysqli')
        ],
        'json' => [
            'name' => 'JSON扩展',
            'required' => '已安装',
            'current' => extension_loaded('json') ? '已安装' : '未安装',
            'status' => extension_loaded('json')
        ],
        'session' => [
            'name' => 'Session扩展',
            'required' => '已安装',
            'current' => extension_loaded('session') ? '已安装' : '未安装',
            'status' => extension_loaded('session')
        ],
        'write_permission' => [
            'name' => '写入权限',
            'required' => '可写',
            'current' => is_writable(__DIR__) ? '可写' : '不可写',
            'status' => is_writable(__DIR__)
        ],
        'config_write' => [
            'name' => '配置文件权限',
            'required' => '可写',
            'current' => (file_exists(__DIR__ . '/config.php') ? (is_writable(__DIR__ . '/config.php') ? '可写' : '不可写') : (is_writable(__DIR__) ? '可创建' : '不可创建')),
            'status' => (file_exists(__DIR__ . '/config.php') ? is_writable(__DIR__ . '/config.php') : is_writable(__DIR__))
        ],
    ];
    
    return $checks;
}

$env_checks = check_environment();
$env_ok = true;
foreach ($env_checks as $check) {
    if (!$check['status']) {
        $env_ok = false;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 黑名单查询系统</title>
    <link href="./assets/css/design-system.css" rel="stylesheet">
    <link href="./assets/css/font-awesome.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .install-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 40px;
            text-align: center;
        }
        
        .install-header h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .install-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .install-body {
            padding: 40px;
        }
        
        .step-indicator {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 40px;
        }
        
        .step-item {
            background: #fff;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .step-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: transparent;
            transition: all 0.3s ease;
        }
        
        .step-item:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .step-item.pending {
            background: #f9fafb;
            border-color: #e5e7eb;
        }
        
        .step-item.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.15);
        }
        
        .step-item.active::before {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .step-item.completed {
            border-color: #10b981;
            background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
        }
        
        .step-item.completed::before {
            background: #10b981;
        }
        
        .step-number-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        
        .step-number {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.125rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .step-item.active .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transform: scale(1.1);
        }
        
        .step-item.completed .step-number {
            background: #10b981;
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .step-item.completed .step-number {
            position: relative;
        }
        
        .step-item.completed .step-number::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.25rem;
            z-index: 2;
        }
        
        .step-item.completed .step-number::before {
            content: attr(data-step);
            opacity: 0;
            visibility: hidden;
        }
        
        .step-item.completed .step-number {
            font-size: 0;
        }
        
        .step-title {
            font-size: 0.9375rem;
            color: #6b7280;
            font-weight: 600;
            margin-top: 8px;
            transition: color 0.3s ease;
        }
        
        .step-item.active .step-title {
            color: #667eea;
            font-weight: 700;
        }
        
        .step-item.completed .step-title {
            color: #10b981;
        }
        
        .step-description {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 4px;
            display: none;
        }
        
        .step-item.active .step-description {
            display: block;
            color: #667eea;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .step-indicator {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .step-item {
                padding: 16px;
            }
            
            .step-number {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .step-title {
                font-size: 0.875rem;
            }
        }
        
        .step-content {
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.9375rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-helper {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-top: 6px;
        }
        
        .check-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .check-item {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .check-item.success {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .check-item.error {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .check-item i {
            margin-right: 12px;
            font-size: 1.25rem;
        }
        
        .check-item.success i {
            color: #10b981;
        }
        
        .check-item.error i {
            color: #ef4444;
        }
        
        .check-label {
            flex: 1;
            font-weight: 500;
            color: #374151;
        }
        
        .check-value {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .check-item.success .check-value {
            color: #10b981;
            font-weight: 600;
        }
        
        .check-item.error .check-value {
            color: #ef4444;
            font-weight: 600;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #667eea;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1><i class="fas fa-cog"></i> 系统安装</h1>
            <p>欢迎使用黑名单查询系统安装向导</p>
        </div>
        
        <div class="install-body">
            <?php if ($should_show_installed_message): ?>
            <!-- 已安装提示（仅在非步骤4时显示） -->
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
                <span>系统已安装完成！</span>
            </div>
            <div style="text-align: center; padding: 40px 0;">
                <p style="margin-bottom: 20px; color: #6b7280;">系统已经安装完成，您可以：</p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="./index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        访问首页
                    </a>
                    <a href="./admin/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i>
                        登录后台
                    </a>
                </div>
                <p style="margin-top: 30px; font-size: 0.875rem; color: #9ca3af;">
                    如需重新安装，请删除 <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">install.lock</code> 和 <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">config.php</code> 文件
                </p>
            </div>
            <?php else: ?>
            <!-- 步骤指示器 -->
            <div class="step-indicator">
                <div class="step-item <?php 
                    if ($step > 1) echo 'completed';
                    elseif ($step == 1) echo 'active';
                    else echo 'pending';
                ?>">
                    <div class="step-number-wrapper">
                        <div class="step-number" data-step="1">1</div>
                    </div>
                    <div class="step-title">环境检测</div>
                
                </div>
                <div class="step-item <?php 
                    if ($step > 2) echo 'completed';
                    elseif ($step == 2) echo 'active';
                    else echo 'pending';
                ?>">
                    <div class="step-number-wrapper">
                        <div class="step-number" data-step="2">2</div>
                    </div>
                    <div class="step-title">数据库配置</div>
                
                </div>
                <div class="step-item <?php 
                    if ($step > 3) echo 'completed';
                    elseif ($step == 3) echo 'active';
                    else echo 'pending';
                ?>">
                    <div class="step-number-wrapper">
                        <div class="step-number" data-step="3">3</div>
                    </div>
                    <div class="step-title">执行安装</div>
                  
                </div>
                <div class="step-item <?php 
                    if ($step >= 4) echo 'completed';
                    elseif ($step == 4) echo 'active';
                    else echo 'pending';
                ?>">
                    <div class="step-number-wrapper">
                        <div class="step-number" data-step="4">4</div>
                    </div>
                    <div class="step-title">完成安装</div>
                
                </div>
            </div>
            
            <!-- 步骤内容 -->
            <div class="step-content">
                <?php if ($step == 1): ?>
                    <!-- 步骤1: 环境检测 -->
                    <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.5rem; color: #374151;">环境检测</h2>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <ul class="check-list">
                        <?php foreach ($env_checks as $key => $check): ?>
                        <li class="check-item <?php echo $check['status'] ? 'success' : 'error'; ?>">
                            <i class="fas <?php echo $check['status'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <span class="check-label"><?php echo htmlspecialchars($check['name']); ?></span>
                            <span class="check-value"><?php echo htmlspecialchars($check['current']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="action-buttons">
                        <?php if ($env_ok): ?>
                        <a href="?step=2" class="btn btn-primary">
                            下一步
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <?php else: ?>
                        <button type="button" class="btn btn-primary" disabled>
                            环境检测未通过
                        </button>
                        <?php endif; ?>
                    </div>
                
                <?php elseif ($step == 2): ?>
                    <!-- 步骤2: 数据库配置 -->
                    <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.5rem; color: #374151;">数据库配置</h2>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div id="test-result" style="margin-bottom: 20px;"></div>
                    
                    <form method="post" action="?step=3" id="dbConfigForm">
                        <div class="form-group">
                            <label class="form-label">数据库地址 *</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                            <div class="form-helper">通常为 localhost 或 127.0.0.1</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">数据库端口 *</label>
                            <input type="text" name="db_port" class="form-control" value="3306" required>
                            <div class="form-helper">MySQL 默认端口为 3306</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">数据库名称 *</label>
                            <input type="text" name="db_name" class="form-control" placeholder="blackcloud" required>
                            <div class="form-helper">请先创建数据库，或使用现有数据库</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">数据库用户名 *</label>
                            <input type="text" name="db_user" class="form-control" required>
                            <div class="form-helper">数据库访问用户名</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">数据库密码</label>
                            <input type="password" name="db_pass" class="form-control">
                            <div class="form-helper">数据库访问密码（如无密码请留空）</div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="?step=1" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                上一步
                            </a>
                            <button type="button" onclick="testConnection()" class="btn btn-secondary" style="background: #10b981;">
                                <i class="fas fa-plug"></i>
                                测试连接
                            </button>
                            <button type="submit" class="btn btn-primary">
                                下一步
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                    
                    <script>
                    // 获取install_process.php的完整URL
                    const installProcessUrl = <?php 
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
                        $host = $_SERVER['HTTP_HOST'];
                        $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                        $base_path = ($script_path === '/' || $script_path === '\\') ? '' : rtrim($script_path, '/');
                        $process_url = $protocol . $host . $base_path . '/install_process.php';
                        echo json_encode($process_url);
                    ?>;
                    
                    function testConnection() {
                        const form = document.getElementById('dbConfigForm');
                        const formData = new URLSearchParams();
                        
                        // 手动获取表单字段值
                        formData.append('db_host', form.querySelector('[name="db_host"]').value.trim());
                        formData.append('db_port', form.querySelector('[name="db_port"]').value.trim() || '3306');
                        formData.append('db_name', form.querySelector('[name="db_name"]').value.trim());
                        formData.append('db_user', form.querySelector('[name="db_user"]').value.trim());
                        formData.append('db_pass', form.querySelector('[name="db_pass"]').value || '');
                        
                        // 验证必填项
                        const db_host = formData.get('db_host');
                        const db_name = formData.get('db_name');
                        const db_user = formData.get('db_user');
                        
                        if (!db_host || !db_name || !db_user) {
                            const resultDiv = document.getElementById('test-result');
                            resultDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> 请填写完整的数据库配置信息</div>';
                            return;
                        }
                        
                        const resultDiv = document.getElementById('test-result');
                        resultDiv.innerHTML = '<div class="alert" style="background: #fef3c7; border-color: #f59e0b; color: #92400e;"><i class="fas fa-spinner fa-spin"></i> 正在测试连接...</div>';
                        
                        fetch(installProcessUrl + '?action=test', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: formData.toString()
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('HTTP错误: ' + response.status);
                            }
                            return response.text().then(text => {
                                if (!text || text.trim() === '') {
                                    throw new Error('服务器返回空响应');
                                }
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('JSON解析失败，响应内容:', text);
                                    throw new Error('服务器响应格式错误，响应内容: ' + text.substring(0, 100));
                                }
                            });
                        })
                        .then(data => {
                            if (data.success) {
                                resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> 数据库连接成功！' + (data.data && data.data.version ? ' (MySQL ' + data.data.version + ')' : '') + '</div>';
                            } else {
                                resultDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> 数据库连接失败：' + (data.message || '未知错误') + '</div>';
                            }
                        })
                        .catch(error => {
                            resultDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> 测试失败：' + error.message + '</div>';
                            console.error('测试连接错误:', error);
                        });
                    }
                    </script>
                
                <?php elseif ($step == 3): ?>
                    <!-- 步骤3: 执行安装 -->
                    <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.5rem; color: #374151;">执行安装</h2>
                    <p style="color: #6b7280; margin-bottom: 20px;">正在初始化数据库，请稍候...</p>
                    <div id="install-progress" style="display: block;">
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 20px;">
                            <div id="progress-bar" style="background: #667eea; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <div id="install-status" style="color: #6b7280; font-size: 0.875rem; text-align: center;">
                            <i class="fas fa-spinner fa-spin"></i> 准备安装...
                        </div>
                    </div>
                    
                    <script>
                    (function() {
                        // 获取安装页面的完整URL
                        const installBaseUrl = <?php echo json_encode(get_install_url()); ?>;
                        
                        // 获取install_process.php的完整URL
                        const installProcessUrl = <?php 
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'];
                            $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
                            $base_path = ($script_path === '/' || $script_path === '\\') ? '' : rtrim($script_path, '/');
                            $process_url = $protocol . $host . $base_path . '/install_process.php';
                            echo json_encode($process_url);
                        ?>;
                        
                        // 准备表单数据
                        const formData = new URLSearchParams();
                        <?php 
                        if (isset($_POST['db_host']) && isset($_POST['db_name']) && isset($_POST['db_user'])) {
                            $db_host = htmlspecialchars($_POST['db_host'], ENT_QUOTES, 'UTF-8');
                            $db_port = htmlspecialchars($_POST['db_port'] ?? '3306', ENT_QUOTES, 'UTF-8');
                            $db_name = htmlspecialchars($_POST['db_name'], ENT_QUOTES, 'UTF-8');
                            $db_user = htmlspecialchars($_POST['db_user'], ENT_QUOTES, 'UTF-8');
                            $db_pass = htmlspecialchars($_POST['db_pass'] ?? '', ENT_QUOTES, 'UTF-8');
                            echo "formData.append('db_host', " . json_encode($db_host) . ");\n";
                            echo "formData.append('db_port', " . json_encode($db_port) . ");\n";
                            echo "formData.append('db_name', " . json_encode($db_name) . ");\n";
                            echo "formData.append('db_user', " . json_encode($db_user) . ");\n";
                            echo "formData.append('db_pass', " . json_encode($db_pass) . ");\n";
                        }
                        ?>
                        
                        const steps = [
                            '连接数据库...',
                            '创建数据表...',
                            '初始化数据...',
                            '执行升级脚本...',
                            '创建配置文件...',
                            '创建安装锁...'
                        ];
                        
                        let currentStep = 0;
                        const progressBar = document.getElementById('progress-bar');
                        const statusDiv = document.getElementById('install-status');
                        
                        function updateProgress(step, message) {
                            currentStep = step;
                            const progress = ((step + 1) / steps.length) * 100;
                            progressBar.style.width = progress + '%';
                            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + message;
                        }
                        
                        // 模拟进度更新
                        let progressIndex = 0;
                        const progressInterval = setInterval(() => {
                            if (progressIndex < steps.length - 1) {
                                updateProgress(progressIndex, steps[progressIndex]);
                                progressIndex++;
                            } else {
                                clearInterval(progressInterval);
                            }
                        }, 600);
                        
                        // 执行安装
                        fetch(installProcessUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: formData.toString()
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('HTTP错误: ' + response.status);
                            }
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('JSON解析失败:', text);
                                    throw new Error('服务器响应格式错误');
                                }
                            });
                        })
                        .then(data => {
                            clearInterval(progressInterval);
                            if (data.success) {
                                updateProgress(steps.length - 1, '安装完成！');
                                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> 安装成功！正在跳转...';
                                statusDiv.style.color = '#10b981';
                                setTimeout(() => {
                                    window.location.href = installBaseUrl + '?step=4';
                                }, 1500);
                            } else {
                                updateProgress(0, '安装失败');
                                statusDiv.innerHTML = '<i class="fas fa-times-circle"></i> 安装失败：' + (data.message || '未知错误');
                                statusDiv.style.color = '#ef4444';
                                progressBar.style.background = '#ef4444';
                            }
                        })
                        .catch(error => {
                            clearInterval(progressInterval);
                            updateProgress(0, '安装失败');
                            statusDiv.innerHTML = '<i class="fas fa-times-circle"></i> 安装失败：' + error.message;
                            statusDiv.style.color = '#ef4444';
                            progressBar.style.background = '#ef4444';
                            console.error('安装错误:', error);
                        });
                    })();
                    </script>
                
                <?php elseif ($step == 4): ?>
                    <!-- 步骤4: 完成安装 -->
                    <?php 
                    // 安全验证：检查是否有有效的安装token
                    if (!$install_token_valid) {
                        // 未授权访问，显示错误提示
                        ?>
                        <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.5rem; color: #374151;">访问被拒绝</h2>
                        
                        <div class="alert alert-error">
                            <i class="fas fa-shield-alt"></i>
                            <span>安全验证失败：您必须通过正常的安装流程访问此页面。</span>
                        </div>
                        
                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                            <p style="margin: 0 0 12px 0; color: #991b1b; font-weight: 600;">
                                为了系统安全，默认管理员账户信息仅在完成安装流程后显示。
                            </p>
                            <p style="margin: 0; color: #991b1b; font-size: 0.875rem;">
                                请按照正常的安装流程完成安装，或者如果您已完成安装，请直接登录后台。
                            </p>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="<?php echo htmlspecialchars(get_install_url(3)); ?>" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i>
                                返回安装步骤
                            </a>
                            <a href="./admin/login.php" class="btn btn-secondary">
                                <i class="fas fa-sign-in-alt"></i>
                                登录后台
                            </a>
                        </div>
                        <?php
                        // 提前结束，不显示默认账户信息
                    } else {
                        // Token验证通过，继续显示安装完成页面
                        // 验证安装是否真的完成
                        $install_verified = false;
                        if ($is_already_installed) {
                            $install_verified = true;
                        } else {
                            // 如果检测不到安装状态，但用户访问了步骤4，检查必要文件
                            $config_exists = file_exists(__DIR__ . '/config.php');
                            $lock_exists = file_exists(__DIR__ . '/install.lock');
                            if ($config_exists || $lock_exists) {
                                $install_verified = true;
                            }
                        }
                        ?>
                        
                        <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 1.5rem; color: #374151;">
                            <?php echo $install_verified ? '安装完成' : '安装状态检查'; ?>
                        </h2>
                        
                        <?php if ($install_verified): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span>恭喜！系统安装成功！</span>
                        </div>
                        
                        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 2px solid #10b981; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.1);">
                            <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 1.25rem; color: #059669; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-user-shield"></i>
                                默认管理员账户
                            </h3>
                            <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #bbf7d0; margin-bottom: 16px;">
                                <div style="display: grid; grid-template-columns: auto 1fr; gap: 12px 20px; align-items: center; margin-bottom: 16px;">
                                    <div style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user" style="color: #667eea;"></i>
                                        用户名：
                                    </div>
                                    <div style="font-family: 'Courier New', monospace; font-size: 1.125rem; font-weight: 600; color: #059669; background: #f0fdf4; padding: 8px 12px; border-radius: 6px; border: 1px solid #bbf7d0;">
                                        admin
                                    </div>
                                    
                                    <div style="font-weight: 600; color: #374151; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-key" style="color: #667eea;"></i>
                                        密码：
                                    </div>
                                    <div style="font-family: 'Courier New', monospace; font-size: 1.125rem; font-weight: 600; color: #059669; background: #f0fdf4; padding: 8px 12px; border-radius: 6px; border: 1px solid #bbf7d0;">
                                        admin
                                    </div>
                                </div>
                            </div>
                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; display: flex; align-items: start; gap: 12px;">
                                <i class="fas fa-exclamation-triangle" style="color: #dc2626; font-size: 1.25rem; margin-top: 2px; flex-shrink: 0;"></i>
                                <div>
                                    <p style="margin: 0 0 8px 0; color: #dc2626; font-weight: 600; font-size: 0.9375rem;">
                                        安全提示
                                    </p>
                                    <p style="margin: 0; color: #991b1b; font-size: 0.875rem; line-height: 1.6;">
                                        为了系统安全，请立即登录后台修改默认密码！建议使用强密码（包含字母、数字和特殊字符）。
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <a href="./index.php" class="btn btn-primary">
                                访问首页
                                <i class="fas fa-home"></i>
                            </a>
                            <a href="./admin/login.php" class="btn btn-secondary">
                                登录后台
                                <i class="fas fa-sign-in-alt"></i>
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>安装状态异常，请检查安装是否完成。</span>
                        </div>
                        <div class="action-buttons">
                            <a href="<?php echo htmlspecialchars(get_install_url(3)); ?>" class="btn btn-primary">
                                <i class="fas fa-redo"></i>
                                重新安装
                            </a>
                            <a href="./index.php" class="btn btn-secondary">
                                <i class="fas fa-home"></i>
                                返回首页
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php
                        // 结束token验证通过的分支
                    }
                    ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

