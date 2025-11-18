<?php

include("../include/common.php");

if($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

require_once __DIR__.'/../include/function.php';
ensure_multi_subject_schema();
$active_subject_types = get_active_subject_types();

$title = '搜索黑名单';
include './layout/header.php';

$search_results = [];
$search_performed = false;
$search_query = '';
$search_method = 0;
$result_count = 0;

if(isset($_POST['qq']) && !empty($_POST['qq'])){
    $search_query = trim((string)$_POST['qq']);
    $search_method = intval($_POST['method'] ?? 0);
    $search_performed = true;
    
    if($search_method == 1){
        
        $search_results = $DB->get_all_prepared("SELECT * FROM black_list WHERE subject LIKE ? ORDER BY id DESC", ['%'.$search_query.'%']);
    } else {
        
        $search_results = $DB->get_all_prepared("SELECT * FROM black_list WHERE subject = ? ORDER BY id DESC", [$search_query]);
    }
    
    $result_count = count($search_results);
}
?>

<link href="../assets/css/design-system.css" rel="stylesheet">
<link href="../assets/css/font-awesome.min.css" rel="stylesheet">

<style>

.admin-search-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--spacing-xl);
}

.search-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-base);
}

.search-card-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--color-border-light);
}

.search-card-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.search-form {
    display: flex;
    gap: var(--spacing-md);
    align-items: flex-end;
    flex-wrap: wrap;
}

.search-form-group {
    flex: 1;
    min-width: 200px;
}

.search-form-label {
    display: block;
    font-weight: var(--font-weight-medium);
    color: var(--color-primary-text);
    margin-bottom: var(--spacing-xs);
    font-size: var(--font-size-sm);
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.search-form-control {
    width: 100%;
    padding: var(--spacing-md);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-base);
    transition: all var(--transition-base);
    font-family: var(--font-family-primary);
}

.search-form-control:focus {
    outline: none;
    border-color: var(--color-accent-primary);
    box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.1);
}

.search-form-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 45px !important;
    cursor: pointer;
    background-color: var(--color-bg-primary);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234a5568' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 14px center;
    background-repeat: no-repeat;
    background-size: 16px;
}

.search-form-select:hover {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%231890ff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}

.search-form-select:focus {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%231890ff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}

.search-form-select option {
    padding: 8px 12px;
    background: var(--color-bg-primary);
    color: var(--color-primary-text);
}

.btn-primary {
    background: var(--color-accent-primary);
    color: white;
    border: 1px solid var(--color-accent-primary);
    padding: var(--spacing-md) var(--spacing-xl);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-medium);
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-family: var(--font-family-primary);
}

.btn-primary:hover {
    background: var(--color-accent-hover);
    border-color: var(--color-accent-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-base);
}

.btn-secondary {
    background: var(--color-bg-secondary);
    color: var(--color-primary-text);
    border: 1px solid var(--color-border-light);
    padding: var(--spacing-md) var(--spacing-xl);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-medium);
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-family: var(--font-family-primary);
}

.btn-secondary:hover {
    background: var(--color-bg-tertiary);
    border-color: var(--color-border-medium);
    transform: translateY(-1px);
}

.results-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-base);
    margin-bottom: var(--spacing-xl);
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-lg) var(--spacing-xl);
    background: var(--color-bg-secondary);
    border-bottom: 1px solid var(--color-border-light);
}

.results-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.results-count {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: 6px 12px;
    background: var(--color-accent-light);
    color: var(--color-accent-primary);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead {
    background: var(--color-bg-secondary);
}

.modern-table th {
    padding: var(--spacing-md) var(--spacing-lg);
    text-align: left;
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    font-size: var(--font-size-sm);
    border-bottom: 2px solid var(--color-border-light);
    white-space: nowrap;
}

.modern-table td {
    padding: var(--spacing-md) var(--spacing-lg);
    border-bottom: 1px solid var(--color-border-light);
    color: var(--color-primary-text);
    font-size: var(--font-size-base);
}

.modern-table tbody tr {
    transition: background-color var(--transition-fast);
}

.modern-table tbody tr:hover {
    background-color: var(--color-bg-secondary);
}

.modern-table tbody tr:last-child td {
    border-bottom: none;
}

.level-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    white-space: nowrap;
}

