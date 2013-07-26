<?php
	require_once '../base.php';
	error_reporting(E_ALL);
	set_error_handler(null);

	class Watermark {
		static function listen() {
			$uri = $_SERVER['REQUEST_URI'];
			$file = SUB_DOMEN . str_replace('..', '', $uri);

			if (!@file_exists($file)) {
				header('HTTP/1.1 404 Not found');
				die();
			}

			self::putWatermark($file);
		}

		static function genWatermark($uid, $filename) {
			require_once 'core_qrcode.php';
			$uid ^= 0xFFFFFFFF;
			$b1 = ($uid >>  0) & 0xFF;
			$b2 = ($uid >>  8) & 0xFF;
			$b3 = ($uid >> 16) & 0xFF;
			$b4 = ($uid >> 24) & 0xFF;
			$bytes = array(rand(0, 255), rand(0, 255), $b1, rand(0, 255), $b2, $b3, $b4, rand(0, 255));
			$qrsize = 4;
			$cell_size = 4;
			$stored = ($qrsize * $qrsize - 4) * 8 - 8;
/**/
			$data = array();
			for ($i = 0; $i < $stored / 8; $i++)
				$data = array_merge($data, $bytes);
/**/
//			debug2($data, $stored);
			$qr = QRCode::get();
			$qr->gen($qrsize, $data);
			return $qr->render($cell_size, $filename);
		}

		static function openWatermark() {
			$uid = User::get()->ID();
			$wm_file = SUB_DOMEN . "/cache/watermarks/$uid.png";
			if (1 || !file_exists($wm_file))
				if (!self::genWatermark($uid, $wm_file))
					return false;

			return new Imagick($wm_file);
		}

		static function openImage($file) {
			$extension  = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));
			$src = new Imagick($file);
			return $src ? array($extension, $src) : null;
		}

		static function putWatermark($file) {
			$data = self::openImage($file);
			if (!$data) return false;

			$img = $data[1];
			$w = $img->getImageWidth();
			$h = $img->getImageHeight();

			$wm = self::openWatermark();
			if (!$wm) {
				header('HTTP/1.1 404 Not found');
				die();
			}


			$img->setImageDepth(8);
			$qrw = $wm->getImageWidth();
			$qrh = $wm->getImageHeight();

			$dx = $w / $qrw;
			$dy = $h / $qrh;
			for ($y = 0; $y < $dy; $y++)
				for ($x = 0; $x < $dx; $x++)
					$img->compositeImage($wm, imagick::COMPOSITE_DARKEN, $x * $qrw, $y * $qrh);

			$img->setImageCompression(Imagick::COMPRESSION_JPEG);
			switch ($data[0]) {
			case 'png':
				$img->setImageCompressionQuality(90); // bigger quality = smaller size
				break;
			default:
				$img->setImageCompressionQuality(70); // lesser quality = smaller size
			}
			header("Content-Type: image/$extension");
			echo $img;
			die();
		}
	}

	Watermark::listen();