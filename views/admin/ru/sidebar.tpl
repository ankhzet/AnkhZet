	function sidebar_links() {
		$acl = User::ACL();
		$a = array(
			'/'
				=> array('index', Loc::lget('titles.main'), '_blank')
		, '/admin'
				=> array('admin', Loc::lget('titles.admin'), '')
		, '/feedbacks'
				=> array('feedbacks', Loc::lget('titles.feedbacks'), '')
		, '/share'
				=> array('share'  , Loc::lget('titles.share'), '')
		);
		$l = array();
		foreach ($a as $k => $t) {
			$key = trim($k);
			if (!ACL::allowed($acl, array($key))) continue;
			$l[] = $k
				? '<li class="mli' . $t[0] . '"><a href="'. $key . '"' . ($t[2] ? ' target="' . $t[2] . '"' : '') . '>' . $t[1] . '</a></li>'
				: '<li class="mli' . $t[0] . '">' . $t[1] . '</li>';
		}
		return clousureList('<ul>', '</ul>', $l, null, 1);
	}