.level-1 {
    background: var(--color-success-light);
    color: var(--color-success);
    border: 1px solid var(--color-success);
}

.level-2 {
    background: var(--color-warning-light);
    color: var(--color-warning);
    border: 1px solid var(--color-warning);
}

.level-3 {
    background: var(--color-error-light);
    color: var(--color-error);
    border: 1px solid var(--color-error);
}

.type-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--color-accent-light);
    color: var(--color-accent-primary);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
}

.action-buttons {
    display: flex;
    gap: var(--spacing-xs);
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-edit {
    background: var(--color-info-light);
    color: var(--color-info);
    border: 1px solid var(--color-info);
}

.btn-edit:hover {
    background: var(--color-info);
    color: white;
}

.btn-delete {
    background: var(--color-error-light);
    color: var(--color-error);
    border: 1px solid var(--color-error);
}

.btn-delete:hover {
    background: var(--color-error);
    color: white;
}

.empty-state {
    text-align: center;
    padding: var(--spacing-huge) var(--spacing-xl);
    color: var(--color-tertiary-text);
}

.empty-state-icon {
    font-size: 64px;
    color: var(--color-border-medium);
    margin-bottom: var(--spacing-lg);
    opacity: 0.5;
}

.empty-state-title {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    margin-bottom: var(--spacing-md);
}

.empty-state-text {
    font-size: var(--font-size-base);
    color: var(--color-secondary-text);
    margin-bottom: var(--spacing-lg);
}

.empty-state-actions {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
    flex-wrap: wrap;
}

.subject-highlight {
    font-weight: var(--font-weight-semibold);
    color: var(--color-accent-primary);
}

.note-text {
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .admin-search-container {
        padding: var(--spacing-md);
    }
    
    .search-form {
        flex-direction: column;
    }
    
    .search-form-group {
        width: 100%;
    }
    
    .modern-table {
        font-size: var(--font-size-sm);
    }
    
    .modern-table th,
    .modern-table td {
        padding: var(--spacing-sm);
    }
    
    .note-text {
        max-width: 150px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .empty-state-actions {
        flex-direction: column;
    }
    
    .empty-state-actions .btn-primary,
    .empty-state-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }
}

.evidence-link {
    color: var(--color-accent-primary);
    cursor: pointer;
    text-decoration: none;
    font-size: var(--font-size-sm);
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    transition: all var(--transition-fast);
}

.evidence-link:hover {
    text-decoration: underline;
    color: var(--color-primary);
}

.evidence-link i {
    font-size: var(--font-size-base);
}

.images-modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.images-modal-content {
    background-color: var(--color-bg-primary);
    margin: 3% auto;
    padding: var(--spacing-xl);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    width: 90%;
    max-width: 1000px;
    box-shadow: var(--shadow-lg);
}

.images-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--color-border-light);
}

.images-modal-title {
    font-size: var(--font-size-xl);
    font-weight: 600;
    color: var(--color-primary-text);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.images-modal-close {
    background: none;
    border: none;
    font-size: var(--font-size-xl);
    color: var(--color-secondary-text);
    cursor: pointer;
    padding: var(--spacing-xs);
    transition: all var(--transition-fast);
    border-radius: var(--border-radius-base);
}

.images-modal-close:hover {
    background: var(--color-bg-secondary);
    color: var(--color-primary-text);
}

.images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--spacing-md);
}

.image-item {
    position: relative;
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-base);
    overflow: hidden;
    background: var(--color-bg-secondary);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.image-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.image-item img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
}

.image-info {
    padding: var(--spacing-sm);
    font-size: var(--font-size-xs);
    color: var(--color-secondary-text);
    background: var(--color-bg-primary);
}

.image-fullscreen {
    display: none;
    position: fixed;
    z-index: 1002;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    cursor: zoom-out;
}

