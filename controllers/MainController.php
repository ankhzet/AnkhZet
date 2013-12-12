<?php

	class MainController extends Controller {
		protected $_name = 'main';

		public function actionMain($r) {
			$uid = User::get()->ID();
			View::addKey('rss-link', $uid ? "?channel=$uid" : "");

			return parent::actionMain($r);
		}
	}
?>