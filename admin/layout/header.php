<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?=$title?> - <?=$sitename?></title>
    <link href="../assets/css/font-awesome.min.css" rel="stylesheet"/>
    <link href="./assets/admin.css" rel="stylesheet"/>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .admin-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #000000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 1000;
            border-bottom: 1px solid #e5e5e5;
        }

        .admin-header .logo {
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: opacity 0.2s ease;
            letter-spacing: 0.5px;
        }

        .admin-header .logo:hover {
            opacity: 0.85;
        }

        .admin-header .logo i {
            font-size: 1.5rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 400;
        }

        .user-info i {
            font-size: 1.125rem;
        }

        .logout-btn {
            color: #000000;
            background: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid #e5e5e5;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: #f5f5f5;
        }

        .admin-main {
            margin-top: 60px;
            padding: 2rem;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            min-height: calc(100vh - 60px);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 2rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e5e5;
        }

        .breadcrumb-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .breadcrumb-item {
            color: #666666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 400;
            transition: color 0.2s ease;
        }

        .breadcrumb-item:hover {
            color: #000000;
        }

        .breadcrumb-item.active {
            color: #000000;
            font-weight: 600;
            font-size: 1rem;
        }

        .breadcrumb-separator {
            color: #cccccc;
            font-size: 0.75rem;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f5f5f5;
            color: #666666;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid #e5e5e5;
        }

        .back-btn:hover {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        .back-btn i {
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .admin-header {
                padding: 0 1rem;
                height: 56px;
            }

            .admin-header .logo {
                font-size: 0.875rem;
            }

            .admin-header .logo i {
                font-size: 1.25rem;
            }

            .user-info span {
                display: none;
            }

            .logout-btn span {
                display: none;
            }

            .admin-main {
                margin-top: 56px;
                padding: 1rem;
                min-height: calc(100vh - 56px);
            }

            .breadcrumb {
                padding: 0.75rem 0;
                gap: 0.5rem;
            }

            .breadcrumb-item {
                font-size: 0.8125rem;
            }

            .breadcrumb-item.active {
                font-size: 0.9375rem;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <header class="admin-header">
        <a href="./" class="logo">
            <i class="fas fa-shield-alt"></i>
            <span><?php echo $sitename; ?> 管理后台</span>
        </a>

        <div class="user-menu">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>管理员</span>
            </div>
            <a href="./login.php?logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>退出登录</span>
            </a>
        </div>
    </header>

    <!-- 主内容区域 -->
    <main class="admin-main">
        <?php if(basename($_SERVER['PHP_SELF']) != 'index.php'): ?>
        <!-- 面包屑导航 -->
        <div class="breadcrumb">
            <div class="breadcrumb-left">
                <a href="./" class="breadcrumb-item">
                    <i class="fas fa-home"></i>
                    <span>仪表盘</span>
                </a>
                <i class="fas fa-chevron-right breadcrumb-separator"></i>
                <span class="breadcrumb-item active">
                    <i class="fas fa-<?php
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $icons = [
                            'index.php' => 'tachometer-alt',
                            'add.php' => 'plus-circle',
                            'list.php' => 'list',
                            'search.php' => 'search',
                            'review.php' => 'check-circle',
                            'subject_types.php' => 'tags',
                            'passwd.php' => 'key',
                            'backup.php' => 'database',
                            'edit.php' => 'edit',
                            'settings.php' => 'cog'
                        ];
                        echo $icons[$current_page] ?? 'file';
                    ?>"></i>
                    <?=$title?>
                </span>
            </div>
            <a href="javascript:history.back()" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>返回</span>
            </a>
        </div>
        <?php endif; ?>