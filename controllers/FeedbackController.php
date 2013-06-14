<?php

	function escapeField($f) {
			return trim(addslashes(htmlspecialchars(strip_tags($_POST[$f]))));
		}

	class FeedbackController extends Controller {
		protected $_name = 'feedback';

		public function actionMain($r) {
			return parent::actionMain(array('about-us'));
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
			$c = new Captcha(addslashes(trim($_REQUEST[rid])));
			if (!(($captcha = trim($_REQUEST[captcha])) && $c->valid($captcha)))
				$e[captcha] = true;

			if (!count($e)) {
				require_once 'core_mail.php';

				$m = new SA_Mail();
				$m->user = 0;
				$title = $name;
				$subject = join(', ', explode(PHP_EOL, $contact));
				$contents = $msg;

				$m->sendMsg($title, $subject, $contents);
				header('Location: /feedback/success');
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