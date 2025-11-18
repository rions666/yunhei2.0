<?php

$mod = 'blank';
include("../include/common.php");

if($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

require_once __DIR__.'/../include/function.php';
ensure_multi_subject_schema();
$active_subject_types = get_active_subject_types();

$title = '黑名单列表';
include './layout/header.php';

$search_subject = isset($_GET['qq']) ? trim($_GET['qq']) : '';
$search_method = isset($_GET['method']) ? intval($_GET['method']) : 0;

if ($search_subject) {
    if ($search_method == 1) {
                $where = "subject LIKE ?";
        $params = ['%'.$search_subject.'%'];
        $search_desc = "模糊搜索：".htmlspecialchars($search_subject);
    } else {
                $where = "subject = ?";
        $params = [$search_subject];
        $search_desc = "精确搜索：".htmlspecialchars($search_subject);
    }
} else {
                $where = "1";
                $params = [];
    $search_desc = '';
}

$rowc = $DB->get_row_prepared("SELECT count(*) AS c FROM black_list WHERE ".$where, $params);
$total_count = $rowc ? intval($rowc['c']) : 0;

$pagesize = 30;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $pagesize;
$total_pages = max(1, ceil($total_count / $pagesize));

$sqlRows = "SELECT * FROM black_list WHERE ".$where." ORDER BY id DESC LIMIT $offset, $pagesize";
$rows = $DB->get_all_prepared($sqlRows, $params);

$pagination_params = '';
if ($search_subject) {
    $pagination_params .= '&qq='.urlencode($search_subject);
    if ($search_method == 1) {
        $pagination_params .= '&method=1';
    }
}
?>

<link href="../assets/css/design-system.css" rel="stylesheet">
<link href="../assets/css/font-awesome.min.css" rel="stylesheet">

<style>

.admin-list-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--spacing-xl);
}

.statistics-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-base);
}

.statistics-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.statistics-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.statistics-count {
    font-size: var(--font-size-xxl);
    font-weight: var(--font-weight-bold);
    color: var(--color-accent-primary);
}

.statistics-desc {
    color: var(--color-secondary-text);
    font-size: var(--font-size-sm);
    margin-top: var(--spacing-xs);
}

.search-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-base);
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
                        }

.search-form-control {
    width: 100%;
    padding: var(--spacing-md);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-base);
    transition: all var(--transition-base);
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

.table-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-base);
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

.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: var(--spacing-xl);
}

.modern-pagination {
    display: flex;
    gap: var(--spacing-xs);
    list-style: none;
    padding: 0;
    margin: 0;
}

.pagination-item {
    display: inline-block;
}

.pagination-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 var(--spacing-sm);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-base);
    color: var(--color-primary-text);
    text-decoration: none;
    font-size: var(--font-size-sm);
    transition: all var(--transition-fast);
    background: var(--color-bg-primary);
}

.pagination-link:hover:not(.disabled):not(.active) {
    background: var(--color-bg-secondary);
    border-color: var(--color-accent-primary);
    color: var(--color-accent-primary);
}

.pagination-link.active {
    background: var(--color-accent-primary);
    border-color: var(--color-accent-primary);
    color: white;
    font-weight: var(--font-weight-semibold);
}

.pagination-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: var(--color-bg-tertiary);
}

.empty-state {
    text-align: center;
    padding: var(--spacing-huge) var(--spacing-xl);
    color: var(--color-tertiary-text);
}

.empty-state-icon {
    font-size: 48px;
    color: var(--color-border-medium);
    margin-bottom: var(--spacing-md);
}

.empty-state-text {
    font-size: var(--font-size-base);
    margin-top: var(--spacing-md);
}

