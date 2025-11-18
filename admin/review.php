<?php

include("../include/common.php");

if($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

require_once __DIR__.'/../include/function.php';
ensure_multi_subject_schema();
$active_subject_types = get_active_subject_types();

if(isset($_GET['my'])){
    $act = $_GET['my'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $backStatus = isset($_GET['status']) ? intval($_GET['status']) : 0;
    $row = $DB->get_row_prepared("SELECT * FROM black_submit WHERE id = ? LIMIT 1", [$id]);

    if(!$row){
        $_SESSION['review_error'] = '记录不存在！';
        header("Location: review.php?status=$backStatus");
        exit;
    }

    if($act === 'approve'){
        if(intval($row['status']) !== 0){
            $_SESSION['review_error'] = '该记录不在待审核状态！';
            header("Location: review.php?status=$backStatus");
            exit;
        }

        $subject = trim($row['subject']);
        $subject_type = $row['subject_type'] ?? 'other';
        $level = intval($row['level']);
        if($level < 1 || $level > 3){ $level = 1; }
        $note = trim((string)$row['note']);

        $exists = $DB->get_row_prepared("SELECT id FROM black_list WHERE subject = ? AND (subject_type = ? OR subject_type IS NULL) LIMIT 1", [$subject, $subject_type]);
        $msg = '';

        if(!$exists){
            $okInsert = $DB->execute_prepared(
                "INSERT INTO `black_list` (`subject`,`subject_type`,`date`,`level`,`note`) VALUES (?,?,?,?,?)",
                [$subject, $subject_type, date('Y-m-d H:i:s'), $level, $note]
            );
            if($okInsert){
                $last_row = $DB->get_row("SELECT LAST_INSERT_ID() as id");
                $blacklist_id = $last_row ? intval($last_row['id']) : 0;

                if($blacklist_id > 0){
                    $submit_images = $DB->get_all_prepared(
                        "SELECT * FROM black_submit_images WHERE submit_id = ? ORDER BY id",
                        [$id]
                    );

                    if($submit_images && is_array($submit_images)){
                        ensure_blacklist_images_table();
                        foreach($submit_images as $img){
                            $DB->execute_prepared(
                                "INSERT INTO `black_list_images` (`blacklist_id`, `original_name`, `file_name`, `file_path`, `file_size`, `mime_type`, `upload_time`) VALUES (?, ?, ?, ?, ?, ?, ?)",
                                [
                                    $blacklist_id,
                                    $img['original_name'],
                                    $img['file_name'],
                                    $img['file_path'],
                                    $img['file_size'],
                                    $img['mime_type'],
                                    $img['upload_time']
                                ]
                            );
                        }
                    }
                }

                $msg = '审核通过并已入库黑名单！';
            } else {
                $msg = '入库失败，但已标记审核通过！';
            }
        } else {
            $msg = '该主体已在黑名单中，已标记审核通过！';
        }

        $DB->execute_prepared("UPDATE black_submit SET status = 1 WHERE id = ?", [$id]);
        $_SESSION['review_success'] = $msg;
        header("Location: review.php?status=$backStatus");
        exit;

    } elseif($act === 'reject'){
        if(intval($row['status']) !== 0){
            $_SESSION['review_error'] = '该记录不在待审核状态！';
            header("Location: review.php?status=$backStatus");
            exit;
        }

        $result = $DB->execute_prepared("UPDATE black_submit SET status = 2 WHERE id = ?", [$id]);
        if($result){
            $_SESSION['review_success'] = '拒绝申请成功,该请求已清除！';
        } else {
            $_SESSION['review_error'] = '拒绝操作失败！';
        }
        header("Location: review.php?status=$backStatus");
        exit;
    }
}

$title = '用户提交审核';
include './layout/header.php';

$status = isset($_GET['status']) ? intval($_GET['status']) : 0;
if($status < 0 || $status > 2) $status = 0;

$pagesize = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $pagesize;

$rowc = $DB->get_row_prepared("SELECT count(*) AS c FROM black_submit WHERE status = ?", [$status]);
$total = $rowc ? intval($rowc['c']) : 0;

$rows = $DB->get_all_prepared(
    "SELECT * FROM black_submit WHERE status = ? ORDER BY id DESC LIMIT $offset,$pagesize",
    [$status]
);

if(!$rows || !is_array($rows)) {
    $rows = [];
}

$totalPages = max(1, ceil($total / $pagesize));

$stats = [
    'pending' => $DB->get_row("SELECT COUNT(*) as count FROM black_submit WHERE status = 0")['count'] ?? 0,
    'approved' => $DB->get_row("SELECT COUNT(*) as count FROM black_submit WHERE status = 1")['count'] ?? 0,
    'rejected' => $DB->get_row("SELECT COUNT(*) as count FROM black_submit WHERE status = 2")['count'] ?? 0,
    'today' => $DB->get_row("SELECT COUNT(*) as count FROM black_submit WHERE DATE(date) = CURDATE()")['count'] ?? 0
];

$status_texts = [
    0 => '待审核',
    1 => '已通过',
    2 => '已拒绝'
];
?>

<link href="../assets/css/design-system.css" rel="stylesheet">
<link href="../assets/css/font-awesome.min.css" rel="stylesheet">

<style>

.admin-review-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--spacing-xl);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-xl);
}

