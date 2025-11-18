<?php

$mod = 'blank';
include(__DIR__.'/../include/common.php');

if($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

require_once __DIR__.'/../include/function.php';
ensure_multi_subject_schema();
$active_subject_types = get_active_subject_types();

if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}

$action = isset($_GET['my']) ? $_GET['my'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : './list.php';

if ($action == 'del') {
    if ($id <= 0) {
        $_SESSION['list_error'] = '无效的记录ID！';
        header('Location: ./list.php');
        exit;
    }

    // 先查询关联的图片
    $images = $DB->get_all_prepared(
        "SELECT * FROM black_list_images WHERE blacklist_id = ? AND is_deleted = 0",
        [$id]
    );

    // 删除物理文件
    if($images && is_array($images)) {
        foreach($images as $img) {
            $file_path = __DIR__ . '/../' . $img['file_path'];
            if(file_exists($file_path)) {
                @unlink($file_path);
            }
        }
    }

    // 删除图片记录
    $DB->execute_prepared("DELETE FROM black_list_images WHERE blacklist_id = ?", [$id]);

    // 删除黑名单记录
    $ok = $DB->execute_prepared("DELETE FROM black_list WHERE id = ? LIMIT 1", [$id]);
    if($ok){
        $_SESSION['list_success'] = '删除成功！';
    } else {
        $_SESSION['list_error'] = '删除失败！';
    }
    header("Location: $back_url");
    exit;
}

if ($action == 'update') {
    if ($id <= 0) {
        $_SESSION['list_error'] = '无效的记录ID！';
        header('Location: ./list.php');
        exit;
    }

    $row = $DB->get_row_prepared("SELECT * FROM black_list WHERE id = ? LIMIT 1", [$id]);
    if(empty($row)){
        $_SESSION['list_error'] = '记录不存在！';
        header('Location: ./list.php');
        exit;
    }

    if(isset($_POST['submit'])) {
        $subject = sanitize_subject($_POST['subject'] ?? '');
        $subject_type = isset($_POST['subject_type']) ? trim($_POST['subject_type']) : ($row['subject_type'] ?? 'other');
        $level = intval($_POST['level'] ?? 1);
        $note = trim((string)($_POST['note'] ?? ''));

        if(empty($subject)) {
            $error_message = '黑名单内容不能为空！';
        } elseif($level < 1 || $level > 3) {
            $error_message = '无效的黑名单等级！';
        } else {
            
            if(!empty($subject_type) && function_exists('validate_subject_by_type')) {
                if(!validate_subject_by_type($subject, $subject_type)) {
                    $error_message = '输入格式不符合' . get_subject_type_name($subject_type) . '的要求';
                }
            }

            if(empty($error_message)) {
                
                $exists = $DB->get_row_prepared(
                    "SELECT id FROM black_list WHERE subject = ? AND subject_type = ? AND id != ? LIMIT 1",
                    [$subject, $subject_type, $id]
                );

                if($exists) {
                    $error_message = '该黑名单已存在其他记录中！';
                } else {
                    
                    $ok = $DB->execute_prepared(
                        "UPDATE `black_list` SET `subject` = ?, `subject_type` = ?, `level` = ?, `note` = ? WHERE `id` = ?",
                        [$subject, $subject_type, $level, $note, $id]
                    );
                    
                    if($ok){
                        
                        if(!empty($_POST['deleted_images'])) {
                            $deleted_ids = json_decode($_POST['deleted_images'], true);
                            if(is_array($deleted_ids) && count($deleted_ids) > 0) {
                                $placeholders = implode(',', array_fill(0, count($deleted_ids), '?'));
                                $DB->execute_prepared(
                                    "UPDATE `black_list_images` SET `is_deleted` = 1 WHERE `id` IN ($placeholders) AND `blacklist_id` = ?",
                                    array_merge($deleted_ids, [$id])
                                );
                            }
                        }

                        if(!empty($_POST['uploaded_images'])) {
                            $images_data = json_decode($_POST['uploaded_images'], true);
                            if(is_array($images_data) && count($images_data) > 0) {
                                $image_count = 0;
                                foreach($images_data as $image_info) {
                                    
                                    $file_name = $image_info['file_name'] ?? $image_info['fileName'] ?? '';
                                    $original_name = $image_info['original_name'] ?? $image_info['originalName'] ?? '';
                                    $file_path = $image_info['file_path'] ?? $image_info['filePath'] ?? '';
                                    $file_size = $image_info['file_size'] ?? $image_info['fileSize'] ?? 0;
                                    $mime_type = $image_info['mime_type'] ?? $image_info['mimeType'] ?? '';
                                    
                                    if(empty($file_name) || empty($original_name)) {
                                        continue;
                                    }

                                    if(!function_exists('validate_image_file_path')) {
                                        require_once __DIR__ . '/../include/function.php';
                                    }
                                    $file_path = validate_image_file_path($file_path, $file_name, __DIR__ . '/..');
                                    $full_path = __DIR__ . '/../' . $file_path;

                                    if(!file_exists($full_path)) {
                                        error_log("Image file not found: " . $full_path);
                                        continue;
                                    }

                                    $image_result = $DB->execute_prepared(
                                        "INSERT INTO `black_list_images` (`blacklist_id`, `original_name`, `file_name`, `file_path`, `file_size`, `mime_type`, `upload_time`) VALUES (?, ?, ?, ?, ?, ?, ?)",
                                        [$id, $original_name, $file_name, $file_path, $file_size, $mime_type, date('Y-m-d H:i:s')]
                                    );
                                    
                                    if($image_result) {
                                        $image_count++;
                                    } else {
                                        error_log("Failed to insert image record for blacklist ID {$id}: {$file_name}");
                                    }
                                }
                            }
                        }
                        
                        $_SESSION['edit_success'] = '修改成功！';
                        header("Location: ./edit.php?my=update&id=$id");
                        exit;
                    } else {
                        $error_message = '修改失败！' . ($DB->error() ?? '');
                    }
                }
            }
        }
    } else {
        
        $row = $DB->get_row_prepared("SELECT * FROM black_list WHERE id = ? LIMIT 1", [$id]);
    }
}

$title = '编辑黑名单';
include './layout/header.php';

ensure_blacklist_images_table();

$error_message = '';
$success_message = '';

if(isset($_SESSION['edit_success'])){
    $success_message = $_SESSION['edit_success'];
    unset($_SESSION['edit_success']);
}

if(isset($_SESSION['edit_error'])){
    $error_message = $_SESSION['edit_error'];
    unset($_SESSION['edit_error']);
}

$existing_images = [];
if ($action == 'update' && isset($row) && $id > 0) {
    $existing_images = $DB->get_all_prepared(
        "SELECT * FROM black_list_images WHERE blacklist_id = ? AND is_deleted = 0 ORDER BY upload_time DESC",
        [$id]
    );
}
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

    .form-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234a5568' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.75rem center;
        background-repeat: no-repeat;
        background-size: 1.25rem;
        padding-right: 2.5rem;
        cursor: pointer;
    }

    .form-textarea {
        min-height: 120px;
        resize: vertical;
        font-family: inherit;
    }

    .level-group {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-top: 0.5rem;
    }

    .level-radio {
        position: relative;
    }

    .level-radio input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
    }

    .level-card {
        padding: 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: #fff;
    }

    .level-radio input:checked + .level-card {
        border-color: #3b82f6;
        background: #eff6ff;
        color: #3b82f6;
    }

    .level-radio input:checked + .level-card.level-1 {
        border-color: #10b981;
        background: #ecfdf5;
        color: #10b981;
    }

    .level-radio input:checked + .level-card.level-3 {
        border-color: #ef4444;
        background: #fef2f2;
        color: #ef4444;
    }

    .level-card:hover {
        border-color: #9ca3af;
    }

    .level-number {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .level-name {
        font-size: 0.875rem;
        font-weight: 500;
    }

    .upload-zone {
        border: 2px dashed #d1d5db;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: #f9fafb;
    }

    .upload-zone:hover {
        border-color: #3b82f6;
        background: #f0f9ff;
    }

    .upload-zone.dragover {
        border-color: #3b82f6;
        background: #eff6ff;
    }

    .upload-icon {
        font-size: 2.5rem;
        color: #9ca3af;
        margin-bottom: 0.75rem;
    }

    .upload-text {
        font-size: 0.9375rem;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }

    .upload-hint {
        font-size: 0.8125rem;
        color: #9ca3af;
    }

    .preview-container {
        margin-top: 1rem;
        display: none;
    }

    .preview-container.active {
        display: block;
    }

    .preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-top: 0.75rem;
    }

    .preview-item {
        position: relative;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }

    .preview-item.existing-image.deleted {
        opacity: 0.5;
        border-color: #ef4444;
    }

    .preview-item img {
        width: 100%;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    .preview-remove {
        position: absolute;
        top: 0.25rem;
        right: 0.25rem;
        background: rgba(239, 68, 68, 0.9);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        transition: all 0.2s;
    }

    .preview-remove:hover {
        background: #ef4444;
        transform: scale(1.1);
    }

    .preview-info {
        padding: 0.5rem;
        font-size: 0.75rem;
        color: #6b7280;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
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

    .btn-outline {
        background: #fff;
        color: #6b7280;
        border: 1px solid #d1d5db;
    }

    .btn-outline:hover {
        background: #f9fafb;
        border-color: #9ca3af;
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

    .alert-success {
        background: #ecfdf5;
        border: 1px solid #bbf7d0;
        color: #16a34a;
    }

    .file-input {
        display: none;
    }

    .help-text {
        font-size: 0.8125rem;
        color: #6b7280;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .existing-images-section {
        margin-bottom: 1.5rem;
        padding: 1rem;
        background: #f9fafb;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .existing-images-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        font-weight: 500;
        color: #374151;
    }

    @media (max-width: 768px) {
        .add-container {
            padding: 1rem;
        }

        .form-section {
            padding: 1.5rem;
        }

        .level-group {
            grid-template-columns: 1fr;
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

    <?php if ($action == 'update' && isset($row)): ?>
        <form method="post" id="editForm" action="./edit.php?my=update&id=<?php echo $id; ?>">
            <input type="hidden" name="backurl" value="<?php echo htmlspecialchars($back_url); ?>"/>

            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    基本信息
                </h2>

                <div class="form-group">
                    <label for="subject_type" class="form-label">
                        黑名单类型 <span class="required">*</span>
                    </label>
                    <select id="subject_type" name="subject_type" class="form-control form-select" required>
                        <option value="">请选择黑名单类型</option>
                        <?php foreach($active_subject_types as $type):
                            $selected = (($row['subject_type'] ?? 'other') == $type['type_key']) ? 'selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($type['type_key']); ?>"
                                data-placeholder="<?php echo htmlspecialchars($type['placeholder'] ?: '请输入' . $type['type_name']); ?>"
                                <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($type['type_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i>
                        选择黑名单的类型（QQ号、手机号、邮箱等）
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject" class="form-label">
                        黑名单内容 <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="subject"
                           name="subject"
                           class="form-control"
                           placeholder="请输入黑名单内容"
                           value="<?php echo htmlspecialchars($row['subject'] ?? ''); ?>"
                           maxlength="100"
                           required>
                    <div class="help-text" id="subject-help">
                        <i class="fas fa-info-circle"></i>
                        根据选择的黑名单类型输入相应的内容
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        风险等级 <span class="required">*</span>
                    </label>
                    <div class="level-group">
                        <label class="level-radio">
                            <input type="radio" name="level" value="1" id="level1" <?php echo ($row['level'] ?? 1) == 1 ? 'checked' : ''; ?> required>
                            <div class="level-card level-1">
                                <div class="level-number">1</div>
                                <div class="level-name">低风险</div>
                            </div>
                        </label>
                        <label class="level-radio">
                            <input type="radio" name="level" value="2" id="level2" <?php echo ($row['level'] ?? 1) == 2 ? 'checked' : ''; ?>>
                            <div class="level-card level-2">
                                <div class="level-number">2</div>
                                <div class="level-name">中风险</div>
                            </div>
                        </label>
                        <label class="level-radio">
                            <input type="radio" name="level" value="3" id="level3" <?php echo ($row['level'] ?? 1) == 3 ? 'checked' : ''; ?>>
                            <div class="level-card level-3">
                                <div class="level-number">3</div>
                                <div class="level-name">高风险</div>
                            </div>
                        </label>
                    </div>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i>
                        根据风险程度选择相应等级
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    详细信息
                </h2>

                <div class="form-group">
                    <label for="note" class="form-label">
                        拉黑原因
                    </label>
                    <textarea id="note" 
                              name="note" 
                              class="form-control form-textarea" 
                              placeholder="请详细描述拉黑原因（可选）"
                              maxlength="2000"><?php echo htmlspecialchars($row['note'] ?? ''); ?></textarea>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i>
                        简要说明将该主体加入黑名单的原因
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-images"></i>
                    证据图片
                </h2>

                <?php if(!empty($existing_images) && count($existing_images) > 0): ?>
                <div class="existing-images-section">
                    <div class="existing-images-header">
                        <span>已有图片 (<?php echo count($existing_images); ?>)</span>
                    </div>
                    <div class="preview-grid" id="existingImagesGrid">
                        <?php foreach($existing_images as $img): 
                            
                            if(!function_exists('validate_image_file_path')) {
                                require_once __DIR__ . '/../include/function.php';
                            }
                            $valid_path = validate_image_file_path($img['file_path'], $img['file_name'], __DIR__ . '/..');
                            $image_path = '../' . $valid_path;
                            $image_exists = file_exists(__DIR__ . '/../' . $valid_path);
                        ?>
                        <div class="preview-item existing-image" data-image-id="<?php echo htmlspecialchars($img['id']); ?>">
                            <?php if($image_exists): ?>
                            <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($img['original_name']); ?>">
                            <?php else: ?>
                            <div style="width: 100%; height: 120px; display: flex; align-items: center; justify-content: center; background: #f0f0f0; color: #999;">
                                <i class="fas fa-image"></i> 文件不存在
                            </div>
                            <?php endif; ?>
                            <div class="preview-info" title="<?php echo htmlspecialchars($img['original_name']); ?>">
                                <?php echo htmlspecialchars($img['original_name']); ?> (<?php echo formatFileSize($img['file_size']); ?>)
                            </div>
                            <button type="button" class="preview-remove" onclick="removeExistingImage(this, <?php echo htmlspecialchars($img['id']); ?>)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="upload-zone" onclick="document.getElementById('imageUpload').click()">
                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                    <div class="upload-text">点击上传新图片或拖拽图片到此处</div>
                    <div class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，单个文件最大 5MB</div>
                </div>
                <input type="file" id="imageUpload" class="file-input" accept="image/*" multiple>
                <input type="hidden" id="uploadedImages" name="uploaded_images" value="">
                <input type="hidden" id="deletedImages" name="deleted_images" value="">

                <div class="preview-container" id="newImagesPreview">
                    <div class="preview-grid" id="newImagesGrid"></div>
                </div>

                <div class="help-text">
                    <i class="fas fa-info-circle"></i>
                    上传相关证据图片有助于提高记录的可信度，支持多张图片上传
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    保存修改
                </button>
                <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    取消
                </a>
            </div>
        </form>
    <?php else: ?>
        
        <div class="form-section">
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span>无效的操作或记录不存在</span>
            </div>
            <div class="form-actions">
                <a href="./list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    返回列表
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="../assets/js/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    
    $('#subject_type').change(function() {
        var selectedOption = $(this).find('option:selected');
        var placeholder = selectedOption.data('placeholder') || '请输入黑名单内容';
        $('#subject').attr('placeholder', placeholder);

        if (selectedOption.val()) {
            $('#subject-help').html('<i class="fas fa-info-circle"></i> 请输入' + selectedOption.text() + '，格式：' + placeholder);
        } else {
            $('#subject-help').html('<i class="fas fa-info-circle"></i> 根据选择的黑名单类型输入相应的内容');
        }
    });

    $('#subject_type').trigger('change');

    let uploadedFiles = [];
    let deletedImageIds = [];
    const imageUpload = document.getElementById('imageUpload');
    const newImagesPreview = document.getElementById('newImagesPreview');
    const newImagesGrid = document.getElementById('newImagesGrid');
    const uploadZone = document.querySelector('.upload-zone');

    if (imageUpload) {
        imageUpload.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    uploadFile(file);
                }
            });
        });
    }

    if (uploadZone) {
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = Array.from(e.dataTransfer.files);
            files.forEach(file => {
                if (file.type.startsWith('image/')) {
                    uploadFile(file);
                }
            });
        });
    }

    function uploadFile(file) {
        if (file.size > 5 * 1024 * 1024) {
            alert('文件大小超过5MB限制！');
            return;
        }
        
        const previewItem = createPreviewItem(file);
        newImagesGrid.appendChild(previewItem);
        newImagesPreview.classList.add('active');
        
        const formData = new FormData();
        formData.append('image', file);
        
        fetch('../upload_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP错误: ' + response.status);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON解析失败:', text);
                    throw new Error('服务器响应格式错误');
                }
            });
        })
        .then(data => {
            if (data.success) {
                previewItem.classList.remove('uploading');
                previewItem.classList.add('upload-success');
                uploadedFiles.push({file: file, serverData: data.data});
                updateUploadedImagesField();
            } else {
                previewItem.classList.remove('uploading');
                previewItem.classList.add('upload-error');
                alert('上传失败: ' + (data.message || '未知错误'));
            }
        })
        .catch(error => {
            console.error('上传错误:', error);
            previewItem.classList.remove('uploading');
            previewItem.classList.add('upload-error');
            alert('上传失败: ' + error.message);
        });
    }

    function createPreviewItem(file) {
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item uploading';
        
        const img = document.createElement('img');
        img.alt = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        const previewInfo = document.createElement('div');
        previewInfo.className = 'preview-info';
        previewInfo.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'preview-remove';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.onclick = function() {
            removeNewPreviewItem(previewItem, file);
        };
        
        previewItem.appendChild(img);
        previewItem.appendChild(previewInfo);
        previewItem.appendChild(removeBtn);
        
        return previewItem;
    }

    function removeNewPreviewItem(previewItem, file) {
        uploadedFiles = uploadedFiles.filter(item => item.file !== file);
        previewItem.remove();
        updateUploadedImagesField();
        if (uploadedFiles.length === 0) {
            newImagesPreview.classList.remove('active');
        }
    }

    function updateUploadedImagesField() {
        const successfulUploads = uploadedFiles.filter(item => item.serverData);
        const imageData = successfulUploads.map(item => item.serverData);
        const uploadedImagesField = document.getElementById('uploadedImages');
        if (uploadedImagesField) {
            uploadedImagesField.value = JSON.stringify(imageData);
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    window.removeExistingImage = function(button, imageId) {
        if (confirm('确定要删除这张图片吗？')) {
            
            deletedImageIds.push(imageId);
            const deletedImagesField = document.getElementById('deletedImages');
            if (deletedImagesField) {
                deletedImagesField.value = JSON.stringify(deletedImageIds);
            }

            const previewItem = button.closest('.preview-item');
            if (previewItem) {
                previewItem.classList.add('deleted');
                button.style.display = 'none';
            }
        }
    };
});

(function() {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.transition = 'opacity 0.3s ease-out';
            successAlert.style.opacity = '0';
            setTimeout(function() {
                successAlert.remove();
            }, 300);
        }, 3000);
    }
})();
</script>

<?php include './layout/footer.php'; ?>

