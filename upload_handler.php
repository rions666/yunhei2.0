<?php
// 图片上传处理脚本
// 处理AJAX上传的图片文件

// 禁用错误显示，防止输出非JSON内容
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 清空输出缓冲区，确保没有意外输出
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// 设置JSON响应头
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// 错误处理函数
function sendJsonError($message, $debug = null) {
    ob_end_clean();
    $response = ['success' => false, 'message' => $message];
    if ($debug !== null) {
        $response['debug'] = $debug;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendJsonSuccess($data) {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => '图片上传成功',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 引入数据库配置
try {
    require_once 'config.php';
} catch (Exception $e) {
    sendJsonError('配置文件加载失败: ' . $e->getMessage());
}


// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('请求方法错误，仅支持POST请求');
}

// 检查是否有文件上传（兼容 'file' 和 'image' 两种字段名）
$fileKey = null;
if (isset($_FILES['file'])) {
    $fileKey = 'file';
} elseif (isset($_FILES['image'])) {
    $fileKey = 'image';
} else {
    sendJsonError('未检测到上传文件');
}

// 检查上传错误码
$uploadError = $_FILES[$fileKey]['error'];
if ($uploadError !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
        UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
        UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '缺少临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => 'PHP扩展阻止了文件上传'
    ];
    
    $errorMessage = $errorMessages[$uploadError] ?? '文件上传失败（错误代码：' . $uploadError . '）';
    sendJsonError($errorMessage);
}

$file = $_FILES[$fileKey];

// 获取文件扩展名
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// 验证文件扩展名
if (!in_array($extension, $allowedExtensions)) {
    sendJsonError(
        '不支持的文件类型，仅支持JPG、PNG、GIF、WebP格式',
        ['extension' => $extension, 'mime_type' => $file['type'] ?? 'unknown']
    );
}

// 使用 getimagesize 验证是否为有效图片（最可靠的验证方式）
$imageInfo = @getimagesize($file['tmp_name']);
$isValidImage = $imageInfo !== false;

// 如果扩展名有效但文件不是有效图片，则拒绝
if (!$isValidImage) {
    // 记录调试信息
    $debugInfo = [
        'extension' => $extension,
        'browser_mime' => $file['type'],
        'file_size' => $file['size']
    ];
    
    // 尝试获取实际 MIME 类型
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $actualMimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $debugInfo['actual_mime'] = $actualMimeType;
        }
    }
    
    sendJsonError(
        '文件不是有效的图片格式，请确保上传的是有效的图片文件',
        $debugInfo
    );
}

// 验证文件的实际格式与扩展名是否匹配
$detectedType = null;
if (isset($imageInfo[2])) {
    $imageType = $imageInfo[2];
    $typeMap = [
        IMAGETYPE_JPEG => ['jpg', 'jpeg'],
        IMAGETYPE_PNG => ['png'],
        IMAGETYPE_GIF => ['gif'],
        IMAGETYPE_WEBP => ['webp']
    ];
    
    if (isset($typeMap[$imageType])) {
        $detectedType = $typeMap[$imageType];
        // 检查扩展名是否与检测到的类型匹配
        if (!in_array($extension, $detectedType)) {
            // 扩展名与文件类型不匹配，但仍然允许上传（记录警告）
            error_log("Image extension mismatch: extension={$extension}, actual_type=" . implode('/', $detectedType) . ", file={$file['name']}");
        }
    }
}

// 验证文件大小（最大5MB）
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    sendJsonError('文件大小超过限制，最大支持5MB');
}

// 生成唯一文件名
// 格式：YYYYMMDDHHMMSS_microsecond_uniqid_random.扩展名
// 确保文件名唯一性：时间戳 + 微秒 + 唯一ID + 随机数
$timestamp = date('YmdHis');
$microtime = substr(str_replace('.', '', microtime(true)), -6); // 微秒部分（6位）
$uniqueId = str_replace('.', '_', uniqid('', true)); // 唯一ID，点号替换为下划线
$randomStr = bin2hex(random_bytes(4)); // 8位随机十六进制字符串
$fileName = $timestamp . '_' . $microtime . '_' . $uniqueId . '_' . $randomStr . '.' . $extension;

// 按日期创建目录结构：uploads/evidence/YYYY/MM/
$year = date('Y');
$month = date('m');
$baseDir = 'uploads/evidence/';
$uploadDir = $baseDir . $year . '/' . $month . '/';
$filePath = $uploadDir . $fileName;

// 确保上传目录存在（递归创建年/月目录）
$yearDir = $baseDir . $year;
$created = false;

