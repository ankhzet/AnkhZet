<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<atom:link href="{%self}" rel="self" type="application/rss+xml" />
		<title><![CDATA[{%title}]]></title>
		<link>{%link}</link>
		<description>{%description}</description>
		<lastBuildDate>{%lastbuilddate}</lastBuildDate>
		<generator>{%generator}</generator>
{%items}
	</channel>
</rss>