@media (max-width: 768px) {
    .admin-list-container {
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
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.alert-success {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #86efac;
    color: #166534;
}

.alert i {
    font-size: 1.25rem;
}

.alert-close {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 1.5rem;
    color: inherit;
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alert-close:hover {
    opacity: 1;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
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

<div class="admin-list-container">
    <?php if (isset($_SESSION['success_message']) || isset($_SESSION['list_success'])): ?>

    <div class="alert alert-success" id="successAlert" style="animation: slideInDown 0.3s ease-out;">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['success_message'] ?? $_SESSION['list_success']); ?></span>
        <button type="button" class="alert-close" onclick="closeAlert()">&times;</button>
    </div>
    <?php
        unset($_SESSION['success_message']);
        unset($_SESSION['list_success']);
    ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['list_error'])): ?>
    <div class="alert alert-error" id="errorAlert" style="animation: slideInDown 0.3s ease-out; background: var(--color-error-light); border: 1px solid var(--color-error); color: var(--color-error);">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?php echo htmlspecialchars($_SESSION['list_error']); ?></span>
        <button type="button" class="alert-close" onclick="closeAlert()">&times;</button>
    </div>
    <?php
        unset($_SESSION['list_error']);
    ?>
    <?php endif; ?>

    <div class="statistics-card">
        <div class="statistics-header">
            <div>
                <h2 class="statistics-title">
                    <i class="fas fa-database"></i>
                    黑名单统计
                </h2>
                <?php if ($search_desc): ?>
                <div class="statistics-desc">
                    <i class="fas fa-search"></i>
                    <?php echo $search_desc; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="statistics-count">
                <?php echo number_format($total_count); ?>
            </div>
        </div>
    </div>

    <div class="search-card">
        <form method="get" action="./list.php" class="search-form">
            <div class="search-form-group">
                <label class="search-form-label">
                    <i class="fas fa-search"></i>
                    搜索主体
                </label>
                <input type="text" 
                       name="qq" 
                       class="search-form-control" 
                       placeholder="请输入要搜索的主体内容" 
                       value="<?php echo htmlspecialchars($search_subject); ?>">
            </div>
            <div class="search-form-group" style="flex: 0 0 auto; min-width: 150px;">
                <label class="search-form-label">搜索方式</label>
                <select name="method" class="search-form-control search-form-select">
                    <option value="0" <?php echo $search_method == 0 ? 'selected' : ''; ?>>精确匹配</option>
                    <option value="1" <?php echo $search_method == 1 ? 'selected' : ''; ?>>模糊搜索</option>
                </select>
            </div>
            <div class="search-form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn-action" style="background: var(--color-accent-primary); color: white; border-color: var(--color-accent-primary); padding: var(--spacing-md) var(--spacing-xl);">
                    <i class="fas fa-search"></i>
                    搜索
                </button>
            </div>
            <?php if ($search_subject): ?>
            <div class="search-form-group" style="flex: 0 0 auto;">
                <a href="./list.php" class="btn-action" style="background: var(--color-bg-secondary); color: var(--color-primary-text); border-color: var(--color-border-light); padding: var(--spacing-md) var(--spacing-xl);">
                    <i class="fas fa-times"></i>
                    清除
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-card">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="empty-state-text">
                    <?php if ($search_subject): ?>
                        未找到匹配"<?php echo htmlspecialchars($search_subject); ?>"的记录
                    <?php else: ?>
                        暂无黑名单记录
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <table class="modern-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>黑名单内容</th>
                    <th>黑名单类型</th>
                    <th>黑名单等级</th>
                    <th>图片证据</th>
                    <th>添加时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $res): 
                    $subject_type = $res['subject_type'] ?? 'other';
                    $type_name = get_subject_type_name($subject_type);
                    $level = intval($res['level']);
                ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($res['id']); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($res['subject']); ?></strong>
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
                            [$res['id']]
                        );
                        $total_images = $image_count ? intval($image_count['count']) : 0;
                        ?>
                        <?php if($total_images > 0): ?>
                        <a href="javascript:void(0)" class="evidence-link" onclick="showImageModal(<?php echo $res['id']; ?>)">
                            <i class="fas fa-images"></i> 查看图片证据 (<?php echo $total_images; ?>)
                        </a>
                        <?php else: ?>
                        <span style="color: var(--color-tertiary-text); font-size: var(--font-size-sm);">无图片</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($res['date']); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="./edit.php?my=update&id=<?php echo htmlspecialchars($res['id']); ?>" 
                               class="btn-action btn-edit">
                                <i class="fas fa-edit"></i>
                                修改
                            </a>
                            <a href="javascript:void(0)"
                               class="btn-action btn-delete"
                               onclick="showDeleteConfirm(<?php echo htmlspecialchars($res['id']); ?>, '<?php echo htmlspecialchars(addslashes($res['subject'])); ?>')">
                                <i class="fas fa-trash"></i>
                                删除
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-container">
        <ul class="modern-pagination">
        <?php
            
            if ($page > 1) {
                echo '<li class="pagination-item">';
                echo '<a href="list.php?page=1'.$pagination_params.'" class="pagination-link">';
                echo '<i class="fas fa-angle-double-left"></i>';
                echo '</a></li>';
                
                echo '<li class="pagination-item">';
                echo '<a href="list.php?page='.($page-1).$pagination_params.'" class="pagination-link">';
                echo '<i class="fas fa-angle-left"></i>';
                echo '</a></li>';
            } else {
                echo '<li class="pagination-item">';
                echo '<span class="pagination-link disabled"><i class="fas fa-angle-double-left"></i></span>';
                echo '</li>';
                
                echo '<li class="pagination-item">';
                echo '<span class="pagination-link disabled"><i class="fas fa-angle-left"></i></span>';
                echo '</li>';
            }

            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo '<li class="pagination-item">';
                echo '<a href="list.php?page=1'.$pagination_params.'" class="pagination-link">1</a>';
                echo '</li>';
                if ($start_page > 2) {
                    echo '<li class="pagination-item"><span class="pagination-link disabled">...</span></li>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<li class="pagination-item">';
                    echo '<span class="pagination-link active">'.$i.'</span>';
                    echo '</li>';
        } else {
                    echo '<li class="pagination-item">';
                    echo '<a href="list.php?page='.$i.$pagination_params.'" class="pagination-link">'.$i.'</a>';
                    echo '</li>';
                }
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<li class="pagination-item"><span class="pagination-link disabled">...</span></li>';
                }
                echo '<li class="pagination-item">';
                echo '<a href="list.php?page='.$total_pages.$pagination_params.'" class="pagination-link">'.$total_pages.'</a>';
                echo '</li>';
            }

            if ($page < $total_pages) {
                echo '<li class="pagination-item">';
                echo '<a href="list.php?page='.($page+1).$pagination_params.'" class="pagination-link">';
                echo '<i class="fas fa-angle-right"></i>';
                echo '</a></li>';
                
                echo '<li class="pagination-item">';
                echo '<a href="list.php?page='.$total_pages.$pagination_params.'" class="pagination-link">';
                echo '<i class="fas fa-angle-double-right"></i>';
                echo '</a></li>';
        } else {
                echo '<li class="pagination-item">';
                echo '<span class="pagination-link disabled"><i class="fas fa-angle-right"></i></span>';
                echo '</li>';
                
                echo '<li class="pagination-item">';
                echo '<span class="pagination-link disabled"><i class="fas fa-angle-double-right"></i></span>';
                echo '</li>';
            }
            ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<div id="deleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <div class="delete-modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="delete-modal-title">确认删除</h3>
        <p class="delete-modal-text">
            您确定要删除黑名单记录<br>
            <strong id="deleteSubjectName"></strong> 吗？
        </p>
        <p class="delete-modal-warning">
            <i class="fas fa-info-circle"></i>
            此操作不可恢复！
        </p>
        <div class="delete-modal-actions">
            <button class="btn-modal btn-cancel" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i>
                取消
            </button>
            <button class="btn-modal btn-confirm-delete" id="confirmDeleteBtn">
                <i class="fas fa-trash"></i>
                确认删除
            </button>
        </div>
    </div>
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

