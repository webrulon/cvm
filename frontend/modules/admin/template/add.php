<?php
/*
 * CVM is more free software. It is licensed under the WTFPL, which
 * allows you to do pretty much anything with it, without having to
 * ask permission. Commercial use is allowed, and no attribution is
 * required. We do politely request that you share your modifications
 * to benefit other developers, but you are under no enforced
 * obligation to do so :)
 * 
 * Please read the accompanying LICENSE document for the full WTFPL
 * licensing text.
 */

if(!isset($_APP)) { die("Unauthorized."); }

$sPageContents = NewTemplater::Render("{$sTheme}/admin/template/add", $locale->strings, array(
	"templates"	=> array("ubuntu.tar.gz", "fedora.tar.gz", "debian7.tar.gz", "opensuse.tar.gz")
));
