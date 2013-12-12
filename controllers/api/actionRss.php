<?php
	require_once 'core_rss.php';
	require_once 'core_updatesfetcher.php';

	class actionRss {
		function execute($params) {
			$time = time();
			$c = Config::read('INI', 'cms://config/config.ini');
			$data = array();
			$data['title'] = $c->get('site-title');
			$data['link'] = 'http://' . $_SERVER['HTTP_HOST'] . '/';
			$data['description'] = $data['title'];
			$data['generator'] = $c->get('rss-feeder');
			$data['lastbuilddate'] = date('r', $time);
			$data['self'] = "{$data['link']}rss.xml";

			msqlDB::o()->debug = post('debug');

			$fetch = UpdatesFetcher::aquireData($params, $title_opts);
			if (is_string($fetch))
				$this->_404($fetch);

			if (isset($title_opts)) {
				$t = array_merge($data, array('sub-channel' => htmlspecialchars(unhtmlentities($title_opts['title']))));
				$data['title'] = patternize(Loc::lget('rss-title' . $title_opts['channel']), $t);
				$data['self'] = "{$data['link']}rss.xml?{$title_opts['self']}";
			}

			$data['items'] = $fetch;

			$gzip = !post_int('nogzip');
			$debug = post_int('debug');
			if ($gzip) {
//				ob_start("ob_gzhandler");
//				$rss = gzcompress($rss);
				header('Content-Encoding: gzip');
			}

			switch (post('type')) {
			case 'json':
				require_once 'json.php';
				$rss = JSON_Result(JSON_Ok, $data, false);
				$content_type = "json";
				$file = "rss.json";
				break;
			default:
				$rss = RSSWorker::get();
				$rss = $rss->format($data);
				$content_type = "rss+xml";
				$file = "rss.xml";
			}

			if (!$debug)
			header("Content-Type: application/$content_type; charset=UTF-8");
			if (!$gzip)
			header('Content-Length: ' . strlen($rss));
			if (!$debug)
			header("Content-Disposition: inline; filename=$file");
//			$rss = ob_get_contents();

			header('Cache-Control: no-store; no-cache');

			if ($gzip) $rss = gzencode($rss);

			echo $rss;

//			if ($gzip)
//				ob_end_flush();

			die();
			return true;
		}
		function _404($text) {
			header('HTTP/1.0 404 Not Found');
			die($text);
		}

	}

	function unhtmlentities ($string) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		return strtr ($string, $trans_tbl);
	}
