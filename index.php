<?php
// JD API OAuth Workflow
require "header.php";

// Saves settings to APICredentials.php
function saveSettings(){
	global $settings;
	$fd = fopen("APICredentials.php", "w");
	$data = '
<?php

$settings =
[
	"JDLINK_API_URL" => "'.$settings["JDLINK_API_URL"].'",
	"App_Key" => "'.$settings["App_Key"].'",
	"App_Secret" => "'.$settings["App_Secret"].'"
];

?>';
	fwrite($fd, $data);
	fclose($fd);
}

// If settings changed, update and save new settings
if(!empty($_POST["appKey"]) && !empty($_POST["appSecret"])){
	$settings["App_Key"] = $_POST["appKey"];
	$settings["App_Secret"] = $_POST["appSecret"];
	saveSettings();
} // end if

// OAuth automated verifier
// Replacing "oob" with an Internet-accessible callback page allows automation
// of the OAuth verifier.  See callback.php for an example callback page.
define("CALLBACK_URL", "oob");
//define("CALLBACK_URL", "https://example.com/callback.php");
$accessToken = NULL;
$authorizationURL = NULL;

// Get an access token if an OAuth verifier was passed
if(!empty($_POST["oauthVerifier"])){
	$oauth = new JDOAuth($settings["App_Key"], $settings["App_Secret"]);

	// Grab the URL for access tokens
    $accessTokenURL = "https://developer.deere.com/oauth/oauth10/token";

	// Set the request token and grab the access token
	$oauth->setToken($_POST['reqToken'], $_POST['reqSecret']);
	$accessToken = $oauth->getAccessToken($accessTokenURL, $_POST["oauthVerifier"]);

	// Save the access token
	$fd = fopen("savedToken.txt", "w");
	fwrite($fd, time().PHP_EOL.$accessToken['oauth_token'].PHP_EOL.$accessToken['oauth_token_secret']);
	fclose($fd);

	// Delete any remaining request token secrets
	if(file_exists("savedRequestToken.txt")){
        unlink("savedRequestToken.txt"); // Delete file
    } // end if

// If the flag for request tokens is set
} else if(!empty($_POST["getToken"])){
	// Delete saved token if it exists
	if(file_exists("savedToken.txt")){
        unlink("savedToken.txt"); // Delete file
    } // end if

	$oauth = new JDOAuth($settings["App_Key"], $settings["App_Secret"]);
    
    // Grab the URL for request tokens and base URL for authorization
    $requestTokenURL = "https://developer.deere.com/oauth/oauth10/initiate";
    $authorizationURL = "https://developer.deere.com/oauth/auz/authorize?oauth_token=";
    $callbackURL = "https://developer.deere.com/oauth/auz/grants/provider/authcomplete";

	// Get request token and append to base URL for authorization	
	$requestToken = $oauth->getRequestToken($requestTokenURL, $callbackURL);
    $authorizationURL .= $requestToken['oauth_token']; // also: $requestToken['oauth_token_secret'];

	// Save the request token secret, used by callback.php
	$fd = fopen("savedRequestToken.txt", "w");
	fwrite($fd, $requestToken["oauth_token_secret"]);
	fclose($fd);
} // end else if

// Attempt to load saved access tokens
$fd = file_exists("savedToken.txt") ? fopen("savedToken.txt", "r") : false;

if($fd == false){ // no token found
	$accessToken = "none";
} else if(time() - intval(fgets($fd)) > 60*60*24*350){ // expired token found (or nearly expired, 350 days)
	$accessToken = "expired";
} else { // valid token found
	$token = trim(fgets($fd)); // Remove \n from end
	$secret = trim(fgets($fd));
	
	if($token == "" || $secret == ""){
        $accessToken = "none";
    } else {
        $accessToken = ["oauth_token" => $token, "oauth_token_secret" => $secret];
    } // end else
} // end else
	
if($fd){
    fclose($fd);
} // end if

?>

<script>
// When "Change" is clicked for Application Credentials
function changeAPICredentials(){
	//$("#savedCredentials").slideUp("slow");
    document.getElementById("savedCredentials").style.display = "none";
	//$("#inputCredentials").css("display", "");
    document.getElementById("inputCredentials").style.display = "";
} // end function
</script>