.stat-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    box-shadow: var(--shadow-base);
    transition: all var(--transition-base);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--border-radius-base);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-xl);
    color: white;
}

.stat-card-icon.bg-warning {
    background: var(--color-warning);
}

.stat-card-icon.bg-success {
    background: var(--color-success);
}

.stat-card-icon.bg-danger {
    background: var(--color-error);
}

.stat-card-icon.bg-info {
    background: var(--color-info);
}

.stat-card-value {
    font-size: var(--font-size-xxl);
    font-weight: var(--font-weight-bold);
    color: var(--color-primary-text);
    margin: 0;
}

.stat-card-label {
    font-size: var(--font-size-sm);
    color: var(--color-secondary-text);
    margin: 0;
}

.filter-card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-base);
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--color-border-light);
}

.filter-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.filter-form {
    display: flex;
    gap: var(--spacing-md);
    align-items: center;
    flex-wrap: wrap;
}

.filter-select {
    padding: var(--spacing-md);
    padding-right: 45px !important;
    border: 2px solid var(--color-border-medium);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-base);
    font-family: var(--font-family-primary);
    background: var(--color-bg-primary);
    color: var(--color-primary-text);
    cursor: pointer;
    transition: all var(--transition-base);
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234a5568' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 14px center;
    background-repeat: no-repeat;
    background-size: 16px;
}

.filter-select:hover {
    border-color: var(--color-border-dark);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%231890ff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}

.filter-select:focus {
    outline: none;
    border-color: var(--color-accent-primary);
    box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.15);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%231890ff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
}

.filter-select option {
    padding: 8px 12px;
    background: var(--color-bg-primary);
    color: var(--color-primary-text);
}

.filter-info {
    color: var(--color-secondary-text);
    font-size: var(--font-size-sm);
    margin-top: var(--spacing-xs);
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
    vertical-align: top;
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

.subject-info {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.subject-text {
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
}

.subject-note {
    font-size: var(--font-size-sm);
    color: var(--color-secondary-text);
    margin-top: var(--spacing-xs);
}

.type-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--color-accent-light);
    color: var(--color-accent-primary);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    margin-top: var(--spacing-xs);
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

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    white-space: nowrap;
}

.status-pending {
    background: var(--color-warning-light);
    color: var(--color-warning);
    border: 1px solid var(--color-warning);
}

.status-approved {
    background: var(--color-success-light);
    color: var(--color-success);
    border: 1px solid var(--color-success);
}

.status-rejected {
    background: var(--color-error-light);
    color: var(--color-error);
    border: 1px solid var(--color-error);
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.btn-action {
    padding: 8px 16px;
    border: none;
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-xs);
    white-space: nowrap;
}

.btn-approve {
    background: var(--color-success-light);
    color: var(--color-success);
    border: 1px solid var(--color-success);
}

.btn-approve:hover {
    background: var(--color-success);
    color: white;
}

.btn-reject {
    background: var(--color-error-light);
    color: var(--color-error);
    border: 1px solid var(--color-error);
}

