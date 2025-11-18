

-- 设置 SQL 模式：插入 0 到自增列时不触发自增，保留 0 值
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 将会话时区设置为 UTC（+00:00）
SET time_zone = "+00:00";


-- 记录当前连接的字符集设置（仅在 MySQL 版本 >= 4.1.1 时执行）
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
-- 记录当前结果集的字符集设置（仅在 MySQL 版本 >= 4.1.1 时执行）
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
-- 记录当前连接的校对规则（仅在 MySQL 版本 >= 4.1.1 时执行）
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
-- 将连接字符集设置为 utf8（仅在 MySQL 版本 >= 4.1.1 时执行）
/*!40101 SET NAMES utf8 */;

-- --------------------------------------------------------

--
-- 表的结构 `black_admin`
-- 管理员账户表：存储后台登录用户与状态
--

CREATE TABLE IF NOT EXISTS `black_admin` (
  `uid` int(11) NOT NULL AUTO_INCREMENT, -- 管理员主键 ID，自增
  `user` varchar(150) NOT NULL,          -- 管理员用户名
  `pass` varchar(150) NOT NULL,          -- 密码哈希（当前为 MD5）
  `last` datetime NOT NULL,              -- 最后登录时间
  `dlip` varchar(15) DEFAULT NULL,       -- 登录 IP（IPv4）
  `active` int(1) DEFAULT NULL,          -- 账户状态（1 启用，0 禁用）
  PRIMARY KEY (`uid`)                    -- 主键约束：uid
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ; -- 存储引擎与默认字符集

--
-- 转存表中的数据 `black_admin`
-- 初始化一个默认管理员 admin，密码为 MD5('admin')
--

INSERT INTO `black_admin` (`uid`, `user`, `pass`, `last`, `dlip`, `active`) VALUES
(1, 'admin', '21232f297a57a5a743894a0e4a801fc3', '2017-02-05 00:00:00', '127.0.0.1', 1);

-- --------------------------------------------------------

--
-- 表的结构 `black_config`
-- 站点配置键值表：存储站点名称、关键字、描述等
--

CREATE TABLE IF NOT EXISTS `black_config` (
  `k` varchar(255) NOT NULL DEFAULT '', -- 配置键（最长 255）
  `v` text,                             -- 配置值（文本）
  PRIMARY KEY (`k`(10))                 -- 主键索引仅取前 10 个字符（存在键截断风险）
) ENGINE=MyISAM DEFAULT CHARSET=utf8;   -- 存储引擎与默认字符集

--
-- 转存表中的数据 `black_config`
-- 初始化默认站点配置
--

INSERT INTO `black_config` (`k`, `v`) VALUES
('sitename', '云黑系统');   -- 站点名称

-- --------------------------------------------------------

--
-- 表的结构 `black_list`
-- 黑名单主体表：存储 主体、等级、时间与原因
--

CREATE TABLE IF NOT EXISTS `black_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,  -- 黑名单主键 ID，自增
  `subject` varchar(100) NOT NULL,       -- 通用主体：微信号/手机号/QQ/游戏账号（最长 100）
  `subject_type` varchar(50) DEFAULT 'other', -- 主体类型（qq, wechat, phone, email等）
  `level` int(1) NOT NULL,               -- 黑名单等级（1-低，2-中，3-高）
  `date` datetime NOT NULL,              -- 拉黑时间
  `note` text COMMENT '拉黑原因',         -- 拉黑原因（备注）
  PRIMARY KEY (`id`),
  KEY `idx_subject` (`subject`),         -- 加速按主体查询
  KEY `idx_subject_type` (`subject_type`) -- 加速按主体类型查询
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; -- 存储引擎与默认字符集

-- 恢复之前记录的字符集/校对设置（仅在 MySQL 版本 >= 4.1.1 时执行）
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
 /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
 /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------

-- 表的结构 `black_submit`
-- 用户提交表：收集用户主动提交的黑名单线索，待审核

CREATE TABLE IF NOT EXISTS `black_submit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(100) NOT NULL,       -- 提交主体
  `subject_type` varchar(50) DEFAULT 'other', -- 主体类型（qq, wechat, phone, email等）
  `level` int(1) NOT NULL,               -- 建议黑名单等级
  `note` text,                           -- 备注/原因
  `contact` varchar(100),                -- 联系方式（QQ/微信/邮箱）
  `evidence` text,                       -- 证据链接或说明
  `date` datetime NOT NULL,              -- 提交时间
  `status` int(1) NOT NULL DEFAULT 0,    -- 审核状态（0 待审，1 通过，2 拒绝）
  PRIMARY KEY (`id`),
  KEY `idx_subject` (`subject`),
  KEY `idx_subject_type` (`subject_type`) -- 加速按主体类型查询
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 多主体类型系统：主体类型配置表
-- 用于管理可选的主体类型及其显示配置
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `black_subject_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_key` varchar(50) NOT NULL UNIQUE,     -- 类型标识符（如：qq, wechat, phone）
  `type_name` varchar(100) NOT NULL,          -- 显示名称（如：QQ号, 微信号, 手机号）
  `placeholder` varchar(200) DEFAULT NULL,    -- 输入框提示文字
  `validation_regex` varchar(500) DEFAULT NULL, -- 验证正则表达式（可选）
  `sort_order` int(11) DEFAULT 0,             -- 排序权重
  `is_active` tinyint(1) DEFAULT 1,           -- 是否启用（1启用，0禁用）
  `created_at` datetime NOT NULL,             -- 创建时间
  `updated_at` datetime DEFAULT NULL,         -- 更新时间
  PRIMARY KEY (`id`),
  KEY `idx_type_key` (`type_key`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

-- --------------------------------------------------------
-- 插入默认主体类型配置
-- --------------------------------------------------------

INSERT INTO `black_subject_types` (`type_key`, `type_name`, `placeholder`, `validation_regex`, `sort_order`, `is_active`, `created_at`) VALUES
('qq', 'QQ号', '请输入QQ号码', '^[1-9][0-9]{4,10}$', 1, 1, NOW()),
('wechat', '微信号', '请输入微信号', '^[a-zA-Z][-_a-zA-Z0-9]{5,19}$', 2, 1, NOW()),
('phone', '手机号', '请输入手机号码', '^1[3-9]\\d{9}$', 3, 1, NOW()),
('email', '邮箱地址', '请输入邮箱地址', '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$', 4, 1, NOW()),
('game_id', '游戏账号', '请输入游戏账号', NULL, 5, 1, NOW()),
('other', '其他', '请输入其他类型账号', NULL, 99, 1, NOW());

-- --------------------------------------------------------
-- 创建视图：便于查询主体类型统计
-- --------------------------------------------------------

CREATE OR REPLACE VIEW `v_subject_type_stats` AS
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
ORDER BY st.sort_order;

-- --------------------------------------------------------
-- 系统版本标记
-- --------------------------------------------------------

INSERT INTO `black_config` (`k`, `v`) VALUES 
('multi_subject_version', '2.0'),
('multi_subject_upgrade_date', NOW())
ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);
