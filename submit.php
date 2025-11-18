<?php
// 检查安装状态
require_once __DIR__ . '/install_check.php';
check_install_redirect();

include("./include/common.php");

// 确保多主体类型系统已初始化
if (function_exists('ensure_multi_subject_schema')) {
    ensure_multi_subject_schema();
}

// 获取活跃的主体类型
$subject_types = [];
if (function_exists('get_active_subject_types')) {
    $subject_types = get_active_subject_types();
}

// 从 session 中获取消息并清除
$message = '';
$success = false;
if(isset($_SESSION['submit_message'])){
    $message = $_SESSION['submit_message'];
    $success = $_SESSION['submit_success'] ?? false;
    unset($_SESSION['submit_message']);
    unset($_SESSION['submit_success']);
}

// 处理表单提交
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    require_once __DIR__.'/include/function.php';
    // 简单验证码校验（算术题），防刷与机器人
    $captcha_answer = trim((string)($_POST['captcha'] ?? ''));
    $captcha_expected = isset($_SESSION['captcha_expected']) ? (string)$_SESSION['captcha_expected'] : '';
    if($captcha_expected === ''){
        // 初始化一个期待值，防止空值导致全部通过
        $_SESSION['captcha_expected'] = '5';
        $captcha_expected = '5';
    }
    if($captcha_answer !== $captcha_expected){
        $message = '验证码错误，请重新输入';
    } else {
        $subject_type = trim((string)($_POST['subject_type'] ?? ''));
        $subject = sanitize_subject($_POST['subject'] ?? '');
        $level = intval($_POST['level'] ?? 1);
        $note = trim((string)($_POST['note'] ?? ''));
        $contact = trim((string)($_POST['contact'] ?? ''));
        $evidence = $note;
        $date = date('Y-m-d H:i:s');
        // 基本长度限制，避免超长输入
        if(function_exists('mb_substr')){
            $contact = mb_substr($contact, 0, 100, 'UTF-8');
            $evidence = mb_substr($evidence, 0, 1000, 'UTF-8');
            $note = mb_substr($note, 0, 2000, 'UTF-8');
        } else {
            $contact = substr($contact, 0, 100);
            $evidence = substr($evidence, 0, 1000);
            $note = substr($note, 0, 2000);
        }
        if($subject === '' || $subject_type === ''){
            $message = '请选择主体类型并输入有效的主体内容';
        }elseif($level < 1 || $level > 3){
            $message = '请选择有效的黑名单等级';
        }else{
            // 验证主体类型格式
            $validation_result = validate_subject_by_type($subject, $subject_type);
            if (!$validation_result) {
                $message = '输入格式不符合' . get_subject_type_name($subject_type) . '的要求';
            } else {
                // 检查是否已在主黑名单
                $exists = $DB->get_row_prepared("SELECT id FROM black_list WHERE subject = ? AND (subject_type = ? OR subject_type IS NULL) LIMIT 1", [$subject, $subject_type]);
                if($exists){
                    $message = '该主体已在云端黑名单中，无需重复提交';
                }else{
                    // 检查是否已有待审核提交（防重复）
                    $pending = $DB->get_row_prepared("SELECT id,date FROM black_submit WHERE subject = ? AND (subject_type = ? OR subject_type IS NULL) AND status = 0 ORDER BY id DESC LIMIT 1", [$subject, $subject_type]);
                    if($pending){
                        $message = '该主体已有待审核提交（时间：'.htmlspecialchars($pending['date']).'），请勿重复提交';
                    }else{
                        // 简单频率限制：同主体+联系方式的10分钟内重复提交阻止
                        $too_fast = false;
                        if($contact !== ''){
                            $prev = $DB->get_row_prepared("SELECT id,date FROM black_submit WHERE subject = ? AND contact = ? ORDER BY id DESC LIMIT 1", [$subject, $contact]);
                            if($prev){
                                $diff = strtotime($date) - strtotime($prev['date']);
                                if($diff < 600){ // 10分钟内
                                    $too_fast = true;
                                }
                            }
                        }
                        if($too_fast){
                            $message = '提交过于频繁，请稍后再试';
                        }else{
                            // 确保提交表存在
                            if(!function_exists('ensure_submit_schema')) {
                                require_once __DIR__ . '/include/function.php';
                            }
                            ensure_submit_schema();
                            
                            // 写入提交表（使用预处理语句）
                            $ok = $DB->execute_prepared(
                                "INSERT INTO `black_submit` (`subject`,`subject_type`,`level`,`note`,`contact`,`evidence`,`date`,`status`) VALUES (?,?,?,?,?,?,?,?)",
                                [$subject, $subject_type, $level, $note, $contact, $evidence, $date, 0]
                            );
                            
                            if($ok){
                                // 获取插入的ID
                                $last_row = $DB->get_row("SELECT LAST_INSERT_ID() as id");
                                $submit_id = $last_row ? intval($last_row['id']) : 0;

                                // 处理上传的图片
                                $image_count = 0;
                                if(isset($_POST['uploaded_images']) && !empty($_POST['uploaded_images'])){
                                    $uploaded_images = json_decode($_POST['uploaded_images'], true);
                                    if(is_array($uploaded_images) && $submit_id > 0){
                                        // 确保提交图片表存在
                                        ensure_submit_images_table();

                                        foreach($uploaded_images as $image_data){
                                            $file_name = $image_data['file_name'] ?? $image_data['fileName'] ?? '';
                                            $original_name = $image_data['original_name'] ?? $image_data['originalName'] ?? '';
                                            $file_path = $image_data['file_path'] ?? $image_data['filePath'] ?? '';
                                            $file_size = $image_data['file_size'] ?? $image_data['fileSize'] ?? 0;
                                            $mime_type = $image_data['mime_type'] ?? $image_data['mimeType'] ?? '';

                                            if(empty($file_name) || empty($original_name) || empty($file_path)) {
                                                continue;
                                            }

                                            // 验证文件路径
                                            if(!function_exists('validate_image_file_path')) {
                                                require_once __DIR__ . '/include/function.php';
                                            }
                                            $file_path = validate_image_file_path($file_path, $file_name, __DIR__);
                                            $full_path = __DIR__ . '/' . $file_path;

                                            // 验证文件是否存在
                                            if(!file_exists($full_path)) {
                                                error_log("Image file not found: " . $full_path);
                                                continue;
                                            }

                                            // 插入图片记录到数据库（使用 black_submit_images 表）
                                            $insert_image = $DB->execute_prepared(
                                                "INSERT INTO `black_submit_images` (`submit_id`,`original_name`,`file_name`,`file_path`,`file_size`,`mime_type`,`upload_time`) VALUES (?,?,?,?,?,?,?)",
                                                [
                                                    $submit_id,
                                                    $original_name,
                                                    $file_name,
                                                    $file_path,
                                                    $file_size,
                                                    $mime_type,
                                                    date('Y-m-d H:i:s')
                                                ]
                                            );

                                            if($insert_image){
                                                $image_count++;
                                            } else {
                                                error_log("Failed to insert image record for submit ID {$submit_id}: {$file_name}");
                                            }
                                        }

                                        // 更新提交记录的证据数量
                                        if($image_count > 0){
                                            $DB->execute_prepared(
                                                "UPDATE `black_submit` SET `evidence_count` = ? WHERE `id` = ?",
                                                [$image_count, $submit_id]
                                            );
                                        }
                                    }
                                }

                                // 重置验证码期待值，下一次提交需重新作答
                                $_SESSION['captcha_expected'] = (string)rand(3,9);

                                // 使用 PRG 模式：存储消息到 session 并重定向
                                $_SESSION['submit_message'] = '提交成功，我们将尽快审核';
                                $_SESSION['submit_success'] = true;
                                header('Location: ./submit.php');
                                exit;
                            }else{
                                $message = '提交失败，请稍后再试';
                            }
                        }
                    }
                }
            }
        }
    }
}

