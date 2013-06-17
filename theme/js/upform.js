var
	upform = (typeof upform != 'undefined') ? upform : (new function () {
		var BTN_CLOSE = 000001;
		var div = null;
		this.buildCtls = function (controls) {
			if (!controls || !controls.length)
				return '';

			var p1 = '<a class="button" href="{%action}">{%caption}</a>';
			var c = [], p = null;

			for (var i in controls) {
				switch (p = controls[i]) {
				case upform.BTN_CLOSE:
					p = {"action": "javascript:upform.close()", "caption": "Закрыть"};
					break;
				}
				c.push((typeof p !== "string" ) ? patternize(p1, p) : p);
			}

			return c.join("");
		}
		this.init = function (params) {
			var title = params.title || '';
			var contents = params.content || '';
			var controls = params.controls || [upform.BTN_CLOSE];
			var onReady = params.onready || null;

			var back = document.createElement('DIV')
			back.className = "up-back";
			div = document.createElement('DIV')
			div.className = "up-form";
			div.innerHTML = '<div class="title"></div><div class="contents"></div><div class="ctls"></div>';
			$('body').append(back);
			$('body').append(div);

			this.setTitle(title);
			this.setContents(contents);
			this.setControls(controls);

			return onReady ? onReady(this) : this;
		}
		this.show = function(done) {
			$('.up-back').fadeIn('fast', function(){
				$('.up-form').fadeIn('fast', done);
			});
		}
		this.close = function() {
			$('.up-form').fadeOut('fast', function() {
				$('.up-back').fadeOut('fast',
					function(){$('.up-back, .up-form').each(function(){this.parentNode.removeChild(this)})}
				);
			});
		}
		this.setContents = function(contents) {
			$('.up-form .contents').html(contents);
		}
		this.setTitle = function(title) {
			$('.up-form .title').html(title);
			if (title)
				$('.up-form .title').show();
			else
				$('.up-form .title').hide();
		}
		this.setControls = function(controls) {
			$('.up-form .ctls').html(this.buildCtls(controls));
		}
	});


function show_error(title, contents) {
	upform.init({
	title: title
	, content: contents
	, onready: function(form) {
		form.show(function(){setTimeout(function(){form.close()}, 5000)})
	}
	});
}