<div class="page-title">OAuth Workflow</div>

	<div id="application-credentials"><b>Application Credentials</b><br>
<?php

// If app key or app secret isn't set, show form to set them
if($settings["App_Key"] == "" || $settings["App_Secret"] == ""){
	echo '
	No valid credentials found.
	<form action="index.php" method="post">
		<div id="inputCredentials">
		<table class="content">
			<tr>
				<td class="parameter">Enter app key:</td>
				<td><input type="text" name="appKey" size="60"></td>
			</tr>
			<tr>
				<td>Enter app secret:</td>
				<td><input type="text" name="appSecret" size="60"><td>
			</tr>
		</table>
		<button type="submit" value="Submit">Submit</button>
		</div>
	</form>';
}
// Otherwise, allow them to be changed (but not if there's an access token loaded)
else {
	echo '
	<div id="savedCredentials">
		<table class="content">
			<tr>
				<td class="parameter">App key:</td>
				<td>'.$settings['App_Key'].'</td>
			</tr>
			<tr>
				<td>App secret:</td>
				<td>'.$settings['App_Secret'].'<td>
			</tr>
        </table>';
    // Don't allow change if access token loaded
	echo gettype($accessToken) == 'array' ? '</div>': '<button onclick="changeAPICredentials();">Change</button></div>';
	
	// Form to change current app key and secret
	echo '
	<form action="index.php" method="post" id="inputCredentials" style="display:none">
		<table class="content">
			<tr>
				<td class="parameter">Enter app key:</td>
				<td><input type="text" name="appKey" value="'.$settings['App_Key'].'" size="60"></input></td>
			</tr>
			<tr>
				<td>Enter app secret:</td>
				<td><input type="text" name="appSecret" value="'.$settings['App_Secret'].'" size="60"></input><td>
			</tr>
		</table>
		<button type="submit" value="Submit">Submit</button>
	</form>';
} // end else
?>
	</div>

	<div id="oauth-access-token"><b>OAuth Access Token</b><br>
<?php
// If no app key or secret, delete any remaing access tokens
if($settings["App_Key"] == "" || $settings["App_Secret"] == ""){
	if(file_exists("savedToken.txt")){
        unlink("savedToken.txt");
    } // end if
	echo "Please set the application key and secret first.";
} // If there's a valid access token, display it
else if(gettype($accessToken) == "array"){
	echo '
	Token successfully loaded.
	<table class="content">
		<tr>
			<td class="parameter">Token:</td>
			<td>'.$accessToken['oauth_token'].'</td>
		</tr>
		<tr>
			<td>Secret:</td>
			<td id="token-secret">'.$accessToken['oauth_token_secret'].'</td>
		</tr>
	</table>
	<form action="index.php" method="post">
		<button name="getToken" type="submit" value="getToken">New Token</button>
	</form>';
} // If there's no valid access token or authorization URL, display the button to get new token
else if($authorizationURL == NULL){
	echo $accessToken == 'none' ? 'No token found.<br>' : 'Token expired.<br>';
	echo '
	<form action="index.php" method="post">
		<button name="getToken" type="submit" value="getToken">New Token</button>
	</form>';
} else { // If there's a no valid token, but authorization URL is set
	if(CALLBACK_URL == 'oob'){ // For no callback, show the form to enter verifier
		echo '
		Enter OAuth verifier from<br><a href="'.$authorizationURL.'" target="_blank">'.$authorizationURL.'</a>
		<form action="index.php" method="post">
			<table>
				<tr>
					<td class="parameter">Request token:</td>
					<td><input type="text" size="60" name="reqToken" value="'.$requestToken['oauth_token'].'" readonly></td>
				</tr>
				<tr>
					<td class="parameter">Request token secret:</td>
					<td><input type="text" size="60" name="reqSecret" value="'.$requestToken['oauth_token_secret'].'" readonly></td>
				</tr>
				<tr>
					<td class="parameter">OAuth verifier:</td>
					<td><input type="text" size="60" name="oauthVerifier"></input></td>
				</tr>
			</table>
			<button type="submit" value="Submit">Submit</button>
        </form>';
    } // end if
	else { // If there's a callback URL, just redirect to the authorization URL
        header("Location: $authorizationURL");
    } // end else
} // end else
?>
</div>

<div class="footer"></div>
