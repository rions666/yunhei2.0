<?php
function daddslashes($input, $force = 0, $strip = false) {
    if (is_array($input)) {
        foreach ($input as $key => $val) {
            $input[$key] = daddslashes($val, $force, $strip);
        }
        return $input;
    }
    if (!is_string($input)) {
        return $input;
    }
    if ($strip) {
        $input = stripslashes($input);
    }
    return addslashes($input);
}

function sanitize_qq($qq){
    // 仅保留数字，最多 20 位（含企业 QQ 或手机号场景）
    $qq = preg_replace('/[^0-9]/', '', (string)$qq);
    return substr($qq, 0, 20);
}

function sanitize_subject($subject){
    $subject = trim((string)$subject);
    // 允许中文、字母数字、空格与常见符号 _-.@+#
    $subject = preg_replace('/[^\p{L}\p{N}\s_\-\.@+#]/u', '', $subject);
    // 限制最大长度，避免超长输入
    return mb_substr($subject, 0, 100, 'UTF-8');
}

function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
    $ckey_length = 4;
    $key = md5($key ? $key : SYS_KEY);
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);
    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);
    $result = '';
    $box = range(0, 255);
    $rndkey = array();
    for($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }
    for($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    for($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if($operation == 'DECODE') {
        if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc.str_replace('=', '', base64_encode($result));
    }
}

function random($length, $numeric = 0) {
    $seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
    $seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
    $hash = '';
    $max = strlen($seed) - 1;
    for($i = 0; $i < $length; $i++) {
        $hash .= $seed[mt_rand(0, $max)];
    }
    return $hash;
}
function showmsg($content = '未知的异常',$type = 4,$back = false,$back_name = false)
{
switch($type)
{
case 1:
	$panel="success";
break;
case 2:
	$panel="info";
break;
case 3:
	$panel="warning";
break;
case 4:
	$panel="danger";
break;
}

echo '<div class="panel panel-'.$panel.'">
      <div class="panel-heading">
        <h3 class="panel-title">提示信息</h3>
        </div>
        <div class="panel-body">';
echo $content;

if ($back) {
	if($back_name){
		echo '<hr/><a href="'.$back.'"><< '.$back_name.'</a>';
	}else{
		echo '<hr/><a href="'.$back.'"><< 返回黑名单列表</a>';
	}
	echo '<br/><a href="javascript:history.back(-1)"><< 返回上一页</a>';
}
else
    echo '<hr/><a href="javascript:history.back(-1)"><< 返回上一页</a>';

echo '</div>
    </div>';
}

function selectconfig($k){
    global $DB;
    $config = $DB->get_row_prepared("SELECT * FROM black_config WHERE k = ? limit 1", [$k]);
    return $config && isset($config['v']) ? $config['v'] : null;
}

function saveconfig($k,$v){
    global $DB;
    $ok = $DB->execute_prepared("UPDATE black_config SET v = ? WHERE k = ?", [$v, $k]);
    return $ok ? true : false;
}

// 运行时自检与迁移：确保 black_list 使用 subject 字段与索引
function ensure_blacklist_schema(){
    global $DB;
    // 检查 subject 列是否存在
    $colSubject = $DB->get_row("SHOW COLUMNS FROM `black_list` LIKE 'subject'");
    $colQQ = $DB->get_row("SHOW COLUMNS FROM `black_list` LIKE 'qq'");
    if(!$colSubject && $colQQ){
        // 将 qq 重命名为 subject
        $DB->query("ALTER TABLE `black_list` CHANGE COLUMN `qq` `subject` varchar(100) NOT NULL");
    }
    // 确保主体索引存在
    $idx = $DB->get_row("SHOW INDEX FROM `black_list` WHERE Key_name='idx_subject'");
    if(!$idx){
        $DB->query("ALTER TABLE `black_list` ADD INDEX `idx_subject` (`subject`)");
    }
}

// 运行时自检与迁移：确保存在用户提交表 black_submit
function ensure_submit_schema(){
    global $DB;
    // 检查提交表是否存在
    $tbl = $DB->get_row("SHOW TABLES LIKE 'black_submit'");
    if(!$tbl){
        $DB->query("CREATE TABLE IF NOT EXISTS `black_submit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(100) NOT NULL,
  `level` int(1) NOT NULL,
  `note` text,
  `contact` varchar(100),
  `evidence` text,
  `date` datetime NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    }
    // 确保主体索引存在
    $idx = $DB->get_row("SHOW INDEX FROM `black_submit` WHERE Key_name='idx_subject'");
    if(!$idx){
        $DB->query("ALTER TABLE `black_submit` ADD INDEX `idx_subject` (`subject`)");
    }
}

// ========== 多主体类型支持函数 ==========

// 运行时自检与迁移：确保多主体类型系统已升级
// 注意：此函数用于已安装系统的升级场景，新安装时install.sql已包含完整结构
function ensure_multi_subject_schema(){
    global $DB;
    
    // 检查主体类型配置表是否存在
    $tbl = $DB->get_row("SHOW TABLES LIKE 'black_subject_types'");
    if(!$tbl){
        // 创建主体类型配置表
        $DB->query("CREATE TABLE IF NOT EXISTS `black_subject_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_key` varchar(50) NOT NULL UNIQUE,
  `type_name` varchar(100) NOT NULL,
  `placeholder` varchar(200) DEFAULT NULL,
  `validation_regex` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type_key` (`type_key`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
        
        // 插入默认主体类型配置
        $DB->query("INSERT INTO `black_subject_types` (`type_key`, `type_name`, `placeholder`, `validation_regex`, `sort_order`, `is_active`, `created_at`) VALUES
('qq', 'QQ号', '请输入QQ号码', '^[1-9][0-9]{4,10}$', 1, 1, NOW()),
('wechat', '微信号', '请输入微信号', '^[a-zA-Z][-_a-zA-Z0-9]{5,19}$', 2, 1, NOW()),
('phone', '手机号', '请输入手机号码', '^1[3-9]\\\\d{9}$', 3, 1, NOW()),
('email', '邮箱地址', '请输入邮箱地址', '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\\\.[a-zA-Z]{2,}$', 4, 1, NOW()),
('game_id', '游戏账号', '请输入游戏账号', NULL, 5, 1, NOW()),
('other', '其他', '请输入其他类型账号', NULL, 99, 1, NOW())");
    }
    
    // 检查现有表是否已添加subject_type字段
    $col_bl = $DB->get_row("SHOW COLUMNS FROM `black_list` LIKE 'subject_type'");
    if(!$col_bl){
        $DB->query("ALTER TABLE `black_list` ADD COLUMN `subject_type` varchar(50) DEFAULT 'other' AFTER `subject`");
        $DB->query("ALTER TABLE `black_list` ADD INDEX `idx_subject_type` (`subject_type`)");
        
        // 历史数据兼容处理：根据subject内容智能推断主体类型
        $DB->query("UPDATE `black_list` SET `subject_type` = 'qq' WHERE `subject_type` = 'other' AND `subject` REGEXP '^[1-9][0-9]{4,10}$'");
        $DB->query("UPDATE `black_list` SET `subject_type` = 'phone' WHERE `subject_type` = 'other' AND `subject` REGEXP '^1[3-9][0-9]{9}$'");
        $DB->query("UPDATE `black_list` SET `subject_type` = 'email' WHERE `subject_type` = 'other' AND `subject` REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\\\.[a-zA-Z]{2,}$'");
        $DB->query("UPDATE `black_list` SET `subject_type` = 'wechat' WHERE `subject_type` = 'other' AND `subject` REGEXP '^[a-zA-Z][-_a-zA-Z0-9]{5,19}$'");
    }
    
    $col_bs = $DB->get_row("SHOW COLUMNS FROM `black_submit` LIKE 'subject_type'");
    if(!$col_bs){
        $DB->query("ALTER TABLE `black_submit` ADD COLUMN `subject_type` varchar(50) DEFAULT 'other' AFTER `subject`");
        $DB->query("ALTER TABLE `black_submit` ADD INDEX `idx_subject_type` (`subject_type`)");
        
        // 历史数据兼容处理
        $DB->query("UPDATE `black_submit` SET `subject_type` = 'qq' WHERE `subject_type` = 'other' AND `subject` REGEXP '^[1-9][0-9]{4,10}$'");
        $DB->query("UPDATE `black_submit` SET `subject_type` = 'phone' WHERE `subject_type` = 'other' AND `subject` REGEXP '^1[3-9][0-9]{9}$'");
        $DB->query("UPDATE `black_submit` SET `subject_type` = 'email' WHERE `subject_type` = 'other' AND `subject` REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\\\.[a-zA-Z]{2,}$'");
        $DB->query("UPDATE `black_submit` SET `subject_type` = 'wechat' WHERE `subject_type` = 'other' AND `subject` REGEXP '^[a-zA-Z][-_a-zA-Z0-9]{5,19}$'");
    }
    
    // 创建视图（如果不存在）
    $view_exists = $DB->get_row("SHOW TABLES LIKE 'v_subject_type_stats'");
    if(!$view_exists){
        $DB->query("CREATE OR REPLACE VIEW `v_subject_type_stats` AS
SELECT 
    st.type_key,
    st.type_name,
    COALESCE(bl_count.cnt, 0) as blacklist_count,
    COALESCE(bs_count.cnt, 0) as submit_count
FROM `black_subject_types` st
LEFT JOIN (
    SELECT subject_type, COUNT(*) as cnt 
    FROM `black_list` 
    GROUP BY subject_type
) bl_count ON st.type_key = bl_count.subject_type
LEFT JOIN (
    SELECT subject_type, COUNT(*) as cnt 
    FROM `black_submit` 
    GROUP BY subject_type  
) bs_count ON st.type_key = bs_count.subject_type
WHERE st.is_active = 1
ORDER BY st.sort_order");
    }
}

// 获取所有启用的主体类型
function get_active_subject_types(){
    global $DB;
    return $DB->get_all_prepared("SELECT * FROM black_subject_types WHERE is_active = 1 ORDER BY sort_order, type_name");
}

// 确保黑名单图片表存在
function ensure_blacklist_images_table(){
    global $DB;
    // 检查图片表是否存在
    $tbl = $DB->get_row("SHOW TABLES LIKE 'black_list_images'");
    if(!$tbl){
        $DB->query("CREATE TABLE IF NOT EXISTS `black_list_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blacklist_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `upload_time` datetime NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_blacklist_id` (`blacklist_id`),
  KEY `idx_upload_time` (`upload_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
    }
}

// 确保提交图片表存在
function ensure_submit_images_table(){
    global $DB;
    // 检查提交图片表是否存在
    $tbl = $DB->get_row("SHOW TABLES LIKE 'black_submit_images'");
    if(!$tbl){
        $DB->query("CREATE TABLE IF NOT EXISTS `black_submit_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submit_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `upload_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_submit_id` (`submit_id`),
  KEY `idx_upload_time` (`upload_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
    }
}

// 验证图片文件路径（新格式：uploads/evidence/YYYY/MM/filename）
function validate_image_file_path($file_path, $file_name, $base_dir = null) {
    if ($base_dir === null) {
        $base_dir = dirname(dirname(__FILE__));
    }
    
    // 验证路径格式是否为新格式（uploads/evidence/YYYY/MM/filename）
    if (!preg_match('#^uploads/evidence/\d{4}/\d{2}/#', $file_path)) {
        // 如果不是新格式，尝试从文件名推断（新上传的文件）
        if (!empty($file_name) && preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $file_name, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            $file_path = "uploads/evidence/{$year}/{$month}/{$file_name}";
        } else {
            // 无法推断，返回原始路径（可能有问题，记录日志）
            error_log("Invalid image file path format: {$file_path}");
            return $file_path;
        }
    }
    
    // 验证文件是否存在
    $full_path = $base_dir . '/' . $file_path;
    if (!file_exists($full_path)) {
        error_log("Image file not found: {$full_path}");
    }
    
    return $file_path;
}

// 根据type_key获取主体类型信息
function get_subject_type_by_key($type_key){
    global $DB;
    return $DB->get_row_prepared("SELECT * FROM black_subject_types WHERE type_key = ? AND is_active = 1 LIMIT 1", [$type_key]);
}

// 验证主体内容是否符合指定类型的格式
function validate_subject_by_type($subject, $type_key){
    $type_info = get_subject_type_by_key($type_key);
    if(!$type_info || empty($type_info['validation_regex'])){
        return true; // 无验证规则时默认通过
    }
    
    // 清理输入
    $subject = trim($subject);
    if(empty($subject)){
        return false;
    }
    
    // 获取正则表达式
    $regex = trim($type_info['validation_regex']);
    
    // 如果正则表达式已经包含分隔符，去掉它
    $regex = preg_replace('/^\/|\/$/', '', $regex);
    
    // 确保正则表达式有开始和结束锚点（完整匹配）
    if(strpos($regex, '^') !== 0){
        $regex = '^'.$regex;
    }
    if(substr($regex, -1) !== '$'){
        $regex = $regex.'$';
    }
    
    // 执行匹配
    $result = @preg_match('/'.$regex.'/u', $subject);
    
    // 如果preg_match返回false，可能是正则表达式错误
    if($result === false){
        // 记录错误但不中断流程，返回true允许通过
        error_log("正则表达式验证错误: " . $regex . " for subject: " . $subject);
        return true;
    }
    
    return $result === 1;
}

// 智能推断主体类型（用于历史数据兼容）
function guess_subject_type($subject){
    $subject = trim($subject);
    if(empty($subject)) return 'other';
    
    // 手机号：1开头的11位数字（优先判断，避免与QQ号冲突）
    if(preg_match('/^1[3-9]\d{9}$/', $subject)){
        return 'phone';
    }
    
    // QQ号：5-11位数字，不以0开头，但排除手机号
    if(preg_match('/^[1-9][0-9]{4,10}$/', $subject)){
        return 'qq';
    }
    
    // 邮箱地址
    if(preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $subject)){
        return 'email';
    }
    
    // 微信号：字母开头，包含字母数字下划线横线，6-20位
    if(preg_match('/^[a-zA-Z][-_a-zA-Z0-9]{5,19}$/', $subject)){
        return 'wechat';
    }
    
    return 'other';
}

// 生成主体类型下拉选择器HTML
function render_subject_type_selector($name = 'subject_type', $selected = '', $class = 'form-control', $required = false){
    $types = get_active_subject_types();
    $html = '<select name="'.$name.'" class="'.$class.'"'.($required ? ' required' : '').'>';
    $html .= '<option value="">请选择主体类型</option>';
    
    foreach($types as $type){
        $sel = ($selected == $type['type_key']) ? ' selected' : '';
        $html .= '<option value="'.htmlspecialchars($type['type_key']).'"'.$sel.'>'.htmlspecialchars($type['type_name']).'</option>';
    }
    
    $html .= '</select>';
    return $html;
}

// 获取主体类型的显示名称
function get_subject_type_name($type_key){
    $type_info = get_subject_type_by_key($type_key);
    return $type_info ? $type_info['type_name'] : '未知类型';
}

?>