.btn-reject:hover {
    background: var(--color-error);
    color: white;
}

.evidence-text {
    max-width: 250px;
    font-size: var(--font-size-sm);
    color: var(--color-secondary-text);
    line-height: 1.5;
}

.evidence-link {
    color: var(--color-accent-primary);
    cursor: pointer;
    text-decoration: none;
    font-size: var(--font-size-xs);
    margin-top: var(--spacing-xs);
    display: inline-block;
}

.evidence-link:hover {
    text-decoration: underline;
}

.contact-info {
    font-size: var(--font-size-sm);
    color: var(--color-secondary-text);
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

.empty-state-text {
    font-size: var(--font-size-base);
    color: var(--color-secondary-text);
}

.pagination-container {
    display: flex;
    justify-content: center;
    margin-top: var(--spacing-xl);
    padding: var(--spacing-lg);
    border-top: 1px solid var(--color-border-light);
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

.date-text {
    font-size: var(--font-size-sm);
    color: var(--color-secondary-text);
}

.evidence-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.evidence-modal-content {
    background-color: var(--color-bg-primary);
    margin: 5% auto;
    padding: var(--spacing-xl);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    width: 90%;
    max-width: 800px;
    box-shadow: var(--shadow-lg);
}

.evidence-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--color-border-light);
}

.evidence-modal-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    margin: 0;
}

.evidence-modal-close {
    background: none;
    border: none;
    font-size: var(--font-size-xl);
    color: var(--color-secondary-text);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius-base);
    transition: all var(--transition-fast);
}

.evidence-modal-close:hover {
    background: var(--color-bg-secondary);
    color: var(--color-primary-text);
}

.evidence-modal-body {
    max-height: 500px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.6;
    color: var(--color-primary-text);
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

.images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--spacing-md);
    margin-top: var(--spacing-lg);
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

