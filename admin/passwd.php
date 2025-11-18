<?php

$mod = 'blank';
include(__DIR__.'/../include/common.php');

if ($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

$current_user = null;
if (isset($_COOKIE["admin_token"])) {
    $token = authcode($_COOKIE['admin_token'], 'DECODE', SYS_KEY);
    if ($token && strpos($token, "\t") !== false) {
        list($user, $sid) = explode("\t", $token, 2);
        $current_user = trim($user);
    }
}

if (!$current_user) {
    exit("<script language='javascript'>alert('无法获取用户信息'); window.location.href='./login.php';</script>");
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $oldpwd_raw = (string)($_POST['oldpwd'] ?? '');
    $newpwd = trim((string)($_POST['newpwd'] ?? ''));
    $newpwd2 = trim((string)($_POST['newpwd2'] ?? ''));

    if (empty($oldpwd_raw) || empty($newpwd)) {
        $error_message = '密码不能为空！';
    } elseif ($newpwd !== $newpwd2) {
        $error_message = '两次输入的新密码不一致！';
    } elseif (strlen($newpwd) < 6) {
        $error_message = '新密码长度不能少于6位！';
    } elseif (strlen($newpwd) > 32) {
        $error_message = '新密码长度不能超过32位！';
    } else {
        
        $row = $DB->get_row_prepared("SELECT * FROM black_admin WHERE user = ? LIMIT 1", [$current_user]);
        if (!$row || empty($row['user'])) {
            $error_message = '用户信息获取失败，请重新登录！';
        } else {
            $stored_password = $row['pass'];
            $password_valid = false;

            if (strpos($stored_password, '$') === 0) {
                
                $password_valid = password_verify($oldpwd_raw, $stored_password);
            } else {
                
                $pass_md5 = md5($oldpwd_raw);
                $password_valid = ($pass_md5 === $stored_password);
            }
            
            if (!$password_valid) {
                $error_message = '原密码错误！';
            } else {
                
                $new_pass_hash = md5($newpwd);
                
                if ($DB->execute_prepared("UPDATE black_admin SET pass = ? WHERE user = ?", [$new_pass_hash, $current_user])) {

                    setcookie("admin_token", "", time() - 3600, '/');
                    
                    $success_msg = urlencode('密码修改完成，请重新登录');
                    @header('Location: ./login.php?success=' . $success_msg);
                    exit;
                } else {
                    $error_message = '密码修改失败，请稍后重试！';
                }
            }
        }
    }
}

$title = '修改密码';
include './layout/header.php';
?>

<link href="../assets/css/design-system.css" rel="stylesheet">
<link href="../assets/css/font-awesome.min.css" rel="stylesheet">
<style>
    .add-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem;
    }

    .form-section {
        background: #fff;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
        font-size: 0.9375rem;
    }

    .required {
        color: #ef4444;
        margin-left: 0.25rem;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.9375rem;
        transition: all 0.2s;
        background: #fff;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-control:disabled {
        background: #f9fafb;
        cursor: not-allowed;
    }

    .form-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #f3f4f6;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-size: 0.9375rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-primary {
        background: #3b82f6;
        color: #fff;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .btn-secondary {
        background: #6b7280;
        color: #fff;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
    }

    .alert-info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }

    .help-text {
        font-size: 0.8125rem;
        color: #6b7280;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .password-strength {
        margin-top: 0.75rem;
        display: none;
    }

    .password-strength.active {
        display: block;
    }

    .strength-bar {
        height: 4px;
        background: #e5e7eb;
        border-radius: 2px;
        overflow: hidden;
        margin-top: 0.5rem;
    }

    .strength-bar-fill {
        height: 100%;
        width: 0%;
        transition: all 0.3s;
        border-radius: 2px;
    }

    .strength-bar-fill.weak {
        width: 33%;
        background: #ef4444;
    }

    .strength-bar-fill.medium {
        width: 66%;
        background: #f59e0b;
    }

    .strength-bar-fill.strong {
        width: 100%;
        background: #10b981;
    }

    .strength-text {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }

    .password-match-error {
        font-size: 0.8125rem;
        color: #dc2626;
        margin-top: 0.5rem;
        display: none;
    }

    .password-match-error.active {
        display: block;
    }

    @media (max-width: 768px) {
        .add-container {
            padding: 1rem;
        }

        .form-section {
            padding: 1.5rem;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="add-container">
    <?php if ($error_message): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error_message); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($success_message); ?></span>
    </div>
    <?php endif; ?>

    <form method="post" id="passwordForm">
        
        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-key"></i>
                修改密码
            </h2>

            <div class="form-group">
                <label for="oldpwd" class="form-label">
                    当前密码 <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="oldpwd" 
                    name="oldpwd" 
                    class="form-control" 
                    placeholder="请输入当前密码" 
                    required
                    autocomplete="current-password"
                >
                <div class="help-text">
                    <i class="fas fa-info-circle"></i>
                    请输入您当前使用的密码
                </div>
            </div>

            <div class="form-group">
                <label for="newpwd" class="form-label">
                    新密码 <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="newpwd" 
                    name="newpwd" 
                    class="form-control" 
                    placeholder="请输入新密码" 
                    minlength="6"
                    maxlength="32"
                    required
                    autocomplete="new-password"
                >
                <div class="help-text">
                    <i class="fas fa-info-circle"></i>
                    密码长度至少6位，建议使用字母、数字和符号组合
                </div>
                <div class="password-strength" id="passwordStrength">
                    <div class="strength-bar">
                        <div class="strength-bar-fill" id="strengthBarFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="newpwd2" class="form-label">
                    确认新密码 <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="newpwd2" 
                    name="newpwd2" 
                    class="form-control" 
                    placeholder="请再次输入新密码" 
                    minlength="6"
                    maxlength="32"
                    required
                    autocomplete="new-password"
                >
                <div class="help-text">
                    <i class="fas fa-info-circle"></i>
                    请再次输入新密码以确认
                </div>
                <div class="password-match-error" id="passwordMatch">
                    <i class="fas fa-exclamation-circle"></i>
                    两次输入的密码不一致
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="section-title">
                <i class="fas fa-shield-alt"></i>
                安全提示
            </h2>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>密码安全建议：</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                        <li>请定期更换密码以确保账户安全</li>
                        <li>密码应包含字母、数字和特殊字符</li>
                        <li>不要使用过于简单或常见的密码</li>
                        <li>修改密码后需要重新登录</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fas fa-save"></i>
                修改密码
            </button>
            <a href="./" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                返回首页
            </a>
        </div>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('passwordForm');
    const oldpwd = document.getElementById('oldpwd');
    const newpwd = document.getElementById('newpwd');
    const newpwd2 = document.getElementById('newpwd2');
    const passwordStrength = document.getElementById('passwordStrength');
    const strengthBarFill = document.getElementById('strengthBarFill');
    const strengthText = document.getElementById('strengthText');
    const passwordMatch = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');

    function checkPasswordStrength(password) {
        let score = 0;
        let feedback = [];

        if (password.length >= 8) {
            score++;
        } else {
            feedback.push('至少8个字符');
        }

        if (/[a-z]/.test(password)) {
            score++;
        } else {
            feedback.push('包含小写字母');
        }

        if (/[A-Z]/.test(password)) {
            score++;
        } else {
            feedback.push('包含大写字母');
        }

        if (/[0-9]/.test(password)) {
            score++;
        } else {
            feedback.push('包含数字');
        }

        if (/[^A-Za-z0-9]/.test(password)) {
            score++;
        } else {
            feedback.push('包含特殊字符');
        }

        if (score < 2) {
            return { level: 'weak', text: '弱', feedback: feedback };
        } else if (score < 4) {
            return { level: 'medium', text: '中等', feedback: feedback };
        } else {
            return { level: 'strong', text: '强', feedback: [] };
        }
    }

    function updatePasswordStrength() {
        const password = newpwd.value;
        
        if (password.length === 0) {
            passwordStrength.classList.remove('active');
            return;
        }

        passwordStrength.classList.add('active');
        const strength = checkPasswordStrength(password);
        
        strengthBarFill.className = 'strength-bar-fill ' + strength.level;
        
        if (strength.feedback.length > 0) {
            strengthText.textContent = '建议：' + strength.feedback.join('、');
            strengthText.style.color = '#6b7280';
        } else {
            strengthText.textContent = '密码强度：' + strength.text;
            strengthText.style.color = strength.level === 'strong' ? '#10b981' : '#f59e0b';
        }
    }

    function checkPasswordMatch() {
        if (newpwd2.value.length === 0) {
            passwordMatch.classList.remove('active');
            newpwd2.setCustomValidity('');
            return;
        }

        if (newpwd.value !== newpwd2.value) {
            passwordMatch.classList.add('active');
            newpwd2.setCustomValidity('两次输入的密码不一致');
        } else {
            passwordMatch.classList.remove('active');
            newpwd2.setCustomValidity('');
        }
    }

    newpwd.addEventListener('input', updatePasswordStrength);
    newpwd2.addEventListener('input', checkPasswordMatch);
    newpwd.addEventListener('input', function() {
        if (newpwd2.value.length > 0) {
            checkPasswordMatch();
        }
    });

    form.addEventListener('submit', function(e) {
        if (newpwd.value !== newpwd2.value) {
            e.preventDefault();
            checkPasswordMatch();
            newpwd2.focus();
            return false;
        }

        if (newpwd.value.length < 6) {
            e.preventDefault();
            alert('密码长度不能少于6位！');
            newpwd.focus();
            return false;
        }

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
        submitBtn.disabled = true;
    });
})();
</script>

<?php include './layout/footer.php'; ?>
