
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
		this.TIMER_NOTES_QUEUE = 1;
		this.TIMEOUT_NOTES_QUEUE = 100;
		var sel, notIE = !!window.getSelection;
		var timers = [];
		var show = 1;

		this.clearTimer = function(timer) {
			if (t = timers[timer])
				clearTimeout(t);
			timers[timer] = 0;
		}
		this.setTimer = function(timer, timeout, callback) {
			this.clearTimer(timer);
			timers[timer] = setTimeout(function() {
				grammarNazzi.clearTimer(timer);
				callback(grammarNazzi);
			}, timeout);
		}
		this.init = function() {
			sel = notIE ? window.getSelection(): document.selection;
			window.addEventListener('keypress', function (e) {
				if (typeof grammar == 'undefined') return;
				switch (e.keyCode) {
				case 13:
					if (!e.ctrlKey) break;
					if (e.shiftKey) {
						if (show = !show)
							grammarNazzi.queueNotes();
						else
							grammarNazzi.removeGrammarNotes();
						break;
					}
					if (!grammarNazzi.getPage()) break;
					var s = grammarNazzi.getSelection();
					if (!s) return;
					var compilation = grammarNazzi.compileSelection(s);
					var text = s.toString();
					grammarNazzi.showForm(text, compilation);
				}
			}, false);
			if (show) this.queueNotes();
			$(document).resize(function() {
				if (show) grammarNazzi.queueNotes();
			});
		}
		this.getSelection = function() {
			return notIE ? (sel.rangeCount ? sel.getRangeAt(0) : false): sel.createRange();
		}
		this.removeGrammarNotes = function() {
			$('.grammar-highlight, .grammar-tip').remove();
		}
		this.queueNotes = function() {
			grammarNazzi.removeGrammarNotes();
			this.setTimer(this.TIMER_NOTES_QUEUE, this.TIMEOUT_NOTES_QUEUE, function(gn) {
				gn.makeGrammarNotes();
			});
		}
		this.makeGrammarNotes = function() {
			if (typeof grammar == 'undefined') return;
			var n = [], g = [];

			for (var i in grammar) {
				var o = this.takeOffset(grammar[i].range);
				if (!o) continue;
				var r = [], s = grammar[i].suggestions;
				for (var j in s)
					r.push('<div class="suggestion">' + s[j].r + '</div>');

				n[i] = o;
				g[i] = r;
			}


			var s = [], u = [], h = 0;
			for (var i in n) {
				h = n[i][0].bottom - n[i][0].top;
				s[i] = {"left": n[i][0].left - 12, "top": n[i][0].top};
				s[i].right = s[i].left + 14;
				s[i].bottom = s[i].top + h;
			}

			for (var i in s)
				for (var j in s)
					if (j != i)
						if (rangesIntersects(s[i], s[j]))
							if ($.isArray(u[i]))
								u[i].push(j);
							else
								u[i] = [j];

			var y = [], k = 0, y = [];
			for (var i in u)
				if (u[i]) {
					for(var l in u[i])
						y[u[i][l]] = y[u[i][l]] ? y[u[i][l]] + 1 : 1;

					for (var j in u) {
						var p = $.inArray(i, u[j]);
						if (p >= 0)
							u[j].splice(p, 1);
					}
				}

			for (var i in y)
				if (y[i]) {
					s[i].top += 14 * y[i];
					s[i].bottom += 14 * y[i];
				}

			for (var i in n)
				this.makeTip(grammar[i].range, g[i], n[i], s[i]);

			$('.grammar-tip').click(function () {grammarNazzi.highlite($(this).attr('range'));});
		}
		this.makeTip = function(range, suggestions, o, t) {
			var div = $(document.createElement('DIV'));
			var sy = $(document).scrollTop();
			$('body').append(div);
			div.html('<div class="text reader">' + suggestions.join('') + '</div>');
			div.css({left: t.left, top: t.top + sy});
			div.addClass('grammar-tip');
			div.attr('range', range);

			for (var i in o) {
				var rect = o[i];
				var high = $(document.createElement('DIV'));
				$('body').append(high);
				high.css({"top": rect.top + sy, "left": rect.left, "width": rect.right - rect.left, "height": rect.bottom - rect.top});
				high.addClass('grammar-highlight');
			}
		}
		this.compileSelection = function(rng) {
			var s = sc = rng.startContainer;
			var e = ec = rng.endContainer;
			while (s && !$(s).attr("node")) s = s.parentNode;
			while (e && !$(e).attr("node")) e = e.parentNode;
			var sn = (n = $(s).attr("node")) ? parseInt(n) : 0;
			var en = (n = $(e).attr("node")) ? parseInt(n) : 0;

			var textNodes = $('.cnt-item .text.reader')[0].childNodes;
			if (sn) {
				c = s.childNodes;
				for (var i = 0; i < c.length; i++)
					if (c[i] == sc) { sn += ':' + i; break; }
			} else
				for (var i = 0; i < textNodes.length; i++)
					if (textNodes[i] == sc) { sn += ':' + i; break; }
			if (en) {
				c = e.childNodes;
				for (var i = 0; i < c.length; i++)
					if (c[i] == ec) { en += ':' + i; break; }
			} else
				for (var i = 0; i < textNodes.length; i++)
					if (textNodes[i] == ec) { en += ':' + i; break; }

			return [sn, rng.startOffset, en, rng.endOffset];
		}
		this.highlite = function (range) {
			var offs = range.split(',');
			var sn = this.getNode(offs[0]), en = this.getNode(offs[2]);
			var so = offs[1], eo = offs[3];
			if (document.createRange) {
				var rng = document.createRange();
				rng.setStart(sn, so);
				rng.setEnd(en, eo);
				sel.removeAllRanges();
				sel.addRange(rng);
			} else
				;
		}
		this.takeOffset = function (range) {
			var offs = range.split(',');
			var sn = this.getNode(offs[0]), en = this.getNode(offs[2]);
			var so = offs[1], eo = offs[3];
			if (document.createRange) {
					var rng = document.createRange();
					rng.setStart(sn, so);
					rng.setEnd(en, eo);
					if (rng.getClientRects)
						return rng.getClientRects();

			} else
				;
			return null;
		}
		this.getNode = function(id) {
			id = id.split(':');
			var c = $('[node="' + id[0] + '"]');
			c = (c.length ? c : $('.cnt-item .text.reader'))[0].childNodes;
			return id[1] ? c[parseInt(id[1])] : c;
		}
		this.getPage = function() {
			var uri = document.location.href;
			var m = uri.match(/\/pages\/(version|diff)\/(\d+)/i);
			return m ? parseInt(m[2]) : 0;
		}
		this.getZone = function() {
			var uri = document.location.href;
			var m = uri.match(/\/pages\/((version\/\d+\/|diff\/\d+)[^$]+)/i);
			return m ? m[1] : null;
		}
		this.toggleButton = function(button, enabled) {
			if (enabled)
				$(button).removeClass('disabled').attr('disabled', false);
			else
				$(button).addClass('disabled').attr('disabled', 'disabled');
		}
		this.send = function(a) {
			if ($(a).attr('disabled') == 'disabled') return false;

			var code = $('.grammar-code').val();
			var text = $('.grammar-before').val();
			var replacement = $('.grammar-area').val();
			this.toggleButton(a, false);
			$('.grammar-area').attr('disabled', 'disabled');
			if (text == replacement) {
				alert('No changes =\\');
				$('.grammar-area').attr('disabled', false);
				this.toggleButton(a, true);
				return;
			}
			$.post('/api.php?action=grammar', {"page": this.getPage(), "zone": this.getZone(), "range": code, "replacement": replacement}
			, function(data, status) {
				if (status == 'success') {
					grammarNazzi.toggleButton(a, true);
					if (data.result == 'ok') {
						row = data.data;
						var i = {"range": row.range, "suggestions": [{"i": row.id, "u": row.user, "r": row.replacement}]};
						grammar.push(i);
						grammarNazzi.show = 1;
						grammarNazzi.queueNotes();
						upform.close();
						return;
					} else
						alert(data.data);
				} else
					alert('Request error');
				$('.grammar-area').attr('disabled', false);
			}, 'json');
		}
		this.showForm = function(text, code) {
			upform.init({
					title: 'Grammar Nazzi [' + code + ']!'
				, content: patternize(
					'<input type="hidden" class="grammar-code" value="{%code}"/>'
				+ '<textarea class="grammar-before" disabled="disabled">{%text}</textarea><br />'
				+ '<h4 style="padding-left: 1%;">Replace with:</h4>'
				+ '<textarea class="grammar-area">{%text}</textarea>'
				, {"text": text, "code": code})
				, controls: [
					{'caption': 'Ok', 'action': 'return grammarNazzi.send(this)'}
				, upform.BTN_CLOSE
				]
				, onready: function(form) { form.show(); }
			});
		}
		$(document).ready(function(){
			grammarNazzi.init();
			grammarNazzi.getZone();
		});
	}


function rangesIntersects(a, b) {
	return (a.left <= b.right &&
					b.left <= a.right &&
					a.top <= b.bottom &&
					b.top <= a.bottom)
}
