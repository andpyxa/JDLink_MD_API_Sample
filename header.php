<!DOCTYPE html>
<head>
<meta charset="UTF-8">
<title>JDLink API</title>
<link rel="stylesheet" type="text/css" href="API_styles.css">
</head>
<body>
<?php

date_default_timezone_set("America/Chicago"); // Timezone

require_once "APICredentials.php";
require_once "JDOAuth.php";

// Exception handler
// Redirects to the error page, where the exception message and trace are printed
// @param $e -- the thrown exception
function exception_handler(Throwable $e)
{
	$message = urlencode($e->getMessage());
	$location = urlencode("<b>".$e->getFile()."</b> line <b>".$e->getLine()."</b>");
	$trace = urlencode(nl2br($e->getTraceAsString()));

	echo "
	<form name='exception' action='error.php' method='post' hidden>
		<input name='message' value='$message'>
		<input name='location' value='$location'>
		<input name='trace' value='$trace'>
		<input type='submit'>
	</form>
	<script> document.exception.submit(); </script>";
}
set_exception_handler("exception_handler");

// Determine if a tab item should be highlighted
function boldLinks(string $link)
{
	echo strpos($_SERVER["REQUEST_URI"], $link) === FALSE ? "" : "selected-tab";
}
?>
<table class="content">
<tr>
	<td id="myjd-header">
		<a href="index.php">JDLink Machine Data API</a>
		<div id="myjd-subheader">Sample OAuth & Retrieval Code in PHP without external libraries</div>
	</td>
	<td>
	</td>
</tr>
<tr>
	<td colspan="2" id="separator-green">&nbsp;</td>
</tr>
<tr>
	<td colspan="2" id="separator-yellow">&nbsp;</td>
</tr>
</table>
<div id='tab-header'>
	<span class="header-item-left header-item"><a class="header-link <?php boldLinks("index.php"); ?>" href="index.php">OAuth</a></span>
	<span class="header-item"><a class="header-link <?php boldLinks("ListMachines.php"); ?>" href="ListMachines.php">Machines</a></span>
</div>

