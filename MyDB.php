<?php
/*
 * @ author <longbo>
 */
namespace common;

class MyDB
{
	
	protected $dsn = "";
	
	protected $debug = false;
	
	protected $logger = NULL;
	
	protected $pdo;
	
	protected $affected_rows = 0;
	
	protected $rs = array();
	
	protected $connectInfo = array();
	
	public $flagStringOnly = false;
	
	protected $isConnected = false;

	protected $prefix = "";
	
	public function __construct($dsn, $user = null, $pass = null, $prefix = null) {
		if ($dsn instanceof \PDO) {
			$this->pdo = $dsn;
			$this->isConnected = true;
			$this->pdo->setAttribute(1002, 'SET NAMES utf8');
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
			$this->dsn = $this->getDatabaseType();
		} else {
			$this->dsn = $dsn;
			$this->connectInfo = array('pass' => $pass, 'user' => $user);
		}
		$this->prefix = $prefix ? trim($prefix) .'_' : null;
	}
	
	public function connect() {
		if ($this->isConnected) return;
		try {
			$user = $this->connectInfo['user'];
			$pass = $this->connectInfo['pass'];
			$this->pdo = new \PDO(
				$this->dsn,
				$user,
				$pass,
				array(
					1002 => 'SET NAMES utf8',
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
				)
			);
			$this->pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
			$this->isConnected = true;
		} catch(\PDOException $e) {
			throw new \PDOException('Could not connect to database.');
		}
	}
	
	protected function bindParams($s, $aValues) {
		foreach($aValues as $key => &$value) {
			if (is_integer($key)) {
				if (is_null($value)){
					$s->bindValue($key+1, null, \PDO::PARAM_NULL);
				} elseif (!$this->flagStringOnly && self::canBeTreatedAsInt($value) && $value < 2147483648) {
					$s->bindParam($key+1, $value, \PDO::PARAM_INT);
				} else {
					$s->bindParam($key+1, $value, \PDO::PARAM_STR);
				}
			} else {
				if (is_null($value)){
					$s->bindValue($key, null, \PDO::PARAM_NULL);
				} elseif (!$this->flagStringOnly && self::canBeTreatedAsInt($value) &&  $value < 2147483648) {
					$s->bindParam($key, $value, \PDO::PARAM_INT);
				} else {
					$s->bindParam($key, $value, \PDO::PARAM_STR);
				}
			}
		}
	}
	
	protected function runQuery($sql, $aValues) {
		$this->connect();
		if ($this->debug && $this->logger) {
			$this->logger->log($sql, $aValues);
		}
		try {
			if (strpos('pgsql', $this->dsn) === 0) {
				$s = $this->pdo->prepare($sql, array(\PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT => true));
			} else {
				$s = $this->pdo->prepare($sql);
			}
			$this->bindParams($s, $aValues);
			$s->execute();
			$this->affected_rows = $s->rowCount();
			if ($s->columnCount()) {
		    	$this->rs = $s->fetchAll();
		    	if ($this->debug && $this->logger) $this->logger->log('resultset: '.count($this->rs).' rows');
	    	} else {
		    	$this->rs = array();
		  	}
		} catch (\PDOException $e) {
			$err = $e->getMessage();
			if ($this->debug && $this->logger) $this->logger->log('An error occurred: '.$err);
			echo 'ERROR:'.$err;
		}
	}

	protected function buildQuery($query = array(), $type = 'select') {
		
		switch ($type) {
			case 'select':
				return $this->buildSelect($query);
				break;
			case 'insert':
				return $this->buildInsert($query);
				break;
			case 'insert_ignore':
				return $this->buildInsert($query, $ignore = true);
				break;
			case 'update':
				return $this->buildUpdate($query);
				break;
			case 'replace':
				return $this->buildInsert($query, $ignore = false, $replace = true);
				break;
			case 'delete':
				return $this->buildDelete($query);
				break;
			default:
				return $this->buildSelect($query);
				break;
		}
	}

