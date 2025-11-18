<?php
if(!defined('IN_CRONLITE'))exit();

$my=isset($_GET['my'])?$_GET['my']:null;

$clientip=$_SERVER['REMOTE_ADDR'];

// 初始化登录状态
$islogin = 0;

if(isset($_COOKIE["admin_token"]))
{
    $token=authcode($_COOKIE['admin_token'], 'DECODE', SYS_KEY);
    if($token && strpos($token, "\t") !== false) {
        list($user, $sid) = explode("\t", $token, 2);
        $user = trim($user);
        
        if (!empty($user)) {
            $udata = $DB->get_row_prepared("SELECT * FROM black_admin WHERE user = ? limit 1", [$user]);
            
            if($udata && !empty($udata['user'])) {
                // 支持新密码哈希和旧密码的兼容验证
                $stored_password = $udata['pass'];
                // 计算 session（使用存储的密码值，无论是新格式还是旧格式）
                // 注意：这里使用存储的密码值来计算 session，与登录时保持一致
                $session=md5($udata['user'].$stored_password.$password_hash);
                
                if($session==$sid) {
                    $DB->execute_prepared("UPDATE black_admin SET last = ?, dlip = ? WHERE user = ?", [$date, $clientip, $user]);
                    $islogin=1;
                    if($udata['active']==0){
                        @header('Content-Type: text/html; charset=UTF-8');
                        exit('您的账号已被封禁！');
                    }
                }
            }
        }
    }
}
?>