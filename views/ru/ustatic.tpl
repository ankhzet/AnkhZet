			<div class="content-header grip">{%grip%}</div>
			<div class="title content-header">{%title%}<?=(isset({%rss-link#var%}) && ($rssLink = {%rss-link#var%})) ? "<span class=\"pull_right\">[<a href=\"/rss.xml{$rssLink}\">RSS</a>]</span>" : ''?>{%hint%}{%moder%}</div>
			<div class="main-container">
{%stat-p1%}
{%content%}

{%stat-p2%}

			</div>