	protected function buildSelect($query = array()) {
		$sql[] = 'SELECT';
		if (is_array($query)) {
			isset($query['cols']) && is_array($query['cols']) ? $sql[] = implode(',', $query['cols']) : $sql[] .= '*';
			if (isset($query['table']) && !empty($query['table'])) {
				$sql[] = 'FROM';
				$sql[] = $this->table($query['table']);
			} else {
				return false;
			}
			if (isset($query['join']) && is_array($query['join'])) {
				in_array(trim($query['join'][0]), array('LEFT', 'RIGHT', 'UNION')) ? $sql[] = strtoupper($query['join'][0]) : $sql[] = 'LEFT';
				$sql[] = 'JOIN';
				$sql[] = $this->table($query['join'][1]);
				if (isset($query['on'])) {
					$sql[] = 'ON';
					$sql[] = $query['on'];
				} else {
					return false;
				}
			}
			if (isset($query['where'])) {
				$sql[] = 'WHERE';
				$sql[] = $query['where'];
			}
			if (isset($query['group'])) {
				$sql[] = 'GROUP BY';
				$sql[] = $query['group'];
			}
			if (isset($query['order'])) {
				$sql[] = 'ORDER BY';
				$sql[] = $query['order'];
			}
			if (isset($query['limit'])) {
				$sql[] = 'LIMIT';
				$sql[] = $query['limit'];
			}
			return implode(' ', $sql);
		} else {
			return false;
		}
	}

	protected function buildInsert($query = array(), $ignore = false, $replace = false) {
		$sql[] = $ignore ? 'INSERT IGNORE INTO' : ($replace ? 'REPLACE INTO' : 'INSERT INTO');
		if (is_array($query)) {
			if (isset($query['table']) && !empty($query['table'])) {
				$sql[] = $this->table($query['table']);
			} else {
				return false;
			}
			if (isset($query['cols']) && is_array($query['cols'])) {
				$sql[] = '(' . implode(',', $query['cols']) . ')';
				$sql[] = 'VALUES';
			} else {
				return false;
			}
			if (isset($query['values']) && is_array($query['values'])) {
				$scalar = 0;
				$values_arr = array();
				foreach ($query['values'] as $val) {
					if (is_array($val)) {
						$values_arr[] = '(' . implode(',', $val) . ')';
						++$scalar;
					} else {
						$values_arr[] = '(' . $val . ')';
					}
				}
				if ($scalar > 0) {
					$value_str = implode(',', $values_arr);
				} else {
					$value_str = '(' . implode(',', $query['values']) . ')';
				}
				$sql[] = $value_str;
			} else {
				return false;
			}
			return implode(' ', $sql);
		} else {
			return false;
		}
	}

	protected function buildUpdate($query = array()) {
		$sql[] = 'UPDATE';
		if (is_array($query)) {
			if (isset($query['table']) && !empty($query['table'])) {
				$sql[] = $this->table($query['table']);
			} else {
				return false;
			}
			$sql[] = 'SET';
			if (isset($query['set']) && is_array($query['set'])) {
				$update_arr = array();
				foreach ($query['set'] as $k => $v) {
					$update_arr[] = $k . '=' . $v;
				}
				$sql[] = implode(',', $update_arr);
			}
			if (isset($query['where'])) {
				$sql[] = 'WHERE';
				$sql[] = $query['where'];
			}
			return implode(' ', $sql);
		} else {
			return false;
		}
	}

	protected function buildDelete($query = array()) {
		$sql[] = 'DELETE FROM';
		if (is_array($query)) {
			if (isset($query['table']) && !empty($query['table'])) {
				$sql[] = $this->table($query['table']);
			} else {
				return false;
			}
			if (isset($query['where'])) {
				$sql[] = 'WHERE';
				$sql[] = $query['where'];
			}
			return implode(' ', $sql);
		} else {
			return false;
		}
	}

