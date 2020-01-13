<?php
// Error Page
// Redirect here if a PHP exception is thrown - shows error message, line, and trace
require "header.php";
?>

<div id="error-border">
	<div class="error">
		A PHP exception occurred<br>
		<?php echo urldecode($_POST["message"]); ?>
	</div>
	<br>
	<div class="container">
		At <?php echo urldecode($_POST["location"]); ?>.<br><br>
		<b>Trace</b><br>
		<?php echo urldecode($_POST["trace"]); ?>
	</div>
</div>