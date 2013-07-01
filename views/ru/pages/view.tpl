				<div class="cnt-item">
					<span class="grammar-hint">
						Чтобы пометить опечатку, выделите ее мышью и нажмите [Ctrl + Enter].<br />
						Чтобы включить/выключить пометки нажмите [Ctrl + Shift + Enter].
					</span>
					<div class="text reader">
						{%preview%}
					</div>
				</div>
				<script>
					var
						text_old = ["{%h_old%}"]
					, text_new = ["{%h_new%}"]
					, grammar = [{%grammar%}]
					, admin = <?=intval($this->ctl->userModer)?>

					;
				</script>
