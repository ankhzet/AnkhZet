<?php
	require_once '../base.php';
	require_once 'json.php';

	$resize = !intval($_REQUEST['noresize']);
	$i = pathinfo(strtolower($_FILES['img']['name']));
	$ext = $i['extension'];
	$copy = intval($_REQUEST['make_copy']) && ($ext == 'gif');

	if (User::ACL() < ACL::ACL_MODER) JSON_result(JSON_Fail, 'access denied');

	$n = $_REQUEST['name'];
	$name = ($n ? preg_replace('/\.(png|jpg|gif)$/i', '', $n) : 'tmp') . '.' . ($copy ? $ext : ($resize ? 'png' : 'jpg'));
	if (strpos($name, '/') === false) $name = '/data/tmp/' . $name;

	if (!isset($_FILES['img'])) JSON_result(JSON_Fail, 'file is\'nt uploaded');

	$file = $_FILES['img'];
	$info = @getimagesize($file['tmp_name']);
	if (!is_array($info)) JSON_result(JSON_Fail, 'not an image file');

	if ($copy) {
		@unlink(SUB_DOMEN . $name);
		if (move_uploaded_file($file['tmp_name'], SUB_DOMEN . $name))
			JSON_result(JSON_Ok, $name);
		else
			JSON_result(JSON_Fail, 'copy failed');
		die();
	}

	$cw = $w = $info[0];
	$ch = $h = $info[1];
	$s = $w / $h;

	$rw = intval($_REQUEST['width']);
	$rh = intval($_REQUEST['height']);

	if ($resize || ($rw || $rh)) {
		if ($rw || $rh) {
			if (!$rw) $rw = $rh * $s;
			if (!$rh) $rh = $rw / $s;
		}
		define(LOGO_WIDTH , $rw ? $rw : 165);
		define(LOGO_HEIGHT, $rh ? $rh : 49);
		if ($cw > LOGO_WIDTH) {
			$cw = LOGO_WIDTH;
			$ch = round($cw / $s);
		}
		if ($ch > LOGO_HEIGHT) {
			$ch = LOGO_HEIGHT;
			$cw = round($ch * $s);
		}
		$x = floor((LOGO_WIDTH - $cw) / 2);
		$y = floor((LOGO_HEIGHT - $ch) / 2);
	} else {
		define(LOGO_WIDTH , $cw);
		define(LOGO_HEIGHT, $ch);
		$x = $y = 0;
	}

	$img   = ImageCreateTrueColor(LOGO_WIDTH, LOGO_HEIGHT);
	$black = ImageColorAllocate($img, 0, 0, 0);
	$back  = ImageColorAllocate($img, 255, 255, 255);
	imagecolortransparent($img, $back);
	ImageFill($img, 0, 0, $back);


	$quality = 85;
	switch ($info[2]) {
	case 1: //gif
		$l = ImageCreateFromGIF($file['tmp_name']);
		break;
	case 2: //jpg
	case 10: //jpeg
		$l = ImageCreateFromJpeg($file['tmp_name']);
		$quality = 95;
		break;
	case 3: //png
		$l = ImageCreateFromPNG($file['tmp_name']);
		break;
	case 6: //bmp
		$l = ImageCreateFromBMP($file['tmp_name']);
		break;
	default:
		JSON_result(JSON_Fail, 'unkown format');
	}
	@unlink($file['tmp_name']);

	ImageCopyResampled($img, $l, $x, $y, 0, 0, $cw, $ch, $w, $h);

	@unlink(SUB_DOMEN . $name);
	if ($resize) {
//		imagetruecolortopalette($img, true, 256);
		imagepng($img, SUB_DOMEN . $name);
	} else {
		imageinterlace($img, 1);
		imagejpeg($img, SUB_DOMEN . $name, $quality);
	}

	JSON_result(JSON_Ok, $name);
?>

