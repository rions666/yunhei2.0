<?php

$mod = 'blank';
include("../include/common.php");

if($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

require_once __DIR__.'/../include/function.php';
ensure_multi_subject_schema();

$title = '黑名单类型管理';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';

if ($action == 'delete' && $id > 0) {
    $existing = $DB->get_row_prepared("SELECT * FROM black_subject_types WHERE id = ?", [$id]);
    if (!$existing) {
        $error = '记录不存在！';
    } else {
        
        $usage_count = $DB->get_row_prepared("SELECT COUNT(*) as cnt FROM black_list WHERE subject_type = ?", [$existing['type_key']]);
        $usage_count = $usage_count ? intval($usage_count['cnt']) : 0;
        
        if ($usage_count > 0) {
            $error = "无法删除：已有 {$usage_count} 条黑名单记录使用此类型！";
        } else {
            $result = $DB->execute_prepared("DELETE FROM black_subject_types WHERE id = ?", [$id]);
            if ($result) {
                $message = '主体类型删除成功！';
            } else {
                $error = '删除失败！';
            }
        }
    }
}

if ($action == 'toggle' && $id > 0) {
    $existing = $DB->get_row_prepared("SELECT * FROM black_subject_types WHERE id = ?", [$id]);
    if ($existing) {
        $new_status = $existing['is_active'] == 1 ? 0 : 1;
        $result = $DB->execute_prepared("UPDATE black_subject_types SET is_active = ?, updated_at = NOW() WHERE id = ?", [$new_status, $id]);
        if ($result) {
            $message = '状态更新成功！';
        } else {
            $error = '状态更新失败！';
        }
    }
}

include './layout/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_key = trim($_POST['type_key'] ?? '');
    $type_name = trim($_POST['type_name'] ?? '');
    $placeholder = trim($_POST['placeholder'] ?? '');
    $validation_regex = trim($_POST['validation_regex'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($type_key) || empty($type_name)) {
        $error = '类型标识和类型名称不能为空！';
    } else {
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $type_key)) {
            $error = '类型标识只能包含字母、数字和下划线！';
        } else {
            if ($action == 'edit' && $id > 0) {
                
                $existing = $DB->get_row_prepared("SELECT * FROM black_subject_types WHERE id = ?", [$id]);
                if (!$existing) {
                    $error = '记录不存在！';
                } else {
                    
                    $check = $DB->get_row_prepared("SELECT id FROM black_subject_types WHERE type_key = ? AND id != ?", [$type_key, $id]);
                    if ($check) {
                        $error = '类型标识已存在！';
                    } else {
                        $result = $DB->execute_prepared(
                            "UPDATE black_subject_types SET type_key = ?, type_name = ?, placeholder = ?, validation_regex = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
                            [$type_key, $type_name, $placeholder ?: null, $validation_regex ?: null, $sort_order, $is_active, $id]
                        );
                        if ($result) {
                            $message = '主体类型更新成功！';
                        } else {
                            $error = '更新失败！';
                        }
                    }
                }
            } else {
                
                $check = $DB->get_row_prepared("SELECT id FROM black_subject_types WHERE type_key = ?", [$type_key]);
                if ($check) {
                    $error = '类型标识已存在！';
                } else {
                    $result = $DB->execute_prepared(
                        "INSERT INTO black_subject_types (type_key, type_name, placeholder, validation_regex, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [$type_key, $type_name, $placeholder ?: null, $validation_regex ?: null, $sort_order, $is_active]
                    );
                    if ($result) {
                        $message = '主体类型添加成功！';
                    } else {
                        $error = '添加失败！';
                    }
                }
            }
        }
    }
}

$edit_data = null;
if ($action == 'edit' && $id > 0) {
    $edit_data = $DB->get_row_prepared("SELECT * FROM black_subject_types WHERE id = ?", [$id]);
    if (!$edit_data) {
        $error = '记录不存在！';
        $action = '';
    }
}

$all_types = $DB->get_all_prepared("SELECT * FROM black_subject_types ORDER BY sort_order, type_name", []);
if ($all_types === false || !is_array($all_types)) {
    $all_types = [];
}