@media (max-width: 768px) {
    .admin-review-container {
        padding: var(--spacing-md);
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .modern-table {
        font-size: var(--font-size-sm);
    }
    
    .modern-table th,
    .modern-table td {
        padding: var(--spacing-sm);
    }
    
    .evidence-text {
        max-width: 150px;
    }
    
    .action-buttons {
        flex-direction: row;
    }
    
    .btn-action {
        flex: 1;
    }
}
</style>

<div class="admin-review-container">

    <?php if(isset($_SESSION['review_success'])): ?>
    <div class="alert alert-success" style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md) var(--spacing-lg); background: var(--color-success-light); border: 1px solid var(--color-success); color: var(--color-success); border-radius: var(--border-radius-base); display: flex; align-items: center; gap: var(--spacing-sm);">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['review_success']); unset($_SESSION['review_success']); ?>
    </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['review_error'])): ?>
    <div class="alert alert-error" style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md) var(--spacing-lg); background: var(--color-error-light); border: 1px solid var(--color-error); color: var(--color-error); border-radius: var(--border-radius-base); display: flex; align-items: center; gap: var(--spacing-sm);">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($_SESSION['review_error']); unset($_SESSION['review_error']); ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon bg-warning">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['pending']); ?></div>
            <div class="stat-card-label">待审核</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon bg-success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['approved']); ?></div>
            <div class="stat-card-label">已通过</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon bg-danger">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['rejected']); ?></div>
            <div class="stat-card-label">已拒绝</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <div class="stat-card-icon bg-info">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['today']); ?></div>
            <div class="stat-card-label">今日提交</div>
        </div>
    </div>

    <div class="filter-card">
        <div class="filter-header">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i>
                筛选条件
            </h3>
        </div>
        <form method="get" class="filter-form">
            <div style="flex: 1; min-width: 200px;">
                <label for="status" style="display: block; margin-bottom: var(--spacing-xs); font-size: var(--font-size-sm); color: var(--color-primary-text);">
                    状态筛选
                </label>
                <select name="status" id="status" class="filter-select" onchange="this.form.submit()">
                    <option value="0" <?php echo $status === 0 ? 'selected' : ''; ?>>待审核</option>
                    <option value="1" <?php echo $status === 1 ? 'selected' : ''; ?>>已通过</option>
                    <option value="2" <?php echo $status === 2 ? 'selected' : ''; ?>>已拒绝</option>
                </select>
            </div>
        </form>
        <div class="filter-info">
            当前状态：<strong><?php echo htmlspecialchars($status_texts[$status]); ?></strong>，
            共 <strong><?php echo number_format($total); ?></strong> 条记录
        </div>
    </div>

    <div class="table-card">
        <?php if($rows && count($rows) > 0): ?>
        <table class="modern-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>黑名单信息</th>
                    <th>提交时间</th>
                    <th>建议等级</th>
                    <th>联系方式</th>
                    <th>举报证据</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $r): 
                    $subject_type = $r['subject_type'] ?? 'other';
                    $type_name = get_subject_type_name($subject_type);
                    $level = intval($r['level']);
                ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($r['id']); ?></td>
                    <td>
                        <div class="subject-info">
                            <span class="subject-text"><?php echo htmlspecialchars($r['subject']); ?></span>
                            <span class="type-badge">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($type_name); ?>
                            </span>
                            <?php if($r['note']): ?>
                            <span class="subject-note"><?php echo htmlspecialchars(mb_substr($r['note'], 0, 50)); ?><?php echo mb_strlen($r['note']) > 50 ? '...' : ''; ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="date-text"><?php echo htmlspecialchars($r['date']); ?></span>
                    </td>
                    <td>
                        <span class="level-badge level-<?php echo $level; ?>">
                            <?php echo $level; ?>级
                        </span>
                    </td>
                    <td>
                        <span class="contact-info"><?php echo htmlspecialchars($r['contact'] ?: '未提供'); ?></span>
                    </td>
                    <td>
                        <div class="evidence-text">
                            <?php
                            $submit_images = $DB->get_all_prepared(
                                "SELECT * FROM black_submit_images WHERE submit_id = ? ORDER BY id",
                                [$r['id']]
                            );
                            $image_count = $submit_images ? count($submit_images) : 0;
                            ?>

                            <?php if($r['evidence']): ?>
                            <?php echo nl2br(htmlspecialchars(mb_substr($r['evidence'], 0, 100))); ?>
                            <?php if(mb_strlen($r['evidence']) > 100): ?>
                            <a href="javascript:void(0)" class="evidence-link" onclick="showFullEvidence('<?php echo htmlspecialchars(addslashes($r['evidence'])); ?>')">
                                查看完整证据
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php if($image_count > 0): ?>
                            <div style="margin-top: var(--spacing-xs);">
                                <a href="javascript:void(0)" class="evidence-link" onclick="showSubmitImages(<?php echo $r['id']; ?>)">
                                    <i class="fas fa-images"></i> 查看图片证据 (<?php echo $image_count; ?>)
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if(!$r['evidence'] && $image_count == 0): ?>
                            <span style="color: var(--color-tertiary-text);">无证据</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if($r['status'] == 0): ?>
                        <span class="status-badge status-pending">
                            <i class="fas fa-clock"></i>
                            待审核
                        </span>
                        <?php elseif($r['status'] == 1): ?>
                        <span class="status-badge status-approved">
                            <i class="fas fa-check-circle"></i>
                            已通过
                        </span>
                        <?php else: ?>
                        <span class="status-badge status-rejected">
                            <i class="fas fa-times-circle"></i>
                            已拒绝
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($r['status'] == 0): ?>
                        <div class="action-buttons">
                            <a href="javascript:void(0)"
                               class="btn-action btn-approve"
                               onclick="showApproveConfirm(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['subject'])); ?>', <?php echo $status; ?>)">
                                <i class="fas fa-check"></i>
                                通过
                            </a>
                            <a href="javascript:void(0)"
                               class="btn-action btn-reject"
                               onclick="showRejectConfirm(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['subject'])); ?>', <?php echo $status; ?>)">
                                <i class="fas fa-times"></i>
                                拒绝
                            </a>
                        </div>
                        <?php else: ?>
                        <span style="color: var(--color-tertiary-text); font-size: var(--font-size-sm);">已处理</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-inbox"></i>
            </div>
            <div class="empty-state-text">
                当前状态下暂无提交记录
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if($totalPages > 1): ?>
    <div class="pagination-container">
        <ul class="modern-pagination">
            <?php if($page > 1): ?>
            <li class="pagination-item">
                <a href="?status=<?php echo $status; ?>&page=<?php echo $page-1; ?>" class="pagination-link">
                    <i class="fas fa-angle-left"></i>
                </a>
            </li>
            <?php else: ?>
            <li class="pagination-item">
                <span class="pagination-link disabled">
                    <i class="fas fa-angle-left"></i>
                </span>
            </li>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($totalPages, $page + 2);
            
            if ($start_page > 1) {
                echo '<li class="pagination-item">';
                echo '<a href="?status='.$status.'&page=1" class="pagination-link">1</a>';
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
                    echo '<a href="?status='.$status.'&page='.$i.'" class="pagination-link">'.$i.'</a>';
                    echo '</li>';
                }
            }
            
            if ($end_page < $totalPages) {
                if ($end_page < $totalPages - 1) {
                    echo '<li class="pagination-item"><span class="pagination-link disabled">...</span></li>';
                }
                echo '<li class="pagination-item">';
                echo '<a href="?status='.$status.'&page='.$totalPages.'" class="pagination-link">'.$totalPages.'</a>';
                echo '</li>';
            }
            ?>
            
            <?php if($page < $totalPages): ?>
            <li class="pagination-item">
                <a href="?status=<?php echo $status; ?>&page=<?php echo $page+1; ?>" class="pagination-link">
                    <i class="fas fa-angle-right"></i>
                </a>
            </li>
            <?php else: ?>
            <li class="pagination-item">
                <span class="pagination-link disabled">
                    <i class="fas fa-angle-right"></i>
                </span>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<div id="evidenceModal" class="evidence-modal">
    <div class="evidence-modal-content">
        <div class="evidence-modal-header">
            <h3 class="evidence-modal-title">
                <i class="fas fa-file-alt"></i>
                举报证据详情
            </h3>
            <button class="evidence-modal-close" onclick="closeEvidenceModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="evidence-modal-body" id="evidenceContent"></div>
    </div>