.image-fullscreen img {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    max-width: 95%;
    max-height: 95%;
    object-fit: contain;
}
</style>

<div class="admin-search-container">

    <div class="search-card">
        <div class="search-card-header">
            <h3 class="search-card-title">
                <i class="fas fa-filter"></i>
                搜索条件
            </h3>
        </div>
        <form method="post" class="search-form">
            <div class="search-form-group">
                <label for="qq" class="search-form-label">
                    <i class="fas fa-tag"></i>
                    搜索内容 <span style="color: var(--color-error);">*</span>
                </label>
                <input type="text" 
                       id="qq" 
                       name="qq" 
                       class="search-form-control" 
                       placeholder="请输入要搜索的主体内容（QQ号、手机号、邮箱等）" 
                       value="<?php echo htmlspecialchars($search_query); ?>" 
                       required
                       autofocus>
                        </div>
            <div class="search-form-group" style="flex: 0 0 auto; min-width: 150px;">
                <label for="method" class="search-form-label">
                    <i class="fas fa-list-ul"></i>
                    搜索方式
                </label>
                <select id="method" name="method" class="search-form-control search-form-select">
                    <option value="0" <?php echo $search_method == 0 ? 'selected' : ''; ?>>精确匹配</option>
                    <option value="1" <?php echo $search_method == 1 ? 'selected' : ''; ?>>模糊搜索</option>
                            </select>
                        </div>
            <div class="search-form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i>
                    开始搜索
                </button>
                    </div>
            <div class="search-form-group" style="flex: 0 0 auto;">
                <a href="./list.php" class="btn-secondary">
                    <i class="fas fa-list"></i>
                    查看全部
                    </a>
                </div>
            </form>
        </div>

    <?php if($search_performed): ?>
    <div class="results-card">
        <div class="results-header">
            <h3 class="results-title">
                <i class="fas fa-list-ul"></i>
                搜索结果
            </h3>
            <span class="results-count">
                <i class="fas fa-database"></i>
                <?php echo $result_count; ?> 条记录
            </span>
    </div>

        <?php if($result_count > 0): ?>
        <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                    <th>黑名单内容</th>
                    <th>黑名单类型</th>
                    <th>黑名单等级</th>
                            <th>图片证据</th>
                            <th>拉黑原因</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                <?php foreach($search_results as $row): 
                    $subject_type = $row['subject_type'] ?? 'other';
                    $type_name = get_subject_type_name($subject_type);
                    $level = intval($row['level']);
                ?>
                        <tr>
                    <td>#<?php echo htmlspecialchars($row['id']); ?></td>
                            <td>
                        <strong class="subject-highlight"><?php echo htmlspecialchars($row['subject']); ?></strong>
                    </td>
                    <td>
                        <span class="type-badge">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($type_name); ?>
                        </span>
                    </td>
                    <td>
                        <span class="level-badge level-<?php echo $level; ?>">
                            <?php echo $level; ?>级
                        </span>
                            </td>
                            <td>
                        <?php
                        $image_count = $DB->get_row_prepared(
                            "SELECT COUNT(*) as count FROM black_list_images WHERE blacklist_id = ? AND is_deleted = 0",
                            [$row['id']]
                        );
                        $total_images = $image_count ? intval($image_count['count']) : 0;
                        ?>
                        <?php if($total_images > 0): ?>
                        <a href="javascript:void(0)" class="evidence-link" onclick="showImageModal(<?php echo $row['id']; ?>)">
                            <i class="fas fa-images"></i> 查看图片证据 (<?php echo $total_images; ?>)
                        </a>
                        <?php else: ?>
                        <span style="color: var(--color-tertiary-text); font-size: var(--font-size-sm);">无图片</span>
                        <?php endif; ?>
                            </td>
                            <td>
                        <span class="note-text" title="<?php echo htmlspecialchars($row['note']); ?>">
                            <?php echo htmlspecialchars($row['note']); ?>
                        </span>
                            </td>
                    <td><?php echo htmlspecialchars($row['date']); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="./edit.php?my=update&id=<?php echo htmlspecialchars($row['id']); ?>" 
                               class="btn-action btn-edit">
                                <i class="fas fa-edit"></i>
                                修改
                            </a>
                            <a href="./edit.php?my=del&id=<?php echo htmlspecialchars($row['id']); ?>" 
                               class="btn-action btn-delete"
                               onclick="return confirm('您确定要删除这个黑名单记录吗？此操作不可恢复！');">
                                <i class="fas fa-trash"></i>
                                删除
                                </a>
                        </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
            <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-search-minus"></i>
            </div>
            <h3 class="empty-state-title">未找到匹配记录</h3>
            <p class="empty-state-text">
                没有找到与 "<strong class="subject-highlight"><?php echo htmlspecialchars($search_query); ?></strong>" 匹配的黑名单记录
            </p>
            <div class="empty-state-actions">
                <a href="./add.php" class="btn-primary">
                    <i class="fas fa-plus"></i>
                    添加新记录
                    </a>
                <button type="button" class="btn-secondary" onclick="document.querySelector('input[name=qq]').value=''; document.querySelector('form').submit();">
                    <i class="fas fa-redo"></i>
                    重新搜索
                    </button>
                </div>
            </div>
            <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div id="imagesModal" class="images-modal">
    <div class="images-modal-content">
        <div class="images-modal-header">
            <h3 class="images-modal-title">
                <i class="fas fa-images"></i>
                图片证据
            </h3>
            <button class="images-modal-close" onclick="closeImagesModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="images-grid" id="imagesGrid"></div>
    </div>
