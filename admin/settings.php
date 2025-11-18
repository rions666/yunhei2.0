<?php

$mod = 'blank';
include("../include/common.php");

if ($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

$title = '系统设置';
$error_message = '';
$success_message = '';

$current_user = null;
if (isset($_COOKIE["admin_token"])) {
    $token = authcode($_COOKIE['admin_token'], 'DECODE', SYS_KEY);
    if ($token && strpos($token, "\t") !== false) {
        list($user, $sid) = explode("\t", $token, 2);
        $current_user = trim($user);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'settings';

    if ($action === 'settings') {

        $site_name = trim($_POST['site_name'] ?? '');
        $site_copyright = trim($_POST['site_copyright'] ?? '');
        $site_icp = trim($_POST['site_icp'] ?? '');
        $contact_qq = trim($_POST['contact_qq'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');

        if (empty($site_name)) {
            $error_message = '网站标题不能为空！';
        } else {

            $updates = [
                'sitename' => $site_name,
                'copyright' => $site_copyright,
                'icp' => $site_icp,
                'contact_qq' => $contact_qq,
                'contact_email' => $contact_email
            ];

            $success_count = 0;
            foreach ($updates as $key => $value) {
                
                $exists = $DB->get_row_prepared("SELECT * FROM black_config WHERE k = ?", [$key]);

                if ($exists) {
                    
                    $result = $DB->execute_prepared("UPDATE black_config SET v = ? WHERE k = ?", [$value, $key]);
                } else {
                    
                    $result = $DB->execute_prepared("INSERT INTO black_config (k, v) VALUES (?, ?)", [$key, $value]);
                }

                if ($result) {
                    $success_count++;
                }
            }

            if ($success_count == count($updates)) {
                $success_message = '设置保存成功！';
                
                $sitename = $site_name;
            } else {
                $error_message = '部分设置保存失败，请重试！';
            }
        }
    } elseif ($action === 'password') {
        
        if (!$current_user) {
            $error_message = '无法获取用户信息！';
        } else {
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
    }
}

$config_sitename = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['sitename']);
$config_copyright = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['copyright']);
$config_icp = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['icp']);
$config_contact_qq = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['contact_qq']);
$config_contact_email = $DB->get_row_prepared("SELECT v FROM black_config WHERE k = ?", ['contact_email']);

$current_sitename = $config_sitename ? $config_sitename['v'] : $sitename;
$current_copyright = $config_copyright ? $config_copyright['v'] : '';
$current_icp = $config_icp ? $config_icp['v'] : '';
$current_contact_qq = $config_contact_qq ? $config_contact_qq['v'] : '';
$current_contact_email = $config_contact_email ? $config_contact_email['v'] : '';

include './layout/header.php';
?>

<style>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
}

.settings-card {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin-bottom: 1.5rem;
}

.settings-section {
    margin-bottom: 2rem;
}

.settings-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    color: #667eea;
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
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-helper {
    font-size: 0.8125rem;
    color: #6b7280;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.form-helper i {
    color: #9ca3af;
}