</div>

<div id="imagesModal" class="images-modal">
    <div class="images-modal-content">
        <div class="evidence-modal-header">
            <h3 class="evidence-modal-title">
                <i class="fas fa-images"></i>
                图片证据
            </h3>
            <button class="evidence-modal-close" onclick="closeImagesModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="images-grid" id="imagesGrid"></div>
    </div>
</div>

<div id="imageFullscreen" class="image-fullscreen" onclick="closeFullscreen()">
    <img id="fullscreenImage" src="" alt="">
</div>

<div id="approveModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <div class="confirm-modal-icon approve-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="confirm-modal-title">确认通过</h3>
        <p class="confirm-modal-text">
            您确定要通过该提交并加入黑名单吗？<br>
            <strong id="approveSubjectName"></strong>
        </p>
        <p class="confirm-modal-warning approve-warning">
            <i class="fas fa-info-circle"></i>
            通过后将自动添加到黑名单列表
        </p>
        <div class="confirm-modal-actions">
            <button class="btn-modal btn-cancel" onclick="closeApproveModal()">
                <i class="fas fa-times"></i>
                取消
            </button>
            <button class="btn-modal btn-confirm-approve" id="confirmApproveBtn">
                <i class="fas fa-check"></i>
                确认通过
            </button>
        </div>
    </div>
</div>

<div id="rejectModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <div class="confirm-modal-icon reject-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <h3 class="confirm-modal-title">确认拒绝</h3>
        <p class="confirm-modal-text">
            您确定要拒绝该提交吗？<br>
            <strong id="rejectSubjectName"></strong>
        </p>
        <p class="confirm-modal-warning reject-warning">
            <i class="fas fa-exclamation-triangle"></i>
            拒绝后该记录将被标记为已拒绝
        </p>
        <div class="confirm-modal-actions">
            <button class="btn-modal btn-cancel" onclick="closeRejectModal()">
                <i class="fas fa-times"></i>
                取消
            </button>
            <button class="btn-modal btn-confirm-reject" id="confirmRejectBtn">
                <i class="fas fa-trash"></i>
                确认拒绝
            </button>
        </div>
    </div>
</div>

