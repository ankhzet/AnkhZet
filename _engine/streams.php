<?php
	class URIStream {
		var $fp;
		var $dp;
		var $path;
		var $real_path;
		public $context;
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

		static function real($path, &$real = null) {
			$url = parse_url($path);
			$host = $url["host"];
			$real = str_ireplace("cms://$host", self::$handlers[$host], $path);
			return $real;
		}

		function dir_opendir($path, $options) {
			self::real($path, $path);
			$this->dp = @opendir($path);
			return !!$this->dp;
		}

		function dir_closedir() {
			return @closedir($this->dp);
		}

		function dir_readdir() {
			return readdir($this->dp);
		}

		function dir_rewinddir() {
			return @rewinddir($this->dp);
		}

		function mkdir ($path, $mode, $options) {
			self::real($path, $path);
			return @mkdir(
			$path
			, $mode
			, ($options & STREAM_MKDIR_RECURSIVE) == STREAM_MKDIR_RECURSIVE
			);
		}

		function rmdir ($path, $options) {
			self::real($path, $path);
			return @rmdir($path);
		}

		function unlink ($path) {
			self::real($path, $path);
			return @unlink($path);
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

		function url_stat($path, $flags) {
			self::real($path, $path);
			$link = $flags & STREAM_URL_STAT_LINK;
			if (!$link)
				if (!(is_file($path) || is_dir($path)))
					return false;
				else;
			else
				if (!is_link($path))
					return false;
				else;

			return (!$link)
				? @stat($path)
				: @lstat($path);
		}

		function stream_stat() {
			return @fstat($this->fp);
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
	URIStream::register('logs', SUB_DOMEN . '/logs_dir');
	URIStream::register('config', SUB_DOMEN . '/_engine');
	URIStream::register('cache', SUB_DOMEN . '/cache');
	URIStream::register('temp', SUB_DOMEN . '/data/tmp');
