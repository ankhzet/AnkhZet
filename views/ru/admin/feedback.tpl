<?php
	View::addKey('moder', '<span class="pull_right">[<a ' . View::$keys['inbox'] . ' href="/feedbacks/inbox">входящие</a> | <a' . View::$keys['trashbin'] . ' href="/feedbacks/trashbin">удаленные</a>]</span>');
?>
		<br />
		<div class="mail">
			<table class="list msg_list" cellpadding="0" cellspacing="0">
				<tr class="mail-head h"><td class="sender">Отправитель</td><td class="date">Дата</td></tr>
				{%messages%}
			</table>
		</div>
