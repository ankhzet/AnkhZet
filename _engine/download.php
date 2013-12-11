<?php

	class PDW {
		const FMT_TXT     = 0x0000;
		const FMT_HTML    = 0x0001;

		const ENC_WIN1251 = 0x0000;
		const ENC_UTF8    = 0x0010;

		const FILE_PLAIN  = 0x0000;
		const FILE_ARCH   = 0x0100;

		var $zipdata   = '';
		var $directory = '';
		var $entries   = 0;
		var $file_num  = 0;
		var $offset    = 0;

		var $_text = null; // caching text contents

		var $FORMATS   = array(self::FMT_TXT => 'txt', self::FMT_HTML => 'html');
		var $CNTTYPES  = array(self::FMT_TXT => 'plain', self::FMT_HTML => 'html');
		var $ENCODINGS = array(self::ENC_WIN1251 => 'win-1251', self::ENC_UTF8 => 'utf-8');

		static function enumFormats($root, $filename, $title, $data) {
			$archived = array(self::FILE_PLAIN, self::FILE_ARCH);
			$formats = array(self::FMT_TXT, self::FMT_HTML);
			$encodings = array(self::ENC_WIN1251, self::ENC_UTF8);

			$w = array();
			$pdw = new self();
			$opts = array();
			foreach ($archived as $_a) {
				$opts[] = '<b>' . Loc::lget($_a == self::FILE_ARCH ? 'zipped' : 'notzipped') . '</b>';
				$a = array();
				foreach ($formats as $_f) {
					$f = array();
					foreach ($encodings as $_e) {
						$sample = isset($w[$_e][$_f])
							? $w[$_e][$_f]
							: $pdw->giveFile($title, $filename, $data, $_f | $_e, 0, false);
						if (!isset($w[$_e][$_f]))
							$w[$_e][$_f] = $sample;

						$format = $sample[2];
						$encoding = $sample[3];
						$size = fs($sample[0] + ( $_a ? 300 : 0));
						$name = $sample[1];

						$ext = strtoupper($pdw->FORMATS[$_f]);
						$archive = $_a == self::FILE_ARCH ? 'ZIP' : 'PLAIN';
						$f[] = "&nbsp; &darr; <a href=\"$root/$ext/$encoding/$archive?filename=$name\">$format, $encoding</a> <small>($size)</small>";
					}
					$a[] = join('<br />', $f);
				}
				$opts[] = join('<br />', $a);
			}
			return join('<br />', $opts);
		}

		static function archiveName($filename) {
			$i = pathinfo($filename);
			$e = $i['extension'];
			return basename($filename, $e) . 'zip';
		}

		function giveFile($title, $filename, $data, $options, $date = 0, $download = true) {
			$filename = preg_replace('/_+$/i', '', $filename);
			$_format   = ($options >> 0) & 0x00F;
			$_encoding = ($options >> 0) & 0x0F0;
			$_archive  = ($options >> 0) & 0xF00;

			$encoding = $this->ENCODINGS[$_encoding];

			switch ($_format) {
			case self::FMT_TXT:
				if ($this->_text)
					$data = $this->_text;
				else {
					$data = str_ireplace(array('</p>', PHP_EOL), "", strip_tags($data, '<br><p><dd>'));
					$data = str_ireplace(array('<br />', '<dd>', '<p>' ,'<p />'), PHP_EOL, $data);
					$data = preg_replace('/[ ]{2,}/', ' ', $data);
					$trans = get_html_translation_table(HTML_ENTITIES);
					$trans = array_flip($trans);
					$data = strtr($data, $trans);
					$data = rtrim(str_replace(PHP_EOL, "\r\n", strip_tags($data))) . "\r\n";
					$this->_text = $data;
				}
				break;
			case self::FMT_HTML:
				$data = str_replace("<br />", "<br />\r\n", $data);

				$p = 0;
				while (preg_match('|<img [^>]*(src=(["\']?))/[^\2>]+\2[^>]*(>)|i', substr($data, $p), $m, PREG_OFFSET_CAPTURE)) {
					$p += intval($m[1][1]);
					$u = $m[1][0] . 'http://samlib.ru';
					$data = substr_replace($data, $u, $p, strlen($m[1][0]));
					$p += strlen($u) + intval($m[3][1]) - intval($m[1][1]) - 5;
				}

				break;
			}

			switch ($_encoding) {
			case self::ENC_UTF8:
				$data = mb_convert_encoding($data, 'UTF-8', 'CP1251');
				$title =  mb_convert_encoding($title, 'UTF-8', 'CP1251');
				break;
			default:
			}

			$filename .= '.' . $this->FORMATS[$_format];

			switch ($_format) {
			case self::FMT_HTML:
				$pattern = file_get_contents('cms://views/htmlfile.tpl');
				$attribs = array(
					'title' => $title
				, 'charset' => $encoding
				, 'text' => $data
				);
				$data = patternize($pattern, $attribs);
				break;
			}

			switch ($_archive) {
			case self::FILE_ARCH:
				$data = $this->makeZip($filename, $data, $date);
				$filename = self::archiveName($filename);
				$mime = null;
				break;
			default:
				$mime = $this->CNTTYPES[$_format];
				$mime = "text/$mime; charset=$encoding";
				$data = @gzcompress($data);
			}


			return $download
			? $this->worker($filename, $data, $mime)
			: array(0 => strlen($data), 1 => $filename, 2 => $this->FORMATS[$_format], 3 => $encoding);
		}

		function makeZip($filename, $data, $page_date) {
			$this->zipdata   = '';
			$this->directory = '';
			$this->entries   = 0;
			$this->file_num  = 0;
			$this->offset    = 0;

			$date = getdate($page_date);
			$time['file_mtime'] = ($date['hours'] << 11) + ($date['minutes'] << 5) + $date['seconds'] / 2;
			$time['file_mdate'] = (($date['year'] - 1980) << 9) + ($date['mon'] << 5) + $date['mday'];

			$this->putFile($filename, $data, $time);

			$zip_data = $this->zipdata;
			$zip_data.= $this->directory."\x50\x4b\x05\x06\x00\x00\x00\x00";
			$zip_data.= pack('v', $this->entries); // total # of entries "on this disk"
			$zip_data.= pack('v', $this->entries); // total # of entries overall
			$zip_data.= pack('V', strlen($this->directory)); // size of central dir
			$zip_data.= pack('V', strlen($this->zipdata)); // offset to start of central dir
			$zip_data.= "\x00\x00"; // .zip file comment length

			return $zip_data;
		}

		function putFile($filepath, $data, $time) {
			$filepath = str_replace("\\", "/", $filepath);

			$uncompressed_size = strlen($data);
			$crc32  = crc32($data);

			$gzdata = gzcompress($data);
			$gzdata = substr($gzdata, 2, -4);
			$compressed_size = strlen($gzdata);

			$file_mtime = $time['file_mtime'];
			$file_mdate = $time['file_mdate'];

			$this->zipdata .=
				"\x50\x4b\x03\x04\x14\x00\x00\x00\x08\x00"
			. pack('v', $file_mtime)
			. pack('v', $file_mdate)
			. pack('V', $crc32)
			. pack('V', $compressed_size)
			. pack('V', $uncompressed_size)
			. pack('v', strlen($filepath)) // length of filename
			. pack('v', 0) // extra field length
			. $filepath
			. $gzdata; // "file data" segment

			$this->directory .=
				"\x50\x4b\x01\x02\x00\x00\x14\x00\x00\x00\x08\x00"
			. pack('v', $file_mtime)
			. pack('v', $file_mdate)
			. pack('V', $crc32)
			. pack('V', $compressed_size)
			. pack('V', $uncompressed_size)
			. pack('v', strlen($filepath)) // length of filename
			. pack('v', 0) // extra field length
			. pack('v', 0) // file comment length
			. pack('v', 0) // disk number start
			. pack('v', 0) // internal file attributes
			. pack('V', 32) // external file attributes - 'archive' bit set
			. pack('V', $this->offset) // relative offset of local header
			. $filepath;

			$this->offset = strlen($this->zipdata);
			$this->entries++;
			$this->file_num++;
		}

		static function worker($filename, $data, $mime = null) {
			@ob_end_clean();
			@ob_end_clean();
			$size = strlen($data);

			if ($mime != null) {
				header("Content-Type: $mime");
				header("Content-Encoding: gzip");
			} else {
				header("Content-Encoding:");
				header("Vary:");
				if (preg_match('"Opera(/| )([0-9].[0-9]{1,2})"', $_SERVER['HTTP_USER_AGENT']))
					$UserBrowser = "Opera";
				elseif (preg_match('"MSIE ([0-9].[0-9]{1,2})"', $_SERVER['HTTP_USER_AGENT']))
					$UserBrowser = "IE";
				else
					$UserBrowser = '';
//			important for download im most browser
				$mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ? 'octetstream' : 'octet-stream';
				header("Content-Type: application/$mime_type");
			}

			header("Content-Disposition: attachment; filename=\"$filename\"");
			header("Cache-control: private");
			header("Pragma: private");
			header("Expires: 0");

			header("Accept-Ranges: bytes");
			if(isset($_SERVER['HTTP_RANGE'])) {
				list($a, $range) = explode("=",$_SERVER['HTTP_RANGE']);
				list($from, $to) = explode("-",$range);
				$from = intval($from);
				$size2 = $size - 1;
				if ($to == '')
					$to = $size2;
				else
					$to = min(intval($to), $size2);

				$new_length = $to - $from + 1;
				header("HTTP/1.1 206 Partial Content");
				header("Content-Range: bytes $from-$to/$size");
				$size = $new_length;
				$data = substr($data, $from, $size);
			}
			header("Content-Length: " . $size);

//			die($data);
			$chunk = intval($size / 10);
			if ($chunk < 128) $chunk = $size;
			$pos = 0;
			while ($pos < $size) {
				$buf = min($size - $pos, $chunk);
				echo substr($data, $pos, $buf);
				flush();
				$pos += $buf;
//				sleep(1);
			}
			die();
		}
	}