$usage_stats = [];
foreach ($all_types as $type) {
    $count = $DB->get_row_prepared("SELECT COUNT(*) as cnt FROM black_list WHERE subject_type = ?", [$type['type_key']]);
    $usage_stats[$type['type_key']] = $count ? intval($count['cnt']) : 0;
}
?>

<link href="../assets/css/design-system.css" rel="stylesheet">
<link href="../assets/css/font-awesome.min.css" rel="stylesheet">

<style>
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--spacing-xl);
}

.message-box {
    padding: var(--spacing-md);
    border-radius: var(--border-radius-base);
    margin-bottom: var(--spacing-lg);
}

.message-success {
    background: var(--color-success-light);
    border: 1px solid var(--color-success);
    color: var(--color-success);
}

.message-error {
    background: var(--color-error-light);
    border: 1px solid var(--color-error);
    color: var(--color-error);
}

.card {
    background: var(--color-bg-primary);
    border: 1px solid var(--color-border-light);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-base);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 1px solid var(--color-border-light);
}

.card-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.table-modern {
    width: 100%;
    border-collapse: collapse;
    margin-top: var(--spacing-md);
}

.table-modern th,
.table-modern td {
    padding: var(--spacing-md);
    text-align: left;
    border-bottom: 1px solid var(--color-border-light);
}

.table-modern th {
    background: var(--color-bg-secondary);
    font-weight: var(--font-weight-semibold);
    color: var(--color-primary-text);
    font-size: var(--font-size-sm);
}

.table-modern td {
    color: var(--color-secondary-text);
    font-size: var(--font-size-base);
}

.table-modern tr:hover {
    background: var(--color-bg-secondary);
}

.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: var(--border-radius-sm);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
}

.badge-active {
    background: var(--color-success-light);
    color: var(--color-success);
}

.badge-inactive {
    background: var(--color-error-light);
    color: var(--color-error);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-xs);
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--color-border-medium);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    cursor: pointer;
    text-decoration: none;
    transition: all var(--transition-base);
    background: var(--color-bg-primary);
    color: var(--color-primary-text);
}

.btn:hover {
    border-color: var(--color-accent-primary);
    color: var(--color-accent-primary);
}

.btn-primary {
    background: var(--color-accent-primary);
    color: #fff;
    border-color: var(--color-accent-primary);
}

.btn-primary:hover {
    background: var(--color-accent-hover);
    border-color: var(--color-accent-hover);
    color: #fff;
}

.btn-danger {
    color: var(--color-error);
    border-color: var(--color-error);
}

.btn-danger:hover {
    background: var(--color-error-light);
    border-color: var(--color-error);
}

.form-group {
    margin-bottom: var(--spacing-lg);
}

.form-label {
    display: block;
    font-weight: var(--font-weight-medium);
    color: var(--color-primary-text);
    margin-bottom: var(--spacing-xs);
    font-size: var(--font-size-sm);
}

.form-control {
    width: 100%;
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--color-border-medium);
    border-radius: var(--border-radius-base);
    font-size: var(--font-size-base);
    font-family: var(--font-family-primary);
    background: var(--color-bg-primary);
    color: var(--color-primary-text);
    transition: all var(--transition-base);
}

.form-control:focus {
    outline: none;
    border-color: var(--color-accent-primary);
    box-shadow: 0 0 0 2px var(--color-accent-light);
}

.form-inline {
    display: flex;
    gap: var(--spacing-md);
    align-items: center;
}

