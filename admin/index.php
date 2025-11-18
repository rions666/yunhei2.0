<?php
$mod = 'blank';
include("../include/common.php");

// 检查登录状态
if($islogin != 1) {
    exit("<script language='javascript'>window.location.href='./login.php';</script>");
}

$title = '仪表盘';
include './layout/header.php';

// 获取统计数据
$total_blacklist = $DB->get_row("SELECT COUNT(*) as count FROM black_list")['count'];
$today_added = $DB->get_row("SELECT COUNT(*) as count FROM black_list WHERE DATE(date) = CURDATE()")['count'];
$active_types = $DB->get_row("SELECT COUNT(DISTINCT subject_type) as count FROM black_list WHERE subject_type IS NOT NULL")['count'];
$pending_review = $DB->get_row("SELECT COUNT(*) as count FROM black_submit WHERE status = 0")['count'];

// 获取系统信息
$php_version = phpversion();
$mysql_version = $DB->get_row("SELECT VERSION() as version")['version'];
$server_time = date('Y-m-d H:i:s');
?>

<div class="dashboard-content">
    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-card-header">
                <div class="stat-card-title">总黑名单数量</div>
                <div class="stat-card-icon">
                    <i class="fas fa-database"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($total_blacklist); ?></div>
            <div class="stat-card-change positive">
                <i class="fas fa-arrow-up"></i>
                <span>系统运行正常</span>
            </div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <div class="stat-card-title">今日新增</div>
                <div class="stat-card-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($today_added); ?></div>
            <div class="stat-card-change positive">
                <i class="fas fa-calendar-day"></i>
                <span>今日统计</span>
            </div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <div class="stat-card-title">活跃类型</div>
                <div class="stat-card-icon">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($active_types); ?></div>
            <div class="stat-card-change positive">
                <i class="fas fa-chart-line"></i>
                <span>类型多样化</span>
            </div>
        </div>

        <div class="stat-card info">
            <div class="stat-card-header">
                <div class="stat-card-title">待审核提交</div>
                <div class="stat-card-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-card-value"><?php echo number_format($pending_review); ?></div>
            <div class="stat-card-change <?php echo $pending_review > 0 ? 'negative' : 'positive'; ?>">
                <i class="fas fa-<?php echo $pending_review > 0 ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                <span><?php echo $pending_review > 0 ? '需要处理' : '无待审核'; ?></span>
            </div>
        </div>
    </div>

    <!-- 快速操作 -->
    <div class="dashboard-section">
        <h2 class="section-title">
            <i class="fas fa-bolt"></i>
            快速操作
        </h2>
        <div class="quick-actions">
            <a href="./add.php" class="action-card">
                <div class="action-card-icon" style="background: var(--success-color);">
                    <i class="fas fa-plus"></i>
                </div>
                <div class="action-card-content">
                    <h4>添加黑名单</h4>
                    <p>快速添加新的黑名单记录</p>
                </div>
            </a>

            <a href="./list.php" class="action-card">
                <div class="action-card-icon" style="background: var(--info-color);">
                    <i class="fas fa-list"></i>
                </div>
                <div class="action-card-content">
                    <h4>黑名单列表</h4>
                    <p>查看和管理所有黑名单</p>
                </div>
            </a>

            <a href="./search.php" class="action-card">
                <div class="action-card-icon" style="background: var(--warning-color);">
                    <i class="fas fa-search"></i>
                </div>
                <div class="action-card-content">
                    <h4>搜索黑名单</h4>
                    <p>快速查找特定记录</p>
                </div>
            </a>

            <a href="./review.php" class="action-card">
                <div class="action-card-icon" style="background: var(--danger-color);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="action-card-content">
                    <h4>审核提交</h4>
                    <p>处理用户提交的黑名单</p>
                </div>
            </a>

            <a href="./subject_types.php" class="action-card">
                <div class="action-card-icon" style="background: var(--primary-color);">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="action-card-content">
                    <h4>类型管理</h4>
                    <p>管理黑名单类型设置</p>
                </div>
            </a>

            <a href="./settings.php" class="action-card">
                <div class="action-card-icon" style="background: #8b5cf6;">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="action-card-content">
                    <h4>系统设置</h4>
                    <p>网站配置和密码管理</p>
                </div>
            </a>

        </div>
    </div>

    <!-- 系统信息 -->
    <div class="dashboard-section">
        <h2 class="section-title">
            <i class="fas fa-info-circle"></i>
            系统信息
        </h2>
        <div class="system-info">
            <div class="system-info-grid">
                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="system-info-content">
                        <div class="system-info-label">当前用户</div>
                        <div class="system-info-value">管理员</div>
                    </div>
                </div>

                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="system-info-content">
                        <div class="system-info-label">服务器时间</div>
                        <div class="system-info-value"><?php echo $server_time; ?></div>
                    </div>
                </div>

                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="fab fa-php"></i>
                    </div>
                    <div class="system-info-content">
                        <div class="system-info-label">PHP 版本</div>
                        <div class="system-info-value"><?php echo $php_version; ?></div>
                    </div>
                </div>

                <div class="system-info-item">
                    <div class="system-info-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="system-info-content">
                        <div class="system-info-label">数据库版本</div>
                        <div class="system-info-value"><?php echo $mysql_version; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-actions .btn {
    padding: 15px 20px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-1px);
}

.text-success {
    color: var(--success-color) !important;
}
</style>

<?php include './layout/footer.php'; ?>