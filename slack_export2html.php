<?php
#	slack_export2html	https://github.com/paijp/slack_export2html
#
#	Copyright (c) 2022 paijp
#
#	This software is released under the Apache 2.0 license.
#	http://www.apache.org/licenses/

if (@$srcdir !== null)
	;
else if (($srcdir = @$argv[1]) != "")
	;
else
	$srcdir = "export/";

if (@$dstdir !== null)
	;
else if (($dstdir = @$argv[2]) != "")
	;
else
	$dstdir = "html/";

date_default_timezone_set($tz = "UTC");

$users = json_decode(file_get_contents("{$srcdir}/users.json"));
$channels = json_decode(file_get_contents("{$srcdir}/channels.json"));
#print_r($users);
#print_r($channels);

$userlist = array();
foreach ($users as $a)
	$userlist[@$a->id] = @$a->name; 

$channellist = array();
foreach ($channels as $a)
	$channellist[@$a->id] = @$a->name;

#print_r($userlist);
#print_r($channellist);


function	escapetext($text)
{
	global	$userlist;
	global	$channellist;
	
	$slist = array(">", chr(0x22), chr(0x27));
	$dlist = array("&gt;", "&quot;", "&#039;");
	$ret = "";
	foreach (explode("<", $text) as $k0 => $v0) {
		if ($k0 == 0) {
			$ret .= str_replace($slist, $dlist, $v0);
			continue;
		}
		$a = explode(">", $v0, 2);
		$a0 = explode("|", @$a[0], 2);
		$url = $body = "";
		if (preg_match('/^http/', @$a0[0]))
			$url = str_replace($slist, $dlist, @$a0[0]);
		else if (preg_match('/^#/', @$a0[0]))
			$url = bin2hex($body = @$channellist[substr($a0[0], 1)]).".html";
		else if (preg_match('/^@/', @$a0[0]))
			$ret .= "<u>@".@$userlist[substr($a0[0], 1)]."</u>";
		else
			$ret .= "&lt;".str_replace($slist, $dlist, @$a[0])."&gt;";
		if ($url != "") {
			if ($body != "")
				;
			else if (@$a0[1] != "")
				$body = str_replace($slist, $dlist, @$a0[1]);
			else
				$body = $url;
			$ret .= '<a rel="noreferrer" referrerpolicy="no-referrer" target="_blank" href="'.$url.'">'."{$body}</a>";
		}
		$ret .= str_replace($slist, $dlist, @$a[1]);
	}
	return $ret;
}


$s = "<h2>".date("y/m/d")."</h2>\n\n";
$s .= "<ul>\n";
foreach ($channellist as $channelname)
	$s .= '<li><a href="'.bin2hex($channelname).'.html">'.htmlspecialchars($channelname)."</a>\n";
$s .= "</ul>\n\n";

file_put_contents("{$dstdir}/index.html", $s, FILE_APPEND);

foreach ($channellist as $channelname) {
	fprintf(STDERR, "channel(%s)\n", htmlspecialchars($channelname));
	$out = "";
	foreach (glob("{$srcdir}/{$channelname}/*") as $fn) {
		$out .= "<h3>".htmlspecialchars($fn)."</h3>\n";
		$messagelist = json_decode(file_get_contents($fn));
#print_r($a);
#die();
		foreach ($messagelist as $message) {
			if (($obj = @$message->root) !== null) {
				$out .= '<blockquote><a href="#'.str_replace(".", "", @$obj->ts).'">thread</a>: <span style="color:#aaa;">'.escapetext(@$obj->text).'</span>';
			}
			$out .= '<p><a name="'.str_replace(".", "", @$message->ts).'">';
			$out .= htmlspecialchars(@$userlist[@$message->user].@$message->username).'</a> <span style="color:#aaa;">';
			$out .= date("y/m/d H:i:s", @$message->ts)." {$tz}</span></p>\n";
			$out .= "<blockquote>".nl2br(escapetext(@$message->text));
			if (($filelist = @$message->files) !== null) {
				$out .= "<ul>\n";
				foreach ($filelist as $obj) {
					if (!preg_match('!^https?://files.slack.com/!', $url = $obj->url_private_download))
						continue;		# avoid local file access like '/etc/passwd'.
#					$ext = "bin";
#					if (preg_match('/[.]([0-9A-Za-z]{1-4})$/', @$obj->name, $a))
#						$ext = $a[1];
					fprintf(STDERR, "download(%s)\n", htmlspecialchars(@$obj->name));
					$s = file_get_contents($url);
					sleep(2);
					if ($s == "")
						continue;
					$hash = sha1($s);
					file_put_contents("{$dstdir}/{$hash}", $s);
					
					if (!preg_match('!^https?://files.slack.com/!', $url = @$obj->thumb_480))
						;		# avoid local file access like '/etc/passwd'.
					else if (preg_match('/[.](png|jpg)[?]/', $url, $a)) {
						$s = file_get_contents($url);
						sleep(2);
						if ($s != "") {
							file_put_contents("{$dstdir}/{$hash}.".$a[1], $s);
							$out .= '<li><a href="'.$hash.'" type="';
							$out .= htmlspecialchars(@$obj->mimetype, ENT_QUOTES).'" download="';
							$out .= htmlspecialchars(@$obj->name, ENT_QUOTES).'">';
							$out .= '<img src="'.$hash.'.'.$a[1].'" alt="';
							$out .= htmlspecialchars(@$obj->title, ENT_QUOTES).'" /></a>'."\n";
							continue;
						}
					}
					$out .= '<li><a href="'.$hash.'" type="';
					$out .= htmlspecialchars(@$obj->mimetype, ENT_QUOTES).'" download="';
					$out .= htmlspecialchars(@$obj->name, ENT_QUOTES).'">';
					$out .= @$obj->title."</a>\n";
				}
				$out .= "</ul>\n";
			}
			if (($attachlist = @$message->attachments) !== null)
				foreach ($attachlist as $obj) {
					$out .= "<blockquote>".nl2br(htmlspecialchars(@$obj->fallback."\n"));
					$s = '<span style="color:#aaa;">'.htmlspecialchars(@$obj->footer);
					$s .= "</span></blockquote>\n";
					if (preg_match('!/archives/([0-9A-Za-z]+)/p([0-9]+)!', @$obj->from_url, $a)) {
						$out .= '<a href="'.bin2hex(@$obj->channel_name).'.html#'.$a[2].'">';
						$out .= "{$s}</a>";
					} else
						$out .= $s;
				}
			$out .= "</blockquote>\n\n";
			if (($obj = @$message->root) !== null)
				$out .= "</blockquote>\n";
		}
		
		
	}
	$out .= "<hr />\n";
	file_put_contents("{$dstdir}/".bin2hex($channelname).".html", $out, FILE_APPEND);
#print $out;
#die();
	
}

