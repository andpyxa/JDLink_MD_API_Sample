<?php
// JDOAuth.php
// PHP 7 OAuth client customized for the JDLink Machine Data API

class JDOAuth
{
	private $ch; // The template curl handle for all requests
	private $consumer_key, $consumer_secret; // OAuth consumer key and secret
	private $token, $token_secret; // Stored OAuth token and token secret
	
	// Constructor - initializes the curl handle template (required)
	// @param $consumer_key -- OAuth app key
	// @param $consumer_secret -- OAuth consumer secret
	public function __construct(string $consumer_key, string $consumer_secret)
	{
		$this->ch = curl_init();
		
		$this->consumer_key = $consumer_key;
		$this->consumer_secret = $consumer_secret;

		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false); // If your server has certificates, you may remove this line
		curl_setopt($this->ch, CURLOPT_ENCODING, "gzip"); // Change to match your site's encoding
	} // end of function
	
	// Destructor - frees the curl handle
	public function __destruct()
	{
		if($this->ch)
			curl_close($this->ch);
	} // end of function

	// Access an OAuth protected resource with GET
	// @param $protected_resource_url -- base url of resource
	// @param $extra_parameters -- query parameters
	// @return the server response
	public function get(string $protected_resource_url, bool $return_headers = false, array $extra_parameters = [])
	{
		$ch = curl_copy_handle($this->ch); // Copy the curl handle template

		// Generate authorization headers, passing oauth_verifier as a "query parameter" so it's included in the signature
		// However, oauth_verifier must be in the authorization header
		$headers = ["Authorization: ".$this->generateAuthorizationHeaders($protected_resource_url, $extra_parameters)];
		$headers[0] .= ", Accept: application/xml"; // Add application/xml to header

		// Set the URL, headers; perform the HTTP request
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $protected_resource_url);
		$response = curl_exec($ch);
		
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		if(curl_error($ch))
			throw new Exception(curl_error($ch));
		curl_close($ch);

		// // Write to file - diagnostics, debug
		// $fp = fopen('Response_diagnostics.txt', 'a');
		// fwrite($fp, $httpCode.' at: '.date('Y-m-d H:i:s'));
		// fwrite($fp, "\n");
		// fclose($fp);

		if($return_headers){ // not using currently
			$header = substr($response, 0, $header_size);
			$body = substr($response, $header_size);
			return ["header" => $header, "body" => $body];
		} elseif($httpCode != 200){ // if not successful "http response 200", just return the error code
			return $httpCode;
		} else {
			return $response;
		} // end else
	} // end of function


	// Get OAuth request tokens
	// @param $request_token_url -- url of request tokens
	// @param $callback_url -- oauth_callback authorization parameter ("oob" = out of band)
	// @return array with keys "oauth_token" and "oauth_token_secret"
	// May return garbage on error
	public function getRequestToken(string $request_token_url, string $callback_url = "oob") : array
	{
		$ch = curl_copy_handle($this->ch); // Copy the curl handle template

		// Generate authorization headers, passing oauth_callback as a "query parameter" so it's included in the signature
		// However, oauth_callback must be in the authorization header
		$headers = ["Authorization: ".$this->generateAuthorizationHeaders($request_token_url, ["oauth_callback" => $callback_url])];
		$headers[0] .= ', oauth_callback="'.urlencode($callback_url).'"'; // Add oauth_callback to header

		// Set the URL, headers; perform the HTTP request
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $request_token_url);
		$response = explode("&", curl_exec($ch));

		if(curl_error($ch))
			throw new Exception(curl_error($ch));
		curl_close($ch);

		// The curl request returns in format "oauth_token=TOKEN&oauth_token_secret=SECRET&oauth_callback_confirmed=true"
		// Convert this to $request_token = TOKEN, $request_token_secret = SECRET
		$request_token = urldecode(explode("=", $response[0])[1]);
		$request_token_secret = urldecode(explode("=", $response[1])[1]);
		return ["oauth_token" => $request_token, "oauth_token_secret" => $request_token_secret];
	} // end of function

	// Get OAuth access tokens
	// @param $access_token_url -- url of access tokens
	// @param $auth_verifier -- verifier to exchange for access token
	// @return array with keys "oauth_token" and "oauth_token_secret"
	// 	 May return garbage on error.
	public function getAccessToken(string $access_token_url, string $auth_verifier) : array
	{
		$ch = curl_copy_handle($this->ch); // Copy the curl handle template

		// Generate authorization headers, passing oauth_verifier as a "query parameter" so it's included in the signature
		// However, oauth_verifier must be in the authorization header
		$headers = ["Authorization: ".$this->generateAuthorizationHeaders($access_token_url, ["oauth_verifier" => $auth_verifier])];
		$headers[0] .= ', oauth_verifier="'.$auth_verifier.'"'; // Add oauth_verifier to header

		// Set the URL, headers; perform the HTTP request
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $access_token_url);
		$response = explode("&", curl_exec($ch));
		
		if(curl_error($ch))
			throw new Exception(curl_error($ch));
		curl_close($ch);

		// The curl request returns in format "oauth_token=TOKEN&oauth_token_secret=SECRET"
		// Convert this to $access_token = TOKEN, $access_token_secret = SECRET
		$access_token = urldecode(explode("=", $response[0])[1]);
		$access_token_secret = urldecode(explode("=", $response[1])[1]);
		return ["oauth_token" => $access_token, "oauth_token_secret" => $access_token_secret];
	} // end of function


	// Set the token to use for requests
	// @params token and token secret
	public function setToken(string $token, string $secret)
	{
		$this->token = $token;
		$this->token_secret = $secret;
	} // end of function

	// Generates an OAuth authorization header for a request
	// @param $protected_resource_url -- the url of the request
	// @param $query_parameters -- extra parameters for the signature
	// @return OAuth header string; always returns successfully
	private function generateAuthorizationHeaders(string $protected_resource_url, array $query_parameters = []) : string
	{
		// $realm = "\"\""; // realm not used
		// Create the OAuth authorization header parameters
		$params = [
		//"realm" => $realm,  // realm not used
		"oauth_timestamp" => strval(time()),
		"oauth_nonce" => $this->generateNonce(),
		"oauth_consumer_key" => $this->consumer_key,
		"oauth_version" => "1.0",
		"oauth_signature_method" => "HMAC-SHA1" ];

		if(!empty($this->token))
			$params["oauth_token"] = $this->token;

		// Create the authorization headers from the parameters
		$headers = "OAuth ";
		foreach($params as $key => $value){
			$headers .= $key.'="'.$value.'", ';
		} // end foreach

		// Generate a signature, including any query parameters
		foreach($query_parameters as $key => $value){
			$params[urlencode($key)] = urlencode($value);
		} // end foreach

		$signature = $this->generateSignature($protected_resource_url, $params);

		// Append the signature to authorization headers
		$headers .= 'oauth_signature="'.$signature.'"';

		return $headers;
	} // end of function

	// Generates an OAuth HMAC-SHA1 signature
	// @param $url -- the url portion of the signature base string
	// @param $params -- parameters to append to the signature base string
	// @return OAuth signature string; always returns successfully
	private function generateSignature(string $url, array $params) : string
	{
		$url = str_replace(':443','',$url); // remove port number from URL for OAuth signature (required for http :80 or https :443)

		$http_method = 'GET'; // Default

		// Sort params alphabetically (required for OAuth hash)
		ksort($params);

		// Generate the signature base string
		$baseString = $http_method."&".rawurlencode($url)."&";
		foreach($params as $key => $value)
			$baseString .= rawurlencode($key.'='.$value."&");
		$baseString = rtrim($baseString, "%26"); // Remove extra "&" fromm end

		// Generate the signature signing key
		$signatureKey = rawurlencode($this->consumer_secret)."&";
		if(!empty($this->token_secret))
			$signatureKey .= rawurlencode($this->token_secret);

		// Hash the base string with the signature key, convert to base64
		$signature = base64_encode(hash_hmac("sha1", $baseString, $signatureKey, true));
		return urlencode($signature);
	} // end of function

	// Generates a nonce; unix timestamp concatenated with 10 char random string
	// This format gives 107 billion unique nonces per second
	// @return OAuth nonce
	private function generateNonce() : string
	{
		// return mt_rand(111111111,999999999); // just a random 9 digit
		$suffix = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
		return time().$suffix;
	} // end of function
} // end of class JDOAuth

?>