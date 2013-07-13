<?php

	function escapeField($f) {
			return trim(addslashes(htmlspecialchars(strip_tags(post($f)))));
		}

	class FeedbackController extends Controller {
		protected $_name = 'feedback';

		public function actionMain($r) {
			$this->view->errors = array();
			$this->view->renderTPL('user/feedform');
		}

		public function actionSend($r) {
			// static type name phone msg
			$a = array('name', 'contact', 'msg');
			$e = array();
			foreach ($a as $key) {
				${$key} = escapeField($key);
				if (!${$key})
					$e[$key] = true;
			}

			require_once 'captcha.php';
			$c = new Captcha(addslashes(trim(post('rid'))));
			$captcha = trim(post('captcha'));
			if (!($captcha && $c->valid($captcha)))
				$e['captcha'] = true;

			if (!count($e)) {
				require_once 'core_mail.php';

				$m = new SA_Mail();
				$m->user = 0;
				$title = $name;
				$subject = join(', ', explode(PHP_EOL, $contact));
				$contents = $msg;

				$m->sendMsg($title, $subject, $contents);
				locate_to('/feedback/success');
				return;
			}
			$this->view->errors = $e;
			return $this->actionMain(null);
		}

		public function actionSuccess($r) {
			$this->view->renderMessage(Loc::lget('feedbacksuccess'), View::MSG_INFO);
		}

	}
?>