// 生成验证码
$x = rand(1,7);
$_SESSION['captcha_expected'] = (string)(2 + $x);
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title><?php echo $sitename;?> - 提交黑名单</title>
    
    <!-- 样式文件 -->
    <link href="./assets/css/design-system.css" rel="stylesheet">
    <link href="./assets/css/font-awesome.min.css" rel="stylesheet">
    
    <style>
        /* 页面特定样式 */
        .layout-header {
            background: var(--color-bg-primary);
            border-bottom: 1px solid var(--color-border-light);
            padding: var(--spacing-lg) 0;
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
        }
        
        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
        }
        
        .site-title {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-semibold);
            color: var(--color-accent-primary);
            margin: 0;
        }
        
        .nav-links {
            display: flex;
            gap: var(--spacing-xl);
        }
        
        .nav-link {
            color: var(--color-secondary-text);
            text-decoration: none;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            transition: color var(--transition-base);
        }
        
        .nav-link:hover {
            color: var(--color-accent-primary);
        }
        
        .nav-link i {
            margin-right: var(--spacing-xs);
        }

        .form-section {
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border-light);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-base);
        }
        
        .form-group {
            margin-bottom: var(--spacing-xl);
        }
        
        .form-label {
            display: block;
            font-weight: var(--font-weight-semibold);
            color: var(--color-primary-text);
            margin-bottom: var(--spacing-sm);
            font-size: var(--font-size-base);
        }
        
        .required {
            color: var(--color-error);
            margin-left: var(--spacing-xs);
        }
        
        .form-control {
            width: 100%;
            padding: var(--spacing-md) var(--spacing-lg);
            border: 1px solid var(--color-border-medium);
            border-radius: var(--border-radius-base);
            font-size: var(--font-size-base);
            font-family: var(--font-family-primary);
            transition: all var(--transition-base);
            background: var(--color-bg-primary);
            color: var(--color-primary-text);
            line-height: 1.5;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--color-accent-primary);
            box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.1);
        }
        
        .form-control:hover {
            border-color: var(--color-border-dark);
        }
        
        .form-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 45px !important;
            cursor: pointer;
            background-color: var(--color-bg-primary);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234a5568' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 14px center;
            background-repeat: no-repeat;
            background-size: 16px;
        }
        
        .form-select:hover {
            border-color: var(--color-border-dark);
        }
        
        .form-select:focus {
            border-color: var(--color-accent-primary);
            box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.1);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
            font-family: var(--font-family-primary);
            line-height: 1.6;
        }
        
        .form-helper {
            font-size: var(--font-size-sm);
            color: var(--color-secondary-text);
            margin-top: var(--spacing-xs);
            line-height: 1.5;
        }
        
        .level-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--spacing-md);
            margin-top: var(--spacing-sm);
        }
        
        .level-option {
            position: relative;
        }
        
        .level-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .level-card {
            padding: var(--spacing-lg);
            border: 2px solid var(--color-border-light);
            border-radius: var(--border-radius-base);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-base);
            background: var(--color-bg-primary);
        }
        
        .level-option input[type="radio"]:checked + .level-card {
            border-color: var(--color-accent-primary);
            background: var(--color-accent-light);
            color: var(--color-accent-primary);
        }
        
        .level-option.level-1 input[type="radio"]:checked + .level-card {
            border-color: var(--color-success);
            background: var(--color-success-light);
            color: var(--color-success);
        }
        
        .level-option.level-2 input[type="radio"]:checked + .level-card {
            border-color: var(--color-warning);
            background: var(--color-warning-light);
            color: var(--color-warning);
        }
        
        .level-option.level-3 input[type="radio"]:checked + .level-card {
            border-color: var(--color-error);
            background: var(--color-error-light);
            color: var(--color-error);
        }
        
        .level-card:hover {
            border-color: var(--color-border-dark);
        }
        
        .level-number {
            font-size: 1.5rem;
            font-weight: var(--font-weight-bold);
            margin-bottom: var(--spacing-xs);
        }
        
        .level-name {
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
        }
        
        .captcha-group {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .captcha-question {
            background: var(--color-bg-secondary);
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--border-radius-base);
            font-weight: var(--font-weight-semibold);
            color: var(--color-primary-text);
            min-width: 120px;
            text-align: center;
            border: 1px solid var(--color-border-light);
        }
        
        .captcha-input {
            flex: 1;
        }
        
        .btn-submit {
            width: 100%;
            padding: var(--spacing-md) var(--spacing-xl);
            background: var(--color-accent-primary);
            color: #fff;
            border: none;
            border-radius: var(--border-radius-base);
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-semibold);
            cursor: pointer;
            transition: all var(--transition-base);
            margin-top: var(--spacing-lg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
        }
        
        .btn-submit:hover {
            background: var(--color-accent-hover);
            box-shadow: var(--shadow-base);
        }
        
        .btn-submit:active {
            background: var(--color-accent-active);
        }
        
        .alert {
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--border-radius-base);
            margin-bottom: var(--spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .alert-success {
            background: var(--color-success-light);
            border: 1px solid var(--color-success);
            color: var(--color-success);
        }
        
        .alert-error {
            background: var(--color-error-light);
            border: 1px solid var(--color-error);
            color: var(--color-error);
        }
        
        /* 图片上传样式 */
        .upload-zone {
            border: 2px dashed var(--color-border-medium);
            border-radius: var(--border-radius-base);
            padding: var(--spacing-xl);
            text-align: center;
            background: var(--color-bg-secondary);
            cursor: pointer;
            transition: all var(--transition-base);
            margin-bottom: var(--spacing-md);
        }
        
        .upload-zone:hover {
            border-color: var(--color-accent-primary);
            background: var(--color-accent-light);
        }
        
        .upload-zone.dragover {
            border-color: var(--color-accent-primary);
            background: var(--color-accent-light);
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--color-accent-primary);
            margin-bottom: var(--spacing-md);
        }
        
        .upload-text {
            font-weight: var(--font-weight-medium);
            color: var(--color-primary-text);
            margin: 0 0 var(--spacing-xs) 0;
        }
        
        .upload-hint {
            font-size: var(--font-size-sm);
            color: var(--color-secondary-text);
            margin: 0;
        }
        
        .preview-container {
            margin-top: var(--spacing-lg);
            display: none;
        }
        
        .preview-container.active {
            display: block;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .preview-item {
            position: relative;
            border: 1px solid var(--color-border-light);
            border-radius: var(--border-radius-base);
            overflow: hidden;
            background: var(--color-bg-primary);
        }
        
        .preview-item.uploading {
            border-color: var(--color-accent-primary);
        }
        
        .preview-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }
        
        .preview-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(255, 77, 79, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-xs);
            transition: all var(--transition-base);
        }
        
        .preview-remove:hover {
            background: var(--color-error);
            transform: scale(1.1);
        }
        
        .upload-progress {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: var(--font-size-xs);
        }
        
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: var(--spacing-xs);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--color-border-light);
        }
        
        .preview-title {
            font-weight: var(--font-weight-semibold);
            color: var(--color-primary-text);
        }
        
        .clear-images {
            background: var(--color-error-light);
            color: var(--color-error);
            border: 1px solid var(--color-error);
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--border-radius-base);
            font-size: var(--font-size-xs);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .clear-images:hover {
            background: var(--color-error);
            color: white;
        }
        
        .help-section {
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border-light);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-base);
        }
        
        .help-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .help-list li {
            position: relative;
            padding-left: var(--spacing-lg);
            margin-bottom: var(--spacing-sm);
            color: var(--color-secondary-text);
            font-size: var(--font-size-sm);
            line-height: 1.5;
        }
        
        .help-list li::before {
            content: '●';
            position: absolute;
            left: 0;
            color: var(--color-accent-primary);
            font-weight: bold;
        }
        
        /* 双列布局样式（与index.php一致） */
        .layout-main {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--spacing-xl);
            align-items: start;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
        }
        
        .layout-content {
            min-width: 0;
        }
        
        .layout-sidebar {
            position: sticky;
            top: var(--spacing-xl);
        }
        
        .text-title {
            font-size: var(--font-size-xxl);
            font-weight: var(--font-weight-bold);
            color: var(--color-primary-text);
            margin-bottom: var(--spacing-xl);
        }
        
        .text-heading {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-semibold);
            color: var(--color-primary-text);
            margin-bottom: var(--spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        /* 响应式设计 */
        @media (max-width: 1024px) {
            .layout-main {
                grid-template-columns: 1fr 300px;
                gap: var(--spacing-lg);
            }
        }
        
        @media (max-width: 768px) {
            .header-nav {
                flex-direction: column;
                gap: var(--spacing-md);
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .layout-main {
                grid-template-columns: 1fr;
                gap: var(--spacing-lg);
            }
            
            .layout-sidebar {
                position: static;
                order: -1;
            }
            
            .level-options {
                grid-template-columns: 1fr;
            }
            
            .captcha-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .captcha-question {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- 页面头部 -->
        <header class="layout-header">
            <div class="header-nav">
                <h1 class="site-title"><?php echo $sitename; ?></h1>
                <nav class="nav-links">
                    <a href="./index.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        首页
                    </a>
                    <a href="./submit.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        提交黑名单
                    </a>
                    <a href="./admin/" class="nav-link">
                        <i class="fas fa-cog"></i>
                        管理后台
                    </a>
                </nav>
            </div>
        </header>

        <!-- 主要内容区域 -->
        <main class="layout-main">
            <!-- 左侧表单 -->
            <div class="layout-content">
                <h1 class="text-title">提交黑名单</h1>
                
                <!-- 提示消息 -->
                <?php if($message): ?>
                <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- 提交表单 -->
                <form method="post" class="form-section" id="submitForm" action="./submit.php">
                    <h2 class="text-heading">提交信息</h2>
                    
                    <div class="form-group">
                        <label for="subject_type" class="form-label">
                            主体类型 <span class="required">*</span>
                        </label>
                        <select name="subject_type" class="form-control form-select" id="subject_type" required>
                            <option value="">请选择主体类型</option>
                            <?php foreach($subject_types as $type): 
                                $selected = isset($_POST['subject_type']) && $_POST['subject_type'] == $type['type_key'] ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($type['type_key']); ?>" 
                                    data-placeholder="<?php echo htmlspecialchars($type['placeholder'] ?? '请输入' . $type['type_name']); ?>"
                                    <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-helper">选择主体的类型（QQ号、手机号、邮箱等）</div>
                    </div>

                    <div class="form-group">
                        <label for="subject" class="form-label">
                            主体内容 <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="subject" 
                               class="form-control" 
                               id="subject" 
                               placeholder="请先选择主体类型" 
                               maxlength="100" 
                               autocomplete="off" 
                               required
                               value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                        <div class="form-helper" id="subject-help">根据选择的主体类型输入相应的内容</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            风险等级 <span class="required">*</span>
                        </label>
                        <div class="level-options">
                            <div class="level-option level-1">
                                <input type="radio" name="level" value="1" id="level1" <?php echo (!isset($_POST['level']) || $_POST['level'] == '1') ? 'checked' : ''; ?> required>
                                <div class="level-card">
                                    <div class="level-number">1</div>
                                    <div class="level-name">低风险</div>
                                </div>
                            </div>
                            <div class="level-option level-2">
                                <input type="radio" name="level" value="2" id="level2" <?php echo (isset($_POST['level']) && $_POST['level'] == '2') ? 'checked' : ''; ?>>
                                <div class="level-card">
                                    <div class="level-number">2</div>
                                    <div class="level-name">中风险</div>
                                </div>
                            </div>
                            <div class="level-option level-3">
                                <input type="radio" name="level" value="3" id="level3" <?php echo (isset($_POST['level']) && $_POST['level'] == '3') ? 'checked' : ''; ?>>
                                <div class="level-card">
                                    <div class="level-number">3</div>
                                    <div class="level-name">高风险</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-helper">根据风险程度选择相应等级</div>
                    </div>

                    <div class="form-group">
                        <label for="note" class="form-label">
                            举报原因 <span class="required">*</span>
                        </label>
                        <textarea name="note" 
                                  id="note" 
                                  class="form-control form-textarea" 
                                  placeholder="请详细描述举报原因，如诈骗、恶意行为等" 
                                  maxlength="2000"
                                  required><?php echo isset($_POST['note']) ? htmlspecialchars($_POST['note']) : ''; ?></textarea>
                        <div class="form-helper">详细的描述有助于我们更好地审核</div>
                    </div>

                    <div class="form-group">
                        <label for="contact" class="form-label">联系方式</label>
                        <input type="text" 
                               name="contact" 
                               id="contact" 
                               class="form-control" 
                               placeholder="QQ/微信/邮箱等，便于审核时联系" 
                               maxlength="100" 
                               autocomplete="off" 
                               value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
                        <div class="form-helper">选填，便于我们在需要时与您联系</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">证据材料 <span style="color: var(--color-secondary-text); font-weight: normal;">(可选)</span></label>
                        
                        <!-- 图片上传区域 -->
                        <div class="upload-zone" id="uploadZone">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <p class="upload-text">点击上传或拖拽图片到此处</p>
                            <p class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，单个文件不超过 5MB</p>
                        </div>
                        <input type="file" id="imageUpload" accept="image/*" multiple style="display: none;">
                        <input type="hidden" id="uploadedImages" name="uploaded_images" value="">
                        
                        <!-- 图片预览区域 -->
                        <div class="preview-container" id="imagePreview">
                            <div class="preview-header">
                                <span class="preview-title">已上传的图片</span>
                                <button type="button" class="clear-images" onclick="clearAllImages()">
                                    <i class="fas fa-trash"></i> 清空
                                </button>
                            </div>
                            <div class="preview-grid" id="previewGrid"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            安全验证 <span class="required">*</span>
                        </label>
                        <div class="captcha-group">
                            <div class="captcha-question">2 + <?php echo $x; ?> = ?</div>
                            <input type="text" 
                                   name="captcha" 
                                   class="form-control captcha-input" 
                                   placeholder="请输入答案" 
                                   autocomplete="off" 
                                   required>
                        </div>
                        <div class="form-helper">请输入计算结果以验证您不是机器人</div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        提交举报
                    </button>
                </form>
            </div>

            <!-- 右侧帮助区 -->
            <div class="layout-sidebar">
                <!-- 提交指南 -->
                <div class="help-section">
                    <h3 class="text-heading">
                        <i class="fas fa-lightbulb"></i>
                        提交指南
                    </h3>
                    <ul class="help-list">
                        <li><strong>风险等级说明：</strong></li>
                        <li>• 高风险：确认恶意行为</li>
                        <li>• 中风险：可疑活动</li>
                        <li>• 低风险：轻微异常</li>
                        <li style="margin-top: var(--spacing-md);"><strong>描述要求：</strong></li>
                        <li>• 详细说明发现过程</li>
                        <li>• 描述具体的风险行为</li>
                        <li>• 提供时间、地点等信息</li>
                        <li>• 避免主观臆断</li>
                        <li style="margin-top: var(--spacing-md);"><strong>证据要求：</strong></li>
                        <li>• 截图、日志等直接证据</li>
                        <li>• 文件大小不超过5MB</li>
                        <li>• 支持常见图片格式</li>
                        <li>• 确保信息真实有效</li>
                    </ul>
                </div>

                <!-- 注意事项 -->
                <div class="help-section">
                    <h3 class="text-heading">
                        <i class="fas fa-info-circle"></i>
                        注意事项
                    </h3>
                    <ul class="help-list">
                        <li>提交后需要等待管理员审核</li>
                        <li>请确保提交信息的真实性</li>
                        <li>恶意提交将被拒绝</li>
                        <li>如有疑问，请联系管理员</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script src="./assets/js/jquery-3.6.0.min.js"></script>
    <script>
    (function() {
        // 主体类型选择器变化时更新输入框placeholder
        $('#subject_type').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const placeholder = selectedOption.data('placeholder') || '请输入主体内容';
            $('#subject').attr('placeholder', placeholder);
            
            if (selectedOption.val()) {
                $('#subject-help').text('请输入' + selectedOption.text() + '，格式：' + placeholder);
            } else {
                $('#subject-help').text('根据选择的主体类型输入相应的内容');
            }
        });
        
        // 初始化时设置placeholder
        $('#subject_type').trigger('change');
        
        // 图片上传功能
        let uploadedFiles = [];
        const uploadZone = document.getElementById('uploadZone');
        const imageUpload = document.getElementById('imageUpload');
        const imagePreview = document.getElementById('imagePreview');
        const previewGrid = document.getElementById('previewGrid');
        const uploadedImagesField = document.getElementById('uploadedImages');
        
        // 点击上传区域触发文件选择
        if (uploadZone) {
            uploadZone.addEventListener('click', function() {
                if (imageUpload) {
                    imageUpload.click();
                }
            });
        }
        
        // 文件选择处理
        if (imageUpload) {
            imageUpload.addEventListener('change', function(e) {
                handleFiles(e.target.files);
            });
        }
        
        // 拖拽上传
        if (uploadZone) {
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });
        }
        
        // 处理选择的文件
        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert('文件 "' + file.name + '" 超过5MB限制');
                        return;
                    }
                    
                    // 检查是否已存在
                    if (uploadedFiles.some(f => f.file && f.file.name === file.name && f.file.size === file.size)) {
                        return;
                    }
                    
                    // 立即上传文件
                    uploadFile(file);
                } else {
                    alert('文件 "' + file.name + '" 不是有效的图片格式');
                }
            });
        }
        
        // 上传文件到服务器
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('image', file);
            
            // 添加上传中的预览项
            const uploadingItem = addUploadingPreviewItem(file);
            
            fetch('./upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // 检查响应状态
                if (!response.ok) {
                    throw new Error('HTTP错误: ' + response.status);
                }
                // 尝试解析 JSON
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
                if (data.success) {
                    // 上传成功，更新预览项
                    updatePreviewItemSuccess(uploadingItem, data.data, file);
                    uploadedFiles.push({
                        file: file,
                        uploaded: true,
                        serverData: data.data
                    });
                    updateUploadedImagesField();
                } else {
                    // 上传失败，移除预览项并显示错误
                    uploadingItem.remove();
                    alert('上传失败：' + (data.message || '未知错误'));
                }
                updatePreviewVisibility();
            })
            .catch(error => {
                console.error('上传错误:', error);
                uploadingItem.remove();
                alert('上传失败：' + error.message);
                updatePreviewVisibility();
            });
        }
        
        // 添加上传中的预览项
        function addUploadingPreviewItem(file) {
            const reader = new FileReader();
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item uploading';
            
            reader.onload = function(e) {
                previewItem.innerHTML = `
                    <img src="${e.target.result}" alt="${file.name}">
                    <div class="upload-progress">
                        <div class="spinner"></div>
                        <span>上传中...</span>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
            
            if (previewGrid) {
                previewGrid.appendChild(previewItem);
            }
            updatePreviewVisibility();
            return previewItem;
        }
        
        // 更新预览项为成功状态
        function updatePreviewItemSuccess(previewItem, serverData, file) {
            previewItem.className = 'preview-item';
            const img = previewItem.querySelector('img');
            if (img) {
                // 更新图片源为服务器路径
                const previewUrl = serverData.file_path ? ('./' + serverData.file_path) : img.src;
                previewItem.innerHTML = `
                    <img src="${previewUrl}" alt="${file.name}">
                    <button type="button" class="preview-remove" onclick="removePreviewItem(this, '${serverData.file_name || file.name}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
            }
        }
        
        // 更新上传图片隐藏字段
        function updateUploadedImagesField() {
            const successfulUploads = uploadedFiles.filter(item => item.uploaded && item.serverData);
            const imageData = successfulUploads.map(item => item.serverData);
            if (uploadedImagesField) {
                uploadedImagesField.value = JSON.stringify(imageData);
            }
        }
        
        // 移除预览项
        window.removePreviewItem = function(button, fileName) {
            uploadedFiles = uploadedFiles.filter(f => {
                if (f.serverData && f.serverData.file_name === fileName) {
                    return false;
                }
                if (f.file && f.file.name === fileName) {
                    return false;
                }
                return true;
            });
            if (button && button.parentElement) {
                button.parentElement.remove();
            }
            updatePreviewVisibility();
            updateUploadedImagesField();
        };
        
        // 清空所有图片
        window.clearAllImages = function() {
            if (confirm('确定要清空所有已上传的图片吗？')) {
                uploadedFiles = [];
                if (previewGrid) {
                    previewGrid.innerHTML = '';
                }
                if (imageUpload) {
                    imageUpload.value = '';
                }
                updatePreviewVisibility();
                updateUploadedImagesField();
            }
        };
        
        // 更新预览区域显示状态
        function updatePreviewVisibility() {
            if (imagePreview && previewGrid) {
                if (uploadedFiles.length > 0) {
                    imagePreview.classList.add('active');
                } else {
                    imagePreview.classList.remove('active');
                }
            }
        }
        
        // 表单提交
        $('#submitForm').on('submit', function(e) {
            const submitBtn = $('#submitBtn');
            const originalHtml = submitBtn.html();
            
            // 显示加载状态
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> 提交中...');
            submitBtn.prop('disabled', true);
        });
    })();
    </script>
</body>
</html>