</div>

<div id="imageFullscreen" class="image-fullscreen" onclick="closeFullscreen()">
    <img id="fullscreenImage" src="" alt="">
</div>

<script>
const allImagesData = <?php
$all_images_data = [];
if($search_results && is_array($search_results)) {
    foreach($search_results as $row) {
        $imgs = $DB->get_all_prepared(
            "SELECT * FROM black_list_images WHERE blacklist_id = ? AND is_deleted = 0 ORDER BY upload_time",
            [$row['id']]
        );
        if($imgs && is_array($imgs)) {
            $all_images_data[$row['id']] = $imgs;
        }
    }
}
echo json_encode($all_images_data);
?>;

function showImageModal(blacklistId) {
    const images = allImagesData[blacklistId];
    if (!images || images.length === 0) {
        alert('没有找到图片证据');
        return;
    }

    const grid = document.getElementById('imagesGrid');
    grid.innerHTML = '';

    images.forEach(function(img) {
        const imageUrl = '../' + img.file_path;
        const item = document.createElement('div');
        item.className = 'image-item';
        item.onclick = function() { showFullscreenImage(imageUrl); };

        item.innerHTML = `
            <img src="${imageUrl}" alt="${img.original_name}" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'200\\' height=\\'200\\'%3E%3Crect fill=\\'%23f0f0f0\\' width=\\'200\\' height=\\'200\\'/%3E%3Ctext x=\\'50%25\\' y=\\'50%25\\' text-anchor=\\'middle\\' dy=\\'.3em\\' fill=\\'%23999\\' font-size=\\'14\\'%3E图片加载失败%3C/text%3E%3C/svg%3E'">
            <div class="image-info">
                <div style="font-weight: 500; color: var(--color-primary-text); margin-bottom: 2px;">${img.original_name}</div>
                <div>${formatFileSize(img.file_size)}</div>
            </div>
        `;

        grid.appendChild(item);
    });

    document.getElementById('imagesModal').style.display = 'block';
}

function closeImagesModal() {
    document.getElementById('imagesModal').style.display = 'none';
}

function showFullscreenImage(url) {
    document.getElementById('fullscreenImage').src = url;
    document.getElementById('imageFullscreen').style.display = 'block';
}

function closeFullscreen() {
    document.getElementById('imageFullscreen').style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

window.onclick = function(event) {
    const imagesModal = document.getElementById('imagesModal');
    if (event.target == imagesModal) {
        closeImagesModal();
    }
};

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImagesModal();
        closeFullscreen();
    }
});
</script>

<?php include './layout/footer.php'; ?>
