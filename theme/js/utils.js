
$(document).ready(function() {
	$('.pin').click(function() {
		var idx = $(this).attr('pin');
		var p = this.parentNode;
		var s = p.getElementsByTagName('SPAN')[0];
		var told = text_old[parseInt(idx) - 1];
		var tnew = text_new[parseInt(idx) - 1];
		var oldtext = p.className == 'old';
		p.className = oldtext ? 'new' : 'old';
		s.innerHTML = oldtext ? tnew : told;
	});

	$('.multi.link').click(function() {
		var a = this;
		if ($(a).attr('confirm')) {
			upform.init({
				title: 'Warning'
			, content: $(a).text() + '?'
			, controls: [
				{"caption": "Ok", "action": "return check_send(this)"}
			, upform.BTN_CLOSE
			]
			, onready: function(form) {
				$('a.button').attr('alt', $(a).attr('alt'));
				form.show(null);
			}
			});
			return;
		}
		return check_send(a);
	});

	$('.multi-check').click(function() {
		var check = $(this).is(':checked');
		$('.multi [type="checkbox"]').each(function() {
			$(this).attr("checked", check ? "checked" : false);
		});
	});

});

function check_send(a) {
	var link = $(a).attr('alt');
	if (link == "") return false;

	var trace = [];
	$('.multi [type="checkbox"]:checked').each(function() {
		var id = $(this).attr('value');
		trace.push(id);
	});

	$(a).attr('alt', '');
	$(a).css('color', '#999');
	$.post(link, {"id": trace, "silent": 1}, function(status, data) {
		document.location.reload();
		$(a).attr('href', 'javascript:void(0)');
	});
}
