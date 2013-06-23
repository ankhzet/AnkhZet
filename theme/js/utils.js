
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
		var link = $(this).attr('alt');
		if (link == "") return false;

		var trace = [];
		$('.multi [type="checkbox"]:checked').each(function() {
			var id = $(this).attr('value');
			trace.push(id);
		});

		$(this).attr('alt', '');
		$(this).css('color', '#999');
		$.post(link, {"id": trace, "silent": 1}, function(status, data) {
//			alert([status, data]);
			document.location.reload();
			$(this).attr('href', 'javascript:void(0)');
		});
		return false;
	});

	$('.multi-check').click(function() {
		var check = $(this).is(':checked');
		$('.multi [type="checkbox"]').each(function() {
			$(this).attr("checked", check ? "checked" : false);
		});
	});
});
