<?php

$mod = 'blank';
include("../include/common.php");
$title = '管理登录';

// 处理登录逻辑
if(isset($_POST['user']) && isset($_POST['pass'])){
    $user = trim((string)$_POST['user']);
    $user_name = $user;
    $passRaw = (string)$_POST['pass']; // 不trim密码，保留原始输入
    
    if(empty($user) || empty($passRaw)) {
        $error_message = '用户名和密码不能为空！';
    } else {
        $row = $DB->get_row_prepared("SELECT * FROM black_admin WHERE user = ? limit 1", [$user]);
        
        if(!$row || empty($row['user'])) {
            $error_message = '此用户不存在';
        } else {
            $stored_password = $row['pass'];
            $password_valid = false;
            
            // 支持新密码哈希（password_hash）和旧密码（md5）的兼容验证
            // 判断是否为新的 password_hash 格式（以 $ 开头）
            if (strpos($stored_password, '$') === 0) {
                // 新格式：使用 password_verify
                $password_valid = password_verify($passRaw, $stored_password);
            } else {
                // 旧格式：使用 md5 兼容验证
                $pass_md5 = md5($passRaw);
                $password_valid = ($pass_md5 === $stored_password);
            }
            
            if (!$password_valid) {
                $error_message = '用户名或密码不正确！';
            } else {
                // 密码验证成功，创建会话
                // 使用存储的密码值计算 session（与 member.php 保持一致）
                $session = md5($user . $stored_password . $password_hash);
                $token = authcode("{$user}\t{$session}", 'ENCODE', SYS_KEY);
                setcookie("admin_token", $token, time() + 7200);
                
                // 直接跳转，不显示弹窗
                @header('Location: ./');
                exit;
            }
        }
    }
}

// 处理退出登录
if(isset($_GET['logout'])){
    setcookie("admin_token", "", time() - 3600);
    // 直接跳转，不显示弹窗
    @header('Location: ./login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?=$title?> - <?=$sitename?></title>
    <link href="../assets/css/font-awesome.min.css" rel="stylesheet"/>
    <link href="./assets/admin.css" rel="stylesheet"/>
    <style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .login-container {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 400px;
        text-align: center;
    }
    
    .login-header {
        margin-bottom: 30px;
    }
    
    .login-logo {
        font-size: 3rem;
        color: #667eea;
        margin-bottom: 10px;
    }
    
    .login-title {
        color: #2c3e50;
        font-size: 1.8rem;
        font-weight: 600;
        margin: 0;
    }
    
    .login-subtitle {
        color: #6c757d;
        font-size: 0.95rem;
        margin: 5px 0 0 0;
    }
    
    .login-form {
        text-align: left;
    }
    
    .form-group {
        margin-bottom: 20px;
        position: relative;
    }
    
    .form-control {
        width: 100%;
        padding: 15px 20px 15px 50px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-control:focus ~ .form-icon {
        color: #667eea;
    }
    
    .form-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        font-size: 1.1rem;
        z-index: 10;
        pointer-events: none;
    }
    
    .login-btn {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }
    
    .login-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    .back-link {
        display: inline-block;
        margin-top: 20px;
        color: #6c757d;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.3s ease;
    }
    
    .back-link:hover {
        color: #667eea;
    }
    
    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #dc3545;
        font-size: 0.9rem;
    }
    
    @media (max-width: 480px) {
        .login-container {
            margin: 20px;
            padding: 30px 25px;
        }
    }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title"><?php echo $sitename; ?></h1>
            <p class="login-subtitle">管理后台登录</p>
        </div>
        
        <?php if(isset($error_message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['success'])): ?>
        <div class="success-message" style="background: #d1fae5; border: 1px solid #10b981; color: #059669; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars(urldecode($_GET['success'])); ?></span>
        </div>
        <?php endif; ?>
        
        <form method="post" class="login-form">
            <div class="form-group">
                <input type="text" name="user" class="form-control" placeholder="请输入用户名" required>
                <i class="fas fa-user form-icon"></i>
            </div>
            
            <div class="form-group">
                <input type="password" name="pass" class="form-control" placeholder="请输入密码" required>
                <i class="fas fa-lock form-icon"></i>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                登录后台
            </button>
        </form>
        
        <a href="../" class="back-link">
            <i class="fas fa-arrow-left"></i>
            返回首页
        </a>
    </div>
</body>
</html>