<style>
.delete-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.2s ease-out;
}

.delete-modal-content {
    background-color: var(--color-bg-primary);
    margin: 10% auto;
    padding: var(--spacing-xl);
    border-radius: var(--border-radius-lg);
    width: 90%;
    max-width: 450px;
    box-shadow: var(--shadow-xl);
    text-align: center;
    animation: slideInDown 0.3s ease-out;
}

.delete-modal-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto var(--spacing-lg);
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: white;
    box-shadow: 0 4px 20px rgba(255, 107, 107, 0.3);
}

.delete-modal-title {
    font-size: var(--font-size-xl);
    font-weight: 600;
    color: var(--color-primary-text);
    margin-bottom: var(--spacing-md);
}

.delete-modal-text {
    font-size: var(--font-size-base);
    color: var(--color-secondary-text);
    margin-bottom: var(--spacing-md);
    line-height: 1.6;
}

.delete-modal-text strong {
    color: var(--color-primary-text);
    font-weight: 600;
    display: inline-block;
    margin-top: var(--spacing-xs);
}

.delete-modal-warning {
    background: var(--color-warning-light);
    border: 1px solid var(--color-warning);
    color: var(--color-warning);
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-sm);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-xs);
}

.delete-modal-actions {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
}

.btn-modal {
    flex: 1;
    padding: var(--spacing-sm) var(--spacing-lg);
    border: none;
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-base);
    font-weight: 500;
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-xs);
}

.btn-cancel {
    background: var(--color-bg-secondary);
    color: var(--color-secondary-text);
    border: 1px solid var(--color-border-base);
}

.btn-cancel:hover {
    background: var(--color-bg-tertiary);
    border-color: var(--color-border-dark);
}

.btn-confirm-delete {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(255, 107, 107, 0.3);
}

.btn-confirm-delete:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>

(function() {
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        
        setTimeout(function() {
            successAlert.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(function() {
                successAlert.remove();
            }, 300);
        }, 3000);
    }
})();

function closeAlert() {
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        successAlert.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(function() {
            successAlert.remove();
        }, 300);
    }
}

let deleteId = null;

function showDeleteConfirm(id, subject) {
    deleteId = id;
    document.getElementById('deleteSubjectName').textContent = subject;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteId = null;
}

document.getElementById('confirmDeleteBtn').onclick = function() {
    if (deleteId) {
        window.location.href = './edit.php?my=del&id=' + deleteId;
    }
};

window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        closeDeleteModal();
    }
};

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDeleteModal();
        closeImagesModal();
        closeFullscreen();
    }
});

const allImagesData = <?php
$all_images_data = [];
foreach($rows as $res) {
    $imgs = $DB->get_all_prepared(
        "SELECT * FROM black_list_images WHERE blacklist_id = ? AND is_deleted = 0 ORDER BY upload_time",
        [$res['id']]
    );
    if($imgs && is_array($imgs)) {
        $all_images_data[$res['id']] = $imgs;
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
    const deleteModal = document.getElementById('deleteModal');
    const imagesModal = document.getElementById('imagesModal');

    if (event.target == deleteModal) {
        closeDeleteModal();
    }
    if (event.target == imagesModal) {
        closeImagesModal();
    }
};
</script>

<?php include './layout/footer.php'; ?>
