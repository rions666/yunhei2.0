<?php
/**
 * 安装检测逻辑
 * 检测系统是否已安装
 */

// 检查安装锁文件
function is_installed() {
    $lock_file = __DIR__ . '/install.lock';
    if (file_exists($lock_file)) {
        return true;
    }
    
    // 检查 config.php 是否存在且可读
    $config_file = __DIR__ . '/config.php';
    if (file_exists($config_file) && is_readable($config_file)) {
        // 检查数据库连接是否可用
        try {
            require_once $config_file;
            if (isset($host) && isset($user) && isset($dbname)) {
                if (!defined('IN_CRONLITE')) {
                    define('IN_CRONLITE', true);
                }
                if (!defined('ROOT')) {
                    define('ROOT', __DIR__ . '/include/');
                }

                // 使用输出缓冲和错误抑制来捕获数据库连接错误
                ob_start();
                $db_connected = false;

                // 临时设置错误处理
                set_error_handler(function($errno, $errstr, $errfile, $errline) {
                    // 抑制所有错误
                    return true;
                });

                try {
                    // 尝试连接数据库
                    @$test_conn = mysqli_connect($host, $user, $pwd ?? '', $dbname, $port ?? 3306);
                    if ($test_conn) {
                        mysqli_close($test_conn);
                        $db_connected = true;
                    }
                } catch (Exception $e) {
                    $db_connected = false;
                }

                restore_error_handler();
                ob_end_clean();

                if ($db_connected) {
                    include_once(__DIR__ . '/include/db.class.php');
                    $DB = new DB($host, $user, $pwd ?? '', $dbname, $port ?? 3306);

                    // 检查关键表是否存在
                    $tables = ['black_admin', 'black_list', 'black_config'];
                    $all_tables_exist = true;
                    foreach ($tables as $table) {
                        $result = $DB->get_row("SHOW TABLES LIKE '{$table}'");
                        if (!$result) {
                            $all_tables_exist = false;
                            break;
                        }
                    }

                    if ($all_tables_exist) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            // 数据库连接失败，认为未安装
        }
    }
    
    return false;
}

// 检查是否需要跳转到安装页面
function check_install_redirect() {
    // 如果访问的是 install.php，不重定向
    $current_script = basename($_SERVER['PHP_SELF']);
    if ($current_script === 'install.php' || strpos($_SERVER['REQUEST_URI'], '/install') !== false) {
        return;
    }
    
    // 检查是否已安装
    if (!is_installed()) {
        // 构建绝对URL，支持IP和域名访问
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base_path = ($script_path === '/' || $script_path === '\\') ? '' : rtrim($script_path, '/');
        $install_url = $protocol . $host . $base_path . '/install.php';
        
        header('Location: ' . $install_url);
        exit;
    }
}

