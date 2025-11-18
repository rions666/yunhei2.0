<?php
/**
 * 安装处理脚本
 * 执行数据库初始化和配置创建
 */

// 启动session用于安装流程验证
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 关闭输出缓冲并清除任何已有的输出
while (ob_get_level()) {
    ob_end_clean();
}

// 关闭错误输出到页面（避免污染JSON响应）
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// 设置JSON响应头
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
}

// 错误处理
function sendJsonResponse($success, $message = '', $data = []) {
    // 清除所有输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $response = [
        'success' => (bool)$success,
        'message' => (string)$message,
        'data' => is_array($data) ? $data : []
    ];
    
    // 确保设置了正确的响应头
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    }
    
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        // 如果JSON编码失败，返回错误信息
        $errorResponse = [
            'success' => false,
            'message' => 'JSON编码失败：' . json_last_error_msg(),
            'data' => []
        ];
        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    } else {
        echo $json;
    }
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, '无效的请求方法');
}

// 获取操作类型
$action = isset($_GET['action']) ? $_GET['action'] : 'install';

// 如果是测试连接
if ($action === 'test') {
    // 清除输出
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 获取POST数据
    $db_host = isset($_POST['db_host']) ? trim($_POST['db_host']) : '';
    $db_port = isset($_POST['db_port']) ? (trim($_POST['db_port']) !== '' ? intval($_POST['db_port']) : 3306) : 3306;
    $db_name = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
    $db_user = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
    $db_pass = isset($_POST['db_pass']) ? trim($_POST['db_pass']) : '';
    
    // 调试：记录接收到的数据（仅在开发环境）
    // error_log('Test connection received: host=' . $db_host . ', name=' . $db_name . ', user=' . $db_user);
    
    // 验证必填项
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $missing = [];
        if (empty($db_host)) $missing[] = '数据库地址';
        if (empty($db_name)) $missing[] = '数据库名称';
        if (empty($db_user)) $missing[] = '数据库用户名';
        sendJsonResponse(false, '请填写完整的数据库配置信息：' . implode('、', $missing));
    }
    
    // 测试数据库连接
    try {
        // 清除任何输出
        if (ob_get_level()) {
            ob_clean();
        }
        
        if (!defined('IN_CRONLITE')) {
            define('IN_CRONLITE', true);
        }
        if (!defined('ROOT')) {
            define('ROOT', __DIR__ . '/include/');
        }
        
        include_once(__DIR__ . '/include/db.class.php');
        
        $DB = new DB($db_host, $db_user, $db_pass, $db_name, $db_port);
        
        if (!$DB || !$DB->link) {
            sendJsonResponse(false, '数据库连接失败：无法建立连接');
            exit;
        }
        
        // 尝试查询数据库版本
        $result = $DB->get_row("SELECT VERSION() as version");
        
        if ($result && isset($result['version'])) {
            sendJsonResponse(true, '数据库连接成功！', ['version' => $result['version']]);
        } else {
            // 即使查询失败，连接也可能成功，只是查询结果为空
            sendJsonResponse(true, '数据库连接成功！', []);
        }
    } catch (Exception $e) {
        sendJsonResponse(false, '数据库连接失败：' . $e->getMessage());
    } catch (Error $e) {
        // PHP 7+ 的错误处理
        sendJsonResponse(false, '数据库连接失败：' . $e->getMessage());
    }
    
    exit;
}

// 获取数据库配置（安装操作）
$db_host = isset($_POST['db_host']) ? trim($_POST['db_host']) : 'localhost';
$db_port = isset($_POST['db_port']) ? intval($_POST['db_port']) : 3306;
$db_name = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
$db_user = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
$db_pass = isset($_POST['db_pass']) ? trim($_POST['db_pass']) : '';

// 验证必填项
if (empty($db_host) || empty($db_name) || empty($db_user)) {
    sendJsonResponse(false, '请填写完整的数据库配置信息');
}

