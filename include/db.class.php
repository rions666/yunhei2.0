<?php
//MySQL、MySQLi、SQLite 三合一数据库操作类
if(!defined('IN_CRONLITE'))exit();

$nomysqli=false;

if(defined('SQLITE')==true){
	class DB {
		public $link = null;
        public $result = null;

		public function __construct($db_file){
			global $siteurl;
            try {
                $this->link = new PDO('sqlite:'.ROOT.'includes/sqlite/'.$db_file.'.db');
                $this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die('Connection Sqlite failed: '.$e->getMessage());
            }
        }

		public function fetch($q){
			return $q->fetch();
		}
		public function get_row($q){
			$sth = $this->link->query($q);
			return $sth->fetch();
		}
		public function count($q){
			$sth = $this->link->query($q);
			return $sth->fetchColumn();
		}
		public function query($q){
			return $this->result=$this->link->query($q);
		}
		public function affected(){
			return $this->result ? $this->result->rowCount() : 0;
		}
		public function error(){
			$error = $this->link->errorInfo();
			return '['.$error[1].'] '.$error[2];
		}
        public function get_row_prepared($sql, $params = []){
            $sth = $this->link->prepare($sql);
            $sth->execute($params);
            return $sth->fetch(PDO::FETCH_ASSOC);
        }
        public function get_all_prepared($sql, $params = []){
            $sth = $this->link->prepare($sql);
            $sth->execute($params);
            return $sth->fetchAll(PDO::FETCH_ASSOC);
        }
        public function execute_prepared($sql, $params = []){
            $sth = $this->link->prepare($sql);
            return $sth->execute($params);
        }
        public function escape($str){
            return addslashes($str);
        }
	}
}
elseif(extension_loaded('mysqli') && $nomysqli==false) {
    class DB {
        public $link = null;

        public function __construct($db_host,$db_user,$db_pass,$db_name,$db_port){
            $this->link = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
            if (!$this->link) die('Connect Error (' . mysqli_connect_errno() . ') '.mysqli_connect_error());
            //mysqli_select_db($this->link, $db_name) or die(mysqli_error($this->link));

            mysqli_query($this->link,"set sql_mode = ''");
            //字符转换，读库
            mysqli_query($this->link,"set character set 'utf8'");
            //写库
            mysqli_query($this->link,"set names 'utf8'");
        }
		public function fetch($q){
			return mysqli_fetch_assoc($q);
		}
		public function get_row($q){
			$result = mysqli_query($this->link,$q);
			return mysqli_fetch_assoc($result);
		}
		public function count($q){
			$result = mysqli_query($this->link,$q);
			$count = mysqli_fetch_array($result);
			return $count[0];
		}
		public function query($q){
			return mysqli_query($this->link,$q);
		}
		public function escape($str){
			return mysqli_real_escape_string($this->link,$str);
		}
        public function get_row_prepared($sql, $params = []){
            $stmt = mysqli_prepare($this->link, $sql);
            if(!$stmt) return null;
            if(!empty($params)){
                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if($result){
                $row = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
                mysqli_stmt_close($stmt);
                return $row;
            }
            mysqli_stmt_close($stmt);
            return null;
        }
        public function get_all_prepared($sql, $params = []){
            $stmt = mysqli_prepare($this->link, $sql);
            if(!$stmt) return [];
            if(!empty($params)){
                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            if($result){
                while($row = mysqli_fetch_assoc($result)){
                    $rows[] = $row;
                }
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmt);
            return $rows;
        }
        public function execute_prepared($sql, $params = []){
            $stmt = mysqli_prepare($this->link, $sql);
            if(!$stmt) return false;
            if(!empty($params)){
                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $ok;
        }
		public function insert($q){
			if(mysqli_query($this->link,$q))
				return mysqli_insert_id($this->link); 
			return false;
		}
		public function affected(){
			return mysqli_affected_rows($this->link);
		}
		public function insert_array($table,$array){
			$q = "INSERT INTO `$table`";
			$q .=" (`".implode("`,`",array_keys($array))."`) ";
			$q .=" VALUES ('".implode("','",array_values($array))."') ";
			
			if(mysqli_query($this->link,$q))
				return mysqli_insert_id($this->link);
			return false;
		}
		public function error(){
			$error = mysqli_error($this->link);
			$errno = mysqli_errno($this->link);
			return '['.$errno.'] '.$error;
		}
		public function close(){
			$q = mysqli_close($this->link);
			return $q;
		}
	}
} else {
	class DB {
		public $link = null;

		public function __construct($db_host,$db_user,$db_pass,$db_name,$db_port){
            $this->link = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
            if (!$this->link) die('Connect Error (' . mysqli_connect_errno() . ') '.mysqli_connect_error());

            mysqli_query($this->link,"set sql_mode = ''");
            mysqli_query($this->link,"set character set 'utf8'");
            mysqli_query($this->link,"set names 'utf8'");
		}
		public function fetch($q){
			return mysqli_fetch_assoc($q);
		}
		public function get_row($q){
			$result = mysqli_query($this->link,$q);
			return mysqli_fetch_assoc($result);
		}
		public function count($q){
			$result = mysqli_query($this->link,$q);
			$count = mysqli_fetch_array($result);
			return $count[0];
		}
        public function query($q){
			return mysqli_query($this->link,$q);
		}
		public function escape($str){
			return mysqli_real_escape_string($this->link,$str);
		}
        public function get_row_prepared($sql, $params = []){
            $stmt = mysqli_prepare($this->link, $sql);
            if(!$stmt) return null;
            if(!empty($params)){
                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if($result){
                $row = mysqli_fetch_assoc($result);
                mysqli_free_result($result);
                mysqli_stmt_close($stmt);
                return $row;
            }
            mysqli_stmt_close($stmt);
            return null;
        }
        public function get_all_prepared($sql, $params = []){
            $stmt = mysqli_prepare($this->link, $sql);
            if(!$stmt) return [];
            if(!empty($params)){
                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            if($result){
                while($row = mysqli_fetch_assoc($result)){
                    $rows[] = $row;
                }
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmt);
            return $rows;
        }
        public function execute_prepared($sql, $params = []){
            $stmt = mysqli_prepare($this->link, $sql);
            if(!$stmt) return false;
            if(!empty($params)){
                $types = str_repeat('s', count($params));
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $ok;
        }
		public function affected(){
			return mysqli_affected_rows($this->link);
		}
		public function insert($q){
			if(mysqli_query($this->link,$q))
				return mysqli_insert_id($this->link);
			return false;
		}
		public function insert_array($table,$array){
			$q = "INSERT INTO `$table`";
			$q .=" (`".implode("`,`",array_keys($array))."`) ";
			$q .=" VALUES ('".implode("','",array_values($array))."') ";

			if(mysqli_query($this->link,$q))
				return mysqli_insert_id($this->link);
			return false;
		}
		public function error(){
			$error = mysqli_error($this->link);
			$errno = mysqli_errno($this->link);
			return '['.$errno.'] '.$error;
		}
		public function close(){
			$q = mysqli_close($this->link);
			return $q;
		}
	}
}
?>