<style>
.confirm-modal {
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

.confirm-modal-content {
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

.confirm-modal-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto var(--spacing-lg);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    color: white;
}

.approve-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
}

.reject-icon {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    box-shadow: 0 4px 20px rgba(255, 107, 107, 0.3);
}

.confirm-modal-title {
    font-size: var(--font-size-xl);
    font-weight: 600;
    color: var(--color-primary-text);
    margin-bottom: var(--spacing-md);
}

.confirm-modal-text {
    font-size: var(--font-size-base);
    color: var(--color-secondary-text);
    margin-bottom: var(--spacing-md);
    line-height: 1.6;
}

.confirm-modal-text strong {
    color: var(--color-primary-text);
    font-weight: 600;
    display: inline-block;
    margin-top: var(--spacing-xs);
}

.confirm-modal-warning {
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-sm);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-xs);
}

.approve-warning {
    background: var(--color-success-light);
    border: 1px solid var(--color-success);
    color: var(--color-success);
}

.reject-warning {
    background: var(--color-warning-light);
    border: 1px solid var(--color-warning);
    color: var(--color-warning);
}

.confirm-modal-actions {
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

.btn-confirm-approve {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-confirm-approve:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-confirm-reject {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(255, 107, 107, 0.3);
}

.btn-confirm-reject:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
}
</style>

<script src="../assets/js/jquery-3.6.0.min.js"></script>
<script>
const submitImagesData = <?php
$all_submit_images = [];
if($rows && is_array($rows)) {
    foreach($rows as $r) {
        $images = $DB->get_all_prepared(
            "SELECT * FROM black_submit_images WHERE submit_id = ? ORDER BY id",
            [$r['id']]
        );
        if($images) {
            $all_submit_images[$r['id']] = $images;
        }
    }
}
echo json_encode($all_submit_images);
?>;

function showFullEvidence(evidence) {
    document.getElementById('evidenceContent').textContent = evidence;
    document.getElementById('evidenceModal').style.display = 'block';
}

function closeEvidenceModal() {
    document.getElementById('evidenceModal').style.display = 'none';
}

function showSubmitImages(submitId) {
    const images = submitImagesData[submitId];
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
    const evidenceModal = document.getElementById('evidenceModal');
    const imagesModal = document.getElementById('imagesModal');

    if (event.target == evidenceModal) {
        closeEvidenceModal();
    }
    if (event.target == imagesModal) {
        closeImagesModal();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEvidenceModal();
        closeImagesModal();
        closeFullscreen();
        closeApproveModal();
        closeRejectModal();
    }
});

let approveId = null;
let approveStatus = null;
let rejectId = null;
let rejectStatus = null;

function showApproveConfirm(id, subject, status) {
    approveId = id;
    approveStatus = status;
    document.getElementById('approveSubjectName').textContent = subject;
    document.getElementById('approveModal').style.display = 'block';
}

function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
    approveId = null;
    approveStatus = null;
}

function showRejectConfirm(id, subject, status) {
    rejectId = id;
    rejectStatus = status;
    document.getElementById('rejectSubjectName').textContent = subject;
    document.getElementById('rejectModal').style.display = 'block';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    rejectId = null;
    rejectStatus = null;
}

document.getElementById('confirmApproveBtn').onclick = function() {
    if (approveId) {
        window.location.href = '?my=approve&id=' + approveId + '&status=' + approveStatus;
    }
};

document.getElementById('confirmRejectBtn').onclick = function() {
    if (rejectId) {
        window.location.href = '?my=reject&id=' + rejectId + '&status=' + rejectStatus;
    }
};

window.onclick = function(event) {
    const evidenceModal = document.getElementById('evidenceModal');
    const imagesModal = document.getElementById('imagesModal');
    const approveModal = document.getElementById('approveModal');
    const rejectModal = document.getElementById('rejectModal');

    if (event.target == evidenceModal) {
        closeEvidenceModal();
    }
    if (event.target == imagesModal) {
        closeImagesModal();
    }
    if (event.target == approveModal) {
        closeApproveModal();
    }
    if (event.target == rejectModal) {
        closeRejectModal();
    }
};
</script>

<?php include './layout/footer.php'; ?>
