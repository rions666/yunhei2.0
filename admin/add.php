<?php

$mod = 'blank';
include("../include/common.php");

if ($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

$error_message = '';
$success_message = '';
$form_data = [];

require_once __DIR__.'/../include/function.php';
ensure_multi_subject_schema();
$subject_types = get_active_subject_types();

$csrf_token = md5(uniqid(rand(), true));
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = $csrf_token;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    error_log("=== POST Data ===");
    error_log("uploaded_images field exists: " . (isset($_POST['uploaded_images']) ? 'YES' : 'NO'));
    if(isset($_POST['uploaded_images'])) {
        error_log("uploaded_images value: " . $_POST['uploaded_images']);
        error_log("uploaded_images length: " . strlen($_POST['uploaded_images']));
    }

    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        $error_message = '安全验证失败，请重新提交！';
    } else {
        $form_data = $_POST;
        
        $subject_type = trim($_POST['subject_type'] ?? '');
        $subject = sanitize_subject($_POST['subject'] ?? '');
        $level = intval($_POST['level'] ?? 1);
        $note = trim((string)($_POST['note'] ?? ''));

        if(empty($subject) || empty($subject_type)) {
            $error_message = '请选择黑名单类型并输入黑名单内容！';
        } elseif($level < 1 || $level > 3) {
            $error_message = '请选择有效的黑名单等级！';
        } else {
            
            if(!validate_subject_by_type($subject, $subject_type)) {
                $error_message = '输入格式不符合' . get_subject_type_name($subject_type) . '的要求';
            }

            if(empty($error_message)) {
                
                $exists = $DB->get_row_prepared(
                    "SELECT id FROM black_list WHERE subject = ? AND (subject_type = ? OR subject_type IS NULL) LIMIT 1",
                    [$subject, $subject_type]
                );

                if($exists) {
                    $error_message = '该黑名单已存在！';
                } else {
                    
                    $date = date('Y-m-d H:i:s');
                    $ok = $DB->execute_prepared(
                        "INSERT INTO `black_list` (`subject`,`subject_type`,`date`,`level`,`note`) VALUES (?,?,?,?,?)",
                        [$subject, $subject_type, $date, $level, $note]
                    );

                    if($ok) {
                        
                        $blacklist_id = mysqli_insert_id($DB->link);

                        if(!empty($_POST['uploaded_images'])) {
                            $images_data = json_decode($_POST['uploaded_images'], true);

                            error_log("Blacklist ID: " . $blacklist_id);
                            error_log("Uploaded images JSON: " . $_POST['uploaded_images']);
                            error_log("Decoded images count: " . (is_array($images_data) ? count($images_data) : 0));

                            if(is_array($images_data) && count($images_data) > 0) {
                                ensure_blacklist_images_table();

                                $image_count = 0;
                                foreach($images_data as $image_info) {
                                    $file_name = $image_info['file_name'] ?? $image_info['fileName'] ?? '';
                                    $original_name = $image_info['original_name'] ?? $image_info['originalName'] ?? '';
                                    $file_path = $image_info['file_path'] ?? $image_info['filePath'] ?? '';
                                    $file_size = $image_info['file_size'] ?? $image_info['fileSize'] ?? 0;
                                    $mime_type = $image_info['mime_type'] ?? $image_info['mimeType'] ?? '';
                                    
                                    if(empty($file_name) || empty($original_name)) {
                                        error_log("Skipping image: empty file_name or original_name");
                                        continue;
                                    }

                                    if(!function_exists('validate_image_file_path')) {
                                        require_once __DIR__ . '/../include/function.php';
                                    }
                                    $file_path = validate_image_file_path($file_path, $file_name, __DIR__ . '/..');
                                    $full_path = __DIR__ . '/../' . $file_path;

                                    error_log("Processing image: file_name={$file_name}, file_path={$file_path}, full_path={$full_path}");

                                    if(!file_exists($full_path)) {
                                        error_log("Image file not found: " . $full_path);
                                        continue;
                                    }

                                    $image_result = $DB->execute_prepared(
                                        "INSERT INTO `black_list_images` (`blacklist_id`, `original_name`, `file_name`, `file_path`, `file_size`, `mime_type`, `upload_time`) VALUES (?, ?, ?, ?, ?, ?, ?)",
                                        [$blacklist_id, $original_name, $file_name, $file_path, $file_size, $mime_type, date('Y-m-d H:i:s')]
                                    );

                                    if($image_result) {
                                        $image_count++;
                                        error_log("Image inserted successfully: {$file_name}");
                                    } else {
                                        error_log("Failed to insert image: {$file_name}, Error: " . $DB->error());
                                    }
                                }
                            }
                        }

                        $_SESSION['csrf_token'] = md5(uniqid(rand(), true));

                        $_SESSION['success_message'] = '黑名单添加成功！';

                        header('Location: ./list.php');
                        exit;
                    } else {
                        $error_message = '添加失败，请重试！';
                    }
                }
            }
        }
    }
}

