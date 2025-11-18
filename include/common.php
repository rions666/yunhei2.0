<?php
//error_reporting(E_ALL); ini_set("display_errors", 1);
error_reporting(0);
define('IN_CRONLITE', true);
define('ROOT', dirname(__FILE__).'/');
define('SYS_KEY', 'blackqq348069510');

date_default_timezone_set("PRC");
$date = date("Y-m-d H:i:s");
session_start();

// 检查配置文件是否存在（排除安装页面）
if (basename($_SERVER['PHP_SELF']) !== 'install.php' && 
    strpos($_SERVER['REQUEST_URI'], '/install') === false &&
    !file_exists(ROOT.'../config.php')) {
    // 构建绝对URL，支持IP和域名访问
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base_path = ($script_path === '/' || $script_path === '\\') ? '' : rtrim($script_path, '/');
    $install_url = $protocol . $host . $base_path . '/install.php';
    header('Location: ' . $install_url);
    exit;
}

require ROOT.'../config.php';

$scriptpath=str_replace('\\','/',$_SERVER['SCRIPT_NAME']);
$sitepath = substr($scriptpath, 0, strrpos($scriptpath, '/'));
$siteurl = ($_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$sitepath.'/';

if(!isset($port))$port='3306';
//连接数据库
include_once(ROOT."db.class.php");
$DB=new DB($host,$user,$pwd,$dbname,$port);

$password_hash='!@#%!s?';
include ROOT."function.php";
include ROOT."member.php";
$tryEnsure = function_exists('ensure_blacklist_schema');
if($tryEnsure){
    ensure_blacklist_schema();
}
// 确保用户提交表存在
$tryEnsureSubmit = function_exists('ensure_submit_schema');
if($tryEnsureSubmit){
    ensure_submit_schema();
}
// 确保多主体类型系统已升级
$tryEnsureMultiSubject = function_exists('ensure_multi_subject_schema');
if($tryEnsureMultiSubject){
    ensure_multi_subject_schema();
}

// 获取站点名称
$sitename = null;

try {
    $sitename = selectconfig("sitename");
} catch (Exception $e) {
    // 如果配置获取失败，使用默认值
    $sitename = '黑名单查询系统';
}

// 如果仍然为空，使用默认值
if (empty($sitename)) {
    $sitename = '黑名单查询系统';
}
?>