.alert {
    padding: 1rem 1.5rem;
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

.alert-success {
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #16a34a;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
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
    background: #667eea;
    color: #fff;
}

.btn-primary:hover {
    background: #5568d3;
}

.btn-secondary {
    background: #6b7280;
    color: #fff;
}

.btn-secondary:hover {
    background: #4b5563;
}

@media (max-width: 768px) {
    .settings-container {
        padding: 0;
    }

    .settings-card {
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

<div class="settings-container">
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

    <form method="post" id="settingsForm">
        <div class="settings-card">
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-globe"></i>
                    网站信息
                </h2>

                <div class="form-group">
                    <label for="site_name" class="form-label">
                        网站标题 <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="site_name"
                           name="site_name"
                           class="form-control"
                           value="<?php echo htmlspecialchars($current_sitename); ?>"
                           maxlength="100"
                           required>
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        显示在网站顶部和浏览器标题栏
                    </div>
                </div>

                <div class="form-group">
                    <label for="site_copyright" class="form-label">
                        版权信息
                    </label>
                    <input type="text"
                           id="site_copyright"
                           name="site_copyright"
                           class="form-control"
                           value="<?php echo htmlspecialchars($current_copyright); ?>"
                           maxlength="200"
                           placeholder="例如：© 2025 黑名单查询系统. All rights reserved.">
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        显示在网站底部，留空则不显示
                    </div>
                </div>

                <div class="form-group">
                    <label for="site_icp" class="form-label">
                        备案号
                    </label>
                    <input type="text"
                           id="site_icp"
                           name="site_icp"
                           class="form-control"
                           value="<?php echo htmlspecialchars($current_icp); ?>"
                           maxlength="100"
                           placeholder="例如：京ICP备12345678号">
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        显示在网站底部，留空则不显示
                    </div>
                </div>

                <div class="form-group">
                    <label for="contact_qq" class="form-label">
                        联系QQ
                    </label>
                    <input type="text"
                           id="contact_qq"
                           name="contact_qq"
                           class="form-control"
                           value="<?php echo htmlspecialchars($current_contact_qq); ?>"
                           maxlength="20"
                           placeholder="例如：406845294">
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        显示在首页系统公告中，留空则不显示
                    </div>
                </div>

                <div class="form-group">
                    <label for="contact_email" class="form-label">
                        联系邮箱
                    </label>
                    <input type="email"
                           id="contact_email"
                           name="contact_email"
                           class="form-control"
                           value="<?php echo htmlspecialchars($current_contact_email); ?>"
                           maxlength="100"
                           placeholder="例如：406845294@qq.com">
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        显示在首页系统公告中，留空则不显示
                    </div>
                </div>
            </div>

            <input type="hidden" name="action" value="settings">

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    保存设置
                </button>
            </div>
        </div>
    </form>

    <form method="post" id="passwordForm">
        <div class="settings-card">
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-key"></i>
                    修改密码
                </h2>

                <div class="form-group">
                    <label for="oldpwd" class="form-label">
                        当前密码 <span class="required">*</span>
                    </label>
                    <input type="password"
                           id="oldpwd"
                           name="oldpwd"
                           class="form-control"
                           placeholder="请输入当前密码"
                           autocomplete="current-password">
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        请输入您当前使用的密码
                    </div>
                </div>

                <div class="form-group">
                    <label for="newpwd" class="form-label">
                        新密码 <span class="required">*</span>
                    </label>
                    <input type="password"
                           id="newpwd"
                           name="newpwd"
                           class="form-control"
                           placeholder="请输入新密码（至少6位）"
                           minlength="6"
                           maxlength="32"
                           autocomplete="new-password">
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        密码长度至少6位，建议使用字母、数字和符号组合
                    </div>
                </div>

                <div class="form-group">
                    <label for="newpwd2" class="form-label">
                        确认新密码 <span class="required">*</span>
                    </label>
                    <input type="password"
                           id="newpwd2"
                           name="newpwd2"
                           class="form-control"
                           placeholder="请再次输入新密码"
                           minlength="6"
                           maxlength="32"
                           autocomplete="new-password">
                    <div class="form-helper">
                        <i class="fas fa-info-circle"></i>
                        请再次输入新密码以确认
                    </div>
                </div>
            </div>

            <input type="hidden" name="action" value="password">

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    修改密码
                </button>
            </div>
        </div>
    </form>

    <div class="form-actions" style="border-top: none; padding-top: 0;">
        <a href="./" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            返回仪表盘
        </a>
    </div>
</div>

<script>

document.getElementById('settingsForm').addEventListener('submit', function(e) {
    const siteName = document.getElementById('site_name').value.trim();

    if (!siteName) {
        e.preventDefault();
        alert('网站标题不能为空！');
        document.getElementById('site_name').focus();
        return false;
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
    submitBtn.disabled = true;
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const oldpwd = document.getElementById('oldpwd').value;
    const newpwd = document.getElementById('newpwd').value;
    const newpwd2 = document.getElementById('newpwd2').value;

    if (!oldpwd || !newpwd || !newpwd2) {
        e.preventDefault();
        alert('请填写所有密码字段！');
        return false;
    }

    if (newpwd !== newpwd2) {
        e.preventDefault();
        alert('两次输入的新密码不一致！');
        document.getElementById('newpwd2').focus();
        return false;
    }

    if (newpwd.length < 6) {
        e.preventDefault();
        alert('新密码长度不能少于6位！');
        document.getElementById('newpwd').focus();
        return false;
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
    submitBtn.disabled = true;
});

document.getElementById('newpwd2').addEventListener('input', function() {
    const newpwd = document.getElementById('newpwd').value;
    const newpwd2 = this.value;

    if (newpwd2 && newpwd !== newpwd2) {
        this.style.borderColor = '#ef4444';
    } else {
        this.style.borderColor = '#d1d5db';
    }
});

setTimeout(function() {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        successAlert.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(function() {
            successAlert.remove();
        }, 300);
    }
}, 3000);
</script>

<?php include './layout/footer.php'; ?>