$title = '添加黑名单';
include './layout/header.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
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
</head>
<body>
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

        <form method="post" id="addForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

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
                        <?php foreach($subject_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['type_key']); ?>"
                                data-placeholder="<?php echo htmlspecialchars($type['placeholder'] ?: '请输入' . $type['type_name']); ?>"
                                <?php echo ($form_data['subject_type'] ?? '') == $type['type_key'] ? 'selected' : ''; ?>>
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
                           placeholder="请先选择黑名单类型"
                           value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>"
                           maxlength="100"
                           required
                           disabled>
                    <div class="help-text" id="subject-help">
                        <i class="fas fa-info-circle"></i>
                        请先选择黑名单类型，然后输入对应的黑名单内容
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        风险等级 <span class="required">*</span>
                    </label>
                    <div class="level-group">
                        <label class="level-radio">
                            <input type="radio" name="level" value="1" id="level1" <?php echo ($form_data['level'] ?? 1) == 1 ? 'checked' : ''; ?> required>
                            <div class="level-card level-1">
                                <div class="level-number">1</div>
                                <div class="level-name">低风险</div>
                            </div>
                        </label>
                        <label class="level-radio">
                            <input type="radio" name="level" value="2" id="level2" <?php echo ($form_data['level'] ?? 1) == 2 ? 'checked' : ''; ?>>
                            <div class="level-card level-2">
                                <div class="level-number">2</div>
                                <div class="level-name">中风险</div>
                            </div>
                        </label>
                        <label class="level-radio">
                            <input type="radio" name="level" value="3" id="level3" <?php echo ($form_data['level'] ?? 1) == 3 ? 'checked' : ''; ?>>
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
                        拉黑原因 <span class="required">*</span>
                    </label>
                    <textarea id="note" 
                              name="note" 
                              class="form-control form-textarea" 
                              placeholder="请详细描述拉黑原因，有助于其他用户了解风险"
                              maxlength="2000" 
                              required><?php echo htmlspecialchars($form_data['note'] ?? ''); ?></textarea>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i>
                        详细的拉黑原因有助于其他用户了解风险（最多2000字符）
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-images"></i>
                    证据材料 <span style="font-weight: normal; color: #6b7280;">(可选)</span>
                </h2>

                <div class="form-group">
                    <div class="upload-zone" id="uploadZone">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <p class="upload-text">点击上传或拖拽图片到此处</p>
                        <p class="upload-hint">支持 JPG、PNG、GIF、WebP 格式，单个文件最大 5MB</p>
                    </div>
                    <input type="file" id="imageUpload" class="file-input" accept="image/*" multiple>
                    <input type="hidden" id="uploadedImages" name="uploaded_images" value="">
                    
                    <div class="preview-container" id="previewContainer">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <span style="font-weight: 500; color: #374151;">已上传的图片</span>
                            <button type="button" onclick="clearAllImages()" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.875rem;">
                                <i class="fas fa-trash"></i> 清空
                            </button>
                        </div>
                        <div class="preview-grid" id="previewGrid"></div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    提交
                </button>
                <a href="./list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    返回
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i>
                    重置
                </button>
            </div>
        </form>
    </div>

    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script>
        (function() {
            let uploadedImages = [];

            $('#subject_type').on('change', function() {
                const selected = $(this).find('option:selected');
                const placeholder = selected.data('placeholder') || '请输入黑名单内容';
                const subjectInput = $('#subject');
                const subjectHelp = $('#subject-help');

                if (selected.val()) {
                    subjectInput.prop('disabled', false);
                    subjectInput.attr('placeholder', placeholder);
                    subjectHelp.html('<i class="fas fa-info-circle"></i> 请输入' + selected.text());
                } else {
                    subjectInput.prop('disabled', true);
                    subjectInput.attr('placeholder', '请先选择黑名单类型');
                    subjectInput.val('');
                    subjectHelp.html('<i class="fas fa-info-circle"></i> 请先选择黑名单类型，然后输入对应的黑名单内容');
                }
            }).trigger('change');

            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('imageUpload');

            uploadZone.addEventListener('click', () => fileInput.click());
            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });
            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('dragover');
            });
            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });

            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });

            function handleFiles(files) {
                Array.from(files).forEach(file => {
                    if (!file.type.startsWith('image/')) {
                        alert('请上传图片文件！');
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        alert('文件大小不能超过5MB！');
                        return;
                    }
                    uploadFile(file);
                });
            }

            function uploadFile(file) {
                const formData = new FormData();
                formData.append('file', file);

                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${URL.createObjectURL(file)}" alt="预览">
                    <div class="preview-info">${file.name}</div>
                    <button type="button" class="preview-remove" onclick="removeImage(this)"><i class="fas fa-times"></i></button>
                `;
                
                const previewGrid = document.getElementById('previewGrid');
                previewGrid.appendChild(previewItem);
                document.getElementById('previewContainer').classList.add('active');

                fetch('../upload_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('服务器响应格式错误');
                    }
                }))
                .then(data => {
                    console.log('=== 上传响应 ===');
                    console.log('Response data:', data);
                    console.log('Success:', data.success);
                    console.log('Data:', data.data);

                    if (data.success) {
                        uploadedImages.push(data.data);
                        console.log('Image added to array. Total images:', uploadedImages.length);
                        updateHiddenInput();
                    } else {
                        alert('上传失败: ' + data.message);
                        previewItem.remove();
                        if (previewGrid.children.length === 0) {
                            document.getElementById('previewContainer').classList.remove('active');
                        }
                    }
                })
                .catch(error => {
                    alert('上传失败: ' + error.message);
                    previewItem.remove();
                    if (previewGrid.children.length === 0) {
                        document.getElementById('previewContainer').classList.remove('active');
                    }
                });
            }

            window.removeImage = function(btn) {
                const item = btn.closest('.preview-item');
                const index = Array.from(item.parentElement.children).indexOf(item);
                uploadedImages.splice(index, 1);
                item.remove();
                updateHiddenInput();
                
                if (document.getElementById('previewGrid').children.length === 0) {
                    document.getElementById('previewContainer').classList.remove('active');
                }
            };

            window.clearAllImages = function() {
                if (confirm('确定要清空所有图片吗？')) {
                    uploadedImages = [];
                    document.getElementById('previewGrid').innerHTML = '';
                    document.getElementById('previewContainer').classList.remove('active');
                    updateHiddenInput();
                }
            };

            function updateHiddenInput() {
                const jsonData = JSON.stringify(uploadedImages);
                document.getElementById('uploadedImages').value = jsonData;
                
                console.log('Uploaded images data:', uploadedImages);
                console.log('JSON string:', jsonData);
            }

            $('#addForm').on('submit', function(e) {
                const subjectType = $('#subject_type').val();
                const subject = $('#subject').val().trim();
                const note = $('#note').val().trim();
                const uploadedImagesValue = $('#uploadedImages').val();

                console.log('=== 表单提交 ===');
                console.log('Subject Type:', subjectType);
                console.log('Subject:', subject);
                console.log('Note:', note);
                console.log('Uploaded Images (hidden field):', uploadedImagesValue);
                console.log('Uploaded Images Array:', uploadedImages);

                if (!subjectType) {
                    e.preventDefault();
                    alert('请选择黑名单类型！');
                    $('#subject_type').focus();
                    return false;
                }

                if (!subject) {
                    e.preventDefault();
                    alert('请输入黑名单内容！');
                    $('#subject').focus();
                    return false;
                }

                if (!note) {
                    e.preventDefault();
                    alert('请输入拉黑原因！');
                    $('#note').focus();
                    return false;
                }

                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.html('<i class="fas fa-spinner fa-spin"></i> 提交中...');
                submitBtn.prop('disabled', true);
            });
        })();
    </script>
</body>
</html>

<?php include './layout/footer.php'; ?>
