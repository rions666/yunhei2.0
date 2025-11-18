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

// 读取联系方式配置
$config_contact_qq = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['contact_qq']);
$config_contact_email = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['contact_email']);
$contact_qq = $config_contact_qq ? $config_contact_qq['v'] : '406845294';
$contact_email = $config_contact_email ? $config_contact_email['v'] : '406845294@qq.com';

// 处理查询请求
$query_result = null;
$query_data = null;
if (isset($_POST['subject'])) {
    require_once __DIR__.'/include/function.php';
    
    $subject = sanitize_subject($_POST['subject']);
    $subject_type = isset($_POST['subject_type']) ? trim($_POST['subject_type']) : '';
    
    if ($subject !== '') {
        // 如果没有指定类型，使用智能推断
        if (empty($subject_type)) {
            $subject_type = guess_subject_type($subject);
        }
        
        $validation_result = validate_subject_by_type($subject, $subject_type);
        if ($validation_result) {
            $row = $DB->get_row_prepared("SELECT * FROM black_list WHERE subject = ? AND (subject_type = ? OR subject_type IS NULL) LIMIT 1", [$subject, $subject_type]);
            $type_name = get_subject_type_name($subject_type);
            
            // 查询关联的图片
            $images = [];
            if ($row) {
                require_once __DIR__.'/include/function.php';
                ensure_blacklist_images_table();
                
                $images = $DB->get_all_prepared(
                    "SELECT * FROM black_list_images WHERE blacklist_id = ? AND is_deleted = 0 ORDER BY upload_time DESC",
                    [$row['id']]
                );
                
                // 验证图片路径
                foreach ($images as &$img) {
                    $img['file_path'] = validate_image_file_path($img['file_path'], $img['file_name'], __DIR__);
                    $img['full_path'] = __DIR__ . '/' . $img['file_path'];
                    $img['exists'] = file_exists($img['full_path']);
                }
                unset($img);
            }
            
            $query_data = [
                'subject' => $subject,
                'subject_type' => $subject_type,
                'type_name' => $type_name,
                'result' => $row,
                'images' => $images,
                'validation' => ['valid' => true]
            ];
        } else {
            $query_result = ['error' => '输入格式不符合' . get_subject_type_name($subject_type) . '的要求'];
        }
    } else {
        $query_result = ['error' => '请输入有效的查询内容'];
    }
}
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
    <title><?php echo $sitename; ?></title>
    
    <!-- 样式文件 -->
    <link href="./assets/css/design-system.css" rel="stylesheet">
    <link href="./assets/css/font-awesome.min.css" rel="stylesheet">
    
    <style>
        /* 页面特定样式 */
        .query-preview {
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border-light);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            min-height: 200px;
            position: relative;
        }
        
        .query-preview.empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-tertiary-text);
            font-style: italic;
        }
        
        .query-preview.empty::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 64px;
            height: 64px;
            background: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23d9d9d9'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='1' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'/%3e%3c/svg%3e") no-repeat center;
            background-size: contain;
            opacity: 0.3;
            margin-bottom: var(--spacing-lg);
        }
        
        .query-result {
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: var(--spacing-sm) 0;
            border-bottom: 1px solid var(--color-border-light);
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-label {
            font-weight: var(--font-weight-medium);
            color: var(--color-secondary-text);
            min-width: 80px;
            flex-shrink: 0;
        }
        
        .result-value {
            color: var(--color-primary-text);
            flex: 1;
            text-align: right;
        }
        
        .result-value.success {
            color: var(--color-success);
        }
        
        .result-value.error {
            color: var(--color-error);
        }
        
        .result-value.warning {
            color: var(--color-warning);
        }
        
        .help-section {
            background: var(--color-bg-primary);
            border: 1px solid var(--color-border-light);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
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
        
        .feature-tips {
            background: var(--color-accent-light);
            border: 1px solid var(--color-accent-primary);
            border-radius: var(--border-radius-base);
            padding: var(--spacing-md);
            margin-top: var(--spacing-md);
            font-size: var(--font-size-sm);
            color: var(--color-accent-primary);
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
        
        .announcement-card {
            background: linear-gradient(135deg, var(--color-accent-light) 0%, rgba(24, 144, 255, 0.05) 100%);
            border: 1px solid var(--color-accent-primary);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }
        
        .announcement-title {
            color: var(--color-accent-primary);
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .announcement-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .announcement-list li {
            padding: var(--spacing-sm) 0;
            color: var(--color-primary-text);
            font-size: var(--font-size-sm);
            line-height: 1.6;
            border-bottom: 1px solid rgba(24, 144, 255, 0.1);
        }
        
        .announcement-list li:last-child {
            border-bottom: none;
        }
        
        .current-time {
            color: var(--color-secondary-text);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-md);
            text-align: right;
        }
        
        /* 布局对齐优化 */
        .layout-main {
            align-items: flex-start;
        }
        
        .layout-content > .text-title {
            margin-top: 0;
            margin-bottom: var(--spacing-xl);
        }
        
        .layout-sidebar > .query-preview {
            margin-top: 0;
        }
        
        .layout-sidebar > .help-section:first-of-type {
            margin-top: 0;
        }
        
        /* 确保左右两侧标题对齐 */
        .query-preview .text-heading,
        .help-section .text-heading {
            margin-top: 0;
            margin-bottom: var(--spacing-lg);
        }
        
        /* 统一卡片间距 */
        .announcement-card,
        .form-section,
        .query-preview,
        .help-section {
            margin-bottom: var(--spacing-xl);
        }
        
        .announcement-card:first-child,
        .form-section:first-child,
        .query-preview:first-child,
        .help-section:first-child {
            margin-top: 25;
        }
        .form-group.focused .form-control {
            border-color: var(--color-accent-primary);
            box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.1);
        }
        
        .form-group.has-success .form-control {
            border-color: var(--color-success);
        }
        
        .form-group.has-error .form-control {
            border-color: var(--color-error);
        }
        
        .status-info {
            background: var(--color-info-light);
            border: 1px solid var(--color-info);
            border-radius: var(--border-radius-base);
            padding: var(--spacing-md);
            color: var(--color-info);
            font-size: var(--font-size-sm);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-sm);
        }
        
        .status-info i {
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .mt-lg {
            margin-top: var(--spacing-lg);
        }

        /* 图片展示样式 */
        .result-images {
            flex-direction: column;
            align-items: flex-start;
            padding: var(--spacing-md) 0;
        }

        .result-images .result-label {
            margin-bottom: var(--spacing-sm);
        }

        .evidence-images {
            width: 100%;
            margin-top: var(--spacing-xs);
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: var(--spacing-sm);
            max-width: 500px;
        }

        .image-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: var(--border-radius-base);
            overflow: hidden;
            border: 1px solid var(--color-border-light);
            background: var(--color-bg-secondary);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .image-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .image-link {
            display: block;
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .evidence-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }

        .image-link:hover .evidence-thumbnail {
            transform: scale(1.1);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .image-link:hover .image-overlay {
            opacity: 1;
        }

        .image-overlay i {
            color: #fff;
            font-size: 1.5rem;
        }

        /* 图片查看模态框 */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            padding: var(--spacing-xl);
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .image-modal.active {
            display: flex;
        }

        .image-modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
        }

        .image-modal img {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: var(--border-radius-base);
        }

        .image-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .image-modal-close:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        /* 操作按钮区域样式 - 简约线条蓝白风格 */
        .action-buttons {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--color-border-light);
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-sm) var(--spacing-lg);
            border: 1px solid var(--color-border-medium);
            border-radius: var(--border-radius-base);
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-medium);
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-base);
            background: var(--color-bg-primary);
            color: var(--color-primary-text);
            box-shadow: var(--shadow-sm);
        }

        .btn-action:hover {
            border-color: var(--color-accent-primary);
            color: var(--color-accent-primary);
            box-shadow: var(--shadow-base);
        }

        .btn-action:active {
            border-color: var(--color-accent-active);
            color: var(--color-accent-active);
            box-shadow: none;
        }

        .btn-view-images {
            background: var(--color-accent-primary);
            color: #fff;
            border-color: var(--color-accent-primary);
        }

        .btn-view-images:hover {
            background: var(--color-accent-hover);
            border-color: var(--color-accent-hover);
            color: #fff;
        }

        .btn-view-images:active {
            background: var(--color-accent-active);
            border-color: var(--color-accent-active);
        }

        .btn-appeal {
            background: var(--color-bg-primary);
            color: var(--color-primary-text);
            border-color: var(--color-border-medium);
        }

        .btn-appeal:hover {
            border-color: var(--color-accent-primary);
            color: var(--color-accent-primary);
        }

        .btn-action i {
            font-size: var(--font-size-base);
        }

        .btn-action .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 6px;
            background: rgba(24, 144, 255, 0.1);
            color: var(--color-accent-primary);
            border: 1px solid rgba(24, 144, 255, 0.2);
            border-radius: 9px;
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            margin-left: var(--spacing-xs);
        }

        .btn-view-images .badge {
            background: rgba(255, 255, 255, 0.25);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* 图片查看模态框增强 */
        .images-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 10001;
            padding: var(--spacing-xl);
            overflow-y: auto;
        }

        .images-modal.active {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .images-modal-header {
            width: 100%;
            max-width: 1200px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md) var(--spacing-lg);
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius-lg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .images-modal-title {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-semibold);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .images-modal-close-btn {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .images-modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg) scale(1.1);
        }

        .images-modal-content {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }

        .images-modal-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: var(--border-radius-base);
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .images-modal-item:hover {
            transform: scale(1.05) translateY(-4px);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .images-modal-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        @media (max-width: 768px) {
            .images-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                gap: var(--spacing-xs);
            }

            .image-modal {
                padding: var(--spacing-md);
            }

            .image-modal img {
                max-height: 80vh;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }

            .images-modal-content {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: var(--spacing-sm);
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
            <!-- 左侧操作区 -->
            <div class="layout-content">
                <h1 class="text-title">黑名单查询系统</h1>
                
                <!-- 公告栏 -->
                <div class="announcement-card">
                    <h3 class="announcement-title">
                        <i class="fas fa-bullhorn"></i>
                        系统公告
                    </h3>
                    <ul class="announcement-list">
                        <li>举报骗子请联系<?php if($contact_qq): ?>QQ <?php echo htmlspecialchars($contact_qq); ?><?php endif; ?><?php if($contact_qq && $contact_email): ?>，或者<?php endif; ?><?php if($contact_email): ?>发送邮件至<?php echo htmlspecialchars($contact_email); ?><?php endif; ?></li>
                        <li>一旦被收录系统，骗子无处可逃！举报需要提供有效证据！</li>
                        <li>如果是被他人恶意举报<?php if($contact_qq || $contact_email): ?>，请联系<?php if($contact_qq): ?>QQ <?php echo htmlspecialchars($contact_qq); ?><?php endif; ?><?php if($contact_qq && $contact_email): ?>或<?php endif; ?><?php if($contact_email): ?>邮箱<?php echo htmlspecialchars($contact_email); ?><?php endif; ?>解除<?php else: ?>，请联系管理员解除<?php endif; ?></li>
                    </ul>
                    <div class="current-time">
                        <i class="fas fa-clock"></i>
                        当前时间：<?php echo $date; ?>
                    </div>
                </div>

                <!-- 查询表单 -->
                <form method="post" class="form-section" id="queryForm">
                    <h2 class="text-heading">查询配置</h2>
                    
                    <div class="form-group">
                        <label for="subject_type" class="form-label">主体类型</label>
                        <select class="form-control form-select" name="subject_type" id="subject_type" required>
                            <option value="">请选择要查询的主体类型</option>
                            <?php
                            foreach ($subject_types as $type) {
                                $selected = (isset($_POST['subject_type']) && $_POST['subject_type'] == $type['type_key']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($type['type_key']) . '" ' . $selected . ' data-placeholder="' . htmlspecialchars($type['placeholder']) . '">' . htmlspecialchars($type['type_name']) . '</option>';
                            }
                            ?>
                        </select>
                        <div class="form-helper">选择您要查询的主体类型，系统将根据类型进行相应的格式验证</div>
                        <div class="feature-tips">
                            ● 支持多种主体类型查询 ● 自动格式验证 ● 实时输入提示
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject" class="form-label">查询内容</label>
                        <input type="text" 
                               class="form-control" 
                               name="subject" 
                               id="subject" 
                               placeholder="请先选择主体类型" 
                               value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" 
                               maxlength="100" 
                               autocomplete="off" 
                               required>
                        <div class="form-helper" id="inputHelper">根据选择的主体类型输入相应的查询内容</div>
                        <div class="form-counter">
                            <span id="charCount">0</span>/100 字符
                        </div>
                    </div>


                </form>


            </div>

            <!-- 右侧预览区 -->
            <div class="layout-sidebar">
                <!-- 实时预览 -->
                <div class="query-preview <?php echo $query_data ? '' : 'empty'; ?>" id="queryPreview">
                    <h3 class="text-heading">查询预览</h3>
                    <?php if ($query_data): ?>
                        <div class="query-result">
                            <div class="result-item">
                                <span class="result-label">查询类型：</span>
                                <span class="result-value"><?php echo htmlspecialchars($query_data['type_name']); ?></span>
                            </div>
                            <div class="result-item">
                                <span class="result-label">查询内容：</span>
                                <span class="result-value"><?php echo htmlspecialchars($query_data['subject']); ?></span>
                            </div>
                            
                            <?php if ($query_data['result']): ?>
                                <div class="result-item">
                                    <span class="result-label">黑名单等级：</span>
                                    <span class="result-value error"><?php echo htmlspecialchars($query_data['result']['level']); ?>级</span>
                                </div>
                                <div class="result-item">
                                    <span class="result-label">录入时间：</span>
                                    <span class="result-value"><?php echo htmlspecialchars($query_data['result']['date']); ?></span>
                                </div>
                                <div class="result-item">
                                    <span class="result-label">黑名单原因：</span>
                                    <span class="result-value"><?php echo htmlspecialchars($query_data['result']['note']); ?></span>
                                </div>
                                
                                <div class="status-error mt-lg">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>风险警告：</strong>该主体已被录入黑名单，请停止任何交易！
                                </div>
                            <?php else: ?>
                                <div class="status-success mt-lg">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>查询结果：</strong>该主体尚未被录入黑名单，但我们不能保证交易绝对安全
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($query_result && isset($query_result['error'])): ?>
                        <div class="query-result">
                            <div class="status-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($query_result['error']); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-huge) 0;">
                            <div class="text-caption">查询结果将在此处实时显示</div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 操作按钮区域 - 仅在查询结果为真时显示 -->
                <?php if ($query_data && $query_data['result']): ?>
                    <?php 
                    // 检查是否有有效图片
                    $hasValidImages = false;
                    $imageCount = 0;
                    if (!empty($query_data['images']) && is_array($query_data['images'])) {
                        $validImages = array_filter($query_data['images'], function($img) {
                            return isset($img['exists']) && $img['exists'];
                        });
                        $imageCount = count($validImages);
                        $hasValidImages = $imageCount > 0;
                    }
                    ?>
                    <?php if ($hasValidImages): ?>
                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-view-images" id="viewImagesBtn" data-images='<?php echo json_encode(array_values($validImages)); ?>'>
                                <i class="fas fa-images"></i>
                                <span>查看图片</span>
                                <span class="badge"><?php echo $imageCount; ?></span>
                            </button>
                            <a href="http://wpa.qq.com/msgrd?v=3&uin=406845294&site=qq&menu=yes" 
                               target="_blank" 
                               class="btn-action btn-appeal">
                                <i class="fas fa-comment-dots"></i>
                                <span>申诉处理</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="action-buttons">
                            <a href="http://wpa.qq.com/msgrd?v=3&uin=406845294&site=qq&menu=yes" 
                               target="_blank" 
                               class="btn-action btn-appeal">
                                <i class="fas fa-comment-dots"></i>
                                <span>申诉处理</span>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>

        <!-- 底部信息区域 -->
        <section class="bottom-info-section">
            <div class="bottom-info-container">
                <!-- 使用指南和系统特性 -->
                <div class="info-grid">
                    <!-- 使用指南 -->
                    <div class="info-card">
                        <h3 class="info-title">
                            <i class="fas fa-compass"></i>
                            使用指南
                        </h3>
                        
                        <h4 class="info-subtitle">查询步骤</h4>
                        <ul class="info-list">
                            <li>选择要查询的主体类型（QQ号码、手机号码等）</li>
                            <li>在输入框中输入具体的查询内容</li>
                            <li>点击"开始查询"按钮执行查询</li>
                            <li>查看右侧预览区域的实时结果</li>
                        </ul>
                        
                        <h4 class="info-subtitle">注意事项</h4>
                        <ul class="info-list">
                            <li>系统会自动验证输入格式的正确性</li>
                            <li>查询结果仅供参考，不能保证绝对准确</li>
                            <li>如有疑问或申诉，请联系管理员</li>
                            <li>请勿恶意查询或提交虚假信息</li>
                        </ul>
                    </div>

                    <!-- 系统特性 -->
                    <div class="info-card">
                        <h3 class="info-title">
                            <i class="fas fa-star"></i>
                            系统特性
                        </h3>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-bolt"></i>
                                <strong>实时查询响应</strong> - 即时显示结果，无需等待
                            </li>
                            <li>
                                <i class="fas fa-layer-group"></i>
                                <strong>多种主体类型支持</strong> - 覆盖常见场景需求
                            </li>
                            <li>
                                <i class="fas fa-check-double"></i>
                                <strong>智能格式验证</strong> - 减少输入错误，提高准确性
                            </li>
                            <li>
                                <i class="fas fa-shield-alt"></i>
                                <strong>专业风险评估</strong> - 提供详细的警告提示
                            </li>
                            <li>
                                <i class="fas fa-mobile-alt"></i>
                                <strong>响应式设计</strong> - 完美适配各种设备屏幕
                            </li>
                            <li>
                                <i class="fas fa-eye"></i>
                                <strong>简洁直观界面</strong> - 用户友好的操作体验
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- 底部版权信息 -->
        <footer class="site-footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-info">
                        <p class="copyright">
                        <?php
                        // 从数据库读取版权信息
                        $config_copyright = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['copyright']);
                        $copyright_text = $config_copyright ? $config_copyright['v'] : ("© " . date('Y') . " " . $sitename . " All rights reserved.");
                        echo htmlspecialchars($copyright_text);
                        ?>
                    </p>
                    </div>
                    <div class="footer-links">
                        <?php
                        // 从数据库读取备案号
                        $config_icp = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['icp']);
                        if ($config_icp && !empty($config_icp['v'])):
                        ?>
                        <a href="https://beian.miit.gov.cn/" target="_blank" class="footer-link">
                            <?php echo htmlspecialchars($config_icp['v']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- JavaScript -->
    <script src="./assets/js/jquery-1.11.3.min.js"></script>
    <script src="./assets/js/real-time-preview.js"></script>
    <script src="./assets/js/inline-help.js"></script>
    <script>
        $(document).ready(function() {
            // 主体类型选择器变化时更新输入框placeholder和帮助文本
            $('#subject_type').change(function() {
                var selectedOption = $(this).find('option:selected');
                var selectedType = selectedOption.val();
                var placeholder = selectedOption.data('placeholder') || '请输入查询内容';
                
                $('#subject').attr('placeholder', placeholder);
                
                if (selectedType) {
                    $('#inputHelper').text('请输入' + selectedOption.text() + '，格式：' + placeholder);
                } else {
                    $('#inputHelper').text('根据选择的主体类型输入相应的查询内容');
                }
                
                // 清空输入框
                $('#subject').val('');
                updateCharCount();
            });
            
            // 字符计数功能
            function updateCharCount() {
                var length = $('#subject').val().length;
                $('#charCount').text(length);
                
                if (length > 80) {
                    $('#charCount').css('color', 'var(--color-warning)');
                } else if (length > 90) {
                    $('#charCount').css('color', 'var(--color-error)');
                } else {
                    $('#charCount').css('color', 'var(--color-tertiary-text)');
                }
            }
            
            $('#subject').on('input', updateCharCount);
            
            // 初始化字符计数
            updateCharCount();
            
            // 禁用表单提交（防止回车键触发表单提交）
            $('#queryForm').on('submit', function(e) {
                e.preventDefault();
                return false;
            });
            
            // 禁用回车键触发表单提交
            $('#subject').on('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // 禁用选择框的回车键提交
            $('#subject_type').on('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // 查看图片按钮点击事件
            $(document).on('click', '#viewImagesBtn', function(e) {
                e.preventDefault();
                
                // 从按钮的data属性获取图片数据
                let imagesData = $(this).data('images');
                
                // 如果 data() 方法返回 undefined，尝试从属性直接读取
                if (!imagesData) {
                    const imagesAttr = $(this).attr('data-images');
                    if (imagesAttr) {
                        try {
                            imagesData = JSON.parse(imagesAttr.replace(/&quot;/g, '"'));
                        } catch (e) {
                            console.error('解析图片数据失败:', e);
                            return;
                        }
                    }
                }
                
                // 如果是字符串，解析JSON
                if (typeof imagesData === 'string') {
                    try {
                        imagesData = JSON.parse(imagesData.replace(/&quot;/g, '"'));
                    } catch (e) {
                        console.error('解析图片数据失败:', e);
                        return;
                    }
                }
                
                if (!imagesData || imagesData.length === 0) {
                    console.warn('没有找到图片数据');
                    return;
                }

                // 转换为需要的格式
                const images = imagesData.map(function(img) {
                    return {
                        src: img.file_path || img.src,
                        alt: img.original_name || img.alt || '证据图片'
                    };
                });

                if (images.length === 0) {
                    console.warn('图片列表为空');
                    return;
                }
                
                console.log('准备显示图片，数量:', images.length);

                // 创建图片查看模态框
                let imagesModal = $('#imagesModal');
                if (imagesModal.length === 0) {
                    imagesModal = $('<div id="imagesModal" class="images-modal"><div class="images-modal-header"><div class="images-modal-title"><i class="fas fa-images"></i> 证据图片 (' + images.length + ')</div><button class="images-modal-close-btn"><i class="fas fa-times"></i></button></div><div class="images-modal-content"></div></div>');
                    $('body').append(imagesModal);
                    
                    // 关闭模态框
                    imagesModal.on('click', '.images-modal-close-btn, .images-modal', function(e) {
                        if (e.target === this || $(e.target).closest('.images-modal-close-btn').length) {
                            imagesModal.removeClass('active');
                        }
                    });
                    
                    // ESC键关闭
                    $(document).on('keydown', function(e) {
                        if (e.key === 'Escape' && imagesModal.hasClass('active')) {
                            imagesModal.removeClass('active');
                        }
                    });
                }

                // 填充图片
                const content = imagesModal.find('.images-modal-content');
                content.empty();
                
                images.forEach(function(img) {
                    const item = $('<div class="images-modal-item"><img src="' + img.src + '" alt="' + img.alt + '"></div>');
                    item.on('click', function() {
                        // 点击图片可以查看大图
                        let imageModal = $('#imageModal');
                        if (imageModal.length === 0) {
                            imageModal = $('<div id="imageModal" class="image-modal"><div class="image-modal-content"><span class="image-modal-close"><i class="fas fa-times"></i></span><img src="" alt=""></div></div>');
                            $('body').append(imageModal);
                            
                            imageModal.on('click', function(e) {
                                if (e.target === this || $(e.target).closest('.image-modal-close').length) {
                                    imageModal.removeClass('active');
                                }
                            });
                        }
                        imageModal.find('img').attr('src', img.src).attr('alt', img.alt);
                        imageModal.addClass('active');
                    });
                    content.append(item);
                });

                // 更新标题和显示模态框
                imagesModal.find('.images-modal-title').html('<i class="fas fa-images"></i> 证据图片 (' + images.length + ')');
                imagesModal.addClass('active');
                
                console.log('模态框已显示，图片数量:', images.length);
            });

            // 图片查看功能（点击缩略图）
            $(document).on('click', '.image-link', function(e) {
                e.preventDefault();
                const imageSrc = $(this).data('image');
                if (!imageSrc) return;
                
                // 创建模态框
                let modal = $('#imageModal');
                if (modal.length === 0) {
                    modal = $('<div id="imageModal" class="image-modal"><div class="image-modal-content"><span class="image-modal-close"><i class="fas fa-times"></i></span><img src="" alt=""></div></div>');
                    $('body').append(modal);
                    
                    // 关闭模态框
                    modal.on('click', function(e) {
                        if (e.target === this || $(e.target).closest('.image-modal-close').length) {
                            modal.removeClass('active');
                        }
                    });
                    
                    // ESC键关闭
                    $(document).on('keydown', function(e) {
                        if (e.key === 'Escape' && modal.hasClass('active')) {
                            modal.removeClass('active');
                        }
                    });
                }
                
                modal.find('img').attr('src', imageSrc);
                modal.addClass('active');
            });
        });
    </script>
</body>
</html>
<?php
if (isset($DB)) {
    $DB->close();
}
?>