if (!is_dir($uploadDir)) {
    // 创建基础目录（如果不存在）
    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0755, true)) {
            sendJsonError('无法创建基础上传目录，请检查父目录权限');
        }
        $created = true;
    }
    
    // 创建年目录
    if (!is_dir($yearDir)) {
        if (!mkdir($yearDir, 0755, true)) {
            sendJsonError('无法创建年份目录，请检查目录权限');
        }
        $created = true;
    }
    
    // 创建月目录
    if (!mkdir($uploadDir, 0755, true)) {
        sendJsonError('无法创建月份目录，请检查目录权限');
    }
    $created = true;
}

// 尝试修复目录权限和所有者（无论目录是新创建还是已存在）
$webUser = 'www';
if (function_exists('posix_getpwuid')) {
    $webUserInfo = @posix_getpwnam($webUser);
    $currentUid = function_exists('posix_geteuid') ? posix_geteuid() : null;
    $isRoot = ($currentUid === 0);
    
    if ($webUserInfo) {
        // 尝试修复权限和所有者
        $dirsToFix = [
            $baseDir => '基础目录',
            $yearDir => '年份目录',
            $uploadDir => '月份目录'
        ];
        
        foreach ($dirsToFix as $dir => $dirName) {
            if (is_dir($dir)) {
                // 尝试修复权限
                @chmod($dir, 0755);
                
                // 尝试修复所有者（如果PHP进程有权限）
                $dirOwner = fileowner($dir);
                if ($isRoot || ($created && $dirOwner != 0)) {
                    // 如果是root用户运行，或者目录是新创建的且不是root所有，可以修改所有者
                    @chown($dir, $webUser);
                    @chgrp($dir, $webUserInfo['gid'] ?? $webUser);
                } elseif ($dirOwner == 0 && !$isRoot) {
                    // 目录是root所有但PHP不是root运行，无法修改，记录日志
                    error_log("Warning: Directory {$dir} is owned by root, cannot change owner to {$webUser}");
                }
            }
        }
    }
} else {
    // 如果没有 posix 函数，只尝试修复权限
    @chmod($uploadDir, 0755);
    @chmod($yearDir, 0755);
    @chmod($baseDir, 0755);
}

// 确保目录可写
if (!is_writable($uploadDir)) {
    $realPath = realpath($uploadDir);
    $owner = 'unknown';
    $ownerUid = null;
    if (function_exists('posix_getpwuid') && $realPath) {
        $ownerUid = fileowner($realPath);
        $ownerInfo = posix_getpwuid($ownerUid);
        $owner = $ownerInfo ? $ownerInfo['name'] : 'unknown';
    }
    
    // 如果所有者不是 www 用户，尝试使用系统命令修复（需要权限）
    if ($ownerUid !== null && $ownerUid != 0) {
        // 尝试通过系统命令修复（如果PHP有权限）
        $uploadDirFull = realpath($uploadDir) ?: __DIR__ . '/' . $uploadDir;
        $fixCmd = "chown -R {$webUser}:{$webUser} " . escapeshellarg($uploadDirFull) . " 2>&1";
        @exec($fixCmd, $output, $returnVar);
    }
    
    // 再次检查
    if (!is_writable($uploadDir)) {
        sendJsonError('上传目录不可写，请检查目录权限。目录路径：' . ($realPath ?: $uploadDir) . '，所有者：' . $owner . '。请运行: chown -R www:www ' . ($realPath ?: $uploadDir) . ' && chmod -R 755 ' . ($realPath ?: $uploadDir));
    }
}

// 移动上传的文件
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    // 检查磁盘空间
    $freeSpace = disk_free_space($uploadDir);
    if ($freeSpace !== false && $freeSpace < $file['size']) {
        sendJsonError('磁盘空间不足');
    }
    
    // 检查临时文件是否还存在
    if (!file_exists($file['tmp_name'])) {
        sendJsonError('临时文件已失效，请重新上传');
    }
    
    sendJsonError('文件保存失败，请检查服务器配置和目录权限');
}

// 验证文件是否成功保存
if (!file_exists($filePath)) {
    sendJsonError('文件保存后验证失败，文件不存在');
}

// 验证文件大小
$savedFileSize = filesize($filePath);
if ($savedFileSize !== $file['size']) {
    // 文件大小不匹配，删除已保存的文件
    @unlink($filePath);
    sendJsonError('文件保存不完整，已删除');
}

// 获取实际 MIME 类型（用于数据库存储）
$finalMimeType = $file['type'];
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detectedMimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        if ($detectedMimeType) {
            $finalMimeType = $detectedMimeType;
        }
    }
}

// 返回成功信息
$responseData = [
    'original_name' => $file['name'],
    'file_name' => $fileName,
    'file_path' => $filePath,
    'file_size' => $savedFileSize,
    'mime_type' => $finalMimeType,
    'preview_url' => $filePath
];

sendJsonSuccess($responseData);
?>