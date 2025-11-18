<?php
/**
 * 实时预览查询接口
 * 用于AJAX请求，返回查询结果的JSON数据
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允许POST请求']);
    exit;
}

// 引入必要的文件
require_once __DIR__ . '/../include/common.php';
require_once __DIR__ . '/../include/function.php';

try {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $subject_type = isset($input['subject_type']) ? trim($input['subject_type']) : '';
    $subject = isset($input['subject']) ? trim($input['subject']) : '';
    
    // 验证输入参数
    if (empty($subject_type) || empty($subject)) {
        echo json_encode([
            'success' => false,
            'error' => '请选择主体类型并输入查询内容'
        ]);
        exit;
    }
    
    // 清理输入
    $subject = sanitize_subject($subject);
    
    if ($subject === '') {
        echo json_encode([
            'success' => false,
            'error' => '输入内容无效'
        ]);
        exit;
    }
    
    // 查询数据库
    $row = $DB->get_row_prepared(
        "SELECT * FROM black_list WHERE subject = ? AND subject_type = ? LIMIT 1", 
        [$subject, $subject_type]
    );
    
    // 构建响应数据
    $response = [
        'success' => true,
        'subject' => $subject,
        'subject_type' => $subject_type,
        'found' => !empty($row)
    ];
    
    // 如果找到记录，添加详细信息
    if ($row) {
        // 查询关联的图片
        $images = [];
        ensure_blacklist_images_table();
        
        $images = $DB->get_all_prepared(
            "SELECT * FROM black_list_images WHERE blacklist_id = ? AND is_deleted = 0 ORDER BY upload_time DESC",
            [$row['id']]
        );
        
        // 验证图片路径
        foreach ($images as &$img) {
            $img['file_path'] = validate_image_file_path($img['file_path'], $img['file_name'], __DIR__ . '/..');
            $img['full_path'] = __DIR__ . '/../' . $img['file_path'];
            $img['exists'] = file_exists($img['full_path']);
        }
        unset($img);
        
        $response['data'] = [
            'level' => $row['level'],
            'date' => $row['date'],
            'note' => $row['note'],
            'images' => $images
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '服务器内部错误: ' . $e->getMessage()
    ]);
}
?>