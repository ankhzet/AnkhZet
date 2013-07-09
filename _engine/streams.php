<?php
	class URIStream {
		var $fp;
		var $path;
		var $real_path;
		private static $handlers = array();
		private static $registered = false;

		static function register($handler, $path) {
			if (!self::$registered)
				self::$registered = stream_wrapper_register("cms", get_class());

			self::$handlers[$handler] = $path;
		}

		function stream_open($path, $mode, $options, &$opened_path) {
			$this->real_path = self::real($path, $real_path);
			$this->path = $path;
			$this->fp = ($real_path != $path) ? fopen($real_path, $mode) : 0;
			return !!$this->fp;
		}

		static function real($path, &$real) {
			$url = parse_url($path);
			$host = $url["host"];
			$real = str_ireplace("cms://$host", self::$handlers[$host], $path);
			return $real;
		}

		function stream_read($count) {
			return fread($this->fp, $count);
		}

		function stream_write($data) {
			return fwrite($this->fp, $data);
		}

		function stream_tell() {
			return ftell($this->fp);
		}

		function stream_truncate($size) {
			return ftruncate($this->fp, $size);
		}

		function stream_eof() {
			return feof($this->fp);
		}

		function stream_close() {
			return fclose($this->fp);
		}

		function stream_seek($offset, $whence) {
			return fseek($this->fp, $offset, $whence);
		}

		function stream_lock($param) {
			return flock($this->fp, $param);
		}

		function url_stat($path) {
			self::real($path, $path);
			return @stat($path);
		}

		function stream_stat($path = null) {
			if (!isset($path)) $path = $this->path;
			self::real($path, $path);
			return @stat($path);
		}

		function stream_metadata($path, $option, $var) {
			self::real($path, $path);
			switch ($option) {
			case STREAM_META_TOUCH:
				return touch($path, $var[0], $var[1]);
			case STREAM_META_OWNER_NAME:
			case STREAM_META_OWNER:
				return chown($path, $var);
			case STREAM_META_GROUP_NAME:
			case STREAM_META_GROUP:
				return chgrp($path, $var);
			case STREAM_META_ACCESS:
				return chmod($path, $var);
			}
			return false;
		}
	}
 
	URIStream::register('root', SUB_DOMEN);
	URIStream::register('views', SUB_DOMEN . '/views');
	URIStream::register('logs', SUB_DOMEN . '/logs');
	URIStream::register('config', SUB_DOMEN . '/_engine');
