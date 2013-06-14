<?php

	require_once $_SERVER[DOCUMENT_ROOT] . '/_engine/dbengine.php';

	function _print($msg) {
		echo 'daemon&gt; ' . $msg . '<br>';
		flush();
	}

	function JSONResult($state, $data = '') {
		die( '{err: \'' . $state . '\'' . ($data ? ', msg: \'' . $data . '\'' : '') . '}' );
	}

	class Daemon {
		const LOCK_FILE = '/daemon.ini';
		const TBL_NAME  = '_daemon';

		const STOPPED   = 0;
		const STOPPING  = 1;
		const WORKING   = 2;

		const ITERATION_TIMEOUT = 1000000;

		static $inst = null;

		protected function __construct() {
			if ($t = $this->start())
				JSONResult('ok', 'Daemon executed for ' . $t . ' seconds');
			else
				JSONResult('already', 'Timestamp: ' . time());
		}

		static function run() {
			if (!isset(self::$inst))
				self::$inst = new self();
			return self::$inst;
		}

		function start() {
			$df = $_SERVER[DOCUMENT_ROOT] . self::LOCK_FILE;
			$f = @fopen($df, 'w');
			$result = false;
			if ($f) {
				$lock = flock($f, LOCK_EX | LOCK_NB);
				if ($lock) {
					$o = msqlDB::o();
					$o->delete(self::TBL_NAME);
					$s = $o->insert(self::TBL_NAME, array('started' => time(), 'working' => self::WORKING), true);
					$r = $s ? @mysql_fetch_row($s) : 0;
					$this->id = $r ? intval($r[0]) : 0;
					$t = time();
					$result = -1;
					set_time_limit(0);
					ignore_user_abort(1);
					while (@ob_end_clean()) {};
					$this->loop();
					$result = time() - $t;

					flock($f, LOCK_UN);
					self::stop(self::STOPPED);
				}
				fclose($f);
				if ($lock) unlink($df);
			}
			return $result;
		}

		static function stop($state = self::STOPPING) {
			$o = msqlDB::o();
			$s = $o->update(self::TBL_NAME, array('stopped' => time(), 'working' => $state), '`working` > ' . $state);
		}

		function loop() {
			while (self::state($this->id) == self::WORKING) {
				try {
					$this->executeTasks();
					$c = msqlDB::o();
					$c->close();
				} catch (Exception $e) {
					_print('Exception: ' . $e->getMessage());
				}
				usleep(self::ITERATION_TIMEOUT);
			}
		}

		static function lastID() {
			$o = msqlDB::o();
			$s = $o->select(self::TBL_NAME, ' 1', 'max(`id`) as `0`');
			$r = $s ? @mysql_fetch_row($s) : 0;
			return $r ? intval($r[0]) : 0;
		}

		static function locked() {
			$df = $_SERVER[DOCUMENT_ROOT] . self::LOCK_FILE;
			$f = @fopen($df, 'w');
			if ($f) {
				$locked = !flock($f, LOCK_EX | LOCK_NB);
				if (!$locked)
					flock($f, LOCK_UN);
				fclose($f);
				return $locked;
			} else
				return true;
		}

		static function state($id) {
			if ($id != self::lastID())
				return self::STOPPED;

			$o = msqlDB::o();
			$s = $o->select(self::TBL_NAME, '`id` = \'' . $id . '\'', '`working` as `0`');
			$r = $s ? @mysql_fetch_row($s) : 0;
			$running = $r ? intval($r[0]) : 0;
			if ($running)
				return self::locked() ? $running : false;
			else
				return self::locked();
		}

		function executeTasks() {

		}
	}

?>