	protected function table($tname) {
		if (is_array($tname)) {
			$tb_arr = array();
			foreach ($tname as $value) {
				$t = explode(' ' , $value);
				if (count($t) > 1) {
					$t[0] = $this->prefix . $t[0];
					$tb = implode(' ', $t);
				} else {
					$tb = $this->prefix . $t[0];
				}
				$tb_arr[] = $tb;
			}
			return implode(',' , $tb_arr);
		} else {
			$t = explode(' ' , $tname);
			if (count($t) > 1) {
				$t[0] = $this->prefix . $t[0];
				$tb = implode(' ', $t);
			} else {
				$tb = $this->prefix . $t[0];
			}
			return $tb;
		}
		
	}
	
	public function getAll($query, $aValues = array()) {
		$sql = strval($this->buildQuery($query));
		$this->runQuery($sql, $aValues);
		return $this->rs;
	}
	
	public function getCol($sql, $aValues = array()) {
		$rows = $this->getAll($sql, $aValues);
		$cols = array();
		if ($rows && is_array($rows) && count($rows)>0) {
			foreach ($rows as $row) {
				$cols[] = array_shift($row);
			}
		}
		return $cols;
	}

	public function getCell($sql, $aValues = array()) {
		$arr = $this->getAll($sql, $aValues);
		$row1 = array_shift($arr);
		$col1 = array_shift($row1);
		return $col1;
	}

	public function getRow($sql, $aValues = array()) {
		$arr = $this->getAll($sql, $aValues);
		return array_shift($arr);
	}

	public function execQuery($sql, $aValues = array()) {
		$this->runQuery($sql, $aValues);
		return $this->affected_rows;
	}

	public function insert($data, $aValues = array(), $ignore = false) {
		$type = $ignore ? 'insert_ignore' : 'insert';
		$sql = strval($this->buildQuery($data, $type));
		$this->runQuery($sql, $aValues);
		return $this->affected_rows;
	}

	public function update($data, $aValues = array()) {
		$sql = strval($this->buildQuery($data, $type = 'update'));
		$this->runQuery($sql, $aValues);
		return $this->affected_rows;
	}

	public function replace($data, $aValues = array()) {
		$sql = strval($this->buildQuery($data, $type = 'replace'));
		$this->runQuery($sql, $aValues);
		return $this->affected_rows;
	}

	public function delete($data, $aValues = array()) {
		$sql = strval($this->buildQuery($data, $type = 'delete'));
		$this->runQuery($sql, $aValues);
		return $this->affected_rows;
	}

	public function getInsertID() {
		$this->connect();
		return (int) $this->pdo->lastInsertId();
	}

	public function affectedRows() {
		$this->connect();
		return (int) $this->affected_rows;
	}

	public function setDebugMode($tf, $logger = NULL) {
		$this->connect();
		$this->debug = (bool) $tf;
		if ($this->debug and !$logger) $logger = self::log();
		$this->setLogger($logger);
	}

	public function setLogger($logger) {
		$this->logger = $logger;
	}

	public function getLogger() {
		return $this->logger;
	}

	public function startTrans() {
		$this->connect();
		$this->pdo->beginTransaction();
	}

	public function commitTrans() {
		$this->connect();
		$this->pdo->commit();
	}

	public function rollBack() {
		$this->connect();
		$this->pdo->rollback();
	}

	public function getDatabaseType() {
		$this->connect();
		return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
	}

	public function getDatabaseVersion() {
		$this->connect();
		return $this->pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION);
	}

	public function getPDO() {
		$this->connect();
		return $this->pdo;
	}

	public function close() {
		$this->pdo = null;
		$this->isConnected = false;
	}

	public function isConnected() {
		if (!$this->isConnected && !$this->pdo) return false;
		return true;
	}

	public static function canBeTreatedAsInt($value) {
		return (boolean) (ctype_digit(strval($value)) && strval($value) === strval(intval($value)));
	}

	public static function log() {
	    if (func_num_args() > 0) {
	     	foreach (func_get_args() as $argument) {
	     		if (is_array($argument)) echo print_r($argument, true); else echo $argument;
				echo "<br>\n";
	     	}
	    }
	}
}

