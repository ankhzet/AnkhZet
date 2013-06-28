
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

var
	grammarNazzi = new function () {
		var sel, notIE = !!window.getSelection;
		var textNodes = [];

		this.init = function() {
			sel = notIE ? window.getSelection(): document.selection;
			textNodes = $('.main-container')[0].childNodes;
			this.makeGrammarNotes();
			$('.grammar div').click(function () {
				var range = $(this).attr('range');
				grammarNazzi.highlite([range]);
			});
		}
		this.getSelection = function() {
			return notIE ? (sel.rangeCount ? sel.getRangeAt(0) : false): sel.createRange();
		}
		this.makeGrammarNotes = function() {

		}
		this.compileSelection = function(rng) {
			var s = sc = rng.startContainer;
			var e = ec = rng.endContainer;
			while (s && !$(s).attr("node")) s = s.parentNode;
			while (e && !$(e).attr("node")) e = e.parentNode;
			var sn = (n = $(s).attr("node")) ? parseInt(n) : 0;
			var en = (n = $(e).attr("node")) ? parseInt(n) : 0;

			if (sn) {
				c = s.childNodes;
				for (var i = 0; i < c.length; i++)
					if (c[i] == sc) {
						sn += ':' + i;
						break;
					}
			} else
				for (var i = 0; i < textNodes.length; i++)
					if (textNodes[i] == sc) {
						sn += ':' + i;
						break;
					}
			if (en) {
				c = e.childNodes;
				for (var i = 0; i < c.length; i++)
					if (c[i] == ec) {
						en += ':' + i;
						break;
					}
			} else
				for (var i = 0; i < textNodes.length; i++)
					if (textNodes[i] == ec) {
						en += ':' + i;
						break;
					}

			return [
				sn
			, rng.startOffset
			, en
			, rng.endOffset];
		}
		this.highlite = function (patts) {
			var rngs = [];
			$(patts).each(function () {
				var offs = this.split(',');
				var sn = grammarNazzi.getNode(offs[0]), en = grammarNazzi.getNode(offs[2]);
				var so = offs[1], eo = offs[3];

				if (document.createRange) {
					var rng = document.createRange();
					rng.setStart(sn, so);
					rng.setEnd(en, eo);
					rngs.push(rng);
				} else
					;
			});

			sel.removeAllRanges();
			$(rngs).each(function () { sel.addRange(this) });
		}
		this.getNode = function(id) {
			if (id[0] == ':') return textNodes[parseInt(id.replace(':', ''))];

			id = id.split(':');
			var c = $('[node="' + id[0] + '"]')[0].childNodes;
			return c[parseInt(id[1])];
		}
		this.getPage = function() {
			var uri = document.location.href;
			var m = uri.match(/\/pages\/(id|diff)\/(\d+)/i);
			return m ? parseInt(m[2]) : 0;
		}
		this.send = function(a) {
			var code = $('.grammar-code').val();
			var text = $('.grammar-before').val();
			var replacement = $('.grammar-area').val();
			if (text == replacement) {
				alert('No changes =\\');
				return;
			}
			$.post('/api.php?action=grammar', {"page": this.getPage(), "range": code, "replacement": replacement}
			, function(status, data) {
				upform.close();
				alert([status, data]);
			});
		}
		$(document).ready(function(){
			grammarNazzi.init();
			window.addEventListener('keypress', function (e) {
				switch (e.keyCode) {
				case 13:
					if (!e.ctrlKey) break;
					if (!grammarNazzi.getPage()) break;
					var s = grammarNazzi.getSelection();
					if (!s) return;
					var compilation = grammarNazzi.compileSelection(s);
					var text = s.toString();
					upform.init({
						title: 'Grammar Nazzi!'
					, content: patternize(
						'<input type="hidden" class="grammar-code" value="{%code}"/>'
					+ '<textarea class="grammar-before" disabled="disabled">{%text}</textarea><br /><br />'
					+ 'Replace with:<br />'
					+ '<textarea class="grammar-area">{%text}</textarea>'
					, {"text": text, "code": compilation})
					, controls: [
						{'caption': 'Ok', 'action': 'return nodeWorker.send(this)'}
					, upform.BTN_CLOSE
					]
					, onready: function(form) { form.show(); }
					});
				}
			}, false);
		});
	}

