
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
});