// 检查是否已安装
$lock_file = __DIR__ . '/install.lock';
if (file_exists($lock_file)) {
    sendJsonResponse(false, '系统已安装，请勿重复安装');
}

try {
    // 清除任何输出
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 1. 测试数据库连接
    if (!defined('IN_CRONLITE')) {
        define('IN_CRONLITE', true);
    }
    if (!defined('ROOT')) {
        define('ROOT', __DIR__ . '/include/');
    }
    
    include_once(__DIR__ . '/include/db.class.php');
    
    $DB = new DB($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    if (!$DB || !$DB->link) {
        sendJsonResponse(false, '数据库连接失败：无法建立连接');
    }
    
    // 2. 读取并执行SQL脚本
    $sql_file = __DIR__ . '/install.sql';
    if (!file_exists($sql_file)) {
        sendJsonResponse(false, '安装SQL文件不存在：' . $sql_file);
    }
    
    $sql_content = file_get_contents($sql_file);
    if ($sql_content === false) {
        sendJsonResponse(false, '无法读取SQL文件：' . $sql_file);
    }
    
    // 移除SQL注释和空行
    $sql_content = preg_replace('/--.*$/m', '', $sql_content);
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
    
    // 分割SQL语句
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    // 关闭错误显示，避免SQL警告污染JSON
    $old_error_reporting = error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
    $old_display_errors = ini_get('display_errors');
    ini_set('display_errors', 0);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && strlen($statement) > 10) {
            // 跳过SET语句（不需要执行）
            if (stripos($statement, 'SET ') === 0 && stripos($statement, 'SET SQL_MODE') !== false) {
                continue;
            }
            if (stripos($statement, 'SET time_zone') === 0) {
                continue;
            }
            if (stripos($statement, 'SET NAMES') === 0 || stripos($statement, 'SET @OLD') === 0) {
                continue;
            }
            if (stripos($statement, 'SET CHARACTER_SET') === 0) {
                continue;
            }
            
            try {
                // 清除可能的输出
                if (ob_get_level()) {
                    ob_clean();
                }
                
                $result = $DB->query($statement);
                // 检查是否有MySQL错误
                if ($result === false) {
                    // 尝试获取错误信息
                    $error_msg = '';
                    if (property_exists($DB, 'link') && $DB->link) {
                        if (function_exists('mysqli_error')) {
                            $error_msg = mysqli_error($DB->link);
                        }
                    }
                    
                    // 忽略已存在的表和重复插入错误
                    if (!empty($error_msg)) {
                        $error_code = 0;
                        if (property_exists($DB, 'link') && $DB->link && function_exists('mysqli_errno')) {
                            $error_code = mysqli_errno($DB->link);
                        }
                        
                        // MySQL错误码：1050=表已存在，1062=重复键，1060=重复列名
                        if ($error_code != 1050 && $error_code != 1062 && $error_code != 1060) {
                            if (stripos($error_msg, 'already exists') === false && 
                                stripos($error_msg, 'Duplicate entry') === false &&
                                stripos($error_msg, 'Duplicate key') === false &&
                                stripos($error_msg, 'Duplicate column') === false) {
                                // 只记录非预期的错误，但不中断安装
                                error_log('SQL执行警告: [' . $error_code . '] ' . $error_msg . ' - Statement: ' . substr($statement, 0, 100));
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
                // 忽略已存在的表和重复插入错误
                if (stripos($error_msg, 'already exists') === false && 
                    stripos($error_msg, 'Duplicate entry') === false &&
                    stripos($error_msg, 'Duplicate key') === false &&
                    stripos($error_msg, 'Duplicate column') === false) {
                    // 记录但不中断
                    error_log('SQL执行异常: ' . $error_msg . ' - Statement: ' . substr($statement, 0, 100));
                }
            }
        }
    }
    
    // 恢复错误设置
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    
    // 清除输出
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 3. 确保运行时表结构完整（图片表等）
    // 清除任何输出（可能在函数执行时产生）
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 定义必要的常量
    if (!defined('ROOT')) {
        define('ROOT', __DIR__ . '/include/');
    }
    if (!defined('IN_CRONLITE')) {
        define('IN_CRONLITE', true);
    }
    
    // 关闭错误显示，避免污染JSON
    $old_error_reporting = error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
    $old_display_errors = ini_get('display_errors');
    ini_set('display_errors', 0);
    
    require_once __DIR__ . '/include/function.php';
    
    // 确保图片表存在（这些表在install.sql中未定义，由PHP函数动态创建）
    if (function_exists('ensure_blacklist_images_table')) {
        ensure_blacklist_images_table();
    }
    
    // 确保提交图片表存在
    if (function_exists('ensure_submit_images_table')) {
        ensure_submit_images_table();
    }
    
    // 恢复错误设置
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    
    // 再次清除输出
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 4. 创建配置文件
    $config_content = "<?php\n";
    $config_content .= "\$host = '" . addslashes($db_host) . "'; //数据库地址\n";
    $config_content .= "\$port = " . intval($db_port) . "; //端口\n";
    $config_content .= "\$user = '" . addslashes($db_user) . "'; //用户名\n";
    $config_content .= "\$pwd = '" . addslashes($db_pass) . "'; //密码\n";
    $config_content .= "\$dbname = '" . addslashes($db_name) . "'; //数据库名\n";
    $config_content .= "?>\n";
    
    $config_file = __DIR__ . '/config.php';
    if (file_put_contents($config_file, $config_content) === false) {
        sendJsonResponse(false, '配置文件创建失败，请检查目录写入权限');
    }
    
    // 设置配置文件权限（仅所有者可读写）
    chmod($config_file, 0600);
    
    // 5. 创建安装锁文件
    $lock_content = date('Y-m-d H:i:s') . "\n";
    $lock_content .= "Installation completed successfully.\n";
    if (file_put_contents($lock_file, $lock_content) === false) {
        sendJsonResponse(false, '安装锁文件创建失败，请检查目录写入权限');
    }
    
    // 6. 创建上传目录（如果不存在）
    $upload_dirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/evidence'
    ];
    
    foreach ($upload_dirs as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                sendJsonResponse(false, '上传目录创建失败：' . $dir);
            }
        }
    }
    
    // 最后清除输出
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 生成安装流程token，用于步骤4的安全验证
    // Token格式：随机字符串_时间戳
    if (function_exists('random_bytes')) {
        $install_token = bin2hex(random_bytes(16)) . '_' . time();
    } else {
        // 兼容旧版本PHP，使用mt_rand
        $install_token = md5(uniqid(rand(), true)) . '_' . time();
    }
    
    // 将token存储在session中，有效期5分钟
    $_SESSION['install_flow_token'] = $install_token;
    $_SESSION['install_current_step'] = 3;
    
    // 记录token生成时间，用于后续验证
    $_SESSION['install_token_time'] = time();
    
    sendJsonResponse(true, '安装成功！', [
        'config_file' => $config_file,
        'lock_file' => $lock_file
        // 注意：不返回token到前端，仅存储在session中以提高安全性
    ]);
    
} catch (Exception $e) {
    // 清除输出并发送错误响应
    while (ob_get_level()) {
        ob_end_clean();
    }
    sendJsonResponse(false, '安装失败：' . $e->getMessage());
} catch (Error $e) {
    // PHP 7+ 的错误处理
    while (ob_get_level()) {
        ob_end_clean();
    }
    sendJsonResponse(false, '安装失败：' . $e->getMessage());
} catch (Throwable $e) {
    // 捕获所有异常
    while (ob_get_level()) {
        ob_end_clean();
    }
    sendJsonResponse(false, '安装失败：' . $e->getMessage());
}

