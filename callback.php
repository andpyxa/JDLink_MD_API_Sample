<?php
// JDLink Machine Data OAuth Workflow Sample Callback Page
// Pass the URL to this page as CALLBACK_URL, and Developer.Deere.com will redirect here
// This page posts the data back to index.php and removes the need to enter a verifier

// Load saved request token secret and delete it
$tokenSecret = file_get_contents("savedRequestToken.txt");
unlink("savedRequestToken.txt");
?>

<!-- Three parameters must be returned to index.php to obtain the access token
	 1. OAuth verifier - passed as a query parameter to this page
	 2. OAuth request token - passed as a query parameter to this page
	 3. OAuth token secret - loaded from file
-->
<form id="parameters" action="index.php" method="post">
	<input type="text" name="oauthVerifier" value="<?php echo $_GET["oauth_verifier"]; ?>" hidden>
	<input type="text" name="reqToken" value="<?php echo $_GET["oauth_token"]; ?>" hidden>
	<input type="text" name="reqSecret" value="<?php echo $tokenSecret; ?>" hidden>
</form>

<!-- Post the three parameters to index.php -->
<script> document.getElementById("parameters").submit(); </script>