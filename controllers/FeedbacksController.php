<?php
	require_once 'common.php';
	require_once 'core_mail.php';

	class FeedbacksController extends Controller {
		protected $_name = 'feedbacks';

		const LIST_ITEM = '
			<tr class="{%un}read">
				<td class="sender">{%avtor}<span class="act">{%popup}</span></td>
				<td class="date">{%date}</td>
			</tr>';
		const MAIL_VIEW = '
			<tr class="{%un}read">
				<td class="sender">{%from}</td>
				<td class="date">{%date}</td>
			</tr>
			<tr>
				<td colspan=2>
					<div class="mail_content">
						<b>Контактные данные</b>:<br/><br/>
						<span>&nbsp; &nbsp; {%contacts}</span><br/><br/>
						<b>Сообщение</b>:<br/><br/>
{%content}
					</div>
				</td>
			</tr>';

		function reLink($href, $value, $ret = '') {
			return '<a href="' . $href . ($ret ? '?return=' . $ret : '') . '">' . $value . '</a>';
		}

		function performReturn() {
			$ret = str_replace(array('\'', '"'), '', post('return'));
			locate_to($ret);
		}

		function renderMsgs($mail, $f, $ret) {
			$l = explode(',', Loc::lget('mail'));

			$m = array();
			if (!count($f))
				$m[] = '<tr><td colspan=2>' . $l[6] . '</td></tr>';
			else
				foreach ($f as $r) {
					$id = intval($r['id']);
					$s = stripslashes($r['sender']);
					$u = stripslashes($r['subject']);
					$c = stripslashes($r['content']);
					$t  = $mail->messageState($r);

					$a1 = $t == 2 ? 0 : ($t == 1 ? 0 : 1);
					$a2 = $t != 2 ? 2 : -1;
					$popup  = $this->reLink('/feedbacks/domark/' . $id . '/' . $a1, $l[$a1], '/feedbacks/' . $ret);
					if ($a2 >= 0)
						$popup .= '<br>' . $this->reLink('/feedbacks/domark/' . $id . '/' . $a2, $l[$a2], '/feedbacks/' . $ret);
					$avtor = $this->reLink('/feedbacks/view/' . $id, safeSubstr($s . ' <span>' . $u . '</span>', 110));
					$date = date('d.m.Y', intval($r['date']));
					$data = array('avtor' => $avtor, 'popup' => $popup, 'date' => $date, 'un' => $a1 ? 'un' : '');

					$m[] = patternize(self::LIST_ITEM, $data);
				}

			View::addKey('messages', join('', $m));
			View::addKey('inbox', (strtolower($ret) != 'inbox') ? ' class="normal"' : ' class="bold"');
			View::addKey('trashbin', (strtolower($ret) != 'trashbin') ? ' class="normal"' : ' class="bold"');
			$this->view->renderTPL('admin/feedback');
		}

		public function actionInbox($r) {
			$m = new SA_Mail();
			$f = $m->fetchInbox();
			$this->renderMsgs($m, $f, 'inbox');
		}

		public function actionTrashbin($r) {
			$m = new SA_Mail();
			$f = $m->fetchDeleted();
			$this->renderMsgs($m, $f, 'trashbin');
		}

		public function actionView($r) {
			$l = explode(',', Loc::lget('mail'));
			$id = uri_frag($r, 0);

			$m = new SA_Mail();
			$f = $m->fetchMessage($id);
			if ($f) {
				$from = stripslashes($f['sender']);
				$cont = stripslashes($f['subject']);
//				$l2   = explode(',', Loc::lget('mail_view'));

				$date = date('d.m.Y', intval($f['date']));
				$content = stripslashes($f['content']);
				$data = array('from' => $from, 'contacts' => $cont, 'date' => $date, 'content' => $content);
				$msg = patternize(self::MAIL_VIEW, $data);

				$s = $m->messageState($f);
				if (($s != 1) && ($s != 2))               // if not message deleted
					$m->markMessage($id, 1); // mark message readed
			} else
				$msg = Loc::lget('Undefined MSGID');


			View::addKey('cavtor', $l[3]);
			View::addKey('cdate', $l[5]);
			View::addKey('messages', $msg);
			View::addKey('inbox', ' class="normal"');
			View::addKey('trashbin', ' class="normal"');
			$this->view->renderTPL('admin/feedback');
		}

		public function actionDomark($r) {
			$id = uri_frag($r, 0);
			$mark = uri_frag($r, 1);

			$m = new SA_Mail();
			$f = $m->markMessage($id, $mark);

			$this->performReturn();
		}

		public function actionMain($r) {
			return $this->actionInbox($r);
		}

		public function actionInit($r) {
			require_once 'core_mail.php';
			$m = new SA_Mail();

			if ($m->init_table())
				$this->view->renderMessage('Initialized', View::MSG_INFO);
			else
				$this->view->renderMessage('Initialization failed', View::MSG_ERROR);
		}

	}
?>