.form-checkbox {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.form-checkbox input {
    margin: 0;
}

.actions-cell {
    display: flex;
    gap: var(--spacing-xs);
}

.empty-state {
    text-align: center;
    padding: var(--spacing-xxxl);
    color: var(--color-tertiary-text);
}
</style>

<div class="admin-container">
    <?php if ($message): ?>
    <div class="message-box message-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="message-box message-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-<?php echo $action == 'edit' ? 'edit' : 'plus'; ?>"></i>
                <?php echo $action == 'edit' ? '编辑主体类型' : '添加主体类型'; ?>
            </h3>
        </div>
        
        <form method="post" action="?<?php echo $action == 'edit' ? 'action=edit&id='.$id : ''; ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                <div class="form-group">
                    <label for="type_key" class="form-label">类型标识 <span style="color: var(--color-error);">*</span></label>
                    <input type="text" id="type_key" name="type_key" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_data['type_key'] ?? ''); ?>"
                           placeholder="例如: qq, wechat, phone" required
                           <?php echo $action == 'edit' ? 'readonly' : ''; ?>
                           pattern="[a-zA-Z0-9_]+" title="只能包含字母、数字和下划线">
                    <small style="color: var(--color-secondary-text); font-size: var(--font-size-xs);">
                        唯一标识符，只能包含字母、数字和下划线，编辑后不可修改
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="type_name" class="form-label">类型名称 <span style="color: var(--color-error);">*</span></label>
                    <input type="text" id="type_name" name="type_name" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_data['type_name'] ?? ''); ?>"
                           placeholder="例如: QQ号, 微信号, 手机号" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="placeholder" class="form-label">输入提示</label>
                <input type="text" id="placeholder" name="placeholder" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_data['placeholder'] ?? ''); ?>"
                       placeholder="例如: 请输入QQ号码">
            </div>
            
            <div class="form-group">
                <label for="validation_regex" class="form-label">验证正则表达式</label>
                <input type="text" id="validation_regex" name="validation_regex" class="form-control" 
                       value="<?php echo htmlspecialchars($edit_data['validation_regex'] ?? ''); ?>"
                       placeholder="例如: ^[1-9][0-9]{4,10}$">
                <small style="color: var(--color-secondary-text); font-size: var(--font-size-xs);">
                    用于验证输入格式，留空则不验证
                </small>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                <div class="form-group">
                    <label for="sort_order" class="form-label">排序权重</label>
                    <input type="number" id="sort_order" name="sort_order" class="form-control" 
                           value="<?php echo htmlspecialchars($edit_data['sort_order'] ?? 0); ?>"
                           min="0" step="1">
                    <small style="color: var(--color-secondary-text); font-size: var(--font-size-xs);">
                        数字越小越靠前，相同权重按名称排序
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">状态</label>
                    <div class="form-checkbox">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               <?php echo ($edit_data['is_active'] ?? 1) == 1 ? 'checked' : ''; ?>>
                        <label for="is_active">启用</label>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: var(--spacing-md); margin-top: var(--spacing-lg);">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    保存
                </button>
                <?php if ($action == 'edit'): ?>
                <a href="./subject_types.php" class="btn">
                    <i class="fas fa-times"></i>
                    取消
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i>
                主体类型列表
            </h3>
        </div>
        
        <?php if (empty($all_types)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; margin-bottom: var(--spacing-md);"></i>
            <p>暂无主体类型，请先添加</p>
        </div>
        <?php else: ?>
        <table class="table-modern">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>类型标识</th>
                    <th>类型名称</th>
                    <th>输入提示</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>使用数量</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_types as $type): ?>
                <tr>
                    <td><?php echo $type['id']; ?></td>
                    <td><code><?php echo htmlspecialchars($type['type_key']); ?></code></td>
                    <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                    <td><?php echo htmlspecialchars($type['placeholder'] ?: '-'); ?></td>
                    <td><?php echo $type['sort_order']; ?></td>
                    <td>
                        <span class="badge <?php echo $type['is_active'] == 1 ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo $type['is_active'] == 1 ? '启用' : '禁用'; ?>
                        </span>
                    </td>
                    <td><?php echo $usage_stats[$type['type_key']] ?? 0; ?></td>
                    <td class="actions-cell">
                        <a href="?action=edit&id=<?php echo $type['id']; ?>" class="btn">
                            <i class="fas fa-edit"></i>
                            编辑
                        </a>
                        <a href="?action=toggle&id=<?php echo $type['id']; ?>" class="btn">
                            <i class="fas fa-<?php echo $type['is_active'] == 1 ? 'eye-slash' : 'eye'; ?>"></i>
                            <?php echo $type['is_active'] == 1 ? '禁用' : '启用'; ?>
                        </a>
                        <?php if (($usage_stats[$type['type_key']] ?? 0) == 0): ?>
                        <a href="?action=delete&id=<?php echo $type['id']; ?>" class="btn btn-danger"
                           onclick="return confirm('确定要删除此主体类型吗？此操作不可恢复！');">
                            <i class="fas fa-trash"></i>
                            删除
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include './layout/footer.php'; ?>

